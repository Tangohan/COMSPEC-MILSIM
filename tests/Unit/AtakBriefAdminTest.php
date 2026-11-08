<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAtakBriefController;
use App\Controllers\Admin\AdminAtakConfigController;
use App\Core\Request;
use App\Core\Response;
use App\Services\Tactical\AtakCommunityBriefService as Service;
use App\Services\Tactical\AtakExperienceService;
use App\Support\AtakCommunityBrief as Brief;
use PHPUnit\Framework\TestCase;

/**
 * Consignes et kit ATAK (back-office) et guide du joueur : le texte enregistré reste celui que lit le mod 4.0.0.
 */
final class AtakBriefAdminTest extends TestCase
{
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** @param array<string, mixed> $post */
    private static function request(array $post): Request
    {
        $_POST = $post;
        $request = new Request();
        $_POST = [];

        return $request;
    }

    /**
     * Non-régression : le formulaire Expérience de Configuration ATAK n'efface plus les consignes ni le kit.
     * Avant, un champ absent valait « » et mergeSettings écrivait ce texte vide (clé reçue).
     */
    public function testFormulaireExperienceNEffacePasLesConsignesNiLeKit(): void
    {
        $svc = new AtakExperienceService();
        $saved = $svc->mergeSettings([], ['community_notes' => 'ordre | A | B', 'community_kit' => 'ItemMap*2 : Carte']);

        $incoming = AdminAtakConfigController::experienceFormInput($svc, self::request(['experience_realism' => '1']));
        self::assertArrayNotHasKey('community_notes', $incoming);
        self::assertArrayNotHasKey('community_kit', $incoming);
        $merged = $svc->mergeSettings($saved, $incoming);
        self::assertSame('ordre | A | B', $merged['community_notes']);
        self::assertSame('ItemMap*2 : Carte', $merged['community_kit']);
        self::assertTrue($merged['realism']);

        // Page restée ouverte depuis l'ancienne version : ses champs vides sont ignorés.
        $old = AdminAtakConfigController::experienceFormInput($svc, self::request(['experience_community_notes' => '', 'experience_community_kit' => '']));
        $kept = $svc->mergeSettings($saved, $old);
        self::assertSame('ordre | A | B', $kept['community_notes']);
        self::assertSame('ItemMap*2 : Carte', $kept['community_kit']);
    }

    public function testNoteRelueALIdentiqueParLeMod(): void
    {
        $note = Brief::validNote('ordre', ' Départ ', "H-10 au hangar\r\nCanal 2");
        self::assertSame(['kind' => 'ordre', 'title' => 'Départ', 'body' => 'H-10 au hangar Canal 2'], $note);
        self::assertSame([$note], Brief::parseNotes(Brief::serializeNotes([$note])));

        // Un séparateur de ligne Unicode créerait une note de plus.
        self::assertSame('a b', Brief::validNote('tip', 'T', "a\u{2028}b")['body']);

        // Texte seul, titre seul, genre écrit en titre, | dans le texte : relus sans glissement.
        $notes = [
            Brief::validNote('consigne', '', 'seul | avec barre'),
            Brief::validNote('tip', 'Titre seul', ''),
            Brief::validNote('consigne', 'ordre', 'corps'),
            Brief::validNote('consigne', 'H < 10', 'x > y'),
        ];
        self::assertSame($notes, Brief::parseNotes(Brief::serializeNotes($notes)));
        self::assertSame('seul | avec barre', $notes[0]['body']);
    }

