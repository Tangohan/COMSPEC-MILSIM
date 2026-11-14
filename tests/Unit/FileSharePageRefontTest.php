<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page « Fichiers partagés » refaite sur l'habillage JNET : même coque et mêmes jetons que l'extranet, mêmes routes, mêmes
 * formulaires et mêmes contrôles de droits qu'avant, aucune fenêtre. Câblage vérifié sur les sources.
 */
final class FileSharePageRefontTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    private function views(): string
    {
        return $this->read('views/partage/_page.php') . $this->read('views/partage/index.php') . $this->read('views/partage/_files.php');
    }

    public function testPageUsesTheJnetShellAndLeavesTheBackOfficeLayout(): void
    {
        $ctl = $this->read('app/Controllers/Web/FileShareController.php');
        self::assertStringContainsString("'partage._page'", $ctl);
        self::assertStringNotContainsString("Response::view('layout.main'", $ctl);
        self::assertStringNotContainsString('admin.partage.index', $ctl);

        $shell = $this->read('views/partage/_page.php');
        self::assertStringContainsString('<!DOCTYPE html>', $shell);
        self::assertStringContainsString('assets/css/jnet_athena.css', $shell);
        self::assertStringContainsString('assets/css/partage.css', $shell);
        self::assertStringContainsString('class="ja fp"', $shell);
        self::assertStringContainsString('JnetUi::account(', $shell);
        self::assertStringContainsString('ja-hd ja-hd--rows', $shell);
        self::assertStringNotContainsString('layout.main', $shell);
        self::assertStringNotContainsString('back-office-', $shell);
        self::assertStringNotContainsString('ja-crest', $this->views());

        foreach (['views/admin/partage/index.php', 'public/assets/css/back-office-partage.css', 'public/assets/js/back-office-partage.js'] as $old) {
            self::assertFalse(is_file(self::ROOT . $old), $old . ' est remplacé');
        }
    }

    public function testBothAddressesAndEveryRouteAreKept(): void
    {
        $routes = $this->read('routes/web.php');
        $ctl = '\\App\\Controllers\\Web\\FileShareController::class';
        $expected = [
            "get('/partage', [" . $ctl . ", 'index']",
            "get('/back-office/partage', [" . $ctl . ", 'index']",
            "get('/partage/fichiers/{id}', [" . $ctl . ", 'download']",
            "post('/partage/fichiers/{id}/renommer', [" . $ctl . ", 'renameFile']",
            "post('/partage/fichiers/{id}/supprimer', [" . $ctl . ", 'deleteFile']",
            "post('/partage/dossiers', [" . $ctl . ", 'createFolder']",
            "post('/partage/dossiers/{id}/modifier', [" . $ctl . ", 'updateFolder']",
            "post('/partage/dossiers/{id}/supprimer', [" . $ctl . ", 'deleteFolder']",
            "post('/partage/dossiers/{id}/fichiers', [" . $ctl . ", 'upload']",
            "post('/partage/dossiers/{id}/reprendre', [" . $ctl . ", 'takeOver']",
            "post('/partage/acces', [" . $ctl . ", 'share']",
            "post('/partage/acces/{id}/retirer', [" . $ctl . ", 'unshare']",
            "post('/partage/corbeille/{type}/{id}/restaurer', [" . $ctl . ", 'restore']",
            "post('/partage/corbeille/{type}/{id}/supprimer', [" . $ctl . ", 'purge']",
        ];
        foreach ($expected as $needle) {
            self::assertStringContainsString($needle, $routes, $needle);
        }
    }

    public function testEveryActionKeepsItsFormAndServerSideChecks(): void
    {
        $views = $this->views();
        foreach ([
            "'partage/dossiers/' . \$folderId . '/fichiers'",
            "url('partage/dossiers')",
            "'partage/dossiers/' . \$folderId . '/modifier'",
            "'partage/dossiers/' . \$folderId . '/supprimer'",
            "'partage/dossiers/' . (int) \$f['id'] . '/reprendre'",
            "url('partage/acces')",
            "'/retirer'",
            "'partage/fichiers/' . \$fileId . '/renommer'",
            "'partage/fichiers/' . \$fileId . '/supprimer'",
            "'/restaurer'",
            "'partage/corbeille/dossier/'",
            "'partage/corbeille/fichier/'",
            'name="files[]"',
            'name="target_type"',
            'name="target_user"',
            'name="target_role"',
            'name="target_unit"',
            'name="level"',
            'name="expires"',
            'name="item_type"',
            'name="parent_id"',
        ] as $needle) {
            self::assertStringContainsString($needle, $views, $needle);
        }
        // Les formulaires portent le jeton CSRF et l'indicateur d'adresse ; le contrôleur vérifie encore tout.
        self::assertStringContainsString('Csrf::field()', $this->read('views/partage/index.php'));
        self::assertStringContainsString('name="_bo"', $this->read('views/partage/index.php'));
        $ctl = $this->read('app/Controllers/Web/FileShareController.php');
        self::assertStringContainsString('Csrf::validate(', $ctl);
        self::assertStringContainsString('$ws->folderLevel($parentId) < FileShareAccess::UPLOAD', $ctl);
        self::assertStringContainsString('$ws->fileLevel($file) < FileShareAccess::MANAGE', $ctl);
        self::assertStringContainsString('!$ws->canDeleteFile($file)', $ctl);
        self::assertStringContainsString('canCreateRoot()', $ctl);
        // Les boutons ne s'affichent que pour qui a le droit, avec les mêmes seuils qu'avant.
        $view = $this->read('views/partage/index.php');
        self::assertStringContainsString('$level >= FileShareAccess::UPLOAD', $view);
        self::assertStringContainsString('$level >= FileShareAccess::MANAGE', $view);
        self::assertStringContainsString('$ws->canDeleteFile($f)', $this->read('views/partage/_files.php'));
    }

    public function testFilesShowTypeSizeDateAuthorAndShareTargets(): void
    {
        $files = $this->read('views/partage/_files.php');
        foreach (['fp-file__size', 'fp-file__date', 'fp-file__by', 'fp-file__share', 'data-kind', 'data-ts', 'data-size', "'Unité '"] as $needle) {
            self::assertStringContainsString($needle, $files, $needle);
        }
        // Les noms des destinataires ne sont montrés qu'à qui gère le fichier ; les autres voient seulement un nombre.
        self::assertStringContainsString('if ($canManage) {', $files);
        self::assertStringContainsString('+<?= count($direct) ?> accès', $files);

        $view = $this->read('views/partage/index.php');
        self::assertStringContainsString('fp-album', $view);
        self::assertStringContainsString('Fil d’Ariane', $view);
        self::assertStringContainsString('data-fp-filter', $view);
        self::assertStringContainsString('data-fp-sort', $view);
        self::assertStringContainsString('data-fp-view="album"', $view);
        self::assertStringContainsString('loading="lazy"', $files);
        foreach (['Ce dossier est vide.', 'La corbeille est vide.', 'Rien n’a encore été partagé avec vous.', 'Aucun résultat.'] as $empty) {
            self::assertStringContainsString($empty, $view, $empty);
        }
    }

    public function testNoPopupNoEyebrowNoEmoji(): void
    {
        $views = $this->views();
        $js = $this->read('public/assets/js/partage.js');
        foreach ([$views, $js] as $src) {
            self::assertStringNotContainsString('<dialog', $src);
            self::assertStringNotContainsString('showModal', $src);
            self::assertStringNotContainsString('alert(', $src);
            self::assertStringNotContainsString('confirm(', $src);
            self::assertStringNotContainsString('data-confirm', $src);
            self::assertStringNotContainsString('toast', strtolower($src));
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src), 'aucun emoji');
        }
        self::assertSame(0, preg_match('/(?:class|id)="[^"]*\b(?:eyebrow|kicker|overline|surtitre|kk)\b/i', $views));
        // Les confirmations se font dans la page : case, puis bouton de confirmation.
        self::assertStringContainsString('fp-confirm', $views);
        // Le titre de page vient avant ceux des sections, et la coque n'en ajoute pas un second.
        $view = $this->read('views/partage/index.php');
        self::assertLessThan(strpos($view, '<h2'), strpos($view, '<h1'));
        self::assertStringNotContainsString('<h1', $this->read('views/partage/_page.php'));
    }

    public function testStyleSheetReusesJnetTokensAndHandlesPhones(): void
    {
        $css = $this->read('public/assets/css/partage.css');
        foreach (['var(--ja-rule', 'var(--ja-c1)', 'var(--ja-ink)', 'var(--u)', '.fp-album', '.fp-files__head', '.fp-tg:checked'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        self::assertStringContainsString('@media (max-width: 720px)', $css);
        self::assertStringContainsString('@media (max-width: 440px)', $css);
        self::assertStringNotContainsString('border-left: 3px', $css);
        self::assertStringNotContainsString('#fff', $css);
    }

    public function testScriptIsOnlyAnEnhancement(): void
    {
        $js = $this->read('public/assets/js/partage.js');
        foreach (['data-fp-filter', 'data-fp-sort', 'data-fp-view', 'data-fp-drop', 'data-fp-share-form', 'localStorage'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        // La mémoire du choix d'affichage ne casse jamais la page.
        self::assertStringContainsString('catch', $js);
        self::assertStringContainsString('assets/js/partage.js', $this->read('views/partage/_page.php'));
    }
}
