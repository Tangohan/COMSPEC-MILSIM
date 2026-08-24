<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UserOnboardingPersistenceTest extends TestCase
{
    public function testSchemaAndIncrementalMigrationExposeOnboardingColumns(): void
    {
        $schema = file_get_contents(__DIR__ . '/../../migrations/schema.sql');
        $migration = file_get_contents(__DIR__ . '/../../migrations/20260824000001_user_onboarding_progress.sql');

        self::assertIsString($schema);
        self::assertIsString($migration);
        foreach (['onboarding_persona', 'onboarding_steps_json', 'onboarding_completed_at'] as $column) {
            self::assertStringContainsString('`' . $column . '`', $schema);
            self::assertStringContainsString('`' . $column . '`', $migration);
        }
    }

    public function testRepositoryAndControllerUseDurableStateWithSessionFallback(): void
    {
        $repository = file_get_contents(__DIR__ . '/../../app/Repositories/UserProfileRepository.php');
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Web/OnboardingController.php');

        self::assertIsString($repository);
        self::assertIsString($controller);
        self::assertStringContainsString('function getOnboardingState', $repository);
        self::assertStringContainsString('function saveOnboardingState', $repository);
        self::assertStringContainsString("Session::set('onboarding_steps_'", $controller);
        self::assertStringContainsString('$this->userProfileRepository->saveOnboardingState', $controller);
    }
}
