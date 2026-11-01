<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AccountController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Auth\LoginSecurityOtpService;
use App\Services\Community\LeaveCommunityService;
use App\Services\Email\EmailEvents;
use App\Services\Profile\UserUiPreferencesValidationService;
use App\Services\Qr\QrPngGenerator;
use App\Services\Steam\SteamWebApiService;
use App\Support\UserPublicName;
use PHPUnit\Framework\TestCase;

/**
 * La refonte de « Profil et préférences » ne perd aucun réglage : chaque champ envoyé par le formulaire
 * arrive bien jusqu'à son enregistrement.
 */
final class AccountPreferencesSaveTest extends TestCase
{
    private const UID = 7;
    private const TID = 3;

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $saved = [];
    private ?\PDO $savedPdo = null;
    private ?bool $savedPublicNameReady = null;
    /** @var array<string, list<mixed>> */
    private array $calls = [];

    protected function setUp(): void
    {
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $started->getValue();
        $started->setValue(null, true);
        $this->saved = ['session' => $_SESSION ?? [], 'post' => $_POST, 'get' => $_GET, 'server' => $_SERVER, 'cookie' => $_COOKIE];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => self::UID, 'tenant_id' => self::TID];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/account/preferences';
        $GLOBALS['__app_locale'] = 'fr';

