<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Deployment\PackageSignatureVerifier;
use App\Services\Deployment\ProtectedPathPolicy;
use App\Services\Deployment\VersionCompatibility;
use PHPUnit\Framework\TestCase;

final class AppUpdateServicesTest extends TestCase
{
    public function testProtectedPaths(): void
    {
        self::assertTrue(ProtectedPathPolicy::isProtected('.env'));
        self::assertTrue(ProtectedPathPolicy::isProtected('.env.local'));
        self::assertTrue(ProtectedPathPolicy::isProtected('storage/logs/app.log'));
        self::assertTrue(ProtectedPathPolicy::isProtected('public/uploads/photo.jpg'));
        self::assertTrue(ProtectedPathPolicy::isProtected('app/Config/database.local.php'));
        self::assertTrue(ProtectedPathPolicy::isProtected('../etc/passwd'));
        self::assertFalse(ProtectedPathPolicy::isProtected('app/Controllers/HomeController.php'));
        self::assertFalse(ProtectedPathPolicy::isProtected('public/assets/css/styles.css'));
        self::assertFalse(ProtectedPathPolicy::isProtected('views/home/index.php'));
    }

    public function testVersionCompatibility(): void
    {
        self::assertTrue(VersionCompatibility::satisfiesMinimum('1.4.0', '1.3.0'));
        self::assertFalse(VersionCompatibility::satisfiesMinimum('1.2.0', '1.3.0'));
        self::assertTrue(VersionCompatibility::isNewerThan('1.4.0', '1.3.9'));
        self::assertFalse(VersionCompatibility::isNewerThan('1.3.0', '1.3.0'));
        self::assertTrue(VersionCompatibility::phpCompatible('8.4.0'));
        self::assertFalse(VersionCompatibility::phpCompatible('99.0.0'));
    }

    public function testSignatureOptionalWhenSecretEmpty(): void
    {
        $prev = $_ENV['UPDATE_PACKAGE_HMAC_SECRET'] ?? null;
        unset($_ENV['UPDATE_PACKAGE_HMAC_SECRET']);
        putenv('UPDATE_PACKAGE_HMAC_SECRET');

        $verifier = new PackageSignatureVerifier();
        self::assertFalse($verifier->isEnforced());
        self::assertTrue($verifier->verifyManifest(['version' => '1.1.0'], 'abc'));

        if ($prev !== null) {
            $_ENV['UPDATE_PACKAGE_HMAC_SECRET'] = $prev;
            putenv('UPDATE_PACKAGE_HMAC_SECRET=' . $prev);
        }
    }

    public function testSignatureRequiredWhenSecretSet(): void
    {
        $_ENV['UPDATE_PACKAGE_HMAC_SECRET'] = 'test-secret-key';
        putenv('UPDATE_PACKAGE_HMAC_SECRET=test-secret-key');

        $verifier = new PackageSignatureVerifier();
        self::assertTrue($verifier->isEnforced());
        $checksum = str_repeat('a', 64);
        $sig = $verifier->sign('1.4.0', '1.3.0', $checksum);
        self::assertTrue($verifier->verifyManifest([
            'version' => '1.4.0',
            'minimum_version' => '1.3.0',
            'signature' => $sig,
        ], $checksum));
        self::assertFalse($verifier->verifyManifest([
            'version' => '1.4.0',
            'minimum_version' => '1.3.0',
            'signature' => 'deadbeef',
        ], $checksum));

        unset($_ENV['UPDATE_PACKAGE_HMAC_SECRET']);
        putenv('UPDATE_PACKAGE_HMAC_SECRET');
    }
}
