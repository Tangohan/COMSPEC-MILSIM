<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseFileRecordService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/**
 * Fiche sujet des fichiers SSE : la page reste en lecture, l'édition de chaque section tient dans une modale ouverte par
 * une icône « Modifier ». La vue est rendue pour de bon (vraie base sqlite, vraie fiche), avec et sans droit d'écriture.
 */
final class SseSubjectEditModalsTest extends TestCase
{
    private const MODALS = [
        'fx-m-alias' => 'alias',
        'fx-m-signalement' => 'signalement',
        'fx-m-liens' => 'liens',
        'fx-m-mention' => 'fichiers/mentions',
        'fx-m-photo' => 'fichiers/photos',
        'fx-m-note' => 'notes',
    ];

    private PDO $pdo;
    private int $subject = 0;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseFilesSqlite::open();
        $identities = new SseIdentityRepository();
        $records = new SseFileRecordRepository();
        $this->subject = $identities->create(1, ['last_name' => 'Keller', 'first_name' => 'Hans', 'birth_date' => '1984-05-02', 'sex' => 'M', 'nationality' => 'USA']);
        $records->saveProfile(1, $this->subject, ['height_cm' => 181, 'build' => 'medium', 'marks' => 'Cicatrice arcade gauche'], null);
        $records->addAlias(1, $this->subject, 'Le Comptable', 'nickname', 'known');
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        SseFilesSqlite::close();
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Rend la vraie vue de la fiche (sans l'enveloppe du portail) ; renvoie le HTML de la zone de contenu. */
    private function render(bool $manage, bool $write): string
    {
        $record = (new SseFileRecordService(new SseFileRecordRepository(), new SseIdentityRepository()))->record(1, $this->subject, ['gm' => false, 'can_see' => static fn (string $kind): bool => true, 'terminal_country' => 'US']);
        self::assertNotNull($record);
        $dir = self::root() . '/views/atak/sse/files';
        $src = (string) file_get_contents($dir . '/subject.php');
        $src = str_replace(["require dirname(__DIR__) . '/_layout.php';", '__DIR__'], ['', "'" . $dir . "'"], $src);
        $tmp = tempnam(sys_get_temp_dir(), 'fxsubject');
        file_put_contents($tmp, $src);
        $vars = [
            'record' => $record,
            'watching' => false,
            'csrfToken' => 'jeton-de-test',
            'filesCanManage' => $manage,
            'filesCanWrite' => $write,
            'mentionLabels' => ['warrant' => 'Mandat', 'wanted' => 'Recherché'],
        ];
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                include $__file;
                $GLOBALS['__fx_content'] = $sseContent ?? '';
            })($tmp, $vars);
        } finally {
            @unlink($tmp);
        }

        return (string) $GLOBALS['__fx_content'];
    }

    /** Le HTML hors des modales : ce que la page montre en lecture. */
    private static function outsideDialogs(string $html): string
    {
        return (string) preg_replace('~<dialog\b.*?</dialog>~s', '', $html);
    }

    public function testPageStaysReadOnlyWithoutInlineEditForms(): void
    {
        $html = $this->render(true, true);
        $page = self::outsideDialogs($html);
        self::assertStringNotContainsString('class="fx-more"', $page, 'plus de volets de formulaire dans la page');
        foreach (self::MODALS as $action) {
            self::assertDoesNotMatchRegularExpression('~<form[^>]+action="[^"]*/' . preg_quote($action, '~') . '"~', $page, 'formulaire ' . $action . ' encore en ligne');
        }
        self::assertStringContainsString('KELLER', $page);
        self::assertStringContainsString('Le Comptable', $page);
    }

    public function testEachEditableSectionHasAnEditIconAndItsModal(): void
    {
        $html = $this->render(true, true);
        foreach (array_keys(self::MODALS) as $id) {
            self::assertMatchesRegularExpression('~<a class="fx-edit" href="#' . $id . '" data-fx-modal-open="' . $id . '" aria-haspopup="dialog" aria-label="[^"]+" title="[^"]+"><svg[^>]*><use href="#fx-pen">~', $html, 'icône Modifier de ' . $id);
            self::assertMatchesRegularExpression('~<dialog class="fx-modal" id="' . $id . '" aria-labelledby="' . $id . '-t">~', $html);
            self::assertStringContainsString('id="' . $id . '-t"', $html);
        }
        self::assertSame(count(self::MODALS), substr_count($html, '<dialog '));
    }

    public function testModalsKeepTheSameFormsPostRoutesAndCsrf(): void
    {
        $html = $this->render(true, true);
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        foreach (self::MODALS as $id => $action) {
            self::assertSame(1, preg_match('~<dialog class="fx-modal" id="' . $id . '".*?</dialog>~s', $html, $m), $id);
            $dialog = $m[0];
            self::assertMatchesRegularExpression('~<form class="fx-modal__form" method="post" action="[^"]*/' . preg_quote($action, '~') . '"~', $dialog, $id);
            self::assertStringContainsString('name="_csrf_token" value="jeton-de-test"', $dialog, 'CSRF conservé : ' . $id);
            self::assertStringContainsString('type="submit"', $dialog);
            self::assertStringContainsString('data-fx-modal-close', $dialog, 'Annuler et fermer : ' . $id);
            self::assertStringContainsString('role="alert" data-fx-modal-err hidden', $dialog, 'emplacement d’erreur : ' . $id);
            self::assertMatchesRegularExpression("~post\\('/atak/sse/fichiers/(sujet/\\{id\\}/)?" . preg_quote(basename($action), '~') . "'~", $routes, 'route POST inchangée : ' . $action);
        }
        self::assertStringContainsString('enctype="multipart/form-data"', $this->dialog($html, 'fx-m-photo'));
        self::assertStringContainsString('name="marks"', $this->dialog($html, 'fx-m-signalement'));
        self::assertStringContainsString('Cicatrice arcade gauche', $this->dialog($html, 'fx-m-signalement'), 'champs préremplis');
    }

    private function dialog(string $html, string $id): string
    {
        self::assertSame(1, preg_match('~<dialog class="fx-modal" id="' . $id . '".*?</dialog>~s', $html, $m), $id);

        return $m[0];
    }

    public function testNoRightNoEditButtonAndNoModal(): void
    {
        $html = $this->render(false, false);
        self::assertStringNotContainsString('fx-edit', $html);
        self::assertStringNotContainsString('<dialog', $html);
        self::assertStringNotContainsString('data-fx-modal-open', $html);
        self::assertStringContainsString('KELLER', $html);
    }

    public function testWriteOnlyKeepsPhotoAndNoteButNotTheManagerSections(): void
    {
        $html = $this->render(false, true);
        self::assertStringContainsString('id="fx-m-photo"', $html);
        self::assertStringContainsString('id="fx-m-note"', $html);
        foreach (['fx-m-alias', 'fx-m-signalement', 'fx-m-liens', 'fx-m-mention'] as $id) {
            self::assertStringNotContainsString($id, $html, $id . ' réservé à la gestion des fichiers');
        }
    }

    public function testServerErrorIsCarriedToTheModalScriptAndNotLostOnThePage(): void
    {
        $_SESSION['_flash']['error'] = 'Alias vide : rien n’a été ajouté.';
        $html = $this->render(true, true);
        self::assertStringContainsString('data-fx-flash-error>Alias vide', $html);
        self::assertArrayNotHasKey('error', $_SESSION['_flash'] ?? [], 'message consommé une seule fois');
        $quiet = $this->render(true, true);
        self::assertStringNotContainsString('data-fx-flash-error', $quiet);
    }

    public function testRegistryDossierLinksOpenTheModals(): void
    {
        $show = (string) file_get_contents(self::root() . '/views/atak/sse/papers/identity_show.php');
        $modals = (string) file_get_contents(self::root() . '/views/atak/sse/files/_subject_modals.php');
        foreach (['fx-m-signalement', 'fx-m-photo', 'fx-m-mention'] as $id) {
            self::assertStringContainsString("#" . $id, $show);
            self::assertStringContainsString("'" . $id . "'", $modals);
        }
    }

    public function testControllerReopensTheModalOnValidationErrors(): void
    {
        $src = (string) file_get_contents(self::root() . '/app/Controllers/Web/SseFileRecordController.php');
        foreach (['#fx-m-alias', '#fx-m-liens', '#fx-m-note'] as $anchor) {
            self::assertStringContainsString($anchor, $src);
        }
    }

    public function testScriptTrapsFocusClosesOnEscapeAndRestoresFocus(): void
    {
        $js = (string) file_get_contents(self::root() . '/public/assets/js/sse-files-modal.js');
        foreach (['showModal', 'data-fx-modal-open', "e.key !== 'Tab'", 'opener.focus', 'sessionStorage', 'data-fx-flash-error'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        // Échap : fermeture native du dialog modal, puis retour du focus à l'icône (événement close).
        self::assertStringContainsString("document.addEventListener('close'", $js);
    }

    public function testStyleCoversMobileFullScreenTouchTargetsAndNoScriptFallback(): void
    {
        $css = (string) file_get_contents(self::root() . '/public/assets/css/sse-files-kit.css');
        self::assertStringContainsString('dialog.fx-modal:target:not([open])', $css, 'repli sans JavaScript');
        self::assertMatchesRegularExpression('~\.fx a\.fx-edit \{[^}]*width: 44px; height: 44px~', $css);
        self::assertMatchesRegularExpression('~@media \(max-width: 640px\) \{\s*dialog\.fx-modal \{ width: 100vw;[^}]*height: 100dvh~', $css, 'plein écran sur mobile');
        self::assertStringNotContainsString('sse-ds', $css);
    }

    public function testNewFilesCarryNoForbiddenWording(): void
    {
        foreach (['views/atak/sse/files/_subject_modals.php', 'public/assets/js/sse-files-modal.js'] as $rel) {
            $text = (string) file_get_contents(self::root() . '/' . $rel);
            self::assertSame(0, preg_match('/simulation|fiction|fictive|sp[ée]cimen|jeu de rôle/iu', $text), $rel);
            self::assertStringNotContainsString('sse-ds', $text);
        }
    }
}
