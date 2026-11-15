<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseWantedChecks;
use App\Support\SseWantedServiceAdmin;
use App\Support\SseWantedServices;
use PHPUnit\Framework\TestCase;

/**
 * Sélecteur et préremplissage, services créés par la communauté (création, modification, duplication, désactivation, suppression,
 * ordre, service par défaut, import et export JSON avec refus des noms réels) et contrôles de cohérence : fonctions pures, sans base.
 */
final class SseWantedServicesTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function config(): array
    {
        return SseWantedSettingsRepository::normalize([]);
    }

    /** @return array<string, mixed> */
    private static function form(array $extra = []): array
    {
        return $extra + [
            'fr' => 'Cellule des opérations du Détroit', 'en' => 'Strait Operations Cell', 'ac' => 'COD', 'family' => 'theatre', 'roles' => ['request', 'alert'],
            'auth_fr' => 'Cellule des opérations du Détroit (fictive)', 'auth_en' => 'Strait Operations Cell (fictional)', 'radio' => 'STRAIT WATCH · canal TAC 41',
            'tel' => '+1 555 0142', 'mail' => 'veille@cod.invalid', 'max' => 'confidentiel', 'class' => 'encadrement', 'thr' => 'high', 'exp' => 21,
            'threats' => ['medium', 'high'], 'types' => ['poi', 'hvt'], 'color' => 'teal', 'emblem' => 'ring', 'lang' => ['fr', 'en'],
        ];
    }

    // ------------------------------------------------------------------ Sélecteur et préremplissage

    public function testThePickerListsEveryEnabledServiceGroupedByFamilyWithItsPreview(): void
    {
        $data = SseWantedServices::clientData(self::config(), 'fr');
        self::assertGreaterThanOrEqual(40, count($data['items']));
        self::assertSame(array_keys(SseWantedServices::families()), array_keys($data['families']));
        $jifc = null;
        foreach ($data['items'] as $item) {
            if ($item['code'] === 'SVC-JIFC') {
                $jifc = $item;
            }
        }
        self::assertNotNull($jifc);
        self::assertSame('Cellule interarmées de fusion du renseignement', $jifc['name']);
        self::assertSame('JIFC', $jifc['ac']);
        self::assertStringContainsString('555 0101', $jifc['contact']);
        self::assertSame('TS', $jifc['maxLabel']);
        self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $jifc['color']);
        self::assertSame('Joint Intelligence Fusion Cell', SseWantedServices::clientData(self::config(), 'en')['items'][0]['name']);
    }

    public function testChoosingAServicePrefillsNameContactAuthorityAndDefaults(): void
    {
        $service = SseWantedServices::find('SVC-CTC', self::config());
        self::assertNotNull($service);
        $fr = SseWantedServices::prefill($service, 'fr');
        self::assertStringContainsString('Cellule de contre-terrorisme interarmées', $fr['contact_label']);
        self::assertStringContainsString('JCTC', $fr['contact_label']);
        self::assertStringContainsString('FALCON NET', $fr['contact_detail']);
        self::assertStringContainsString('555 0121', $fr['contact_detail']);
        self::assertSame('Cellule de contre-terrorisme interarmées', $fr['authority']);
        self::assertSame('confidentiel', $fr['classification']);
        self::assertSame('critical', $fr['threat_level']);
        self::assertSame(14, $fr['expiry_days']);
        self::assertNotEmpty($fr['instructions']);
        $en = SseWantedServices::prefill($service, 'en');
        self::assertStringContainsString('Joint Counter-Terrorism Cell', $en['contact_label']);
        self::assertSame('Joint Counter-Terrorism Cell', $en['authority']);
        self::assertNull(SseWantedServices::find('SVC-INCONNU', self::config()));
    }

    public function testAServiceCannotIssueAboveItsMaximumClassification(): void
    {
        $civil = SseWantedServices::find('SVC-NGO-SEC', self::config());
        $jifc = SseWantedServices::find('SVC-JIFC', self::config());
        self::assertTrue(SseWantedServices::canIssueAt($civil, 'interne'));
        self::assertFalse(SseWantedServices::canIssueAt($civil, 'encadrement'));
        self::assertFalse(SseWantedServices::canIssueAt($civil, 'tres_restreint'));
        self::assertTrue(SseWantedServices::canIssueAt($jifc, 'tres_restreint'));
        self::assertTrue(SseWantedServices::canIssueAt($jifc, 'interne'));
    }

    // ------------------------------------------------------------------ Services de la communauté

    public function testACommunityCanCreateAServiceThatFeedsThePickerAndKeepsAStableCode(): void
    {
        $r = SseWantedServiceAdmin::create(self::config(), self::form());
        self::assertTrue($r['ok'], implode(' ', $r['errors']));
        self::assertSame('CSV-COD', $r['code']);
        $service = SseWantedServices::find('CSV-COD', $r['config']);
        self::assertNotNull($service);
        self::assertSame('custom', $service['source']);
        self::assertSame('teal', $service['color']);
        self::assertStringContainsString('STRAIT WATCH', SseWantedServices::contactLine($service));
        $codes = array_column(SseWantedServices::clientData($r['config'])['items'], 'code');
        self::assertContains('CSV-COD', $codes);
        self::assertContains('SVC-JIFC', $codes, 'Le catalogue reste proposé');
        // Les réglages enregistrés puis relus donnent le même service (JSON de sse_wanted_settings).
        $reloaded = SseWantedSettingsRepository::normalize(json_decode((string) json_encode($r['config']), true));
        self::assertSame($service['fr'], SseWantedServices::find('CSV-COD', $reloaded)['fr']);
    }

    public function testCreationIsStrictOnLengthsCharactersContactsAndDuplicates(): void
    {
        $config = self::config();
        foreach ([
            ['fr' => 'AB'], ['fr' => 'Cellule <script>alert(1)</script>'], ['ac' => 'A'], ['ac' => 'TROP-LONG-SIGLE-12345'], ['tel' => '+33 1 42 00 00 00'], ['tel' => '0612345678'],
            ['mail' => 'chef@service.gouv.fr'], ['mail' => 'chef@exemple.com'], ['radio' => 'Canal <b>1</b>'],
        ] as $bad) {
            $r = SseWantedServiceAdmin::create($config, self::form($bad));
            self::assertFalse($r['ok'], json_encode($bad));
            self::assertNotSame([], $r['errors']);
            self::assertSame($config, $r['config'], 'Rien n’est enregistré en cas d’erreur');
        }
        $first = SseWantedServiceAdmin::create($config, self::form());
        self::assertTrue($first['ok']);
        $twice = SseWantedServiceAdmin::create($first['config'], self::form());
        self::assertFalse($twice['ok'], 'Un second service du même nom est refusé');
        $other = SseWantedServiceAdmin::create($first['config'], self::form(['fr' => 'Cellule des opérations du Détroit Nord', 'en' => 'Strait North', 'ac' => 'COD']));
        self::assertTrue($other['ok']);
        self::assertNotSame('CSV-COD', $other['code'], 'Deux services au même sigle reçoivent des codes distincts');
        self::assertSame('CSV-COD2', $other['code']);
    }

    public function testRealOrganisationNamesAreRefusedEvenWhenTheCommunityCreatesThem(): void
    {
        $config = self::config();
        foreach ([
            ['fr' => 'Bureau du FBI de Quantico'], ['en' => 'CIA Station Office'], ['ac' => 'NSA'], ['auth_fr' => 'Agence Interpol régionale'], ['ins_fr' => 'Prévenir la DEA avant toute action.'],
            ['radio' => 'Canal NATO 4'], ['fr' => 'Équipe SOCOM de liaison'], ['en' => 'Navy SEALs desk'],
        ] as $bad) {
            $r = SseWantedServiceAdmin::create($config, self::form($bad));
            self::assertFalse($r['ok'], json_encode($bad));
            self::assertStringContainsString('organisation réelle', implode(' ', $r['errors']));
        }
    }

    public function testEditingKeepsTheCodeAndOnlyCommunityServicesCanBeEdited(): void
    {
        $config = SseWantedServiceAdmin::create(self::config(), self::form())['config'];
        $edited = SseWantedServiceAdmin::update($config, 'CSV-COD', self::form(['fr' => 'Cellule du Détroit (renommée)', 'color' => 'olive']));
        self::assertTrue($edited['ok'], implode(' ', $edited['errors']));
        self::assertSame('Cellule du Détroit (renommée)', SseWantedServices::find('CSV-COD', $edited['config'])['fr']);
        self::assertSame('olive', SseWantedServices::find('CSV-COD', $edited['config'])['color']);
        self::assertFalse(SseWantedServiceAdmin::update($config, 'SVC-JIFC', self::form())['ok'], 'Un service du catalogue se duplique, il ne se modifie pas');
        self::assertFalse(SseWantedServiceAdmin::update($config, 'CSV-ABSENT', self::form())['ok']);
    }

    public function testACatalogueServiceIsDuplicatedThenCustomized(): void
    {
        $copy = SseWantedServiceAdmin::duplicate(self::config(), 'SVC-CTC');
        self::assertTrue($copy['ok'], implode(' ', $copy['errors']));
        self::assertSame('CSV-JCTC', $copy['code']);
        $s = SseWantedServices::find('CSV-JCTC', $copy['config']);
        self::assertSame('custom', $s['source']);
        self::assertStringContainsString('(copie)', $s['fr']);
        self::assertSame('ct', $s['family']);
        self::assertSame(SseWantedServices::find('SVC-CTC', $copy['config'])['tel'], $s['tel'], 'Les coordonnées fictives sont reprises');
        $again = SseWantedServiceAdmin::duplicate($copy['config'], 'SVC-CTC');
        self::assertTrue($again['ok']);
        self::assertStringContainsString('(copie 2)', SseWantedServices::find((string) $again['code'], $again['config'])['fr']);
        self::assertFalse(SseWantedServiceAdmin::duplicate(self::config(), 'SVC-ABSENT')['ok']);
    }

    public function testCatalogueServicesAreHiddenAndCustomOnesDeactivatedWithoutLosingAnything(): void
    {
        $config = SseWantedServiceAdmin::create(self::config(), self::form())['config'];
        $hidden = SseWantedServiceAdmin::setEnabled($config, 'SVC-JIFC', false);
        self::assertTrue($hidden['ok']);
        self::assertArrayNotHasKey('SVC-JIFC', SseWantedServices::available($hidden['config']));
        self::assertArrayHasKey('SVC-JIFC', SseWantedServices::all($hidden['config']), 'Un service masqué reste lisible pour les avis déjà posés');
        self::assertNotNull(SseWantedServices::find('SVC-JIFC', $hidden['config']));
        $shown = SseWantedServiceAdmin::setEnabled($hidden['config'], 'SVC-JIFC', true);
        self::assertArrayHasKey('SVC-JIFC', SseWantedServices::available($shown['config']));
        $off = SseWantedServiceAdmin::setEnabled($config, 'CSV-COD', false);
        self::assertArrayNotHasKey('CSV-COD', SseWantedServices::available($off['config']));
        self::assertArrayHasKey('CSV-COD', SseWantedServices::all($off['config']));
    }

    public function testDeletingIsRefusedForCatalogueServicesAndTurnsIntoADeactivationWhenAvisUseTheService(): void
    {
        $config = SseWantedServiceAdmin::create(self::config(), self::form())['config'];
        self::assertFalse(SseWantedServiceAdmin::delete($config, 'SVC-JIFC', 0)['ok']);
        $used = SseWantedServiceAdmin::delete($config, 'CSV-COD', 3);
        self::assertTrue($used['ok']);
        self::assertStringContainsString('3 avis', $used['message']);
        self::assertNotNull(SseWantedServices::find('CSV-COD', $used['config']), 'Jamais de perte : le service reste');
        self::assertArrayNotHasKey('CSV-COD', SseWantedServices::available($used['config']));
        $free = SseWantedServiceAdmin::delete($config, 'CSV-COD', 0);
        self::assertTrue($free['ok']);
        self::assertNull(SseWantedServices::find('CSV-COD', $free['config']));
    }

    public function testOrderAndDefaultServiceAreKeptPerCommunity(): void
    {
        $config = self::config();
        $first = array_keys(SseWantedServices::all($config))[0];
        $moved = SseWantedServiceAdmin::move($config, $first, 'down');
        self::assertSame($first, array_keys(SseWantedServices::all($moved['config']))[1]);
        $back = SseWantedServiceAdmin::move($moved['config'], $first, 'up');
        self::assertSame($first, array_keys(SseWantedServices::all($back['config']))[0]);
        $reordered = SseWantedServiceAdmin::reorder($config, ['SVC-CTC', 'SVC-JIFC']);
        self::assertSame(['SVC-CTC', 'SVC-JIFC'], array_slice(array_keys(SseWantedServices::all($reordered['config'])), 0, 2));
        $default = SseWantedServiceAdmin::setDefault($config, 'SVC-TTC-EA');
        self::assertTrue($default['ok']);
        self::assertSame('SVC-TTC-EA', SseWantedServices::defaultCode($default['config']));
        $hidden = SseWantedServiceAdmin::setEnabled($default['config'], 'SVC-TTC-EA', false);
        self::assertSame('', SseWantedServices::defaultCode($hidden['config']), 'Un service retiré ne reste pas par défaut');
        self::assertFalse(SseWantedServiceAdmin::setDefault($hidden['config'], 'SVC-TTC-EA')['ok']);
        self::assertFalse(SseWantedServiceAdmin::setDefault($config, 'SVC-INCONNU')['ok']);
    }

    public function testExportThenImportSharesServicesBetweenCommunities(): void
    {
        $config = SseWantedServiceAdmin::create(self::config(), self::form())['config'];
        $json = SseWantedServiceAdmin::export($config);
        $data = json_decode($json, true);
        self::assertSame('comspec.wanted-services', $data['format']);
        self::assertCount(1, $data['services']);
        self::assertArrayNotHasKey('enabled', $data['services'][0]);
        $imported = SseWantedServiceAdmin::import(self::config(), $json);
        self::assertTrue($imported['ok']);
        self::assertSame(1, $imported['added']);
        self::assertSame('CSV-COD', array_keys(SseWantedServices::all($imported['config']))[count(SseWantedServices::catalogue())], 'Le code partagé est gardé quand il est libre');
        $again = SseWantedServiceAdmin::import($imported['config'], $json);
        self::assertSame(0, $again['added']);
        self::assertSame(1, $again['skipped'], 'Un service déjà présent n’est pas dupliqué');
        $bad = SseWantedServiceAdmin::import(self::config(), '{"format":"autre","services":[]}');
        self::assertFalse($bad['ok']);
        self::assertFalse(SseWantedServiceAdmin::import(self::config(), 'pas du json')['ok']);
        self::assertFalse(SseWantedServiceAdmin::import(self::config(), str_repeat('x', SseWantedServiceAdmin::IMPORT_MAX_BYTES + 1))['ok']);
    }

    public function testImportRefusesRealNamesAndRealContactsButKeepsTheValidServices(): void
    {
        $json = (string) json_encode(['format' => 'comspec.wanted-services', 'version' => 1, 'services' => [
            ['fr' => 'Cellule de veille de la baie nord', 'en' => 'North Bay Watch Cell', 'ac' => 'CVBN', 'family' => 'theatre', 'roles' => ['alert'], 'tel' => '+1 555 0144'],
            ['fr' => 'Task force mixte FBI et CIA', 'en' => 'FBI and CIA joint task force', 'ac' => 'FBICIA', 'family' => 'ct', 'roles' => ['request']],
            ['fr' => 'Bureau fictif de contact', 'en' => 'Fictional desk', 'ac' => 'BFC', 'family' => 'coord', 'mail' => 'contact@police.gouv.fr'],
            ['fr' => 'Importé avec un code livré', 'en' => 'Imported with a shipped code', 'ac' => 'IMP', 'code' => 'SVC-JIFC', 'family' => 'coord'],
        ]], JSON_UNESCAPED_UNICODE);
        $r = SseWantedServiceAdmin::import(self::config(), $json);
        self::assertSame(2, $r['added']);
        self::assertCount(2, $r['rejected']);
        self::assertStringContainsString('organisation réelle', implode(' ', $r['rejected'][0]['reasons']));
        self::assertNull(SseWantedServices::find('CSV-FBICIA', $r['config']));
        $codes = array_keys(SseWantedServices::all($r['config']));
        self::assertSame(count($codes), count(array_unique($codes)), 'Aucun doublon de code après import');
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^(SVC|CSV)-/', $code);
        }
        self::assertSame('Cellule interarmées de fusion du renseignement', SseWantedServices::find('SVC-JIFC', $r['config'])['fr'], 'Un import ne remplace jamais un service livré');
    }

    public function testStoredSettingsAreBoundedAndIgnoreAnythingUnknown(): void
    {
        $dirty = ['services' => [
            'custom' => ['SVC-FAKE' => ['fr' => 'Pas de la communauté'], 'CSV-OK' => ['fr' => 'Service correct', 'color' => 'inconnue', 'roles' => ['mandat-inconnu']], 'bad code' => ['fr' => 'x']],
            'hidden' => ['SVC-JIFC', 'SVC-FANTOME', 42], 'order' => ['CSV-OK', 'CSV-OK', 'SVC-JIFC'], 'default' => 'SVC-FANTOME',
        ], 'process' => ['validation' => '1', 'timezone' => 'Mars/Olympus']];
        $config = SseWantedSettingsRepository::normalize($dirty);
        self::assertSame(['CSV-OK'], array_keys($config['services']['custom']));
        self::assertSame(['SVC-JIFC'], $config['services']['hidden']);
        self::assertSame(['CSV-OK', 'SVC-JIFC'], $config['services']['order']);
        self::assertSame('', $config['services']['default']);
        self::assertSame(['request'], $config['services']['custom']['CSV-OK']['roles']);
        self::assertTrue($config['process']['validation']);
        self::assertSame('Europe/Paris', $config['process']['timezone'], 'Un fuseau inconnu retombe sur le fuseau par défaut');
        self::assertTrue($config['process']['notify']);
    }

    // ------------------------------------------------------------------ Contrôles de cohérence

    /** @return array<string, mixed> */
    private static function notice(array $extra = []): array
    {
        return $extra + ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'no', 'reason' => 'Motif correct.', 'zone' => 'Corne de l’Est', 'issued_at' => '2026-11-15 10:00:00', 'expires_at' => '2026-12-15 10:00:00'];
    }

    /** @param array{blocking: list<array{code: string, text: string}>, warnings: list<array{code: string, text: string}>} $r */
    private static function codes(array $r, string $kind): array
    {
        return array_column($r[$kind], 'code');
    }

    public function testBlockingChecksStopTheNotice(): void
    {
        $now = new \DateTimeImmutable('2026-11-15 12:00:00', new \DateTimeZone('UTC'));
        $jifc = SseWantedServices::find('SVC-JIFC', self::config());
        $civil = SseWantedServices::find('SVC-NGO-SEC', self::config());
        $ctx = ['now' => $now, 'classification' => 'confidentiel'];
        self::assertSame([], self::codes(SseWantedChecks::run(self::notice(), $jifc, $ctx), 'blocking'));
        self::assertContains('expiry_order', self::codes(SseWantedChecks::run(self::notice(['expires_at' => '2026-11-10 10:00:00']), $jifc, $ctx), 'blocking'));
        self::assertContains('expiry_past', self::codes(SseWantedChecks::run(self::notice(['issued_at' => '2026-10-01 10:00:00', 'expires_at' => '2026-11-01 10:00:00']), $jifc, $ctx), 'blocking'));
        self::assertContains('reason', self::codes(SseWantedChecks::run(self::notice(['reason' => '']), $jifc, $ctx), 'blocking'));
        self::assertContains('service_class', self::codes(SseWantedChecks::run(self::notice(), $civil, $ctx), 'blocking'));
        self::assertContains('active', self::codes(SseWantedChecks::run(self::notice(), $jifc, $ctx + ['has_active' => true]), 'blocking'));
        self::assertContains('member', self::codes(SseWantedChecks::run(self::notice(), $jifc, $ctx + ['is_member' => true]), 'blocking'));
        $msg = SseWantedChecks::run(self::notice(), $civil, $ctx)['blocking'][0]['text'];
        self::assertStringContainsString('HHA-SEC', $msg);
        self::assertStringContainsString('ne peut pas émettre au niveau', $msg);
    }

    public function testWarningsDoNotBlockButAreClear(): void
    {
        $now = new \DateTimeImmutable('2026-11-15 12:00:00', new \DateTimeZone('UTC'));
        $civil = SseWantedServices::find('SVC-NGO-SEC', self::config());
        $ctx = ['now' => $now, 'classification' => 'interne'];
        $r = SseWantedChecks::run(self::notice(['armed' => 'yes', 'zone' => '', 'notice_type' => 'capture_or_kill', 'threat_level' => 'critical', 'expires_at' => null]), $civil, $ctx);
        self::assertSame([], $r['blocking']);
        foreach (['armed_zone', 'service_threat', 'service_type', 'no_expiry'] as $code) {
            self::assertContains($code, self::codes($r, 'warnings'), $code);
        }
        $soft = SseWantedChecks::run(self::notice(['notice_type' => 'capture_or_kill', 'threat_level' => 'low']), null, $ctx);
        self::assertContains('type_threat', self::codes($soft, 'warnings'));
        $long = SseWantedChecks::run(self::notice(['expires_at' => '2028-01-01 10:00:00']), null, $ctx);
        self::assertContains('long_expiry', self::codes($long, 'warnings'));
        self::assertSame([], self::codes(SseWantedChecks::run(self::notice(), null, $ctx), 'blocking'), 'Sans service (« Autre ») : aucun blocage de classification');
    }
}
