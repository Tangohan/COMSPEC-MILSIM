<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\EnlistmentController;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Auth\AuthService;
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Profile\CandidateRpSnapshotService;
use App\Support\PendingEnlistmentReturn;
use PHPUnit\Framework\TestCase;

/**
 * « Un compte, une identité » : pas de candidature sans compte, et l'identité d'une candidature vient du compte
 * (fiche et profil), jamais d'un champ envoyé par le navigateur, requête forgée comprise. Données fictives.
 */
final class EnlistmentAccountRequiredTest extends TestCase
{
    private const UID = 41;

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $saved = [];
    /** @var list<array{0: int, 1: array<string, mixed>}> */
    private array $created = [];
    /** @var list<array{0: int, 1: array<string, mixed>}> */
    private array $profileUpserts = [];

    protected function setUp(): void
    {
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $started->getValue();
        $started->setValue(null, true);
        $this->saved = ['session' => $_SESSION ?? [], 'post' => $_POST, 'get' => $_GET, 'server' => $_SERVER];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/c/unite-demo/enlistment';
        $this->created = [];
        $this->profileUpserts = [];
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->saved['session'];
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
    }

    /**
     * @param array<string, mixed>|null $user compte connecté, ou null pour un visiteur
     * @param array<string, mixed>|null $profile
     */
    private function controller(?array $user, ?array $profile = null): EnlistmentController
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('check')->willReturn($user !== null);
        $auth->method('user')->willReturn($user);

        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findBySlug')->willReturn(['id' => 7, 'name' => 'Unité Démo', 'slug' => 'unite-demo', 'settings' => '{}', 'tenant_type' => 'full']);

        $enlistments = $this->createMock(EnlistmentRepository::class);
        $enlistments->method('findOngoingSubmittedForAccount')->willReturn(null);
        $enlistments->method('create')->willReturnCallback(function (int $tenantId, array $payload): int {
            $this->created[] = [$tenantId, $payload];

            return 99;
        });

        $profiles = $this->createMock(UserProfileRepository::class);
        $profiles->method('getByUserId')->willReturn($profile);
        $profiles->method('upsert')->willReturnCallback(function (int $uid, array $data): void {
            $this->profileUpserts[] = [$uid, $data];
        });

        $timeline = $this->createMock(EnlistmentTimelineRepository::class);
        $timeline->method('tableExists')->willReturn(false);
        $openings = $this->createMock(RecruitmentOpeningRepository::class);
        $openings->method('tablesExist')->willReturn(false);
        $blocklist = $this->createMock(IndicatorBlocklistService::class);
        $blocklist->method('isEmailBlockedForTenant')->willReturn(false);
        $users = $this->createMock(UserRepository::class);
        $users->method('listActiveUserIdsWithAnyPermissionSlug')->willReturn([]);

