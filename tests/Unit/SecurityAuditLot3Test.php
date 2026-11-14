<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 3 : API d'accès, qualifications, documents RH, onboarding, API tactiques, GET qui écrivent. */
final class SecurityAuditLot3Test extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** 0.7 : l'API des règles d'accès ne répond plus à un membre ordinaire, en lecture comme en écriture. */
    public function testAccessControlApiReadsAreForAdminsToo(): void
    {
        $s = self::src('app/Controllers/Api/AccessControlApiController.php');
        foreach (['roles', 'permissions', 'rules', 'scopes'] as $method) {
            $body = substr($s, (int) strpos($s, 'public function ' . $method . '('));
            self::assertStringContainsString('if ($request->isGet()) {', substr($body, 0, 320), $method);
            self::assertStringContainsString('denyUnlessTenantAdmin($request)', substr($body, 0, 420), $method);
        }
        $sim = substr($s, (int) strpos($s, 'public function simulation('));
        self::assertStringContainsString('denyUnlessTenantAdmin($request)', substr($sim, 0, 260));
        // Le jeton n'est exigé que sur les écritures.
        self::assertStringContainsString('!$request->isGet() && !Csrf::verify($request)', $s);
        // Le journal d'audit des interventions reste branché sur la session d'intervention (AuthMiddleware).
        self::assertStringContainsString('TenantAdminAuditMiddleware', self::src('app/Middleware/AuthMiddleware.php'));
    }

    /** 0.8 : les droits liés à une qualification ne donnent rien aujourd'hui, et l'écran le dit. */
    public function testQualificationGrantsSayTheyAreNotApplied(): void
    {
        $view = self::src('views/admin/organization/qualifications/form.php');
        self::assertStringContainsString('data-qual-grants-inactive', $view);
        self::assertStringContainsString('ne donnent aucun accès aujourd’hui', $view);
        self::assertStringContainsString('il ne donne pas encore d’accès automatiquement', self::src('app/Controllers/Admin/Organization/QualificationReferentielController.php'));
        // Aucune migration ne crée les tables cibles : le mécanisme reste sans effet.
        $found = [];
        foreach ((array) glob(self::ROOT . 'migrations/*') as $file) {
            if (is_file($file) && preg_match('/user_direct_permissions|tenant_user_permissions/', (string) file_get_contents($file))) {
                $found[] = $file;
            }
        }
        self::assertSame([], $found);
    }

    /** 0.10 : le coffre de documents RH exige les droits de gestion du dossier, pas seulement l'accès aux effectifs. */
    public function testHrDocumentVaultNeedsAFileRight(): void
    {
        $access = self::src('app/Support/EffectifsLmsAccess.php');
        $fn = substr($access, (int) strpos($access, 'function canViewHrDocuments('));
        self::assertStringContainsString("self::canEditProfiles(\$gate) || \$gate->allows('personnel.status.manage')", substr($fn, 0, 200));
        $ctrl = self::src('app/Controllers/Admin/RhDossierWorkspaceController.php');
        $list = substr($ctrl, (int) strpos($ctrl, 'public function documents('));
        self::assertStringContainsString('canViewHrDocuments', substr($list, 0, 900));
        $dl = substr($ctrl, (int) strpos($ctrl, 'public function downloadDocument('));
        self::assertStringContainsString('canViewHrDocuments', substr($dl, 0, 500));
        self::assertLessThan(strpos($dl, 'findById($id, $tenantId)'), strpos($dl, 'canViewHrDocuments'));
        // La fiche membre ne liste plus les pièces à qui ne peut pas les ouvrir.
        self::assertStringContainsString('canViewHrDocuments(Gate::getInstance())', self::src('app/Controllers/Admin/EffectifsWorkspaceController.php'));
        // Le membre garde le téléchargement de ses propres pièces.
        self::assertStringContainsString("(int) (\$row['user_id'] ?? 0) !== \$userId", self::src('app/Controllers/Web/RhWorkspaceController.php'));
    }

    /** 0.12 : l'onboarding n'écrit un grade ou un niveau d'accès que pour qui a le droit correspondant, côté serveur. */
    public function testOnboardingGradeAndAccessNeedTheirOwnRights(): void
    {
        $ctrl = self::src('app/Controllers/Admin/AdminRecruitmentsController.php');
        $save = substr($ctrl, (int) strpos($ctrl, 'public function onboardingSave('));
        $guard = strpos($save, 'canManageGrades($gate)');
        self::assertNotFalse($guard);
        self::assertLessThan(strpos($save, 'normalizeSubmission('), $guard);
        self::assertStringContainsString("\$input['grade_id'] = '';", $save);
        self::assertStringContainsString('canManageRoles($gate)', $save);
        self::assertStringContainsString("\$input['access_role_id'] = '';", $save);
        $view = self::src('views/admin/recruitments/onboarding.php');
        self::assertStringContainsString("name=\"grade_id\"<?= \$canChooseGrade ? '' : ' disabled' ?>", $view);
        self::assertStringContainsString("<?= \$canChooseAccessRole ? '' : ' disabled' ?>", $view);
    }

    /** 0.13 : le relief, les routes et la scène ne sont réécrits que par le mod (clé valide) ou un responsable ATAK. */
    public function testTacticalIngestNeedsKeyOrManager(): void
    {
        $scope = self::src('app/Support/TacticalMissionScope.php');
        $fn = substr($scope, (int) strpos($scope, 'function denyUnlessKeyOrManager('));
        self::assertStringContainsString('ComspecApiKeyAuth::requestPresentsValidKey()', substr($fn, 0, 200));
        self::assertStringContainsString("'atak.manage'", substr($fn, 0, 900));
        self::assertStringContainsString("'admin.organization'", substr($fn, 0, 900));
        self::assertStringContainsString('403', substr($fn, 0, 1100));
        self::assertSame(2, substr_count(self::src('app/Controllers/Api/AtakTerrainApiController.php'), 'TacticalMissionScope::denyUnlessKeyOrManager()'));
        self::assertSame(1, substr_count(self::src('app/Controllers/Api/AtakGeoNetworkApiController.php'), 'TacticalMissionScope::denyUnlessKeyOrManager()'));
        self::assertSame(1, substr_count(self::src('app/Controllers/Api/AtakSceneApiController.php'), 'TacticalMissionScope::denyUnlessKeyOrManager()'));
        // Les écrans web qui lisent et alimentent le tableau de situation ne sont pas touchés.
        self::assertStringNotContainsString('denyUnlessKeyOrManager', self::src('app/Controllers/Api/IffController.php'));
    }

    /** 0.15 : afficher une page n'écrit plus rien ; une tâche planifiée remet les données en ordre. */
    public function testPagesNoLongerWriteOnGet(): void
    {
        self::assertStringNotContainsString('assignMissing(', self::src('app/Controllers/Admin/EffectifsWorkspaceController.php'));
        $grades = self::src('app/Controllers/Admin/Organization/AdvancementAdminController.php');
        $index = substr($grades, (int) strpos($grades, 'public function grades('), 900);
        self::assertStringNotContainsString('completeForTenant', $index);
        self::assertStringNotContainsString('completeForTenant', substr(self::src('app/Controllers/Admin/Organization/GradeReferentielController.php'), 0, 4000));
        $imp = substr($grades, (int) strpos($grades, 'public function importScale('));
        self::assertStringContainsString('completeForTenant', substr($imp, 0, 600));
        $realism = self::src('app/Controllers/Admin/AdminAtakRealismController.php');
        self::assertStringNotContainsString('repairCorruptIdentitiesForTenant', $realism);
        self::assertStringNotContainsString('persistWebSessionClassification', $realism);
        self::assertStringNotContainsString('ensureDefaultCryptoDomain', $realism);
        self::assertStringNotContainsString('repairCorruptIdentitiesForTenant', self::src('app/Controllers/Admin/AdminAtakOperatorsController.php'));
        $cfg = self::src('app/Controllers/Admin/AdminAtakRealismConfigController.php');
        self::assertStringNotContainsString('upsertConfig', substr($cfg, 0, (int) strpos($cfg, 'public function save(')));
        // Le mod, qui lisait la configuration créée par la première visite, reçoit les valeurs par défaut.
        self::assertStringContainsString('buildDefaultConfig()', self::src('app/Controllers/Api/AtakRealismApiController.php'));
        $job = self::src('app/Services/Cron/Jobs/TenantDataUpkeepCronJob.php');
        foreach (['NewMemberNumbers::assignMissing', 'ensureForTenant', 'repairCorruptIdentitiesForTenant', 'persistWebSessionClassification', 'ensureDefaultCryptoDomain'] as $needle) {
            self::assertStringContainsString($needle, $job);
        }
        self::assertStringContainsString('TenantDataUpkeepCronJob::class', self::src('app/Core/Container.php'));
        self::assertStringContainsString("'tenant_data_upkeep'", self::src('app/Services/Cron/CronSchedule.php'));
    }
}
