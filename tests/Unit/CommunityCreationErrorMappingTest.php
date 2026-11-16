<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SqlDataTooLong;
use App\Support\UserFacingExceptionMapper;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Une valeur trop longue pour sa colonne (MySQL strict 1406) ne doit plus finir sur le message générique
 * « Impossible de créer la communauté pour le moment » : message précis, table et colonne dans le journal.
 */
final class CommunityCreationErrorMappingTest extends TestCase
{
    private const MYSQL_1406 = "SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name' at row 1";

    private const GENERIC = 'Impossible de créer la communauté pour le moment. Réessayez plus tard ou contactez le support.';

    /** Déclenche une vraie exécution PDO qui échoue comme MySQL : la fonction SQL lève l'erreur 1406. */
    private function failingInsert(): PDOException
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT)');
        $pdo->sqliteCreateFunction('mysql_strict', static function (): never {
            throw new PDOException(self::MYSQL_1406);
        }, 1);
        try {
            $st = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug) VALUES (?, mysql_strict(?), ?)');
            $st->execute([1, 'libellé', 'atak.toc.phone']);
        } catch (PDOException $e) {
            return $e;
        }
        self::fail('L’insertion aurait dû échouer.');
    }

    public function testDataTooLongGetsAPreciseMessageInsteadOfTheGenericOne(): void
    {
        $msg = UserFacingExceptionMapper::communityCreationMessage(new PDOException(self::MYSQL_1406));
        self::assertNotSame(self::GENERIC, $msg);
        self::assertStringStartsWith('Un texte dépasse la longueur enregistrable (name).', $msg);
        self::assertStringNotContainsString('SQLSTATE', $msg);
    }

    public function testWrappedDataTooLongIsRecognised(): void
    {
        $e = new \RuntimeException('Création interrompue', 0, new PDOException(self::MYSQL_1406));
        self::assertTrue(SqlDataTooLong::matches($e));
        self::assertSame('name', SqlDataTooLong::column($e));
    }

    public function testTableAndColumnComeFromTheFailingStatement(): void
    {
        $e = $this->failingInsert();
        self::assertTrue(SqlDataTooLong::matches($e));
        self::assertSame('permissions', SqlDataTooLong::table($e));
        self::assertSame('permissions.name', SqlDataTooLong::reference($e));
        self::assertStringContainsString('(permissions.name)', UserFacingExceptionMapper::communityCreationMessage($e));
        $log = SqlDataTooLong::logLine($e);
        self::assertStringStartsWith('SQLSTATE 22001 valeur trop longue pour permissions.name (CommunityCreationErrorMappingTest.php:', $log);
        // Jamais la valeur refusée dans le journal.
        self::assertStringNotContainsString('libellé', $log);
    }

    public function testOtherTechnicalErrorsKeepTheGenericMessage(): void
    {
        self::assertFalse(SqlDataTooLong::matches(new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'x'")));
        self::assertSame(self::GENERIC, UserFacingExceptionMapper::communityCreationMessage(new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'x'")));
        self::assertSame('', SqlDataTooLong::logLine(new \RuntimeException('autre')));
    }
}
