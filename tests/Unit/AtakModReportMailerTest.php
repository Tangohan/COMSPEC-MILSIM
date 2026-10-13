<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Monitoring\AtakModReportMailer;
use PHPUnit\Framework\TestCase;

final class AtakModReportMailerTest extends TestCase
{
    private function report(string $detail = ''): array
    {
        return [
            'id' => 12,
            'channel' => 'Liaison',
            'message' => "La carte ne se charge plus <script>\napres reconnexion",
            'detail' => $detail,
            'source' => 'player',
            'steam_uid' => '76561198000000000',
            'player_name' => 'Doe',
            'callsign' => 'VIPER 1-1',
            'client_ip' => '1.2.3.4',
            'mod_version' => '1.85.0',
            'extension_version' => '2.0.82',
            'arma_build' => '152000',
        ];
    }

    public function testComposeNamesTypePlayerVersionAndLink(): void
    {
        [$subject, $html, $text] = AtakModReportMailer::compose($this->report("liaison=linked\n--- journal ---\nligne"), 'https://x/admin/atak-mod-reports');

        self::assertStringContainsString('Liaison', $subject);
        self::assertStringContainsString('VIPER 1-1 (Doe)', $subject);
        self::assertStringContainsString('1.85.0', $text);
        self::assertStringContainsString('Journal joint : oui', $text);
        self::assertStringContainsString('https://x/admin/atak-mod-reports', $html);
        self::assertStringContainsString('--- journal ---', $html);
        self::assertStringNotContainsString("<script>", $html);
        self::assertStringNotContainsString("\n", $subject);
    }

    public function testComposeWithoutLogAndLimitNote(): void
    {
        [, $html, $text] = AtakModReportMailer::compose($this->report(), 'https://x', true);

        self::assertStringContainsString('Journal joint : non', $text);
        self::assertStringNotContainsString('Détail technique', $text);
        self::assertStringContainsString('signalements cette heure', $html);
    }
}
