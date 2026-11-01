<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TenantRepository;
use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Integrations\DiscordWebhookDirectory;
use App\Services\Integrations\DiscordWebhookService;
use App\Support\DiscordChannelInput;
use App\Support\DiscordWebhookCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Salons Discord de la communauté : salon de l'organisation pour tous les usages, salons à part en mode avancé,
 * liens validés (Discord uniquement, https) et chiffrés, jamais réaffichés.
 */
final class DiscordWebhookDirectoryTest extends TestCase
{
    private const ORG = 'https://discord.com/api/webhooks/1290000000000000001/AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';
    private const REC = 'https://discord.com/api/webhooks/1290000000000000002/ZyXwVuTsRqPoNmLkJiHgFeDcBa9876543210';

    private TenantRepository $tenants;

    protected function setUp(): void
    {
        $this->tenants = new class () extends TenantRepository {
            /** @var array<string, mixed> */
            public array $settings = [];

            public function __construct()
            {
            }

            public function getSettings(int $tenantId): array
            {
                return $this->settings;
            }

            public function mergeSettings(int $tenantId, array $patch): void
            {
                $this->settings = array_merge($this->settings, json_decode(json_encode($patch), true));
            }
        };
    }

    private function dir(): DiscordWebhookDirectory
    {
        return new DiscordWebhookDirectory($this->tenants);
    }

    private function relay(): DiscordEventRelayService
    {
        return new DiscordEventRelayService($this->tenants, new DiscordWebhookService(), $this->dir());
    }

    private function raw(): string
    {
        /** @phpstan-ignore-next-line */
        return json_encode($this->tenants->settings);
    }

    public function testOnlyHttpsDiscordWebhookLinksAreAccepted(): void
    {
        self::assertSame('', DiscordChannelInput::webhookUrl('http://discord.com/api/webhooks/1290000000000000001/AbCdEfGhIjKlMnOpQrStUvWx'));
        self::assertSame('', DiscordChannelInput::webhookUrl('https://evil.example/api/webhooks/1290000000000000001/AbCdEfGhIjKlMnOpQrStUvWx'));
        self::assertSame('', DiscordChannelInput::webhookUrl('https://discord.com.evil.example/api/webhooks/1/AbCdEfGhIjKlMnOpQrStUvWx'));
        self::assertSame('', DiscordChannelInput::webhookUrl('https://discord.com/channels/123456789012345678/123456789012345679'));
        self::assertSame(self::ORG, DiscordChannelInput::webhookUrl(str_replace('discord.com', 'discordapp.com', self::ORG)));
        self::assertSame(self::ORG, DiscordChannelInput::webhookUrl('<' . self::ORG . '>'));

        $bad = $this->dir()->saveOrganisation(5, 'https://example.com/hook', 'X', '#x');
        self::assertFalse($bad['ok']);
        self::assertFalse($this->dir()->organisation(5)['configured']);
        self::assertFalse($this->dir()->add(5, 'Recrutement', '#recrutement', '123456789012345678')['ok']);
    }

    public function testLinksAreEncryptedAndNeverShownBack(): void
    {
        self::assertTrue($this->dir()->saveOrganisation(5, self::ORG, 'Discord SOAR', 'annonces')['ok']);
        $added = $this->dir()->add(5, 'Recrutement', '#recrutement', self::REC);
        self::assertTrue($added['ok']);

        self::assertStringNotContainsString('AbCdEfGhIjKlMnOpQrSt', $this->raw());
        self::assertStringNotContainsString('ZyXwVuTsRqPoNmLkJiHg', $this->raw());
        $org = $this->dir()->organisation(5);
        self::assertTrue($org['configured']);
        self::assertTrue($org['encrypted']);
        self::assertSame('discord.com/api/webhooks/1290…/••••', $org['masked']);
        self::assertSame('#annonces', $org['channel']);
        $list = $this->dir()->list(5);
        self::assertCount(1, $list);
        self::assertArrayNotHasKey('url', $list[0]);
        self::assertStringNotContainsString('ZyXw', json_encode($list));

        // Lien laissé vide : l'ancien est gardé.
        self::assertTrue($this->dir()->saveOrganisation(5, '', 'Autre nom', '#general')['ok']);
        self::assertSame(self::ORG, $this->dir()->organisationUrl(5));
        self::assertSame(self::REC, $this->dir()->urlFor(5, (string) $added['id']));
        self::assertFalse($this->dir()->add(5, 'Doublon', '', self::REC)['ok'], 'même salon deux fois');
    }

