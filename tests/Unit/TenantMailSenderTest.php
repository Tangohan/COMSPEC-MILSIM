<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TenantRepository;
use App\Services\Email\TenantMailSender;
use App\Support\SecretCipher;
use PHPUnit\Framework\TestCase;

/**
 * Compte d'envoi des e-mails propre à une communauté (Back-office > Intégrations > E-mails de la communauté).
 */
final class TenantMailSenderTest extends TestCase
{
    private function repo(): TenantRepository
    {
        return new class extends TenantRepository {
            /** @var array<int, array<string, mixed>> */
            public array $settings = [];

            public function __construct()
            {
            }

            public function getSettings(int $tenantId): array
            {
                return $this->settings[$tenantId] ?? [];
            }

            public function mergeSettings(int $tenantId, array $patch): void
            {
                $this->settings[$tenantId] = array_merge($this->settings[$tenantId] ?? [], $patch);
            }
        };
    }

    private function sender(TenantRepository $repo, string $ip = '203.0.113.10'): TenantMailSender
    {
        return new TenantMailSender($repo, static fn (string $host): array => [$ip]);
    }

    /** @return array<string, string> */
    private function form(array $over = []): array
    {
        return $over + [
            'enabled' => '1',
            'provider' => 'brevo',
            'host' => '',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'compte@brevo.example',
            'password' => 'cle-smtp-secrete',
            'from_address' => 'no-reply@commu.example',
            'from_name' => '1er RPIMa',
            'reply_to' => '',
        ];
    }

    public function testNothingConfiguredMeansNoCommunityTransport(): void
    {
        $sender = $this->sender($this->repo());

        self::assertNull($sender->transportConfig(5));
        self::assertFalse($sender->state(5)['ready']);
        self::assertNull($sender->transportConfig(1), 'la plateforme (tenant 1) n’a pas de compte de communauté');
    }

    public function testSavedAccountIsEncryptedAndUsedForSending(): void
    {
        $repo = $this->repo();
        $sender = $this->sender($repo);

        self::assertTrue($sender->save(5, $this->form())['ok']);
        $stored = $repo->settings[5][TenantMailSender::SETTINGS_KEY];
        self::assertStringStartsWith('sc1:', $stored['password']);
        self::assertStringNotContainsString('cle-smtp-secrete', json_encode($repo->settings, JSON_THROW_ON_ERROR));

        $cfg = $sender->transportConfig(5);
        self::assertNotNull($cfg);
        self::assertSame('community:brevo', $cfg['transport_label']);
        self::assertSame('smtp-relay.brevo.com', $cfg['smtp']['host']);
        self::assertSame('cle-smtp-secrete', $cfg['smtp']['password']);
        self::assertSame('no-reply@commu.example', $cfg['from_address']);

        // Mot de passe vide au réenregistrement : on garde l'ancien.
        self::assertTrue($sender->save(5, $this->form(['password' => '', 'from_name' => 'RPIMa']))['ok']);
        self::assertSame('cle-smtp-secrete', $sender->transportConfig(5)['smtp']['password']);
        self::assertFalse(isset($sender->state(5)['password']));
        self::assertTrue($sender->state(5)['has_password']);
    }

    public function testDisabledAccountIsIgnored(): void
    {
        $repo = $this->repo();
        $sender = $this->sender($repo);
        $sender->save(5, $this->form());
        self::assertTrue($sender->save(5, $this->form(['enabled' => '0']))['ok']);

        self::assertNull($sender->transportConfig(5));
    }

    public function testRejectsLocalServersAndOddPorts(): void
    {
        $repo = $this->repo();

        $local = $this->sender($repo, '127.0.0.1')->save(5, $this->form(['provider' => 'smtp', 'host' => 'smtp.commu.example']));
        self::assertFalse($local['ok']);
        self::assertStringContainsString('serveur public', $local['message']);

        $private = $this->sender($repo, '10.0.0.4')->save(5, $this->form(['provider' => 'smtp', 'host' => 'smtp.commu.example']));
        self::assertFalse($private['ok']);

        $port = $this->sender($repo)->save(5, $this->form(['port' => '3306']));
        self::assertFalse($port['ok']);
        self::assertStringContainsString('Port', $port['message']);

        $from = $this->sender($repo)->save(5, $this->form(['from_address' => 'pas-une-adresse']));
        self::assertFalse($from['ok']);
        self::assertArrayNotHasKey(5, $repo->settings);
    }

    public function testCipherRoundTripAndTamper(): void
    {
        $c = SecretCipher::encrypt('abc');
        self::assertSame('abc', SecretCipher::decrypt($c));
        self::assertNull(SecretCipher::decrypt(substr($c, 0, -2) . 'xx'));
        self::assertNull(SecretCipher::decrypt('en-clair'));
    }
}