        return new EnlistmentController(
            $enlistments,
            $timeline,
            $tenants,
            $auth,
            $users,
            $profiles,
            $this->createMock(CandidateRpSnapshotService::class),
            $this->createMock(EmailService::class),
            $blocklist,
            $this->createMock(UserNotificationPreferencesRepository::class),
            $openings,
            $this->createMock(AnalyticsEventService::class),
            $this->createMock(PersonnelProfileRepository::class),
            $this->createMock(PersonnelAssignmentRepository::class),
            $this->createMock(PersonnelJobRoleRepository::class),
            $this->createMock(UnitRepository::class),
            $this->createMock(TenantBrandingRepository::class),
        );
    }

    /** @param array<string, mixed> $post */
    private function post(array $post): Request
    {
        $_POST = $post + ['_csrf_token' => str_repeat('a', 64)];

        return new Request();
    }

    public function testSubmissionWithoutAccountIsRefusedAndNothingIsStored(): void
    {
        $request = $this->post([
            'enlistment_flow' => 'guest',
            'guest_rp_first_name' => 'Faux',
            'guest_rp_last_name' => 'Candidat',
            'email' => 'faux@example.invalid',
            'consent_data_sharing' => '1',
            'no_ai_confirmed' => '1',
        ]);

        $response = $this->controller(null)->store($request, ['slug' => 'unite-demo']);

        self::assertSame(302, $response->statusCode());
        self::assertStringEndsWith('/login', (string) $response->headerValue('Location'));
        self::assertSame([], $this->created);
        self::assertSame([], $this->profileUpserts);
    }

    public function testRefusalKeepsTheCommunityToComeBackAfterLogin(): void
    {
        $request = $this->post(['enlistment_opening_id' => '3', 'invite_link' => 'lien-demo-01']);

        $this->controller(null)->store($request, ['slug' => 'unite-demo']);

        $back = PendingEnlistmentReturn::continueUrl();
        self::assertNotNull($back);
        self::assertStringEndsWith('/c/unite-demo/enlistment?ouverture=3&lien=lien-demo-01', $back);
    }

    public function testRefusalWithoutAccountEvenWithoutCsrfToken(): void
    {
        $_POST = ['email' => 'faux@example.invalid'];

        $response = $this->controller(null)->store(new Request(), ['slug' => 'unite-demo']);

        self::assertStringEndsWith('/login', (string) $response->headerValue('Location'));
        self::assertSame([], $this->created);
    }

    public function testIdentityComesFromTheAccountNotFromForgedFields(): void
    {
        $user = ['id' => self::UID, 'email' => 'camille@example.invalid', 'display_name' => 'Cam'];
        $profile = ['first_name' => 'Camille', 'last_name' => 'Exemple', 'birth_date' => '1990-04-12', 'nationality' => 'Française'];
        $request = $this->post([
            'first_name' => 'Pirate',
            'last_name' => 'Forgé',
            'email' => 'pirate@example.invalid',
            'callsign' => 'Spectre',
            'submitter_user_id' => '999',
            'submitted_via' => 'guest',
            'enlistment_flow' => 'guest',
            'guest_rp_first_name' => 'Autre',
            'guest_rp_last_name' => 'Personne',
            'full_name' => 'Encore Un Faux',
            'age' => '17',
            'shared_fields' => ['mask_email' => '1'],
            // Déjà connus du profil : ignorés même s'ils sont envoyés.
            'profile_first_name' => 'Pirate',
            'profile_last_name' => 'Forgé',
            'profile_birth_date' => '2010-01-01',
            'profile_nationality' => 'Ailleurs',
            'consent_data_sharing' => '1',
            'no_ai_confirmed' => '1',
            'motivation_why_join' => 'Texte de motivation fictif.',
        ]);

        $response = $this->controller($user, $profile)->store($request, ['slug' => 'unite-demo']);

        self::assertStringContainsString('/enlistment/success', (string) $response->headerValue('Location'));
        self::assertNotSame([], $this->created);
        [$tenantId, $payload] = $this->created[0];
        self::assertSame(7, $tenantId);
        self::assertSame('Camille', $payload['first_name']);
        self::assertSame('Exemple', $payload['last_name']);
        self::assertSame('camille@example.invalid', $payload['email']);
        self::assertSame(self::UID, $payload['submitter_user_id']);
        self::assertSame('account', $payload['submitted_via']);
        self::assertNull($payload['callsign']);
        self::assertSame('Française', $payload['country']);
        self::assertSame((new \DateTimeImmutable('1990-04-12'))->diff(new \DateTimeImmutable('now'))->y, $payload['age']);
        self::assertArrayNotHasKey('mask_email', $payload['shared_fields']);
        self::assertTrue($payload['shared_fields']['share_email']);
        self::assertSame([], $this->profileUpserts);
    }

    public function testOnlyMissingProfileFieldsAreTakenFromTheFormAndCompleteTheProfile(): void
    {
        $user = ['id' => self::UID, 'email' => 'camille@example.invalid', 'display_name' => 'Cam'];
        $profile = ['first_name' => 'Camille', 'last_name' => 'Exemple', 'birth_date' => '', 'nationality' => ''];
        $request = $this->post([
            'profile_birth_date' => '1988-09-30',
            'profile_nationality' => 'Belge',
            'profile_first_name' => 'Pirate',
            'consent_data_sharing' => '1',
            'no_ai_confirmed' => '1',
            'motivation_why_join' => 'Texte de motivation fictif.',
        ]);

        $this->controller($user, $profile)->store($request, ['slug' => 'unite-demo']);

        [, $payload] = $this->created[0];
        self::assertSame('Camille', $payload['first_name']);
        self::assertSame('Belge', $payload['country']);
        self::assertSame([[self::UID, ['birth_date' => '1988-09-30', 'nationality' => 'Belge']]], $this->profileUpserts);
    }

    public function testConsentIsRequiredForAnAccountSubmission(): void
    {
        $user = ['id' => self::UID, 'email' => 'camille@example.invalid', 'display_name' => 'Cam'];
        $profile = ['first_name' => 'Camille', 'last_name' => 'Exemple'];
        $request = $this->post(['no_ai_confirmed' => '1']);

        $response = $this->controller($user, $profile)->store($request, ['slug' => 'unite-demo']);

        self::assertStringEndsWith('/enlistment/error', (string) $response->headerValue('Location'));
        self::assertSame([], $this->created);
    }

    public function testAccountStepRemembersTheTargetThenGoesToLoginOrRegister(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['ouverture' => '5'];
        $response = $this->controller(null)->accountStep(new Request(), ['slug' => 'unite-demo']);
        self::assertStringEndsWith('/login', (string) $response->headerValue('Location'));
        self::assertStringEndsWith('/c/unite-demo/enlistment?ouverture=5', (string) PendingEnlistmentReturn::continueUrl());

        $_GET = ['creer' => '1'];
        $response = $this->controller(null)->accountStep(new Request(), ['slug' => 'unite-demo']);
        self::assertStringEndsWith('/register', (string) $response->headerValue('Location'));
    }

    public function testAccountStepForAConnectedAccountGoesStraightToTheForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $user = ['id' => self::UID, 'email' => 'camille@example.invalid', 'display_name' => 'Cam'];

        $response = $this->controller($user)->accountStep(new Request(), ['slug' => 'unite-demo']);

        self::assertStringEndsWith('/c/unite-demo/enlistment', (string) $response->headerValue('Location'));
    }

    public function testViewsNoLongerOfferGuestIdentityOrMaskedEmail(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['views/enlistment.php', 'views/enlistment/simple.php', 'views/enlistment/discord.php', 'views/enlistment/_account_identity.php', 'views/enlistment/_account_consent.php'] as $file) {
            $src = (string) file_get_contents($root . '/' . $file);
            self::assertStringNotContainsString('Invité (personnage)', $src, $file);
            self::assertStringNotContainsString('Compte Athena</button>', $src, $file);
            self::assertStringNotContainsString('name="mask_email"', $src, $file);
            self::assertStringNotContainsString('name="enlistment_flow"', $src, $file);
            self::assertStringNotContainsString('name="guest_rp_', $src, $file);
            self::assertStringNotContainsString('name="email"', $src, $file);
        }
        $form = (string) file_get_contents($root . '/views/enlistment.php');
        self::assertStringContainsString('_account_identity.php', $form);
        self::assertStringContainsString('_account_consent.php', $form);
    }
}
