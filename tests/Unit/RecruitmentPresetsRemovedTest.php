<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AccountController;
use App\Controllers\Web\EnlistmentController;
use App\Core\Request;
use App\Core\Router;
use App\Services\Account\AccountDeletionService;
use App\Services\Profile\CandidateRpSnapshotService;
use PHPUnit\Framework\TestCase;

/**
 * Les « profils de candidature » (préréglages d'enrôlement enregistrés sur le compte) sont retirés :
 * plus de route, plus d'écran, plus de choix dans le formulaire de candidature.
 */
final class RecruitmentPresetsRemovedTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<array{method: string, path: string, pattern: string}> */
    private function registeredRoutes(): array
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        try {
            $router = new Router(new Request());
            $register = require self::ROOT . '/routes/web.php';
            $register($router);
        } finally {
            $_SERVER = $server;
        }
        $prop = new \ReflectionProperty(Router::class, 'routes');

        return $prop->getValue($router);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function oldAddresses(): array
    {
        return [
            ['GET', '/account/recruitment-presets'],
            ['GET', '/account/recruitment-presets/create'],
            ['POST', '/account/recruitment-presets/create'],
            ['GET', '/account/recruitment-presets/12/edit'],
            ['POST', '/account/recruitment-presets/12/edit'],
            ['POST', '/account/recruitment-presets/12/delete'],
        ];
    }

    public function testOldAddressesNoLongerMatchAnyRouteSoTheRouterAnswers404(): void
    {
        $routes = $this->registeredRoutes();
        self::assertGreaterThan(100, count($routes), 'Les routes du site doivent être chargées.');

        foreach (self::oldAddresses() as [$method, $path]) {
            foreach ($routes as $route) {
                if ($route['method'] !== $method) {
                    continue;
                }
                self::assertSame(
                    0,
                    preg_match($route['pattern'], $path),
                    $method . ' ' . $path . ' est encore servi par ' . $route['path']
                );
            }
        }

        // Les pages voisines du compte restent servies.
        $paths = array_column($routes, 'path');
        self::assertContains('/account', $paths);
        self::assertContains('/account/preferences', $paths);
    }

    public function testCodeScreensAndMenuEntryAreGone(): void
    {
        self::assertFalse(class_exists('App\\Repositories\\RecruitmentPresetRepository'));
        self::assertFalse(class_exists('App\\Services\\Profile\\RecruitmentPresetPayloadService'));
        self::assertFileDoesNotExist(self::ROOT . '/views/account/recruitment_presets.php');
        self::assertFileDoesNotExist(self::ROOT . '/views/account/recruitment_presets_form.php');

        foreach (['recruitmentPresets', 'recruitmentPresetsCreate', 'recruitmentPresetsEdit', 'recruitmentPresetsDelete'] as $method) {
            self::assertFalse(method_exists(AccountController::class, $method), $method);
        }
        foreach ([AccountController::class, EnlistmentController::class, AccountDeletionService::class] as $class) {
            foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $param) {
                self::assertStringNotContainsString('RecruitmentPreset', (string) $param->getType(), $class);
            }
        }

        foreach ([
            'views/partials/account/shell_open.php',
            'views/enlistment.php',
            'views/enlistment/simple.php',
            'app/Support/AccountSpaceCatalog.php',
        ] as $file) {
            $src = (string) file_get_contents(self::ROOT . '/' . $file);
            self::assertStringNotContainsString('recruitment-presets', $src, $file);
            self::assertStringNotContainsString('recruitment_preset_id', $src, $file);
            self::assertStringNotContainsString('Profils de candidature', $src, $file);
        }
    }

    public function testAccountDeletionStillErasesLegacyRows(): void
    {
        $src = (string) file_get_contents(self::ROOT . '/app/Services/Account/AccountDeletionService.php');
        self::assertStringContainsString("'recruitment_presets'", $src);
    }

    public function testGuestCandidateSnapshotKeepsItsShape(): void
    {
        $svc = new CandidateRpSnapshotService();
        $snap = $svc->buildRpSnapshotForEnlistment([
            'rp' => ['first_name' => 'Jake', 'last_name' => 'Miller', 'birth_date' => '1990-02-30', 'nationality' => 'Française', 'character_name' => ''],
            'admin_notes' => '',
            'availability' => ['schedule' => [['dow' => 3, 'start' => '20:00', 'end' => '23:30']], 'timezone_label' => '', 'free_text' => ''],
        ]);

        self::assertSame('Jake', $snap['rp_first_name']);
        self::assertSame('Miller', $snap['rp_last_name']);
        self::assertSame('', $snap['rp_birth_date'], 'Une date impossible est écartée.');
        self::assertSame('Jake Miller', $snap['character_name']);
        self::assertSame(CandidateRpSnapshotService::PAYLOAD_VERSION, $snap['payload_version']);
        self::assertSame('Mer 20:00–23:30', $snap['derived_availability']['weekly_availability']);
        self::assertTrue(CandidateRpSnapshotService::personnelRpDossierNeedsAttention([]));
        self::assertFalse(CandidateRpSnapshotService::personnelRpDossierNeedsAttention(['character_name' => 'Jake']));
    }
}
