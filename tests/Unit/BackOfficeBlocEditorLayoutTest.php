<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\BackOffice\Home\HomeBlocks;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * Page « Bloc personnalisé » du back-office (/back-office/accueil/bloc) : l'éditeur de la page publique tient dans la
 * coque (zone d'édition contrainte, panneau de réglages fermé par défaut, barre flottante dans la zone d'édition) et
 * l'aperçu du texte riche est rendu, pas brut. La page publique de la communauté ne change pas.
 */
final class BackOfficeBlocEditorLayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($this->root . '/' . $rel));
    }

    private function editorXPath(): DOMXPath
    {
        $starter = HomeBlocks::starterBlock('article');
        self::assertNotNull($starter);
        $html = Response::view('admin.organization.bloc_editor', [
            'blocksLayout' => ['blocks' => [$starter]],
            'blocksConfig' => ['root' => '.bo-blocks[data-cl-editing]', 'layout' => ['blocks' => [$starter]]],
            'blocksSheets' => [],
        ])->body();
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function one(DOMXPath $xp, string $query): DOMElement
    {
        $nodes = $xp->query($query);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, $query);
        $node = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    public function testEditorChromeLivesInsideTheConstrainedPageContainer(): void
    {
        $xp = $this->editorXPath();
        $page = $this->one($xp, '//div[contains(concat(" ", normalize-space(@class), " "), " bo-blocks-page ")]');
        self::assertSame('body', $page->parentNode?->nodeName, 'La page est le seul bloc de tête : plus rien ne flotte à côté.');
        // Barre flottante, panneau, voile et champ fichier sont tous DANS le conteneur de la page, jamais enfants directs de la coque.
        foreach (['[@data-pbe-dock]', '[@data-pbe-panel]', '[@data-pbe-scrim]', '[@data-pbe-file]'] as $attr) {
            $inside = $xp->query('//div[contains(@class,"bo-blocks-page")]//*' . $attr);
            self::assertNotFalse($inside);
            self::assertSame(1, $inside->length, $attr);
        }
        $topLevel = $xp->query('/html/body/*[@data-pbe-dock or @data-pbe-panel or @data-pbe-scrim]');
        self::assertNotFalse($topLevel);
        self::assertSame(0, $topLevel->length);
        // Zone d'édition : région nommée, dans la mise en page à une colonne (+ panneau).
        $layout = $this->one($xp, '//div[@data-bo-blocks-layout]');
        $region = $this->one($xp, '//div[@data-bo-blocks-layout]//div[@data-cl-editing][@role="region"]');
        self::assertNotSame('', trim($region->getAttribute('aria-label')));
        self::assertSame($layout, $region->parentNode?->parentNode);
    }

    public function testSettingsPanelIsClosedByDefaultAndLabelled(): void
    {
        $xp = $this->editorXPath();
        $panel = $this->one($xp, '//aside[@data-pbe-panel]');
        self::assertSame('true', $panel->getAttribute('aria-hidden'));
        self::assertStringNotContainsString('is-open', $panel->getAttribute('class'));
        self::assertNotSame('', trim($panel->getAttribute('aria-label')));
        $close = $this->one($xp, '//aside[@data-pbe-panel]//button[@data-pbe-action="close"]');
        self::assertNotSame('', trim($close->getAttribute('aria-label')));
        $scrim = $this->one($xp, '//div[@data-pbe-scrim]');
        self::assertTrue($scrim->hasAttribute('hidden'));
    }

    public function testFloatingToolbarKeepsItsActionsAndAccessibleNames(): void
    {
        $xp = $this->editorXPath();
        $dock = $this->one($xp, '//div[@data-pbe-dock]');
        self::assertSame('toolbar', $dock->getAttribute('role'));
        self::assertNotSame('', trim($dock->getAttribute('aria-label')));
        foreach (['library', 'publish'] as $action) {
            $btn = $this->one($xp, '//div[@data-pbe-dock]//button[@data-pbe-action="' . $action . '"]');
            self::assertNotSame('', trim($btn->textContent), 'Un libellé texte reste dans le DOM pour chaque bouton.');
        }
        self::assertSame('status', $this->one($xp, '//div[@data-pbe-dock]//div[contains(@class,"pbe-dock__status")]')->getAttribute('role'));
    }

    public function testStylesheetNeverForcesAFullScreenFixedLayerOnTheShell(): void
    {
        $css = $this->read('public/assets/css/back-office-blocks.css');
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        self::assertNotEmpty($rules);
        foreach ($rules as $rule) {
            if (!preg_match('/position\s*:\s*fixed/', $rule[2])) {
                continue;
            }
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim(preg_replace('#/\*.*?\*/#s', '', $selector) ?? '');
                self::assertMatchesRegularExpression(
                    '/\.pbe-(dock|panel)\b/',
                    $selector,
                    'Seuls la barre et le tiroir de réglages peuvent être fixes : ' . $selector
                );
                self::assertDoesNotMatchRegularExpression('/(^|\s)(\.bo-blocks|\.bo-blocks-page|\.bo-blocks-layout|\.bo-blocks-main|body|html|\.ath-[a-z_-]+)\s*$/', $selector);
            }
            self::assertDoesNotMatchRegularExpression('/inset\s*:\s*0\s*;/', $rule[2], 'Pas de couche plein écran.');
        }
    }

    public function testClosedPanelIsRemovedFromTheLayoutAndShellAnimationDoesNotTouchThePage(): void
    {
        $css = $this->read('public/assets/css/back-office-blocks.css');
        self::assertMatchesRegularExpression('/\.bo-blocks-page \.pbe-panel\s*\{[^}]*display:\s*none/', $css);
        self::assertMatchesRegularExpression('/\.bo-blocks-page \.pbe-panel\.is-open\s*\{\s*display:\s*flex/', $css);
        self::assertMatchesRegularExpression('/\.ath-main__body > \.bo-blocks-page\s*\{\s*animation:\s*none/', $css);
        self::assertMatchesRegularExpression('/\.bo-blocks-page \.pbe-dock\s*\{[^}]*transform:\s*none/', $css);
        // Ni police ni jeu de styles écartés du back-office.
        self::assertStringNotContainsString('sse-ds', $css);
        self::assertStringNotContainsString("'Archivo'", $css);
    }

    public function testShellAnimationExplainsWhyTransformsAreNotUsed(): void
    {
        $refresh = $this->read('public/assets/css/back-office-refresh.css');
        self::assertMatchesRegularExpression('/\.ath-main__body > \*\s*\{\s*animation:\s*athRefreshRise[^;]*both/', $refresh);
        $panelCss = $this->read('public/assets/css/community-page-builder.css');
        self::assertStringContainsString('translateX(104%)', $panelCss, 'Le tiroir public cache toujours son panneau par transform (inchangé).');
    }

    public function testRichTextPreviewIsRenderedNotRawMarkdown(): void
    {
        $block = HomeBlocks::starterBlock('article');
        self::assertNotNull($block);
        $html = Response::view('community.partials.page_block', ['pbBlock' => $block, 'pbEditing' => true, 'pbSheets' => []])->body();
        self::assertStringContainsString('<h4>Un sous-titre</h4>', $html);
        self::assertStringContainsString('<strong>gras</strong>', $html);
        self::assertStringContainsString('<em>italique</em>', $html);
        self::assertMatchesRegularExpression('/<a [^>]*href="https:\/\/example\.org"/', $html);
        self::assertStringContainsString('<li>premier point</li>', $html);
        self::assertStringNotContainsString('**gras**', $html);
        self::assertStringNotContainsString('## Un sous-titre', $html);
        self::assertStringContainsString('data-pbe-open="text"', $html);
        self::assertStringNotContainsString('data-cl-prop="text"', $html, 'Le texte riche ne s\'édite plus en balisage brut sur place.');
    }

    public function testEmptyRichTextShowsAPlaceholderNotContent(): void
    {
        $block = HomeBlocks::starterBlock('richtext');
        self::assertNotNull($block);
        $block['props']['text'] = '';
        $html = Response::view('community.partials.page_block', ['pbBlock' => $block, 'pbEditing' => true, 'pbSheets' => []])->body();
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xp = new DOMXPath($doc);
        $rich = $this->one($xp, '//div[contains(concat(" ", normalize-space(@class), " "), " pb-rich--empty ")]');
        self::assertSame('', trim($rich->textContent), 'Le texte d\'aide est un placeholder (attribut), pas du contenu.');
        self::assertNotSame('', trim($rich->getAttribute('data-placeholder')));
    }

    public function testPublishedBlockIsUnchanged(): void
    {
        $block = HomeBlocks::starterBlock('article');
        self::assertNotNull($block);
        $html = Response::view('community.partials.page_block', ['pbBlock' => $block, 'pbEditing' => false, 'pbSheets' => []])->body();
        self::assertStringContainsString('<strong>gras</strong>', $html);
        self::assertStringNotContainsString('data-pbe-open', $html);
        self::assertStringNotContainsString('pb-rich--empty', $html);
    }

    public function testPublicCommunityEditorKeepsItsOwnChromeAndAssets(): void
    {
        $view = $this->read('views/community/partials/page_editor.php');
        self::assertStringContainsString('data-pbe-dock', $view);
        self::assertStringContainsString('data-pbe-panel', $view);
        self::assertStringNotContainsString('bo-blocks-page', $view);
        $css = $this->read('public/assets/css/community-page-builder.css');
        self::assertMatchesRegularExpression('/\.pbe-dock\s*\{[^}]*position:\s*fixed/', $css);
        self::assertMatchesRegularExpression('/\.pbe-panel\s*\{[^}]*position:\s*fixed/', $css);
        // Les règles propres à la page du back-office ne fuient pas dans la feuille publique.
        self::assertStringNotContainsString('bo-blocks-page', $css);
        $js = $this->read('public/assets/js/community-page-editor.js');
        self::assertStringContainsString('[data-pbe-open]', $js);
    }

    public function testNewStringsHaveAnEnglishTranslation(): void
    {
        $en = (array) require $this->root . '/lang/en/site_auto.php';
        foreach (['Réglages du bloc', 'Contenu du bloc personnalisé', 'Cliquez pour écrire le texte (titres, listes, gras, liens)'] as $fr) {
            self::assertArrayHasKey($fr, $en);
            self::assertNotSame('', trim((string) $en[$fr]));
        }
    }
}
