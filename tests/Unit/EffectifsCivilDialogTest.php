<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Fiche membre › fenêtre « État civil du personnage » : rendue pour de vrai (partiel PHP), elle garde exactement les mêmes
 * champs, la même route et le même jeton CSRF ; les champs sont rangés en sections courtes dans une grille à deux
 * colonnes où chacun a la même structure (libellé puis contrôle) ; une erreur d'enregistrement s'affiche dans la fenêtre.
 */
final class EffectifsCivilDialogTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    /** Champs envoyés par la fenêtre, tels qu'avant la refonte (aucun ajouté, aucun retiré). */
    private const FIELDS = [
        'first_name', 'last_name', 'nickname_primary', 'birth_place', 'family_situation', 'sex', 'nationality',
        'blood_type', 'weight_kg', 'height_cm', 'drug_intolerances', 'allergies', 'languages', 'motto',
    ];

    /** @param array<string, mixed>|null $error */
    private static function render(?array $error = null): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $id = 42;
        $csrfToken = 'jeton-csrf-test';
        $memberFirstName = 'Damian';
        $memberLastName = 'Stones';
        $profile = [
            'nickname_primary' => 'Adam', 'birth_place' => '', 'family_situation' => '', 'sex' => 'Homme',
            'nationality' => 'USA', 'blood_type' => 'A+', 'weight_kg' => 102, 'height_cm' => 0,
            'drug_intolerances' => '', 'allergies' => '', 'languages' => 'FR et EN', 'motto' => '',
        ];
        $civilStatusOptions = [
            'family_situation' => ['Célibataire' => 'Célibataire', 'Marié(e)' => 'Marié(e)'],
            'sex' => ['Homme' => 'Homme', 'Femme' => 'Femme'],
            'blood_type' => ['A+' => 'A+', 'O-' => 'O-'],
        ];
        $callsign = 'YB1';
        $display = 'dami';
        $identityRow = ['trigram' => ''];
        $civilCountries = [['code' => 'FRA', 'name' => 'France'], ['code' => 'USA', 'name' => 'États-Unis']];
        $civilError = $error;
        ob_start();
        try {
            require self::ROOT . 'views/admin/effectifs_workspace/partials/member_civil_dialog.php';
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    private static function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }

    public function testDialogKeepsTheSameFieldsRouteAndCsrfToken(): void
    {
        $html = self::render();
        $x = self::dom($html);
        $form = $x->query('//dialog[@id="eff-dlg-civil"]/form')->item(0);
        self::assertInstanceOf(\DOMElement::class, $form);
        self::assertSame('post', $form->getAttribute('method'));
        self::assertStringEndsWith('/back-office/ressources/effectifs/membres/42/etat-civil', $form->getAttribute('action'));
        self::assertSame('jeton-csrf-test', $x->query('//form//input[@type="hidden"][@name="_csrf_token"]')->item(0)?->getAttribute('value'));

        $names = [];
        foreach ($x->query('//form//*[self::input or self::select or self::textarea][@name]') as $el) {
            $names[] = $el->getAttribute('name');
        }
        $names = array_values(array_diff($names, ['_csrf_token']));
        sort($names);
        $expected = self::FIELDS;
        sort($expected);
        self::assertSame($expected, $names, 'mêmes champs qu’avant, chacun une seule fois');

        // Mêmes limites que la validation du contrôleur.
        $attr = static fn (string $name, string $a): string => (string) $x->query('//*[@name="' . $name . '"]')->item(0)?->getAttribute($a);
        self::assertSame(['20', '300'], [$attr('weight_kg', 'min'), $attr('weight_kg', 'max')]);
        self::assertSame(['100', '250'], [$attr('height_cm', 'min'), $attr('height_cm', 'max')]);
        foreach (['first_name' => '100', 'last_name' => '100', 'nickname_primary' => '100', 'birth_place' => '150', 'languages' => '255', 'motto' => '255', 'allergies' => '500', 'drug_intolerances' => '500'] as $name => $max) {
            self::assertSame($max, $attr($name, 'maxlength'), $name);
        }
        // Valeurs actuelles reprises, nationalité enregistrée sélectionnée.
        self::assertSame('Damian', $attr('first_name', 'value'));
        self::assertSame('102', $attr('weight_kg', 'value'));
        self::assertSame('', $attr('height_cm', 'value'), 'taille absente : champ vide, pas « 0 »');
        self::assertSame('USA', $x->query('//select[@name="nationality"]/option[@selected]')->item(0)?->getAttribute('value'));
        // Prénom et nom en tête, avant le surnom (ordre attendu par la fiche).
        self::assertTrue(strpos($html, 'name="first_name"') < strpos($html, 'name="nickname_primary"'));
    }

    public function testFieldsAreGroupedInShortSectionsOnAStrictTwoColumnGrid(): void
    {
        $html = self::render();
        $x = self::dom($html);
        $titles = [];
        foreach ($x->query('//section[contains(@class,"eff-civil__section")]//h3') as $h3) {
            $titles[] = trim($h3->textContent);
        }
        self::assertSame(['Identité', 'Corps et santé', 'Langues et devise'], $titles);
        // Chaque section : un titre, une ligne de description, une grille.
        foreach ($x->query('//section[contains(@class,"eff-civil__section")]') as $section) {
            self::assertSame(1, $x->query('.//div[contains(@class,"eff-civil__section-head")]/p', $section)->length);
            self::assertSame(1, $x->query('.//div[contains(@class,"eff-civil__grid")]', $section)->length);
        }

        // Structure identique pour tous les champs : un bloc .eff-civil__field = un libellé lié à un contrôle.
        $fields = $x->query('//div[contains(concat(" ", @class, " ")," eff-civil__field ")]');
        self::assertSame(count(self::FIELDS), $fields->length);
        foreach ($fields as $field) {
            $label = $x->query('./label[@class="eff-civil__label"]', $field);
            self::assertSame(1, $label->length, 'un libellé en tête de chaque champ');
            self::assertSame($x->query('./*[1]', $field)->item(0), $label->item(0), 'le libellé est le premier élément du champ');
            $for = $label->item(0)->getAttribute('for');
            $control = $x->query('.//*[@id="' . $for . '"]', $field);
            self::assertSame(1, $control->length, 'libellé lié au contrôle de son champ : ' . $for);
            self::assertStringContainsString('eff-civil__control', $control->item(0)->getAttribute('class'));
        }
        // Sur une grille à deux colonnes, les champs non pleine largeur vont par paires dans chaque section.
        foreach ($x->query('//div[contains(@class,"eff-civil__grid")]') as $grid) {
            $halves = 0;
            foreach ($x->query('./div', $grid) as $cell) {
                if (!str_contains($cell->getAttribute('class'), 'eff-civil__wide')) {
                    $halves++;
                }
            }
            self::assertSame(0, $halves % 2, 'aucune cellule orpheline dans une grille');
        }

        // Aides : Taille et Devise ne sont plus vides ; unités écrites dans le champ.
        self::assertSame('Ex. 180', $x->query('//input[@name="height_cm"]')->item(0)?->getAttribute('placeholder'));
        self::assertNotSame('', (string) $x->query('//input[@name="motto"]')->item(0)?->getAttribute('placeholder'));
        self::assertSame(1, $x->query('//p[@id="eff-civil-motto-help"]')->length);
        self::assertSame(['cm', 'kg'], array_map(static fn ($n): string => trim($n->textContent), iterator_to_array($x->query('//span[@class="eff-civil__suffix"]'))));

        // Aperçu du nom en encart (Listes / Fiche / Carte tactique), relié aux champs Prénom et Nom.
        $preview = $x->query('//div[contains(@class,"eff-civil__preview")]//dl[@data-member-name-preview]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $preview);
        self::assertSame('#eff-dlg-civil-first-name', $preview->getAttribute('data-first'));
        self::assertSame(['list', 'card', 'c2'], array_map(static fn ($n): string => $n->getAttribute('data-mnp'), iterator_to_array($x->query('.//dd[@data-mnp]', $preview))));

        // Coque bo-dialog : en-tête, corps défilant, pied ; pas d'ancienne grille ni de mention de fiction.
        self::assertSame(1, $x->query('//header[contains(@class,"eff-civil__head")]')->length);
        self::assertSame(1, $x->query('//div[@data-civil-scroll]')->length);
        self::assertSame(1, $x->query('//footer[contains(@class,"eff-civil__foot")]//button[@type="submit"]')->length);
        self::assertStringNotContainsString('eff-dialog__grid', $html);
        self::assertDoesNotMatchRegularExpression('/fiction|\bRP\b|simulation|sse-ds|class="sse-/i', $html);
        self::assertSame(0, $x->query('//*[@role="alert"]')->length, 'pas d’erreur sans erreur');
    }

    public function testSaveErrorIsShownInsideTheDialogOnTheRefusedField(): void
    {
        $x = self::dom(self::render(['message' => 'Le prénom et le nom ne peuvent pas contenir de HTML.', 'fields' => ['first_name']]));
        $dialog = $x->query('//dialog[@id="eff-dlg-civil"]')->item(0);
        self::assertTrue($dialog->hasAttribute('data-civil-has-error'));
        $alert = $x->query('//dialog//div[@role="alert"][@id="eff-dlg-civil-error"]');
        self::assertSame(1, $alert->length);
        self::assertStringContainsString('ne peuvent pas contenir de HTML', $alert->item(0)->textContent);
        self::assertSame('true', $x->query('//input[@name="first_name"]')->item(0)?->getAttribute('aria-invalid'));
        self::assertSame('eff-dlg-civil-error', $x->query('//input[@name="first_name"]')->item(0)?->getAttribute('aria-describedby'));
        self::assertFalse($x->query('//input[@name="last_name"]')->item(0)?->hasAttribute('aria-invalid'));
    }

    public function testMemberPageMountsTheDialogAndControllerReturnsErrorsIntoIt(): void
    {
        $view = (string) file_get_contents(self::ROOT . 'views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString("require __DIR__ . '/partials/member_civil_dialog.php'", $view);
        self::assertStringContainsString("Session::getFlash('eff_civil_error')", $view);
        self::assertStringContainsString('data-dialog-open="eff-dlg-civil"', $view);

        $controller = (string) file_get_contents(self::ROOT . 'app/Controllers/Admin/EffectifsWorkspaceController.php');
        $handler = substr($controller, (int) strpos($controller, 'function updateMemberCivilStatus'));
        $handler = substr($handler, 0, (int) strpos($handler, 'public function quickAssignment'));
        self::assertStringContainsString("effectifs_member_url(\$id, 'etat-civil')", $handler);
        self::assertStringContainsString("Session::flash('eff_civil_error'", $handler);
        self::assertStringContainsString("'fields' => \$nameRejected", $handler);
        // Page : composants de fenêtre partagés + feuille propre à la fenêtre.
        self::assertStringContainsString("'back-office-bands.css', 'eff-civil-dialog.css'", $controller);

        $css = (string) file_get_contents(self::ROOT . 'public/assets/css/eff-civil-dialog.css');
        self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $css);
        self::assertStringContainsString('grid-template-rows: 20px 44px', $css);
        self::assertStringContainsString('::-webkit-inner-spin-button', $css);
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        self::assertStringContainsString('height: 100dvh', $css);
        self::assertDoesNotMatchRegularExpression('/(?<![\w-])#[0-9a-f]{3,8}(?![\w-])/i', $css, 'jetons du back-office uniquement, aucune couleur littérale');

        $js = (string) file_get_contents(self::ROOT . 'public/assets/js/eff-member.js');
        self::assertStringContainsString('function initCivilDialog()', $js);
        self::assertStringContainsString("'is-scrolled'", $js);
        self::assertStringContainsString("'has-more'", $js);
    }
}
