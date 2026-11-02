<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Services\Academy\AcademyStudioCredentials;

/** Studio : pages et actions des qualifications et brevets (droits de création de cours, liens avec les cours). */
final class AcademyStudioCredentialsControllerTest extends AcademyStudioTestCase
{
    /** @var list<array<int, mixed>> */
    private array $calls = [];

    private function withCredentials(array $list = []): void
    {
        $creds = $this->createMock(AcademyStudioCredentials::class);
        $creds->method('ready')->willReturn(true);
        $creds->method('rulesReady')->willReturn(true);
        $creds->method('list')->willReturn($list);
        $creds->method('forCourse')->willReturn([]);
        $creds->method('linkCourse')->willReturnCallback(function (int $tenant, int $id, string $key): void {
            $this->calls[] = ['link', $tenant, $id, $key];
        });
        $creds->method('save')->willReturnCallback(function (int $tenant, ?int $id, string $kind, array $in): int {
            $this->calls[] = ['save', $tenant, $id, $kind, $in['name'] ?? null, $in['conditions']['courses'] ?? []];
            return 42;
        });
        $container = new \ReflectionProperty(Container::class, 'instances');
        $container->setValue(null, $container->getValue() + [AcademyStudioCredentials::class => $creds]);
    }

    private function get(array $query): \App\Core\Response
    {
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        return (new AcademyController())->space(new Request(), ['space' => 'studio']);
    }

    public function testListAndCreationPagesUseCourseCreationRights(): void
    {
        $this->withCredentials([['id' => 1, 'code' => 'BRV-1', 'name' => 'Brevet de chef de bord', 'short' => '', 'description' => '', 'category' => '', 'kind' => 'brevet',
            'origin' => 'community', 'badge' => '', 'holders' => 0, 'archived' => false, 'permanent' => true, 'months' => 0, 'courses' => [], 'updated_at' => '']]);
        $list = $this->get(['vue' => 'brevets']);
        self::assertSame(200, $list->statusCode());
        self::assertStringContainsString('Brevet de chef de bord', $list->body());
        self::assertStringContainsString('?nouveau=brevet', $list->body());
        $home = $this->get([]);
        self::assertStringContainsString('?vue=qualifications', $home->body());
        self::assertStringContainsString('data-open-dialog="sth-pick"', $home->body());

        Gate::reset();
        self::assertSame(403, $this->get(['nouveau' => 'brevet'])->statusCode());
        self::assertSame(403, $this->post('studio_cred_save', ['course' => 'academy', 'cred_kind' => 'brevet', 'name' => 'X'])->statusCode());
        self::assertSame([], $this->calls);
    }

    public function testSavingAndLinkingFromACourse(): void
    {
        $this->withCredentials();
        $key = $this->newDraft();
        $saved = $this->post('studio_cred_save', ['course' => 'academy', 'cred_kind' => 'brevet', 'name' => 'Brevet de navigateur',
            'conditions' => ['present' => '1', 'courses' => [$key]]]);
        self::assertSame(303, $saved->statusCode());
        self::assertStringEndsWith('/academy/studio?fiche=42', (string) $saved->headerValue('Location'));
        self::assertSame(['save', 10, null, 'brevet', 'Brevet de navigateur', [$key]], $this->calls[0]);

        $linked = $this->post('studio_cred_link', ['course' => $key, 'return' => 'course', 'cred_id' => '42']);
        self::assertSame(303, $linked->statusCode());
        self::assertStringEndsWith('/academy/studio?course=' . $key, (string) $linked->headerValue('Location'));
        self::assertSame(['link', 10, 42, $key], $this->calls[1]);
        self::assertContains('academy.credential.course_linked', $this->audited);

        // Cours inconnu de la communauté : rien n'est rattaché.
        $this->post('studio_cred_link', ['course' => 'academy', 'cred_id' => '42', 'link_course' => 'course-0000000000000000']);
        self::assertCount(2, $this->calls);
    }
}
