<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\Attach\SseAttachWindow;
use App\Support\SseAttachCatalog as A;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAttachFixture;
use Tests\Support\SseAttachSqlite;

/**
 * Mise en place de la fenêtre « Rattacher » : le même composant sur chaque page inventoriée (une ligne d'inclusion, un bouton
 * data-ra-open), les anciens petits formulaires retirés, les anciennes routes gardées, la migration idempotente et compatible
 * sqlite, et les règles d'interface (style du portail, aucun libellé au-dessus d'un titre, pas de design system « SSE DS »,
 * textes de 13 px au moins, cibles de 44 px, aucun texte dans le script).
 *
 * Les fichiers sont lus tels quels puis ramenés à des fins de ligne « \n » : aucun contrôle ne dépend du checkout.
 */
final class SseAttachWiringTest extends TestCase
{
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Pages d'élément : la section « Éléments rattachés » (et son bouton « Rattacher à… ») en une ligne. */
    public function testEveryElementPageIncludesTheAttachedSectionInOneLine(): void
    {
        $pages = [
            'views/atak/sse/case_show.php' => 'case', 'views/atak/sse/interest_case_show.php' => 'interest', 'views/atak/sse/person_show.php' => 'person',
            'views/atak/sse/papers/identity_show.php' => 'identity', 'views/atak/sse/papers/document_show.php' => 'paper', 'views/atak/sse/papers/check_show.php' => 'check',
            'views/atak/sse/document_show.php' => 'document', 'views/atak/sse/field_note_show.php' => 'note', 'views/atak/sse/transmission_show.php' => 'transmission',
            'views/atak/sse/site_show.php' => 'site', 'views/atak/sse/campaign_show.php' => 'campaign', 'views/atak/sse/mesh_show.php' => 'mesh', 'views/atak/sse/lead_show.php' => 'lead',
        ];
        foreach ($pages as $file => $type) {
            $src = self::read($file);
            self::assertSame(1, preg_match_all("~^\\s*<\\?php \\\\App\\\\Services\\\\Sse\\\\Attach\\\\SseAttachWindow::section\\('" . $type . "', [^\\n]+\\); \\?>$~m", $src), $file);
        }
    }

    /** Listes et formulaires : un bouton data-ra-open et la fenêtre rendue une fois, hors de tout formulaire. */
    public function testListsOpenTheSameWindowAndOldSmallFormsAreGone(): void
    {
        $note = self::read('views/atak/sse/field_note_show.php');
        self::assertSame(3, substr_count($note, 'data-ra-open data-ra-pieces="note:<?= (int) $noteId ?>"'));
        self::assertStringNotContainsString('fnr-dlg-dossier', $note);
        self::assertStringNotContainsString('name="case_id"', $note);
        self::assertStringNotContainsString('Aucun dossier</option>', $note);
        self::assertStringContainsString('SseAttachWindow::render()', $note);

        $persons = self::read('views/atak/sse/persons/_content.php');
        self::assertStringContainsString('data-ra-open data-ra-type="person" data-ra-collect=".pe-check input:checked"', $persons);
        self::assertStringNotContainsString('data-pe-act="link"', $persons);
        self::assertStringNotContainsString('pe-dlg-link', self::read('views/atak/sse/persons/_dialogs.php'));

        $photos = self::read('views/atak/sse/files/photobook.php');
        self::assertStringContainsString('data-ra-open data-ra-pieces="photo:<?= (int) $ph[\'id\'] ?>"', $photos);
        self::assertStringNotContainsString('placeholder="IDT-…" aria-label="Fiche à rattacher"', $photos);

        self::assertStringContainsString('data-ra-open data-ra-pieces="notice:<?= (int) $n[\'id\'] ?>"', self::read('views/atak/sse/papers/wanted_list.php'));
        self::assertStringContainsString('data-ra-open data-ra-pieces="<?= $h($it[\'subject_type\'] . \':\' . (int) $it[\'subject_id\']) ?>"', self::read('views/atak/sse/triage.php'));
        self::assertStringContainsString("SseAttachWindow::button('document', (int) \$document['id']", self::read('views/atak/sse/document_form.php'));
        foreach (['views/atak/sse/papers/wanted_list.php', 'views/atak/sse/triage.php', 'views/atak/sse/files/photobook.php', 'views/atak/sse/persons/_content.php', 'views/atak/sse/document_form.php'] as $file) {
            self::assertStringContainsString('SseAttachWindow::render()', self::read($file), $file);
        }
    }