    public function testLegacyPlainLinkIsStillReadThenEncrypted(): void
    {
        $this->tenants->settings = ['integrations' => ['discord_webhook_url' => self::ORG]];
        self::assertSame(self::ORG, $this->dir()->organisationUrl(5));
        self::assertFalse($this->dir()->organisation(5)['encrypted']);
        $this->dir()->saveOrganisation(5, '', '', '');
        self::assertTrue($this->dir()->organisation(5)['encrypted']);
        self::assertSame(self::ORG, $this->dir()->organisationUrl(5));
    }

    public function testOrganisationIsTheFallbackForEveryUsage(): void
    {
        $this->dir()->saveOrganisation(5, self::ORG, '', '');
        $rec = (string) $this->dir()->add(5, 'Recrutement', '', self::REC)['id'];
        $input = [];
        foreach (DiscordWebhookCatalog::keys() as $key) {
            $input[$key] = ['on' => '1', 'dest' => 'organisation'];
        }
        $input[DiscordWebhookCatalog::KEY_QUICK_PICTURE] = ['dest' => 'organisation'];
        $input[\App\Services\Email\EmailEvents::ENLISTMENT_SUBMITTED_STAFF] = ['on' => '1', 'dest' => $rec];
        $input[DiscordWebhookCatalog::KEY_ANNOUNCEMENTS] = ['on' => '1', 'dest' => 'inconnu'];
        self::assertTrue($this->relay()->saveEvents(5, $input)['ok']);

        $relay = $this->relay();
        self::assertSame(self::ORG, $relay->resolveUrl(5, DiscordWebhookCatalog::KEY_MEMBER_INVITES));
        self::assertSame(self::ORG, $relay->resolveUrl(5, DiscordWebhookCatalog::KEY_ANNOUNCEMENTS), 'salon inconnu : organisation');
        self::assertSame(self::REC, $relay->resolveUrl(5, \App\Services\Email\EmailEvents::ENLISTMENT_SUBMITTED_STAFF));
        self::assertNull($relay->resolveUrl(5, DiscordWebhookCatalog::KEY_QUICK_PICTURE), 'usage coupé');

        $this->dir()->remove(5, $rec);
        self::assertSame(self::ORG, $this->relay()->resolveUrl(5, \App\Services\Email\EmailEvents::ENLISTMENT_SUBMITTED_STAFF), 'salon retiré : organisation');
        self::assertNull($this->relay()->resolveUrl(1, DiscordWebhookCatalog::KEY_MEMBER_INVITES), 'jamais pour la plateforme');
    }

    public function testLegacyPerEventLinkIsAdoptedIntoTheEncryptedList(): void
    {
        $this->tenants->settings = ['integrations' => [
            'discord_event_webhooks' => [DiscordWebhookCatalog::KEY_ATAK_RECON_NOTES => ['mode' => 'custom', 'url' => self::REC]],
        ]];
        self::assertSame(self::REC, $this->relay()->resolveUrl(5, DiscordWebhookCatalog::KEY_ATAK_RECON_NOTES));
        $this->relay()->saveEvents(5, [DiscordWebhookCatalog::KEY_ATAK_RECON_NOTES => ['on' => '1', 'dest' => 'legacy']]);
        self::assertStringNotContainsString('ZyXwVuTsRqPoNmLkJiHg', $this->raw());
        self::assertCount(1, $this->dir()->list(5));
        self::assertSame(self::REC, $this->relay()->resolveUrl(5, DiscordWebhookCatalog::KEY_ATAK_RECON_NOTES));
        self::assertNull($this->relay()->resolveUrl(5, DiscordWebhookCatalog::KEY_ANNOUNCEMENTS), 'rien d’autre activé, pas de salon d’organisation');
    }

    public function testInviteTemplateIsStoredOnlyWhenCustomised(): void
    {
        self::assertSame(\App\Support\ProfileInviteDiscordMessage::DEFAULT_TEMPLATE, $this->dir()->inviteTemplate(5));
        $this->dir()->saveInviteTemplate(5, 'Hello {indicatif} : {lien}');
        self::assertSame('Hello {indicatif} : {lien}', $this->dir()->inviteTemplate(5));
        $this->dir()->saveInviteTemplate(5, '');
        self::assertSame(\App\Support\ProfileInviteDiscordMessage::DEFAULT_TEMPLATE, $this->dir()->inviteTemplate(5));
    }
}
