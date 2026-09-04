<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DocumentAttachedFile;
use PHPUnit\Framework\TestCase;

final class DocumentAttachedFileTest extends TestCase
{
    public function testEmptyPointerIsLegitimate(): void
    {
        self::assertFalse(DocumentAttachedFile::hasPointer(null));
        self::assertFalse(DocumentAttachedFile::hasPointer(''));
        self::assertFalse(DocumentAttachedFile::hasPointer('   '));
        self::assertTrue(DocumentAttachedFile::hasPointer('12/36/v1.pdf'));
    }

    public function testHumanLabelsStayReadable(): void
    {
        self::assertSame('Document PDF', DocumentAttachedFile::humanKind('application/pdf'));
        self::assertSame('Image', DocumentAttachedFile::humanKind('image/jpeg'));
        self::assertSame('Vidéo', DocumentAttachedFile::humanKind('video/mp4'));
        self::assertSame('compte-rendu.pdf', DocumentAttachedFile::displayName('compte-rendu.pdf', 'application/pdf'));
        self::assertSame('Document PDF', DocumentAttachedFile::displayName(null, 'application/pdf'));
        self::assertSame('12,0 Ko', DocumentAttachedFile::humanSize(12288));
        self::assertSame('', DocumentAttachedFile::humanSize(null));
    }

    public function testArchivePathKeepsOriginalBasename(): void
    {
        $rel = DocumentAttachedFile::archiveRelativePath(4, 36, '4/36/v2.pdf', '20260902140000');
        self::assertSame('detached/4/36/20260902140000_v2.pdf', $rel);
        self::assertStringNotContainsString('..', $rel);
    }

    public function testMoveAsideDoesNotInventMissingFile(): void
    {
        $tmp = sys_get_temp_dir() . '/comspec-doc-detach-' . bin2hex(random_bytes(4));
        $src = $tmp . '/missing.pdf';
        $dest = $tmp . '/archive/out.pdf';
        self::assertFalse(DocumentAttachedFile::moveAsideIfPresent($src, $dest));
        self::assertFileDoesNotExist($dest);
    }

    public function testMoveAsideArchivesExistingFile(): void
    {
        $tmp = sys_get_temp_dir() . '/comspec-doc-detach-' . bin2hex(random_bytes(4));
        $src = $tmp . '/current.pdf';
        $dest = $tmp . '/archive/out.pdf';
        mkdir($tmp, 0755, true);
        file_put_contents($src, '%PDF-1.4');
        self::assertTrue(DocumentAttachedFile::moveAsideIfPresent($src, $dest));
        self::assertFileDoesNotExist($src);
        self::assertFileExists($dest);
        self::assertSame('%PDF-1.4', (string) file_get_contents($dest));
        @unlink($dest);
        @rmdir($tmp . '/archive');
        @rmdir($tmp);
    }
}
