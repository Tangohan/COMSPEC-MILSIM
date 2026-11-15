<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\Game\GameSseAlertesApiController;
use App\Core\Request;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAlertGameService;
use App\Services\Sse\SseAlertProposals;
use App\Services\Sse\SseAlertService;
use App\Services\Sse\SseFichesGameService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseWantedAccess;
use App\Support\ComspecApiKeyAuth;
use App\Support\SseAlertLevel;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAlertSqlite;

/**
 * Alertes SSE du téléphone ATAK (ICD-SSE-ALERTES-001) : catalogue, liste, détail, émission (active ou proposée selon les droits du
 * site), signalement, renouvellement, levée, accusé de lecture ; idempotence par identifiant client ; filtre par l'habilitation
 * (une alerte hors habilitation est une référence inconnue, partout) ; jamais l'identité d'un membre ; pages de la taille d'un
 * retour de COMSPEC Link ; mode dégradé sans les tables de l'étape 4830 ; formes des réponses du contrat.
 *
 * Comptes : 7 Encadrement (gère « fichiers », émet), 8 Confidentiel (écrit dans « fichiers », ne gère pas), 9 Très restreint sans
 * droit d'écrire, 11 plancher sans droit d'écrire.
 */
final class SseAlertGameTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<array<string, mixed>> lignes envoyées au journal d'audit */
    private array $audited = [];

    /** @var list<array{0: int, 1: int, 2: array<string, mixed>}> notifications du site */
    private array $notified = [];

    /** @var list<array{0: string, 1: int, 2: int}> appels du plafond de débit */
    private array $rates = [];

    /** @var list<string> préfixes de clés de débit à refuser */
    private array $trip = [];

    private int $seq = 0;

    protected function setUp(): void
    {
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER, 'env' => $_ENV];
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->open(true);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_ENV['APP_URL'] = 'https://athena.example';
        $_SERVER['HTTP_HOST'] = 'athena.example';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_IF_NONE_MATCH']);
        ComspecApiKeyAuth::resetForTests();
        $this->audited = [];
        $this->notified = [];
        $this->rates = [];
        $this->trip = [];
        $this->seq = 0;
    }

    protected function tearDown(): void
    {
        SseAlertSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        $_ENV = $this->saved['env'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        ComspecApiKeyAuth::resetForTests();
    }

    private function open(bool $withAlertTables): void
    {
        $this->pdo = SseAlertSqlite::open($withAlertTables);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $this->pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, steam_id TEXT, access_token_hash TEXT, expires_at TEXT, revoked_at TEXT)');
    }

    // ------------------------------------------------------------------ Montage

    private function alertService(): SseAlertService
    {
        return new SseAlertService(null, null, null, function (int $tenant, int $user, array $payload): void {
            $this->notified[] = [$tenant, $user, $payload];
        }, static fn (int $tenant): array => [7, 8]);
    }

    private function service(): SseAlertGameService
    {
        return new SseAlertGameService(null, static fn (int $tenant, int $user): string => match ($user) {
            7 => 'encadrement', 8 => 'confidentiel', 9 => 'tres_restreint', default => 'interne',
        }, $this->alertService());
    }

    private static function access(int $tenant, int $user, string $level): SseSheetAccess
    {
        $manage = in_array($user, [7, 8], true);
        $wanted = new SseWantedAccess(false, $manage, $manage, true, $tenant, $user, 'Téléphone ATAK', true, $user === 7);

        return new SseSheetAccess($wanted, static fn (string $section, string $right): bool => $manage || $right === 'read', true, $level);
    }

    private function proposals(?SseAlertService $alerts = null): SseAlertProposals
    {
        return new SseAlertProposals($alerts ?? $this->alertService(), null, null, null, null, function (int $tenant, int $user, array $payload): void {
            $this->notified[] = [$tenant, $user, $payload];
        }, static fn (int $tenant): array => [7, 8], function (int $tenant, ?int $user, string $label, string $action, int $id, string $reason, array $after): void {
            $this->audited[] = ['action' => $action, 'object_type' => 'sse_alert_proposal', 'object_id' => $id, 'actor_user_id' => $user, 'reason' => $reason, 'after' => $after];
        });
    }

    private function controller(?string $maintenance = null, ?string $body = null): GameSseAlertesApiController
    {
        $service = $this->service();

        return new GameSseAlertesApiController(
            $service,
            function (string $key, int $max, int $window): bool {
                $this->rates[] = [$key, $max, $window];

                return in_array(explode('|', $key)[0], $this->trip, true);
            },
            static fn (): ?string => $maintenance,
            static fn (): ?string => 'interne',
            function (array $row): void {
                $this->audited[] = $row;
            },
            static fn (int $tenant, int $user, string $level): SseSheetAccess => self::access($tenant, $user, $level),
            $this->proposals($service->alerts()),
            null,
            static fn (): string => (string) $body,
        );
    }

    private function gameToken(int $tenant, int $user): void
    {
        $token = str_repeat('c', 58) . sprintf('%02d%04d', $tenant, $user);
        if ((int) $this->pdo->query("SELECT COUNT(*) FROM game_sessions WHERE access_token_hash = '" . hash('sha256', $token) . "'")->fetchColumn() === 0) {
            $st = $this->pdo->prepare('INSERT INTO game_sessions (tenant_id, user_id, access_token_hash, expires_at) VALUES (?, ?, ?, ?)');
            $st->execute([$tenant, $user, hash('sha256', $token), '2099-01-01 00:00:00']);
        }
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        ComspecApiKeyAuth::resetForTests();
    }

    /**
     * Une opération par le canal « kind », au nom d'un compte.
     *
     * @param array<string, mixed> $body
     * @return array{0: int, 1: array<string, mixed>, 2: list<mixed>} statut, réponse JSON, page relue comme le jeu (vide en cas d'erreur)
     */
    private function call(int $user, array $body, int $tenant = 1): array
    {
        $this->gameToken($tenant, $user);
        $resp = $this->controller()->kindResponse(['kind' => SseAlertGameService::KIND] + $body);
        $data = (array) json_decode($resp->body(), true);

        return [$resp->statusCode(), $data, $resp->statusCode() === 200 ? self::parse((string) $data['ref']) : []];
    }

    /** @return list<mixed> ce que le jeu relit : même forme que parseSimpleArray. */
    private static function parse(string $ref): array
    {
        self::assertStringNotContainsString('|', $ref);
        self::assertStringNotContainsString("\n", $ref);
        self::assertLessThanOrEqual(SseFichesGameService::PAGE_BYTES, strlen($ref));
        $json = preg_replace_callback('/"((?:[^"]|"")*)"/', static fn (array $m): string => (string) json_encode(str_replace('""', '"', $m[1]), JSON_UNESCAPED_UNICODE), $ref);
        $rows = json_decode((string) $json, true);
        self::assertIsArray($rows, $ref);

        return $rows;
    }

    /** @param list<mixed> $row @return array<string, mixed> */
    private static function named(array $row): array
    {
        self::assertCount(count(SseAlertGameService::COLUMNS), $row);

        return array_combine(SseAlertGameService::COLUMNS, $row);
    }

    // ------------------------------------------------------------------ Jeu de données

    private function identity(int $tenant, string $last, string $first, ?int $userId = null, string $alias = '', ?int $personId = null): int
    {
        return (new SseIdentityRepository())->create($tenant, [
            'last_name' => $last, 'first_name' => $first, 'kind' => 'civil', 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK',
            'birth_place' => 'Pyrgos', 'alias' => $alias !== '' ? $alias : null, 'user_id' => $userId, 'person_id' => $personId,
        ]);
    }

    private function person(int $tenant, string $last, string $first, string $subjectId = ''): int
    {
        $cols = ['tenant_id' => $tenant, 'last_name' => $last, 'first_name' => $first, 'alias' => 'Le Chauffeur', 'sex_apparent' => 'M', 'age_estimated' => 41, 'birth_date' => '1984-03-12',
            'birth_place' => 'Kavala', 'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est', 'distinguishing_marks' => 'Cicatrice à la lèvre',
            'grid_reference' => '072 118', 'location_description' => 'Pyrgos, marché couvert', 'subject_id' => $subjectId !== '' ? $subjectId : null,
            'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 14:32:00'];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $o @return array{id: int, ref: string} */
    private function notice(int $tenant, int $identity, array $o = []): array
    {
        $ref = $o['ref'] ?? sprintf('A-%04d/2026', ++$this->seq);
        $status = $o['status'] ?? 'active';
        $st = $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, reason, instructions, zone, last_seen_place, last_seen_at,
            authority, contact_label, contact_detail, status, active_key, issued_at, expires_at, closed_at, closed_reason, closed_by_user_id, created_at, updated_at)
            VALUES (:t, :i, :ref, 2026, :seq, :type, :threat, :armed, :reason, :instr, :zone, :place, :seen, :auth, :cl, :cd, :status, :ak, '2026-11-01 08:00:00', :exp, :closed, :cr, :cb, '2026-11-01 08:00:00', :upd)");
        $st->execute([
            't' => $tenant, 'i' => $identity, 'ref' => $ref, 'seq' => ++$this->seq, 'type' => $o['type'] ?? 'hvt', 'threat' => $o['threat'] ?? 'high', 'armed' => $o['armed'] ?? 'no',
            'reason' => $o['reason'] ?? 'Trafic d’armes sur le port', 'instr' => $o['instructions'] ?? "Ne pas intervenir seul\nSignaler au service",
            'zone' => $o['zone'] ?? 'Pyrgos', 'place' => $o['place'] ?? 'Entrepôt quai 4', 'seen' => '2026-11-10 21:30:00',
            'auth' => 'Bureau de recherche', 'cl' => 'Cellule de coordination des recherches', 'cd' => 'Canal SABRE 40.500',
            'status' => $status, 'ak' => $status === 'active' ? 1 : null, 'exp' => $o['expires'] ?? null,
            'closed' => $o['closed'] ?? ($status === 'active' ? null : gmdate('Y-m-d H:i:s', time() - 3600)), 'cr' => $status === 'active' ? null : 'Affaire close', 'cb' => $o['closed_by'] ?? null,
            'upd' => $o['updated'] ?? '2026-11-12 09:00:00',
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'ref' => $ref];
    }

    /** @param array<string, mixed> $codes */
    private function noticeCodes(int $noticeId, array $codes): void
    {
        $this->pdo->prepare('INSERT INTO sse_wanted_notice_extras (notice_id, tenant_id, codes_json, physical_json, updated_at) VALUES (?, 1, ?, ?, ?)')
            ->execute([$noticeId, json_encode($codes, JSON_UNESCAPED_UNICODE), json_encode(['height_cm' => 181, 'build' => 'athletic']), '2026-11-12 09:00:00']);
    }

    /**
     * Fiche de vigilance émise, telle que SseSheetService l'enregistre (instantané imprimé), avec son lien de signalement.
     *
     * @param array<string, mixed> $o
     * @return array{id: int, ref: string, token: string}
     */
    private function vigilance(int $tenant, ?int $identity, string $classification, array $o = []): array
    {
        $ref = $o['ref'] ?? sprintf('FV-2026-%04d', ++$this->seq);
        $token = SseAlertRepository::newToken();
        $report = ['code' => 'SVC-COORD', 'fr' => 'Cellule de coordination', 'en' => 'Coordination Cell', 'ac' => 'CCR', 'auth_fr' => 'Bureau', 'auth_en' => 'Bureau',
            'channel' => 'Canal SABRE 40.500', 'delay' => '15m', 'color' => '#1F3A5F', 'emblem' => 'hex'];
        $facts = ['display' => $o['display'] ?? 'DUPONT Marc', 'aliases' => ['Le Rouquin'], 'threat' => $o['threat'] ?? 'medium', 'armed' => $o['armed'] ?? 'no', 'zone' => 'Kavala',
            'last_seen_place' => 'Marché', 'last_seen_at' => '2026-11-09 18:00', 'height_cm' => 181, 'hair' => 'Roux',
            'links' => ['vehicle' => ['Fourgon blanc 12-AB-34'], 'address' => ['12 rue du Port'], 'associate' => ['Paul MARTIN (frère)']],
            'birth_date' => '1984-03-12', 'birth_place' => 'Pyrgos', 'id_document' => 'PASS-998877'];
        $alert = ['key' => 'reinforced', 'rank' => 2, 'order' => 'hold',
            'measures' => $o['measures'] ?? [['code' => 'A-OBS', 'fr' => 'Observer et rendre compte', 'en' => 'Observe and report'], ['code' => 'A-NOI', 'fr' => 'Ne pas intervenir seul', 'en' => 'Do not engage alone']],
            'reasons' => [['code' => 'M-TER-01', 'fr' => 'Planification d’attaque', 'en' => 'Attack planning']],
            'donts' => [['Ne pas se faire repérer.', 'Ne pas intervenir seul.'], ['Do not get detected.', 'Do not engage alone.']], 'report' => $report, 'token' => $token];
        $until = $o['until'] ?? '2099-01-01';
        $snap = ['data' => ['facts' => $facts, 'alert' => $alert, 'valid_until' => $until, 'issued_at' => '2026-11-05', 'authority' => ['Bureau', 'Bureau']], 'params' => ['validity' => 30]];
        $texts = $o['texts'] ?? ['reason' => 'Surveillance du port', 'instructions' => "Rester à distance\nNoter les plaques", 'behaviour' => 'Passe chaque soir', 'mention' => ''];
        $st = $this->pdo->prepare('INSERT INTO sse_doc_issues (tenant_id, person_id, identity_id, model, ref, ref_year, ref_seq, lang, classification, faces, texts_json, snapshot_json, sensitive_json, issued_by_user_id, issued_by_label, issued_at)
            VALUES (:t, NULL, :i, \'vigilance\', :ref, 2026, :seq, \'fr\', :cl, \'auto\', :texts, :snap, \'[]\', :by, \'ANALYSTE\', :at)');
        $st->execute(['t' => $tenant, 'i' => $identity, 'ref' => $ref, 'seq' => ++$this->seq, 'cl' => $classification, 'by' => $o['by'] ?? 8,
            'texts' => json_encode($texts, JSON_UNESCAPED_UNICODE), 'snap' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'at' => $o['at'] ?? '2026-11-05 10:00:00']);
        $id = (int) $this->pdo->lastInsertId();
        if (SseAlertRepository::ready()) {
            $link = (new SseAlertRepository())->ensureLink($tenant, SseAlertRepository::DOC_VIGILANCE, $id, ['token' => $token, 'report' => $report, 'valid_until' => $until]);
            if (isset($o['lifted'])) {
                $this->pdo->prepare('UPDATE sse_alert_links SET lifted_at = ?, lift_reason = ?, lifted_by_user_id = 7 WHERE id = ?')->execute([$o['lifted'], 'Sujet interpellé', (int) $link['id']]);
            }
        }

        return ['id' => $id, 'ref' => $ref, 'token' => $token];
    }

    /** Un avis et deux vigilances lisibles au plancher, une vigilance Confidentiel, une autre communauté. */
    private function seed(): void
    {
        $a = $this->identity(1, 'Karimzadeh', 'Élodie', null, 'La Chatte');
        $n = $this->notice(1, $a, ['ref' => 'A-0001/2026', 'type' => 'hvt', 'threat' => 'critical', 'armed' => 'yes']);
        $this->noticeCodes($n['id'], ['reasons' => [['code' => 'M-TER-01', 'fr' => 'Planification d’attaque', 'en' => 'Attack planning']],
            'measures' => [['code' => 'A-NOI', 'fr' => 'Ne pas intervenir seul', 'en' => 'Do not engage alone'], ['code' => 'A-ARR', 'fr' => 'Appréhender', 'en' => 'Apprehend']]]);
        $this->pdo->exec("INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, visibility, reveal_phase, created_at) VALUES
            (1, {$a}, 'vehicle', 'Pick-up gris 55-ZZ-01', 'x', 'known', 0, '2026-11-01 10:00:00'),
            (1, {$a}, 'associate', 'Yanis BENALI', 'x', 'known', 0, '2026-11-01 10:00:00')");
        $b = $this->identity(1, 'Dupont', 'Marc');
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0001']);
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0002', 'threat' => 'low', 'at' => '2026-11-07 10:00:00']);
        $this->vigilance(1, $this->identity(1, 'Secret', 'Dossier'), 'confidentiel', ['ref' => 'FV-2026-0003', 'display' => 'SECRET Dossier']);
        $this->notice(2, $this->identity(2, 'Bravo', 'Cible'), ['ref' => 'A-0900/2026']);
    }

    /** @return list<string> */
    private static function refs(array $pkg): array
    {
        return array_column($pkg['alerts'], 'ref');
    }

    private function rows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    // ------------------------------------------------------------------ Contrat : colonnes et formes

    public function testColumnsAreThoseOfTheContractInItsOrder(): void
    {
        $expected = ['ref', 'doc', 'type', 'color', 'rank', 'level', 'order', 'name', 'alias', 'threat', 'armed', 'conduct', 'reason', 'zone', 'place', 'issued', 'until', 'soon',
            'service', 'svc', 'channel', 'delay', 'url', 'token', 'state', 'acked', 'updated', 'cls', 'mark', 'cut'];
        self::assertSame($expected, SseAlertGameService::COLUMNS);
        self::assertSame('sse_alertes', SseAlertGameService::KIND);
        // Le tableau du § 3.2 du contrat : mêmes colonnes, mêmes numéros.
        $icd = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/technical/ICD-SSE-ALERTES.md');
        $section = substr($icd, (int) strpos($icd, '### 3.2'), (int) strpos($icd, '### 3.3') - (int) strpos($icd, '### 3.2'));
        preg_match_all('/^\|\s*(\d+)\s*\|\s*`([a-z]+)`\s*\|/m', $section, $m);
        self::assertSame($expected, $m[2]);
        self::assertSame(array_keys($expected), array_map('intval', $m[1]));
    }

    public function testSseFichesKeepsItsTwentyFiveColumns(): void
    {
        self::assertCount(25, SseFichesGameService::COLUMNS);
        $this->seed();
        $pkg = (new SseFichesGameService(null, static fn (): string => 'interne'))->build(1, 'interne');
        $byRef = array_column($pkg['fiches'], null, 'ref');
        self::assertArrayHasKey('FV-2026-0002', $byRef);
        self::assertCount(25, SseFichesGameService::cells($byRef['FV-2026-0002']));
        self::assertSame(['ref', 'kind', 'type', 'name', 'alias', 'threat', 'armed', 'level', 'mark', 'reason', 'measures', 'desc', 'remarks', 'note', 'zone', 'place', 'seen', 'issued', 'until',
            'updated', 'vehicles', 'addresses', 'people', 'auth', 'cut'], SseFichesGameService::COLUMNS);
        foreach (SseFichesGameService::pages($pkg) as $page) {
            foreach (self::parse($page)[6] as $row) {
                self::assertCount(25, $row);
            }
        }
    }

    // ------------------------------------------------------------------ Liste

    public function testListServesActiveNoticesAndEveryRunningVigilance(): void
    {
        $this->seed();
        $b = $this->identity(1, 'Ancien', 'Albert');
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0100', 'until' => '2020-01-01']);
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0101', 'lifted' => gmdate('Y-m-d H:i:s', time() - 600)]);
        $this->notice(1, $this->identity(1, 'Levé', 'Luc'), ['ref' => 'A-0101/2026', 'status' => 'lifted']);
        $this->notice(1, $this->identity(1, 'Échu', 'Eric'), ['ref' => 'A-0102/2026', 'expires' => '2020-01-01 00:00:00']);

        $pkg = $this->service()->build(1, 'interne', 11);
        // Rang décroissant (4 : armé, 2 : menace moyenne, 1 : faible) ; les DEUX vigilances du même sujet.
        self::assertSame(['A-0001/2026', 'FV-2026-0001', 'FV-2026-0002'], self::refs($pkg));
        self::assertSame(3, $pkg['total']);
        self::assertFalse($pkg['truncated']);
        self::assertSame('interne', $pkg['level']);
        self::assertSame('CUI', $pkg['mark']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $pkg['revision']);

        $avis = SseAlertGameService::publicAlert($pkg['alerts'][0]);
        self::assertSame(SseAlertGameService::COLUMNS, array_keys($avis));
        self::assertSame(['avis', 'hvt', 'red', 4, 'danger', 'order'], [$avis['doc'], $avis['type'], $avis['color'], $avis['rank'], $avis['level'], $avis['order']]);
        self::assertSame('KARIMZADEH Élodie', $avis['name']);
        self::assertSame('La Chatte', $avis['alias']);
        self::assertSame(['critical', 'yes'], [$avis['threat'], $avis['armed']]);
        self::assertSame(['A-NOI Ne pas intervenir seul', 'A-ARR Appréhender', 'Ne pas intervenir seul', 'Signaler au service'], $avis['conduct']);
        self::assertSame("M-TER-01 Planification d’attaque\nTrafic d’armes sur le port", $avis['reason']);
        self::assertSame(['Pyrgos', 'Entrepôt quai 4', '2026-11-01', ''], [$avis['zone'], $avis['place'], $avis['issued'], $avis['until']]);
        self::assertFalse($avis['soon']);
        self::assertSame('Cellule de coordination des recherches', $avis['service']);
        self::assertSame('Canal SABRE 40.500', $avis['channel']);
        self::assertSame('now', $avis['delay']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9]{22}$/', $avis['token']);
        self::assertSame('https://athena.example/atak/sse/signalement/' . $avis['token'], $avis['url']);
        self::assertSame(['active', false, '2026-11-12 09:00', 'interne', 'CUI', false], [$avis['state'], $avis['acked'], $avis['updated'], $avis['cls'], $avis['mark'], $avis['cut']]);

        $fv = SseAlertGameService::publicAlert($pkg['alerts'][1]);
        self::assertSame(['vigilance', 'vigilance', '', 2, 'reinforced', 'hold'], [$fv['doc'], $fv['type'], $fv['color'], $fv['rank'], $fv['level'], $fv['order']]);
        self::assertSame('DUPONT Marc', $fv['name']);
        self::assertSame(['A-OBS Observer et rendre compte', 'A-NOI Ne pas intervenir seul', 'Rester à distance', 'Noter les plaques'], $fv['conduct']);
        self::assertSame("M-TER-01 Planification d’attaque\nSurveillance du port", $fv['reason']);
        self::assertSame(['2026-11-05', '2099-01-01'], [$fv['issued'], $fv['until']]);
        self::assertSame(['Cellule de coordination (CCR)', 'SVC-COORD', 'Canal SABRE 40.500', '15m'], [$fv['service'], $fv['svc'], $fv['channel'], $fv['delay']]);
        self::assertStringEndsWith('/atak/sse/signalement/' . $fv['token'], $fv['url']);
        self::assertSame(1, SseAlertGameService::publicAlert($pkg['alerts'][2])['rank']);

        // Langue et filtre par genre.
        $en = SseAlertGameService::publicAlert($this->service()->build(1, 'interne', 11, 'en')['alerts'][1]);
        self::assertSame('A-OBS Observe and report', $en['conduct'][0]);
        self::assertSame('Coordination Cell (CCR)', $en['service']);
        self::assertSame(['A-0001/2026'], self::refs($this->service()->build(1, 'interne', 11, 'fr', 'avis')));
        self::assertSame(['FV-2026-0001', 'FV-2026-0002'], self::refs($this->service()->build(1, 'interne', 11, 'fr', 'vigilance')));
        // Rien d'interne ne part en JSON.
        foreach (array_keys($avis) as $k) {
            self::assertFalse(str_starts_with((string) $k, '_'));
        }
    }

    public function testAPerishableAlertIsFlaggedAndAPendingNoticeIsNeverListed(): void
    {
        $soon = $this->vigilance(1, $this->identity(1, 'Bientôt', 'Échu'), 'interne', ['until' => gmdate('Y-m-d', time() + 86400)]);
        $pending = $this->notice(1, $this->identity(1, 'Proposé', 'Paul'), ['ref' => 'A-0200/2026']);
        (new SseWantedMetaRepository())->save(1, $pending['id'], ['validation_state' => SseWantedMetaRepository::PENDING]);
        $pkg = $this->service()->build(1, 'tres_restreint', 9);
        self::assertSame([$soon['ref']], self::refs($pkg));
        self::assertTrue($pkg['alerts'][0]['soon']);
        self::assertNull($this->service()->find(1, 'tres_restreint', 9, 'A-0200/2026'), 'Un avis à valider n’existe pas pour le jeu');
        foreach (['detail', 'report', 'renew', 'lift', 'ack'] as $op) {
            self::assertSame(404, $this->call(7, ['op' => $op, 'ref' => 'A-0200/2026', 'text' => 'Vu au marché', 'reason' => 'Motif de levée'])[0], $op);
        }
        // Validé : il est diffusé.
        (new SseWantedMetaRepository())->save(1, $pending['id'], ['validation_state' => SseWantedMetaRepository::LIVE]);
        self::assertContains('A-0200/2026', self::refs($this->service()->build(1, 'interne', 9)));
    }

    public function testClearanceFiltersTheListAndAnAlertAboveIsUnknownForEveryOperation(): void
    {
        $this->seed();
        self::assertNotContains('FV-2026-0003', self::refs($this->service()->build(1, 'interne', 11)));
        self::assertNotContains('FV-2026-0003', self::refs($this->service()->build(1, 'encadrement', 7)));
        self::assertContains('FV-2026-0003', self::refs($this->service()->build(1, 'confidentiel', 8)));
        self::assertSame('SECRET//NOFORN', array_column($this->service()->build(1, 'confidentiel', 8)['alerts'], null, 'ref')['FV-2026-0003']['mark']);
        self::assertNull($this->service()->find(1, 'encadrement', 7, 'FV-2026-0003'));
        self::assertNull($this->service()->find(1, 'tres_restreint', 9, 'A-0900/2026'), 'Une alerte d’une autre communauté n’existe pas');

        $token = (string) $this->pdo->query("SELECT l.token FROM sse_alert_links l JOIN sse_doc_issues d ON d.id = l.target_id WHERE l.doc = 'vigilance' AND d.ref = 'FV-2026-0003'")->fetchColumn();
        $before = [$this->rows('sse_alert_reports'), $this->rows('sse_alert_acks'), $this->rows('sse_doc_issues')];
        // Compte 7 : gère les fichiers mais n'est habilité qu'Encadrement. Même réponse qu'une référence inconnue, partout.
        foreach ([
            ['op' => 'detail', 'ref' => 'FV-2026-0003'],
            ['op' => 'report', 'ref' => 'FV-2026-0003', 'text' => 'Vu près du port'],
            ['op' => 'report', 'token' => $token, 'text' => 'Vu près du port'],
            ['op' => 'renew', 'ref' => 'FV-2026-0003', 'days' => 30],
            ['op' => 'lift', 'ref' => 'FV-2026-0003', 'reason' => 'Sujet interpellé'],
            ['op' => 'ack', 'ref' => 'FV-2026-0003'],
        ] as $body) {
            [$status, $data] = $this->call(7, $body);
            [$unknownStatus, $unknown] = $this->call(7, ['ref' => 'FV-2026-9999', 'token' => ''] + $body);
            self::assertSame(404, $status, $body['op']);
            self::assertSame('not_found', $data['error']);
            self::assertSame([$unknownStatus, $unknown], [$status, $data], 'Indiscernable d’une référence inconnue : ' . $body['op']);
            self::assertStringNotContainsString('SECRET', json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        self::assertSame($before, [$this->rows('sse_alert_reports'), $this->rows('sse_alert_acks'), $this->rows('sse_doc_issues')]);
        self::assertNull($this->pdo->query("SELECT l.lifted_at FROM sse_alert_links l JOIN sse_doc_issues d ON d.id = l.target_id WHERE d.ref = 'FV-2026-0003'")->fetchColumn() ?: null);
        // Le compte habilité la lit.
        self::assertSame('FV-2026-0003', $this->call(8, ['op' => 'detail', 'ref' => 'FV-2026-0003'])[2][2][0]);
    }

    public function testNeverAMemberIdentityNorWhatIsHidden(): void
    {
        $member = $this->identity(1, 'Membre', 'Vrai', 7);
        $this->notice(1, $member, ['ref' => 'A-0500/2026']);
        $this->vigilance(1, $member, 'interne', ['ref' => 'FV-2026-0500', 'display' => 'MEMBRE Vrai']);
        $normal = $this->identity(1, 'Normal', 'Jean');
        $this->notice(1, $normal, ['ref' => 'A-0501/2026']);
        $this->pdo->exec("INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, linked_identity_id, relation, visibility, reveal_phase, created_at)
            VALUES (1, {$normal}, 'associate', 'Vrai MEMBRE', 'x', {$member}, 'associate', 'known', 0, '2026-11-01 10:00:00'),
                   (1, {$normal}, 'associate', 'Cousin Quelconque', 'x', NULL, 'family', 'known', 0, '2026-11-01 10:00:00'),
                   (1, {$normal}, 'vehicle', 'Berline cachée', 'x', NULL, NULL, 'hidden', 0, '2026-11-01 10:00:00')");
        $hidden = $this->identity(1, 'Cachée', 'Agent', null, 'Alias caché');
        $this->notice(1, $hidden, ['ref' => 'A-0502/2026']);
        (new SseFileRecordRepository())->saveProfile(1, $hidden, ['visibility' => ['identity' => ['v' => 'hidden', 'phase' => 0]]], null);
        $this->pdo->exec("INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, visibility, reveal_phase, created_at) VALUES (1, {$hidden}, 'vehicle', 'Camion rouge', 'x', 'known', 0, '2026-11-01 10:00:00')");

        $pkg = $this->service()->build(1, 'tres_restreint', 9);
        self::assertSame(['A-0501/2026', 'A-0502/2026'], self::refs($pkg));
        self::assertNull($this->service()->find(1, 'tres_restreint', 9, 'A-0500/2026'));
        self::assertNull($this->service()->find(1, 'tres_restreint', 9, 'FV-2026-0500'));
        foreach (['detail', 'report', 'renew', 'lift', 'ack'] as $op) {
            self::assertSame(404, $this->call(7, ['op' => $op, 'ref' => 'A-0500/2026', 'text' => 'Vu au marché', 'reason' => 'Motif de levée'])[0], $op);
        }
        self::assertSame(0, $this->rows('sse_alert_links WHERE target_id = (SELECT id FROM sse_wanted_notices WHERE ref = \'A-0500/2026\') AND doc = \'avis\''), 'Aucune adresse de signalement pour un membre');
        // Personnes liées : à partir de Confidentiel, jamais un membre ; un lien caché n'est pas donné.
        $detail = SseAlertGameService::detail($this->service()->find(1, 'tres_restreint', 9, 'A-0501/2026'));
        self::assertCount(1, $detail['people']);
        self::assertStringStartsWith('Cousin Quelconque', $detail['people'][0]);
        self::assertSame([], $detail['vehicles']);
        self::assertSame([], SseAlertGameService::detail($this->service()->find(1, 'encadrement', 7, 'A-0501/2026'))['people']);
        // Identité cachée par le MJ : une barre, ni alias, ni lien.
        $bar = $this->service()->find(1, 'tres_restreint', 9, 'A-0502/2026');
        self::assertStringNotContainsString('Cachée', (string) json_encode([SseAlertGameService::publicAlert($bar), SseAlertGameService::detail($bar)], JSON_UNESCAPED_UNICODE));
        self::assertStringContainsString('█', $bar['name']);
        self::assertSame('', $bar['alias']);
        self::assertSame([], SseAlertGameService::detail($bar)['vehicles']);
        // Rien du compte du membre, ni état civil, dans tout ce qui part.
        $all = (string) json_encode([array_map([SseAlertGameService::class, 'publicAlert'], $pkg['alerts']), SseAlertGameService::pages($pkg), SseAlertGameService::detailPage($bar)], JSON_UNESCAPED_UNICODE);
        foreach (['MEMBRE', 'Membre', '1984', 'Pyrgos, ', 'PASS-', '7656119', 'photo', 'steam'] as $leak) {
            self::assertStringNotContainsString($leak, $all, $leak);
        }
        // Émettre sur un membre : sujet introuvable, rien de créé, pas même une proposition.
        $issues = $this->rows('sse_doc_issues');
        foreach ([7, 11] as $user) {
            [$status, $data] = $this->call($user, ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDT-%06d', $member), 'threat' => 'low', 'cid' => 'membre-' . $user . '-0001']);
            self::assertSame(422, $status);
            self::assertSame('invalid', $data['error']);
        }
        self::assertSame($issues, $this->rows('sse_doc_issues'));
        self::assertSame(0, $this->rows('sse_alert_proposals'));
    }

    // ------------------------------------------------------------------ Pages et révision

    public function testPagesStayUnderTheDllLimitAndGiveBackEveryAlertInOrder(): void
    {
        $long = str_repeat('Motif « très » long, avec | barres et "guillemets" ', 30);
        for ($i = 0; $i < 90; $i++) {
            $this->notice(1, $this->identity(1, 'Nom' . $i, 'Prénom'), ['reason' => $long, 'instructions' => str_repeat("Consigne longue à appliquer sans délai, numéro {$i}\n", 12), 'threat' => ['low', 'medium', 'high', 'critical'][$i % 4]]);
        }
        $pkg = $this->service()->build(1, 'interne', 11);
        self::assertCount(SseAlertGameService::MAX_ALERTS, $pkg['alerts']);
        self::assertTrue($pkg['truncated']);
        self::assertSame(90, $pkg['total']);
        $ranks = array_column($pkg['alerts'], 'rank');
        $sorted = $ranks;
        rsort($sorted);
        self::assertSame($sorted, $ranks, 'Les plus graves d’abord');
        $pages = SseAlertGameService::pages($pkg);
        self::assertGreaterThan(1, count($pages));
        self::assertLessThanOrEqual(SseAlertGameService::MAX_PAGES, count($pages));
        $seen = [];
        foreach ($pages as $i => $page) {
            self::assertLessThanOrEqual(SseFichesGameService::PAGE_BYTES, strlen($page), "page {$i}");
            $p = self::parse($page);
            self::assertCount(9, $p);
            self::assertSame([SseAlertGameService::VERSION, 'list', $pkg['revision'], $pkg['generated_at'], SseAlertGameService::TTL, $i, $i + 1 < count($pages) ? $i + 1 : 0], array_slice($p, 0, 7));
            self::assertSame(['interne', 'CUI', 90, true], $p[8]);
            foreach ($p[7] as $row) {
                $a = self::named($row);
                self::assertTrue($a['cut']);
                self::assertLessThanOrEqual(SseAlertGameService::SHORT['reason'], mb_strlen(str_replace(['\p', '\n'], ['|', "
"], $a['reason'])));
                self::assertLessThanOrEqual(SseAlertGameService::SHORT['lines'], count($a['conduct']));
                $seen[] = $a['ref'];
            }
        }
        self::assertSame(self::refs($pkg), $seen, 'Mêmes alertes, même ordre, aucune perdue en route');
        // Par le canal du jeu : page par page, jusqu'à « page suivante = 0 ».
        $got = [];
        $page = 0;
        do {
            [$status, $data, $p] = $this->call(11, ['op' => 'list', 'page' => $page]);
            self::assertSame(200, $status);
            self::assertSame([$page, count($pages)], [$data['page'], $data['pages']]);
            $got = array_merge($got, array_column($p[7], 0));
            $page = (int) $data['next'];
        } while ($page !== 0);
        self::assertSame($seen, $got);
        // Détail : textes longs, une seule page.
        $detail = $this->call(11, ['op' => 'detail', 'ref' => $seen[0]])[2];
        $reason = str_replace(['\p', '\n'], ['|', "
"], $detail[2][12]);
        self::assertGreaterThan(SseAlertGameService::SHORT['reason'], mb_strlen($reason));
        self::assertLessThanOrEqual(SseAlertGameService::FULL['reason'], mb_strlen($reason));
        self::assertLessThanOrEqual(SseAlertGameService::FULL['lines'], count($detail[2][11]));
    }

    public function testKnownRevisionGivesTheUnchangedMarkAndTheRevisionFollowsTheContentAndTheLevel(): void
    {
        $this->seed();
        [$status, $data, $page] = $this->call(11, ['op' => 'list', 'rev' => '', 'page' => 0, 'lang' => 'fr', 'doc' => '', 'tenant_id' => 2]);
        self::assertSame(200, $status);
        self::assertGreaterThan(0, $data['id']);
        self::assertSame(['A-0001/2026', 'FV-2026-0001', 'FV-2026-0002'], array_column($page[7], 0), 'La communauté est celle du jeton, jamais du corps');
        [, $again, $mark] = $this->call(11, ['op' => 'list', 'rev' => $page[2]]);
        self::assertTrue($again['unchanged']);
        self::assertSame(0, $again['pages']);
        self::assertCount(10, $mark);
        self::assertSame([SseAlertGameService::VERSION, 'list', $page[2]], array_slice($mark, 0, 3));
        self::assertSame([SseAlertGameService::TTL, 0, 0, [], [], true], array_slice($mark, 4));
        // Autre niveau, autre contenu : autre révision.
        self::assertNotSame($page[2], $this->call(8, ['op' => 'list'])[2][2]);
        $this->pdo->exec("UPDATE sse_wanted_notices SET zone = 'Kavala' WHERE ref = 'A-0001/2026'");
        [, $changed, $next] = $this->call(11, ['op' => 'list', 'rev' => $page[2]]);
        self::assertFalse(isset($changed['unchanged']));
        self::assertNotSame($page[2], $next[2]);
    }

    public function testAnAcknowledgementChangesTheRevisionOfThatAccountOnly(): void
    {
        $this->seed();
        $rev11 = $this->call(11, ['op' => 'list'])[2][2];
        $rev9 = $this->service()->build(1, 'interne', 9)['revision'];
        self::assertSame($rev11, $rev9, 'Même niveau, mêmes accusés : même révision');
        [$status, , $ack] = $this->call(11, ['op' => 'ack', 'ref' => 'FV-2026-0001']);
        self::assertSame(200, $status);
        self::assertCount(4, $ack);
        self::assertSame([SseAlertGameService::VERSION, 'ack', 'FV-2026-0001'], array_slice($ack, 0, 3));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $ack[3]);
        // Un seul accusé par compte et par alerte ; le second rend la même date.
        self::assertSame($ack, $this->call(11, ['op' => 'ack', 'ref' => 'FV-2026-0001'])[2]);
        self::assertSame(1, $this->rows('sse_alert_acks'));
        [, $data, $page] = $this->call(11, ['op' => 'list', 'rev' => $rev11]);
        self::assertFalse(isset($data['unchanged']), 'Le compte voit son accusé : sa révision a changé');
        self::assertNotSame($rev11, $page[2]);
        $acked = array_column(array_map([self::class, 'named'], $page[7]), 'acked', 'ref');
        self::assertSame(['A-0001/2026' => false, 'FV-2026-0001' => true, 'FV-2026-0002' => false], $acked);
        self::assertSame($rev9, $this->service()->build(1, 'interne', 9)['revision'], 'La révision d’un autre compte ne bouge pas');
        // Un avis s'accuse aussi (lien créé au premier besoin).
        self::assertSame(200, $this->call(9, ['op' => 'ack', 'ref' => 'A-0001/2026'])[0]);
        self::assertSame(2, $this->rows('sse_alert_acks'));
        self::assertCount(2, array_filter(array_column($this->audited, 'action'), static fn (string $a): bool => $a === 'alertes.ack'), 'Un accusé répété n’est journalisé qu’une fois');
    }

    // ------------------------------------------------------------------ Catalogue

    public function testCatalogGivesTheSectionsOfTheContractAndTheRightsOfTheAccount(): void
    {
        $rows = [];
        $page = 0;
        $revision = '';
        do {
            [$status, $data, $p] = $this->call(7, ['op' => 'catalog', 'rev' => '', 'page' => $page, 'lang' => 'fr']);
            self::assertSame(200, $status);
            self::assertCount(7, $p);
            self::assertSame([SseAlertGameService::VERSION, 'catalog'], [$p[0], $p[1]]);
            self::assertSame([$page, (int) $data['next']], [$p[3], $p[4]]);
            self::assertSame([true, true, true, true], $p[6]);
            $revision = $p[2];
            $rows = array_merge($rows, $p[5]);
            $page = (int) $data['next'];
        } while ($page !== 0);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $revision);
        $by = [];
        foreach ($rows as $row) {
            self::assertCount(count(SseAlertGameService::CATALOG[$row[0]]) + 1, $row, $row[0]);
            $by[$row[0]][] = array_slice($row, 1);
        }
        self::assertSame(['level', 'type', 'color', 'reason', 'measure', 'service', 'validity', 'delay', 'sure', 'threat', 'armed'], array_keys($by));
        self::assertSame([
            ['watch', 'VIGILANCE', 'BE ON THE LOOKOUT', 1, '#F2C200', 'triangle'],
            ['reinforced', 'VIGILANCE RENFORCÉE', 'HEIGHTENED VIGILANCE', 2, '#F08C00', 'triangle'],
            ['caution', 'APPROCHE AVEC PRÉCAUTION', 'APPROACH WITH CAUTION', 3, '#D9480F', 'diamond'],
            ['danger', 'DANGER — ARMÉ ET DANGEREUX', 'DANGER — ARMED AND DANGEROUS', 4, '#B0121F', 'octagon'],
        ], $by['level']);
        $types = array_column($by['type'], null, 0);
        self::assertSame(['hvt', 'Cible de haute valeur (HVT)', 'High-value target (HVT)', 'red', 'NOTICE ROUGE', 'RED NOTICE', 'CIBLE DE HAUTE VALEUR', 'HIGH-VALUE TARGET'], $types['hvt']);
        self::assertSame('blue', $types['poi'][3]);
        self::assertSame(['red', 'blue', 'green', 'yellow', 'orange', 'black'], array_column($by['color'], 0));
        self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $by['color'][0][3]);
        self::assertMatchesRegularExpression('/^M-[A-Z0-9]{2,4}-\d{2}$/', $by['reason'][0][0]);
        self::assertContains('A-OBS', array_column($by['measure'], 0));
        self::assertNotSame([], $by['service']);
        foreach ($by['service'] as $svc) {
            self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $svc[4]);
            self::assertTrue(is_bool($svc[7]));
            self::assertNotSame('', $svc[8], 'Canal de compte rendu');
        }
        self::assertSame(['now', '15m', '1h', '4h', '24h'], array_column($by['delay'], 0));
        self::assertSame(['low', 'medium', 'high'], array_column($by['sure'], 0));
        self::assertSame(['low', 'medium', 'high', 'critical'], array_column($by['threat'], 0));
        self::assertSame(['yes', 'no', 'unknown'], array_column($by['armed'], 0));
        self::assertSame(0, preg_match('/fiction|jeu de rôle|\bRP\b|simulation|roleplay/iu', (string) json_encode($rows, JSON_UNESCAPED_UNICODE)));

        // Révision connue : rien que les droits, propres au compte (ils n'entrent pas dans l'empreinte).
        [, $same, $mark] = $this->call(11, ['op' => 'catalog', 'rev' => $revision]);
        self::assertTrue($same['unchanged']);
        self::assertSame([SseAlertGameService::VERSION, 'catalog', $revision, 0, 0, [], [false, false, false, true], true], $mark);
        self::assertSame([true, true, true, true], $this->call(8, ['op' => 'catalog', 'rev' => $revision])[2][6]);
        // La communauté change son catalogue : la révision change.
        $config = (new SseWantedSettingsRepository())->get(1);
        $config['disabled_types'] = ['escaped'];
        (new SseWantedSettingsRepository())->save(1, $config, 7);
        [, $other, $fresh] = $this->call(11, ['op' => 'catalog', 'rev' => $revision]);
        self::assertFalse(isset($other['unchanged']));
        self::assertNotSame($revision, $fresh[2]);
        self::assertNotContains('escaped', array_column(array_filter($fresh[5], static fn (array $r): bool => $r[0] === 'type'), 1));
    }

    // ------------------------------------------------------------------ Détail

    public function testDetailHasTheShapeOfTheContractAndAClosedAlertStaysReadableThirtyDays(): void
    {
        $this->seed();
        $b = $this->identity(1, 'Ancien', 'Albert');
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0101', 'lifted' => gmdate('Y-m-d H:i:s', time() - 5 * 86400)]);
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0102', 'lifted' => gmdate('Y-m-d H:i:s', time() - 40 * 86400)]);
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0103', 'until' => gmdate('Y-m-d', time() - 3 * 86400)]);
        $this->vigilance(1, $b, 'interne', ['ref' => 'FV-2026-0104', 'until' => gmdate('Y-m-d', time() - 60 * 86400)]);
        $this->notice(1, $this->identity(1, 'Levé', 'Luc'), ['ref' => 'A-0101/2026', 'status' => 'lifted']);
        $this->notice(1, $this->identity(1, 'Vieux', 'Levé'), ['ref' => 'A-0103/2026', 'status' => 'lifted', 'closed' => gmdate('Y-m-d H:i:s', time() - 45 * 86400)]);

        [$status, , $d] = $this->call(8, ['op' => 'detail', 'ref' => 'A-0001/2026', 'lang' => 'fr']);
        self::assertSame(200, $status);
        self::assertCount(11, $d);
        self::assertSame([SseAlertGameService::VERSION, 'detail'], [$d[0], $d[1]]);
        $a = self::named($d[2]);
        self::assertSame(['A-0001/2026', 'avis', 'active'], [$a['ref'], $a['doc'], $a['state']]);
        self::assertSame(['M-TER-01 Planification d’attaque'], $d[3]);
        self::assertSame(['Pick-up gris 55-ZZ-01'], $d[4]);
        self::assertSame(['Yanis BENALI'], $d[5], 'Personnes liées à partir de Confidentiel');
        self::assertStringContainsString('181', $d[6]);
        self::assertSame([0, '', ''], [$d[7], $d[8], $d[9]]);
        self::assertSame(['Ne pas intervenir seul.', 'Ne pas appréhender sans ordre.'], $d[10]);
        self::assertSame([], $this->call(11, ['op' => 'detail', 'ref' => 'A-0001/2026'])[2][5], 'Au plancher : pas de personne liée');

        $fv = $this->call(11, ['op' => 'detail', 'ref' => 'FV-2026-0001', 'lang' => 'en'])[2];
        self::assertSame(['M-TER-01 Attack planning'], $fv[3]);
        self::assertSame(['Fourgon blanc 12-AB-34'], $fv[4]);
        self::assertSame(['Do not get detected.', 'Do not engage alone.'], $fv[10]);

        $lifted = $this->call(11, ['op' => 'detail', 'ref' => 'FV-2026-0101'])[2];
        self::assertSame('lifted', self::named($lifted[2])['state']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $lifted[8]);
        self::assertSame('Sujet interpellé', $lifted[9]);
        self::assertSame('expired', self::named($this->call(11, ['op' => 'detail', 'ref' => 'FV-2026-0103'])[2][2])['state']);
        $closed = $this->call(11, ['op' => 'detail', 'ref' => 'A-0101/2026'])[2];
        self::assertSame(['lifted', 'Affaire close'], [self::named($closed[2])['state'], $closed[9]]);
        foreach (['FV-2026-0102', 'FV-2026-0104', 'A-0103/2026', 'NOPE'] as $gone) {
            self::assertSame(404, $this->call(9, ['op' => 'detail', 'ref' => $gone])[0], $gone);
        }
        // Une alerte close n'est plus listée.
        self::assertSame(['A-0001/2026', 'FV-2026-0001', 'FV-2026-0002'], self::refs($this->service()->build(1, 'interne', 11)));
        self::assertContains('alertes.detail', array_column($this->audited, 'action'));
    }

    // ------------------------------------------------------------------ Émission

    public function testAnAccountWithTheRightIssuesAnActiveVigilanceOnceWhateverTheResends(): void
    {
        $person = $this->person(1, 'Karimzadeh', 'Dariush', 'SUJ-0042');
        $this->identity(1, 'Karimzadeh', 'Dariush', null, '', $person);
        $body = ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDN-%05d', $person), 'threat' => 'high', 'armed' => 'no', 'reasons' => 'M-TER-01,M-ZZZ-99', 'measures' => 'A-OBS,A-NOI',
            'zone' => 'Port de Kavala', 'grid' => '072118', 'place' => 'Quai 4', 'days' => 14, 'text' => 'Repéré deux soirs de suite près des entrepôts.', 'lang' => 'fr', 'cid' => 'u7-000123-1'];
        [$status, , $r] = $this->call(7, $body);
        self::assertSame(200, $status);
        self::assertCount(8, $r);
        self::assertSame([SseAlertGameService::VERSION, 'create', 'active'], array_slice($r, 0, 3));
        self::assertMatchesRegularExpression('/^FV-\d{4}-\d{4}$/', $r[3]);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9]{22}$/', $r[5]);
        self::assertSame('https://athena.example/atak/sse/signalement/' . $r[5], $r[4]);
        self::assertNotSame('', $r[6]);
        self::assertFalse($r[7]);
        self::assertSame(1, $this->rows('sse_doc_issues'));
        self::assertSame(0, $this->rows('sse_alert_proposals'));
        self::assertSame('u7-000123-1', $this->pdo->query('SELECT client_id FROM sse_alert_links')->fetchColumn());

        // Renvoi (file hors ligne) : la même réponse, drapeau « replay », rien de créé.
        [$again, , $replay] = $this->call(7, $body);
        self::assertSame(200, $again);
        self::assertSame(array_slice($r, 0, 7), array_slice($replay, 0, 7));
        self::assertTrue($replay[7]);
        self::assertSame(1, $this->rows('sse_doc_issues'));
        self::assertSame(1, $this->rows('sse_alert_links'));

        // Elle est en cours dans la liste, à la classification par défaut de la communauté ramenée à l'habilitation de l'émetteur.
        $alert = array_column(array_map([self::class, 'named'], $this->call(7, ['op' => 'list'])[2][7]), null, 'ref')[$r[3]];
        self::assertSame(['vigilance', 3, 'caution', 'hold', 'high', 'no', 'encadrement'], [$alert['doc'], $alert['rank'], $alert['level'], $alert['order'], $alert['threat'], $alert['armed'], $alert['cls']]);
        self::assertStringContainsString('█', $alert['name'], 'Diffusée Encadrement : l’identité est caviardée comme sur le PDF (règle du site)');
        self::assertSame('Port de Kavala', $alert['zone']);
        self::assertSame(gmdate('Y-m-d', time() + 14 * 86400), $alert['until']);
        self::assertStringStartsWith('A-OBS ', $alert['conduct'][0]);
        self::assertStringStartsWith('M-TER-01 ', $alert['reason']);
        self::assertStringContainsString('Repéré deux soirs de suite', $alert['reason']);
        self::assertStringContainsString('Quai 4 (072118)', $alert['reason']);
        self::assertSame($r[5], $alert['token']);
        self::assertSame(404, $this->call(11, ['op' => 'detail', 'ref' => $r[3]])[0], 'Classée Encadrement : inconnue au plancher');
        self::assertContains('alertes.create', array_column($this->audited, 'action'));
        // Par son identifiant de sujet, sans identifiant client : une seconde fiche (pas de protection sans « cid »).
        unset($body['cid']);
        self::assertSame('active', $this->call(7, ['subject' => 'SUJ-0042'] + $body)[2][2]);
        self::assertSame(2, $this->rows('sse_doc_issues'));
        // Communauté qui diffuse en Confidentiel : le nom est lisible par les comptes habilités, la fiche inconnue des autres.
        $config = (new SseWantedSettingsRepository())->get(1);
        $config['default_classification'] = 'confidentiel';
        (new SseWantedSettingsRepository())->save(1, $config, 7);
        $named = $this->call(8, ['cid' => 'u8-000124-1'] + $body)[2];
        $seen = array_column(array_map([self::class, 'named'], $this->call(8, ['op' => 'list'])[2][7]), null, 'ref')[$named[3]];
        self::assertSame(['KARIMZADEH Dariush', 'confidentiel'], [$seen['name'], $seen['cls']]);
        self::assertSame(404, $this->call(7, ['op' => 'detail', 'ref' => $named[3]])[0]);
    }

    public function testAnAccountWithTheRightPlacesAnActiveNoticeOnce(): void
    {
        $identity = $this->identity(1, 'Recherché', 'Roger');
        $body = ['op' => 'create', 'doc' => 'avis', 'subject' => sprintf('IDT-%06d', $identity), 'type' => 'arrest', 'threat' => 'critical', 'armed' => 'yes', 'reasons' => 'M-TER-01', 'measures' => 'A-NOI,A-ARR',
            'zone' => 'Kavala', 'place' => 'Gare routière', 'days' => 30, 'text' => '', 'cid' => 'u8-000777-4'];
        [$status, , $r] = $this->call(8, $body);
        self::assertSame(200, $status);
        self::assertSame('active', $r[2]);
        self::assertSame(1, $this->rows('sse_wanted_notices'));
        self::assertStringEndsWith('/atak/sse/signalement/' . $r[5], $r[4]);
        $replay = $this->call(8, $body)[2];
        self::assertSame([$r[3], $r[4], $r[5], true], [$replay[3], $replay[4], $replay[5], $replay[7]]);
        self::assertSame(1, $this->rows('sse_wanted_notices'));
        $alert = array_column(array_map([self::class, 'named'], $this->call(11, ['op' => 'list'])[2][7]), null, 'ref')[$r[3]];
        self::assertSame(['avis', 'arrest', 'red', 4, 'danger', 'order', 'RECHERCHÉ Roger'], [$alert['doc'], $alert['type'], $alert['color'], $alert['rank'], $alert['level'], $alert['order'], $alert['name']]);
        self::assertSame(gmdate('Y-m-d', time() + 30 * 86400), $alert['until']);
        self::assertStringStartsWith('A-NOI ', $alert['conduct'][0]);
        // Saisie refusée : rien de créé.
        foreach ([
            ['type' => 'nimporte'], ['threat' => 'enorme'], ['days' => 45], ['subject' => '', 'unknown' => 'court'], ['subject' => 'IDT-999999'], ['doc' => 'tract'],
            ['reasons' => '', 'text' => ''], ['text' => str_repeat('x', 401)],
        ] as $bad) {
            [$status, $data] = $this->call(8, ['cid' => 'u8-bad-' . md5((string) json_encode($bad))] + $bad + $body);
            self::assertSame(422, $status, (string) json_encode($bad));
            self::assertSame('invalid', $data['error']);
            self::assertNotSame([], $data['errors']);
        }
        self::assertSame(1, $this->rows('sse_wanted_notices'));
        self::assertSame(0, $this->rows('sse_alert_proposals'));
    }

    public function testFreeTextNamingARealOrganisationIsRefused(): void
    {
        $forbidden = \App\Support\SseWantedRealNames::forbidden();
        self::assertNotSame([], $forbidden);
        $name = (string) (is_string(array_key_first($forbidden)) ? array_key_first($forbidden) : reset($forbidden));
        self::assertNotNull(\App\Support\SseWantedRealNames::find('Signalé par ' . $name));
        $identity = $this->identity(1, 'Recherché', 'Roger');
        $n = $this->notice(1, $identity, ['ref' => 'A-0300/2026']);
        [$status, $data] = $this->call(7, ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDT-%06d', $identity), 'text' => 'Signalé par ' . $name]);
        self::assertSame(422, $status);
        self::assertSame('invalid', $data['error']);
        [$status, $data] = $this->call(11, ['op' => 'report', 'ref' => $n['ref'], 'text' => 'Vu avec ' . $name]);
        self::assertSame(422, $status);
        self::assertSame(0, $this->rows('sse_doc_issues') + $this->rows('sse_alert_reports') + $this->rows('sse_alert_proposals'));
    }

    public function testShortValidityAndUntilLiftedAreAcceptedAndExpireAtTheExactHour(): void
    {
        $person = $this->person(1, 'Karimzadeh', 'Dariush', 'SUJ-0042');
        $this->identity(1, 'Karimzadeh', 'Dariush', null, '', $person);
        $base = ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDN-%05d', $person), 'threat' => 'high', 'measures' => 'A-OBS', 'text' => 'Vu au port.'];
        [$status, , $r] = $this->call(7, $base + ['validity_hours' => 6, 'cid' => 'u7-court-1']);
        self::assertSame([200, 'active'], [$status, $r[2]]);
        $view = $this->alertService()->findVigilance(1, (int) $this->pdo->query('SELECT MAX(id) FROM sse_doc_issues')->fetchColumn());
        $left = strtotime($view['expires_at'] . ' UTC') - time();
        self::assertTrue($left > 5 * 3600 && $left <= 6 * 3600 + 5, 'échéance à 6 h');
        self::assertSame('active', $view['state']);
        // Jusqu'à levée : aucune échéance, jamais expirée ni périssable.
        [, , $open] = $this->call(7, $base + ['validity' => 'until_lifted', 'cid' => 'u7-levee-1']);
        $view = $this->alertService()->findVigilance(1, (int) $this->pdo->query('SELECT MAX(id) FROM sse_doc_issues')->fetchColumn());
        self::assertSame(['active', '', true, false], [$view['state'], $view['until'], $view['until_lifted'], $view['perishable']]);
        self::assertSame('active', $open[2]);
        // Durée inconnue : refusée, rien de créé de plus.
        $count = $this->rows('sse_doc_issues');
        self::assertNotSame(200, $this->call(7, $base + ['validity_hours' => 5, 'cid' => 'u7-court-2'])[0]);
        self::assertSame($count, $this->rows('sse_doc_issues'));
        // Le catalogue les annonce.
        $keys = array_column(SseAlertGameService::catalogSections($this->service()->catalog(1))['validity'], 'key');
        self::assertSame([], array_diff(['h6', 'h24', 'h72', 'until_lifted'], $keys));
    }

    public function testTheStateOfAProposalIsGivenBackToItsAuthorOnly(): void
    {
        $identity = $this->identity(1, 'Suspect', 'Sam');
        $this->call(11, ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDT-%06d', $identity), 'threat' => 'medium', 'measures' => 'A-OBS', 'cid' => 'u11-prop-1']);
        $rows = $this->alertService()->alerts()->proposalsOf(1, 11);
        self::assertSame(['PROP-1', 'pending'], [$rows[0]['ref'], $rows[0]['state']]);
        self::assertSame([], $this->alertService()->alerts()->proposalsOf(1, 12));
        self::assertSame([], $this->alertService()->alerts()->proposalsOf(2, 11));
        $this->alertService()->alerts()->decideProposal(1, 1, false, 'Sujet déjà couvert.', 7);
        $r = $this->alertService()->alerts()->proposalsOf(1, 11)[0];
        self::assertSame(['rejected', 'Sujet déjà couvert.'], [$r['state'], $r['decision_note']]);
        [$status, , $page] = $this->call(11, ['op' => 'proposals']);
        self::assertSame(200, $status);
        self::assertSame([SseAlertGameService::VERSION, 'proposals'], array_slice($page, 0, 2));
        self::assertSame(['PROP-1', 'vigilance', 'rejected'], array_slice($page[2][0], 0, 3));
        self::assertSame('u11-prop-1', $page[2][0][7]);
    }

    public function testWithoutTheRightOrForAnUnknownOrUnderValidationTheCreationIsAProposal(): void
    {
        $identity = $this->identity(1, 'Suspect', 'Sam');
        $ref = sprintf('IDT-%06d', $identity);
        // Sans droit d'émettre : proposition, gestionnaires prévenus, rien d'actif.
        $body = ['op' => 'create', 'doc' => 'vigilance', 'subject' => $ref, 'threat' => 'medium', 'measures' => 'A-OBS', 'text' => 'Tourne autour du dépôt.', 'cid' => 'u11-000001-1'];
        [$status, , $r] = $this->call(11, $body);
        self::assertSame(200, $status);
        self::assertSame([SseAlertGameService::VERSION, 'create', 'proposal', 'PROP-1', '', ''], array_slice($r, 0, 6));
        self::assertFalse($r[7]);
        self::assertSame(0, $this->rows('sse_doc_issues'));
        self::assertSame([7, 8], array_column($this->notified, 1));
        self::assertSame('proposal', $this->notified[0][2]['event']);
        self::assertSame('PROP-1', $this->notified[0][2]['ref']);
        self::assertContains('alertes.proposal', array_column($this->audited, 'action'));
        // Renvoi : même réponse, une seule proposition, pas de seconde notification.
        $replay = $this->call(11, $body)[2];
        self::assertSame(array_slice($r, 0, 7), array_slice($replay, 0, 7));
        self::assertTrue($replay[7]);
        self::assertSame(1, $this->rows('sse_alert_proposals'));
        self::assertCount(2, $this->notified);
        // Sujet inconnu du registre : proposition même avec le droit d'émettre.
        [, , $unknown] = $this->call(7, ['op' => 'create', 'doc' => 'vigilance', 'subject' => '', 'unknown' => 'Homme, 40 ans, veste verte, boite de la jambe gauche.', 'threat' => 'low']);
        self::assertSame(['proposal', 'PROP-2'], [$unknown[2], $unknown[3]]);
        // Circuit de validation des avis : proposition même pour un gestionnaire ; sans lui, alerte active.
        $config = (new SseWantedSettingsRepository())->get(1);
        $config['process']['validation'] = true;
        (new SseWantedSettingsRepository())->save(1, $config, 7);
        $avis = ['op' => 'create', 'doc' => 'avis', 'subject' => $ref, 'type' => 'poi', 'threat' => 'medium', 'reasons' => 'M-TER-01'];
        self::assertSame(['proposal', 'PROP-3'], array_slice($this->call(7, $avis)[2], 2, 2));
        self::assertSame('active', $this->call(7, ['doc' => 'vigilance'] + $avis)[2][2], 'Le circuit ne concerne que les avis');
        self::assertSame(0, $this->rows('sse_wanted_notices'));
        // Une proposition n'apparaît ni dans la liste ni en détail.
        self::assertSame(404, $this->call(7, ['op' => 'detail', 'ref' => 'PROP-1'])[0]);
        self::assertSame(1, count($this->call(7, ['op' => 'list'])[2][7]));
        $pending = $this->proposals()->listProposals(1);
        self::assertSame(['PROP-3', 'PROP-2', 'PROP-1'], array_column($pending, 'ref'));
        self::assertSame(['validation', 'unknown', 'right'], array_map(static fn (array $p): string => (string) $p['payload']['cause'], $pending));
        self::assertSame([], $this->proposals()->listProposals(2));
    }

    public function testAProposalIsAcceptedWithTheRightsOfTheOneWhoAcceptsOrRefusedWithAReason(): void
    {
        $identity = $this->identity(1, 'Suspect', 'Sam');
        $ref = sprintf('IDT-%06d', $identity);
        $this->call(11, ['op' => 'create', 'doc' => 'vigilance', 'subject' => $ref, 'threat' => 'high', 'measures' => 'A-OBS', 'text' => 'Tourne autour du dépôt.']);
        $this->call(11, ['op' => 'create', 'doc' => 'avis', 'subject' => $ref, 'type' => 'poi', 'threat' => 'medium', 'reasons' => 'M-TER-01']);
        $this->call(11, ['op' => 'create', 'doc' => 'vigilance', 'unknown' => 'Homme, 40 ans, veste verte, boite de la jambe gauche.']);
        $this->notified = [];
        $proposals = $this->proposals();
        // Sans le droit d'écrire, ou depuis une autre communauté : refusé, rien ne bouge.
        self::assertSame('forbidden', $proposals->decide(1, 1, true, '', self::access(1, 11, 'interne'))['error']);
        self::assertSame('forbidden', $proposals->decide(1, 1, true, '', self::access(2, 7, 'encadrement'))['error']);
        self::assertSame('notfound', $proposals->decide(1, 99, true, '', self::access(1, 7, 'encadrement'))['error']);
        self::assertSame('reason', $proposals->decide(1, 2, false, '', self::access(1, 7, 'encadrement'))['error']);
        self::assertSame(0, $this->rows('sse_doc_issues') + $this->rows('sse_wanted_notices'));

        $ok = $proposals->decide(1, 1, true, 'Bien vu', self::access(1, 7, 'encadrement'));
        self::assertSame([true, '', []], [$ok['ok'], $ok['error'], $ok['errors']]);
        self::assertMatchesRegularExpression('/^FV-\d{4}-\d{4}$/', $ok['ref']);
        $row = $this->pdo->query('SELECT state, result_ref, decided_by_user_id, decision_note FROM sse_alert_proposals WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['accepted', $ok['ref'], 7, 'Bien vu'], [$row['state'], $row['result_ref'], (int) $row['decided_by_user_id'], $row['decision_note']]);
        self::assertSame(7, (int) $this->pdo->query('SELECT issued_by_user_id FROM sse_doc_issues')->fetchColumn(), 'Émise par celui qui accepte');
        self::assertContains($ok['ref'], array_column($this->call(7, ['op' => 'list'])[2][7], 0), 'Acceptée : listée sous sa référence définitive');
        self::assertSame('state', $proposals->decide(1, 1, true, '', self::access(1, 7, 'encadrement'))['error'], 'Une seule décision');
        self::assertSame(1, $this->rows('sse_doc_issues'));
        self::assertSame([[1, 11, 'proposal_accepted']], array_map(static fn (array $n): array => [$n[0], $n[1], $n[2]['event']], $this->notified));

        $no = $proposals->decide(1, 2, false, 'Doublon d’un avis déjà demandé', self::access(1, 8, 'confidentiel'));
        self::assertSame([true, ''], [$no['ok'], $no['ref']]);
        self::assertSame('rejected', $this->pdo->query('SELECT state FROM sse_alert_proposals WHERE id = 2')->fetchColumn());
        self::assertSame(0, $this->rows('sse_wanted_notices'));
        // Un inconnu : il faut d'abord sa fiche ; avec sa référence, la proposition s'accepte.
        self::assertSame('subject', $proposals->decide(1, 3, true, '', self::access(1, 7, 'encadrement'))['error']);
        $with = $proposals->decide(1, 3, true, '', self::access(1, 7, 'encadrement'), $ref);
        self::assertTrue($with['ok']);
        self::assertSame(2, $this->rows('sse_doc_issues'));
        $actions = array_column(array_filter($this->audited, static fn (array $r): bool => ($r['object_type'] ?? '') === 'sse_alert_proposal'), 'action');
        self::assertContains('alert.proposal_accepted', $actions);
        self::assertContains('alert.proposal_rejected', $actions);
    }

    // ------------------------------------------------------------------ Signalement

    public function testReportByReferenceOrTokenIsStoredOnceAndRefusedOnAClosedAlert(): void
    {
        $this->seed();
        $token = (string) $this->pdo->query("SELECT l.token FROM sse_alert_links l JOIN sse_doc_issues d ON d.id = l.target_id WHERE l.doc = 'vigilance' AND d.ref = 'FV-2026-0001'")->fetchColumn();
        $body = ['op' => 'report', 'ref' => 'FV-2026-0001', 'what' => 'seen', 'when' => '', 'place' => 'Marché couvert', 'grid' => '072118', 'pos' => [7215.5, 11840.25], 'text' => 'Vu à pied, seul.',
            'dir' => 'Nord', 'vehicle' => 'Fourgon blanc', 'sure' => 'high', 'cid' => 'u11-000050-2', 'photo_path' => 'secret/photo.jpg', 'photo' => 'AAAA'];
        [$status, , $r] = $this->call(11, $body);
        self::assertSame(200, $status);
        self::assertCount(6, $r);
        self::assertSame([SseAlertGameService::VERSION, 'report'], [$r[0], $r[1]]);
        self::assertMatchesRegularExpression('/^SIG-\d{4}-\d{4}$/', $r[2]);
        self::assertSame(['FV-2026-0001', 'new', false], [$r[3], $r[4], $r[5]]);
        $row = $this->pdo->query('SELECT * FROM sse_alert_reports')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['seen', 'Marché couvert', '072118', 'Vu à pied, seul.', 'Nord', 'Fourgon blanc', 'high', 'game', 11], [$row['what'], $row['place'], $row['grid'], $row['body'], $row['direction'], $row['vehicle'], $row['certainty'], $row['source'], (int) $row['reporter_user_id']]);
        self::assertSame([7215.5, 11840.25], [(float) $row['pos_x'], (float) $row['pos_y']]);
        self::assertNull($row['photo_path'], 'Pas de photo par ce canal');
        self::assertSame(['report', 'FV-2026-0001', $r[2]], [$this->notified[0][2]['event'], $this->notified[0][2]['ref'], $this->notified[0][2]['report']]);
        // Renvoi : même numéro, rien de plus.
        $replay = $this->call(11, $body)[2];
        self::assertSame([$r[2], $r[3], $r[4], true], [$replay[2], $replay[3], $replay[4], $replay[5]]);
        self::assertSame(1, $this->rows('sse_alert_reports'));
        // Par le jeton de l'adresse de signalement ; sur un avis (lien créé au premier besoin).
        [, , $byToken] = $this->call(9, ['op' => 'report', 'token' => $token, 'what' => 'info', 'text' => 'Aurait quitté la ville.']);
        self::assertSame(['FV-2026-0001', 'new', false], [$byToken[3], $byToken[4], $byToken[5]]);
        self::assertNotSame($r[2], $byToken[2]);
        self::assertSame('A-0001/2026', $this->call(11, ['op' => 'report', 'ref' => 'A-0001/2026', 'text' => 'Vu au port.'])[2][3]);
        self::assertSame(3, $this->rows('sse_alert_reports'));
        self::assertSame(2, SseAlertGameService::detail($this->service()->find(1, 'interne', 11, 'FV-2026-0001'))['reports']);
        self::assertSame(2, $this->call(11, ['op' => 'detail', 'ref' => 'FV-2026-0001'])[2][7]);
        // Saisie refusée.
        foreach ([['text' => 'x'], ['when' => '2099-01-01 10:00'], ['when' => 'hier']] as $bad) {
            self::assertSame(422, $this->call(11, ['cid' => null] + $bad + $body)[0], (string) json_encode($bad));
        }
        self::assertSame(404, $this->call(11, ['op' => 'report', 'token' => str_repeat('Z', 22), 'text' => 'Vu au port.'])[0]);
        // Alerte levée ou expirée : 409 ; mais le renvoi d'un signalement déjà reçu rend toujours la même réponse.
        $closed = $this->vigilance(1, $this->identity(1, 'Ancien', 'Albert'), 'interne', ['lifted' => gmdate('Y-m-d H:i:s', time() - 600)]);
        $expired = $this->vigilance(1, $this->identity(1, 'Échu', 'Eric'), 'interne', ['until' => gmdate('Y-m-d', time() - 86400)]);
        $lifted = $this->notice(1, $this->identity(1, 'Levé', 'Luc'), ['status' => 'lifted']);
        foreach ([$closed['ref'], $expired['ref'], $lifted['ref']] as $ref) {
            [$status, $data] = $this->call(11, ['op' => 'report', 'ref' => $ref, 'text' => 'Vu au port.']);
            self::assertSame([409, 'closed'], [$status, $data['error']], $ref);
        }
        $this->pdo->exec("UPDATE sse_alert_links SET lifted_at = '2026-11-14 10:00:00' WHERE token = '{$token}'");
        self::assertTrue($this->call(11, $body)[2][5]);
        self::assertSame(3, $this->rows('sse_alert_reports'));
        self::assertContains('alertes.report', array_column($this->audited, 'action'));
        self::assertContains('alertes.refused', array_column($this->audited, 'action'));
    }

    // ------------------------------------------------------------------ Renouvellement et levée

    public function testRenewGivesANewAlertOnceAndNeedsTheRightToIssue(): void
    {
        $person = $this->person(1, 'Karimzadeh', 'Dariush');
        $identity = $this->identity(1, 'Karimzadeh', 'Dariush', null, '', $person);
        $first = $this->call(7, ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDT-%06d', $identity), 'threat' => 'high', 'measures' => 'A-OBS', 'days' => 7])[2];
        $notice = $this->notice(1, $this->identity(1, 'Recherché', 'Roger'), ['ref' => 'A-0400/2026', 'expires' => gmdate('Y-m-d H:i:s', time() + 5 * 86400)]);
        $this->pdo->exec('UPDATE sse_doc_issues SET classification = \'interne\'');

        // Sans le droit d'émettre : refus, rien de créé.
        foreach ([$first[3], $notice['ref']] as $ref) {
            [$status, $data] = $this->call(11, ['op' => 'renew', 'ref' => $ref, 'days' => 30, 'cid' => 'u11-renew-' . md5($ref)]);
            self::assertSame([403, 'forbidden'], [$status, $data['error']], $ref);
        }
        self::assertSame([1, 1], [$this->rows('sse_doc_issues'), $this->rows('sse_wanted_notices')]);

        $body = ['op' => 'renew', 'ref' => $first[3], 'days' => 60, 'reason' => 'Toujours actif', 'cid' => 'u7-000200-1'];
        [$status, , $r] = $this->call(7, $body);
        self::assertSame(200, $status);
        self::assertCount(7, $r);
        self::assertSame([SseAlertGameService::VERSION, 'renew'], [$r[0], $r[1]]);
        self::assertMatchesRegularExpression('/^FV-\d{4}-\d{4}$/', $r[2]);
        self::assertNotSame($first[3], $r[2]);
        self::assertSame(gmdate('Y-m-d', time() + 60 * 86400), $r[3]);
        self::assertStringEndsWith('/atak/sse/signalement/' . $r[5], $r[4]);
        self::assertNotSame($first[5], $r[5]);
        self::assertFalse($r[6]);
        $replay = $this->call(7, $body)[2];
        self::assertSame([$r[2], $r[3], $r[4], $r[5], true], array_slice($replay, 2));
        self::assertSame(2, $this->rows('sse_doc_issues'));
        // La précédente est close (« renouvelée ») et reste lisible ; la nouvelle est en cours.
        $list = array_column($this->call(7, ['op' => 'list', 'doc' => 'vigilance'])[2][7], 0);
        self::assertSame([$r[2]], $list);
        $old = $this->call(7, ['op' => 'detail', 'ref' => $first[3]])[2];
        self::assertSame('lifted', self::named($old[2])['state']);
        self::assertStringContainsString($r[2], $old[9]);
        self::assertSame(409, $this->call(7, ['op' => 'renew', 'ref' => $first[3], 'days' => 30])[0], 'Une fiche levée ne se renouvelle pas');

        // Avis : nouvel avis, l'ancien clos ; son adresse reste et renvoie vers le nouveau.
        $nb = ['op' => 'renew', 'ref' => 'A-0400/2026', 'days' => 90, 'cid' => 'u8-000201-1'];
        [$status, , $n] = $this->call(8, $nb);
        self::assertSame(200, $status);
        self::assertNotSame('A-0400/2026', $n[2]);
        self::assertSame(gmdate('Y-m-d', time() + 90 * 86400), $n[3]);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9]{22}$/', $n[5]);
        self::assertSame([$n[2], $n[3], $n[4], $n[5], true], array_slice($this->call(8, $nb)[2], 2));
        self::assertSame(2, $this->rows('sse_wanted_notices'));
        self::assertSame([$n[2]], array_column($this->call(8, ['op' => 'list', 'doc' => 'avis'])[2][7], 0));
        self::assertSame($n[2], $this->pdo->query("SELECT l.renewed_by_ref FROM sse_alert_links l WHERE l.doc = 'avis' AND l.target_id = " . $notice['id'])->fetchColumn());
        self::assertContains('alertes.renew', array_column($this->audited, 'action'));
    }

    public function testLiftFollowsTheRulesOfTheSiteNeedsAReasonAndAnswersTheSameOnAResend(): void
    {
        $this->seed();
        // Vigilance : gérer « fichiers » (7) ou en être l'émetteur (8) ; un simple lecteur non.
        $own = $this->vigilance(1, $this->identity(1, 'Mien', 'Marc'), 'interne', ['by' => 8]);
        $other = $this->vigilance(1, $this->identity(1, 'Autre', 'Anne'), 'interne', ['by' => 7]);
        foreach ([[11, 'FV-2026-0001'], [9, 'A-0001/2026'], [8, $other['ref']]] as [$user, $ref]) {
            [$status, $data] = $this->call($user, ['op' => 'lift', 'ref' => $ref, 'reason' => 'Sujet interpellé', 'cid' => 'lift-' . $user . '-0001']);
            self::assertSame([403, 'forbidden'], [$status, $data['error']], $ref);
        }
        foreach (['', 'ab', str_repeat('x', 501)] as $reason) {
            [$status, $data] = $this->call(7, ['op' => 'lift', 'ref' => 'FV-2026-0001', 'reason' => $reason]);
            self::assertSame([422, 'invalid'], [$status, $data['error']]);
        }
        self::assertSame(0, $this->rows('sse_alert_links WHERE lifted_at IS NOT NULL'));
        self::assertSame(0, $this->rows("sse_wanted_notices WHERE status <> 'active' AND tenant_id = 1"));

        $body = ['op' => 'lift', 'ref' => 'FV-2026-0001', 'reason' => 'Sujet interpellé', 'cid' => 'u7-000300-1'];
        [$status, , $r] = $this->call(7, $body);
        self::assertSame(200, $status);
        self::assertCount(5, $r);
        self::assertSame([SseAlertGameService::VERSION, 'lift', 'FV-2026-0001'], array_slice($r, 0, 3));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $r[3]);
        self::assertFalse($r[4]);
        self::assertSame([$r[2], $r[3], true], array_slice($this->call(7, $body)[2], 2), 'Renvoi : même réponse');
        self::assertSame(1, $this->rows('sse_alert_links WHERE lifted_at IS NOT NULL'));
        [$status, $data] = $this->call(8, ['op' => 'lift', 'ref' => 'FV-2026-0001', 'reason' => 'Encore', 'cid' => 'u8-000300-9']);
        self::assertSame([409, 'closed'], [$status, $data['error']], 'Déjà levée par un autre');
        self::assertSame(409, $this->call(7, ['op' => 'lift', 'ref' => 'FV-2026-0001', 'reason' => 'Encore'])[0], 'Sans identifiant client : déjà levée');
        self::assertSame(200, $this->call(8, ['op' => 'lift', 'ref' => $own['ref'], 'reason' => 'Fin de la surveillance'])[0], 'L’émetteur lève sa fiche');
        self::assertNotContains('FV-2026-0001', array_column($this->call(11, ['op' => 'list'])[2][7], 0));
        self::assertSame('Sujet interpellé', $this->call(11, ['op' => 'detail', 'ref' => 'FV-2026-0001'])[2][9]);

        // Avis : écrire dans « fichiers » (8 suffit), comme sur le site.
        $nb = ['op' => 'lift', 'ref' => 'A-0001/2026', 'reason' => 'Capturée par la patrouille', 'cid' => 'u8-000301-1'];
        [$status, , $n] = $this->call(8, $nb);
        self::assertSame(200, $status);
        self::assertSame(['A-0001/2026', false], [$n[2], $n[4]]);
        self::assertSame('lifted', $this->pdo->query("SELECT status FROM sse_wanted_notices WHERE ref = 'A-0001/2026'")->fetchColumn());
        self::assertSame([$n[2], $n[3], true], array_slice($this->call(8, $nb)[2], 2));
        self::assertNotContains('A-0001/2026', array_column($this->call(11, ['op' => 'list'])[2][7], 0));
        self::assertContains('alertes.lift', array_column($this->audited, 'action'));
    }

    // ------------------------------------------------------------------ Gardes

    public function testWritesNeedAnAccountAndReadsDoNot(): void
    {
        $this->seed();
        // Jeton de jeu sans compte relié (clé de communauté seule) : lectures au plancher, écritures refusées.
        [$status, , $page] = $this->call(0, ['op' => 'list']);
        self::assertSame(200, $status);
        self::assertSame('interne', $page[8][0]);
        self::assertSame([false, false, false, false], $this->call(0, ['op' => 'catalog'])[2][6]);
        self::assertSame(200, $this->call(0, ['op' => 'detail', 'ref' => 'A-0001/2026'])[0]);
        foreach ([
            ['op' => 'create', 'doc' => 'vigilance', 'unknown' => 'Homme, 40 ans, veste verte.'],
            ['op' => 'report', 'ref' => 'A-0001/2026', 'text' => 'Vu au port.'],
            ['op' => 'renew', 'ref' => 'A-0001/2026'],
            ['op' => 'lift', 'ref' => 'A-0001/2026', 'reason' => 'Motif'],
            ['op' => 'ack', 'ref' => 'A-0001/2026'],
        ] as $body) {
            [$status, $data] = $this->call(0, $body);
            self::assertSame([403, 'account_required'], [$status, $data['error']], $body['op']);
        }
        self::assertSame(0, $this->rows('sse_alert_reports') + $this->rows('sse_alert_acks') + $this->rows('sse_alert_proposals'));
        // Sans clé, ou avec une clé inconnue : rien.
        unset($_SERVER['HTTP_AUTHORIZATION']);
        ComspecApiKeyAuth::resetForTests();
        $this->audited = [];
        $resp = $this->controller()->kindResponse(['kind' => SseAlertGameService::KIND, 'op' => 'list', 'tenant_id' => 1]);
        self::assertSame(401, $resp->statusCode());
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('z', 64);
        ComspecApiKeyAuth::resetForTests();
        $resp = $this->controller()->kindResponse(['kind' => SseAlertGameService::KIND, 'op' => 'list', 'tenant_id' => 1]);
        self::assertSame(401, $resp->statusCode());
        self::assertSame('unauthorized', json_decode($resp->body(), true)['error']);
        self::assertStringNotContainsString('A-0001', $resp->body());
        self::assertSame([], $this->audited, 'Un accès refusé ne lit rien');
    }

    public function testWithoutTheAlertTablesReadsAreServedWithoutAddressAndWritesAreUnavailable(): void
    {
        SseAlertSqlite::close();
        $this->open(false);
        self::assertFalse(SseAlertRepository::ready());
        $this->seed();
        [$status, , $page] = $this->call(7, ['op' => 'list']);
        self::assertSame(200, $status);
        $alerts = array_map([self::class, 'named'], $page[7]);
        self::assertSame(['A-0001/2026', 'FV-2026-0001', 'FV-2026-0002'], array_column($alerts, 'ref'));
        foreach ($alerts as $a) {
            self::assertSame(['', '', false], [$a['url'], $a['token'], $a['acked']], $a['ref']);
            self::assertNotSame('', $a['service']);
        }
        $detail = $this->call(7, ['op' => 'detail', 'ref' => 'FV-2026-0001'])[2];
        self::assertSame(['', ''], [$detail[2][22], $detail[2][23]]);
        self::assertSame([false, false, false, false], $this->call(7, ['op' => 'catalog'])[2][6]);
        foreach ([
            ['op' => 'create', 'doc' => 'vigilance', 'unknown' => 'Homme, 40 ans, veste verte.'],
            ['op' => 'report', 'ref' => 'A-0001/2026', 'text' => 'Vu au port.'],
            ['op' => 'renew', 'ref' => 'FV-2026-0001'],
            ['op' => 'lift', 'ref' => 'A-0001/2026', 'reason' => 'Motif de levée'],
            ['op' => 'ack', 'ref' => 'A-0001/2026'],
        ] as $body) {
            [$status, $data] = $this->call(7, $body);
            self::assertSame([503, 'unavailable'], [$status, $data['error']], $body['op']);
        }
        self::assertSame('active', $this->pdo->query("SELECT status FROM sse_wanted_notices WHERE ref = 'A-0001/2026'")->fetchColumn());
        self::assertSame(3, $this->rows('sse_doc_issues'));
        self::assertSame(0, $this->rows('sse_wanted_notices WHERE tenant_id = 1 AND id > 1'));
    }

    public function testEveryGroupHasItsOwnRateLimitAndMaintenanceStopsEverything(): void
    {
        $this->seed();
        $expect = [
            'list' => ['sse_game_alertes', 120, 60], 'catalog' => ['sse_game_alertes', 120, 60], 'detail' => ['sse_game_alertes', 120, 60],
            'report' => ['sse_game_alertes_report', 12, 600], 'create' => ['sse_game_alertes_create', 6, 600],
            'renew' => ['sse_game_alertes_manage', 12, 600], 'lift' => ['sse_game_alertes_manage', 12, 600], 'ack' => ['sse_game_alertes_ack', 60, 600],
        ];
        foreach ($expect as $op => [$prefix, $max, $window]) {
            $this->rates = [];
            $this->trip = [$prefix];
            [$status, $data] = $this->call(7, ['op' => $op, 'ref' => 'A-0001/2026', 'reason' => 'Motif de levée', 'text' => 'Vu au port.', 'doc' => 'vigilance', 'unknown' => 'Homme, 40 ans, veste verte.']);
            self::assertSame([429, 'rate_limited', $window], [$status, $data['error'], $data['retry_after']], $op);
            self::assertSame([[$prefix . '|1|u7', $max, $window]], $this->rates, $op);
            self::assertStringNotContainsString('A-0001', (string) json_encode($data));
        }
        self::assertSame((string) GameSseAlertesApiController::WRITE_WINDOW, $this->controller()->kindResponse(['kind' => SseAlertGameService::KIND, 'op' => 'ack', 'ref' => 'A-0001/2026'])->headerValue('Retry-After'));
        self::assertSame(0, $this->rows('sse_alert_reports') + $this->rows('sse_alert_acks') + $this->rows('sse_alert_proposals'));
        self::assertSame('active', $this->pdo->query("SELECT status FROM sse_wanted_notices WHERE ref = 'A-0001/2026'")->fetchColumn());
        // Le débit est compté par compte.
        $this->trip = [];
        $this->rates = [];
        $this->call(11, ['op' => 'list']);
        self::assertSame('sse_game_alertes|1|u11', $this->rates[0][0]);
        // Maintenance : 503, pour une lecture comme pour une écriture.
        $this->gameToken(1, 7);
        foreach (['list', 'ack'] as $op) {
            $resp = $this->controller('Maintenance en cours')->kindResponse(['kind' => SseAlertGameService::KIND, 'op' => $op, 'ref' => 'A-0001/2026']);
            self::assertSame(503, $resp->statusCode());
            self::assertSame(['error' => 'maintenance', 'message' => 'Maintenance en cours'], json_decode($resp->body(), true));
        }
        self::assertSame(0, $this->rows('sse_alert_acks'));
    }

    public function testEveryAccessIsJournalledAsAGameAlert(): void
    {
        $this->seed();
        $this->call(7, ['op' => 'list']);
        $this->call(7, ['op' => 'detail', 'ref' => 'FV-2026-0001']);
        $this->call(7, ['op' => 'ack', 'ref' => 'FV-2026-0001']);
        $this->call(7, ['op' => 'detail', 'ref' => 'FV-2026-0003']);
        $rows = array_values(array_filter($this->audited, static fn (array $r): bool => ($r['object_type'] ?? '') === 'game_alert'));
        self::assertSame(['alertes.read', 'alertes.detail', 'alertes.ack', 'alertes.detail'], array_column($rows, 'action'));
        self::assertSame([1, 7, 'Téléphone ATAK', 'encadrement'], [$rows[0]['tenant_id'], $rows[0]['actor_user_id'], $rows[0]['actor_label'], $rows[0]['after']['level']]);
        self::assertSame('FV-2026-0001', $rows[1]['object_uuid']);
        self::assertSame('refusée ou inconnue', $rows[3]['reason']);
    }

    // ------------------------------------------------------------------ Routes JSON

    public function testRestRoutesAnswerJsonObjectsUnderTheSameGuards(): void
    {
        $this->seed();
        $this->gameToken(1, 8);
        $_GET = ['tenant_id' => '2'];
        $catalog = $this->controller()->catalog(new Request());
        self::assertSame(200, $catalog->statusCode());
        $c = json_decode($catalog->body(), true);
        self::assertSame(['vigilance' => true, 'notice' => true, 'lift' => true, 'report' => true], $c['rights']);
        self::assertSame(['key' => 'watch', 'fr' => 'VIGILANCE', 'en' => 'BE ON THE LOOKOUT', 'rank' => 1, 'hex' => '#F2C200', 'shape' => 'triangle'], $c['level'][0]);
        foreach (array_keys(SseAlertGameService::CATALOG) as $section) {
            self::assertNotSame([], $c[$section], $section);
        }
        self::assertStringStartsWith('W/"ssealertes-cat-', (string) $catalog->headerValue('ETag'));
        $_GET = ['since_rev' => $c['revision']];
        self::assertTrue(json_decode($this->controller()->catalog(new Request())->body(), true)['unchanged']);
        $_GET = [];
        $_SERVER['HTTP_IF_NONE_MATCH'] = (string) $catalog->headerValue('ETag');
        self::assertSame(304, $this->controller()->catalog(new Request())->statusCode());
        unset($_SERVER['HTTP_IF_NONE_MATCH']);

        $list = $this->controller()->index(new Request());
        self::assertSame(200, $list->statusCode());
        $l = json_decode($list->body(), true);
        self::assertSame('confidentiel', $l['level']);
        self::assertSame(['A-0001/2026', 'FV-2026-0001', 'FV-2026-0003', 'FV-2026-0002'], array_column($l['alerts'], 'ref'));
        self::assertSame(SseAlertGameService::COLUMNS, array_keys($l['alerts'][0]));
        self::assertStringNotContainsString('A-0900/2026', $list->body());
        self::assertStringStartsWith('W/"ssealertes-', (string) $list->headerValue('ETag'));
        $_GET = ['since_rev' => $l['revision']];
        $same = json_decode($this->controller()->index(new Request())->body(), true);
        self::assertTrue($same['unchanged']);
        self::assertArrayNotHasKey('alerts', $same);
        $_GET = ['format' => 'sqf', 'page' => '0', 'doc' => 'avis'];
        $sqf = json_decode($this->controller()->index(new Request())->body(), true);
        self::assertSame(['A-0001/2026'], array_column(self::parse($sqf['ref'])[7], 0));
        $_GET = [];

        $detail = $this->controller()->detail(new Request(), ['ref' => rawurlencode('A-0001/2026')]);
        self::assertSame(200, $detail->statusCode());
        $d = json_decode($detail->body(), true);
        self::assertSame('A-0001/2026', $d['alert']['ref']);
        self::assertSame(['M-TER-01 Planification d’attaque'], $d['reasons']);
        self::assertSame(['version', 'level', 'alert', 'reasons', 'vehicles', 'people', 'physical', 'reports', 'lifted_at', 'lift_reason', 'donts'], array_keys($d));
        self::assertSame(404, $this->controller()->detail(new Request(), ['ref' => 'FV-2026-9999'])->statusCode());
        $this->gameToken(1, 11);
        self::assertSame(404, $this->controller()->detail(new Request(), ['ref' => 'FV-2026-0003'])->statusCode());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $post = fn (string $action, array $body, array $params = []): array => (function () use ($action, $body, $params): array {
            $resp = $this->controller(null, (string) json_encode($body))->{$action}(new Request(), $params);

            return [$resp->statusCode(), (array) json_decode($resp->body(), true)];
        })();
        [$status, $report] = $post('report', ['text' => 'Vu au port.', 'sure' => 'low', 'cid' => 'rest-000001-1', 'ref' => 'FV-2026-0003', 'tenant_id' => 2], ['ref' => rawurlencode('A-0001/2026')]);
        self::assertSame(200, $status);
        self::assertSame(['version', 'report', 'ref', 'status', 'replay'], array_keys($report));
        self::assertSame(['A-0001/2026', 'new', false], [$report['ref'], $report['status'], $report['replay']], 'La référence est celle de l’adresse, jamais du corps');
        [$status, $ack] = $post('ack', [], ['ref' => 'FV-2026-0001']);
        self::assertSame([200, ['version', 'ref', 'acked_at']], [$status, array_keys($ack)]);
        [$status, $refused] = $post('lift', ['reason' => 'Sujet interpellé'], ['ref' => 'FV-2026-0001']);
        self::assertSame([403, 'forbidden'], [$status, $refused['error']]);
        [$status, $proposal] = $post('create', ['doc' => 'vigilance', 'unknown' => 'Homme, 40 ans, veste verte, boite.', 'cid' => 'rest-000002-1']);
        self::assertSame([200, 'proposal', 'PROP-1', false], [$status, $proposal['state'], $proposal['ref'], $proposal['replay']]);
        self::assertSame(['version', 'state', 'ref', 'url', 'token', 'message', 'replay'], array_keys($proposal));
        $this->gameToken(1, 7);
        [$status, $lift] = $post('lift', ['reason' => 'Sujet interpellé'], ['ref' => 'FV-2026-0001']);
        self::assertSame([200, ['version', 'ref', 'lifted_at', 'replay']], [$status, array_keys($lift)]);
        [$status, $renew] = $post('renew', ['days' => 30], ['ref' => rawurlencode('A-0001/2026')]);
        self::assertSame([200, ['version', 'ref', 'until', 'url', 'token', 'replay']], [$status, array_keys($renew)]);
        self::assertNotSame('A-0001/2026', $renew['ref']);
    }

    // ------------------------------------------------------------------ Textes et câblage

    public function testNothingSentToTheGameSpeaksOfTheGame(): void
    {
        $this->seed();
        $identity = $this->identity(1, 'Suspect', 'Sam');
        $sent = [];
        foreach ([
            [7, ['op' => 'list']], [7, ['op' => 'detail', 'ref' => 'A-0001/2026']], [7, ['op' => 'detail', 'ref' => 'NOPE']],
            [11, ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDT-%06d', $identity)]], [7, ['op' => 'create', 'doc' => 'avis', 'subject' => sprintf('IDT-%06d', $identity)]],
            [7, ['op' => 'create', 'doc' => 'vigilance', 'subject' => sprintf('IDT-%06d', $identity), 'threat' => 'high']],
            [11, ['op' => 'report', 'ref' => 'A-0001/2026', 'text' => 'x']], [11, ['op' => 'lift', 'ref' => 'A-0001/2026', 'reason' => 'Motif de levée']],
            [0, ['op' => 'ack', 'ref' => 'A-0001/2026']], [7, ['op' => 'lift', 'ref' => 'A-0001/2026', 'reason' => '']], [11, ['op' => 'renew', 'ref' => 'FV-2026-0001']],
        ] as [$user, $body]) {
            $sent[] = $this->call($user, $body)[1];
        }
        $this->trip = ['sse_game_alertes'];
        $sent[] = $this->call(7, ['op' => 'list'])[1];
        $text = (string) json_encode($sent, JSON_UNESCAPED_UNICODE);
        self::assertSame(0, preg_match('/fiction|jeu de rôle|\bRP\b|simulation|roleplay|role-play/iu', $text), $text);
    }

    public function testRoutesAndTheKindChannelAreDeclared(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $class = '\\App\\Controllers\\Api\\Game\\GameSseAlertesApiController::class';
        $at = [];
        foreach ([
            ["get('/api/game/v1/sse/alertes/catalog'", 'catalog'], ["get('/api/game/v1/sse/alertes'", 'index'], ["get('/api/game/v1/sse/alertes/{ref}'", 'detail'],
            ["post('/api/game/v1/sse/alertes'", 'create'], ["post('/api/game/v1/sse/alertes/{ref}/report'", 'report'], ["post('/api/game/v1/sse/alertes/{ref}/renew'", 'renew'],
            ["post('/api/game/v1/sse/alertes/{ref}/lift'", 'lift'], ["post('/api/game/v1/sse/alertes/{ref}/ack'", 'ack'],
        ] as [$route, $action]) {
            $line = '$router->' . $route . ', [' . $class . ", '" . $action . "']);";
            self::assertStringContainsString($line, $routes);
            self::assertTrue(method_exists(GameSseAlertesApiController::class, $action));
            $at[$action] = strpos($routes, $line);
        }
        self::assertLessThan($at['detail'], $at['catalog'], '« catalog » avant « {ref} »');
        self::assertGreaterThan(strpos($routes, "'/api/game/v1/sse/fiches/{ref}'"), $at['catalog']);
        $identity = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/SseIdentityApiController.php');
        self::assertStringContainsString('SseAlertGameService::KIND', $identity);
        self::assertStringContainsString('GameSseAlertesApiController', $identity);
        self::assertLessThan(strpos($identity, 'SseAlertGameService::KIND'), strpos($identity, 'SseFichesGameService::KIND_SHARE'));
        // Les niveaux du contrat (§ 3.1.1) sont ceux de la brique partagée.
        self::assertSame(['watch', 'reinforced', 'caution', 'danger'], array_keys(SseAlertLevel::LEVELS));
    }
}
