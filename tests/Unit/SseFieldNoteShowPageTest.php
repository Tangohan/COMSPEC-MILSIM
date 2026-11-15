<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseFieldNoteTimeline;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFieldNoteShowFixture;

/**
 * Page de lecture d'une fiche de renseignement du portail SSE : la VRAIE vue est rendue avec la vraie coque sur trois jeux de
 * données (fiche courte, fiche longue, lecteur en lecture seule). Le document tient d'un seul bloc, le widget « Liaisons » est
 * inclus une fois et hors du document, les formulaires vivent dans des fenêtres avec leur jeton, les boutons suivent les droits,
 * aucune étiquette au-dessus d'un titre, aucun texte minuscule, aucune mention de fiction.
 * Les fichiers sont lus sans dépendre des fins de ligne du dépôt.
 */
final class SseFieldNoteShowPageTest extends TestCase
{
    private const VIEW = 'views/atak/sse/field_note_show.php';
    private const CSS = 'public/assets/css/sse-fiche-rens.css';
    private const JS = 'public/assets/js/sse-fiche-rens.js';

    protected function setUp(): void
    {
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1, 'sse_tenant_id' => 1, 'callsign' => 'ANALYSTE'];
        $_SERVER['REQUEST_URI'] = '/atak/sse/fiches/1';
        \App\Core\Csrf::token();
    }

    protected function tearDown(): void
    {
        SseFieldNoteShowFixture::close();
        $_SESSION = [];
    }

    private static function read(string $relative): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative));
    }

    /** Rend la page entière (vue + coque) pour une variante du jeu de données. */
    private static function render(string $variant): string
    {
        $data = SseFieldNoteShowFixture::scenario($variant);
        $level = ob_get_level();
        ob_start();
        try {
            (static function (array $__data): void {
                extract($__data);
                require dirname(__DIR__, 2) . '/' . self::VIEW;
            })($data);

            return str_replace("\r\n", "\n", (string) ob_get_clean());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /** Le contenu propre à la page (sans la coque). */
    private static function page(string $html): string
    {
        $start = strpos($html, '<div class="fnr fnr--');
        self::assertNotFalse($start, 'la page est rendue');
        $end = strpos($html, '</main>', (int) $start);

        return substr($html, (int) $start, ($end === false ? strlen($html) : $end) - (int) $start);
    }

    /** La page sans l'onglet Liaisons : le widget (autre composant) a ses propres fenêtres, formulaires et libellés. */
    private static function own(string $page): string
    {
        $out = preg_replace('#<section class="fnr-panel" role="tabpanel" id="fnr-panel-liaisons".*?</aside>#s', '</aside>', $page, 1, $count);
        self::assertSame(1, $count, 'onglet Liaisons, dernier de la colonne');

        return (string) $out;
    }

    private static function document(string $page): string
    {
        self::assertSame(1, substr_count($page, '<article class="fnr-doc"'), 'un seul document');
        $start = (int) strpos($page, '<article class="fnr-doc"');
        $end = (int) strpos($page, '</article>', $start);
        self::assertGreaterThan($start, $end);

        return substr($page, $start, $end - $start);
    }

    public function testShortNoteRendersTheDocumentAndExplicitEmptyStates(): void
    {
        $html = self::render('a');
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'la vraie coque du portail');
        $page = self::page($html);
        $doc = self::document($page);

        self::assertMatchesRegularExpression('#<h1 class="fnr-title"><span class="fnr-title__ref">FR-2026-000002</span> <span class="fnr-title__kind">Fiche technique</span></h1>\s*<p class="fnr-desc">#', $page);
        foreach (['Classée sans suite', 'Information non classifiée contrôlée (CUI)', 'Source assez sûre · Information possible'] as $chip) {
            self::assertStringContainsString($chip, $page, $chip);
        }
        self::assertStringNotContainsString('fnr-chip--urgency', $page, 'une urgence de routine ne s’affiche pas en alerte');
        // Le document : bandeaux haut et bas, catégories, tampon de cotation, circonstances, texte.
        self::assertSame(2, substr_count($doc, 'INFORMATION NON CLASSIFIÉE CONTRÔLÉE (CUI)'));
        foreach (['Armement / Matériel', 'Logistique', 'Sécurité / Protection', 'FRT — Fiche technique', '24227 22393', '15193 / 17303', 'TECHINT', 'Nidasos', '01/09/2026 à 20:10', 'pick-up blanc', 'ALPHA-2'] as $text) {
            self::assertStringContainsString($text, $doc, $text);
        }
        self::assertMatchesRegularExpression('#<div class="fnr-stamp"[^>]*>\s*<strong>C3</strong>#', $doc);
        self::assertStringNotContainsString('4. Pièces jointes', $doc, 'pas de section vide dans le document');
        // États vides explicatifs, avec leur bouton.
        self::assertMatchesRegularExpression('#<strong>Aucune pièce jointe</strong>\s*<p>[^<]+</p>\s*<button type="button" class="btn" data-fnr-open="fnr-dlg-piece"#', $page);
        self::assertMatchesRegularExpression('#<strong>Aucun dossier</strong>\s*<p>[^<]+</p>\s*<button type="button" class="btn" data-fnr-open="fnr-dlg-dossier"#', $page);
        self::assertStringContainsString('Pièces <span class="fnr-count">0/4</span>', $page);
    }

    public function testLongNoteShowsGalleryCaseHistoryAndUrgency(): void
    {
        $page = self::page(self::render('b'));
        $doc = self::document($page);

        self::assertStringContainsString('class="fnr fnr--confidentiel"', $page);
        self::assertSame(2, substr_count($doc, 'SECRET (S)'), 'bandeau de classification en haut et en bas');
        self::assertStringContainsString('Urgence : Critique', $page);
        self::assertStringContainsString('Source habituellement sûre · Information probable', $page);
        self::assertGreaterThanOrEqual(24, substr_count($doc, '<br>') + substr_count($doc, '<p>'), 'le texte long est rendu ligne à ligne');
        self::assertSame(4, preg_match_all('#<li class="[^"]*">\s*<span class="fnr-annex__img#', $doc), 'quatre pièces annexées au document');
        self::assertSame(4, preg_match_all('#<figure>#', $page), 'quatre pièces dans la galerie');
        self::assertStringContainsString('Pièces <span class="fnr-count">4/4</span>', $page);
        self::assertStringNotContainsString('id="fnr-dlg-piece"', $page, 'fiche pleine : plus de fenêtre d’ajout');
        self::assertSame(4, substr_count($page, 'data-fnr-open="fnr-dlg-retirer"'));
        self::assertMatchesRegularExpression('#<a class="fnr-case" href="[^"]*atak/sse/dossiers/\d+">\s*<strong class="fnr-mono">SSE-2026-0001</strong>#', $page);
        // Historique : huit entrées, la plus récente d'abord, avec la note du changement d'état.
        preg_match('#<ol class="fnr-history">(.*?)</ol>#s', $page, $m);
        self::assertNotEmpty($m);
        self::assertSame(8, substr_count($m[1], '<li>'));
        self::assertLessThan(strpos($m[1], '12/11/2026 à 22:45'), strpos($m[1], '14/11/2026 à 08:20'));
        self::assertStringContainsString('Transmise → <strong>En exploitation</strong>', $m[1]);
        self::assertStringContainsString('Observation demandée sur le dépôt jeudi', $m[1]);
    }

    public function testLinksWidgetIsIncludedOnceOutsideTheDocument(): void
    {
        $src = self::read(self::VIEW);
        self::assertSame(1, substr_count($src, 'SseLinksWidget::render('));
        $page = self::page(self::render('b'));
        $doc = self::document($page);
        self::assertStringNotContainsString('data-sl-', $doc, 'rien ne coupe le document');
        self::assertStringNotContainsString('<form', $doc);
        self::assertStringNotContainsString('<dialog', $doc);
        preg_match('#<section class="fnr-panel" role="tabpanel" id="fnr-panel-liaisons".*?</aside>#s', $page, $m);
        self::assertNotEmpty($m, 'onglet Liaisons');
        self::assertSame(1, substr_count($m[0], 'data-sl-open'), 'le widget vit dans l’onglet Liaisons');
        self::assertSame(1, substr_count($page, 'data-sl-open'));
        self::assertMatchesRegularExpression('#Liaisons \(\d+\)#', $m[0]);
    }

    public function testTabsAreAccessibleAndEachSectionHasOneDescriptionLine(): void
    {
        $page = self::page(self::render('b'));
        self::assertSame(1, substr_count($page, '<div class="fnr-tabs" role="tablist"'), 'une barre d’onglets propre à la page');
        foreach (['essentiel', 'suivi', 'pieces', 'dossier', 'liaisons'] as $i => $tab) {
            self::assertMatchesRegularExpression('#<button type="button" role="tab" id="fnr-tab-' . $tab . '" aria-controls="fnr-panel-' . $tab . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"#', $page);
            self::assertMatchesRegularExpression('#<section class="fnr-panel" role="tabpanel" id="fnr-panel-' . $tab . '" aria-labelledby="fnr-tab-' . $tab . '" data-fnr-panel="' . $tab . '"' . ($i === 0 ? '' : ' hidden') . '>\s*<h2 class="fnr-panel__title">[^<]+(<span class="fnr-count">[^<]+</span>)?</h2>\s*<p class="fnr-desc">[^<]+</p>#', $page, $tab);
        }
        $src = self::read(self::VIEW);
        // Titre de chaque onglet et de chaque fenêtre : une ligne de description juste dessous.
        self::assertSame(5, preg_match_all('#<h2 class="fnr-panel__title">.*?</h2>\s*<p class="fnr-desc">#', $src));
        self::assertSame(4, preg_match_all('#<h2 id="fnr-dlg-[a-z]+-title">[^<]+</h2>\s*<p class="fnr-desc">#', $src));
    }

    public function testNoEyebrowNoRejectedDesignSystem(): void
    {
        $src = self::read(self::VIEW);
        $css = self::read(self::CSS);
        $js = self::read(self::JS);
        foreach ([$src, $css, $js] as $text) {
            self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline|supertitle/i', $text);
            self::assertDoesNotMatchRegularExpression('/sse-ds|Archivo/i', $text);
        }
        self::assertDoesNotMatchRegularExpression('/class="[^"]*\bsse-/', $src, 'aucune classe .sse-* dans la page');
        self::assertDoesNotMatchRegularExpression('/(^|[\s,{}])\.sse-(?!notify[ ,{])/', $css, 'styles propres, préfixe .fnr-');
        // Rien entre l'ouverture de l'en-tête et le titre que le lien de retour ; le titre du document n'a rien au-dessus de lui
        // dans son bloc.
        self::assertMatchesRegularExpression('#<div class="fnr-head__main">\s*<h1 class="fnr-title">#', $src);
        self::assertMatchesRegularExpression('#<div class="fnr-doc__headmain">\s*<h2 class="fnr-doc__title">#', $src);
        self::assertDoesNotMatchRegularExpression('#<(p|span|div|small)[^>]*>[^<]*</\1>\s*<h[1-3]#', $src, 'aucune petite étiquette au-dessus d’un titre');
        self::assertStringNotContainsString('text-transform: uppercase', $css);
        self::assertStringContainsString("asset_url('assets/css/sse-fiche-rens.css')", $src);
        self::assertStringContainsString("asset_url('assets/js/sse-fiche-rens.js')", $src);
        self::assertStringNotContainsString('sse-reading', $src . self::read('public/assets/css/sse_field_note.css'), 'ancienne feuille étroite retirée');
    }

    public function testFormsLiveInDialogsWithTheirTokenAndRoutes(): void
    {
        foreach (['a' => ['suivi' => 'suivi', 'dossier' => 'rattachement', 'piece' => 'pieces'], 'b' => ['suivi' => 'suivi', 'dossier' => 'rattachement', 'retirer' => '']] as $variant => $expected) {
            $page = self::page(self::render($variant));
            SseFieldNoteShowFixture::close();
            // Hors du widget Liaisons (qui a ses propres fenêtres), chaque formulaire est dans une fenêtre de la page.
            $own = self::own($page);
            preg_match_all('#<dialog class="fnr-dialog[^"]*" id="fnr-dlg-([a-z]+)" aria-labelledby="fnr-dlg-\1-title">(.*?)</dialog>#s', $own, $dialogs, PREG_SET_ORDER);
            self::assertSame(array_keys($expected), array_map(static fn (array $d): string => $d[1], $dialogs), 'fenêtres de la variante ' . $variant);
            $outside = (string) preg_replace('#<dialog class="fnr-dialog.*?</dialog>#s', '', $own);
            self::assertStringNotContainsString('<form', $outside, 'aucun formulaire ouvert en permanence');
            self::assertStringNotContainsString('<select', $outside);
            self::assertStringNotContainsString('type="file"', $outside);
            foreach ($dialogs as [, $name, $body]) {
                self::assertSame(1, substr_count($body, '<form method="post"'), $name);
                self::assertMatchesRegularExpression('#<input type="hidden" name="_csrf_token" value="[^"]+">#', $body, $name);
                self::assertStringContainsString('data-fnr-close aria-label="Fermer la fenêtre"', $body, $name);
                if ($expected[$name] !== '') {
                    self::assertMatchesRegularExpression('#action="[^"]*atak/sse/fiches/\d+/' . $expected[$name] . '"#', $body, $name);
                }
            }
            $by = array_column($dialogs, 2, 1);
            self::assertMatchesRegularExpression('#<select id="fiche-suivi" name="status"#', $by['suivi']);
            self::assertStringContainsString('name="triage_note" maxlength="400"', $by['suivi']);
            foreach (['brouillon', 'transmise', 'prise_en_compte', 'exploitee', 'sans_suite'] as $status) {
                self::assertStringContainsString('<option value="' . $status . '"', $by['suivi']);
            }
            self::assertMatchesRegularExpression('#<select id="fiche-dossier" name="case_id"[^>]*>\s*<option value="0">Aucun dossier</option>#', $by['dossier']);
            if (isset($by['piece'])) {
                self::assertStringContainsString('enctype="multipart/form-data"', $by['piece']);
                self::assertStringContainsString('type="file" name="pieces[]" multiple required', $by['piece']);
                self::assertStringContainsString('name="caption" maxlength="255"', $by['piece']);
            }
            if (isset($by['retirer'])) {
                // La route de suppression est posée par le bouton de la pièce choisie.
                self::assertSame(4, preg_match_all('#data-fnr-action="[^"]*atak/sse/fiches/\d+/pieces/\d+/supprimer"#', $own));
            }
        }
    }

    public function testReadOnlyReaderSeesNoWriteAction(): void
    {
        $page = self::page(self::render('c'));
        $own = self::own($page);
        foreach (['<form', '<dialog', 'data-fnr-open', '_csrf_token', 'Modifier le suivi', 'Rattacher à un dossier', 'Joindre une pièce', 'Changer de dossier', 'Retirer', 'Suivi dans Remontées', 'Rédiger une nouvelle fiche', 'fnr-history'] as $absent) {
            self::assertStringNotContainsString($absent, $own, $absent);
        }
        // Il lit tout de même le document, l'état, la note de suivi, les pièces et le dossier.
        foreach (['data-fnr-print', 'Copier la référence', 'Prise en compte', 'Observation demandée sur le dépôt jeudi', 'SSE-2026-0001', 'Copier la position'] as $present) {
            self::assertStringContainsString($present, $own, $present);
        }
        self::assertSame(4, preg_match_all('#<figure>#', $own));
        // La pièce floutée par le bureau ne s'ouvre pas en grand pour lui.
        self::assertSame(3, substr_count($own, '<a class="fnr-gallery__img"'));
        self::assertSame(1, substr_count($own, '<span class="fnr-gallery__img">'));
    }

    public function testWriterWithoutManagementOnlyAttaches(): void
    {
        $page = self::page(self::render('d'));
        self::assertStringContainsString('id="fnr-dlg-piece"', $page);
        self::assertStringContainsString('Rédiger une nouvelle fiche', $page);
        foreach (['id="fnr-dlg-suivi"', 'id="fnr-dlg-dossier"', 'Modifier le suivi', 'Rattacher à un dossier', '/suivi"', '/rattachement"', 'Suivi dans Remontées'] as $absent) {
            self::assertStringNotContainsString($absent, $page, $absent);
        }
    }

    public function testNoFictionMentionAnywhereOnThePage(): void
    {
        $pattern = '/fiction|fictif|fictive|jeu de r[ôo]le|role ?play|usage RP|\bRP\b|simulation/iu';
        self::assertDoesNotMatchRegularExpression($pattern, self::read(self::VIEW));
        foreach (['a', 'b', 'c'] as $variant) {
            // Le widget Liaisons (autre composant) cite des éléments du portail : seul le contenu propre à la page est vérifié.
            $own = self::own(self::page(self::render($variant)));
            self::assertDoesNotMatchRegularExpression($pattern, strip_tags($own), $variant);
            SseFieldNoteShowFixture::close();
        }
    }

    public function testStylesAreReadableTouchableAndPrintOnlyTheDocument(): void
    {
        $css = self::read(self::CSS);
        preg_match_all('/font-size:\s*([0-9.]+)(rem|px|em)/', $css, $sizes, PREG_SET_ORDER);
        self::assertGreaterThan(30, count($sizes));
        foreach ($sizes as [$decl, $value, $unit]) {
            $px = $unit === 'px' ? (float) $value : (float) $value * 16;
            self::assertGreaterThanOrEqual($unit === 'em' ? 15.0 : 13.0, $px, $decl);
        }
        self::assertGreaterThanOrEqual(8, substr_count($css, 'min-height: 44px'), 'cibles de 44 px');
        self::assertDoesNotMatchRegularExpression('/min-height:\s*(?:[0-9]|[1-3][0-9]|4[0-3])px/', $css);
        self::assertMatchesRegularExpression('/@media \(min-width: 1240px\) \{\s*\.fnr-layout \{ grid-template-columns: minmax\(0, 860px\)/', $css, 'deux colonnes, document en largeur de lecture');
        self::assertStringContainsString('@media screen and (max-width: 899px)', $css);
        preg_match('/@media print \{(.*)\}\s*$/s', $css, $print);
        self::assertNotEmpty($print);
        foreach (['.fnr-head', '.fnr-side', '.fnr-dialog', '.sx-topbar', '.sx-nav'] as $hidden) {
            self::assertStringContainsString($hidden, $print[1], $hidden);
        }
        self::assertStringContainsString('print-color-adjust: exact', $css, 'les bandeaux gardent leur couleur à l’impression');
        // Barre d'actions collante du téléphone : le bouton Cerbère du portail se place au-dessus (data-cw-lift).
        self::assertStringContainsString('<div class="fnr-actions" data-cw-lift>', self::read(self::VIEW));
        self::assertStringContainsString('[data-cw-lift]', self::read('public/assets/js/sse-cerbere-widget.js'));
        self::assertStringNotContainsString('.cw', $css, 'le widget Cerbère n’est pas touché');
    }

    public function testScriptHoldsNoTextAndMessagesGoThroughThePortalNotifier(): void
    {
        $js = self::read(self::JS);
        $code = (string) preg_replace('#^\s*//[^
]*$#m', '', (string) preg_replace('#/\*.*?\*/#s', '', $js));
        self::assertDoesNotMatchRegularExpression('/[àâçéèêëîïôùûœ]/iu', $code, 'aucun texte français dans le script');
        self::assertDoesNotMatchRegularExpression('/\b(alert|confirm|prompt)\s*\(/', $code);
        self::assertStringContainsString('window.SseNotify', $code);
        self::assertStringContainsString('showModal', $code);
        $src = self::read(self::VIEW);
        self::assertStringNotContainsString('onsubmit=', $src);
        self::assertStringNotContainsString('onclick=', $src);
        self::assertStringNotContainsString('<script>', $src);
        foreach (['data-fnr-copied="', 'data-fnr-copy-failed="', 'data-fnr-files-message="'] as $label) {
            self::assertStringContainsString($label, $src, $label);
        }
    }

    public function testTimelineKeepsInternalCommentsForTheBackOfficeOnly(): void
    {
        $events = [
            ['kind' => 'state', 'actor' => 'Analyste Sept', 'body' => 'Recoupement en cours.', 'meta' => ['from' => 'a_traiter', 'to' => 'en_cours'], 'at' => '2026-11-12 22:45:00'],
            ['kind' => 'comment', 'actor' => 'Analyste Sept', 'body' => 'Note interne.', 'meta' => [], 'at' => '2026-11-12 23:10:00'],
            ['kind' => 'edit', 'actor' => '', 'body' => 'ne sort pas', 'meta' => [], 'at' => '2026-11-13 07:34:00'],
            ['kind' => 'inconnu', 'actor' => 'X', 'body' => '', 'meta' => [], 'at' => '2026-11-13 08:00:00'],
        ];
        $public = SseFieldNoteTimeline::fromEvents($events, false);
        self::assertCount(2, $public);
        self::assertSame('13/11/2026 à 07:34', $public[0]['at']);
        self::assertSame('Système', $public[0]['actor']);
        self::assertSame('', $public[0]['body'], 'seuls l’état et le commentaire portent un texte');
        self::assertSame(['À traiter', 'En exploitation', 'Recoupement en cours.'], [$public[1]['from'], $public[1]['to'], $public[1]['body']]);
        $full = SseFieldNoteTimeline::fromEvents($events, true);
        self::assertCount(3, $full);
        self::assertSame('Note interne.', $full[1]['body']);
        self::assertSame([], SseFieldNoteTimeline::fromEvents([], true));
        // Le contrôleur ne lit l'historique que pour qui gère les fiches, et les commentaires que pour le back-office.
        $controller = self::read('app/Controllers/Web/SseFieldNoteController.php');
        self::assertMatchesRegularExpression('/if \(\$this->canManage\(\)\) \{\s*try \{\s*\$history = \\\\App\\\\Support\\\\SseFieldNoteTimeline::fromEvents\(\s*\$this->intake\(\)->events\(\$tenantId, \'fiche\', \(int\) \$note\[\'id\'\]\),\s*\\\\App\\\\Controllers\\\\Admin\\\\AdminIntelIntakeController::allowed\(\)/', $controller);
    }

    public function testEnglishTranslationsCoverThePage(): void
    {
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['Modifier le suivi', 'Joindre une pièce', 'Imprimer / PDF', 'File des fiches', 'Aucune pièce jointe', 'Constat du %1 à %2, rédigé par %3.', 'Retirer cette pièce jointe ?', '1. Circonstances', 'Urgence : %1'] as $key) {
            self::assertArrayHasKey($key, $en, $key);
            self::assertNotSame($key, $en[$key]);
        }
    }
}
