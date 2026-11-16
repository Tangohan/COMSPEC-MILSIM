<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseAlertReportForm;
use App\Support\SseWantedCodes;
use PHPUnit\Framework\TestCase;

/**
 * Contrat du formulaire de signalement : champs montrés et exigés d'après la conduite à tenir (codes A-…) et le niveau de la fiche.
 * Les règles sont des données (config/sse_alert_report_form.php), vérifiées ici contre le catalogue des conduites.
 */
final class SseAlertReportFormTest extends TestCase
{
    protected function tearDown(): void
    {
        SseAlertReportForm::useConfig(null);
    }

    public function testWithoutAnyRuleTheFormIsTheBaseOne(): void
    {
        $f = SseAlertReportForm::resolve([], 1);

        self::assertSame(['dir', 'vehicle', 'photo'], $f['fields']);
        self::assertSame([], $f['required']);
        self::assertFalse($f['urgent']);
        self::assertSame([], $f['notes']);
    }

    public function testDoNotEngageAloneRemovesThePhotoAndAsksForDistanceCountAndWeapons(): void
    {
        $f = SseAlertReportForm::resolve(['A-NOI'], 2);

        self::assertNotContains('photo', $f['fields']);
        foreach (['distance', 'people', 'armed'] as $field) {
            self::assertContains($field, $f['fields']);
        }
        self::assertCount(1, $f['notes']);
        self::assertNotSame('', $f['notes'][0][0]);
        self::assertNotSame('', $f['notes'][0][1]);
    }

    public function testWhatIsRemovedAlwaysBeatsWhatIsAdded(): void
    {
        $f = SseAlertReportForm::resolve(['A-PHO', 'A-NOI'], 1);
        self::assertNotContains('photo', $f['fields'], 'la photo encouragée ne l’emporte pas sur « ne pas approcher »');

        self::assertContains('photo', SseAlertReportForm::resolve(['A-PHO'], 1)['fields']);
        self::assertNotContains('photo', SseAlertReportForm::resolve([], 4)['fields'], 'niveau 4 : pas de photo');
    }

    public function testLiveCaptureAsksForPositionAndHeadingAndRequiresTheHeading(): void
    {
        $f = SseAlertReportForm::resolve(['A-DNG', 'A-LIV', 'A-REP'], 3);

        self::assertSame(['dir'], $f['required']);
        self::assertContains('people', $f['fields']);
        self::assertContains('armed', $f['fields']);
        self::assertTrue($f['urgent'], 'rendre compte : le service et le délai passent en tête');
    }

    public function testObservationAsksForDurationBehaviourAndVehicle(): void
    {
        $f = SseAlertReportForm::resolve(['A-OBS', 'A-TRK'], 1);

        foreach (['duration', 'behaviour', 'vehicle', 'dir'] as $field) {
            self::assertContains($field, $f['fields']);
        }
        self::assertContains('photo', $f['fields']);
        self::assertFalse($f['urgent']);
    }

    public function testTheTopLevelAndAnArmedSheetAreUrgentAndAskForWeapons(): void
    {
        self::assertTrue(SseAlertReportForm::resolve([], 4)['urgent']);
        self::assertContains('armed', SseAlertReportForm::resolve([], 4)['fields']);
        self::assertContains('armed', SseAlertReportForm::resolve([], 1, 'yes')['fields']);
        self::assertNotContains('armed', SseAlertReportForm::resolve([], 1, 'no')['fields']);
    }

    public function testNothingIsRequiredThatIsNotDisplayed(): void
    {
        SseAlertReportForm::useConfig([
            'default' => ['dir'],
            'fields' => SseAlertReportForm::config()['fields'],
            'measures' => ['A-ONE' => ['require' => ['people', 'dir', 'place']], 'A-TWO' => ['drop' => ['people', 'dir']]],
            'levels' => [], 'armed' => [],
        ]);
        // « people » n'est jamais ajouté, « dir » est retiré : seul le lieu (champ commun) peut être exigé.
        self::assertSame(['place'], SseAlertReportForm::resolve(['A-ONE', 'A-TWO'], 1)['required']);
        self::assertSame(['dir', 'place'], SseAlertReportForm::resolve(['A-ONE'], 1)['required']);
    }

    public function testEveryRuleIsKeyedByARealConductCodeAndUsesKnownFields(): void
    {
        $cfg = SseAlertReportForm::config();
        $catalogue = SseWantedCodes::catalogue();
        $fields = array_keys($cfg['fields']);
        foreach ($cfg['measures'] as $code => $rule) {
            self::assertArrayHasKey($code, $catalogue['measures'], $code . ' doit exister dans le catalogue des conduites');
            self::assertSame([], array_diff((array) ($rule['add'] ?? []), $fields), $code);
            self::assertSame([], array_diff((array) ($rule['drop'] ?? []), $fields), $code);
            self::assertSame([], array_diff((array) ($rule['require'] ?? []), array_merge($fields, ['place'])), $code);
            if (isset($rule['note'])) {
                self::assertCount(2, $rule['note'], $code . ' : une consigne en français et en anglais');
                self::assertNotSame('', trim((string) $rule['note'][1]));
            }
        }
        foreach ($cfg['fields'] as $id => $def) {
            self::assertCount(2, $def['label'], $id);
        }
        self::assertSame([1, 2, 3, 4], array_keys($cfg['levels']));
    }

    public function testOnlyDisplayedFieldsAreReadAndTheyAreCleaned(): void
    {
        $form = SseAlertReportForm::resolve(['A-NOI'], 2);
        $v = SseAlertReportForm::sanitize($form, ['people' => '250', 'armed' => 'yes', 'distance' => 'bogus', 'duration' => '10 minutes', 'behaviour' => 'calme', 'dir' => 'nord', 'photo' => 'x']);

        self::assertSame('99', $v['people'], 'borné');
        self::assertSame('yes', $v['armed']);
        self::assertSame('', $v['distance'], 'choix inconnu ignoré');
        self::assertSame('nord', $v['dir']);
        self::assertArrayNotHasKey('duration', $v, 'champ non affiché : jamais lu');
        self::assertArrayNotHasKey('behaviour', $v);
        self::assertArrayNotHasKey('photo', $v);
        self::assertSame('', SseAlertReportForm::sanitize($form, ['people' => '-3'])['people']);
        self::assertSame('', SseAlertReportForm::sanitize($form, ['people' => '2.5'])['people']);
    }

    public function testMissingFieldsAreNamedInTheReadersLanguage(): void
    {
        $form = SseAlertReportForm::resolve(['A-LIV'], 1);

        self::assertSame(['Indiquez la direction prise.'], SseAlertReportForm::missing($form, ['dir' => ' '], 'fr'));
        self::assertSame(['Say which way the subject went.'], SseAlertReportForm::missing($form, [], 'en'));
        self::assertSame([], SseAlertReportForm::missing($form, ['dir' => 'nord'], 'fr'));
    }

    public function testObservedFieldsWithoutAColumnAreWrittenAsLabelledLines(): void
    {
        $lines = SseAlertReportForm::foldedLines(['people' => '3', 'armed' => 'yes', 'distance' => 'far', 'duration' => '10 minutes', 'dir' => 'nord'], 'fr');
        self::assertSame(['Nombre de personnes : 3', 'Armement vu : Arme vue', 'Distance d’observation : Plus de 200 m', 'Durée de l’observation : 10 minutes'], $lines);
        self::assertSame('Weapons seen : Weapons seen', SseAlertReportForm::foldedLines(['armed' => 'yes'], 'en')[0]);
    }
}
