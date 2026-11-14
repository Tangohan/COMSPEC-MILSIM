<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Services\Jnet\JnetUnitWorkspaceService as WS;
use PHPUnit\Framework\TestCase;

/**
 * JNET : l'éditeur de pages d'une unité. Une page se lit et s'écrit comme un document (texte modifiable en place, sans
 * cadre ni libellé de type, contrôles au survol, palette « / », barre d'enregistrement), tout en gardant le format de
 * stockage des blocs, les droits, le jeton CSRF et le repli sans JavaScript.
 */
final class JnetPageEditorTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private mixed $sessionStarted = null;

    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('b', 64)];
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @param array<string, mixed> $usw */
    private function render(string $view, array $usw, string $base = '/jnet/u/4'): string
    {
        $render = static function (string $__file, array $__usw, string $__base): string {
            $usw = $__usw;
            $base = $__base;
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(self::ROOT . 'views/jnet/unit/' . $view . '.php', $usw, $base);
    }

    /** @return array<string, mixed> */
    private function page(bool $edit, bool $canPost = true): array
    {
        $blocks = WS::sanitizeBlocks([
            ['id' => 'a0000001', 't' => 'h', 'text' => 'Fréquences'],
            ['id' => 'a0000002', 't' => 'p', 'text' => "Canal 1\nCanal 2"],
            ['id' => 'a0000003', 't' => 'todo', 'text' => 'Tester les radios', 'done' => true],
            ['id' => 'a0000004', 't' => 'call', 'text' => 'Silence radio'],
            ['id' => 'a0000005', 't' => 'poll', 'text' => 'Quel soir ?', 'opts' => ['Jeudi', 'Dimanche']],
            ['id' => 'a0000006', 't' => 'count'],
            ['id' => 'a0000007', 't' => 'div'],
        ]);

        return [
            'canPost' => $canPost,
            'canModerate' => false,
            'viewerId' => 1,
            'shareTargets' => [],
            'page' => [
                'id' => 7,
                'title' => 'Procédure radio',
                'created_by' => 1,
                'updated_at' => '2026-11-12 10:00:00',
                'updated_by_label' => 'X0',
                'blocksList' => $blocks,
                'votes' => ['a0000005' => [0 => [1], 1 => []]],
                'voterLabels' => [1 => 'X0'],
                'reactions' => ['counts' => ['vu' => 2], 'mine' => ['vu']],
                'comments' => [],
                'edit' => $edit,
                'event' => null,
            ],
        ];
    }

    public function testEditModeKeepsTheFormFieldsAndStorageFormat(): void
    {
        $html = $this->render('_page', $this->page(true));
        self::assertStringContainsString('action="/jnet/u/4/pages/7"', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringContainsString('name="version" value="2026-11-12 10:00:00"', $html);
        self::assertStringContainsString('name="title"', $html);
        self::assertStringContainsString('name="blocks[0][id]" value="a0000001"', $html);
        self::assertStringContainsString('name="blocks[2][done]" value="1" checked', $html);
        self::assertStringContainsString('name="blocks[4][opts][]" value="Jeudi"', $html);
        // Le repli sans JavaScript : monter, descendre, supprimer, ajouter et enregistrer sont des envois de formulaire.
        foreach (['value="up:1"', 'value="down:1"', 'value="del:1"', 'value="add:poll"', 'value="save"'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        // Les gabarits clonés par le script : un par type de bloc.
        self::assertSame(count(WS::BLOCK_TYPES), substr_count($html, 'data-uw-block-tpl="'));
        self::assertSame(substr_count($html, 'method="post"'), substr_count($html, 'name="_csrf_token"'));
        // Le contenu envoyé retombe sur le format stocké.
        $stored = WS::sanitizeBlocks([['id' => 'a0000005', 't' => 'poll', 'text' => 'Q', 'opts' => ['Jeudi', '', 'Dimanche', '']]]);
        self::assertSame(['Jeudi', 'Dimanche'], $stored[0]['opts']);
    }

    public function testEditModeHasNoFrameNoTypeLabelAndNoGenericFormLook(): void
    {
        $html = $this->render('_page', $this->page(true));
        foreach (['<fieldset', '<legend', 'uw-block__type', 'uw-block__tools', 'Ajouter un bloc</p>', 'Enregistrer la page'] as $gone) {
            self::assertStringNotContainsString($gone, $html, $gone);
        }
        self::assertStringContainsString('Tapez / pour insérer un bloc', $html);
        self::assertStringContainsString('Insérer un outil', $html);
        self::assertStringContainsString('class="uw-title uw-ed uw-ed--t"', $html, 'titre modifiable en place');
        self::assertStringContainsString('aria-label="Fil d’Ariane"', $html);
        self::assertStringContainsString('data-uw-status', $html, 'état d’enregistrement');
        self::assertStringContainsString('>Annuler</a>', $html);
        $css = $this->read('public/assets/css/jnet_unit_workspace.css');
        self::assertSame(0, preg_match('/\.uw-block\b/', $css), 'plus de cadre gris par bloc');
        self::assertMatchesRegularExpression('/:where\(\.uw-ed\) \{[^}]*resize: none;/s', $css, 'plus de coin de redimensionnement');
        self::assertStringContainsString('resize: none;', $css);
        self::assertStringContainsString('field-sizing: content;', $css);
        // Colonne de lecture généreuse, contrôles de bloc seulement au survol ou au focus, cibles d'au moins 32 px.
        self::assertStringContainsString('max-width: 976px;', $css);
        self::assertStringContainsString('.uw-bk:focus-within > .uw-bh', $css);
        self::assertMatchesRegularExpression('/\.uw-bh__b,\s*\.uw-del \{[^}]*width: 32px;[^}]*height: 32px;/s', $css);
        self::assertMatchesRegularExpression('/\.uw-body \{[^}]*font-size: 17px;[^}]*line-height: 1\.7;/s', $css);
    }

    public function testReadModeRendersEveryBlockTypeAndTheDiscussion(): void
    {
        $html = $this->render('_page', $this->page(false));
        self::assertStringContainsString('<h1 class="uw-title">Procédure radio</h1>', $html);
        self::assertStringContainsString('<h2>Fréquences</h2>', $html);
        self::assertStringContainsString('Canal 1<br />', $html);
        self::assertStringContainsString('class="uw-callout"', $html);
        self::assertStringContainsString('class="uw-count"', $html);
        self::assertStringContainsString('role="checkbox" aria-checked="true"', $html);
        self::assertStringContainsString('class="uw-po is-on"', $html, 'mon vote est marqué');
        self::assertStringContainsString('style="--w: 100%"', $html, 'barre de résultat');
        self::assertStringContainsString('1 vote ·', $html);
        foreach (['vu', 'recu', 'question'] as $kind) {
            self::assertStringContainsString('name="kind" value="' . $kind . '"', $html, $kind);
        }
        self::assertStringContainsString('Modifier</a>', $html);
        self::assertStringContainsString('placeholder="Commenter, puis Entrée"', $html);
        self::assertStringContainsString('Aucun commentaire. Lancez la discussion.', $html);
        self::assertSame(substr_count($html, 'method="post"'), substr_count($html, 'name="_csrf_token"'));
    }

    public function testReadOnlyMemberGetsNoWriteControl(): void
    {
        $html = $this->render('_page', $this->page(false, false));
        foreach (['method="post"', 'data-uw-act', 'Modifier</a>', 'name="body"', 'Supprimer la page'] as $absent) {
            self::assertStringNotContainsString($absent, $html, $absent);
        }
        self::assertStringContainsString('class="uw-po is-on"', $html);
        self::assertStringContainsString('Aucun commentaire.', $html);
    }

    public function testEmptyPageSaysSoAndMissingPageHasAWayBack(): void
    {
        $usw = $this->page(false);
        $usw['page']['blocksList'] = WS::sanitizeBlocks([['t' => 'p', 'text' => '']]);
        $html = $this->render('_page', $usw);
        self::assertStringContainsString('Cette page est vide pour l’instant.', $html);
        self::assertStringContainsString('Commencer à écrire', $html);
        $missing = $this->render('_page', ['canPost' => true, 'page' => null]);
        self::assertStringContainsString('Page introuvable.', $missing);
        self::assertStringContainsString('Retour aux pages', $missing);
    }

    public function testPageListIsARegisterWithReorderAndEmptyState(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Procédure radio', 'blocks' => '[{"id":"a0000001","t":"p","text":"x"}]', 'updated_at' => '2026-11-12 10:00:00', 'updated_by_label' => 'X0', 'comment_count' => 2],
            ['id' => 2, 'title' => '', 'blocks' => '[]', 'updated_at' => '2026-11-13 10:00:00', 'updated_by_label' => 'X1', 'comment_count' => 0],
        ];
        $html = $this->render('_pages', ['canPost' => true, 'pages' => $pages]);
        self::assertStringContainsString('class="ja-rows uw-pagerows" data-uw-reorder="/jnet/u/4/pages/ordre"', $html);
        self::assertStringContainsString('aria-label="Monter la page"', $html);
        self::assertStringContainsString('aria-label="Descendre la page"', $html);
        self::assertStringContainsString('Sans titre', $html);
        self::assertStringContainsString('1 bloc · 2 commentaires', $html);
        self::assertStringContainsString('Nouvelle page', $html);
        self::assertSame(substr_count($html, 'method="post"'), substr_count($html, 'name="_csrf_token"'));
        $readOnly = $this->render('_pages', ['canPost' => false, 'pages' => $pages]);
        self::assertStringNotContainsString('method="post"', $readOnly);
        self::assertStringNotContainsString('data-uw-reorder', $readOnly);
        $empty = $this->render('_pages', ['canPost' => true, 'pages' => []]);
        self::assertStringContainsString('Aucune page pour l’instant.', $empty);
        self::assertStringContainsString('Créer la première page', $empty);
    }

    public function testEditorScriptHandlesKeyboardPaletteDragAndAutosave(): void
    {
        $js = $this->read('public/assets/js/jnet_unit_workspace.js');
        foreach ([
            "'plaintext-only'",
            "e.key === 'Enter'",
            "e.key === 'Backspace'",
            "e.key === '/'",
            "'ArrowDown'",
            "'Escape'",
            "e.altKey",
            "String(e.key).toLowerCase() === 's'",
            'data-uw-add-end',
            'data-uw-grip',
            "sortable(blocks, '[data-uw-block]'",
            "fd.set('act', 'save')",
            'res.version',
            'res.conflict',
            'requestSubmit',
            'beforeunload',
            'visibilitychange',
            'Modifications non enregistrées',
            'getRandomValues',
        ] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertStringNotContainsString('innerHTML', $js);
        // Le texte d'une tâche vide se transforme en texte, et Entrée sur une tâche en crée une autre.
        self::assertStringContainsString("type === 'todo' && text === ''", $js);
        self::assertStringContainsString("insertAfter(fs, type === 'todo' ? 'todo' : 'p')", $js);
    }

    public function testServerAnswersTheAutosaveAndTheInlineGestures(): void
    {
        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        self::assertStringContainsString("['conflict' => true]", $ctl, 'conflit signalé au script');
        self::assertStringContainsString("['version' => (string) (\$fresh['updated_at'] ?? '')]", $ctl, 'nouvelle version renvoyée');
        self::assertStringContainsString('Csrf::validate', $ctl);
        // Case, vote et réaction répondent aussi en JSON, sans changer leurs droits.
        foreach (['togglePageTodo', 'vote', 'react'] as $method) {
            $start = strpos($ctl, 'public function ' . $method . '(');
            self::assertNotFalse($start, $method);
            $body = substr($ctl, (int) $start, 1500);
            self::assertStringContainsString("guard(\$request, \$params, 'pages', true)", $body, $method);
            self::assertStringContainsString('return $this->done($request,', $body, $method);
            self::assertStringNotContainsString('Response::redirect', $body, $method);
        }
        self::assertSame(15, preg_match_all('/\$this->guard\(\$request, \$params,/', $ctl), 'aucune route de plus');
    }

    public function testAnUnchangedSaveIsNotAConflict(): void
    {
        $repo = $this->read('app/Repositories/JnetUnitWorkspaceRepository.php');
        self::assertStringContainsString('updated_at = ?', $repo);
        self::assertStringContainsString('SELECT 1 FROM jnet_unit_pages WHERE tenant_id = ? AND unit_id = ? AND id = ?', $repo);
    }

    public function testPageEditorViewsStayQuietAndLeakNothing(): void
    {
        foreach (['views/jnet/unit/_page.php', 'views/jnet/unit/_pages.php', 'public/assets/js/jnet_unit_workspace.js'] as $file) {
            $src = $this->read($file);
            self::assertStringNotContainsString('class="kk"', $src, $file);
            self::assertStringNotContainsString('alert(', $src, $file);
            self::assertStringNotContainsString('confirm(', $src, $file);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src), 'aucun emoji : ' . $file);
        }
        $css = $this->read('public/assets/css/jnet_unit_workspace.css');
        // Les classes de l'éditeur ne reprennent aucun nom déjà pris par un autre composant de l'espace.
        self::assertStringContainsString('.uw-savebar {', $css);
        self::assertStringContainsString('.uw-body {', $css);
    }
}
