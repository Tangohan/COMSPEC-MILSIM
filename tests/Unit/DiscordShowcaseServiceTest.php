<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\DiscordShowcaseService;
use App\Support\HtmlTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Vitrine Discord des communautés : demande, validation (simulation ou réelle), fréquence, blocage,
 * nettoyage du texte libre (ni mention, ni lien) et forme du message envoyé au webhook.
 */
final class DiscordShowcaseServiceTest extends TestCase
{
    private const HOOK = 'https://discord.com/api/webhooks/1290000000000000001/AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';

    /** @var array<string, string> */
    private array $store = [];

    /** @var list<array{0:string, 1:string, 2:?array<string, mixed>}> */
    private array $calls = [];

    /** @var list<array{status:int, body:string}> */
    private array $responses = [];

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->store = [];
        $this->calls = [];
        $this->responses = [];
        $this->now = new \DateTimeImmutable('2026-10-19 14:00:00');
        HtmlTranslator::useDictionary(['Français' => 'French', 'Allemagne' => 'Germany']);
    }

    protected function tearDown(): void
    {
        HtmlTranslator::reset();
    }

    private function service(): DiscordShowcaseService
    {
        return new DiscordShowcaseService(
            fn (string $key): string => $this->store[$key] ?? '',
            function (array $pairs): void {
                $this->store = array_merge($this->store, $pairs);
            },
            function (string $method, string $url, ?array $payload): array {
                $this->calls[] = [$method, $url, $payload];

                return array_shift($this->responses) ?? ['status' => 500, 'body' => ''];
            },
            fn (): \DateTimeImmutable => $this->now,
        );
    }

    /** @return array<string, mixed> */
    private function card(): array
    {
        return DiscordShowcaseService::card(
            [
                'id' => 7,
                'slug' => 'task-force-alpha',
                'name' => 'Task Force Alpha',
                'registry_country_label' => 'Allemagne',
                'registry_locale' => 'fr',
                'registry_locale_label' => 'Français',
                'game_label' => 'Arma 3',
                'registry_recruitment_open' => true,
                'registry_style_badge_labels' => ['Milsim'],
                'registry_excerpt' => 'Unité francophone, opérations le samedi.',
            ],
            ['logo_url' => '/uploads/logo.png', 'banner_url' => 'https://cdn.example.org/b.jpg', 'accent_color' => '#12d18e'],
            ['contact_discord_url' => 'https://discord.com/invite/AbC123'],
            'https://athena.ttrd.fr'
        );
    }

    public function testCardUsesPublicDataAndAbsoluteUrls(): void
    {
        $card = $this->card();
        self::assertSame('https://athena.ttrd.fr/c/task-force-alpha?ref=discord', $card['public_url']);
        self::assertSame('https://athena.ttrd.fr/uploads/logo.png', $card['logo']);
        self::assertSame('https://discord.gg/AbC123', $card['discord_invite']);
        self::assertSame(0x12D18E, $card['color']);
        self::assertSame('', DiscordShowcaseService::discordInvite('https://github.com/x/y'));
        self::assertSame('', DiscordShowcaseService::absoluteUrl('javascript:alert(1)', 'https://athena.ttrd.fr'));
    }

    public function testPitchHasNoMentionNorLink(): void
    {
        $clean = DiscordShowcaseService::sanitizePitch("@everyone rejoignez-nous <@123456789012345678> !\n# Titre\nhttps://github.com/x/y et [ici](https://evil.example) discord.gg/abc @here");
        self::assertStringNotContainsString('@', $clean);
        self::assertStringNotContainsString('http', $clean);
        self::assertStringNotContainsString('github', $clean);
        self::assertStringNotContainsString('discord.gg', $clean);
        self::assertStringNotContainsString('#', $clean);
        self::assertStringContainsString('ici', $clean);
        self::assertLessThanOrEqual(DiscordShowcaseService::PITCH_MAX, mb_strlen(DiscordShowcaseService::sanitizePitch(str_repeat('a', 900))));
    }

    public function testPayloadIsAForumPostWithoutMentions(): void
    {
        $config = DiscordShowcaseService::normalizeConfig(['tags' => ['athena' => '1290000000000000011', 'recruiting' => '1290000000000000012', 'fr' => 'abc']]);
        $payload = DiscordShowcaseService::buildPayload($this->card(), '', $config, $this->now);

        self::assertSame(['parse' => []], $payload['allowed_mentions']);
        self::assertSame('Task Force Alpha', $payload['thread_name']);
        self::assertSame(['1290000000000000011', '1290000000000000012'], $payload['applied_tags']);
        $embed = $payload['embeds'][0];
        self::assertSame('Unité francophone, opérations le samedi.', $embed['description']);
        $fields = array_column($embed['fields'], 'value', 'name');
        self::assertSame('Germany', $fields['Country']);
        self::assertSame('French', $fields['Language']);
        self::assertSame('🟢 Open', $fields['Recruitment']);
        self::assertStringContainsString('https://discord.gg/AbC123', $fields['Links']);
        self::assertStringNotContainsString('github', (string) json_encode($payload));
    }

    public function testSimulationRecordsWithoutCallingDiscord(): void
    {
        $service = $this->service();
        self::assertFalse($service->request(7, 3, 'Salut', false)['ok'], 'hors annuaire');
        self::assertTrue($service->request(7, 3, 'Salut', true)['ok']);
        self::assertFalse($service->request(7, 3, 'Encore', true)['ok'], 'une seule demande en attente');

        $result = $service->approve(7, 1, $this->card());
        self::assertTrue($result['ok']);
        self::assertStringContainsString('simulation', $result['message']);
        self::assertSame([], $this->calls);
        $entry = $service->entry(7);
        self::assertSame(DiscordShowcaseService::STATUS_PUBLISHED, $entry['status']);
        self::assertTrue($entry['simulated']);
    }

    public function testLivePublishCreatesThenEditsTheSamePostAndRespectsCooldown(): void
    {
        $service = $this->service();
        self::assertFalse($service->saveConfig(['mode' => 'live'])['ok'], 'pas de réel sans lien');
        self::assertTrue($service->saveConfig(['mode' => 'live', 'webhook' => self::HOOK, 'forum' => true])['ok']);
        self::assertSame(self::HOOK, $this->store[DiscordShowcaseService::WEBHOOK_KEY]);

        $service->request(7, 3, '', true);
        $this->responses[] = ['status' => 200, 'body' => '{"id":"1300000000000000001","channel_id":"1300000000000000002"}'];
        self::assertTrue($service->approve(7, 1, $this->card())['ok']);
        self::assertSame('POST', $this->calls[0][0]);
        self::assertStringEndsWith('?wait=true', $this->calls[0][1]);
        $entry = $service->entry(7);
        self::assertSame('1300000000000000002', $entry['thread_id']);
        self::assertSame('1300000000000000001', $entry['message_id']);

        // Mise à jour refusée avant le délai, acceptée après.
        $this->now = $this->now->modify('+3 days');
        self::assertFalse($service->request(7, 3, 'v2', true)['ok']);
        $this->now = $this->now->modify('+5 days');
        self::assertTrue($service->request(7, 3, 'v2', true)['ok']);
        $this->responses[] = ['status' => 200, 'body' => '{}'];
        self::assertTrue($service->approve(7, 1, $this->card())['ok']);
        self::assertSame('PATCH', $this->calls[1][0]);
        self::assertStringContainsString('/messages/1300000000000000001?thread_id=1300000000000000002', $this->calls[1][1]);
        self::assertArrayNotHasKey('thread_name', (array) $this->calls[1][2]);

        // Retrait : suppression du message.
        $this->responses[] = ['status' => 204, 'body' => ''];
        self::assertTrue($service->remove(7)['ok']);
        self::assertSame('DELETE', $this->calls[2][0]);
        self::assertSame(DiscordShowcaseService::STATUS_WITHDRAWN, $service->entry(7)['status']);
    }

    public function testFailedPublishKeepsTheRequestPending(): void
    {
        $service = $this->service();
        $service->saveConfig(['mode' => 'live', 'webhook' => self::HOOK]);
        $service->request(7, 3, '', true);
        $this->responses[] = ['status' => 429, 'body' => ''];
        self::assertFalse($service->approve(7, 1, $this->card())['ok']);
        self::assertSame(DiscordShowcaseService::STATUS_PENDING, $service->entry(7)['status']);
    }

    public function testBlockAndRejectWaits(): void
    {
        $service = $this->service();
        $service->request(7, 3, '', true);
        self::assertTrue($service->reject(7, 1, 'Bannière hors charte')['ok']);
        self::assertSame('Bannière hors charte', $service->entry(7)['reason']);
        self::assertFalse($service->request(7, 3, '', true)['ok'], '24 h après un refus');
        $this->now = $this->now->modify('+25 hours');
        self::assertTrue($service->request(7, 3, '', true)['ok']);

        $service->block(7, 'Spam');
        $entry = $service->entry(7);
        self::assertTrue($entry['blocked']);
        self::assertNotSame(DiscordShowcaseService::STATUS_PENDING, $entry['status']);
        self::assertFalse($service->request(7, 3, '', true)['ok']);
        self::assertFalse($service->approve(7, 1, $this->card())['ok']);
        $service->unblock(7);
        self::assertTrue($service->request(7, 3, '', true)['ok']);
    }

    public function testWebhookIsNeverShownInFull(): void
    {
        $masked = DiscordShowcaseService::maskWebhook(self::HOOK);
        self::assertStringNotContainsString('AbCdEf', $masked);
        self::assertStringNotContainsString('1290000000000000001', $masked);
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/discord_showcase.php');
        self::assertStringContainsString('type="password" name="webhook"', $view);
        self::assertStringNotContainsString('value="<?= $h($hook', $view);
    }

    public function testControllersAreRegisteredInTheContainer(): void
    {
        // Le routeur passe par Container::get : un contrôleur absent du conteneur donne une erreur 500.
        $container = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        foreach (['SystemDiscordShowcaseController', 'OrganizationDiscordShowcaseController'] as $class) {
            self::assertStringContainsString($class . '::class => new ', $container, $class);
        }
    }
}