    public function testOldRoutesStayAsAliasesAndNewRoutesAreDeclared(): void
    {
        $routes = self::read('routes/web.php');
        foreach (["post('/atak/sse/fiches/{id}/rattachement'", "post('/atak/sse/fichiers/photos/{id}/rattacher'", "post('/atak/sse/liaisons'", "post('/atak/sse/interet/{id}/liens'"] as $old) {
            self::assertStringContainsString($old, $routes, $old);
        }
        $expected = [
            "get('/atak/sse/rattacher/contexte'" => 'context', "get('/atak/sse/rattacher/cibles'" => 'targets', "get('/atak/sse/rattacher/cible'" => 'target',
            "post('/atak/sse/rattacher/apercu'" => 'preview', "post('/atak/sse/rattacher/annuler'" => 'undo', "post('/atak/sse/rattacher/creer'" => 'create',
            "post('/atak/sse/rattacher/aligner'" => 'align', "post('/atak/sse/rattacher/reglage'" => 'setting', "post('/atak/sse/rattacher/{id}/modifier'" => 'update',
            "post('/atak/sse/rattacher/{id}/detacher'" => 'detach', "post('/atak/sse/rattacher/{id}/deplacer'" => 'move', "post('/atak/sse/rattacher'" => 'store',
            "get('/atak/sse/pistes'" => 'leads', "post('/atak/sse/pistes'" => 'leadStore', "get('/atak/sse/pistes/{id}'" => 'leadShow', "post('/atak/sse/pistes/{id}'" => 'leadUpdate',
        ];
        foreach ($expected as $route => $method) {
            self::assertStringContainsString($route . ", [\\App\\Controllers\\Web\\SseAttachController::class, '" . $method . "'], \$mwSsePortal);", $routes, $route);
            self::assertTrue(method_exists(\App\Controllers\Web\SseAttachController::class, $method), $method);
        }
        // Les routes fixes passent avant « {id} » : « /rattacher/apercu » n'est jamais pris pour un identifiant.
        self::assertLessThan(strpos($routes, "post('/atak/sse/rattacher/{id}/modifier'"), strpos($routes, "post('/atak/sse/rattacher/apercu'"));
        self::assertSame(16, substr_count($routes, 'SseAttachController::class'));
        self::assertSame('dossiers', \App\Support\SseAccessCatalog::sectionForPath('/atak/sse/pistes/3'));
    }