    public function testNoteRefuseeSansRienCouperEnSilence(): void
    {
        $bad = [
            ['autre', 'T', 'x'],
            ['tip', '', '  '],
            ['tip', 'a|b', 'x'],
            ['tip', 'T', 'a<b suite perdue'],
            ['tip', '<b>T</b>', 'x'],
            ['tip', str_repeat('é', Brief::MAX_TITLE + 1), 'x'],
            ['tip', 'T', str_repeat('é', Brief::MAX_BODY + 1)],
            ['tip', 'T', str_repeat('a|', 300)],
            ['tip', "T\xC3", 'x'],
        ];
        foreach ($bad as [$kind, $title, $body]) {
            try {
                Brief::validNote($kind, $title, $body);
                self::fail('Devrait être refusée : ' . $title . ' / ' . $body);
            } catch (\DomainException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
        self::assertSame(Brief::MAX_BODY, mb_strlen(Brief::validNote('tip', 'T', str_repeat('é', Brief::MAX_BODY))['body']));
    }

    public function testKitReluALIdentiqueParLeMod(): void
    {
        $kit = [
            Brief::validKitEntry(['ACE_fieldDressing', 'ACE_elasticBandage'], 2, 'Pansements : deux'),
            Brief::validKitEntry(['ItemMap'], 1, ''),
            Brief::validKitEntry(['ItemGPS', 'itemgps', 'ItemGPS'], 99, ''),
        ];
        self::assertSame($kit, Brief::parseKit(Brief::serializeKit($kit)));
        self::assertSame("ACE_fieldDressing/ACE_elasticBandage*2 : Pansements : deux\nItemMap\nItemGPS*99", Brief::serializeKit($kit));
        self::assertSame(['ItemGPS'], $kit[2]['classes']);

        // Forme exacte de l'API lue par GetCommunityBrief.
        $payload = Brief::payload(['community_notes' => 'ordre | A | B', 'community_kit' => Brief::serializeKit($kit)], '2026-11-08T10:00:00+00:00');
        self::assertSame(['classes', 'count', 'label'], array_keys($payload['kit'][0]));
        self::assertSame(['id', 'kind', 'title', 'body', 'updated_at'], array_keys($payload['notes'][0]));
    }

    public function testKitRefuseLesSaisiesQuiCasseraientLeTexte(): void
    {
        $bad = [
            [[], 1, ''],
            [['pas une classe'], 1, ''],
            [['a/b'], 1, ''],
            [['../etc'], 1, ''],
            [[str_repeat('x', 65)], 1, ''],
            [['ItemMap'], 0, ''],
            [['ItemMap'], Brief::MAX_COUNT + 1, ''],
            // Une virgule créerait une entrée du kit de plus (« ItemMap : A,ItemGPS »).
            [['ItemMap'], 1, 'A,ItemGPS'],
            [['ItemMap'], 1, 'A;B'],
            [['ItemMap'], 1, str_repeat('é', Brief::MAX_TITLE + 1)],
            [array_map(static fn (int $i): string => 'Item' . $i, range(0, Brief::MAX_VARIANTS)), 1, ''],
        ];
        foreach ($bad as [$classes, $count, $label]) {
            try {
                Brief::validKitEntry($classes, $count, $label);
                self::fail('Devrait être refusée : ' . json_encode([$classes, $count, $label]));
            } catch (\DomainException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testClassesVenantDuFormulaire(): void
    {
        self::assertSame(
            ['ItemMap', 'ItemGPS', 'ItemCompass', 'ItemWatch'],
            AdminAtakBriefController::classesFrom(self::request(['class_pick' => 'ItemMap', 'variants' => ['ItemGPS'], 'variants_manual' => 'ItemCompass, ItemWatch'])),
        );
        // La saisie avancée passe avant le choix, et un tableau là où on attend un texte ne plante pas.
        self::assertSame(['Manuel'], AdminAtakBriefController::classesFrom(self::request(['class_pick' => 'ItemMap', 'class_manual' => ' Manuel '])));
        self::assertSame([], AdminAtakBriefController::classesFrom(self::request(['class_pick' => ['x'], 'variants' => 'y'])));
    }

    public function testCeQueLeJeuNeLitPasEstSignale(): void
    {
        $notes = str_repeat("une note\n", Brief::MAX_NOTES + 2) . 'tip | ' . str_repeat('t', Brief::MAX_TITLE + 1) . ' | x';
        $unread = Brief::unread($notes, "ItemMap\npas une classe*2, ../x\nItemGPS");
        self::assertSame(3, $unread['notes_over']);
        self::assertSame(['pas une classe*2', '../x'], $unread['kit']);

        $cut = Brief::unread('tip | ' . str_repeat('t', Brief::MAX_TITLE + 1) . ' | x', '');
        self::assertSame(1, $cut['notes_cut']);
        self::assertSame(['notes_over' => 0, 'notes_cut' => 0, 'kit' => []], Brief::unread("ordre | A | B\n", "ItemMap*2 : Carte, ItemGPS\n"));
    }

    /** La DLL coupe à 7 996 octets, à la ligne près, notes d'abord : le kit peut disparaître sans prévenir. */
    public function testVolumeRecuParLeTelephone(): void
    {
        $note = Brief::validNote('consigne', 'Titre', str_repeat('é', 500));
        $kit = [Brief::validKitEntry(['ItemMap'], 1, '')];

        $small = Brief::phoneFit([$note], $kit);
        self::assertTrue($small['fits']);
        self::assertSame(1, $small['notes_kept']);
        self::assertSame(1, $small['kit_kept']);
        self::assertSame(strlen("N\tconsigne\tTitre\t" . str_repeat('é', 500) . "\nK\tItemMap\t1\t "), $small['bytes']);

        $full = Brief::phoneFit(array_fill(0, 10, $note), $kit);
        self::assertFalse($full['fits']);
        self::assertSame(7, $full['notes_kept']);
        self::assertSame(0, $full['kit_kept']);
    }

    public function testListeModifieeParPlace(): void
    {
        $a = ['kind' => 'ordre', 'title' => 'A', 'body' => ''];
        $b = ['kind' => 'tip', 'title' => 'B', 'body' => ''];
        $c = ['kind' => 'ordre', 'title' => 'C', 'body' => ''];

        self::assertSame([$a, $b, $c], Service::put([$a, $b], null, $c, 3, 'plein'));
        self::assertSame([$c, $b], Service::put([$a, $b], 0, $c, 3, 'plein'));
        self::assertSame([$a, $c], Service::remove([$a, $b, $c], 1));

        self::assertSame([[$b, $a, $c], 1], Service::move([$a, $b, $c], 0, 1));
        // Filtre « ordre » : la note saute la note masquée, sinon le clic semblerait sans effet.
        self::assertSame([[$c, $b, $a], 2], Service::move([$a, $b, $c], 0, 1, 'ordre'));

        foreach ([
            static fn () => Service::put([$a, $b], null, $c, 2, 'plein'),
            static fn () => Service::put([$a], 5, $c, 3, 'plein'),
            static fn () => Service::remove([$a], -1),
            static fn () => Service::move([$a, $b], 0, -1),
            static fn () => Service::move([$a, $b], 1, 1),
            static fn () => Service::move([$a, $b], 0, 1, 'ordre'),
        ] as $refused) {
            try {
                $refused();
                self::fail('Devrait être refusé.');
            } catch (\DomainException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testObjetEnDoubleRefuse(): void
    {
        $kit = [Brief::validKitEntry(['ItemMap', 'ItemGPS'], 1, '')];
        Service::assertNoDuplicate($kit, ['ItemWatch'], null);
        Service::assertNoDuplicate($kit, ['itemmap'], 0);
        $this->expectException(\DomainException::class);
        Service::assertNoDuplicate($kit, ['itemgps'], null);
    }

    public function testEcrituresEtDroits(): void
    {
        $routes = self::read('routes/web.php');
        foreach ([
            "get('/back-office/ressources/atak-consignes', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'index']",
            "get('/back-office/ressources/atak-consignes/catalogue', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'catalogue']",
            "post('/back-office/ressources/atak-consignes/notes', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'saveNote']",
            "post('/back-office/ressources/atak-consignes/notes/supprimer', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'deleteNote']",
            "post('/back-office/ressources/atak-consignes/notes/deplacer', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'moveNote']",
            "post('/back-office/ressources/atak-consignes/kit', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'saveKit']",
            "post('/back-office/ressources/atak-consignes/kit/supprimer', [\\App\\Controllers\\Admin\\AdminAtakBriefController::class, 'deleteKit']",
        ] as $route) {
            self::assertStringContainsString($route . ", [AuthMiddleware::class, TenantResourceAdminMiddleware::class]);", $routes);
        }

        $controller = self::read('app/Controllers/Admin/AdminAtakBriefController.php');
        self::assertStringContainsString('Csrf::verify($request)', $controller);
        self::assertStringContainsString("Session::get('tenant_id')", $controller);
        self::assertStringNotContainsString("input('tenant", $controller);

        $container = self::read('app/Core/Container.php');
        self::assertStringContainsString('AdminAtakBriefController::class => new', $container);
        self::assertStringContainsString('AtakCommunityBriefService::class => new', $container);

        // Mêmes droits que Configuration ATAK, juste à côté dans le menu.
        $nav = self::read('config/navigation.php');
        self::assertMatchesRegularExpression("~'path' => 'back-office/ressources/atak-config', 'any_permissions' => \\['admin.system', 'admin.organization', 'admin.access'\\]\\],\n\\s+\\['label' => 'Consignes et kit ATAK', 'path' => 'back-office/ressources/atak-consignes', 'any_permissions' => \\['admin.system', 'admin.organization', 'admin.access'\\]~", $nav);
    }

    public function testPageSansConfirmEtAvecRepli(): void
    {
        $js = self::read('public/assets/js/back-office-atak-brief.js');
        self::assertStringNotContainsString('confirm(', $js);
        self::assertStringNotContainsString('location.reload', $js);
        self::assertStringContainsString("json.ok !== true", $js);
        self::assertStringContainsString("indexOf('application/json') === -1", $js);

        $view = self::read('views/admin/atak-brief/_live.php') . self::read('views/admin/atak-brief/index.php');
        self::assertStringNotContainsString('$nav', $view);
        self::assertStringNotContainsString('data-ath-modal', $view);
        self::assertStringNotContainsString('onclick', $view);

        $config = self::read('views/admin/atak-config/index.php');
        self::assertStringNotContainsString('experience_community_', $config);
        self::assertStringContainsString("url('back-office/ressources/atak-consignes')", $config);
        self::assertStringContainsString('name="experience_guide_custom"', $config);
    }

    /** @param array<string, mixed> $form */
    private static function live(array $form): string
    {
        return Response::view('admin.atak-brief._live', ['form' => $form + [
            'ready' => true, 'genre' => '', 'notes' => [], 'kit' => [], 'notesRev' => 'r1', 'kitRev' => 'r2', 'updatedAt' => '', 'guide' => '',
            'unread' => ['notes_over' => 0, 'notes_cut' => 0, 'kit' => []],
            'fit' => ['bytes' => 0, 'fits' => true, 'notes_kept' => 0, 'kit_kept' => 0],
            'catalogSize' => 3, 'q' => '', 'picks' => [],
        ]])->body();
    }

    public function testAffichageEchappeEtExpliqueLeCatalogueVide(): void
    {
        $item = ['class' => 'Item<x>', 'name' => '<img src=x onerror=1>', 'kind' => 'item', 'kindLabel' => 'Objet', 'mod' => '<i>mod</i>', 'icon' => '', 'known' => false];
        $html = self::live([
            'notes' => [['kind' => 'ordre', 'title' => '<script>t</script>', 'body' => 'contact@exemple.fr & "x"']],
            'kit' => [['classes' => ['ItemMap'], 'count' => 2, 'label' => '<b>l</b>', 'items' => [$item]]],
            'fit' => ['bytes' => 10, 'fits' => true, 'notes_kept' => 1, 'kit_kept' => 1],
            'picks' => [$item],
        ]);

        self::assertStringNotContainsString('<script>t', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringNotContainsString('<b>l</b>', $html);
        self::assertStringNotContainsString('<i>mod', $html);
        self::assertStringContainsString('<b translate="no">&lt;script&gt;t&lt;/script&gt;</b>', $html);
        // Une adresse e-mail reste entière dans le formulaire : elle serait sinon enregistrée amputée.
        self::assertStringContainsString('contact@exemple.fr &amp; &quot;x&quot;</textarea>', $html);
        self::assertStringContainsString('name="note_body"', $html);
        self::assertStringContainsString('name="note_title"', $html);
        self::assertStringContainsString('name="class_manual"', $html);
        self::assertStringContainsString('Pas encore vu en jeu', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringContainsString('value="r1"', $html);
        self::assertStringContainsString('value="r2"', $html);
        self::assertStringNotContainsString('catalogue de votre communauté est vide', $html);

        $empty = self::live(['catalogSize' => 0]);
        self::assertStringContainsString('catalogue de votre communauté est vide', $empty);
        self::assertStringContainsString('option avancée', $empty);
        self::assertStringContainsString('<details class="bo-brief-adv" open>', $empty);
    }

    public function testFiltreEtDeplacementSuiventLeGenre(): void
    {
        $html = self::live([
            'genre' => 'tip',
            'notes' => [
                ['kind' => 'ordre', 'title' => 'Masqué', 'body' => ''],
                ['kind' => 'tip', 'title' => 'Premier', 'body' => ''],
                ['kind' => 'tip', 'title' => 'Second', 'body' => ''],
            ],
        ]);
        self::assertStringNotContainsString('id="bo-brief-note-0"', $html);
        self::assertStringContainsString('id="bo-brief-note-1"', $html);
        self::assertStringContainsString('name="genre" value="tip"', $html);
        // Premier de son genre : on ne peut pas le monter, même si une note masquée est au-dessus.
        self::assertMatchesRegularExpression('~value="1">\s*<input type="hidden" name="genre" value="tip">\s*<button[^>]*value="up"[^>]*disabled~', $html);
    }

    /** @return list<array<string, string|bool>> */
    private static function guideCases(): array
    {
        $rules = ['screen_notifications' => 'on', 'vehicle_detail' => 'off', 'require_equipment' => 'on', 'show_opfor' => 'off', 'show_independent' => 'on', 'show_civilian' => 'off'];

        return [[], ['realism' => true] + $rules, ['troll' => true] + $rules, $rules, ['require_equipment' => 'off', 'screen_notifications' => 'off']];
    }

    public function testGuideSansLesInstructionsPerimees(): void
    {
        $svc = new AtakExperienceService();
        foreach (self::guideCases() as $settings) {
            $guide = $svc->buildGuide($settings + ['guide_custom' => '']);
            foreach (['tablette Athena', 'Touche K', 'Ctrl+K', 'Tacmap', 'Connexion en jeu', 'clé d’accès', 'Overwatch (communauté)', 'GPS', 'pastille verte', 'Consultez la tablette'] as $stale) {
                self::assertStringNotContainsString($stale, $guide);
            }
            self::assertDoesNotMatchRegularExpression('/[\x{2190}-\x{21FF}\x{27A1}\x{2794}\x{279C}\x{2B05}]/u', $guide);
            self::assertStringContainsString('athena-comspec.eu/lier', $guide);
            self::assertStringContainsString('Options > Contrôles > Configurer les addons > COMSPEC ATAK', $guide);
        }
    }

    /** Chaque touche citée par le guide est bien la touche par défaut du mod. */
    public function testToucheDuGuideDeclareeDansLeMod(): void
    {
        $guide = (new AtakExperienceService())->buildGuide([]);
        $xeh = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/XEH_postInitClient.sqf');
        // CBA : [touche, [Maj, Ctrl, Alt]] ; 0x16 = U, 0xC9 = Page haut, 0xD1 = Page bas.
        foreach ([
            ['Ctrl+U', 'PhoneCarry', '[0x16, [false,true,false]]'],
            ['Alt+U', 'PhoneMain', '[0x16, [false, false, true]]'],
            ['Maj+U', 'PhoneMiniUse', '[0x16, [true, false, false]]'],
            ['Ctrl+Page haut', 'PhoneZoomIn', '[0xC9, [false, true, false]]'],
            ['Ctrl+Page bas', 'PhoneZoomOut', '[0xD1, [false, true, false]]'],
        ] as [$label, $action, $key]) {
            self::assertStringContainsString($label, $guide);
            self::assertMatchesRegularExpression('/"COMSPEC ATAK", "' . $action . '".*' . preg_quote($key, '/') . '\] call CBA_fnc_addKeybind/', $xeh);
        }
        $link = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageAthena.sqf');
        self::assertStringContainsString('["CODE", "account"', $link);
        self::assertStringContainsString('OUI, MON UNITÉ UTILISE ATHENA', $link);
        self::assertStringContainsString('name="Communauté"', self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp'));
        self::assertStringContainsString('["guide", ""], 4000]', self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_tenantAction.sqf'));
    }

    public function testGuideSuitLesReglages(): void
    {
        $svc = new AtakExperienceService();
        $realism = $svc->buildGuide(['realism' => true, 'screen_notifications' => 'on']);
        self::assertStringContainsString('Mode réalisme : ACTIF', $realism);
        self::assertStringContainsString('immobilité, déplacement brusque', $realism);
        // Le réalisme masque les bandeaux même « activés » : le guide ne doit pas dire le contraire.
        self::assertStringNotContainsString('bandeaux d’alerte Athena sont activés', $realism);

        self::assertStringContainsString('immobile après 2 min', $svc->buildGuide(['troll' => true]));
        self::assertStringContainsString('Mode milsim', $svc->buildGuide([]));
        self::assertStringContainsString('Téléphone obligatoire sur vous', $svc->buildGuide(['require_equipment' => 'on']));
        self::assertStringContainsString('Aucun objet requis', $svc->buildGuide(['require_equipment' => 'off']));
        self::assertStringContainsString('le serveur de jeu qui décide', $svc->buildGuide([]));
        self::assertStringContainsString('ne voient pas les positions du camp ennemi', $svc->buildGuide(['show_opfor' => 'off']));
    }

    public function testConsignesPersonnaliseesALaSuiteDuGuideEtDansLeVolumeDuJeu(): void
    {
        $svc = new AtakExperienceService();
        $guide = $svc->buildGuide(['guide_custom' => "Fréquences → 41.0\nModpack ⇒ Opé"]);
        self::assertGreaterThan(strpos($guide, 'Règles de votre communauté'), strpos($guide, 'Message de votre communauté'));
        self::assertStringContainsString('Fréquences > 41.0', $guide);
        self::assertStringContainsString('Modpack > Opé', $guide);

        // Plafond du message appliqué à l'enregistrement.
        $merged = $svc->mergeSettings([], ['guide_custom' => str_repeat('é', AtakExperienceService::GUIDE_CUSTOM_MAX + 50)]);
        self::assertSame(AtakExperienceService::GUIDE_CUSTOM_MAX, mb_strlen($merged['guide_custom']));

        // Pire cas : le jeu en lit 4 000 caractères, et la DLL perd la ligne entière au-delà de son volume.
        foreach (self::guideCases() as $settings) {
            $full = $svc->buildGuide($settings + ['guide_custom' => $merged['guide_custom']]);
            self::assertLessThanOrEqual(4000, mb_strlen($full));
            self::assertLessThanOrEqual(6800, strlen(str_replace("\n", '§NL§', $full)));
        }
    }
}
