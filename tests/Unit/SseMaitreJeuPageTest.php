<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseMaitreJeuPageTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @param list<array<string,mixed>> $persons
     */
    private function render(array $entries, array $persons, string $searchQuery = ''): string
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/app/Support/helpers.php';
        ob_start();
        (static function () use ($root, $entries, $persons, $searchQuery): void {
            require $root . '/views/atak/sse/partials/mj_page.php';
        })();

        return (string) ob_get_clean();
    }

    /** @return array<string,mixed> */
    private function entry(int $id, string $level = 'surveillance', ?string $notes = 'Motif'): array
    {
        return [
            'id' => $id, 'display_name' => 'Nom ' . $id, 'alias' => 'Al' . $id, 'threat_level' => $level,
            'threat_level_label' => ucfirst($level), 'notes' => $notes, 'game_ref' => null, 'known_docs' => [],
            'created_at' => '2026-11-01 10:00:00',
        ];
    }

    public function testDashboardCountsAndTabsAreRendered(): void
    {
        $html = $this->render(
            [$this->entry(3, 'hvt'), $this->entry(2, 'prioritaire'), $this->entry(1)],
            [['id' => 9, 'display_name' => 'Fiche', 'alias' => '', 'status_label' => 'Libre']]
        );
        self::assertStringContainsString('role="tablist"', $html);
        self::assertSame(3, substr_count($html, 'role="tab"'));
        self::assertSame(3, substr_count($html, 'role="tabpanel"'));
        self::assertMatchesRegularExpression('#Prioritaires</div>\s*<div class="metric-value">2</div>#', $html);
        self::assertStringContainsString('01/11/2026', $html);
        self::assertStringContainsString('badge--red', $html);
    }

    public function testCreateFormKeepsRouteFieldsAndReturnTarget(): void
    {
        $html = $this->render([], []);
        self::assertStringContainsString('atak/sse/croisements/watchlist"', $html);
        foreach (['last_name', 'first_name', 'alias', 'threat_level', 'notes', '_csrf_token'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $html);
        }
        self::assertStringContainsString('name="return_to" value="maitre-jeu"', $html);
        self::assertStringContainsString('La liste est vide', $html);
    }

    public function testRemovalGoesThroughConfirmDialogAndKeepsRoute(): void
    {
        $html = $this->render([$this->entry(7)], []);
        self::assertStringContainsString('data-mj-detail="7"', $html);
        self::assertStringContainsString('atak\/sse\/croisements\/watchlist\/7\/retirer', $html);
        self::assertStringContainsString('id="mj-remove"', $html);
        self::assertStringNotContainsString('confirm(', $html);
    }

    public function testEntryJsonCannotBreakOutOfScript(): void
    {
        $e = $this->entry(1, 'surveillance', '</script><b>x</b>');
        $html = $this->render([$e], []);
        self::assertStringNotContainsString('</script><b>', $html);
    }

    public function testNoEyebrowLabelsAndEverySectionHasADescriptionLine(): void
    {
        $html = $this->render([$this->entry(1)], []);
        self::assertStringNotContainsString('panel-index', $html);
        self::assertSame(3, preg_match_all('#<div class="mj-panel__head">\s*<h2>[^<]+</h2>\s*<p>[^<]+</p>#', $html));
        self::assertStringNotContainsString('eyebrow', $this->read('public/assets/css/mj.css'));
    }

    public function testDialogsAreNativeModalsWithEscapeAndFocusHandling(): void
    {
        $js = $this->read('public/assets/js/mj-page.js');
        self::assertStringContainsString('showModal', $js);
        self::assertStringContainsString('lastOpener.focus()', $js);
        self::assertStringNotContainsString('confirm(', $js);
        self::assertStringNotContainsString('alert(', $js);
        $html = $this->render([], []);
        self::assertSame(3, substr_count($html, '<dialog '));
        self::assertSame(3, preg_match_all('#<dialog [^>]*aria-labelledby="mj-(?:create|drawer|remove)-title"#', $html));
    }

    public function testViewLoadsItsAssetsThroughAssetUrl(): void
    {
        $view = $this->read('views/atak/sse/maitre_jeu.php');
        self::assertStringContainsString("asset_url('assets/css/mj.css')", $view);
        self::assertStringContainsString("asset_url('assets/js/mj-page.js')", $view);
        self::assertStringContainsString('<script defer', $view);
        self::assertSame(0, preg_match('/font-size:\s*(?:[0-9]|10|11)(?:\.\d+)?px/', $this->read('public/assets/css/mj.css')));
    }

    public function testControllerStillGatesThePageOnManageRight(): void
    {
        $src = $this->read('app/Controllers/Web/SsePortalController.php');
        $i = (int) strpos($src, 'public function gameMaster');
        self::assertStringContainsString('canManage()', substr($src, $i, 300));
    }
}
