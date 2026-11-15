<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Request;
use App\Services\Community\BaseRoleLabelAlignment;
use App\Services\Community\TenantDefaultRoleDefinitions;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\CourrierRightGuard;
use PHPUnit\Framework\TestCase;

/**
 * Audit du back-office, point 5.8 : le niveau d'accès de base s'appelle « Membre » partout ; le Bureau Courrier demande
 * « Voir le Bureau Courrier » (comme le menu) côté serveur sur chacune de ses actions, et un droit d'écriture sur les écritures.
 */
final class AuditCourrierMembreTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const CTRL = 'app/Controllers/Courrier/';

    private static function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $path));
    }

    /** @return array<string, string> nom de méthode publique => corps */
    private static function actions(string $source): array
    {
        preg_match_all('/    public function (\w+)\(Request \$request[^\n]*\n    \{\n(.*?)\n    \}\n/s', $source, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $row) {
            $out[$row[1]] = $row[2];
        }

        return $out;
    }

    /** Le code dit « Membre » (français et anglais) ; le slug et la clé technique restent `member`. */
    public function testBaseLevelIsNamedMembreInTheCode(): void
    {
        $member = array_values(array_filter(TenantDefaultRoleDefinitions::operationalRoles(), static fn (array $r): bool => $r['slug'] === 'member'));
        self::assertSame('Membre', $member[0]['name']);
        self::assertSame('Member', TenantDefaultRoleDefinitions::canonicalEnglishLabelsBySlug()['member']);
        $profile = CommunityAccessProfiles::definitionByKey(CommunityAccessProfiles::MEMBER);
        self::assertSame('member', $profile['slug']);
        self::assertSame('Membre', $profile['name']);
    }

    /** Les textes qui nomment le niveau de base disent « Membre » ; les autres sens d'« opérateur » ne bougent pas. */
    public function testTextsNameTheBaseLevelMembre(): void
    {
        foreach ([
            'views/admin/organization/settings.php' => ['ni le rôle obligatoire Membre,', 'ni le rôle obligatoire Opérateur'],
            'views/admin/configuration.php' => ['(ou Visiteur, sinon Membre),', 'sinon Opérateur'],
            'views/documentation/site/_guide_part_a.php' => ['<tr><td>Membre</td><td>Membre titulaire</td>', '<tr><td>Opérateur</td><td>Membre titulaire</td>'],
            'app/Support/DemoPortalAccounts.php' => ["'role_label' => 'Membre',", "'role_label' => 'Opérateur',"],
        ] as $path => [$present, $absent]) {
            $s = self::src($path);
            self::assertStringContainsString($present, $s, $path);
            self::assertStringNotContainsString($absent, $s, $path);
        }
        $guide = self::src('views/documentation/site/_guide_part_a.php');
        self::assertStringContainsString('par exemple Membre et Instructeur', $guide);
        self::assertStringContainsString('Le <strong>Membre</strong> est le membre titulaire type', $guide);
        $en = self::src('lang/en/site_auto.php');
        foreach (['ni le rôle obligatoire Membre,', '(ou Visiteur, sinon Membre), et affectation comme', 'par exemple Membre et Instructeur', 'unité milsim. Le\' =>'] as $key) {
            self::assertStringContainsString($key, $en);
        }
        self::assertStringNotContainsString('rôle obligatoire Opérateur', $en);
        self::assertStringNotContainsString('sinon Opérateur', $en);
        // Les autres « opérateurs » restent.
        self::assertStringContainsString("'Opérateur' => 'Operator',", $en);
        self::assertStringContainsString("'boPageGroup' => 'Opérateur'", self::src('app/Controllers/Web/PersonnelController.php'));
    }

    /** L'étape de données ne renomme que le niveau `member` encore nommé « Opérateur » : jamais un nom choisi par la communauté. */
    public function testMigrationRenamesOnlyTheSeededLabel(): void
    {
        $pdo = new class () extends \PDO {
            /** @var list<array{0: string, 1: object}> */
            public array $log = [];

            public function __construct()
            {
            }

            // SqlText::equals lit le pilote pour choisir la forme de la comparaison du slug.
            public function getAttribute(int $attribute): mixed
            {
                return 'sqlite';
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $st = new class () extends \PDOStatement {
                    /** @var list<mixed> */
                    public array $args = [];

                    public function execute(?array $params = null): bool
                    {
                        $this->args = $params ?? [];

                        return true;
                    }

                    public function rowCount(): int
                    {
                        return 2;
                    }
                };
                $this->log[] = [$query, $st];

                return $st;
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
            {
                return false;
            }
        };

        self::assertSame(2, BaseRoleLabelAlignment::apply($pdo));
        [$sql, $st] = $pdo->log[0];
        self::assertSame('UPDATE roles SET name = ? WHERE tenant_id IS NOT NULL AND slug = ? AND name = ?', $sql);
        self::assertSame(['Membre', 'member', 'Opérateur'], $st->args);

        $pdo->log = [];
        self::assertSame(2, BaseRoleLabelAlignment::revert($pdo));
        self::assertSame(['Opérateur', 'member', 'Membre'], $pdo->log[0][1]->args);

        $step = self::src('database/steps/4520_base_role_label_member.php');
        self::assertStringContainsString('BaseRoleLabelAlignment::apply($pdo)', $step);
        $registry = self::src('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'base_role_label_member'", $registry);
        self::assertStringContainsString("'file' => '4520_base_role_label_member.php', 'rerun' => true]", $registry);
        self::assertTrue(is_file(self::ROOT . 'database/steps/4520_base_role_label_member.php'));
    }

    /** Toutes les actions du Bureau Courrier vérifient un droit, sauf la vérification publique par UUID. */
    public function testEveryCourrierActionChecksARight(): void
    {
        $expected = [
            'CourrierDashboardController' => ['index' => 'VIEW', 'history' => 'VIEW', 'archives' => 'ARCHIVES'],
            'CourrierReadController' => ['show' => 'VIEW'],
            'CourrierPdfController' => ['print' => 'VIEW', 'pdf' => 'VIEW', 'pdfExternal' => 'VIEW'],
            'CourrierNotificationController' => ['index' => 'VIEW', 'notify' => 'VIEW'],
            'CourrierEditorController' => ['index' => 'EDITOR_READ', 'edit' => 'EDITOR_READ', 'save' => 'WRITE'],
            'CourrierTemplateController' => ['index' => 'EDITOR_READ', 'create' => 'WRITE', 'store' => 'WRITE', 'edit' => 'EDITOR_READ', 'update' => 'WRITE'],
            'CourrierPresetController' => ['index' => 'EDITOR_READ', 'setDefault' => 'WRITE'],
            'CourrierSnippetController' => ['list' => 'EDITOR_READ'],
            'CourrierWorkflowController' => ['transition' => 'WORKFLOW'],
            'CourrierSignatureController' => [
                'verify' => 'VIEW', 'mySignatures' => 'VIEW', 'signatureImage' => 'VIEW', 'documentSignatureImage' => 'VIEW',
                'manage' => 'VIEW', 'store' => 'VIEW', 'setDefault' => 'VIEW', 'destroy' => 'VIEW',
            ],
        ];
        foreach ($expected as $class => $methods) {
            $actions = self::actions(self::src(self::CTRL . $class . '.php'));
            foreach ($methods as $method => $set) {
                self::assertArrayHasKey($method, $actions, $class . '::' . $method);
                self::assertStringContainsString(
                    'if (($denied = CourrierRightGuard::deniedUnless($request, CourrierRightGuard::' . $set . ')) !== null) {',
                    substr($actions[$method], 0, 220),
                    $class . '::' . $method
                );
            }
        }
        // sign (droit propre) et traçabilité (déjà gardée) ne sont pas touchées ; verifyByUuid reste publique.
        $sign = self::actions(self::src(self::CTRL . 'CourrierSignatureController.php'));
        self::assertStringNotContainsString('CourrierRightGuard', $sign['verifyByUuid']);
        self::assertStringContainsString("allows('courrier.validate')", $sign['sign']);
        $routes = self::src('routes/web.php');
        self::assertStringContainsString("\$router->get('/courrier/verify', [CourrierSignatureController::class, 'verifyByUuid']);", $routes);
        preg_match_all('#\$router->(?:get|post)\(\'/courrier[^\']*\', [^\n]*\n#', $routes, $m);
        foreach ($m[0] as $line) {
            if (str_contains($line, "'/courrier/verify'")) {
                continue;
            }
            self::assertStringContainsString('$mwCourrier);', $line);
        }
    }

    /** Les droits de lecture sont ceux du menu ; ceux d'écriture sont les droits de rédaction et de validation qui existent déjà. */
    public function testRightsMatchTheMenuAndExistingPermissions(): void
    {
        self::assertSame(['courrier.view', 'admin.organization', 'admin.access'], CourrierRightGuard::VIEW);
        self::assertSame(['courrier.view', 'courrier.archive', 'admin.organization', 'admin.access'], CourrierRightGuard::ARCHIVES);
        self::assertSame(['courrier.create', 'courrier.validate', 'admin.organization', 'admin.access'], CourrierRightGuard::WRITE);
        $catalog = self::src('app/Authorization/TenantPermissionCatalog.php');
        foreach (['courrier.view', 'courrier.create', 'courrier.validate', 'courrier.archive'] as $slug) {
            self::assertStringContainsString("'slug' => '" . $slug . "'", $catalog);
        }
        $nav = self::src('config/navigation.php');
        self::assertStringContainsString("'path' => 'courrier', 'permission' => 'courrier.view'", $nav);
        self::assertStringContainsString("'path' => 'courrier/history', 'permission' => 'courrier.view'", $nav);
        self::assertStringContainsString("'path' => 'courrier/archives', 'any_permissions' => ['courrier.view', 'courrier.archive']", $nav);
        self::assertStringContainsString("'path' => 'courrier/editor', 'permission' => 'courrier.create'", $nav);
        self::assertStringContainsString("'path' => 'courrier/templates', 'any_permissions' => ['courrier.create', 'courrier.validate']", $nav);
    }

    /** Le niveau Membre livré n'a pas courrier.view ; le Gestionnaire (tous les droits) l'a : seuls les membres sans le droit perdent l'accès manuel. */
    public function testDefaultMemberLevelHasNoCourrierRightAndManagerKeepsAll(): void
    {
        self::assertNotContains('courrier.view', CommunityAccessProfiles::memberPermissionSlugs());
        self::assertNotContains('courrier.view', CommunityAccessProfiles::hrPermissionSlugs());
        foreach (['courrier.view', 'courrier.create', 'courrier.validate', 'courrier.archive'] as $slug) {
            self::assertContains($slug, CommunityAccessProfiles::managerPermissionSlugs());
        }
    }

    /** Comportement : lecture refusée sans courrier.view (403 JSON pour un script, redirection pour un navigateur), admise avec. */
    public function testGuardRefusesWithoutViewAndAdmitsHolders(): void
    {
        $_SERVER['REQUEST_URI'] = '/courrier/history';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';
        $script = new Request();
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
        $browser = new Request();
        $gate = Gate::getInstance();
        try {
            $gate->setPermissions(['documents.view', 'training.view']);
            $denied = CourrierRightGuard::deniedUnless($script, CourrierRightGuard::VIEW);
            self::assertNotNull($denied);
            self::assertSame(403, $denied->statusCode());
            $page = CourrierRightGuard::deniedUnless($browser, CourrierRightGuard::VIEW);
            self::assertNotNull($page);
            self::assertSame(302, $page->statusCode());

            $gate->setPermissions(['courrier.view']);
            self::assertNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::VIEW));
            self::assertNull(CourrierRightGuard::deniedUnless($browser, CourrierRightGuard::ARCHIVES));
            self::assertNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::EDITOR_READ));
            // Lire n'autorise pas d'écrire.
            self::assertNotNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::WRITE));

            $gate->setPermissions(['courrier.archive']);
            self::assertNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::ARCHIVES));
            self::assertNotNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::VIEW));

            foreach (['courrier.create', 'courrier.validate'] as $right) {
                $gate->setPermissions([$right]);
                self::assertNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::WRITE), $right);
            }
            // L'administration de la communauté garde l'accès, comme pour la traçabilité et les contrôles internes du module.
            foreach (['admin.organization', 'admin.access'] as $manager) {
                $gate->setPermissions([$manager]);
                self::assertNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::VIEW), $manager);
                self::assertNull(CourrierRightGuard::deniedUnless($script, CourrierRightGuard::WRITE), $manager);
            }
        } finally {
            $gate->setPermissions([]);
            unset($_SERVER['HTTP_SEC_FETCH_DEST'], $_SERVER['REQUEST_URI'], $_SESSION['_flash']);
        }
    }
}