        // Pseudo public : enregistré par une requête directe, ici captée sans base de données.
        $pdoProp = new \ReflectionProperty(Database::class, 'pdo');
        $this->savedPdo = $pdoProp->getValue();
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql): \PDOStatement {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->method('execute')->willReturnCallback(function (?array $params = null) use ($sql): bool {
                $this->calls['sql'][] = [$sql, $params];

                return true;
            });

            return $stmt;
        });
        $pdoProp->setValue(null, $pdo);
        $ready = new \ReflectionProperty(UserPublicName::class, 'ready');
        $this->savedPublicNameReady = $ready->getValue();
        $ready->setValue(null, true);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(UserPublicName::class, 'ready'))->setValue(null, $this->savedPublicNameReady);
        $_SESSION = $this->saved['session'];
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        $_COOKIE = $this->saved['cookie'];
        unset($GLOBALS['__app_locale']);
    }

    private function record(string $what): \Closure
    {
        return function (mixed ...$args) use ($what) {
            $this->calls[$what][] = $args;

            return null;
        };
    }

    private function controller(): AccountController
    {
        $user = ['id' => self::UID, 'tenant_id' => self::TID, 'email' => 'jean@example.com', 'callsign' => 'Old', 'steam_id' => null, 'profile_slug' => null];

        $auth = $this->createMock(AuthService::class);
        $auth->method('user')->willReturn($user);

        $users = $this->createMock(UserRepository::class);
        $users->method('isProfileSlugTaken')->willReturn(false);
        $users->method('update')->willReturnCallback(function (int $uid, int $tid, array $data): bool {
            $this->calls['users.update'][] = [$uid, $tid, $data];

            return true;
        });

        $profiles = $this->createMock(UserProfileRepository::class);
        $profiles->method('getByUserId')->willReturn(['first_name' => 'Old', 'last_name' => 'Name', 'language' => 'fr', 'timezone' => 'Europe/Paris']);
        $profiles->method('upsert')->willReturnCallback($this->record('profiles.upsert'));

        $legal = $this->createMock(UserLegalIdentityRepository::class);
        $legal->method('getByUserId')->willReturn(null);

        $personnel = $this->createMock(PersonnelProfileRepository::class);
        $personnel->method('getByUserId')->willReturn([]);
        $personnel->method('ensureRecord')->willReturnCallback($this->record('personnel.ensure'));
        $personnel->method('update')->willReturnCallback(function (int $uid, array $data): bool {
            $this->calls['personnel.update'][] = [$uid, $data];

            return true;
        });

        $ui = $this->createMock(UserUiPreferencesRepository::class);
        $ui->method('getOrDefaults')->willReturn(['theme' => 'system', 'density' => 'comfortable', 'sidebar_collapsed' => true]);
        $ui->method('upsert')->willReturnCallback($this->record('ui.upsert'));

        $notif = $this->createMock(UserNotificationPreferencesRepository::class);
        $notif->method('listForUser')->willReturn([]);
        $notif->method('setEnabled')->willReturnCallback($this->record('notif.set'));

        $display = $this->createMock(UserProfileDisplaySettingsRepository::class);
        $display->method('getOrDefaults')->willReturn(['site_photo_priority' => 'operator']);
        $display->method('upsert')->willReturnCallback($this->record('display.upsert'));

        $steam = $this->createMock(SteamWebApiService::class);
        $steam->method('isConfigured')->willReturn(false);
        $steam->method('resolveSteamIdFromUserInput')->willReturnCallback(
            static fn (string $raw): ?string => $raw === 'STEAM_0:1:19867136' ? '76561198000000273' : null
        );

        return new AccountController(
            $auth,
            $users,
            $legal,
            $profiles,
            $personnel,
            $ui,
            $notif,
            new UserUiPreferencesValidationService(),
            $display,
            $steam,
            $this->createMock(LoginSecurityOtpService::class),
            $this->createMock(LeaveCommunityService::class),
            $this->createMock(AuditService::class),
            $this->createMock(QrPngGenerator::class),
        );
    }

    /** @param array<string, mixed> $post */
    private function submit(array $post): \App\Core\Response
    {
        $_POST = $post + ['_csrf_token' => str_repeat('a', 64)];
        $controller = $this->controller();
        // La langue choisie pose aussi un cookie : sans en-têtes HTTP en ligne de commande, PHP le signale.
        set_error_handler(static function (int $no, string $msg): bool {
            return str_contains($msg, 'Cannot modify header information');
        }, E_WARNING);
        try {
            return $controller->preferences(new Request());
        } finally {
            restore_error_handler();
        }
    }

    public function testEveryPreferenceFieldReachesItsStorage(): void
    {
        $response = $this->submit([
            'first_name' => '  Jean ',
            'last_name' => 'Dupont',
            'public_name' => 'Ghosty',
            'callsign' => 'Ghost',
            'extra_callsigns' => ['Ghost-2', '', 'Spectre', '', ''],
            'profile_slug' => 'Jean-Dupont',
            'steam_id' => 'STEAM_0:1:19867136',
            'timezone' => 'Europe/Brussels',
            'language' => 'en',
            'ui_theme' => 'dark',
            'ui_density' => 'compact',
            'notif_email' => [EmailEvents::NEW_DEVICE_LOGIN => '1', EmailEvents::ATTENDANCE_REMINDER => '1'],
        ]);

        self::assertSame(302, $response->statusCode());
        self::assertStringContainsString('account/preferences', (string) $response->headerValue('Location'));
        self::assertSame('Préférences enregistrées.', $_SESSION['_flash']['success'] ?? Session::getFlash('success'));

        // Compte : nom affiché, indicatif, Steam (numéro reconnu), adresse courte (en minuscules).
        [$uid, $tid, $userData] = $this->calls['users.update'][0];
        self::assertSame([self::UID, self::TID], [$uid, $tid]);
        self::assertSame('Jean Dupont', $userData['display_name']);
        self::assertSame('Ghost', $userData['callsign']);
        self::assertSame('76561198000000273', $userData['steam_id']);
        self::assertSame('jean-dupont', $userData['profile_slug']);

        // Profil : prénom, nom, fuseau, langue.
        [, $profileData] = $this->calls['profiles.upsert'][0];
        self::assertSame(['first_name' => 'Jean', 'last_name' => 'Dupont', 'timezone' => 'Europe/Brussels', 'language' => 'en'], $profileData);

        // Pseudo public.
        $publicNameWrites = array_values(array_filter(
            $this->calls['sql'] ?? [],
            static fn (array $c): bool => str_contains($c[0], 'SET public_name')
        ));
        self::assertSame(['Ghosty', self::UID], $publicNameWrites[0][1] ?? null);

        // Fiche : indicatif et alias radio.
        [, $personnelData] = $this->calls['personnel.update'][0];
        self::assertSame('Ghost', $personnelData['callsign']);
        self::assertSame(['Ghost-2', 'Spectre'], json_decode($personnelData['extra_callsigns_json'], true));
        self::assertSame('Jean Dupont', $personnelData['character_name']);

        // Affichage : thème et densité ; le réglage retiré du formulaire garde sa valeur.
        [, , $uiData] = $this->calls['ui.upsert'][0];
        self::assertSame('dark', $uiData['theme']);
        self::assertSame('compact', $uiData['density']);
        self::assertTrue($uiData['sidebar_collapsed']);
        self::assertSame('dark', Session::getFlash('ui_theme_sync'));

        // Photo affichée : toujours le portrait opérateur.
        self::assertSame(['site_photo_priority' => 'operator'], $this->calls['display.upsert'][0][1]);

        // Notifications : chaque type du catalogue est enregistré, coché = activé.
        $notif = [];
        foreach ($this->calls['notif.set'] as [$nUid, $nTid, $channel, $key, $enabled]) {
            self::assertSame([self::UID, self::TID, 'email'], [$nUid, $nTid, $channel]);
            $notif[$key] = $enabled;
        }
        self::assertGreaterThan(20, count($notif));
        self::assertTrue($notif[EmailEvents::NEW_DEVICE_LOGIN]);
        self::assertTrue($notif[EmailEvents::ATTENDANCE_REMINDER]);
        self::assertFalse($notif[EmailEvents::MULTIPLE_LOGIN_ATTEMPTS]);
        self::assertSame(2, count(array_filter($notif)));
    }

    public function testEmptySteamFieldUnlinksAndMissingNameKeepsTypedValues(): void
    {
        $this->submit([
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'steam_id' => '', 'profile_slug' => '',
            'language' => 'xx', 'ui_theme' => 'light', 'ui_density' => 'comfortable',
        ]);
        [, , $userData] = $this->calls['users.update'][0];
        self::assertNull($userData['steam_id']);
        self::assertNull($userData['profile_slug']);
        self::assertSame('fr', $this->calls['profiles.upsert'][0][1]['language'], 'Langue inconnue : retour au français.');

        $this->calls = [];
        $response = $this->submit(['first_name' => '', 'last_name' => 'Martin', 'callsign' => 'Viper']);
        self::assertSame(200, $response->statusCode());
        self::assertArrayNotHasKey('users.update', $this->calls, 'Rien n’est enregistré quand un champ obligatoire manque.');
        self::assertArrayNotHasKey('notif.set', $this->calls);
    }
}
