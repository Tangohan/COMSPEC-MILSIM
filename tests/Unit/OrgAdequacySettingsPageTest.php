<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacyImpact;
use App\Services\Organization\OrgAdequacySettings;
use PHPUnit\Framework\TestCase;

/**
 * Page « Adéquation de l’organisation » refaite : bande, onglets, vue d’ensemble, règles, liste des échelons,
 * fenêtre par échelon (mêmes champs POST qu’avant), aide contextuelle, droits, rétablissement du référentiel.
 */
final class OrgAdequacySettingsPageTest extends TestCase
{
    private array $session;

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $_SESSION = ['tenant_id' => 14, 'user_id' => 3];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function sample(array $settings): array
    {
        $p = static fn (string $l, ?string $n): array => ['label' => $l, 'has_grade' => $n !== null, 'grade_label' => (string) $n, 'grade_level' => $n !== null ? GradeLevel::fromCode($n) : null];

        return OrgAdequacyEngine::evaluate([
            'settings' => $settings,
            'profile' => EchelonStandards::PROFILE_US,
            'units' => [
                ['id' => 1, 'parent_id' => 0, 'name' => 'Escadron', 'commander_user_id' => 7, 'echelon' => 'compagnie'],
                ['id' => 2, 'parent_id' => 1, 'name' => 'Groupe <1>', 'commander_user_id' => 8, 'echelon' => 'groupe'],
            ],
            'members_by_unit' => [1 => [7], 2 => [8]],
            'primary_unit' => [7 => 1, 8 => 2],
            'people' => [7 => $p('Mercer', 'OF-2'), 8 => $p('Hale', 'OR-4'), 9 => $p('Sans Place', 'OR-3')],
        ]);
    }

    /** @param array<string, mixed> $over */
    private function render(array $settings, array $over = []): string
    {
        $vars = $over + [
            'effectifsNav' => 'chaine',
            'effectifsContent' => 'admin.effectifs_workspace.adequation',
            'adequacySettings' => $settings,
            'adequacyProfile' => 'us',
            'adequacyStandards' => OrgAdequacySettings::effectiveStandards($settings, 'us'),
            'adequacyGradeLabels' => [4 => ['Corporal'], 5 => ['Sergeant'], 6 => ['Staff Sergeant']],
            'adequacyQualifications' => [['id' => 3, 'label' => 'Advanced Leader Course']],
            'adequacyJobRoles' => [['id' => 31, 'label' => 'Squad leader']],
            'adequacyResult' => self::sample($settings),
            'adequacyViewerId' => 3,
            'canEditAdequacy' => true,
            'csrfToken' => 'tok',
        ];
        $file = self::root() . '/views/admin/effectifs_workspace/shell.php';
        $render = static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            try {
                require $__file;
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };

        return $render($file, $vars);
    }

