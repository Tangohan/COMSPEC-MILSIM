<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerminalsAssetTest extends TestCase
{
    public function testLiaisonBadgeHasReservedWidthAndDoesNotScale(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $start = strpos($css, '.atak-terminal-card__head {');
        $end = strpos($css, '.atak-terminal-card__rows {');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertGreaterThan($start, $end);
        $block = substr($css, $start, $end - $start);

        self::assertStringContainsString('flex-wrap: nowrap;', $block);
        self::assertStringContainsString('align-items: center;', $block);
        self::assertStringContainsString('flex: 0 0 8.25rem;', $block);
        self::assertStringContainsString('white-space: nowrap;', $block);
        self::assertStringContainsString('text-align: center;', $block);
        self::assertStringContainsString('text-overflow: ellipsis;', $block);
        self::assertStringContainsString('atak-terminal-state-pulse', $block);
        self::assertStringNotContainsString('align-items: baseline;', $block);
        self::assertStringNotContainsString('atak-badge-pulse', $block);
        self::assertStringNotContainsString('scale(', $block);
    }

    public function testCardShowsCertificateAndRenewAction(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-terminals.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak.css');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakRealismRepository.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/AtakController.php');

        self::assertStringContainsString("row('Certificat'", $js);
        self::assertStringContainsString("row('Référence'", $js);
        self::assertStringContainsString("row('Échéance'", $js);
        self::assertStringContainsString('Émettre un certificat', $js);
        self::assertStringContainsString('atak-terminal-card__actions', $css);
        self::assertStringContainsString('/api/atak/terminals/{id}/certificate/regenerate', $routes);
        self::assertStringContainsString('function regenerateCertificateForTerminal', $repo);
        self::assertStringContainsString("'canManageCertificates'", $ctrl);
    }

    public function testCardShowsPackVersionsOutsideTheTypeRow(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terminals.js');

        self::assertStringContainsString("row('Overwatch'", $js);
        self::assertStringContainsString("row('Liaison Athena'", $js);
        self::assertStringContainsString('function typeLabel(', $js);
        self::assertStringContainsString('function versionRows(', $js);
        self::assertStringContainsString('packVersionFromPlatform', $js);
        self::assertStringContainsString('t.mod_version', $js);
        self::assertStringContainsString('t.extension_version', $js);
        self::assertStringContainsString('versionRows(t, extra)', $js);
        self::assertStringContainsString('certRows(t)', $js);
        self::assertStringContainsString('data-regen-cert', $js);
        self::assertStringContainsString('Renouveler le certificat', $js);
        self::assertStringContainsString('/certificate/regenerate', $js);
        self::assertStringContainsString('t.last_client_ip', $js);
        self::assertStringNotContainsString(
            "t.terminal_type === 'phone' ? 'Téléphone' : (t.platform_label || t.terminal_type || 'Poste')",
            $js
        );
    }

    public function testLiaisonBadgeUsesLiveSignalNotParkStatus(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terminals.js');

        self::assertStringContainsString('function terminalLiveStatus(', $js);
        self::assertStringContainsString('function findUnitForTerminal(', $js);
        self::assertStringContainsString('TERMINAL_LIVE_MS', $js);
        self::assertStringContainsString("s === 'delayed') return { label: 'Signal différé'", $js);
        self::assertStringContainsString('findUnitForTerminal(t, units)', $js);
        self::assertStringContainsString('statusLabel(terminalLiveStatus(t, unit))', $js);
        self::assertStringNotContainsString("s === 'linked' || s === 'active'", $js);
        self::assertStringNotContainsString('(unit && unit.status) || t.status', $js);
    }
}
