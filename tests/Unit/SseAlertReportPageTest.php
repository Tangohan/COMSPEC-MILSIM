<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseAlertReportController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Notifications\ActivityHubPresentationService;
use App\Services\Sse\SseAlertGameService;
use App\Services\Sse\SseAlertProposals;
use App\Services\Sse\SseAlertReportPhotos;
use App\Services\Sse\SseAlertService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseTriageInbox;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseImageStorage;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAlertSqlite;

/**
 * Signalements des alertes SSE sur le site : page ouverte depuis le QR code (avis et fiche de vigilance), même « introuvable » pour un
 * jeton inconnu, d'une autre communauté, révoqué ou au-dessus de l'habilitation, invité refusé, CSRF, accusé de réception, débit,
 * lecture seule d'une alerte levée, photo (type par le contenu, taille, EXIF retiré, route contrôlée), états, révocation, propositions,
 * QR code en SVG, liste, notifications et « À trier ». Base sqlite réelle (tables de l'étape 4830 créées depuis la migration).
 *
 * Comptes : 7 gestionnaire (Très restreint, écrit et gère « fichiers »), 8 lecteur Confidentiel (lit seulement), 9 plancher (lit, interne).
 */
final class SseAlertReportPageTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<array{0: int, 1: int, 2: array<string, mixed>}> */
    private array $notified = [];

    /** @var array{0: string, 1: array<string, mixed>}|null dernière vue demandée */
    private ?array $last = null;

    private string $privateDir = '';

    private int $seq = 0;

    protected function setUp(): void
    {
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'files' => $_FILES ?? [], 'server' => $_SERVER, 'env' => $_ENV];
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseAlertSqlite::open(true);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_ENV['APP_URL'] = 'https://athena.example';
        $_SERVER['HTTP_HOST'] = 'athena.example';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->notified = [];
        $this->last = null;
        $this->seq = 0;
        $this->privateDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sal-private-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        SseAlertSqlite::close();
        if ($this->privateDir !== '' && is_dir($this->privateDir)) {
            foreach (glob($this->privateDir . '/*/*') ?: [] as $f) {
                @unlink($f);
            }
            foreach (glob($this->privateDir . '/*') ?: [] as $d) {
                @rmdir($d);
            }
            @rmdir($this->privateDir);
        }
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_FILES = $this->saved['files'];
        $_SERVER = $this->saved['server'];
        $_ENV = $this->saved['env'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
    }

    // ------------------------------------------------------------------ Montage

    private static function access(int $tenant, int $user, string $level, bool $manage = false, bool $signed = true, bool $read = true): SseSheetAccess
    {
        $wanted = new SseWantedAccess(!$signed, $manage, $manage, true, $tenant, $signed ? $user : null, 'RAVEN-' . $user, $read, $manage);

        return new SseSheetAccess($wanted, static fn (string $s, string $r): bool => $manage || $r === 'read', $signed, $level);
    }

    private function manager(): SseSheetAccess
    {
        return self::access(1, 7, 'tres_restreint', true);
    }

    private function reader(): SseSheetAccess
    {
        return self::access(1, 8, 'confidentiel');
    }

    private function floor(): SseSheetAccess
    {
        return self::access(1, 9, 'interne');
    }

    private function alertService(): SseAlertService
    {
        return new SseAlertService(null, null, null, function (int $tenant, int $user, array $payload): void {
            $this->notified[] = [$tenant, $user, $payload];
        }, static fn (int $tenant): array => [7, 8]);
    }

    private function proposals(SseAlertService $alerts): SseAlertProposals
    {
        return new SseAlertProposals($alerts, null, null, null, null, function (int $tenant, int $user, array $payload): void {
            $this->notified[] = [$tenant, $user, $payload];
        }, static fn (int $tenant): array => [7, 8], static function (): void {
        });
    }

    private function controller(SseSheetAccess $access): SseAlertReportController
    {
        $alerts = $this->alertService();

        return new SseAlertReportController(null, null, $access, $alerts, new SseAlertGameService(null, null, $alerts), $this->proposals($alerts), new SseAlertReportPhotos($this->privateDir), function (string $view, array $data): Response {
            $this->last = [$view, $data];

            return (new Response())->setBody('VIEW:' . $view . ':' . ($data['mode'] ?? ''));
        }, true);
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, bool $csrf = true): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $post + ($csrf ? ['_csrf_token' => Csrf::token()] : []);

        return new Request();
    }

    private function get(array $query = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $query;
        $_POST = [];

        return new Request();
    }

    /** @return array<string, string> */
    private static function form(array $over = []): array
    {
        return $over + ['what' => 'seen', 'when' => gmdate('Y-m-d\TH:i', time() - 300), 'place' => 'Entrepôt du quai 4', 'grid' => '045 123', 'text' => 'Fourgon blanc garé devant l’entrepôt, deux hommes à bord.',
            'dir' => 'vers le nord', 'vehicle' => 'Fourgon blanc', 'sure' => 'high'];
    }

    // ------------------------------------------------------------------ Jeu de données

    private function identity(int $tenant, string $last, string $first, ?int $userId = null): int
    {
        return (new SseIdentityRepository())->create($tenant, ['last_name' => $last, 'first_name' => $first, 'kind' => 'civil', 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'birth_place' => 'Pyrgos', 'user_id' => $userId]);
    }

    /** @param array<string, mixed> $o @return array{id: int, ref: string, token: string} */
    private function notice(int $tenant, int $identity, array $o = []): array
    {
        $ref = $o['ref'] ?? sprintf('A-%04d/2026', ++$this->seq);
        $status = $o['status'] ?? 'active';
        $st = $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, reason, instructions, zone, last_seen_place, last_seen_at,
            authority, contact_label, contact_detail, status, active_key, issued_at, expires_at, closed_at, closed_reason, closed_by_user_id, created_at, updated_at)
            VALUES (:t, :i, :ref, 2026, :seq, :type, :threat, :armed, :reason, :instr, :zone, :place, :seen, :auth, :cl, :cd, :status, :ak, '2026-11-01 08:00:00', NULL, :closed, :cr, NULL, '2026-11-01 08:00:00', '2026-11-12 09:00:00')");
        $st->execute(['t' => $tenant, 'i' => $identity, 'ref' => $ref, 'seq' => ++$this->seq, 'type' => 'hvt', 'threat' => 'critical', 'armed' => 'yes', 'reason' => 'Trafic d’armes sur le port',
            'instr' => "Ne pas intervenir seul\nSignaler au service", 'zone' => 'Pyrgos', 'place' => 'Entrepôt quai 4', 'seen' => '2026-11-10 21:30:00', 'auth' => 'Bureau de recherche',
            'cl' => 'Cellule de coordination des recherches', 'cd' => 'Canal SABRE 40.500', 'status' => $status, 'ak' => $status === 'active' ? 1 : null,
            'closed' => $status === 'active' ? null : gmdate('Y-m-d H:i:s', time() - 3600), 'cr' => $status === 'active' ? null : 'Affaire close']);
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO sse_wanted_notice_extras (notice_id, tenant_id, codes_json, physical_json, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([$id, $tenant,
            json_encode(['reasons' => [['code' => 'M-TER-01', 'fr' => 'Planification d’attaque', 'en' => 'Attack planning']], 'measures' => [['code' => 'A-NOI', 'fr' => 'Ne pas intervenir seul', 'en' => 'Do not engage alone'], ['code' => 'A-OBS', 'fr' => 'Observer', 'en' => 'Observe']]], JSON_UNESCAPED_UNICODE), '{}', '2026-11-12 09:00:00']);
        $link = (new SseAlertRepository())->ensureLink($tenant, 'avis', $id, ['report' => ['code' => '', 'fr' => 'Cellule de coordination des recherches', 'en' => 'Search coordination cell', 'channel' => 'Canal SABRE 40.500', 'delay' => '15m']]);

        return ['id' => $id, 'ref' => $ref, 'token' => (string) $link['token']];
    }

    /** @param array<string, mixed> $o @return array{id: int, ref: string, token: string} */
    private function vigilance(int $tenant, int $identity, string $classification, array $o = []): array
    {
        $ref = $o['ref'] ?? sprintf('FV-2026-%04d', ++$this->seq);
        $token = SseAlertRepository::newToken();
        $report = ['code' => 'SVC-COORD', 'fr' => 'Cellule de coordination', 'en' => 'Coordination Cell', 'ac' => 'CCR', 'auth_fr' => 'Bureau', 'auth_en' => 'Bureau', 'channel' => 'Canal SABRE 40.500', 'delay' => '15m', 'color' => '#1F3A5F', 'emblem' => 'hex'];
        $facts = ['display' => $o['display'] ?? 'DUPONT Marc', 'aliases' => ['Le Rouquin'], 'threat' => 'medium', 'armed' => 'no', 'zone' => 'Kavala', 'last_seen_place' => 'Marché', 'last_seen_at' => '2026-11-09 18:00'];
        $alert = ['key' => 'reinforced', 'rank' => 2, 'order' => 'hold', 'measures' => [['code' => 'A-OBS', 'fr' => 'Observer et rendre compte', 'en' => 'Observe and report'], ['code' => 'A-NOI', 'fr' => 'Ne pas intervenir seul', 'en' => 'Do not engage alone']],
            'reasons' => [['code' => 'M-TER-01', 'fr' => 'Planification d’attaque', 'en' => 'Attack planning']], 'donts' => [['Ne pas intervenir seul.'], ['Do not engage alone.']], 'report' => $report, 'token' => $token];
        $until = $o['until'] ?? '2099-01-01';
        $snap = ['data' => ['facts' => $facts, 'alert' => $alert, 'valid_until' => $until, 'issued_at' => '2026-11-05', 'authority' => ['Bureau', 'Bureau']], 'params' => ['validity' => 30]];
        $st = $this->pdo->prepare('INSERT INTO sse_doc_issues (tenant_id, person_id, identity_id, model, ref, ref_year, ref_seq, lang, classification, faces, texts_json, snapshot_json, sensitive_json, issued_by_user_id, issued_by_label, issued_at)
            VALUES (:t, NULL, :i, \'vigilance\', :ref, 2026, :seq, \'fr\', :cl, \'auto\', :texts, :snap, \'[]\', 8, \'ANALYSTE\', \'2026-11-05 10:00:00\')');
        $st->execute(['t' => $tenant, 'i' => $identity, 'ref' => $ref, 'seq' => ++$this->seq, 'cl' => $classification,
            'texts' => json_encode(['reason' => 'Surveillance du port', 'instructions' => 'Rester à distance', 'behaviour' => '', 'mention' => ''], JSON_UNESCAPED_UNICODE), 'snap' => json_encode($snap, JSON_UNESCAPED_UNICODE)]);
        $id = (int) $this->pdo->lastInsertId();
        $link = (new SseAlertRepository())->ensureLink($tenant, 'vigilance', $id, ['token' => $token, 'report' => $report, 'valid_until' => $until]);
        if (isset($o['lifted'])) {
            $this->pdo->prepare('UPDATE sse_alert_links SET lifted_at = ?, lift_reason = ?, lifted_by_user_id = 7 WHERE id = ?')->execute([$o['lifted'], 'Sujet interpellé', (int) $link['id']]);
        }

        return ['id' => $id, 'ref' => $ref, 'token' => $token];
    }

    /** @return array{notice: array{id: int, ref: string, token: string}, vigilance: array{id: int, ref: string, token: string}} */
    private function seed(): array
    {
        return [
            'notice' => $this->notice(1, $this->identity(1, 'Karimzadeh', 'Élodie'), ['ref' => 'A-0001/2026']),
            'vigilance' => $this->vigilance(1, $this->identity(1, 'Dupont', 'Marc'), 'interne', ['ref' => 'FV-2026-0001']),
        ];
    }

    private function rows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    /** Un signalement existant, créé comme le fait la page. @return array<string, mixed> */
    private function report(string $token, array $over = [], ?SseSheetAccess $by = null): array
    {
        $c = $this->controller($by ?? $this->reader());
        $res = $c->submit($this->post(self::form($over)), ['token' => $token]);
        self::assertSame(200, $res->statusCode(), (string) json_encode($this->last[1]['errors'] ?? []));

        return (array) $this->last[1]['report'];
    }

    // ------------------------------------------------------------------ La page

    public function testPageForAValidNoticeTokenShowsTheAlertHeader(): void
    {
        $s = $this->seed();
        $res = $this->controller($this->reader())->show($this->get(), ['token' => $s['notice']['token']]);

        self::assertSame(200, $res->statusCode());
        self::assertSame('atak.sse.alerts.report', $this->last[0]);
        $d = $this->last[1];
        self::assertSame('form', $d['mode']);
        self::assertSame('A-0001/2026', $d['view']['ref']);
        self::assertSame('avis', $d['view']['doc']);
        self::assertSame('NOTICE ROUGE', $d['view']['notice']['title'], 'notice de couleur : sa couleur et son titre');
        self::assertSame('#BE121E', $d['view']['notice']['hex']);
        self::assertSame(4, $d['view']['level']['rank']);
        self::assertSame('octagon', $d['view']['level']['shape']);
        self::assertSame('NE PAS APPRÉHENDER — SIGNALER', $d['view']['order'], 'consigne dominante');
        self::assertSame('Cellule de coordination des recherches', $d['view']['service']);
        self::assertSame('Canal SABRE 40.500', $d['view']['channel']);
        self::assertSame('Sous 15 minutes', $d['view']['delay']);
        self::assertNotSame([], $d['view']['conduct']);
        self::assertLessThanOrEqual(6, count($d['view']['conduct']));
        self::assertGreaterThanOrEqual(8, strlen($d['cid']));
        self::assertSame(url('atak/sse/signalement/' . $s['notice']['token']), $d['action']);
    }

    public function testPageForAVigilanceTokenShowsItsLevelShapeAndRank(): void
    {
        $s = $this->seed();
        $this->controller($this->reader())->show($this->get(), ['token' => $s['vigilance']['token']]);
        $v = $this->last[1]['view'];

        self::assertSame('vigilance', $v['doc']);
        self::assertSame('FV-2026-0001', $v['ref']);
        self::assertSame(2, $v['level']['rank']);
        self::assertSame('triangle', $v['level']['shape']);
        self::assertNull($v['notice']);
        self::assertSame('VIGILANCE RENFORCÉE', $v['level']['title']);
        self::assertSame('#F08C00', $v['level']['hex']);
        self::assertSame('NE PAS APPRÉHENDER — SIGNALER', $v['order']);
        self::assertSame('DUPONT Marc', $v['name']);
        self::assertSame('active', $v['state']);
    }

    public function testUnknownOtherCommunityRevokedAndAboveClearanceTokensAllGiveTheSameNotFoundPage(): void
    {
        $s = $this->seed();
        $secret = $this->vigilance(1, $this->identity(1, 'Secret', 'Dossier'), 'confidentiel', ['ref' => 'FV-2026-0003']);
        $other = $this->notice(2, $this->identity(2, 'Bravo', 'Cible'), ['ref' => 'A-0900/2026']);
        $revoked = $this->vigilance(1, $this->identity(1, 'Revoque', 'Rémi'), 'interne', ['ref' => 'FV-2026-0004']);
        $this->pdo->exec("UPDATE sse_alert_links SET revoked_at = '2026-11-14 10:00:00' WHERE token = '" . $revoked['token'] . "'");
        $member = $this->notice(1, $this->identity(1, 'Membre', 'Vrai', 7), ['ref' => 'A-0777/2026']);

        $bodies = [];
        $statuses = [];
        foreach ([str_repeat('Z', 22), 'abc', $other['token'], $revoked['token'], $secret['token'], $member['token']] as $token) {
            $res = $this->controller($this->floor())->show($this->get(), ['token' => $token]);
            $statuses[] = $res->statusCode();
            $bodies[] = json_encode([$this->last[0], $this->last[1]['mode'], $this->last[1]['title'], array_keys($this->last[1])]);
        }
        self::assertSame([404, 404, 404, 404, 404, 404], $statuses);
        self::assertSame(1, count(array_unique($bodies)), 'même page, rien ne distingue les causes');
        self::assertSame('notfound', $this->last[1]['mode']);
        self::assertArrayNotHasKey('view', $this->last[1]);
        // Le même jeton « Confidentiel » est lisible à une habilitation suffisante.
        self::assertSame(200, $this->controller($this->reader())->show($this->get(), ['token' => $secret['token']])->statusCode());
        // Une identité de membre n'est jamais une alerte lisible, même pour un gestionnaire.
        self::assertSame(404, $this->controller($this->manager())->show($this->get(), ['token' => $member['token']])->statusCode());
        self::assertSame(200, $this->controller($this->manager())->show($this->get(), ['token' => $s['notice']['token']])->statusCode());
    }

    public function testGuestAndAccountsWithoutTheReadRightAreRefusedWithThePreciseRight(): void
    {
        $s = $this->seed();
        $guest = $this->controller(self::access(1, 0, 'interne', false, false));
        $res = $guest->show($this->get(), ['token' => $s['notice']['token']]);
        self::assertSame(403, $res->statusCode());
        self::assertSame('denied', $this->last[1]['mode']);
        self::assertSame(SseAlertReportController::NEED_ACCOUNT, $this->last[1]['message']);
        self::assertSame(403, $guest->submit($this->post(self::form()), ['token' => $s['notice']['token']])->statusCode());
        self::assertSame(0, $this->rows('sse_alert_reports'));

        $res = $this->controller(self::access(1, 9, 'interne', false, true, false))->show($this->get(), ['token' => $s['notice']['token']]);
        self::assertSame(403, $res->statusCode());
        self::assertSame(SseAlertReportController::NEED_READ, $this->last[1]['message']);
        self::assertStringContainsString('Fichiers et sujets', SseAlertReportController::NEED_READ);
    }

    public function testWithoutTheAlertTablesThePageSaysSo(): void
    {
        SseAlertSqlite::close();
        $this->pdo = SseAlertSqlite::open(false);
        $res = $this->controller($this->reader())->show($this->get(), ['token' => str_repeat('a', 22)]);

        self::assertSame(503, $res->statusCode());
        self::assertSame('unavailable', $this->last[1]['mode']);
    }

    // ------------------------------------------------------------------ Envoi

    public function testWithoutTheCsrfTokenNothingIsRecorded(): void
    {
        $s = $this->seed();
        $res = $this->controller($this->reader())->submit($this->post(self::form(), false), ['token' => $s['notice']['token']]);

        self::assertSame(403, $res->statusCode());
        self::assertSame([SseAlertReportController::TOKEN_EXPIRED], $this->last[1]['errors']);
        self::assertSame('Entrepôt du quai 4', $this->last[1]['old']['place'], 'la saisie est gardée');
        self::assertSame(0, $this->rows('sse_alert_reports'));
        $res = $this->controller($this->reader())->submit($this->post(self::form(['_csrf_token' => 'faux']), false), ['token' => $s['notice']['token']]);
        self::assertSame(403, $res->statusCode());
        self::assertSame(0, $this->rows('sse_alert_reports'));
    }

    public function testAReportGetsAReceiptNumberAndShowsInThePanel(): void
    {
        $s = $this->seed();
        $res = $this->controller($this->reader())->submit($this->post(self::form()), ['token' => $s['notice']['token']]);

        self::assertSame(200, $res->statusCode());
        self::assertSame('receipt', $this->last[1]['mode'], 'accusé de réception sur la même page, pas de redirection');
        $report = $this->last[1]['report'];
        self::assertMatchesRegularExpression('/^SIG-' . gmdate('Y') . '-0001$/', $report['ref']);
        self::assertSame('new', $report['status']);
        self::assertSame('web', $report['source']);
        self::assertSame('high', $report['certainty']);
        self::assertSame('seen', $report['what']);
        self::assertSame('RAVEN-8', $report['reporter_label']);
        self::assertSame(1, $this->rows('sse_alert_reports'));
        // Les gestionnaires sont prévenus, sauf l'auteur.
        self::assertSame(['report'], array_unique(array_column(array_column($this->notified, 2), 'event')));
        self::assertSame([7], array_column($this->notified, 1));
        self::assertSame('atak/sse/avis/signalements', $this->notified[0][2]['path']);

        // Le panneau du gestionnaire montre le signalement.
        $vars = $this->controller($this->manager())->panelVars('avis', $s['notice']['id'], 'A-0001/2026');
        self::assertCount(1, $vars['alertReports']);
        self::assertTrue($vars['alertCanManage']);
        self::assertSame(url('atak/sse/signalement/' . $s['notice']['token']), $vars['alertUrl']);
        $html = $this->panelHtml($vars);
        self::assertStringContainsString($report['ref'], $html);
        self::assertStringContainsString('Fourgon blanc garé devant l’entrepôt', $html);
        self::assertStringContainsString('Copier le lien', $html);
        self::assertStringContainsString('/qr.svg', $html);
        self::assertStringContainsString('Révoquer l’adresse', $html);
        // Un lecteur voit les signalements mais ni la révocation ni le bouton de traitement.
        $readerHtml = $this->panelHtml($this->controller($this->reader())->panelVars('avis', $s['notice']['id'], 'A-0001/2026'));
        self::assertStringContainsString($report['ref'], $readerHtml);
        self::assertStringNotContainsString('Révoquer l’adresse', $readerHtml);
        self::assertStringNotContainsString('data-sal-action', $readerHtml);
    }

    /** @param array<string, mixed> $vars */
    private function panelHtml(array $vars): string
    {
        $_SERVER['REQUEST_URI'] = '/atak/sse/avis';
        extract($vars);
        $sseExtraStyles = '';
        $sseExtraScripts = '';
        ob_start();
        require dirname(__DIR__, 2) . '/views/atak/sse/alerts/_reports_panel.php';

        return (string) ob_get_clean();
    }

    public function testTheSameSubmissionReplayedKeepsOneReport(): void
    {
        $s = $this->seed();
        $c = $this->controller($this->reader());
        $form = self::form(['cid' => 'abcdef0123456789abcdef01']);
        $c->submit($this->post($form), ['token' => $s['vigilance']['token']]);
        $first = $this->last[1]['report']['ref'];
        $c->submit($this->post($form), ['token' => $s['vigilance']['token']]);

        self::assertSame($first, $this->last[1]['report']['ref']);
        self::assertTrue($this->last[1]['replay']);
        self::assertSame(1, $this->rows('sse_alert_reports'));
    }

    public function testInvalidInputGivesTheErrorsAndKeepsTheForm(): void
    {
        $s = $this->seed();
        $c = $this->controller($this->reader());
        $res = $c->submit($this->post(self::form(['text' => 'x'])), ['token' => $s['notice']['token']]);
        self::assertSame(422, $res->statusCode());
        self::assertSame('form', $this->last[1]['mode']);
        self::assertNotSame([], $this->last[1]['errors']);

        $res = $c->submit($this->post(self::form(['when' => gmdate('Y-m-d\TH:i', time() + 7200)])), ['token' => $s['notice']['token']]);
        self::assertSame(422, $res->statusCode());
        self::assertStringContainsString('futur', implode(' ', $this->last[1]['errors']));
        self::assertSame(0, $this->rows('sse_alert_reports'));

        $c->submit($this->post(self::form(['sure' => 'nimporte', 'what' => 'autre'])), ['token' => $s['notice']['token']]);
        self::assertSame('medium', $this->last[1]['report']['certainty'], 'certitude inconnue : probable');
        self::assertSame('seen', $this->last[1]['report']['what']);
    }

    public function testTheRateLimitStopsTheThirteenthReportInTenMinutes(): void
    {
        $s = $this->seed();
        $c = $this->controller($this->reader());
        for ($i = 1; $i <= SseAlertService::REPORT_LIMIT; ++$i) {
            self::assertSame(200, $c->submit($this->post(self::form(['text' => 'Observation numéro ' . $i . ' au quai'])), ['token' => $s['notice']['token']])->statusCode());
        }
        $res = $c->submit($this->post(self::form()), ['token' => $s['notice']['token']]);

        self::assertSame(429, $res->statusCode());
        self::assertStringContainsString('Trop de signalements', $this->last[1]['errors'][0]);
        self::assertSame(SseAlertService::REPORT_LIMIT, $this->rows('sse_alert_reports'));
        // Un autre compte n'est pas limité par celui-ci.
        self::assertSame(200, $this->controller(self::access(1, 9, 'interne'))->submit($this->post(self::form()), ['token' => $s['notice']['token']])->statusCode());
    }

    public function testALiftedOrExpiredAlertIsReadOnly(): void
    {
        $lifted = $this->vigilance(1, $this->identity(1, 'Leve', 'Luc'), 'interne', ['ref' => 'FV-2026-0100', 'lifted' => gmdate('Y-m-d H:i:s', time() - 600)]);
        $expired = $this->vigilance(1, $this->identity(1, 'Ancien', 'Albert'), 'interne', ['ref' => 'FV-2026-0101', 'until' => gmdate('Y-m-d', time() - 86400)]);
        $notice = $this->notice(1, $this->identity(1, 'Ferme', 'Fred'), ['ref' => 'A-0101/2026', 'status' => 'lifted']);
        foreach ([[$lifted, 'lifted'], [$expired, 'expired'], [$notice, 'lifted']] as [$alert, $state]) {
            $c = $this->controller($this->reader());
            $res = $c->show($this->get(), ['token' => $alert['token']]);
            self::assertSame(200, $res->statusCode(), $alert['ref']);
            self::assertSame('closed', $this->last[1]['mode'], $alert['ref']);
            self::assertSame($state, $this->last[1]['view']['state']);
            $res = $c->submit($this->post(self::form()), ['token' => $alert['token']]);
            self::assertSame(409, $res->statusCode(), $alert['ref']);
        }
        self::assertSame(0, $this->rows('sse_alert_reports'));
        // La page levée porte la date de levée et le motif.
        $this->controller($this->reader())->show($this->get(), ['token' => $lifted['token']]);
        self::assertNotSame('', $this->last[1]['view']['lifted_at']);
        self::assertSame('Sujet interpellé', $this->last[1]['view']['lift_reason']);
    }

    // ------------------------------------------------------------------ Photo

    private static function jpegWithExif(): string
    {
        $im = imagecreatetruecolor(320, 240);
        imagefilledrectangle($im, 0, 0, 320, 240, (int) imagecolorallocate($im, 90, 120, 150));
        ob_start();
        imagejpeg($im, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($im);
        $exif = "Exif\0\0" . 'GPS-LAT-48.8566 SECRET-DEVICE-ID-12345';

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
    }

    private function upload(string $bytes, string $name = 'photo.jpg'): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sal');
        file_put_contents($tmp, $bytes);
        $_FILES = ['photo' => ['name' => $name, 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)]];
    }

    public function testThePhotoIsCheckedByItsContentReencodedAndKeptPrivate(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD requis');
        }
        $s = $this->seed();
        $original = self::jpegWithExif();
        self::assertStringContainsString('SECRET-DEVICE-ID-12345', $original);
        $this->upload($original);
        $report = $this->report($s['notice']['token']);

        self::assertNotNull($report['photo_path']);
        self::assertStringStartsWith('sse-private/sse-alert-reports/', (string) $report['photo_path']);
        self::assertTrue(SseImageStorage::isPrivatePath((string) $report['photo_path']));
        $file = SseImageStorage::absolute((string) $report['photo_path'], null, $this->privateDir);
        self::assertNotNull($file);
        $stored = (string) file_get_contents((string) $file);
        self::assertSame('image/jpeg', SseImageStorage::verifiedMime((string) $file));
        self::assertStringNotContainsString('SECRET-DEVICE-ID', $stored, 'EXIF retiré par ré-encodage');
        self::assertStringNotContainsString('Exif', $stored);
        self::assertStringNotContainsString('GPS-LAT', $stored);
    }

    public function testAFakeImageOrAnOversizedPhotoRefusesTheWholeReport(): void
    {
        $s = $this->seed();
        $c = $this->controller($this->reader());
        $this->upload('<?php echo "pas une image"; ?>', 'photo.jpg');
        $res = $c->submit($this->post(self::form()), ['token' => $s['notice']['token']]);
        self::assertSame(422, $res->statusCode());
        self::assertStringContainsString('JPEG, PNG ou WebP', $this->last[1]['errors'][0]);
        self::assertSame('Entrepôt du quai 4', $this->last[1]['old']['place']);

        $this->upload(str_repeat("\xFF\xD8\xFF", 4) . str_repeat('A', SseAlertReportPhotos::MAX_BYTES), 'gros.jpg');
        $res = $c->submit($this->post(self::form()), ['token' => $s['notice']['token']]);
        self::assertSame(422, $res->statusCode());
        self::assertStringContainsString('4 Mo', $this->last[1]['errors'][0]);
        self::assertSame(0, $this->rows('sse_alert_reports'));
        self::assertSame([], glob($this->privateDir . '/sse-alert-reports/*') ?: []);
    }

    public function testAPhotoStoredForAnInvalidReportIsRemoved(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD requis');
        }
        $s = $this->seed();
        $this->upload(self::jpegWithExif());
        $res = $this->controller($this->reader())->submit($this->post(self::form(['text' => 'x'])), ['token' => $s['notice']['token']]);

        self::assertSame(422, $res->statusCode());
        self::assertSame([], glob($this->privateDir . '/sse-alert-reports/*') ?: [], 'aucune photo orpheline');
    }

    public function testThePhotoIsServedByAControlledRouteToManagersOnly(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD requis');
        }
        $s = $this->seed();
        $this->upload(self::jpegWithExif());
        $report = $this->report($s['notice']['token']);
        $_FILES = [];

        $res = $this->controller($this->manager())->photo($this->get(), ['id' => $report['id']]);
        self::assertSame(200, $res->statusCode());
        self::assertSame('image/jpeg', $res->headerValue('Content-Type'));
        self::assertSame('nosniff', $res->headerValue('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $res->headerValue('Cache-Control'));
        self::assertTrue($res->hasBodyStream());
        // Lecteur sans droit d'écrire, plancher, invité, mauvaise communauté, signalement inconnu : même 404.
        foreach ([$this->reader(), $this->floor(), self::access(1, 0, 'interne', false, false), self::access(2, 7, 'tres_restreint', true)] as $who) {
            self::assertSame(404, $this->controller($who)->photo($this->get(), ['id' => $report['id']])->statusCode());
        }
        self::assertSame(404, $this->controller($this->manager())->photo($this->get(), ['id' => 999])->statusCode());
        // La route publique n'existe pas : le chemin n'est jamais rendu dans une page de lecteur.
        $readerHtml = $this->panelHtml($this->controller($this->reader())->panelVars('avis', $s['notice']['id'], 'A-0001/2026'));
        self::assertStringNotContainsString('sse-private', $readerHtml);
        self::assertStringNotContainsString('/photo', $readerHtml);
        self::assertStringContainsString('réservée aux gestionnaires', $readerHtml);
    }

    // ------------------------------------------------------------------ États

    public function testAManagerChangesTheStatusAndTheReasonIsRequiredToDismiss(): void
    {
        $s = $this->seed();
        $report = $this->report($s['notice']['token']);
        $c = $this->controller($this->manager());

        $res = $c->status($this->post(['status' => 'acknowledged', 'back' => 'atak/sse/avis/signalements?onglet=tous']), ['id' => $report['id']]);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('onglet=tous', (string) $res->headerValue('Location'));
        self::assertStringContainsString($report['ref'] . ' : pris en compte.', (string) Session::getFlash('success'));
        self::assertSame('acknowledged', $this->pdo->query('SELECT status FROM sse_alert_reports WHERE id = ' . (int) $report['id'])->fetchColumn());

        $c->status($this->post(['status' => 'dismissed', 'note' => '']), ['id' => $report['id']]);
        self::assertSame('Indiquez pourquoi le signalement est écarté.', Session::getFlash('error'));
        self::assertSame('acknowledged', $this->pdo->query('SELECT status FROM sse_alert_reports WHERE id = ' . (int) $report['id'])->fetchColumn());

        $c->status($this->post(['status' => 'dismissed', 'note' => 'Doublon du signalement précédent']), ['id' => $report['id']]);
        self::assertSame('dismissed', $this->pdo->query('SELECT status FROM sse_alert_reports WHERE id = ' . (int) $report['id'])->fetchColumn());
        self::assertSame('Doublon du signalement précédent', $this->pdo->query('SELECT status_note FROM sse_alert_reports WHERE id = ' . (int) $report['id'])->fetchColumn());
        self::assertSame(7, (int) $this->pdo->query('SELECT handled_by_user_id FROM sse_alert_reports WHERE id = ' . (int) $report['id'])->fetchColumn());

        $c->status($this->post(['status' => 'nimporte']), ['id' => $report['id']]);
        self::assertSame('Signalement introuvable.', Session::getFlash('error'), 'état inconnu : refusé par le service');
    }

    public function testStatusChangeNeedsTheRightTheCsrfTokenAndTheSameCommunity(): void
    {
        $s = $this->seed();
        $report = $this->report($s['notice']['token']);
        $other = $this->notice(2, $this->identity(2, 'Bravo', 'Cible'), ['ref' => 'A-0900/2026']);
        $foreign = $this->pdo->prepare("INSERT INTO sse_alert_reports (tenant_id, link_id, ref, ref_year, ref_seq, what, seen_at, body, certainty, status, source, created_at) VALUES (2, (SELECT id FROM sse_alert_links WHERE token = ?), 'SIG-2026-0900', 2026, 900, 'seen', '2026-11-14 10:00:00', 'Autre communauté', 'high', 'new', 'web', '2026-11-14 10:00:00')");
        $foreign->execute([$other['token']]);
        $foreignId = (int) $this->pdo->lastInsertId();

        // Lecteur : le message nomme le droit manquant, rien ne change.
        $this->controller($this->reader())->status($this->post(['status' => 'acknowledged']), ['id' => $report['id']]);
        self::assertSame(SseAlertReportController::NEED_MANAGE, Session::getFlash('error'));
        self::assertStringContainsString('Fichiers et sujets', SseAlertReportController::NEED_MANAGE);
        // Sans jeton CSRF.
        $this->controller($this->manager())->status($this->post(['status' => 'acknowledged'], false), ['id' => $report['id']]);
        self::assertSame(SseAlertReportController::TOKEN_EXPIRED, Session::getFlash('error'));
        // Signalement d'une autre communauté : introuvable (IDOR).
        $this->controller($this->manager())->status($this->post(['status' => 'acknowledged']), ['id' => $foreignId]);
        self::assertSame('Signalement introuvable.', Session::getFlash('error'));
        self::assertSame('new', $this->pdo->query('SELECT status FROM sse_alert_reports WHERE id = ' . $foreignId)->fetchColumn());
        self::assertSame('new', $this->pdo->query('SELECT status FROM sse_alert_reports WHERE id = ' . (int) $report['id'])->fetchColumn());
        // Signalement d'une fiche au-dessus de l'habilitation du gestionnaire : introuvable aussi.
        $secret = $this->vigilance(1, $this->identity(1, 'Secret', 'Dossier'), 'confidentiel', ['ref' => 'FV-2026-0003']);
        $secretReport = $this->report($secret['token']);
        $this->controller(self::access(1, 7, 'interne', true))->status($this->post(['status' => 'acknowledged']), ['id' => $secretReport['id']]);
        self::assertSame('Signalement introuvable.', Session::getFlash('error'));
        // Un retour hors du portail est ignoré.
        $res = $this->controller($this->manager())->status($this->post(['status' => 'acknowledged', 'back' => 'https://exemple.test/hameçon']), ['id' => $report['id']]);
        self::assertStringNotContainsString('exemple.test', (string) $res->headerValue('Location'));
    }

    // ------------------------------------------------------------------ Révocation

    public function testRevokingTheAddressKillsTheTokenAndTheQrCode(): void
    {
        $s = $this->seed();
        $link = (new SseAlertRepository())->linkFor(1, 'avis', $s['notice']['id']);
        self::assertSame(200, $this->controller($this->reader())->show($this->get(), ['token' => $s['notice']['token']])->statusCode());

        $this->controller($this->reader())->revoke($this->post([]), ['id' => $link['id']]);
        self::assertSame(SseAlertReportController::NEED_MANAGE, Session::getFlash('error'));
        $this->controller($this->manager())->revoke($this->post([], false), ['id' => $link['id']]);
        self::assertSame(SseAlertReportController::TOKEN_EXPIRED, Session::getFlash('error'));
        $this->controller(self::access(2, 7, 'tres_restreint', true))->revoke($this->post([]), ['id' => $link['id']]);
        self::assertSame('Adresse de signalement introuvable.', Session::getFlash('error'), 'autre communauté : introuvable');
        self::assertNull($this->pdo->query('SELECT revoked_at FROM sse_alert_links WHERE id = ' . (int) $link['id'])->fetchColumn() ?: null);

        $res = $this->controller($this->manager())->revoke($this->post([]), ['id' => $link['id']]);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('révoquée', (string) Session::getFlash('success'));
        self::assertNotFalse($this->pdo->query('SELECT revoked_at FROM sse_alert_links WHERE id = ' . (int) $link['id'])->fetchColumn());
        self::assertSame(404, $this->controller($this->reader())->show($this->get(), ['token' => $s['notice']['token']])->statusCode());
        self::assertSame(404, $this->controller($this->reader())->qr($this->get(), ['token' => $s['notice']['token']])->statusCode());
        $this->controller($this->manager())->revoke($this->post([]), ['id' => $link['id']]);
        self::assertSame('Cette adresse de signalement est déjà révoquée.', Session::getFlash('error'));
        // Les signalements reçus restent lisibles dans le panneau, sans adresse.
        $vars = $this->controller($this->manager())->panelVars('avis', $s['notice']['id'], 'A-0001/2026');
        self::assertSame('', $vars['alertUrl']);
        self::assertStringContainsString('Adresse révoquée', $this->panelHtml($vars));
    }

    // ------------------------------------------------------------------ QR code

    public function testTheQrCodeIsAnSvgGeneratedLocally(): void
    {
        $s = $this->seed();
        $res = $this->controller($this->reader())->qr($this->get(), ['token' => $s['vigilance']['token']]);

        self::assertSame(200, $res->statusCode());
        self::assertStringStartsWith('image/svg+xml', (string) $res->headerValue('Content-Type'));
        $svg = $res->body();
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('viewBox="0 0 ', $svg);
        self::assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg), 'aucune ressource externe');
        self::assertStringNotContainsString('<script', $svg);
        self::assertGreaterThan(400, substr_count($svg, 'h1v1h-1z'), 'des centaines de modules');
        self::assertNull($res->headerValue('Content-Disposition'));
        $down = $this->controller($this->reader())->qr($this->get(['telecharger' => '1']), ['token' => $s['vigilance']['token']]);
        self::assertStringContainsString('attachment; filename="qr-signalement-FV-2026-0001.svg"', (string) $down->headerValue('Content-Disposition'));
        // Jeton inconnu ou invité : 404 sans rien dire.
        self::assertStringContainsString('<svg', $down->body());
        self::assertSame(404, $this->controller($this->reader())->qr($this->get(), ['token' => str_repeat('a', 22)])->statusCode());
        self::assertSame(404, $this->controller(self::access(1, 0, 'interne', false, false))->qr($this->get(), ['token' => $s['vigilance']['token']])->statusCode());
        self::assertSame('', SseAlertReportController::qrSvg(''));
    }

    // ------------------------------------------------------------------ Liste et propositions

    public function testTheListCountsByStatusFiltersByTabAndHidesAlertsAboveTheClearance(): void
    {
        $s = $this->seed();
        $a = $this->report($s['notice']['token']);
        $b = $this->report($s['vigilance']['token'], ['text' => 'Vu près de la gare, seul, sac à dos noir.']);
        $secret = $this->vigilance(1, $this->identity(1, 'Secret', 'Dossier'), 'confidentiel', ['ref' => 'FV-2026-0003']);
        $c = $this->report($secret['token'], ['text' => 'Signalement sur la fiche confidentielle.']);
        $m = $this->controller($this->manager());
        $m->status($this->post(['status' => 'corroborated']), ['id' => $a['id']]);

        $m->index($this->get());
        $d = $this->last[1];
        self::assertSame('atak.sse.alerts.reports', $this->last[0]);
        self::assertSame(['new' => 2, 'acknowledged' => 0, 'corroborated' => 1, 'dismissed' => 0, 'all' => 3], $d['counts']);
        self::assertSame('nouveau', $d['tab']);
        self::assertSame([$c['ref'], $b['ref']], array_column($d['reports'], 'ref'), 'onglet « nouveaux »');
        self::assertSame('FV-2026-0001', $d['alerts'][(int) $b['link_id']]['ref']);
        self::assertSame('A-0001/2026', $d['alerts'][(int) $a['link_id']]['ref']);

        $m->index($this->get(['onglet' => 'recoupe']));
        self::assertSame([$a['ref']], array_column($this->last[1]['reports'], 'ref'));
        $m->index($this->get(['onglet' => 'tous']));
        self::assertCount(3, $this->last[1]['reports']);

        // Gestionnaire d'habilitation inférieure : la fiche confidentielle n'existe pas dans sa liste.
        $this->controller(self::access(1, 7, 'interne', true))->index($this->get(['onglet' => 'tous']));
        self::assertSame(2, $this->last[1]['counts']['all']);
        self::assertSame([$b['ref'], $a['ref']], array_column($this->last[1]['reports'], 'ref'));
        // Sans le droit de lire : renvoi vers l'accueil avec le droit manquant.
        $res = $this->controller(self::access(1, 9, 'interne', false, true, false))->index($this->get());
        self::assertSame(302, $res->statusCode());
        self::assertSame(SseAlertReportController::NEED_READ, Session::getFlash('error'));
    }

    public function testTheValidationQueueIsForManagersAndRefusalNeedsAReason(): void
    {
        $this->seed();
        $repo = new SseAlertRepository();
        $payload = ['subject' => 'IDT-000001', 'unknown' => '', 'type' => 'hvt', 'threat' => 'high', 'armed' => 'no', 'reasons' => [], 'measures' => ['A-NOI'], 'zone' => 'Pyrgos', 'place' => 'Quai 4', 'grid' => '045 123', 'days' => 14, 'text' => 'Chauffeur du fourgon blanc, vu au port.', 'service' => '', 'delay' => '', 'lang' => 'fr'];
        $p1 = $repo->createProposal(1, 'avis', $payload, 'cid-proposal-0001', 9, 'RAVEN-9');
        $p2 = $repo->createProposal(1, 'vigilance', ['subject' => '', 'unknown' => 'Homme grand, casquette bleue, vu au marché'] + $payload, 'cid-proposal-0002', 9, 'RAVEN-9');
        $repo->createProposal(2, 'avis', $payload, 'cid-proposal-0003', 9, 'AUTRE');

        $this->controller($this->manager())->index($this->get(['onglet' => 'a-valider']));
        $d = $this->last[1];
        self::assertSame('a-valider', $d['tab']);
        self::assertSame(2, $d['proposalCount']);
        self::assertSame(['PROP-' . $p2['id'], 'PROP-' . $p1['id']], array_column($d['proposals'], 'ref'));
        self::assertSame('IDT-000001', $d['proposals'][1]['subject']);
        self::assertSame(url('atak/sse/papiers/identites/1'), $d['proposals'][1]['subject_href']);
        self::assertSame('Homme grand, casquette bleue, vu au marché', $d['proposals'][0]['unknown']);
        self::assertSame('caution', $d['proposals'][1]['level']);
        self::assertSame('A-NOI', $d['proposals'][1]['codes'][0]['code']);
        // Un lecteur ne voit pas la file : l'onglet retombe sur les nouveaux signalements.
        $this->controller($this->reader())->index($this->get(['onglet' => 'a-valider']));
        self::assertSame('nouveau', $this->last[1]['tab']);
        self::assertSame([], $this->last[1]['proposals']);

        $c = $this->controller($this->manager());
        $c->decide($this->post(['decision' => 'reject', 'note' => '']), ['id' => $p1['id']]);
        self::assertSame('Le motif du refus est obligatoire.', Session::getFlash('error'));
        $c->decide($this->post(['decision' => 'reject', 'note' => 'Sujet déjà couvert par un avis en cours'], false), ['id' => $p1['id']]);
        self::assertSame(SseAlertReportController::TOKEN_EXPIRED, Session::getFlash('error'));
        $this->controller($this->reader())->decide($this->post(['decision' => 'reject', 'note' => 'Sujet déjà couvert']), ['id' => $p1['id']]);
        self::assertSame(SseAlertReportController::NEED_MANAGE, Session::getFlash('error'));
        $c->decide($this->post(['decision' => 'reject', 'note' => 'Sujet déjà couvert par un avis en cours']), ['id' => $p1['id']]);
        self::assertStringContainsString('refusée', (string) Session::getFlash('success'));
        self::assertSame('rejected', $this->pdo->query('SELECT state FROM sse_alert_proposals WHERE id = ' . (int) $p1['id'])->fetchColumn());
        $c->decide($this->post(['decision' => 'reject', 'note' => 'Encore une fois']), ['id' => $p1['id']]);
        self::assertSame('Cette proposition a déjà été traitée.', Session::getFlash('error'));
        // Autre communauté : introuvable.
        $other = $repo->createProposal(2, 'avis', $payload, 'cid-proposal-0004', 9, 'AUTRE');
        $c->decide($this->post(['decision' => 'reject', 'note' => 'Hors communauté']), ['id' => $other['id']]);
        self::assertSame('Proposition introuvable.', Session::getFlash('error'));
        self::assertSame('pending', $this->pdo->query('SELECT state FROM sse_alert_proposals WHERE id = ' . (int) $other['id'])->fetchColumn());
        // Un inconnu ne s'accepte pas sans sa référence ; décision inconnue refusée.
        $c->decide($this->post(['decision' => 'accept']), ['id' => $p2['id']]);
        self::assertStringContainsString('inconnu', (string) Session::getFlash('error'));
        $c->decide($this->post(['decision' => 'peut-être']), ['id' => $p2['id']]);
        self::assertSame('Choisissez d’accepter ou de refuser la proposition.', Session::getFlash('error'));
        self::assertSame('pending', $this->pdo->query('SELECT state FROM sse_alert_proposals WHERE id = ' . (int) $p2['id'])->fetchColumn());
    }

    public function testAcceptingAProposalIssuesTheNoticeWithTheManagersRights(): void
    {
        $this->seed();
        $iid = $this->identity(1, 'Cible', 'Camille');
        $repo = new SseAlertRepository();
        $payload = ['subject' => sprintf('IDT-%06d', $iid), 'unknown' => '', 'type' => 'hvt', 'threat' => 'high', 'armed' => 'no', 'reasons' => [], 'measures' => ['A-NOI'], 'zone' => 'Pyrgos', 'place' => '', 'grid' => '', 'days' => 14,
            'text' => 'Chauffeur du fourgon blanc, vu au port plusieurs soirs de suite.', 'service' => '', 'delay' => '', 'lang' => 'fr'];
        $p = $repo->createProposal(1, 'avis', $payload, 'cid-proposal-0010', 9, 'RAVEN-9');
        $before = $this->rows('sse_wanted_notices');

        $res = $this->controller($this->manager())->decide($this->post(['decision' => 'accept', 'note' => 'Vérifié']), ['id' => $p['id']]);

        self::assertSame(302, $res->statusCode());
        $flash = (string) (Session::getFlash('success') ?? '');
        self::assertStringContainsString('alerte', $flash, $flash);
        self::assertSame($before + 1, $this->rows('sse_wanted_notices'));
        self::assertSame('accepted', $this->pdo->query('SELECT state FROM sse_alert_proposals WHERE id = ' . (int) $p['id'])->fetchColumn());
        self::assertNotSame('', (string) $this->pdo->query('SELECT result_ref FROM sse_alert_proposals WHERE id = ' . (int) $p['id'])->fetchColumn());
    }

    // ------------------------------------------------------------------ « À trier », notifications, routes

    public function testNewReportsAndPendingProposalsFeedTheTriageInbox(): void
    {
        $s = $this->seed();
        $report = $this->report($s['notice']['token'], ['sure' => 'high']);
        $other = $this->report($s['vigilance']['token']);
        $this->controller($this->manager())->status($this->post(['status' => 'acknowledged']), ['id' => $other['id']]);
        (new SseAlertRepository())->createProposal(1, 'vigilance', ['subject' => 'IDT-000002', 'threat' => 'low'], 'cid-proposal-0020', 9, 'RAVEN-9');

        $defs = array_column(SseTriageInbox::defaultDefinitions(7), null, 'key');
        self::assertArrayHasKey('signalements', $defs);
        self::assertArrayHasKey('propositions', $defs);
        self::assertSame('avis-recherche', $defs['signalements']['nav']);
        self::assertSame('Signalement', $defs['signalements']['label']);
        self::assertSame('atak/sse/avis/signalements', $defs['signalements']['path']);
        $items = ($defs['signalements']['load'])(1);
        self::assertCount(1, $items, 'seuls les signalements nouveaux remontent');
        self::assertSame('report:' . $report['id'], $items[0]['key']);
        self::assertSame($report['ref'], $items[0]['title']);
        self::assertSame(1, $items[0]['priority'], 'un signalement certain passe devant');
        self::assertSame([], ($defs['signalements']['load'])(2));
        $props = ($defs['propositions']['load'])(1);
        self::assertCount(1, $props);
        self::assertStringStartsWith('proposal:', $props[0]['key']);
    }

    public function testNotificationsGetATitleADetailAndALinkThatPassTheRegex(): void
    {
        $presenter = new ActivityHubPresentationService();
        $row = static fn (array $payload): array => ['type' => 'sse_wanted_notice', 'payload_json' => json_encode($payload), 'created_at' => '2026-11-15 10:00:00', 'read_at' => null];

        $report = $presenter->formatNotificationRow($row(['event' => 'report', 'ref' => 'A-0001/2026', 'report' => 'SIG-2026-0004', 'label' => '', 'path' => 'atak/sse/avis/signalements']));
        self::assertSame('Nouveau signalement : A-0001/2026', $report['title']);
        self::assertStringContainsString('SIG-2026-0004', $report['detail']);
        self::assertSame(url('atak/sse/avis/signalements'), $report['href']);

        $proposal = $presenter->formatNotificationRow($row(['event' => 'proposal', 'ref' => 'PROP-3', 'doc' => 'avis', 'label' => 'IDT-000001', 'path' => 'atak/sse/avis']));
        self::assertSame('Alerte proposée à valider : IDT-000001', $proposal['title']);
        self::assertStringContainsString('PROP-3', $proposal['detail']);
        self::assertSame(url('atak/sse/avis'), $proposal['href']);

        self::assertStringStartsWith('Proposition d’alerte acceptée', $presenter->formatNotificationRow($row(['event' => 'proposal_accepted', 'ref' => 'A-0009/2026', 'path' => 'atak/sse/avis']))['title']);
        self::assertStringStartsWith('Proposition d’alerte refusée', $presenter->formatNotificationRow($row(['event' => 'proposal_rejected', 'ref' => 'PROP-3', 'path' => 'atak/sse/avis']))['title']);
        // Un chemin étranger ne sort jamais du portail.
        self::assertSame(url('atak/sse/avis'), $presenter->formatNotificationRow($row(['event' => 'report', 'ref' => 'x', 'path' => 'https://evil.example/']))['href']);
    }

    public function testTheRoutesAreWiredToTheController(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach ([
            "get('/atak/sse/signalement/{token}', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'show']",
            "post('/atak/sse/signalement/{token}', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'submit']",
            "get('/atak/sse/signalement/{token}/qr.svg', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'qr']",
            "get('/atak/sse/avis/signalements', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'index']",
            "post('/atak/sse/avis/signalements/{id}/statut', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'status']",
            "get('/atak/sse/avis/signalements/{id}/photo', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'photo']",
            "post('/atak/sse/avis/liens/{id}/revoquer', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'revoke']",
            "post('/atak/sse/avis/propositions/{id}/decision', [\\App\\Controllers\\Web\\SseAlertReportController::class, 'decide']",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        // Toutes derrière la porte du portail SSE (compte et session), jamais publiques.
        preg_match_all("#SseAlertReportController::class, '\w+'\](.*)\);#", $routes, $m);
        self::assertCount(8, $m[1]);
        foreach ($m[1] as $mw) {
            self::assertSame(', $mwSsePortal', $mw);
        }
        self::assertSame('fichiers', \App\Support\SseAccessCatalog::sectionForPath('/atak/sse/avis/signalements'));
        self::assertSame('fichiers', \App\Support\SseAccessCatalog::sectionForPath('/atak/sse/avis/propositions/3/decision'));
    }

    // ------------------------------------------------------------------ Vues

    public function testTheViewsHaveNoEyebrowNoSseDsAndNothingBelowThirteenPixels(): void
    {
        $root = dirname(__DIR__, 2);
        $files = ['views/atak/sse/alerts/report.php', 'views/atak/sse/alerts/reports.php', 'views/atak/sse/alerts/_reports_panel.php', 'public/assets/css/sse-alerts.css', 'public/assets/js/sse-alerts.js'];
        foreach ($files as $f) {
            $src = (string) file_get_contents($root . '/' . $f);
            self::assertNotSame('', $src, $f);
            self::assertDoesNotMatchRegularExpression('/eyebrow|sse-ds|Archivo|class="[^"]*\bsse-(?!notify|note)/i', $src, $f . ' : ni eyebrow ni SSE DS');
            self::assertDoesNotMatchRegularExpression('/fiction|jeu de rôle|\bRP\b|simulation|spécimen|specimen/iu', $src, $f . ' : aucune mention de fiction');
        }
        $css = (string) file_get_contents($root . '/public/assets/css/sse-alerts.css');
        preg_match_all('/font-size:\s*([\d.]+)(rem|px)/', $css, $m, PREG_SET_ORDER);
        self::assertNotSame([], $m);
        foreach ($m as [$all, $n, $unit]) {
            $px = $unit === 'rem' ? (float) $n * 16 : (float) $n;
            self::assertGreaterThanOrEqual(13.0, $px, $all);
        }
        // Aucun libellé au-dessus d'un titre : pas de classe « kicker / overline / eyebrow ».
        self::assertDoesNotMatchRegularExpression('/kicker|overline/i', $css);
        // Le FR est dans les vues, l'EN arrive par le dictionnaire du site : pas de texte dans le script.
        self::assertDoesNotMatchRegularExpression('/[éèàùêâôîç]/u', (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', (string) file_get_contents($root . '/public/assets/js/sse-alerts.js')));
    }

    public function testNoMemberNameOrPhotoReachesThePage(): void
    {
        $s = $this->seed();
        $member = $this->notice(1, $this->identity(1, 'Dumembre', 'Vrai', 7), ['ref' => 'A-0777/2026']);
        $c = $this->controller($this->manager());
        $c->show($this->get(), ['token' => $s['notice']['token']]);
        $json = (string) json_encode($this->last[1]);
        self::assertStringNotContainsString('Dumembre', $json);
        self::assertStringNotContainsString('Vrai Membre', $json);
        $c->show($this->get(), ['token' => $member['token']]);
        self::assertSame('notfound', $this->last[1]['mode']);
        self::assertStringNotContainsString('Dumembre', (string) json_encode($this->last[1]));
        $c->index($this->get(['onglet' => 'tous']));
        self::assertStringNotContainsString('Dumembre', (string) json_encode($this->last[1]));
    }

    /** Contrôleur branché sur la vraie coque du portail (aucune vue simulée). */
    private function shellController(SseSheetAccess $access): SseAlertReportController
    {
        $alerts = $this->alertService();

        return new SseAlertReportController(null, null, $access, $alerts, new SseAlertGameService(null, null, $alerts), $this->proposals($alerts), new SseAlertReportPhotos($this->privateDir), null, true);
    }

    public function testThePageUsesThePortalShellWithItsHeaderBannerAndTheSignalementsEntryActive(): void
    {
        $s = $this->seed();
        $_SERVER['REQUEST_URI'] = '/atak/sse/signalement/' . $s['notice']['token'];
        $html = $this->shellController($this->manager())->show($this->get(), ['token' => $s['notice']['token']])->body();

        self::assertStringContainsString('class="iw-topbar sx-topbar"', $html, 'barre du haut du portail');
        self::assertStringContainsString('sx-classbar', $html, 'bandeau de classification');
        self::assertStringContainsString('aria-label="Navigation du bureau SSE"', $html, 'navigation du portail');
        self::assertStringContainsString('class="sal" data-sal', $html, 'le contenu de la page est dans la coque');
        self::assertSame(1, substr_count($html, 'role="banner"'), 'un seul en-tête de portail');
        // L'entrée active et le fil d'Ariane sont ceux des signalements, pas ceux des papiers d'identité.
        self::assertMatchesRegularExpression('#href="[^"]*/atak/sse/avis/signalements" class="iw-nav-link sx-nav__link is-active"#', $html);
        self::assertStringNotContainsString('/atak/sse/papiers" class="iw-nav-link sx-nav__link is-active"', $html);
    }

    public function testTheUniformNotFoundPageAlsoCarriesThePortalShell(): void
    {
        $this->seed();
        $_SERVER['REQUEST_URI'] = '/atak/sse/signalement/' . str_repeat('Z', 22);
        $res = $this->shellController($this->reader())->show($this->get(), ['token' => str_repeat('Z', 22)]);

        self::assertSame(404, $res->statusCode());
        self::assertStringContainsString('sx-topbar', $res->body());
        self::assertStringContainsString('Signalement introuvable', $res->body());
    }
}