    private static function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return new \DOMXPath($doc);
    }

    /**
     * Ce que le navigateur enverrait au clic sur « Enregistrer » (champs du formulaire, sans bouton de soumission).
     *
     * @return array<string, mixed>
     */
    private static function submitted(\DOMXPath $x): array
    {
        $pairs = [];
        $form = $x->query('//form[@id="adqp-form"]')->item(0);
        self::assertNotNull($form);
        foreach ($x->query('.//input | .//select', $form) as $el) {
            /** @var \DOMElement $el */
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            if ($el->tagName === 'select') {
                $value = '';
                $first = null;
                foreach ($x->query('.//option', $el) as $opt) {
                    /** @var \DOMElement $opt */
                    $first ??= $opt->getAttribute('value');
                    if ($opt->hasAttribute('selected')) {
                        $value = $opt->getAttribute('value');
                        $first = null;
                        break;
                    }
                }
                $pairs[] = [$name, $first ?? $value];
                continue;
            }
            $type = strtolower($el->getAttribute('type'));
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            if (in_array($type, ['submit', 'button', 'search'], true)) {
                continue;
            }
            $pairs[] = [$name, $el->getAttribute('value')];
        }
        $query = implode('&', array_map(static fn (array $p): string => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), $pairs));
        parse_str($query, $out);

        return $out;
    }

    public function testPageHasBandTabsOverviewAndNoEyebrow(): void
    {
        $settings = OrgAdequacySettings::sanitize(['echelons' => ['groupe' => ['strength_min' => '6']]]);
        $html = $this->render($settings);
        $x = self::dom($html);

        self::assertSame(1, $x->query('//header[contains(@class,"bo-band--hero")]//h2[@id="adqp-title"]')->length);
        self::assertStringContainsString('Compare votre organisation réelle à ce que chaque échelon devrait avoir.', $html);
        self::assertSame(3, $x->query('//header[contains(@class,"adqp-hero")]//p[contains(@class,"bo-figure")]')->length, 'écarts, échelons évalués, règles actives');
        self::assertSame(1, $x->query('//button[@data-adqp-help-open]')->length);
        // Aucun surtitre : ni étiquette de bande, ni petit libellé « effk-k ».
        self::assertSame(0, $x->query('//*[contains(@class,"bo-band__tag") or contains(concat(" ",@class," ")," effk-k ")]')->length);

        $tabs = $x->query('//*[@role="tablist"]/*[@role="tab"]');
        self::assertSame(3, $tabs->length);
        foreach ($tabs as $tab) {
            /** @var \DOMElement $tab */
            $panel = $x->query('//*[@id="' . $tab->getAttribute('aria-controls') . '"]')->item(0);
            self::assertInstanceOf(\DOMElement::class, $panel);
            self::assertSame('tabpanel', $panel->getAttribute('role'));
            self::assertSame($tab->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
        }
        self::assertSame('true', $x->query('//*[@id="adqp-tab-ensemble"]')->item(0)->getAttribute('aria-selected'));
        self::assertFalse($x->query('//*[@id="adqp-panel-ensemble"]')->item(0)->hasAttribute('hidden'));
        self::assertTrue($x->query('//*[@id="adqp-panel-echelons"]')->item(0)->hasAttribute('hidden'));

        // Vue d’ensemble : une carte par type, la sous-capacité ouvre une fenêtre avec le lien vers l’unité et le membre.
        self::assertSame(count(OrgAdequacyImpact::GROUPS), $x->query('//li[@data-adqp-group]')->length);
        $under = $x->query('//dialog[@id="adqp-dlg-type-under"]')->item(0);
        self::assertNotNull($under);
        self::assertSame(1, $x->query('.//a[contains(@href,"organisation/unites/2")]', $under)->length);
        self::assertSame(1, $x->query('.//a[contains(@href,"membres/8")]', $under)->length);
        self::assertStringContainsString('Groupe &lt;1&gt;', $html);
        self::assertStringNotContainsString('Groupe <1>', $html);

        // Section : index à côté du titre, une ligne de description.
        self::assertSame(3, $x->query('//*[contains(@class,"bo-section__head")]/span[contains(@class,"bo-section__index")]')->length);

        $ech = $this->render($settings, ['adequacyTab' => 'echelons']);
        $xe = self::dom($ech);
        self::assertSame('true', $xe->query('//*[@id="adqp-tab-echelons"]')->item(0)->getAttribute('aria-selected'));
        self::assertSame(count(EchelonStandards::keys()), $xe->query('//li[@data-adqp-ech]')->length);
        $squad = $xe->query('//li[@data-adqp-ech="groupe"]')->item(0);
        self::assertSame(1, $xe->query('.//span[contains(@class,"adqp-pill--custom")]', $squad)->length, 'badge « personnalisé »');
        self::assertSame(0, $xe->query('//li[@data-adqp-ech="equipe"]//span[contains(@class,"adqp-pill--custom") and not(@data-adqp-is)]')->length);
        self::assertStringContainsString('<div><dt>Chef</dt><dd>Staff Sergeant (OR-6)</dd></div>', $ech);
        self::assertStringContainsString('<div><dt>Effectif</dt><dd>6–9</dd></div>', $ech);
        self::assertSame(1, $xe->query('.//input[@role="switch" and @name="org_adequacy[echelons][groupe][enabled]"]', $squad)->length);
        self::assertSame(1, $xe->query('.//button[@data-adqp-open="adqp-dlg-ech-groupe"]//svg', $squad)->length, 'crayon SVG');
    }

    public function testEchelonDialogKeepsTheSamePostFields(): void
    {
        $settings = OrgAdequacySettings::sanitize([
            'overqualification_margin' => 3,
            'rules' => ['functions' => '0'],
            'echelons' => [
                'groupe' => ['leader_grade_min' => 'OR-5', 'deputy_required' => '0', 'strength_min' => '6', 'qualification_ids' => ['3']],
                'regiment' => ['enabled' => '0'],
            ],
        ]);
        $html = $this->render($settings, ['adequacyTab' => 'echelons']);
        $x = self::dom($html);
        $form = $x->query('//form[@id="adqp-form"]')->item(0);
        self::assertSame('post', strtolower($form->getAttribute('method')));
        self::assertStringEndsWith('chaine/adequation', $form->getAttribute('action'));
        self::assertSame(1, $x->query('//form[@id="adqp-form"]//input[@name="_csrf_token" and @value="tok"]')->length);

        foreach (EchelonStandards::keys() as $key) {
            $dlg = $x->query('//form[@id="adqp-form"]//dialog[@data-adqp-ech-dialog="' . $key . '"]')->item(0);
            self::assertNotNull($dlg, $key);
            self::assertSame('adqp-dlg-ech-' . $key . '-t', $dlg->getAttribute('aria-labelledby'));
            foreach (['leader_grade_min', 'leader_grade_max', 'deputy_grade_min', 'deputy_grade_max', 'strength_min', 'strength_max'] as $f) {
                self::assertSame(1, $x->query('.//*[@name="org_adequacy[echelons][' . $key . '][' . $f . ']"]', $dlg)->length, $key . ' ' . $f);
            }
            self::assertSame(3, $x->query('.//input[@type="radio" and @name="org_adequacy[echelons][' . $key . '][deputy_required]"]', $dlg)->length);
            self::assertSame(1, $x->query('.//input[@name="org_adequacy[echelons][' . $key . '][qualification_ids][]"]', $dlg)->length);
            self::assertSame(1, $x->query('.//input[@name="org_adequacy[echelons][' . $key . '][job_role_ids][]"]', $dlg)->length);
            self::assertSame(4, $x->query('.//*[@data-adqp-stepbody]', $dlg)->length, 'quatre étapes');
            self::assertSame(1, $x->query('.//details[contains(@class,"adqp-sources")]', $dlg)->length, 'sources dans la fenêtre');
            self::assertSame(1, $x->query('.//*[@data-adqp-impact="' . $key . '"]', $dlg)->length, '« Ce que ça change »');
        }
        self::assertSame(1, $x->query('//*[@name="org_adequacy[echelons][groupe][subunits_min]"]')->length);
        self::assertSame(0, $x->query('//*[@name="org_adequacy[echelons][equipe][subunits_min]"]')->length);
        // Les sources ne sont plus dans la liste.
        self::assertSame(0, $x->query('//ul[contains(@class,"adqp-ech-list")]//*[contains(text(),"ATP 3-21.8")]')->length);

        // Ce que le formulaire renvoie, une fois nettoyé, est exactement le réglage affiché.
        $posted = self::submitted($x);
        self::assertSame($settings, OrgAdequacySettings::sanitize($posted['org_adequacy'] ?? []));
        self::assertSame('tok', $posted['_csrf_token']);
        self::assertSame('', $posted['org_adequacy']['echelons']['groupe']['leader_grade_max'], 'valeur du référentiel : champ vide');
    }

    public function testDefaultsAreAVisibleStateAndRestorable(): void
    {
        $defaults = OrgAdequacySettings::defaults();
        $html = $this->render($defaults, ['adequacyTab' => 'echelons']);
        $x = self::dom($html);
        // Plus aucun libellé « Par défaut : » dans les menus : l’état est une pastille et la valeur est grisée.
        self::assertStringNotContainsString('Par défaut', $html);
        self::assertSame(0, $x->query('//select[contains(@name,"[echelons]")]/optgroup/option[@selected]')->length);
        self::assertSame($x->query('//select[contains(@name,"[echelons]")]')->length, $x->query('//select[contains(@name,"[echelons]") and contains(@class,"is-ref")]')->length);
        self::assertStringContainsString('<option value="">Référentiel : Staff Sergeant (OR-6)</option>', $html);
        self::assertSame(0, $x->query('//*[@data-adqp-is="custom" and not(@hidden) and ancestor::*[@data-adqp-field]]')->length);
        self::assertGreaterThan(0, $x->query('//button[@data-adqp-reset-echelon]')->length);
        // Le rendu par défaut renvoie les valeurs par défaut ; « Tout rétablir » envoie reset=1.
        self::assertSame($defaults, OrgAdequacySettings::sanitize(self::submitted($x)['org_adequacy'] ?? []));
        self::assertSame(1, $x->query('//dialog[@id="adqp-dlg-reset"]//button[@type="submit" and @name="reset" and @value="1"]')->length);
        self::assertStringContainsString('Rétablir les valeurs par défaut', $html);

        // Valeur surchargée : pastille « personnalisé » et bouton « Rétablir » du champ visibles.
        $custom = $this->render(OrgAdequacySettings::sanitize(['echelons' => ['groupe' => ['leader_grade_min' => 'OR-4']]]), ['adequacyTab' => 'echelons']);
        $xc = self::dom($custom);
        $field = $xc->query('//select[@id="adq-groupe-lmin"]/ancestor::*[@data-adqp-field][1]')->item(0);
        self::assertFalse($xc->query('.//*[@data-adqp-is="custom"]', $field)->item(0)->hasAttribute('hidden'));
        self::assertFalse($xc->query('.//button[@data-adqp-reset]', $field)->item(0)->hasAttribute('hidden'));
        self::assertSame(1, $xc->query('//select[@id="adq-groupe-lmin"]//option[@value="OR-4" and @selected]')->length);

        $ctrl = (string) file_get_contents(self::root() . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("\$raw = \\App\\Services\\Organization\\OrgAdequacySettings::defaults();", $ctrl, 'reset=1 inchangé');
    }

    public function testContextualHelpAndGuide(): void
    {
        $html = $this->render(OrgAdequacySettings::defaults(), ['adequacyTab' => 'echelons']);
        $x = self::dom($html);
        $buttons = $x->query('//button[contains(@class,"adqp-q")]');
        self::assertGreaterThan(count(EchelonStandards::keys()) * 8, $buttons->length);
        foreach ($buttons as $b) {
            /** @var \DOMElement $b */
            self::assertSame('false', $b->getAttribute('aria-expanded'));
            self::assertStringStartsWith('Aide : ', $b->getAttribute('aria-label'));
            $pop = $x->query('//*[@id="' . $b->getAttribute('aria-controls') . '"]')->item(0);
            self::assertInstanceOf(\DOMElement::class, $pop);
            self::assertTrue($pop->hasAttribute('hidden'));
            self::assertStringContainsString('Exemple', $pop->textContent);
        }
        foreach (['Grade minimum', 'Grade maximum', 'Adjoint exigé', 'Effectif', 'Sous-unités', 'Qualifications de commandement', 'Emploi attendu', 'Seuil de surqualification', 'Attentes appliquées'] as $notion) {
            self::assertStringContainsString('aria-label="Aide : ' . $notion . '"', $html, $notion);
        }
        $guide = $x->query('//dialog[@id="adqp-dlg-help"]')->item(0);
        self::assertSame(4, $x->query('.//*[@data-adqp-stepbody]', $guide)->length);
        self::assertSame(4, $x->query('.//svg[contains(@class,"adqp-art")]', $guide)->length);
        // Règles : chaque règle a son interrupteur, sa phrase et son effet ; le seuil a un stepper et un exemple.
        self::assertSame(count(OrgAdequacySettings::RULES), $x->query('//input[@role="switch" and starts-with(@name,"org_adequacy[rules]")]')->length);
        self::assertSame(count(OrgAdequacySettings::RULES), $x->query('//p[contains(@class,"adqp-switch__fx")]')->length);
        self::assertSame(2, $x->query('//*[@data-adqp-stepper]//button[@data-adqp-step-by]')->length);
        self::assertStringContainsString('data-adqp-margin-ex>OR-8</b>', $html);

        $js = (string) file_get_contents(self::root() . '/public/assets/js/eff-adequacy-page.js');
        self::assertStringContainsString("e.key !== 'Tab'", $js, 'focus piégé');
        self::assertStringContainsString('beforeunload', $js);
        self::assertStringContainsString('localStorage', $js);
        self::assertStringContainsString('catch (e)', $js, 'repli sans stockage local');
        self::assertDoesNotMatchRegularExpression('/sse-ds|\.sse-|Archivo/', (string) file_get_contents(self::root() . '/public/assets/css/eff-adequacy-page.css'));
    }

    public function testReadOnlyAndRights(): void
    {
        $html = $this->render(OrgAdequacySettings::defaults(), ['canEditAdequacy' => false, 'adequacyTab' => 'echelons']);
        $x = self::dom($html);
        self::assertSame(0, $x->query('//form[@id="adqp-form"]//*[(self::input and not(@type="hidden")) or self::select][not(@disabled)]')->length - $x->query('//form[@id="adqp-form"]//input[@data-adqp-filter]')->length);
        self::assertSame(0, $x->query('//button[@type="submit"]')->length);
        self::assertSame(0, $x->query('//*[@data-adqp-savebar]')->length);
        self::assertSame(0, $x->query('//button[@data-adqp-reset]')->length);
        self::assertStringContainsString('Lecture seule', $html);
        self::assertStringContainsString('aria-label="Voir l’échelon Squad"', $html);

        $ctrl = str_replace("\r\n", "\n", (string) file_get_contents(self::root() . '/app/Controllers/Admin/EffectifsWorkspaceController.php'));
        $save = substr($ctrl, (int) strpos($ctrl, 'public function saveAdequacySettings'), 2400);
        self::assertStringContainsString('Csrf::validate', $save);
        self::assertStringContainsString('$this->canEditAdequacy()', $save);
        self::assertStringContainsString('OrgAdequacySettings::sanitize($raw)', $save);
        $preview = substr($ctrl, (int) strpos($ctrl, 'public function previewAdequacySettings'), 1800);
        self::assertStringContainsString('denyUnlessAccess', $preview);
        self::assertStringContainsString('Csrf::validate', $preview);
        self::assertStringNotContainsString('saveForTenant', $preview, 'l’aperçu n’enregistre rien');
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        self::assertStringContainsString("'/back-office/ressources/effectifs/chaine/adequation', [EffectifsWorkspaceController::class, 'saveAdequacySettings'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/chaine/adequation/apercu', [EffectifsWorkspaceController::class, 'previewAdequacySettings'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
    }

    public function testRefusedSaveReopensTheEchelonWithItsError(): void
    {
        $html = $this->render(OrgAdequacySettings::defaults(), ['adequacyOpenEchelon' => 'section', 'adequacyModalError' => 'Session expirée.']);
        $x = self::dom($html);
        self::assertFalse($x->query('//*[@id="adqp-panel-echelons"]')->item(0)->hasAttribute('hidden'));
        $err = $x->query('//dialog[@id="adqp-dlg-ech-section"]//*[@data-adqp-err]')->item(0);
        self::assertSame('alert', $err->getAttribute('role'));
        self::assertFalse($err->hasAttribute('hidden'));
        self::assertSame('Session expirée.', trim($err->textContent));
        self::assertStringContainsString('data-adqp-open-echelon="section"', $html);
        self::assertTrue($x->query('//dialog[@id="adqp-dlg-ech-groupe"]//*[@data-adqp-err]')->item(0)->hasAttribute('hidden'));
    }

    public function testEmptyStates(): void
    {
        $off = OrgAdequacySettings::sanitize(['enabled' => '0']);
        $html = $this->render($off, ['adequacyResult' => OrgAdequacyEngine::evaluate(['settings' => $off])]);
        self::assertStringContainsString('Le contrôle est désactivé', $html);
        self::assertStringContainsString('contrôle désactivé', $html);
        self::assertSame(0, self::dom($html)->query('//li[@data-adqp-group]')->length);

        $quiet = OrgAdequacySettings::sanitize(['rules' => array_fill_keys(array_keys(OrgAdequacySettings::RULES), '0')]);
        $html = $this->render($quiet, ['adequacyResult' => self::sample($quiet)]);
        $x = self::dom($html);
        self::assertStringContainsString('Aucune règle active : rien n’est vérifié.', $html);
        self::assertStringNotContainsString('class="adqp-allgood"', $html);
        self::assertSame(count(OrgAdequacyImpact::GROUPS), $x->query('//li[@data-adqp-group and contains(@class,"is-off")]')->length);
        self::assertSame(0, $x->query('//dialog[starts-with(@id,"adqp-dlg-type-")]')->length);
    }

    public function testImpactReadsTheEngineWithoutChangingIt(): void
    {
        // Tout code d’écart du moteur appartient à exactement un type de la vue d’ensemble.
        $seen = [];
        foreach (OrgAdequacyImpact::GROUPS as $def) {
            foreach ($def['codes'] as $code) {
                self::assertArrayNotHasKey($code, $seen);
                $seen[$code] = true;
            }
            foreach ($def['rules'] as $rule) {
                self::assertArrayHasKey($rule, OrgAdequacySettings::RULES);
            }
        }
        self::assertEqualsCanonicalizing(array_keys(OrgAdequacyEngine::titles()), array_keys($seen));

        $result = self::sample([]);
        $grouped = OrgAdequacyImpact::grouped($result);
        self::assertSame(count($result['issues']), array_sum(array_map('count', $grouped)));
        self::assertSame(['leader_grade_below'], array_values(array_unique(array_map(static fn (array $i): string => $i['code'], $grouped['under']))));
        self::assertSame(['without_place'], array_map(static fn (array $i): string => $i['code'], $grouped['placement']));

        $by = OrgAdequacyImpact::byEchelon($result);
        self::assertSame(EchelonStandards::keys(), array_keys($by));
        self::assertSame(1, $by['groupe']['units']);
        self::assertSame(1, $by['compagnie']['units']);
        self::assertGreaterThan(0, $by['groupe']['issues']);
        self::assertSame(['units' => 0, 'issues' => 0], $by['equipe']);
        // Écarts de personne (sans place) jamais comptés pour un échelon.
        $unitIssues = count(array_filter($result['issues'], static fn (array $i): bool => $i['unit_id'] > 0 && !in_array($i['code'], ['without_place', 'without_chief'], true)));
        self::assertSame($unitIssues, array_sum(array_column($by, 'issues')));

        // Un réglage plus souple réduit les écarts de l’échelon (aperçu « Ce que ça change »).
        $softer = OrgAdequacySettings::sanitize(['echelons' => ['groupe' => ['leader_grade_min' => 'OR-4', 'leader_grade_max' => 'OR-6']]]);
        self::assertLessThan($by['groupe']['issues'], OrgAdequacyImpact::byEchelon(self::sample($softer))['groupe']['issues']);

        self::assertTrue(OrgAdequacyImpact::groupDisabled('over', OrgAdequacySettings::sanitize(['rules' => ['grade_above' => '0']])));
        self::assertFalse(OrgAdequacyImpact::groupDisabled('under', OrgAdequacySettings::sanitize(['rules' => ['grade_below' => '0']])), 'qualifications et emploi restent actifs');
        self::assertFalse(OrgAdequacyImpact::customized(OrgAdequacySettings::sanitize(['echelons' => ['groupe' => ['enabled' => '0']]]), 'groupe'));
        self::assertTrue(OrgAdequacyImpact::customized(OrgAdequacySettings::sanitize(['echelons' => ['groupe' => ['strength_max' => '12']]]), 'groupe'));
    }
}
