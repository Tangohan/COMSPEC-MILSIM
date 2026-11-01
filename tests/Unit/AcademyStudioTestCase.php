<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AcademyRepository;
use App\Repositories\AcademyCourseShareRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Repositories\TenantRepository;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyStudio;
use App\Services\Academy\GoogleSlidesImporter;
use App\Services\Platform\FeatureGateService;
use PDO;
use PHPUnit\Framework\TestCase;

/** Base des tests du Studio : base SQLite jetable, import Google Slides simulé, membre « encadrement » connecté. */
abstract class AcademyStudioTestCase extends TestCase
{
    protected const DECK = '1EAYk18WDjIG-zp_0vLm3CsfQh_i8eXc67Jo2O9C6Vuc';

    protected PDO $pdo;
    protected AuditService $audit;
    /** @var list<string> */
    protected array $audited = [];
    protected string $storage;
    protected array $instances;
    protected ?PDO $previousPdo;
    /** @var list<string> */
    protected array $fetched = [];
    protected array $nextResponse = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Schéma après les étapes academy_tables (3130) et academy_course_lifecycle_sharing (3270).
        foreach ([
            'CREATE TABLE academy_courses (tenant_id INTEGER NOT NULL, course_key TEXT NOT NULL, content_json TEXT NOT NULL, published INTEGER NOT NULL DEFAULT 0, updated_by INTEGER NOT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, archived_at TEXT NULL, deleted_at TEXT NULL, PRIMARY KEY (tenant_id, course_key))',
            'CREATE TABLE academy_learners (tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL, course_key TEXT NOT NULL, state_json TEXT NOT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (tenant_id, user_id, course_key))',
            'CREATE TABLE academy_course_shares (tenant_id INTEGER NOT NULL, course_key TEXT NOT NULL, user_id INTEGER NOT NULL, role TEXT NOT NULL, granted_by INTEGER NULL, created_at TEXT NOT NULL, PRIMARY KEY (tenant_id, course_key, user_id))',
            'CREATE TABLE academy_course_links (tenant_id INTEGER NOT NULL, course_key TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, token_cipher TEXT NULL, access TEXT NOT NULL, role TEXT NOT NULL, expires_at TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL, PRIMARY KEY (tenant_id, course_key))',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $pdoProperty = new \ReflectionProperty(Database::class, 'pdo');
        $this->previousPdo = $pdoProperty->getValue();
        $pdoProperty->setValue(null, $this->pdo);
        $this->storage = sys_get_temp_dir() . '/academy-studio-test-' . bin2hex(random_bytes(4));
        $container = new \ReflectionProperty(Container::class, 'instances');
        $this->instances = $container->getValue();
        $feature = $this->createMock(FeatureGateService::class);
        $feature->method('allows')->willReturn(true);
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(['id' => 10, 'slug' => 'team-a', 'name' => 'Team A']);
        $this->nextResponse = $this->pdfResponse('%PDF-1.4 /Type /Pages /Count 3 /Type /Page /Type /Page /Type/Page %%EOF');
        $importer = new GoogleSlidesImporter(function (string $url): array {
            $this->fetched[] = $url;
            return $this->nextResponse;
        }, $this->storage);
        $users = $this->createMock(UserRepository::class);
        $users->method('listForTenant')->willReturn([['id' => 8, 'callsign' => 'Viper'], ['id' => 9, 'callsign' => 'Ghost']]);
        $users->method('isMemberOfTenant')->willReturnCallback(static fn (int $user, int $tenant): bool => $tenant === 10 && in_array($user, [7, 8, 9], true));
        $this->audit = $this->createMock(AuditService::class);
        $this->audit->method('logChange')->willReturnCallback(function (string $action): void {
            $this->audited[] = $action;
        });
        $container->setValue(null, [FeatureGateService::class => $feature, TenantRepository::class => $tenants, GoogleSlidesImporter::class => $importer,
            UserRepository::class => $users, AuditService::class => $this->audit, AcademyCourseShareRepository::class => new AcademyCourseShareRepository($this->pdo)]);
        Gate::reset();
        Gate::getInstance()->setPermissions(['training.create']);
        \App\Core\Session::start();
        $_SESSION = ['user_id' => 7, 'tenant_id' => 10, '_csrf_token' => str_repeat('a', 64)];
        $_GET = $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/academy/action/studio_new';
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->instances);
        Gate::reset();
        foreach (glob($this->storage . '/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->storage . '/*') ?: [] as $dir) {
            rmdir($dir);
        }
        if (is_dir($this->storage)) {
            rmdir($this->storage);
        }
        $_GET = $_POST = [];
        unset($GLOBALS['__app_locale']);
    }

    protected function pdfResponse(string $body, string $title = 'Briefing%20radio.pdf'): array
    {
        return ['status' => 200, 'type' => 'application/pdf', 'body' => $body, 'disposition' => 'attachment; filename="x.pdf"; filename*=UTF-8\'\'' . $title, 'host' => 'doc-04-bs-slides.googleusercontent.com'];
    }

    protected function post(string $action, array $input): Response
    {
        $_POST = $input + ['_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/academy/action/' . $action;
        return (new AcademyController())->action(new Request(), ['action' => $action]);
    }

    protected function newDraft(): string
    {
        $response = $this->post('studio_new', ['course' => 'academy']);
        self::assertSame(303, $response->statusCode());
        self::assertMatchesRegularExpression('#/academy/studio\?course=(course-[a-f0-9]{16})$#', (string) $response->headerValue('Location'));
        preg_match('#course=(course-[a-f0-9]{16})#', (string) $response->headerValue('Location'), $m);
        return $m[1];
    }

    protected function draft(string $key): array
    {
        return (new AcademyRepository($this->pdo))->editable(10, $key) ?? [];
    }

    /** Formulaire tel que le Studio l'envoie : le brouillon actuel, champ par champ. */
    protected function form(array $course): array
    {
        return array_intersect_key($course, array_flip(['title', 'summary', 'cover', 'license', 'version', 'duration_minutes', 'chapters']));
    }

}
