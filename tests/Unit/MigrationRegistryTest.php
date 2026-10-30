<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Migrations\MigrationRegistry;
use App\Support\Migrations\MigrationStepTracker;
use PHPUnit\Framework\TestCase;

/** File active et archive historique distinctes, classement des sorties et conservation des sources. */
final class MigrationRegistryTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testActiveQueueIsEmptyAndHistoricalSourcesRemainArchived(): void
    {
        self::assertSame([], MigrationRegistry::load($this->root()));
        self::assertNull(MigrationRegistry::find($this->root(), 'atak_maps'));
        self::assertNull(MigrationRegistry::find($this->root(), 'sql_files'));
        $archive = require $this->root() . '/database/steps/registry-archive.php';
        self::assertGreaterThan(250, count($archive));
        $ids = array_column($archive, 'id');
        self::assertSame(count($ids), count(array_unique($ids)));
        foreach ($archive as $step) {
            self::assertFileExists($this->root() . '/database/steps/' . $step['file']);
        }
    }

    public function testArchivedSourcesRemainAvailableWithoutMakingThemActive(): void
    {
        $source = \Tests\Support\MigrationPipelineSource::read($this->root());
        self::assertStringContainsString('runPendingSqlFiles', $source);
        self::assertStringContainsString('ATHENA_FIRST_ADMIN_PASSWORD', $source);
        $active = (string) file_get_contents($this->root() . '/run-migrations.php');
        foreach (['runPendingSqlFiles', 'migrations_full_post.php', 'community_platform_migration.php', 'registry-archive.php'] as $retired) {
            self::assertStringNotContainsString($retired, $active);
        }
        self::assertFileExists($this->root() . '/bootstrap/training_onboarding_course_seed.php');
        self::assertSame([], MigrationRegistry::load($this->root()));
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
