<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\AcademyCourseShareRepository;
use App\Repositories\AcademyRepository;
use App\Services\Academy\AcademyCurriculum;

/** Studio : archivage, corbeille, partage par personne et par lien (droits vérifiés côté serveur). */
final class AcademyCourseLifecycleSharingTest extends AcademyStudioTestCase
{
    private function publishedCourse(): string
    {
        $key = $this->newDraft();
        $demo = AcademyCurriculum::demo();
        $form = $this->form($demo);
        $form['chapters'] = array_map(static fn (array $chapter): array => $chapter + ['resources' => []], $demo['chapters']);
        $this->post('studio_publish', ['course' => $key, 'draft' => $form]);
        self::assertTrue($this->draft($key)['published']);
        return $key;
    }

    private function enrol(string $key, int $userId = 20): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (10, ?, ?, ?)');
        $stmt->execute([$userId, $key, json_encode(['chapter' => 0, 'stage' => 0, 'snapshot' => ['title' => ['fr' => 'Copie', 'en' => 'Copy']]])]);
    }

    private function asMember(int $userId = 8): void
    {
        Gate::reset();
        $_SESSION['user_id'] = $userId;
    }

    private function studio(array $query): \App\Core\Response
    {
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        return (new AcademyController())->space(new Request(), ['space' => 'studio']);
    }

    private function catalogueKeys(): array
    {
        return array_column((new AcademyRepository($this->pdo))->courses(10), 'key');
    }

    public function testArchiveRemovesFromCatalogueAndStudioWhileLearnersKeepTheirVersion(): void
    {
        $key = $this->publishedCourse();
        $this->enrol($key);
        self::assertContains($key, $this->catalogueKeys());
        $this->post('studio_archive', ['course' => $key]);
        self::assertSame('studio_archived', $_SESSION['academy_notice']);
        self::assertNotContains($key, $this->catalogueKeys());
        self::assertTrue($this->draft($key)['archived']);
        self::assertArrayHasKey($key, (new AcademyRepository($this->pdo))->states(10, 20), 'Le stagiaire garde sa copie et sa progression.');
        $body = $this->studio([])->body();
        self::assertStringNotContainsString('studio?course=' . $key . '"', $body, 'Absente du Studio actif');
        self::assertStringContainsString('studio_unarchive', $this->studio(['vue' => 'archives'])->body());
        // Une formation archivée ne se modifie plus tant qu'elle n'est pas restaurée.
        self::assertSame(409, $this->post('studio_save', ['course' => $key, 'draft' => []])->statusCode());
        $this->post('studio_unarchive', ['course' => $key]);
        self::assertContains($key, $this->catalogueKeys());
        self::assertContains('academy.course.archived', $this->audited);
        self::assertContains('academy.course.unarchived', $this->audited);
    }

    public function testDeleteNeedsTheTypedNameIsRefusedWithLearnersAndGoesToARestorableTrash(): void
    {
        $key = $this->publishedCourse();
        $title = AcademyCurriculum::demo()['title']['fr'];
        $this->enrol($key);
        $this->post('studio_delete', ['course' => $key, 'confirm_title' => $title]);
        self::assertSame('studio_delete_has_learners', $_SESSION['academy_notice']);
        self::assertNotSame([], $this->draft($key));

        $draft = $this->newDraft();
        $form = $this->form($this->draft($draft));
        $form['title'] = ['fr' => 'Brouillon jetable', 'en' => ''];
        $this->post('studio_save', ['course' => $draft, 'draft' => $form]);
        $this->post('studio_delete', ['course' => $draft, 'confirm_title' => 'Autre nom']);
        self::assertSame('studio_delete_confirm', $_SESSION['academy_notice']);
        self::assertNotSame([], $this->draft($draft));
        $this->post('studio_delete', ['course' => $draft, 'confirm_title' => 'Brouillon jetable']);
        self::assertSame('studio_deleted', $_SESSION['academy_notice']);
        self::assertSame([], $this->draft($draft), 'À la corbeille : plus modifiable');
        self::assertStringContainsString('Brouillon jetable', $this->studio(['vue' => 'corbeille'])->body());
        $this->post('studio_trash_restore', ['course' => $draft]);
        self::assertSame('Brouillon jetable', $this->draft($draft)['title']['fr']);

        // Au-delà de 30 jours, la corbeille est vidée (partages compris).
        $this->post('studio_delete', ['course' => $draft, 'confirm_title' => 'Brouillon jetable']);
        $repository = new AcademyRepository($this->pdo);
        self::assertSame([], $repository->purgeTrash(10));
        self::assertSame([$draft], $repository->purgeTrash(10, time() + 31 * 86400));
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM academy_courses WHERE course_key = '" . $draft . "'")->fetchColumn());
        self::assertContains('academy.course.trashed', $this->audited);
    }

    public function testSharingWithAMemberGivesEditorOrViewerRightsButNeverPublishing(): void
    {
        $key = $this->newDraft();
        $this->post('studio_share_add', ['course' => $key, 'share_user' => '99', 'share_role' => 'editor']);
        self::assertSame('share_invalid_member', $_SESSION['academy_notice'], 'Hors communauté : refusé');
        $this->post('studio_share_add', ['course' => $key, 'share_user' => '8', 'share_role' => 'editor']);
        self::assertSame('share_added', $_SESSION['academy_notice']);
        self::assertContains('academy.course.shared', $this->audited);
        $body = $this->studio(['course' => $key, 'share' => '1'])->body();
        self::assertStringContainsString('data-autoshow', $body);
        self::assertStringContainsString('Viper', $body);

        $this->asMember(8);
        $list = $this->studio([]);
        self::assertSame(200, $list->statusCode());
        self::assertStringContainsString('studio?course=' . $key, $list->body());
        self::assertStringNotContainsString('data-open-dialog="sth-new"', $list->body(), 'Un éditeur invité ne crée pas de cours');
        $editor = $this->studio(['course' => $key]);
        self::assertSame(200, $editor->statusCode());
        self::assertStringNotContainsString('formaction="/academy/action/studio_publish"', $editor->body());
        $form = $this->form($this->draft($key));
        $form['title'] = ['fr' => 'Modifié par Viper', 'en' => ''];
        self::assertSame(303, $this->post('studio_save', ['course' => $key, 'draft' => $form])->statusCode());
        self::assertSame('Modifié par Viper', $this->draft($key)['title']['fr']);
        foreach (['studio_publish', 'studio_archive', 'studio_delete', 'studio_share_add', 'studio_link_save', 'studio_revise', 'studio_new'] as $action) {
            self::assertSame(403, $this->post($action, ['course' => $key])->statusCode(), $action);
        }
        // Un autre membre, sans partage, n'a aucun accès.
        $this->asMember(9);
        self::assertSame(403, $this->studio(['course' => $key])->statusCode());
        self::assertSame(403, $this->post('studio_save', ['course' => $key, 'draft' => $form])->statusCode());

        // Lecteur : redirigé vers l'aperçu stagiaire, jamais dans l'éditeur.
        (new AcademyCourseShareRepository($this->pdo))->grant(10, $key, 9, 'viewer', 7);
        $viewer = $this->studio(['course' => $key]);
        self::assertSame(303, $viewer->statusCode());
        self::assertStringContainsString('classroom?course=' . $key . '&preview=1', (string) $viewer->headerValue('Location'));
        self::assertSame(403, $this->post('studio_save', ['course' => $key, 'draft' => $form])->statusCode());

        // Retrait de l'accès.
        Gate::getInstance()->setPermissions(['training.create']);
        $_SESSION['user_id'] = 7;
        $this->post('studio_share_remove', ['course' => $key, 'share_user' => '8']);
        $this->asMember(8);
        self::assertSame(403, $this->studio(['course' => $key])->statusCode());
    }

    public function testLinkAccessCommunityPublicRegenerationAndExpiry(): void
    {
        $key = $this->publishedCourse();
        $shares = new AcademyCourseShareRepository($this->pdo);
        $this->post('studio_link_save', ['course' => $key, 'link_access' => 'public', 'link_role' => 'editor', 'link_expiry' => 'never']);
        self::assertSame('viewer', $shares->link(10, $key)['role'], 'Un lien public reste en lecture seule');
        $token = (string) $shares->link(10, $key)['token'];
        self::assertSame(64, strlen($shares->link(10, $key)['token_hash']));
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM academy_course_links WHERE token_hash = '" . $token . "'")->fetchColumn(), 'Jeton jamais stocké en clair');

        // Public : lecture seule sans compte, sans questions ni réponses.
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $public = (new AcademyController())->shared(new Request(), ['token' => $token]);
        self::assertSame(200, $public->statusCode());
        self::assertStringContainsString(AcademyCurriculum::demo()['chapters'][0]['theory']['fr'], html_entity_decode($public->body(), ENT_QUOTES));
        self::assertStringNotContainsString(AcademyCurriculum::demo()['chapters'][0]['question']['fr'], html_entity_decode($public->body(), ENT_QUOTES));
        self::assertSame('noindex, nofollow', $public->headerValue('X-Robots-Tag'));

        // Nouveau lien : l'ancien cesse de fonctionner.
        $_SESSION = ['user_id' => 7, 'tenant_id' => 10, '_csrf_token' => str_repeat('a', 64)];
        Gate::getInstance()->setPermissions(['training.create']);
        $this->post('studio_link_regenerate', ['course' => $key, 'link_access' => 'community', 'link_role' => 'editor', 'link_expiry' => 'keep']);
        self::assertNull($shares->resolve($token));
        self::assertSame(404, (new AcademyController())->shared(new Request(), ['token' => $token])->statusCode());
        $community = (string) $shares->link(10, $key)['token'];
        self::assertContains('academy.course.link_regenerated', $this->audited);

        // Communauté : connexion exigée, puis appartenance à la communauté du lien.
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $login = (new AcademyController())->shared(new Request(), ['token' => $community]);
        self::assertStringContainsString('login?redirect=', (string) $login->headerValue('Location'));
        $_SESSION = ['user_id' => 9, 'tenant_id' => 11, '_csrf_token' => str_repeat('a', 64)];
        self::assertSame(403, (new AcademyController())->shared(new Request(), ['token' => $community])->statusCode());

        // Brouillon partagé par lien « éditeur » à la communauté : un membre sans rôle LMS peut l'éditer, pas le publier.
        $draft = $this->newDraftAsStaff();
        $this->post('studio_link_save', ['course' => $draft, 'link_access' => 'community', 'link_role' => 'editor', 'link_expiry' => '7']);
        $draftToken = (string) $shares->link(10, $draft)['token'];
        $this->asMember(9);
        $_SESSION['tenant_id'] = 10;
        $opened = (new AcademyController())->shared(new Request(), ['token' => $draftToken]);
        self::assertStringContainsString('studio?course=' . $draft, (string) $opened->headerValue('Location'));
        self::assertSame(303, $this->post('studio_save', ['course' => $draft, 'draft' => $this->form($this->draft($draft))])->statusCode());
        self::assertSame(403, $this->post('studio_publish', ['course' => $draft])->statusCode());

        // Lien désactivé : la session ne garde aucun droit.
        Gate::getInstance()->setPermissions(['training.create']);
        $_SESSION['user_id'] = 7;
        $this->post('studio_link_save', ['course' => $draft, 'link_access' => 'off']);
        $this->asMember(9);
        self::assertSame(403, $this->post('studio_save', ['course' => $draft, 'draft' => []])->statusCode());

        // Lien expiré.
        $expired = $shares->saveLink(10, $key, 'public', 'viewer', time() - 60, 7, true);
        self::assertNull($shares->resolve((string) $expired));
    }

    private function newDraftAsStaff(): string
    {
        Gate::getInstance()->setPermissions(['training.create']);
        $_SESSION['user_id'] = 7;
        $_SESSION['tenant_id'] = 10;
        return $this->newDraft();
    }
}
