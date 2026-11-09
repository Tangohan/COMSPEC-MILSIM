<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Fenêtre « Charger un replay » d'Overwatch : structure accessible, même API, métadonnées ajoutées sans rupture. */
final class OverwatchReplayPickerAssetTest extends TestCase
{
    private static function read(string $path): string
    {
        $c = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($c);

        return $c;
    }

    public function testModalIsAccessibleAndKeyboardDriven(): void
    {
        $js = self::read('public/assets/js/atak-overwatch-replay.js');
        foreach (['role="dialog"', 'aria-modal="true"', 'aria-labelledby="owr-title"', 'role="listbox"', 'role="option"', 'aria-selected', 'aria-activedescendant', 'aria-live="polite"', 'role="alert"'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        foreach (["'Escape'", "'Tab'", "'ArrowDown'", "'ArrowUp'", "'Enter'", 'shell.inert', 'opener.focus'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        // Recherche, filtres (date, carte, mission), tri, départ, vitesse, options, états.
        foreach (['data-owr-q', 'data-owr-filter="date"', 'data-owr-filter="map"', 'data-owr-filter="mission"', 'data-owr-filter="sort"', 'data-owr-start', 'data-owr-speed', 'data-owr-pref="trails"', 'Aucun enregistrement disponible', 'Chargement des enregistrements…', 'data-owr-retry'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
    }

    public function testModalReusesTheExistingReplayApiOnly(): void
    {
        $js = self::read('public/assets/js/atak-overwatch-replay.js');
        self::assertStringContainsString('/api/replay/sessions/', $js);
        self::assertStringContainsString('/api/replay/events/', $js);
        self::assertStringNotContainsString('/api/replay/list', $js);
        $ops = self::read('public/assets/js/atak-overwatch-ops.js');
        self::assertStringContainsString("'/api/replay/mission/'", $ops);
        self::assertStringContainsString('replayLoad: replayLoad', $ops);
        $routes = self::read('routes/web.php');
        self::assertSame(1, substr_count($routes, "'/api/replay/sessions/{missionId}'"));
    }

    public function testSessionListKeepsLegacyFieldsAndAddsMetadata(): void
    {
        $repo = self::read('app/Repositories/ReplayRepository.php');
        foreach (["'key' => \$key", "'label' => AtakMissionKey::label(\$key)", "'from' =>", "'to' =>", "'samples' =>"] as $legacy) {
            self::assertStringContainsString($legacy, $repo, $legacy);
        }
        foreach (["'mission' =>", "'map' =>", "'units' =>", "'durationSeconds' =>", "'approxBytes' =>", 'COUNT(DISTINCT unit_id)'] as $added) {
            self::assertStringContainsString($added, $repo, $added);
        }
        // Isolation inchangée : la requête reste filtrée par mission_id (déjà cloisonné par le contrôleur).
        self::assertStringContainsString('WHERE mission_id = ? AND mission_key IS NOT NULL', $repo);
    }

    public function testViewOpensModalInsteadOfTheOldSelects(): void
    {
        $view = self::read('views/atak-overwatch-beta.php');
        self::assertStringContainsString('id="ow-replay-change" data-ow-replay', $view);
        self::assertStringContainsString('class="ow-select owr-legacy"', $view);
        self::assertStringContainsString('<option value="8">8×</option>', $view);
        $css = self::read('public/assets/css/atak-overwatch-replay.css');
        self::assertStringContainsString('@media (max-width:760px)', $css);
        self::assertStringContainsString('body.ow-theme-day .owr-back', $css);
        self::assertStringContainsString('.owr-legacy{display:none!important}', $css);
    }
}
