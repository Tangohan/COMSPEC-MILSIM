<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UserMediaPublicUrlTest extends TestCase
{
    private mixed $previousBasePath;
    private mixed $previousAppUrl;
    private mixed $previousScriptName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousBasePath = $_ENV['APP_BASE_PATH'] ?? null;
        $this->previousAppUrl = $_ENV['APP_URL'] ?? null;
        $this->previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        $_ENV['APP_URL'] = 'https://athena.ttrd.fr';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }

    protected function tearDown(): void
    {
        if ($this->previousBasePath === null) {
            unset($_ENV['APP_BASE_PATH']);
        } else {
            $_ENV['APP_BASE_PATH'] = $this->previousBasePath;
        }
        if ($this->previousAppUrl === null) {
            unset($_ENV['APP_URL']);
        } else {
            $_ENV['APP_URL'] = $this->previousAppUrl;
        }
        if ($this->previousScriptName === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $this->previousScriptName;
        }
        parent::tearDown();
    }

    public function testVpsRootPublicKeepsUploadsWithoutPublicPrefix(): void
    {
        $_ENV['APP_BASE_PATH'] = '';
        self::assertSame(
            'https://athena.ttrd.fr/uploads/recon/recon_x.jpg',
            user_media_public_url('uploads/recon/recon_x.jpg')
        );
        self::assertSame(
            '/uploads/recon/recon_x.jpg',
            normalize_public_uploads_url('/uploads/recon/recon_x.jpg')
        );
        self::assertSame(
            'https://athena.ttrd.fr/uploads/recon/recon_x.jpg',
            normalize_public_uploads_url('https://athena.ttrd.fr/public/uploads/recon/recon_x.jpg')
        );
        self::assertSame(
            '/uploads/recon/recon_x.jpg',
            normalize_public_uploads_url('/public/uploads/recon/recon_x.jpg')
        );
    }

    public function testHostingerBasePathPrefixesUploads(): void
    {
        $_ENV['APP_BASE_PATH'] = '/public';
        self::assertSame(
            'https://athena.ttrd.fr/public/uploads/recon/recon_x.jpg',
            user_media_public_url('uploads/recon/recon_x.jpg')
        );
        self::assertSame(
            '/public/uploads/recon/recon_x.jpg',
            normalize_public_uploads_url('/uploads/recon/recon_x.jpg')
        );
    }
}
