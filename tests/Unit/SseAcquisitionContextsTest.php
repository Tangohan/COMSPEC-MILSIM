<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRecordRepository;
use App\Support\SseAcquisitionContexts;
use PHPUnit\Framework\TestCase;

/**
 * Contextes d'acquisition du portail SSE : catalogue forces spéciales, anciens codes toujours acceptés et lisibles,
 * jeu « police » inchangé, personnalisation par communauté, aucune clé technique renommée, vues qui importent leurs
 * classes, vocabulaire de police absent des écrans du Bureau SSE.
 */
final class SseAcquisitionContextsTest extends TestCase
{
    private const LEGACY = ['controle', 'perquisition', 'reddition', 'autre'];

    private static function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testCatalogCodesAreUniqueStableAndFullyTranslated(): void
    {
        $cfg = SseAcquisitionContexts::config();
        $groups = SseAcquisitionContexts::groups();
        $seen = [];
        foreach ((array) $cfg['contexts'] as $row) {
            $code = (string) $row['code'];
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]{1,39}$/', $code);
            self::assertFalse(isset($seen[$code]), 'code en double : ' . $code);
            $seen[$code] = true;
            foreach (['fr', 'en', 'desc_fr', 'desc_en'] as $k) {
                self::assertTrue(trim((string) ($row[$k] ?? '')) !== '', $code . ' : ' . $k);
            }
            self::assertTrue(isset($groups[(string) $row['group']]), $code . ' : groupe inconnu');
        }
        foreach ($groups as $code => $g) {
            self::assertTrue($g['fr'] !== '' && $g['en'] !== '', 'groupe ' . $code);
        }
        foreach (['raid_action_directe', 'exploitation_site_sensible', 'point_controle_tactique', 'cordon_fouille', 'interrogatoire_tactique', 'enrolement_biometrique', 'document_media_capture', 'autre'] as $code) {
            self::assertTrue(isset($seen[$code]), $code);
        }
        self::assertGreaterThanOrEqual(15, count($seen));
    }

    public function testEveryLegacyCodeMapsToAnExistingContext(): void
    {
        $catalog = SseAcquisitionContexts::catalogByCode();
        foreach (self::LEGACY as $code) {
            $map = SseAcquisitionContexts::legacy()[$code] ?? null;
            self::assertNotNull($map, $code);
            self::assertTrue(isset($catalog[$map['to']]), $code . ' -> ' . $map['to']);
            self::assertTrue(SseAcquisitionContexts::isLegacyCode($code));
        }
        self::assertSame('point_controle_tactique', SseAcquisitionContexts::canonical('CONTROLE'));
        self::assertSame('raid_action_directe', SseAcquisitionContexts::canonical('raid_action_directe'));
    }

    public function testOldCodesAreStillDisplayedWithTheirCorrespondence(): void
    {
        $ctx = SseAcquisitionContexts::defaults();
        $controle = $ctx->label('controle', 'fr');
        self::assertStringContainsString('Point de contrôle tactique', $controle);
        self::assertStringContainsString('ancien : Contrôle', $controle);
        self::assertStringContainsString('formerly: Check', $ctx->label('controle', 'en'));
        self::assertStringContainsString('ancien : Perquisition', $ctx->label('perquisition', 'fr'));
        self::assertStringContainsString('Exploitation de site sensible', $ctx->label('perquisition', 'fr'));
        self::assertStringContainsString('ancien : Reddition', $ctx->label('reddition', 'fr'));
        self::assertSame('Autre (précisez)', $ctx->label('autre', 'fr'));
        self::assertStringContainsString('Action directe', $ctx->label('raid_action_directe', 'fr'));
        // Texte libre ou code inconnu : tel quel, jamais perdu ; vide : tiret.
        self::assertSame('Repéré près du dépôt', $ctx->label('Repéré près du dépôt'));
        self::assertSame('detention', $ctx->label('detention'));
        self::assertSame('—', $ctx->label(''));
    }

    public function testStoredValueIsNeverRewrittenOrRejected(): void
    {
        foreach (self::LEGACY as $code) {
            self::assertSame($code, SseAcquisitionContexts::storeValue($code));
        }
        self::assertSame('controle', SseAcquisitionContexts::storeValue(' Controle '));
        self::assertSame('raid_action_directe', SseAcquisitionContexts::storeValue('RAID_ACTION_DIRECTE'));
        self::assertSame('Texte libre ancien', SseAcquisitionContexts::storeValue('Texte libre ancien'));
        self::assertSame('Gras', SseAcquisitionContexts::storeValue('<b>Gras</b>'));
        self::assertNull(SseAcquisitionContexts::storeValue(''));
        self::assertNull(SseAcquisitionContexts::storeValue(null));
        self::assertNull(SseAcquisitionContexts::storeValue(['a']));
        self::assertTrue(SseAcquisitionContexts::defaults()->isKnown('perquisition'));
        self::assertTrue(SseAcquisitionContexts::defaults()->isKnown('cordon_fouille'));
        self::assertFalse(SseAcquisitionContexts::defaults()->isKnown('inconnu'));
    }

    public function testPersonRepositoryStoresAndShowsTheCodesThroughTheCatalog(): void
    {
        $repo = self::read('app/Repositories/SsePersonRepository.php');
        self::assertStringContainsString("'circumstances' => SseAcquisitionContexts::storeValue(\$data['circumstances'] ?? null)", $repo);
        self::assertStringContainsString('SseAcquisitionContexts::forTenant($tenantId)', $repo);
        self::assertStringContainsString("'circumstances_label' => \$this->circumstancesLabel(", $repo);
        self::assertStringNotContainsString("'perquisition' => 'Perquisition'", $repo, 'la liste de police en dur a disparu');
        // Le contrat de l'API ne change pas : mêmes clés, même route.
        self::assertStringContainsString("'circumstances' => \$row['circumstances'] ?? null,", $repo);
        self::assertStringContainsString("'language_spoken', 'distinguishing_marks', 'affiliation', 'circumstances',", self::read('app/Controllers/Api/SseApiController.php'));
        self::assertStringContainsString("'/api/sse/persons', [SseApiController::class, 'personsStore']", self::read('routes/web.php'));
    }

    public function testPoliceSetKeepsTheFourOriginalChoicesUnchanged(): void
    {
        $police = SseAcquisitionContexts::fromSettings(['acquisition_set' => 'police']);
        self::assertSame('police', $police->set());
        $options = $police->options('fr');
        self::assertSame(['controle', 'perquisition', 'reddition', 'autre'], array_column($options, 'code'));
        self::assertSame(['Contrôle', 'Perquisition', 'Reddition', 'Autre'], array_column($options, 'label'));
        self::assertSame('Contrôle', $police->label('controle', 'fr'));
        self::assertSame('Perquisition', $police->label('perquisition', 'fr'));
        self::assertSame('Reddition', $police->label('reddition', 'fr'));
        self::assertSame('Autre', $police->label('autre', 'fr'));
        // Un code récent envoyé par le mod à jour reste lisible dans une communauté « police ».
        self::assertStringContainsString('Action directe', $police->label('raid_action_directe', 'fr'));
        // Par défaut (aucun réglage) : forces spéciales.
        self::assertSame('us_sof', SseAcquisitionContexts::fromSettings([])->set());
        self::assertSame('us_sof', SseAcquisitionContexts::fromSettings(['acquisition_set' => 'n_importe_quoi'])->set());
    }

    public function testCommunityCanDisableRenameAndAddContexts(): void
    {
        $ctx = SseAcquisitionContexts::fromSettings([
            'acquisition_overrides' => [
                'cordon_fouille' => ['enabled' => false],
                'raid_action_directe' => ['fr' => 'Raid de nuit', 'en' => 'Night raid'],
            ],
            'acquisition_custom' => [
                ['fr' => 'Bouclage de village', 'en' => 'Village lockdown', 'group' => 'controle_fouille', 'enabled' => true],
            ],
        ]);
        $codes = array_column($ctx->options('fr'), 'code');
        self::assertNotContains('cordon_fouille', $codes);
        self::assertContains('raid_action_directe', $codes);
        self::assertContains('c_bouclage_de_village', $codes);
        self::assertSame('Raid de nuit', $ctx->label('raid_action_directe', 'fr'));
        self::assertSame('Night raid', $ctx->label('raid_action_directe', 'en'));
        self::assertSame('Village lockdown', $ctx->label('c_bouclage_de_village', 'en'));
        // Un contexte désactivé reste lisible sur les fiches qui l'utilisent.
        self::assertStringContainsString('Cordon et fouille', $ctx->label('cordon_fouille', 'fr'));
        // Groupes : le contexte ajouté est rangé là où la communauté l'a mis.
        foreach ($ctx->groupedOptions('fr') as $g) {
            if ($g['group'] === 'controle_fouille') {
                self::assertContains('c_bouclage_de_village', array_column($g['options'], 'code'));
            }
        }
    }

    public function testCustomizationIsSanitizedAndBounded(): void
    {
        $custom = SseAcquisitionContexts::normalizeCustom([
            ['fr' => '<script>alert(1)</script>Évacuation "rapide"', 'en' => '<img src=x onerror=alert(1)>Quick', 'group' => 'inexistant'],
            ['fr' => '   '],
            ['fr' => 'Autre contexte', 'code' => 'controle'],
            ['fr' => str_repeat('x', 300)],
        ]);
        self::assertCount(3, $custom);
        self::assertSame('alert(1)Évacuation "rapide"', $custom[0]['fr']);
        self::assertSame('Quick', $custom[0]['en']);
        self::assertSame('autre', $custom[0]['group']);
        self::assertSame(0, preg_match('/[<>]/', $custom[0]['fr'] . $custom[0]['en']));
        self::assertMatchesRegularExpression('/^c_[a-z0-9_]{1,38}$/', $custom[0]['code']);
        // Un code déjà pris (ancien code compris) n'est jamais réutilisé.
        self::assertTrue($custom[1]['code'] !== 'controle');
        self::assertLessThanOrEqual(SseAcquisitionContexts::LABEL_MAX, mb_strlen($custom[2]['fr']));
        $many = array_map(static fn (int $i): array => ['fr' => 'Contexte ' . $i], range(1, 40));
        self::assertCount(SseAcquisitionContexts::CUSTOM_LIMIT, SseAcquisitionContexts::normalizeCustom($many));
        // Un réglage vers un code inconnu est ignoré.
        self::assertSame([], SseAcquisitionContexts::normalizeOverrides(['inconnu' => ['enabled' => false], 'cordon_fouille' => 'x']));
        self::assertSame(['cordon_fouille' => ['enabled' => false]], SseAcquisitionContexts::normalizeOverrides(['cordon_fouille' => ['enabled' => '0']]));
    }

    public function testSettingsAreStoredWithTheFileSettingsAndKeepTheirDefaults(): void
    {
        $defaults = SseFileRecordRepository::normalizeSettings([]);
        self::assertSame('us_sof', $defaults['acquisition_set']);
        self::assertSame([], $defaults['acquisition_overrides']);
        self::assertSame([], $defaults['acquisition_custom']);
        // Les réglages déjà enregistrés par les communautés (sans ces clés) restent valides.
        $old = SseFileRecordRepository::normalizeSettings(['default_country' => 'FRA', 'purpose_required' => false]);
        self::assertSame('FRA', $old['default_country']);
        self::assertSame('us_sof', $old['acquisition_set']);
        $police = SseFileRecordRepository::normalizeSettings(['acquisition_set' => 'police']);
        self::assertSame('police', $police['acquisition_set']);
    }

    public function testHtmlLabelsCannotInjectMarkupInTheScreens(): void
    {
        $admin = self::read('views/admin/sse_files/index.php');
        $modals = self::read('views/atak/sse/partials/case_modals.php');
        foreach ([$admin, $modals] as $src) {
            self::assertStringNotContainsString('<?= $e[\'fr\'] ?>', $src);
        }
        // Chaque libellé du catalogue passe par $h() dans le formulaire du portail et dans l'écran de gestion.
        self::assertStringContainsString('<?= $h($o[\'label\']) ?>', $modals);
        self::assertStringContainsString('<?= $h($g[\'label\']) ?>', $modals);
        self::assertStringContainsString('value="<?= $h($e[\'fr\']) ?>"', $admin);
        self::assertStringContainsString('<?= $h($e[\'code\']) ?>', $admin);
    }

    public function testViewsImportTheClassesTheyUse(): void
    {
        $admin = self::read('views/admin/sse_files/index.php');
        self::assertStringContainsString('use App\\Support\\SseAcquisitionContexts;', $admin);
        // La modale du dossier appelle la classe par son nom complet : aucune importation à oublier.
        $modals = self::read('views/atak/sse/partials/case_modals.php');
        self::assertStringContainsString('\\App\\Support\\SseAcquisitionContexts::forTenant(', $modals);
        self::assertStringContainsString('\\App\\Core\\Session::get(', $modals);
        $controller = self::read('app/Controllers/Admin/AdminSseFilesController.php');
        self::assertStringContainsString('use App\\Support\\SseAcquisitionContexts;', $controller);
        $repo = self::read('app/Repositories/SseFileRecordRepository.php');
        self::assertStringContainsString('use App\\Support\\SseAcquisitionContexts;', $repo);
        $person = self::read('app/Repositories/SsePersonRepository.php');
        self::assertStringContainsString('use App\\Support\\SseAcquisitionContexts;', $person);
    }

    public function testManagementScreenReusesTheExistingRightAndCsrf(): void
    {
        $controller = self::read('app/Controllers/Admin/AdminSseFilesController.php');
        $start = strpos($controller, 'public function contextsSave(');
        self::assertNotFalse($start);
        $body = substr($controller, (int) $start, 900);
        self::assertStringContainsString('self::allowed()', $body);
        self::assertStringContainsString('Csrf::validate', $body);
        self::assertStringContainsString("can('atak.sse.files.admin')", $controller, 'même droit que le reste des fichiers SSE');
        self::assertStringContainsString("'contextes' => 'Contextes d’acquisition'", $controller);
        self::assertStringContainsString("/back-office/renseignement/fichiers/contextes', [\$boSseFiles, 'contextsSave'], [AuthMiddleware::class]", self::read('routes/web.php'));
        self::assertStringContainsString('Csrf::field()', self::read('views/admin/sse_files/index.php'));
    }

    public function testTechnicalKeysAndContractsAreNotRenamed(): void
    {
        // Vocabulaire : seuls les libellés changent, jamais une clé, une colonne, une route ou un statut technique.
        $campaign = self::read('views/atak/sse/campaign_show.php');
        self::assertStringContainsString("'scelles'", $campaign);
        self::assertStringContainsString("\$tab === 'scelles'", $campaign);
        self::assertStringContainsString("'SEALED' => 'Sous chaîne de possession'", self::read('app/Services/Sse/SseTerrainService.php'));
        self::assertStringContainsString("'SEALED', 'TRANSFERRED'", self::read('app/Services/Sse/SseTerrainService.php'));
        self::assertStringContainsString("'suspect' =>", self::read('app/Support/SseIdWorkshopCatalog.php'));
        self::assertStringContainsString("public const CATEGORIES = ['civilian', 'military', 'suspect', 'hvt'];", self::read('app/Repositories/SseAiProfileRepository.php'));
        self::assertStringContainsString("'papiers-controles'", self::read('app/Support/SsePortalNav.php'));
        self::assertStringContainsString("'path' => 'atak/sse/papiers/controles'", self::read('app/Support/SsePortalNav.php'));
        self::assertStringContainsString('name="seal_label"', self::read('views/atak/sse/digital/device_form.php'));
        self::assertStringContainsString("'circumstances'", self::read('app/Controllers/Web/SsePortalController.php'));
    }

    public function testPoliceVocabularyIsGoneFromTheBureauScreens(): void
    {
        $banned = '/proc[èe]s-verbal|scellés?|enqu[êe]teur|garde à vue|perquisition|interpell|reddition/iu';
        $files = [
            'config/sse_case_presets.php', 'app/Support/SsePortalNav.php', 'app/Support/SseFileCatalog.php',
            'app/Support/SseIdWorkshopCatalog.php', 'app/Services/Sse/SseTerrainService.php', 'app/Services/Sse/SseCampaignService.php',
        ];
        $dir = dirname(__DIR__, 2) . '/views/atak/sse';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f instanceof \SplFileInfo && $f->isFile() && $f->getExtension() === 'php') {
                $files[] = 'views/atak/sse/' . str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
            }
        }
        foreach ($files as $rel) {
            $src = self::read($rel);
            // Les clés techniques (scelles, seal_code) ne sont pas des libellés : on les écarte avant de chercher.
            $src = (string) preg_replace("/'scelles'|\\\$tab === 'scelles'|seal_code|seal_label/", '', $src);
            self::assertSame(0, preg_match($banned, $src, $m), $rel . ' : ' . ($m[0] ?? ''));
        }
        // Le jeu « police » garde ces mots : c'est son rôle (écran de gestion et catalogue d'origine).
        self::assertSame(1, preg_match($banned, self::read('config/sse_acquisition_contexts.php')));
    }

    public function testEnglishEntriesExistForTheNewVisibleTexts(): void
    {
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach ([
            'Contextes d’acquisition', 'Jeu de contextes', 'Registre de chaîne de possession', 'Rapport de collecte',
            'Sous chaîne de possession', 'Personne d’intérêt (PoI)', 'Recherchés appréhendés', 'Vérification d’identité sur le terrain',
            'ancien : Contrôle',
        ] as $fr) {
            self::assertTrue(isset($dict[$fr]) && trim((string) $dict[$fr]) !== '', $fr);
        }
        foreach ((array) SseAcquisitionContexts::config()['contexts'] as $row) {
            self::assertSame($row['en'], $dict[$row['fr']] ?? null, $row['code']);
        }
    }

    public function testModSourcesOfferTheNewContextsAndKeepSendingKnownCodes(): void
    {
        // Liste de repli du SEEK II (le site de la communauté la remplace quand il répond : fn_sseCatalogue).
        $onload = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseCatalogueSeek.sqf');
        $codes = array_keys(SseAcquisitionContexts::catalogByCode());
        foreach ($codes as $code) {
            self::assertStringContainsString('["' . $code . '", "', $onload, $code);
        }
        foreach (['["controle", "', '["perquisition", "', '["reddition", "'] as $old) {
            self::assertStringNotContainsString($old, $onload);
        }
        $submit = self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf');
        self::assertStringContainsString('"circumstances":"%1"', $submit);
        self::assertStringContainsString('"exploitation_site_sensible"', $submit);
        foreach (['fn_buildAthenaPersonPayload', 'fn_toJsonPerson'] as $fn) {
            $src = self::read('mod/@COMSPEC_SSE/addons/network/functions/' . $fn . '.sqf');
            self::assertStringContainsString('"exploitation_site_sensible"', $src);
            self::assertStringNotContainsString('"perquisition"', $src);
        }
        // Chaque libellé du mod est dans le dictionnaire du téléphone.
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        $checked = 0;
        foreach ($codes as $code) {
            self::assertSame(1, preg_match('/\["' . $code . '", "([^"]+)"\]/', $onload, $m), $code);
            self::assertStringContainsString('["' . $m[1] . '", "', $en, $m[1]);
            $checked++;
        }
        self::assertGreaterThanOrEqual(15, $checked);
    }
}
