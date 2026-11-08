<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\SystemUsersController;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Support\CsvSafe;
use App\Support\PlatformUserColumns;
use App\Support\PlatformUsersDirectoryQuery;
use PHPUnit\Framework\TestCase;

/**
 * Tableau des comptes (administration du site) : listes blanches du tri et des filtres, export CSV sûr,
 * et rendu réel de la page avec des données fictives (clés de tableau numériques comprises).
 */
final class PlatformUsersTableTest extends TestCase
{
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 1];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(base_path('views/' . $view . '.php'), $vars);
    }

    public function testSortAndFiltersOnlyAcceptKnownValues(): void
    {
        foreach (['u.email; DROP TABLE users', "nom' OR '1'='1", 'NOM ', 'password_hash', '', '0'] as $hostile) {
            [$key] = UserRepository::normalizePlatformDirectorySort($hostile, 'asc');
            self::assertContains($key, ['', 'nom'], 'Tri accepté pour ' . $hostile);
        }
        self::assertSame(['', 'desc'], UserRepository::normalizePlatformDirectorySort('inconnu', 'asc'));
        self::assertSame(['connexion', 'desc'], UserRepository::normalizePlatformDirectorySort('connexion', 'DESC'));
        self::assertSame(['email', 'asc'], UserRepository::normalizePlatformDirectorySort('email', 'n importe quoi'));
        foreach (UserRepository::PLATFORM_DIRECTORY_SORTS as $key) {
            self::assertSame($key, UserRepository::normalizePlatformDirectorySort($key, 'asc')[0]);
        }

        $f = PlatformUsersDirectoryQuery::normalize([
            'q' => str_repeat('é', 500), 'status' => 'root', 'inactif' => '9999', 'cree' => 'x', 'sanction' => 'oui',
            'tri' => 'u.id', 'sens' => 'desc', 'par' => '100000', 'page' => '-3', 'tenant_id' => '-9',
        ]);
        self::assertSame(120, mb_strlen($f['q']));
        self::assertSame('', $f['status']);
        self::assertSame('', $f['inactif']);
        self::assertSame('', $f['cree']);
        self::assertSame('', $f['sanction']);
        self::assertSame('', $f['tri']);
        self::assertSame(50, $f['par']);
        self::assertSame(1, $f['page']);
        self::assertSame(0, $f['tenant_id']);
        self::assertNull($f['cols']);

        // Une valeur non scalaire (?q[]=…) ne provoque ni erreur ni filtre.
        $arr = PlatformUsersDirectoryQuery::normalize(['q' => ['x'], 'tri' => ['nom'], 'status' => ['active']]);
        self::assertSame('', $arr['q']);
        self::assertSame('', $arr['tri']);
    }

    public function testSortSqlComesFromTheWhitelistOnly(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertMatchesRegularExpression('/function platformDirectorySortSpec\(string \$key, bool \$hasMemberships, bool \$hasDeletedAt\): \?array/', $repo);
        // Le sens ne peut produire que deux mots, et la clé est validée avant d'arriver à la requête.
        self::assertStringContainsString("\$dir = \$sortDir === 'desc' ? 'DESC' : 'ASC';", $repo);
        self::assertStringContainsString('self::normalizePlatformDirectorySort(', $repo);
        self::assertStringNotContainsString("ORDER BY {\$options", $repo);
    }

    public function testQueryStringKeepsOnlyNonDefaultValuesAndColumns(): void
    {
        $f = PlatformUsersDirectoryQuery::normalize(['tri' => 'creation', 'sens' => 'desc', 'par' => '25', 'cols_set' => '1', 'cols' => ['email', 'bidon', 'volume']]);
        self::assertSame(['nom', 'email', 'volume'], $f['cols']);
        $qs = PlatformUsersDirectoryQuery::query($f, ['page' => 3]);
        parse_str($qs, $back);
        self::assertSame('creation', $back['tri']);
        self::assertSame('desc', $back['sens']);
        self::assertSame('25', $back['par']);
        self::assertSame('3', $back['page']);
        self::assertSame(['nom', 'email', 'volume'], $back['cols']);
        self::assertSame('', PlatformUsersDirectoryQuery::query(PlatformUsersDirectoryQuery::normalize([])));
        self::assertSame(PlatformUserColumns::DEFAULT, PlatformUserColumns::normalize(null));
        self::assertSame(['nom'], PlatformUserColumns::normalize([]));
    }

    public function testCsvNeutralisesSpreadsheetFormulas(): void
    {
        foreach (['=1+1', '+33600000000', '-2+3', '@SUM(A1)', "\tcmd", "\rcmd", '=HYPERLINK("http://x")'] as $cell) {
            self::assertSame("'" . $cell, CsvSafe::cell($cell), $cell);
        }
        foreach (['Alice', '', 'a=b', 'x@y', 12, 0] as $cell) {
            self::assertSame((string) $cell, CsvSafe::cell($cell));
        }
        $csv = CsvSafe::build(['Nom', 'E-mail'], [['=cmd|calc', 'alice@example.test'], ['@Evil', '-1']]);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString("'=cmd|calc", $csv);
        self::assertStringContainsString("'@Evil", $csv);
        self::assertStringNotContainsString(';=cmd', $csv);
    }

    public function testExportIsGuardedLoggedAndLimited(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertMatchesRegularExpression('/function exportCsv\(Request \$request/', $ctrl);
        self::assertStringContainsString('CsvSafe::build(', $ctrl);
        self::assertStringContainsString('AuditAction::PLATFORM_USERS_EXPORTED', $ctrl);
        self::assertStringContainsString('Csrf::validate', $ctrl);
        self::assertStringContainsString('EXPORT_MAX_ROWS = 5000', $ctrl);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("\$router->get('/admin/users/export', [SystemUsersController::class, 'exportCsv'], [AuthMiddleware::class, SystemAdminMiddleware::class]);", $routes);
        self::assertStringContainsString("\$router->post('/admin/users/export', [SystemUsersController::class, 'exportCsv'], [AuthMiddleware::class, SystemAdminMiddleware::class]);", $routes);
        $labels = (string) file_get_contents($root . '/app/Services/Audit/AuditActionLabel.php');
        self::assertStringContainsString("'platform.users_exported'", $labels);
    }

    public function testStateOfAPersonComesFromTheirSheets(): void
    {
        $live = static fn (string $status): array => ['status' => $status, 'deleted_at' => null];
        self::assertSame('actif', SystemUsersController::stateFromMemberships([$live('inactive'), $live('active')]));
        self::assertSame('verif', SystemUsersController::stateFromMemberships([$live('inactive'), $live('pending_verification')]));
        self::assertSame('suspendu', SystemUsersController::stateFromMemberships([$live('inactive')]));
        self::assertSame('anonymise', SystemUsersController::stateFromMemberships([['status' => 'active', 'deleted_at' => '2026-01-01 00:00:00']]));
        self::assertSame('anonymise', SystemUsersController::stateFromMemberships([['status' => 'merged', 'deleted_at' => null]]));
    }

    /** @return list<array<string, mixed>> */
    private function fakeGroups(): array
    {
        $make = static fn (int $id, string $email, string $name, string $call, string $state, array $extra = []): array => array_merge([
            'email' => $email, 'email_key' => $email, 'display_name' => $name, 'callsign' => $call,
            'memberships' => [[
                'id' => $id, 'tenant_id' => 2, 'email' => $email, 'display_name' => $name, 'callsign' => $call,
                'status' => 'active', 'created_at' => '2025-03-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00',
                'last_login_at' => '2026-11-01 09:30:00', 'steam_id' => '76561198000000001', 'deleted_at' => null,
                'tenant_name' => 'Unité Exemple', 'tenant_slug' => 'exemple', 'role_name' => 'Membre',
                'membership_status' => 'active', 'membership_left_at' => null,
            ]],
            'account_anchor' => ['id' => $id, 'tenant_id' => 2, 'deleted_at' => null],
            'no_org' => false, 'ban' => null, 'sanctions_count' => 0,
            'community_names' => ['Unité Exemple', 'Autre unité', 'Troisième unité'],
            'last_login' => '2026-11-01 09:30:00', 'created' => '2025-03-01 10:00:00',
            'volume_bytes' => 1234567, 'has_steam' => true, 'state' => $state,
        ], $extra);

        return [
            $make(10, 'alice@example.test', 'Alice Test', 'Viper', 'actif'),
            $make(11, 'evil@example.test', '=HYPERLINK("x")', '<b>Evil</b>', 'banni', [
                'ban' => ['created_at' => '2026-11-01 10:00:00', 'ends_at' => '2026-12-01 22:59:59', 'reason' => 'Motif <script>x</script>'],
                'sanctions_count' => 2,
            ]),
            $make(12, 'bob@example.test', '', '', 'suspendu', ['community_names' => [], 'has_steam' => false, 'last_login' => '']),
        ];
    }

    public function testUsersPageRendersUnderStrictTypesWithNumericKeysAndHostileText(): void
    {
        $filters = PlatformUsersDirectoryQuery::normalize(['tri' => 'connexion', 'sens' => 'desc', 'inactif' => '30', 'cree' => '90', 'cols_set' => '1', 'cols' => ['email', 'etat', 'volume', 'sanctions', 'connexion', 'creation', 'communautes', 'indicatif']]);
        $html = $this->render('admin/system/users', [
            'platformUserGroups' => $this->fakeGroups(), 'platformUsersTotal' => 123, 'platformUsersPage' => 2, 'platformUsersPages' => 3,
            'platformUsersFilters' => $filters, 'platformUserColumns' => $filters['cols'],
            'platformTenants' => [['id' => 2, 'name' => 'Unité Exemple', 'slug' => 'exemple']],
            'pendingPurgeRequests' => [], 'pendingPurgeRequestsCount' => 0, 'platformBansCount' => 1, 'platformBanSupportsEnd' => true,
        ]);
        // Tableau : en-tête avec tri serveur, une ligne par personne, accès au dossier complet, export, sélection.
        self::assertStringContainsString('aria-sort="descending"', $html);
        self::assertSame(3, substr_count($html, 'data-pu-row'));
        self::assertStringContainsString('Dossier complet', $html);
        self::assertStringContainsString('admin/users/person?email=alice%40example.test', $html);
        self::assertStringContainsString('admin/users/export', $html);
        self::assertStringContainsString('data-pu-select-all', $html);
        self::assertStringContainsString('Données par jour', $html);
        self::assertStringContainsString('Non mesurable', $html);
        self::assertStringContainsString('Sans communauté', $html);
        // Les textes saisis par les personnes sont échappés.
        self::assertStringNotContainsString('<script>x</script>', $html);
        self::assertStringNotContainsString('<b>Evil</b>', $html);
        self::assertStringContainsString('&lt;b&gt;Evil&lt;/b&gt;', $html);
        // Fin de sanction affichée à l'heure de Paris.
        self::assertStringContainsString('01/12/2026 à 23:59', $html);
        // Colonne non choisie : absente du tableau (et donc de l'export).
        $few = PlatformUsersDirectoryQuery::normalize(['cols_set' => '1', 'cols' => ['email']]);
        $slim = $this->render('admin/system/users', [
            'platformUserGroups' => $this->fakeGroups(), 'platformUsersTotal' => 3, 'platformUsersFilters' => $few, 'platformUserColumns' => $few['cols'],
            'platformTenants' => [], 'pendingPurgeRequests' => [], 'platformBansCount' => 0,
        ]);
        self::assertStringContainsString('data-col="email"', $slim);
        self::assertStringNotContainsString('data-col="volume"', $slim);
        self::assertStringNotContainsString('data-col="connexion"', $slim);

        $empty = $this->render('admin/system/users', [
            'platformUserGroups' => [], 'platformUsersTotal' => 0, 'platformUsersFilters' => PlatformUsersDirectoryQuery::normalize([]),
            'platformUserColumns' => PlatformUserColumns::DEFAULT, 'platformTenants' => [],
        ]);
        self::assertStringContainsString('Aucun compte ne correspond', $empty);
    }

    public function testSanctionsAndDataPagesRender(): void
    {
        $days = ['2026-11-02', '2026-11-03'];
        $totals = [
            '2026-11-02' => ['bytes' => 5000, 'photo_bytes' => 100, 'requests' => 12, 'communities' => 1, 'photos' => 3],
            '2026-11-03' => ['bytes' => 0, 'photo_bytes' => 0, 'requests' => 0, 'communities' => 0, 'photos' => null],
        ];
        foreach (['communaute', 'compte'] as $view) {
            $rows = $view === 'compte'
                ? [['label' => 'Viper', 'sub' => 'Alice Test', 'email_key' => 'alice@example.test', 'linked' => true, 'total' => 5000, 'cells' => ['2026-11-02' => 5000, '2026-11-03' => 0]],
                    ['label' => 'Joueur inconnu', 'sub' => '', 'email_key' => '', 'linked' => false, 'total' => 10, 'cells' => ['2026-11-02' => 10, '2026-11-03' => 0]]]
                : [['tenant_id' => 2, 'name' => 'Unité Exemple', 'slug' => 'exemple', 'total' => 5000, 'cells' => ['2026-11-02' => 5000, '2026-11-03' => 0]]];
            $html = $this->render('admin/system/users_data', [
                'dataPeriod' => 7, 'dataView' => $view, 'dataTenantId' => 0, 'dataTenants' => [],
                'dataTotals' => $totals, 'dataTable' => ['days' => $days, 'rows' => $rows, 'truncated' => false],
                'dataSources' => ['communities' => true, 'players' => true, 'photos' => true], 'dataError' => null,
            ]);
            self::assertStringContainsString('Ce que mesure chaque chiffre', $html);
            self::assertStringContainsString('jamais un poids', $html);
            self::assertStringContainsString('02/11', $html);
        }
        $none = $this->render('admin/system/users_data', ['dataSources' => ['communities' => false, 'players' => false, 'photos' => false], 'dataError' => null]);
        self::assertStringContainsString('aucune mesure', $none);

        $person = $this->render('admin/system/user_person', [
            'personEmail' => 'alice@example.test', 'personDisplayName' => 'Alice Test', 'personCallsign' => 'Viper', 'personSteamId' => '',
            'personAthenaId' => '', 'personCivil' => [], 'personHasLiveOrg' => true, 'personMemberships' => [], 'personMergePreview' => [],
            'personIsPlatformAdmin' => false, 'personKnownIps' => [], 'personBan' => null, 'personBanSupportsEnd' => true, 'personLiveFiches' => 1,
            'personBanHistory' => [
                ['id' => 2, 'reason' => 'Motif <i>un</i>', 'created_at' => '2026-05-01 10:00:00', 'lifted_at' => '2026-05-08 10:00:00', 'ends_at' => '2026-05-08 10:00:00',
                    'banned_by_user_id' => 1, 'lifted_by_user_id' => null, 'banned_by_name' => 'Admin', 'banned_by_callsign' => 'Zeus', 'lifted_by_name' => null, 'lifted_by_callsign' => null, 'lift_note' => 'Fin'],
            ],
        ]);
        self::assertStringContainsString('id="historique-sanctions"', $person);
        self::assertStringContainsString('name="confirm_ban"', $person);
        self::assertStringContainsString('Terminée (date de fin atteinte)', $person);
        self::assertStringNotContainsString('<i>un</i>', $person);
    }
}
