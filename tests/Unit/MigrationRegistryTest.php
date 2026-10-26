<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Migrations\MigrationRegistry;
use App\Support\Migrations\MigrationStepTracker;
use PHPUnit\Framework\TestCase;

/** Registre des étapes du pipeline, classement des sorties, seeds retirés. */
final class MigrationRegistryTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testEveryRegisteredStepExistsIsUniqueAndLints(): void
    {
        $steps = MigrationRegistry::load($this->root());
        self::assertGreaterThan(250, count($steps));
        $ids = array_column($steps, 'id');
        self::assertSame(count($ids), count(array_unique($ids)));
        foreach ($steps as $i => $s) {
            self::assertFileExists($s['path'], $s['id']);
            self::assertArrayHasKey($s['group'], MigrationRegistry::GROUPS);
            self::assertSame($i + 1, $s['index']);
            $src = (string) file_get_contents($s['path']);
            self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $src, $s['file']);
        }
        // Ordre historique : schéma d'abord, fichiers SQL puis FBAC à la fin.
        self::assertSame('schema', $ids[0]);
        self::assertLessThan(array_search('function_based_access_v2', $ids, true), array_search('sql_files', $ids, true));
        self::assertLessThan(array_search('atak_recon_notes', $ids, true), array_search('payments_ledger', $ids, true));
        self::assertLessThan(array_search('atak_recon_notes', $ids, true), array_search('inbox_hide_threads', $ids, true));
        self::assertNotNull(MigrationRegistry::find($this->root(), 'atak_maps'));
        self::assertNull(MigrationRegistry::find($this->root(), 'inconnue'));
    }

    public function testLegacyContentSeedsAreNoLongerInThePipeline(): void
    {
        $src = \Tests\Support\MigrationPipelineSource::read($this->root());
        foreach (['run_training_onboarding_course_seed', 'run_training_roles_org_course_seed', 'run_training_bureau_recrutement_course_seed',
            'run_training_atak_course_seed', 'run_training_onboarding_bulk_assign', 'cerbere_officiel', "password_hash('admin'"] as $gone) {
            self::assertStringNotContainsString($gone, $src, $gone);
        }
        // Les fichiers restent : la création d'une communauté s'en sert encore (TenantSeedHelper).
        self::assertFileExists($this->root() . '/bootstrap/training_onboarding_course_seed.php');
        // Référentiels gardés.
        foreach (['atak_maps', 'grade_referential_seed', 'courrier_presets_variables', 'platform_system_tenant'] as $kept) {
            self::assertNotNull(MigrationRegistry::find($this->root(), $kept), $kept);
        }
        self::assertStringContainsString('ATHENA_FIRST_ADMIN_PASSWORD', $src);
    }

    public function testClassifyAndRedact(): void
    {
        self::assertSame(['ok', 'Rien à faire : déjà à jour.'], MigrationStepTracker::classify(''));
        self::assertSame(['ok', 'table prête'], MigrationStepTracker::classify("Migration x...\n  [OK] table prête\n"));
        self::assertSame(['warn', 'Fichier absent : a.php'], MigrationStepTracker::classify("  [ATTENTION] Fichier absent : a.php\n"));
        self::assertSame(['error', 'Étape x : boum'], MigrationStepTracker::classify("ok\n  [ERREUR] Étape x : boum\n  [ATTENTION] y\n"));
        self::assertSame('error', MigrationStepTracker::classify("  [ATTENTION] z\n", true)[0]);
        self::assertSame('Mot de passe généré (une fois) : ••••••••', MigrationStepTracker::redact('Mot de passe généré (une fois) : Abc123xyz'));
    }

    public function testStepsNeverEndTheWholeScriptByAccident(): void
    {
        foreach (MigrationRegistry::load($this->root()) as $s) {
            $tokens = token_get_all((string) file_get_contents($s['path']));
            $depth = 0;
            foreach ($tokens as $t) {
                if ($t === '{') {
                    $depth++;
                } elseif ($t === '}') {
                    $depth--;
                } elseif ($depth === 0 && is_array($t) && $t[0] === T_RETURN) {
                    self::fail('return au premier niveau dans ' . $s['file']);
                }
            }
        }
        self::assertTrue(true);
    }
}