    public function testMigrationIsIdempotentSqliteCompatibleAndRegistered(): void
    {
        $sql = self::read('migrations/2026_11_19_100_sse_attachments.sql');
        self::assertSame(3, substr_count($sql, 'CREATE TABLE IF NOT EXISTS'));
        foreach (A::LEVELS as $level) {
            self::assertStringContainsString($level, $sql);
        }
        self::assertStringNotContainsString('ALTER TABLE', $sql, 'les unicités sont dans les CREATE TABLE');
        foreach (['UNIQUE KEY uq_sse_link_attachment (tenant_id, link_id)', 'UNIQUE KEY uq_sse_attach_tag (tenant_id, slug)', 'UNIQUE KEY uq_sse_lead_ref (tenant_id, reference_code)'] as $unique) {
            self::assertStringContainsString($unique, $sql);
        }
        $step = self::read('database/steps/4840_sse_attachments.php');
        self::assertStringContainsString("runSqlFile(\$root . '/migrations/2026_11_19_100_sse_attachments.sql')", $step);
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString("'file' => '4840_sse_attachments.php'", self::read('database/steps/registry.php'));
        $down = self::read('migrations/rollback/sse_attachments_down.sql');
        foreach (SseAttachSqlite::TABLES as $table) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table . ';', $down);
        }
        self::assertStringNotContainsString('sse_entity_links;', $down, 'le retour arrière ne touche pas aux liens');
        if (!extension_loaded('pdo_sqlite')) {
            return;
        }
        // Le vrai fichier SQL se crée sur sqlite (base non migrée au départ), avec ses unicités.
        $pdo = SseAttachSqlite::open(false);
        SseAttachSqlite::createAttachTables($pdo);
        self::assertTrue(\App\Repositories\SseAttachmentRepository::ready());
        $pdo->exec("INSERT INTO sse_attach_tags (tenant_id, name, slug, color, created_at) VALUES (1, 'A', 'a', 'slate', '2026-11-01')");
        $dup = false;
        try {
            $pdo->exec("INSERT INTO sse_attach_tags (tenant_id, name, slug, color, created_at) VALUES (1, 'A bis', 'a', 'slate', '2026-11-01')");
        } catch (\Throwable) {
            $dup = true;
        }
        self::assertTrue($dup, 'unicité de l’étiquette par communauté');
        $pdo->exec("INSERT INTO sse_attach_tags (tenant_id, name, slug, color, created_at) VALUES (2, 'A', 'a', 'slate', '2026-11-01')");
        SseAttachSqlite::close();
    }

    public function testWindowAndSectionRenderWithThePortalStyleAndNoEyebrow(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $saved = $_SESSION ?? null;
        $_SESSION = ['user_id' => 22, 'tenant_id' => 1, '_csrf_token' => 'jeton', 'sse_tenant_id' => 1];
        $pdo = SseAttachSqlite::open();
        $ids = SseAttachFixture::seed($pdo);
        try {
            SseAttachWindow::useViewer(SseAttachFixture::operator());
            $section = (string) SseAttachWindow::section('case', $ids['case'], ['return' => true, 'back' => 'atak/sse/dossiers/' . $ids['case']]);
            $again = (string) SseAttachWindow::section('lead', $ids['lead1'], ['return' => true]);
            $button = SseAttachWindow::button('note', $ids['note_free']);
            $window = $section;
        } finally {
            SseAttachWindow::useViewer(null);
            SseAttachSqlite::close();
            if ($saved === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $saved;
            }
        }
        // La fenêtre n'est rendue qu'une fois par page, quel que soit le nombre de sections et de boutons.
        self::assertSame(1, substr_count($section, 'id="ra-window"'));
        self::assertSame(0, substr_count($again, 'id="ra-window"'));
        self::assertStringNotContainsString('id="ra-window"', $button);
        self::assertStringContainsString('data-ra-open data-ra-pieces="note:' . $ids['note_free'] . '"', $button);
        self::assertSame('', SseAttachWindow::button('campaign', 1), 'une campagne n’est pas une pièce');

        // Fenêtre : dialogue modal nommé, étapes, périmètres, recherche, tri, filtres, réglages, alignement, récapitulatif.
        foreach (['role="dialog" aria-modal="true" aria-labelledby="ra-title" aria-describedby="ra-lead"', 'data-ra-scopes', 'data-ra-q', 'data-ra-sort', 'data-ra-filter="status"',
            'data-ra-filter="class"', 'data-ra-filter="tag"', 'data-ra-filter="mine"', 'Proposés par Cerbère', 'Récents', 'Afficher la suite', 'name="nature"', 'name="label"', 'name="reason"',
            'name="note"', 'data-ra-tag-input', 'Aligner la confidentialité de la pièce sur celle de la cible', 'data-ra-recap-rows', 'data-ra-undo-bar', 'Créer et sélectionner',
            'value="relevance"', 'value="recent"', 'value="alpha"', 'value="class"', 'value="linked"', 'name="_csrf_token"'] as $needle) {
            self::assertStringContainsString($needle, $window, $needle);
        }
        foreach (array_keys(A::NATURES) as $nature) {
            self::assertStringContainsString('<option value="' . $nature . '">', $window);
        }
        // Section : pièce nommée par son libellé, motif, auteur, actions, élément restreint sans fuite.
        foreach (['Éléments rattachés', 'Observation du pont (nuit du 9)', 'Recueilli dans le cadre de cette affaire', 'Analyste Sept', 'Modifier le rattachement', 'Déplacer vers une autre cible',
            'Détacher', 'data-ras-filter="type"', 'data-ras-filter="nature"', 'data-ras-filter="tag"', 'data-ras-sort', '1 élément(s) restreint(s)', '/ SSE-2026-0001'] as $needle) {
            self::assertStringContainsString($needle, $section, $needle);
        }
        self::assertStringNotContainsString('Source protégée au port', $section);
        self::assertStringNotContainsString('FR-2026-0011', $section);

        foreach ([$window, $again] as $html) {
            // Aucun libellé au-dessus d'un titre : un titre n'est jamais précédé d'un petit paragraphe d'étiquette.
            self::assertSame(0, preg_match('~<(p|span|small)[^>]*class="[^"]*(eyebrow|kicker|overline|surtitre)[^"]*"~i', $html));
            self::assertSame(0, preg_match('~</(p|span|small)>\s*<h[1-4]\b~', $html), 'aucun texte ne précède un titre dans son bloc');
            // Pas le design system « SSE DS », ni sa police.
            self::assertSame(0, preg_match('~class="[^"]*\bsse-(?!notify)[a-z]~', $html));
            self::assertStringNotContainsString('sse-ds', $html);
            self::assertStringNotContainsString('Archivo', $html);
            foreach (['fiction', 'roleplay', 'simulation'] as $word) {
                self::assertSame(false, stripos($html, $word), $word);
            }
        }
    }

    public function testAssetsFollowTheInterfaceRules(): void
    {
        $css = self::read('public/assets/css/sse-rattacher.css');
        self::assertStringNotContainsString('Archivo', $css);
        self::assertStringNotContainsString('.sse-', $css);
        self::assertStringNotContainsString('@import', $css);
        foreach (['var(--bg', 'var(--surface', 'var(--border', 'var(--text-strong', 'var(--green-bright'] as $token) {
            self::assertStringContainsString($token, $css, $token);
        }
        // Tous les sélecteurs sont préfixés : rien ne déborde sur le reste du portail.
        preg_match_all('~^([^\s@}/{][^{]*)\{~m', $css, $m);
        foreach ($m[1] as $selectors) {
            foreach (explode(',', (string) preg_replace('~:is\([^)]*\)~', ':is()', $selectors)) as $selector) {
                $selector = trim($selector);
                if ($selector === '' || $selector === 'from' || $selector === 'to') {
                    continue;
                }
                self::assertSame(1, preg_match('~^(html\.ra-lock|\.ra[\s.:\[-]|\.ras[\s.:\[-]|\.ra$|\.ras$)~', $selector), 'sélecteur hors préfixe : ' . $selector);
            }
        }
        // Textes de 13 px au moins (0,8125 rem), cibles tactiles de 44 px, animations coupées si l'utilisateur le demande.
        preg_match_all('~font-size:\s*([0-9.]+)(rem|px)~', $css, $sizes, PREG_SET_ORDER);
        foreach ($sizes as $s) {
            $px = $s[2] === 'rem' ? (float) $s[1] * 16 : (float) $s[1];
            if ((float) $s[1] === 0.75) {
                continue; // pastille de compteur (chiffre seul)
            }
            self::assertGreaterThanOrEqual(13.0, $px, $s[0]);
        }
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        self::assertStringContainsString('@media (max-width: 720px)', $css);

        // Le script ne porte aucun texte : les libellés viennent de la vue (traduits avec la page).
        $js = self::read('public/assets/js/sse-rattacher.js');
        $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $js);
        self::assertSame(0, preg_match('~[\'"][^\'"\n]*[éèêàùçôîÉÀ][^\'"\n]*[\'"]~u', $code), 'aucun texte français dans le script');
        self::assertStringContainsString('try {', $js);
        self::assertStringContainsString('window.localStorage', $js);
        self::assertStringContainsString("e.key === 'Escape'", $js);
        self::assertStringContainsString("e.key === 'Tab'", $js);
        self::assertStringContainsString("'_csrf_token'", $js);
        $view = self::read('views/atak/sse/_attach_window.php');
        self::assertStringContainsString('assets/js/sse-rattacher.js', $view);
        self::assertStringContainsString('assets/css/sse-rattacher.css', $view);
        self::assertStringNotContainsString('<style', $view);
    }

    public function testCatalogLimitsAndDocumentation(): void
    {
        self::assertSame(15, A::UNDO_SECONDS);
        self::assertSame(['interne', 'encadrement', 'confidentiel', 'tres_restreint'], A::LEVELS);
        self::assertSame(['CUI', 'C', 'S', 'TS'], array_map(static fn (string $l): string => A::levelAbbr($l), A::LEVELS));
        self::assertSame('elodie karimzadeh', A::fold('  Élodie   KARIMZADEH '));
        self::assertSame('piece-a-conviction', A::tagSlug('Pièce à conviction'));
        $doc = self::read('docs/sse-rattacher.md');
        foreach (['sse_link_attachments', 'sse_attach_tags', 'sse_leads', '4840', 'SseAttachWindow::section', 'data-ra-open', 'ne peut que relever'] as $needle) {
            self::assertStringContainsString($needle, $doc, $needle);
        }
    }
}
