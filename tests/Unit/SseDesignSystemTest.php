<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseDocumentChromeCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Système de design du portail SSE (sse-ds.css, sse-ds.js) : branchement dans le layout, jetons et alias,
 * composants câblés (fenêtres, tiroir, onglets), aucun sur-titre, et page de référence « Présentation des documents »
 * rendue pour de vrai (gabarits réels, compteurs, onglets, données de longueur et de nombre réalistes).
 */
final class SseDesignSystemTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 1];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale'], $_GET['modifier']);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root() . '/' . $path);
    }

    /**
     * @param list<array<string,mixed>> $prefabs
     */
    private function renderPage(array $prefabs, string $active, bool $canManage, string $modifier = ''): string
    {
        $_GET['modifier'] = $modifier;
        $data = [
            'prefabs' => $prefabs,
            'activePrefabCode' => $active,
            'editPrefab' => $prefabs[0] ?? null,
            'paperStyles' => SseDocumentChromeCatalog::paperStyles(),
            'canManage' => $canManage,
            'documentChrome' => $prefabs[0] ?? SseDocumentChromeCatalog::defaultChrome(),
        ];
        ob_start();
        (static function () use ($data): void {
            extract($data, EXTR_SKIP);
            require dirname(__DIR__, 2) . '/views/atak/sse/partials/document_presentation_body.php';
        })();
        unset($_GET['modifier']);

        return (string) ob_get_clean();
    }

    /** @return list<array<string,mixed>> */
    private function prefabs(int $custom): array
    {
        $out = [];
        foreach (SseDocumentChromeCatalog::builtInPrefabs() as $row) {
            $row['is_builtin'] = true;
            $out[] = $row;
        }
        $styles = array_keys(SseDocumentChromeCatalog::paperStyles());
        for ($i = 0; $i < $custom; ++$i) {
            $row = SseDocumentChromeCatalog::builtInPrefabs()[$i % 5];
            $row['code'] = 'custom_' . $i;
            $row['label'] = 'Note de renseignement hebdomadaire — Direction du renseignement militaire, section Proche-Orient n° ' . $i;
            $row['paper_style'] = $styles[$i % 4];
            $row['is_builtin'] = false;
            $row['is_default'] = false;
            $out[] = $row;
        }

        return $out;
    }

    // ------------------------------------------------------------------ branchement

    public function testLayoutLoadsTheDesignSystemAfterSseUiAndBeforeNotify(): void
    {
        $layout = $this->read('views/atak/sse/_layout.php');
        $ui = strpos($layout, 'assets/css/sse_ui.css');
        $ds = strpos($layout, 'assets/css/sse-ds.css');
        $notify = strpos($layout, 'assets/css/sse_notify.css');
        self::assertNotFalse($ds);
        self::assertGreaterThan($ui, $ds, 'sse-ds.css doit suivre sse_ui.css : le nouveau système est la référence.');
        self::assertLessThan($notify, $ds, 'sse_notify.css consomme les jetons de sse-ds.css.');
        self::assertMatchesRegularExpression('#assets/js/sse-ds\.js\'\)\) \?>" defer></script>#', $layout);
        self::assertGreaterThan(strpos($layout, 'sse-ui.js'), strpos($layout, 'sse-ds.js'));
    }

    public function testFontsAreSelfHostedLightAndLicensed(): void
    {
        $layout = $this->read('views/atak/sse/_layout.php');
        self::assertStringContainsString('assets/fonts/archivo/archivo-latin.woff2', $layout);
        self::assertStringContainsString('@font-face { font-family: "Archivo"', $layout);
        self::assertStringContainsString('jetbrains-mono-latin.woff2', $layout);
        self::assertStringNotContainsString('fonts.googleapis.com', $layout);
        foreach (['public/assets/fonts/archivo/archivo-latin.woff2', 'public/assets/fonts/nine-line/jetbrains-mono-latin.woff2'] as $font) {
            self::assertFileExists($this->root() . '/' . $font);
            self::assertLessThan(300 * 1024, (int) filesize($this->root() . '/' . $font), $font);
            self::assertSame('wOF2', substr($this->read($font), 0, 4), $font);
        }
        self::assertStringContainsString('SIL Open Font License', $this->read('public/assets/fonts/archivo/archivo-OFL.txt'));
    }

    public function testNoOverlineNorEyebrowNorNumberedHeadingsInTheSystemOrItsReferencePage(): void
    {
        foreach ([
            'public/assets/css/sse-ds.css',
            'public/assets/js/sse-ds.js',
            'public/assets/css/sse_notify.css',
            'views/atak/sse/document_presentation.php',
            'views/atak/sse/partials/document_presentation_body.php',
        ] as $file) {
            $src = strtolower($this->read($file));
            self::assertStringNotContainsString('overline', $src, $file);
            self::assertStringNotContainsString('eyebrow', $src, $file);
            self::assertStringNotContainsString('kicker', $src, $file);
        }
        $page = $this->read('views/atak/sse/partials/document_presentation_body.php');
        self::assertSame(0, preg_match('#<h[1-4][^>]*>\s*(?:\d{1,2}[.\s/·-]|0\d)#', $page), 'Pas de numérotation devant un titre.');
    }

    public function testTokensAndFiveTonesAreDefined(): void
    {
        $css = $this->read('public/assets/css/sse-ds.css');
        foreach ([
            '--sse-bg', '--sse-surface', '--sse-sunken', '--sse-raised', '--sse-hover', '--sse-line', '--sse-text', '--sse-strong', '--sse-muted',
            '--sse-accent', '--sse-accent-ink', '--sse-class', '--sse-ok', '--sse-info', '--sse-warn', '--sse-bad', '--sse-neut',
            '--sse-cls-u', '--sse-cls-cui', '--sse-cls-c', '--sse-cls-s', '--sse-cls-ts', '--sse-font', '--sse-font-mono',
        ] as $token) {
            self::assertStringContainsString($token . ':', $css, $token);
        }
        self::assertStringContainsString('--sse-radius: 6px', $css);
        self::assertStringContainsString('--sse-pad: 24px', $css);
        foreach (['ok', 'info', 'warn', 'bad', 'neut'] as $tone) {
            self::assertStringContainsString('[data-tone="' . $tone . '"]', $css);
        }
        foreach (['1', '2', '3', '4'] as $n) {
            self::assertStringContainsString('.sse-level[data-level="' . $n . '"]', $css);
        }
        foreach (['unclassified', 'cui', 'confidential', 'secret', 'top-secret'] as $c) {
            self::assertStringContainsString('.sse-classif[data-classif="' . $c . '"]', $css);
        }
    }

    public function testLegacyVariablesAreAliasedToTheNewTokens(): void
    {
        $css = $this->read('public/assets/css/sse-ds.css');
        $aliases = [
            '--bg:' => 'var(--sse-bg)', '--surface:' => 'var(--sse-surface)', '--surface-2:' => 'var(--sse-raised)', '--border:' => 'var(--sse-line)',
            '--text-strong:' => 'var(--sse-strong)', '--muted:' => 'var(--sse-muted)', '--accent:' => 'var(--sse-accent)', '--green:' => 'var(--sse-accent)',
            '--sans:' => 'var(--sse-font)', '--font:' => 'var(--sse-font)', '--sx-ink:' => 'var(--sse-strong)', '--sx-line:' => 'var(--sse-line)',
            '--sx-acc:' => 'var(--sse-accent)', '--sx-mono:' => 'var(--sse-font-mono)', '--ui-surface:' => 'var(--sse-surface)', '--ui-accent:' => 'var(--sse-accent)',
            '--nt-err-line:' => 'var(--sse-bad-bd)', '--fx-ac:' => 'var(--sse-accent)',
        ];
        foreach ($aliases as $old => $new) {
            self::assertMatchesRegularExpression('#' . preg_quote($old, '#') . '\s*' . preg_quote($new, '#') . '#', $css, $old);
        }
        self::assertStringContainsString('body.sse-iw {', $css);
    }

    public function testNotifyComponentConsumesTheTokensWithoutChangingItsApi(): void
    {
        $css = $this->read('public/assets/css/sse_notify.css');
        foreach (['--nt-err-line: var(--sse-bad)', '--nt-ok-line: var(--sse-ok-bd)', '--nt-warn-line: var(--sse-warn)', 'border-radius: var(--sse-radius'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
        self::assertSame(0, preg_match('/--nt-[a-z-]+:\s*#[0-9a-f]{3,8}/i', $css), 'Plus de couleur en dur dans les jetons des messages.');
        self::assertStringContainsString('.sse-toast', $css);
        $js = $this->read('public/assets/js/sse-notify.js');
        self::assertStringContainsString('window.SseNotify = {', $js);
        foreach (['show:', 'error:', 'success:', 'warning:', 'failure:', 'fieldErrors:', 'dismiss:'] as $api) {
            self::assertStringContainsString($api, $js);
        }
        self::assertStringContainsString('sse-note sse-toast sse-note--', $js);
    }

    public function testNoTextSmallerThanTwelvePixelsInTheSystem(): void
    {
        foreach (['public/assets/css/sse-ds.css', 'public/assets/css/sse_notify.css'] as $file) {
            $css = $this->read($file);
            self::assertSame(0, preg_match('/font-size:\s*(?:[0-9]|10|11)(?:\.\d+)?px/', $css), $file);
            self::assertSame(0, preg_match('/font:\s*[a-z0-9 ]*?\b(?:[0-9]|10|11)(?:\.\d+)?px\//', $css), $file);
            self::assertSame(0, preg_match('/font-size:\s*0?\.[0-7]\d*rem/', $css), $file);
        }
    }

    public function testDialogsDrawerAndTabsAreWired(): void
    {
        $js = $this->read('public/assets/js/sse-ds.js');
        foreach ([
            'showModal', 'data-sse-open', 'data-sse-close', 'sse:dialog-open', 'sse:dialog-close', "'Tab'", 'back.focus', 'sse-dialog-open',
            'data-sse-drawer', 'data-sse-template', 'data-sse-open-item',
            'role="tab"', 'ArrowRight', 'ArrowLeft', 'Home', 'End', 'history.replaceState', 'data-sse-hash',
            'data-sse-list', 'data-sse-search', 'data-sse-filter', 'data-sse-sort', 'data-sse-select', 'data-sse-bulk',
        ] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        $code = (string) preg_replace('#^\s*//.*$#m', '', $code);
        $code = (string) preg_replace('#\s//\s.*$#m', '', $code);
        self::assertSame(0, preg_match('/[À-ÿ]/u', $code), 'Aucun texte français dans le script.');
        $css = $this->read('public/assets/css/sse-ds.css');
        foreach (['.sse-modal', '.sse-drawer', '.sse-tab[aria-selected="true"]', '.sse-table--cards', '.sse-toolbar', '.sse-skeleton', '.sse-empty', '.sse-bulk', '.sse-alert'] as $sel) {
            self::assertStringContainsString($sel, $css, $sel);
        }
        self::assertStringContainsString('prefers-reduced-motion', $css);
    }

    // ------------------------------------------------------------------ page de référence

    public function testReferencePageRendersHeaderTilesTabsWithCountsAndTheTicketingTable(): void
    {
        $prefabs = $this->prefabs(35);
        $html = $this->renderPage($prefabs, 'standard_restreint', true);

        self::assertSame(1, substr_count($html, '<h1'), 'Un seul en-tête de page.');
        self::assertStringContainsString('Présentation des documents', $html);
        self::assertStringContainsString('sse-lede', $html);
        self::assertStringContainsString('data-sse-open="#sse-pres-edit" data-sse-record=""', $html);
        // Tuiles : 40 modèles, 5 préfaits, 35 personnalisés.
        self::assertMatchesRegularExpression('#sse-tile__label">Modèles</span>\s*<span class="sse-tile__value">40</span>#', $html);
        self::assertMatchesRegularExpression('#Personnalisés</span>\s*<span class="sse-tile__value">35</span>#', $html);
        // Onglets à compteur.
        self::assertStringContainsString('role="tablist"', $html);
        self::assertMatchesRegularExpression('#data-sse-tab="all"[^>]*>Tous <span class="sse-count">40</span>#', $html);
        self::assertMatchesRegularExpression('#data-sse-tab="actif"[^>]*>En service <span class="sse-count">1</span>#', $html);
        self::assertMatchesRegularExpression('#data-sse-tab="prefait"[^>]*>Préfaits <span class="sse-count">5</span>#', $html);
        self::assertMatchesRegularExpression('#data-sse-tab="perso"[^>]*>Personnalisés <span class="sse-count">35</span>#', $html);
        self::assertSame(4, substr_count($html, 'role="tab"'));
        // Tableau : une ligne par modèle, recherche, filtre, tri, statut et type.
        self::assertSame(40, substr_count($html, 'data-sse-row'));
        self::assertSame(1, substr_count($html, '<span class="sse-status" data-tone="ok">En service</span>'));
        self::assertSame(39, substr_count($html, '<span class="sse-status" data-tone="neut">Disponible</span>'));
        self::assertStringContainsString('data-sse-search', $html);
        self::assertStringContainsString('data-sse-filter="style"', $html);
        self::assertStringContainsString('data-sse-sort="name"', $html);
        self::assertStringContainsString('data-sse-page-size="50"', $html);
        self::assertStringContainsString('sse-table--cards', $html);
        self::assertSame(40, substr_count($html, 'data-label="Aspect du papier"'));
        self::assertStringContainsString('class="sse-tag"', $html);
        // Tiroir et fenêtres : le niveau de classification de l'aperçu (Secret) est une pastille .sse-classif.
        self::assertStringContainsString('<dialog class="sse-drawer', $html);
        self::assertStringContainsString('<template data-sse-template>', $html);
        self::assertStringContainsString('class="sse-classif" data-classif="secret"', $html);
        self::assertStringContainsString('<dialog class="sse-modal sse-modal--wide" id="sse-pres-edit"', $html);
        self::assertStringContainsString('<dialog class="sse-modal" id="sse-pres-activate"', $html);
        // Les routes et les champs de l'ancienne page sont conservés.
        self::assertStringContainsString('/atak/sse/presentation"', $html);
        self::assertStringContainsString('/atak/sse/presentation/actif"', $html);
        foreach (['label', 'description', 'paper_style', 'banner', 'title_person', 'title_docs', 'subtitle_dossier', 'subtitle_feuille', 'subtitle_docs', 'footer', 'quality_prefix', 'org_line', 'access_note', 'seal_top', 'seal_bottom', 'btn_consult', 'btn_transmit', 'btn_close', 'make_active', 'prefab_code', 'code', '_csrf_token'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $html, $field);
        }
        // Une seule feuille complète (gabarit papier réel) quel que soit le nombre de modèles.
        self::assertSame(1, substr_count($html, '<article class="sse-doc-paper '));
        // Données JSON des fiches : balises neutralisées.
        self::assertStringNotContainsString('</script><script', $html);
    }

    public function testReferencePageHasNoSmallInlineTextAndNoInlineStyles(): void
    {
        $html = $this->renderPage($this->prefabs(3), 'standard_restreint', true);
        self::assertSame(0, preg_match('/font-size:\s*(?:[0-9]|10|11)(?:\.\d+)?px/', $html));
        self::assertSame(0, preg_match('/style="[^"]*font-size/', $html));
        // Tout bouton d'icône a un nom accessible, tout champ une étiquette.
        self::assertSame(substr_count($html, 'class="sse-iconbtn"'), substr_count($html, 'class="sse-iconbtn" data-sse-close aria-label='));
        self::assertStringContainsString('<label class="sr-only" for="sse-pres-q">', $html);
        self::assertStringContainsString('aria-controls="sse-pres-table"', $html);
        self::assertStringContainsString('id="sse-pres-table"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
    }

    public function testReferencePageEscapesLongAndHostileNames(): void
    {
        $prefabs = $this->prefabs(1);
        $prefabs[5]['label'] = '<script>alert(1)</script> Annexe technique ultra-confidentielle numéro 17-B bis — ' . str_repeat('très long ', 20);
        $prefabs[5]['description'] = '"><img src=x onerror=alert(1)>';
        $html = $this->renderPage($prefabs, 'standard_restreint', true);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertMatchesRegularExpression('#<script type="application/json" id="sse-pres-records">[^<]*</script>#', $html);
    }

    public function testReferencePageEmptyAndSingleStatesAreExplainedWithAnAction(): void
    {
        $empty = $this->renderPage([], '', true);
        self::assertStringContainsString('Aucun modèle de présentation', $empty);
        self::assertStringContainsString('class="sse-empty"', $empty);
        self::assertStringContainsString('Créer un modèle', $empty);
        self::assertStringNotContainsString('<table', $empty);
        self::assertStringNotContainsString('role="tablist"', $empty);

        $emptyReadOnly = $this->renderPage([], '', false);
        self::assertStringContainsString('réservée aux personnels habilités', $emptyReadOnly);
        self::assertStringNotContainsString('Créer un modèle', $emptyReadOnly);

        $one = $this->renderPage($this->prefabs(0), 'standard_restreint', true);
        self::assertSame(5, substr_count($one, 'data-sse-row'));
        self::assertStringContainsString('data-sse-empty-filtered', $one);
        self::assertStringContainsString('data-sse-reset', $one);
    }

    public function testReadOnlySessionsGetNeitherTheEditFormNorTheActivateButtons(): void
    {
        $html = $this->renderPage($this->prefabs(2), 'standard_restreint', false);
        self::assertStringNotContainsString('id="sse-pres-edit"', $html);
        self::assertStringNotContainsString('id="sse-pres-activate"', $html);
        self::assertStringNotContainsString('name="_csrf_token"', $html);
        self::assertStringNotContainsString('Nouveau modèle', $html);
        self::assertStringNotContainsString('data-sse-open="#sse-pres-activate"', $html);
        // L'aperçu reste consultable.
        self::assertStringContainsString('<dialog class="sse-drawer', $html);
    }

    public function testDeepLinkOpensTheDrawerOfThatTemplate(): void
    {
        $html = $this->renderPage($this->prefabs(2), 'standard_restreint', true, 'terrain_tache');
        self::assertStringContainsString('data-sse-open-item="terrain_tache"', $html);
        $none = $this->renderPage($this->prefabs(2), 'standard_restreint', true, 'inconnu');
        self::assertStringNotContainsString('data-sse-open-item', $none);
    }

    public function testPortalViewUsesTheBodyPartialAndTheLayout(): void
    {
        $view = $this->read('views/atak/sse/document_presentation.php');
        self::assertStringContainsString("require __DIR__ . '/partials/document_presentation_body.php';", $view);
        self::assertStringContainsString('$sseContent = ob_get_clean();', $view);
        self::assertStringContainsString("require __DIR__ . '/_layout.php';", $view);
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/atak/sse/presentation', [SsePortalController::class, 'documentPresentationIndex']", $routes);
        self::assertStringContainsString("'/atak/sse/presentation/actif', [SsePortalController::class, 'documentPresentationActivate']", $routes);
        self::assertStringContainsString("'/atak/sse/presentation', [SsePortalController::class, 'documentPresentationStore']", $routes);
    }

    public function testEnglishTranslationsExistForThePage(): void
    {
        $en = require $this->root() . '/lang/en/site_auto.php';
        foreach (['Aucun modèle de présentation', 'Voir la feuille complète', 'Préfaits : %1', 'Aucun modèle ne correspond', 'Retour à la rédaction'] as $fr) {
            self::assertArrayHasKey($fr, $en, $fr);
        }
    }
}
