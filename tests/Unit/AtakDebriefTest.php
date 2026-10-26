<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAtakDebriefController;
use App\Repositories\AtakDebriefRepository;
use App\Support\AarCustomForm;
use App\Support\AtakDebriefSpec;
use PHPUnit\Framework\TestCase;

/** CR de fin d'opération imposé sur l'ATAK : fiche du téléphone, réponses, états du suivi, page et traductions. */
final class AtakDebriefTest extends TestCase
{
    /** Fiche SQF -> JSON (les "" de SQF deviennent des guillemets échappés), comme parseSimpleArray la lirait. */
    private static function parse(string $spec): array
    {
        $json = preg_replace_callback('/"((?:[^"]|"")*)"/', static fn (array $m): string => (string) json_encode(str_replace('""', '"', $m[1])), $spec);

        return json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);
    }

    public function testPhoneSpecIsASimpleArrayWithEscapedText(): void
    {
        $q = AarCustomForm::normalizeFields([
            ['label' => 'Votre "rôle" | poste', 'type' => 'select', 'options' => ['A', 'B"b']],
            ['label' => "Texte\nlibre", 'type' => 'textarea', 'required' => 1],
        ]);
        $spec = AtakDebriefSpec::phoneSpec('fill', 'Op "Aigle"', 'Intro', 'ignoré', $q);
        $a = self::parse($spec);

        self::assertSame(['DBF1', 'fill', 'Op "Aigle"', 'Intro', ''], array_slice($a, 0, 5));
        self::assertSame(['q1', 'select', 'Votre "rôle" / poste', '', 0, ['A', 'B"b']], $a[5][0]);
        self::assertSame('Texte libre', $a[5][1][2]);
        self::assertSame(1, $a[5][1][4]);
        self::assertStringNotContainsString("\n", $spec);
        self::assertStringNotContainsString('|', $spec);
    }

    public function testPhoneSpecStaysUnderTheLinkBudget(): void
    {
        $big = AarCustomForm::normalizeFields(array_fill(0, 40, ['label' => str_repeat('é', 150), 'type' => 'text']));
        $spec = AtakDebriefSpec::phoneSpec('fill', 'x', '', '', $big);
        self::assertLessThanOrEqual(AtakDebriefSpec::MAX_BYTES, strlen($spec));
        self::assertGreaterThan(0, count(self::parse($spec)[5]));

        $read = AtakDebriefSpec::phoneSpec('read', 'x', '', str_repeat('Long texte à lire. ', 400), $big);
        self::assertLessThanOrEqual(AtakDebriefSpec::MAX_BYTES, strlen($read));
        self::assertSame([], self::parse($read)[5], 'Un CR à lire ne porte pas de questions.');
    }

    public function testAnswersAndUids(): void
    {
        $answers = AtakDebriefSpec::answers(['a_summary' => 'ok', 'answers' => ['comms' => 'Bonnes'], 'debrief_id' => 3, 'a_x y' => 'z']);
        self::assertSame(['comms' => 'Bonnes', 'summary' => 'ok', 'xy' => 'z'], $answers);
        self::assertSame(['76561198000000001', '76561198000000002'], AtakDebriefSpec::parseUids("76561198000000001, 76561198000000002\nfoo 123"));

        $shown = AtakDebriefSpec::present([], ['summary' => 'A', 'extra' => 'B']);
        self::assertSame('Déroulé de votre mission', $shown[0]['label']);
        self::assertSame(['label' => 'extra', 'value' => 'B'], end($shown));
    }

    public function testTrackingStates(): void
    {
        self::assertSame('sent', AtakDebriefRepository::state([], 'fill'));
        self::assertSame('delivered', AtakDebriefRepository::state(['msg_delivered_at' => '2026-10-26 10:00:00'], 'fill'));
        self::assertSame('opened', AtakDebriefRepository::state(['opened_at' => '2026-10-26 10:00:00'], 'fill'));
        self::assertSame('submitted', AtakDebriefRepository::state(['submitted_at' => '2026-10-26 10:00:00', 'opened_at' => 'x'], 'fill'));
        self::assertSame('read', AtakDebriefRepository::state(['msg_acked_at' => '2026-10-26 10:00:00'], 'read'));
        self::assertSame('expired', AtakDebriefRepository::state(['msg_expires_at' => '2020-01-01 00:00:00'], 'fill'));
        self::assertSame('u:12', AtakDebriefRepository::whoKey(12, '76561198000000001'));
        self::assertSame('s:76561198000000001', AtakDebriefRepository::whoKey(null, '76561198000000001'));

        self::assertTrue(AdminAtakDebriefController::matches(['state' => 'delivered', 'online' => true], 'attente'));
        self::assertTrue(AdminAtakDebriefController::matches(['state' => 'none', 'online' => false], 'non_recu'));
        self::assertTrue(AdminAtakDebriefController::matches(['state' => 'read', 'online' => false], 'remis'));
        self::assertTrue(AdminAtakDebriefController::matches(['state' => 'read', 'online' => false], 'hors_ligne'));
    }

    public function testPageIsWiredAndTranslated(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['/back-office/atak/suivi-cr', '/back-office/atak/suivi-cr/imposer', '/back-office/atak/suivi-cr/reglages', '/back-office/atak/suivi-cr/{id}/relancer'] as $path) {
            self::assertStringContainsString("'" . $path . "'", $routes);
        }
        self::assertLessThan(strpos($routes, "'/back-office/atak/comptes-rendus/{id}'"), strpos($routes, "'/back-office/atak/suivi-cr'"));
        self::assertStringContainsString("'back-office/atak/suivi-cr'", (string) file_get_contents($root . '/config/back_office_pages.php'));
        self::assertStringContainsString("back-office/atak/suivi-cr", (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString("'atak_debrief'", \Tests\Support\MigrationPipelineSource::read($root));
        self::assertFileExists($root . '/public/assets/css/back-office-debrief.css');

        $en = require $root . '/lang/en/site_auto.php';
        foreach (array_merge(array_values(AdminAtakDebriefController::FILTERS), array_values(AtakDebriefRepository::STATES), array_values(AtakDebriefSpec::MODES)) as $label) {
            self::assertArrayHasKey($label, $en, $label);
        }
        foreach (AtakDebriefSpec::BUILTIN as $q) {
            self::assertArrayHasKey($q['label'], $en, $q['label']);
        }
    }
}
