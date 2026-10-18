<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakStartupCheckService;
use App\Services\Security\FileRateLimiter;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

/**
 * Check de démarrage du mod relayé dans le salon Discord de debug (POST /api/atak/startup-check).
 */
final class AtakStartupCheckServiceTest extends TestCase
{
    private const WEBHOOK = 'https://discord.com/api/webhooks/1/test';

    /** @return array<string, string> */
    private function body(string $session): array
    {
        return [
            'atak' => '1.75.0',
            'config' => '1.75.0',
            'link' => '2.0.81',
            'arma' => '2.18',
            'player' => 'Tango',
            'callsign' => 'Alpha 1-1',
            'player_key' => 'k' . bin2hex(random_bytes(6)),
            'session' => $session,
            'world' => 'Altis',
            'mission' => 'Op Test',
            'mp' => '1',
            'step_mod' => 'ok|1.75.0',
            'step_dll' => 'warn|DLL 2.0.80 chargée, 2.0.81 attendue',
            'step_errors' => 'fail|Erreur dans C:\\Users\\tetar\\Documents https://github.com/x 192.168.1.42 @everyone',
            'step_inconnu' => 'ok|ignoré',
        ];
    }

    /** @param list<array<string, mixed>> $sent */
    private function service(array &$sent, ?string $webhook = self::WEBHOOK, ?\Closure $community = null): AtakStartupCheckService
    {
        return new AtakStartupCheckService($webhook, new FileRateLimiter(), sender: function (string $url, array $embed) use (&$sent): array {
            $sent[] = $embed + ['_url' => $url];

            return ['ok' => true];
        }, communityChannel: $community ?? static fn (string $name): ?array => null);
    }

    public function testPostsOneEmbedPerPlayerSession(): void
    {
        $sent = [];
        $svc = $this->service($sent);
        $body = $this->body('s' . bin2hex(random_bytes(6)));

        self::assertSame('posted', $svc->handle($body)['status']);
        self::assertSame('duplicate', $svc->handle($body)['status']);
        self::assertCount(1, $sent);

        $embed = $sent[0];
        self::assertStringStartsWith('❌ Check de démarrage · Tango (Alpha 1-1)', $embed['title']);
        self::assertStringContainsString('1 erreur, 1 avertissement. · 1 error, 1 warning.', $embed['description']);
        self::assertStringContainsString('⚠️ **DLL COMSPEC Link / Link DLL**', $embed['description']);
        self::assertStringNotContainsString('ignoré', $embed['description']);
        self::assertSame('ATAK 1.75.0 · Link 2.0.81 · Arma 2.18', $embed['fields'][1]['value']);
    }

    public function testLinkReportShowsAssignedPhoneAndTerminal(): void
    {
        $sent = [];
        $body = $this->body('s' . bin2hex(random_bytes(6)));
        $body['trigger'] = 'link';
        $body['step_link'] = 'ok|lié · 1er RPIMa';
        $body['step_phone'] = 'ok|n° 06 12 34 56 78 · IMEI 35-123456-123456-7 · attribué par Athena (FR)';
        $body['step_terminal'] = 'ok|Samsung Galaxy S7 · modèle S7';
        $this->service($sent)->handle($body);

        $embed = $sent[0];
        self::assertStringContainsString('Liaison en jeu · Tango (Alpha 1-1)', $embed['title']);
        self::assertStringContainsString('✅ **Numéro et IMEI / Number and IMEI** · n° 06 12 34 56 78 · IMEI 35-123456-123456-7', $embed['description']);
        self::assertStringContainsString('✅ **Terminal** · Samsung Galaxy S7', $embed['description']);
        self::assertSame('Liaison en jeu · In-game link', $embed['footer']['text']);
    }

    public function testUnknownTriggerFallsBackToStartupCheck(): void
    {
        $body = $this->body('x');
        $body['trigger'] = 'nope';
        self::assertSame('start', AtakStartupCheckService::normalize($body)['trigger'] ?? null);
        $body['trigger'] = 'link_failed';
        self::assertSame('link_failed', AtakStartupCheckService::normalize($body)['trigger'] ?? null);
    }

    public function testGameTextIsScrubbedForDiscord(): void
    {
        $sent = [];
        $this->service($sent)->handle($this->body('s' . bin2hex(random_bytes(6))));
        $text = json_encode($sent[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        self::assertStringNotContainsString('github', $text);
        self::assertStringNotContainsString('tetar', $text);
        self::assertStringNotContainsString('1.42', $text);
        self::assertStringNotContainsString('@everyone', $text);
        self::assertStringContainsString('192.168.x.x', $text);
    }

    public function testWithoutChannelNothingIsPosted(): void
    {
        $sent = [];
        $result = $this->service($sent, '')->handle($this->body('s1'));

        self::assertTrue($result['ok']);
        self::assertSame('no_channel', $result['status']);
        self::assertSame([], $sent);
    }

    public function testLinkedPlayerGoesToHisCommunityChannel(): void
    {
        $sent = [];
        $asked = [];
        $community = static function (string $name) use (&$asked): ?array {
            $asked[] = $name;

            return $name === '1er RPIMa' ? ['tenant' => 7, 'url' => 'https://discord.com/api/webhooks/7/commu'] : null;
        };
        $body = $this->body('s' . bin2hex(random_bytes(6)));
        $body['trigger'] = 'link';
        $body['step_link'] = 'ok|lié · 1er RPIMa';

        self::assertSame('posted', $this->service($sent, '', $community)->handle($body)['status']);
        self::assertSame(['1er RPIMa'], $asked);
        self::assertCount(1, $sent);
        self::assertSame('https://discord.com/api/webhooks/7/commu', $sent[0]['_url']);

        // Avec le salon facultatif de la plateforme : une copie de plus.
        $sent = [];
        $body['session'] = 's' . bin2hex(random_bytes(6));
        $this->service($sent, self::WEBHOOK, $community)->handle($body);
        self::assertSame(['https://discord.com/api/webhooks/7/commu', self::WEBHOOK], array_column($sent, '_url'));
    }

    public function testUnlinkedPlayerHasNoCommunity(): void
    {
        self::assertSame('', AtakStartupCheckService::communityName(['step_link' => 'fail|non lié : jeton expiré']));
        self::assertSame('1er RPIMa', AtakStartupCheckService::communityName(['step_link' => 'ok|lié · 1er RPIMa']));
        self::assertSame('Task Force', AtakStartupCheckService::communityName(['community' => 'Task Force', 'step_link' => 'ok|lié · Autre']));

        $sent = [];
        $body = $this->body('s' . bin2hex(random_bytes(6)));
        $body['step_link'] = 'fail|non lié';
        $result = $this->service($sent, '', static fn (string $name): ?array => ['tenant' => 7, 'url' => 'https://discord.com/api/webhooks/7/commu'])->handle($body);
        self::assertSame('no_channel', $result['status']);
        self::assertSame([], $sent);
    }

    public function testRejectsBodiesThatAreNotModReports(): void
    {
        $sent = [];
        self::assertSame('invalid', $this->service($sent)->handle(['player' => 'x'])['status']);
        self::assertSame('invalid', $this->service($sent)->handle(['atak' => '1.75.0'])['status']);
        self::assertSame([], $sent);
    }

    public function testEndpointNeedsNoApiKey(): void
    {
        self::assertFalse(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/startup-check'));
    }
}
