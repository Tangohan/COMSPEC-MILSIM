<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseSheetController;
use App\Controllers\Web\SseVigilanceController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAlertService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseVigilanceFollow;
use App\Services\Sse\SseWantedAccess;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAlertSqlite;

/**
 * Interface des fiches de vigilance du portail : écran d'émission (niveaux, consignes, service à informer, durées), émission,
 * renouvellement et levée par les vrais contrôleurs sur une base sqlite (droits précis, CSRF, communauté de la session, anti-IDOR),
 * liste des fiches émises, bandeau d'avertissement borné à l'habilitation, vues sans eyebrow ni design system « SSE DS », FR et EN.
 */
final class SseVigilanceUiTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER, 'env' => $_ENV];
        $this->pdo = SseAlertSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_ENV['APP_URL'] = 'https://athena.example';
        $_SERVER['HTTP_HOST'] = 'athena.example';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'RAVEN-6', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.files.admin', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
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
        Gate::reset();
    }

    // ------------------------------------------------------------------ Montage

    /** @param callable(string, string): bool|null $allows */
    private static function access(string $level = 'confidentiel', bool $admin = true, int $tenant = 1, int $user = 7, ?callable $allows = null): SseSheetAccess
    {
        $wanted = new SseWantedAccess(false, true, true, true, $tenant, $user, 'RAVEN-6', true, $admin);

        return new SseSheetAccess($wanted, $allows !== null ? \Closure::fromCallable($allows) : static fn (string $s, string $l): bool => true, true, $level);
    }

    private function person(): int
    {
        $cols = ['tenant_id' => 1, 'last_name' => 'Karimzadeh', 'first_name' => 'Dariush', 'alias' => 'Le Chauffeur', 'sex_apparent' => 'M', 'age_estimated' => 41, 'birth_date' => '1984-03-12',
            'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est', 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 18:40:00'];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));
        $pid = (int) $this->pdo->lastInsertId();
        (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Dariush', 'kind' => 'civil', 'person_id' => $pid, 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK']);

        return $pid;
    }

    /** @return array<string, mixed> */
    private function subject(int $pid, ?SseSheetAccess $a = null): array
    {
        $s = (new SseSheetFactsService())->subject(1, 'person', $pid, $a ?? self::access());
        self::assertNotNull($s);

        return $s;
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, bool $csrf = true): Request
    {
        $_POST = $post + ($csrf ? ['_csrf_token' => 'jeton'] : []);
        $_SERVER['REQUEST_METHOD'] = 'POST';

        return new Request();
    }

    private function issueDirect(int $pid, array $input = [], ?SseSheetAccess $a = null): array
    {
        $a ??= self::access();
        $res = (new SseSheetService())->issue(1, $this->subject($pid, $a), 'vigilance', $input + ['lang' => 'fr', 'classification' => 'confidentiel', 'threat' => 'high', 'armed' => 'no', 'validity' => 14, 'faces' => 'auto', 'reasons' => ['M-INS-01']], $a);
        self::assertTrue($res['ok'], implode(' ', $res['errors']));

        return $res;
    }

    private function count(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function flash(string $type): ?string
    {
        return \App\Core\Session::getFlash($type);
    }

    private function editorHtml(int $pid, ?SseSheetAccess $a = null, string $query = ''): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $query !== '' ? ['emis' => $query] : [];

        return (new SseSheetController(null, null, null, null, null, $a ?? self::access()))->editor(new Request(), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance'])->body();
    }

    // ------------------------------------------------------------------ Émission

    public function testTheEditorOffersTheFourLevelsTheServiceAndTheShortcuts(): void
    {
        $pid = $this->person();
        $html = $this->editorHtml($pid);
        self::assertSame(4, substr_count($html, 'data-vig-level'));
        foreach (['VIGILANCE RENFORCÉE', 'APPROCHE AVEC PRÉCAUTION', 'DANGER — ARMÉ ET DANGEREUX'] as $title) {
            self::assertStringContainsString($title, $html);
        }
        // Rang n/4 et forme à côté de la couleur : jamais la couleur seule.
        self::assertStringContainsString('vig-pips', $html);
        self::assertStringContainsString('<polygon', $html);
        self::assertStringContainsString('Service à informer', $html);
        self::assertStringContainsString('Autre…', $html);
        self::assertStringContainsString('name="service_other"', $html);
        self::assertStringContainsString('name="report_delay"', $html);
        self::assertStringContainsString('name="measures[]"', $html);
        self::assertStringContainsString('name="reasons[]"', $html);
        foreach (['h6', 'h24', 'h72', '7', '14', '30', '90', 'until_lifted'] as $v) {
            self::assertStringContainsString('name="validity" value="' . $v . '"', $html, $v);
        }
        self::assertStringContainsString('/rapide', $html);
        self::assertStringContainsString('assets/css/sse-vigilance.css', $html);
        self::assertStringContainsString('assets/js/sse-vigilance.js', $html);
        self::assertStringContainsString('Génération rapide', $html);
        // Jamais le design system, jamais d'eyebrow.
        self::assertStringNotContainsString('sse-ds', $html);
        self::assertStringNotContainsString('eyebrow', $html);
        self::assertSame(0, preg_match('/class="sse-/', substr($html, (int) strpos($html, 'class="page-heading"'))));
    }

    public function testIssuingFromTheEditorKeepsTheChosenLevelConsignesServiceAndGivesAnAddress(): void
    {
        $pid = $this->person();
        $ctl = new SseSheetController(null, null, null, null, null, self::access());
        $req = $this->post([
            'lang' => 'fr', 'classification' => 'confidentiel', 'level' => 'danger', 'validity' => '30', 'measures_sent' => '1', 'measures' => ['A-DNG', 'A-NOI', 'A-QRF'],
            'reasons' => ['M-INS-01', 'M-TER-01'], 'zone' => 'Marché de Georgetown', 'service' => 'OTHER', 'service_other' => 'Cellule de coordination locale',
            'report_channel' => 'Canal SABRE 40.500', 'report_delay' => '15m', 'texts' => ['reason' => 'Motif libre.'],
        ]);
        $res = $ctl->issue($req, ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance']);
        self::assertSame(302, $res->statusCode());
        self::assertSame(1, $this->count('sse_doc_issues'));
        $view = (new SseAlertService())->findVigilance(1, 1);
        self::assertNotNull($view);
        self::assertSame(['danger', 4, 'active'], [$view['level'], $view['rank'], $view['state']]);
        self::assertSame(['A-DNG', 'A-NOI', 'A-QRF'], array_column($view['measures'], 'code'));
        self::assertSame(['M-INS-01', 'M-TER-01'], array_column($view['reasons'], 'code'));
        self::assertSame('Cellule de coordination locale', $view['report']['fr']);
        self::assertSame('15m', $view['report']['delay']);
        self::assertSame('Marché de Georgetown', $view['zone']);
        self::assertStringContainsString('/atak/sse/signalement/', $view['url']);
        self::assertSame($view['url'] . '/qr.svg', SseVigilanceFollow::present($view, self::access(), 0)['qr_url']);
    }

    public function testAnotherCommunityNameInTheFreeServiceFieldIsRefused(): void
    {
        $pid = $this->person();
        $ctl = new SseSheetController(null, null, null, null, null, self::access());
        $res = $ctl->issue($this->post(['lang' => 'fr', 'classification' => 'confidentiel', 'level' => 'watch', 'service' => 'OTHER', 'service_other' => 'FBI']), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance']);
        self::assertSame(422, $res->statusCode());
        self::assertSame(0, $this->count('sse_doc_issues'));
        self::assertStringContainsString('organisation réelle', $res->body());
    }

    public function testIssuingWithoutTheCsrfTokenOrTheRightIsRefusedAndNamesTheMissingRight(): void
    {
        $pid = $this->person();
        $ctl = new SseSheetController(null, null, null, null, null, self::access());
        $res = $ctl->issue($this->post(['lang' => 'fr', 'level' => 'watch'], false), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance']);
        self::assertSame(419, $res->statusCode());
        self::assertSame(0, $this->count('sse_doc_issues'));

        $read = self::access('confidentiel', false, 1, 7, static fn (string $s, string $l): bool => $l === 'read');
        $res = (new SseSheetController(null, null, null, null, null, $read))->issue($this->post(['lang' => 'fr', 'classification' => 'confidentiel', 'level' => 'watch']), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance']);
        self::assertSame(403, $res->statusCode());
        self::assertSame(0, $this->count('sse_doc_issues'));
        self::assertStringContainsString('Droit manquant', $res->body());
        self::assertStringContainsString('Vous pouvez préparer la fiche, pas l’émettre.', (new SseSheetController(null, null, null, null, null, $read))->editor($this->getReq(), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance'])->body());
    }

    private function getReq(): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        return new Request();
    }

    public function testShortDurationsAndUntilLiftedAreIssuedAndShown(): void
    {
        $pid = $this->person();
        $ctl = new SseSheetController(null, null, null, null, null, self::access());
        $ctl->issue($this->post(['lang' => 'fr', 'classification' => 'confidentiel', 'level' => 'watch', 'validity' => 'h24']), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance']);
        $ctl->issue($this->post(['lang' => 'fr', 'classification' => 'confidentiel', 'level' => 'watch', 'validity' => 'until_lifted']), ['kind' => 'personne', 'id' => (string) $pid, 'model' => 'vigilance']);
        self::assertSame(2, $this->count('sse_doc_issues'));
        $h = (new SseAlertService())->findVigilance(1, 1);
        $l = (new SseAlertService())->findVigilance(1, 2);
        self::assertNotSame('', $h['expires_at']);
        self::assertTrue($l['until_lifted']);
        $p = SseVigilanceFollow::present($l, self::access(), 0);
        self::assertSame('Jusqu’à levée', $p['until_label']);
        self::assertSame('active', $p['state']);
    }

    // ------------------------------------------------------------------ Renouveler et lever

    public function testRenewIssuesANewSheetLiftsTheOldOneAndNeedsTheCsrfToken(): void
    {
        $pid = $this->person();
        $first = $this->issueDirect($pid);
        $ctl = new SseVigilanceController(null, null, null, self::access());

        $ctl->renew($this->post(['days' => '90'], false), ['id' => (string) $first['id']]);
        self::assertSame(1, $this->count('sse_doc_issues'));
        self::assertStringContainsString('trop longtemps', (string) $this->flash('error'));

        $res = $ctl->renew($this->post(['days' => '90', 'from' => 'personne']), ['id' => (string) $first['id']]);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('#documents-emis', (string) $res->headerValue('Location'));
        self::assertSame(2, $this->count('sse_doc_issues'));
        $svc = new SseAlertService();
        self::assertSame('lifted', $svc->findVigilance(1, (int) $first['id'])['state']);
        $new = $svc->findVigilance(1, 2);
        self::assertSame(['active', gmdate('Y-m-d', time() + 90 * 86400)], [$new['state'], $new['until']]);
        self::assertStringContainsString('émise jusqu’au', (string) $this->flash('success'));
    }

    public function testRenewWithoutTheRightToIssueIsRefusedWithTheMissingRight(): void
    {
        $pid = $this->person();
        $first = $this->issueDirect($pid);
        $reader = self::access('confidentiel', false, 1, 7, static fn (string $s, string $l): bool => $l === 'read');
        (new SseVigilanceController(null, null, null, $reader))->renew($this->post(['days' => '30']), ['id' => (string) $first['id']]);
        self::assertSame(1, $this->count('sse_doc_issues'));
        $msg = (string) $this->flash('error');
        self::assertStringContainsString('Droit manquant', $msg);
        self::assertStringNotContainsString('Action non autorisée', $msg);
    }

    public function testLiftNeedsAReasonAndTheRightToManageAndRecordsTheLift(): void
    {
        $pid = $this->person();
        $first = $this->issueDirect($pid);
        $id = (string) $first['id'];

        $manager = new SseVigilanceController(null, null, null, self::access());
        $manager->lift($this->post(['reason' => '  ']), ['id' => $id]);
        self::assertStringContainsString('motif', (string) $this->flash('error'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_alert_links WHERE lifted_at IS NOT NULL')->fetchColumn());

        // Un autre compte, sans gérer « Fichiers et sujets » et qui n'est pas l'émetteur.
        $other = self::access('confidentiel', false, 1, 9);
        (new SseVigilanceController(null, null, null, $other))->lift($this->post(['reason' => 'Personne localisée']), ['id' => $id]);
        $msg = (string) $this->flash('error');
        self::assertStringContainsString('Droit manquant : gérer', $msg);
        self::assertStringContainsString('Fichiers et sujets', $msg);

        $manager->lift($this->post(['reason' => 'Personne localisée et prise en charge.', 'from' => 'identite']), ['id' => $id]);
        self::assertStringContainsString('levée', (string) $this->flash('success'));
        $view = (new SseAlertService())->findVigilance(1, (int) $id);
        self::assertSame(['lifted', 'Personne localisée et prise en charge.'], [$view['state'], $view['lift_reason']]);
        // Déjà levée : refusée, dit pourquoi.
        $manager->lift($this->post(['reason' => 'Encore']), ['id' => $id]);
        self::assertStringContainsString('déjà levée', (string) $this->flash('error'));
    }

    public function testASheetOfAnotherCommunityIsNeverReachedByRenewOrLift(): void
    {
        $pid = $this->person();
        $first = $this->issueDirect($pid);
        $foreign = new SseVigilanceController(null, null, null, self::access('confidentiel', true, 2));
        $foreign->lift($this->post(['reason' => 'Tentative']), ['id' => (string) $first['id']]);
        self::assertStringContainsString('introuvable', (string) $this->flash('error'));
        $foreign->renew($this->post(['days' => '30']), ['id' => (string) $first['id']]);
        self::assertStringContainsString('introuvable', (string) $this->flash('error'));
        self::assertSame(1, $this->count('sse_doc_issues'));
        self::assertSame('active', (new SseAlertService())->findVigilance(1, (int) $first['id'])['state']);
        // Un identifiant qui n'existe pas : même réponse.
        $mine = new SseVigilanceController(null, null, null, self::access());
        $res = $mine->lift($this->post(['reason' => 'Tentative']), ['id' => '999']);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('introuvable', (string) $this->flash('error'));
    }

    public function testSheetsAboveTheReadersClearanceAreHiddenFromRenewAndLift(): void
    {
        $pid = $this->person();
        $first = $this->issueDirect($pid, ['classification' => 'confidentiel']);
        $low = new SseVigilanceController(null, null, null, self::access('interne'));
        $low->lift($this->post(['reason' => 'Tentative']), ['id' => (string) $first['id']]);
        self::assertStringContainsString('introuvable', (string) $this->flash('error'));
        $low->renew($this->post(['days' => '30']), ['id' => (string) $first['id']]);
        self::assertStringContainsString('introuvable', (string) $this->flash('error'));
    }

    // ------------------------------------------------------------------ Liste et bandeau

    public function testTheListCarriesStateLevelDeadlineAndTheRightOfEachAction(): void
    {
        $pid = $this->person();
        $a = $this->issueDirect($pid, ['threat' => 'low', 'validity' => 7]);
        $b = $this->issueDirect($pid, ['threat' => 'high', 'armed' => 'yes', 'validity' => 30]);
        $c = $this->issueDirect($pid, ['validity' => 7]);
        (new SseAlertService())->liftVigilance(1, (int) $c['id'], 'Personne localisée', self::access());
        $this->pdo->exec("UPDATE sse_alert_links SET valid_until = '" . gmdate('Y-m-d', time() - 2 * 86400) . "' WHERE target_id = " . (int) $a['id']);

        $follow = SseVigilanceFollow::forSubject(1, $this->subject($pid), self::access(), 0);
        $rows = [];
        foreach ($follow['list'] as $r) {
            $rows[$r['id']] = $r;
        }
        self::assertSame('expired', $rows[$a['id']]['state']);
        self::assertSame('active', $rows[$b['id']]['state']);
        self::assertSame('lifted', $rows[$c['id']]['state']);
        self::assertSame(['danger', 4, 'octagon'], [$rows[$b['id']]['level'], $rows[$b['id']]['rank'], $rows[$b['id']]['shape']]);
        self::assertTrue($rows[$b['id']]['can_renew'] && $rows[$b['id']]['can_lift']);
        self::assertFalse($rows[$c['id']]['can_renew']);
        self::assertStringContainsString('levée', (string) $rows[$c['id']]['renew_denial']);
        self::assertSame('Personne localisée', $rows[$c['id']]['lift_reason']);

        // Lecteur sans droit d'émettre ni de gérer : les deux actions refusées, avec le droit manquant.
        $reader = self::access('confidentiel', false, 1, 9, static fn (string $s, string $l): bool => $l === 'read');
        $p = SseVigilanceFollow::present((new SseAlertService())->findVigilance(1, (int) $b['id']), $reader, 0);
        self::assertFalse($p['can_renew']);
        self::assertFalse($p['can_lift']);
        self::assertStringContainsString('Droit manquant', (string) $p['renew_denial']);
        self::assertStringContainsString('Droit manquant : gérer', (string) $p['lift_denial']);

        ob_start();
        $m = ['vigilances' => $follow['list'], 'kind' => 'personne'];
        require dirname(__DIR__, 2) . '/views/atak/sse/_vigilance_list.php';
        $html = (string) ob_get_clean();
        foreach (['En cours', 'Expirée', 'Levée', 'Renouveler', 'Lever', 'Adresse et QR code', 'name="reason"', 'required', 'name="_csrf_token"', 'qr.svg'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertStringContainsString('/atak/sse/identites/emission/vigilances/' . $b['id'] . '/lever', $html);
        self::assertStringContainsString('/atak/sse/identites/emission/vigilances/' . $b['id'] . '/renouveler', $html);
    }

    public function testTheBannerShowsTheMostSeriousActiveSheetWithoutMoreThanTheClearanceAllows(): void
    {
        $pid = $this->person();
        $this->issueDirect($pid, ['threat' => 'medium', 'validity' => 30]);
        $top = $this->issueDirect($pid, ['threat' => 'high', 'armed' => 'yes', 'validity' => 7, 'zone' => 'Zone secrète', 'service' => 'OTHER', 'service_other' => 'Cellule locale']);
        $follow = SseVigilanceFollow::forSubject(1, $this->subject($pid), self::access(), 0);
        $b = $follow['banner'];
        self::assertNotNull($b);
        self::assertSame([(int) $top['id'], 'danger', 4], [$b['id'], $b['level'], $b['rank']]);
        self::assertSame(1, $b['others']);
        foreach (['reasons', 'zone', 'service', 'report', 'name'] as $k) {
            self::assertFalse(array_key_exists($k, $b), $k);
        }
        // Lecteur dont l'habilitation est plus basse que la classification : ni bandeau, ni détail.
        $low = SseVigilanceFollow::forSubject(1, $this->subject($pid), self::access('interne'), 0);
        self::assertNull($low['banner']);
        self::assertTrue($low['list'][0]['masked']);
        self::assertFalse(array_key_exists('zone', $low['list'][0]));
        self::assertFalse($low['list'][0]['can_pdf']);

        ob_start();
        $m = ['banner' => $b, 'reason' => null, 'ready' => true];
        require dirname(__DIR__, 2) . '/views/atak/sse/_vigilance_banner.php';
        $html = (string) ob_get_clean();
        self::assertStringContainsString('DANGER — ARMÉ ET DANGEREUX', $html);
        self::assertStringContainsString('vig-banner__halo', $html);
        self::assertStringContainsString('vig-banner__veil', $html);
        self::assertStringContainsString('4/4', $html);
        self::assertStringNotContainsString('Zone secrète', $html);
        self::assertStringNotContainsString('Cellule locale', $html);
    }

    public function testNoActiveSheetMeansNoBanner(): void
    {
        $pid = $this->person();
        $f = $this->issueDirect($pid);
        (new SseAlertService())->liftVigilance(1, (int) $f['id'], 'Levée', self::access());
        self::assertNull(SseVigilanceFollow::forSubject(1, $this->subject($pid), self::access(), 0)['banner']);
    }

    // ------------------------------------------------------------------ Vues, FR et EN

    public function testOurViewsHaveNoEyebrowNoSseDesignSystemAndNoFictionMention(): void
    {
        $root = dirname(__DIR__, 2);
        $files = ['views/atak/sse/sheets/vigilance_editor.php', 'views/atak/sse/_vigilance_banner.php', 'views/atak/sse/_vigilance_list.php', 'public/assets/css/sse-vigilance.css', 'public/assets/js/sse-vigilance.js',
            'app/Services/Sse/SseVigilanceFollow.php', 'app/Controllers/Web/SseVigilanceController.php'];
        foreach ($files as $f) {
            $src = (string) file_get_contents($root . '/' . $f);
            foreach (['sse-ds', 'Archivo', 'eyebrow', 'overline', 'toast'] as $bad) {
                self::assertStringNotContainsString($bad, $src, $f . ' : ' . $bad);
            }
            self::assertSame(0, preg_match('/class="[^"]*\bsse-/', $src), $f . ' : classe sse-*');
            foreach (['fiction', 'jeu de rôle', 'simulation', 'spécimen', 'specimen'] as $bad) {
                self::assertStringNotContainsStringIgnoreCaseCompat($bad, $src, $f);
            }
        }
        // Textes de 13 px au moins (0,8125 rem) : aucune taille plus petite dans la feuille de style.
        $css = (string) file_get_contents($root . '/public/assets/css/sse-vigilance.css');
        preg_match_all('/font-size:\s*([0-9.]+)(rem|px)/', $css, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $px = $hit[2] === 'rem' ? (float) $hit[1] * 16 : (float) $hit[1];
            self::assertGreaterThanOrEqual(13.0, $px, $hit[0]);
        }
        // Aucun libellé en capitales posé au-dessus d'un titre : pas de text-transform: uppercase.
        self::assertStringNotContainsString('uppercase', $css);
    }

    private static function assertStringNotContainsStringIgnoreCaseCompat(string $needle, string $hay, string $msg): void
    {
        self::assertFalse(stripos($hay, $needle) !== false, $msg . ' : ' . $needle);
    }

    public function testTheEnglishPairsCoverTheScreensAndTheEnglishScreenIsTranslated(): void
    {
        $tsv = 'C:/Users/tetar/AppData/Local/Temp/claude/15b436256604086c/26c3cb4c-e895-4719-a463-4064f9ad0b4c/scratchpad/ui-agent/en.tsv';
        if (!is_file($tsv)) {
            self::markTestSkipped('paires anglaises hors dépôt absentes');
        }
        $pairs = [];
        foreach (file($tsv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$fr, $en] = array_pad(explode("\t", $line, 2), 2, '');
            self::assertNotSame('', trim($en), 'traduction vide : ' . $fr);
            $pairs[$fr] = $en;
        }
        $site0 = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['Fiche de vigilance', 'Service à informer', 'Niveau de vigilance', 'Renouveler', 'Lever', 'Lever la fiche', 'Jusqu’à levée', 'Adresse et QR code', 'Copier le lien', 'En cours', 'Expirée', 'Levée', 'Générer sans personnaliser'] as $key) {
            self::assertTrue(isset($pairs[$key]) || isset($site0[$key]), $key);
        }
        // Chaque texte fixe de nos trois vues est connu du dictionnaire du site ou de nos paires.
        $site = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        $missing = [];
        foreach (['views/atak/sse/sheets/vigilance_editor.php', 'views/atak/sse/_vigilance_banner.php', 'views/atak/sse/_vigilance_list.php'] as $f) {
            $src = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $f);
            $src = (string) preg_replace('~<\?(?:php|=).*?\?>~s', " ", $src);
            $src = (string) preg_replace('~<(script|style|textarea).*?</>~s', '', $src);
            preg_match_all('~>([^<> ]+)<~u', $src, $nodes);
            preg_match_all('~(?:title|placeholder|aria-label|alt|data-confirm)="([^"<> ]+)"~u', $src, $attrs);
            foreach (array_merge($nodes[1], $attrs[1]) as $t) {
                $t = trim(html_entity_decode($t, ENT_QUOTES, 'UTF-8'));
                if ($t === '' || preg_match('/\p{L}{3}/u', $t) !== 1 || in_array($t, ['English', 'PDF'], true) || isset($pairs[$t]) || isset($site[$t])) {
                    continue;
                }
                $missing[] = $f . ' : ' . $t;
            }
        }
        self::assertSame([], $missing);
        // L'écran anglais est rendu en anglais depuis le catalogue (niveaux, services, consignes).
        $GLOBALS['__app_site_lang'] = 'en';
        $html = $this->editorHtml($this->person());
        unset($GLOBALS['__app_site_lang']);
        self::assertStringContainsString('BE ON THE LOOKOUT', $html);
        self::assertStringContainsString('APPROACH WITH CAUTION', $html);
    }
}
