<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use PDO;

/**
 * Référence unique d'un document SSE, attribuée à l'enregistrement initial : SSE-NR-26-0042 (préfixe du type, année sur
 * deux chiffres, séquence par communauté, par préfixe et par année).
 *
 * Le format est paramétrable par type de document : chaîne avec {PFX}, {YY}, {YYYY} et {SEQ} (séquence sur quatre chiffres
 * au moins). La séquence vient de sse_document_counters : une ligne par communauté, préfixe et année, incrémentée dans une
 * transaction (l'UPDATE verrouille la ligne : deux enregistrements simultanés ne reçoivent jamais le même numéro). Un
 * numéro attribué n'est jamais réutilisé, même si le document est supprimé ensuite.
 */
final class SseDocumentReference
{
    /** type de document => [préfixe, format]. */
    public const FORMATS = [
        'flash' => ['FL', 'SSE-{PFX}-{YY}-{SEQ}'],
        'compte_rendu' => ['CR', 'SSE-{PFX}-{YY}-{SEQ}'],
        'note_analyse' => ['NR', 'SSE-{PFX}-{YY}-{SEQ}'],
        'synthese' => ['SY', 'SSE-{PFX}-{YY}-{SEQ}'],
        'diffusion' => ['DF', 'SSE-{PFX}-{YY}-{SEQ}'],
    ];

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** @return array{0:string,1:string} préfixe et format d'un type (note d'analyse si inconnu) */
    public static function formatFor(string $type): array
    {
        $f = self::FORMATS[$type] ?? self::FORMATS['note_analyse'];
        $format = $f[1];
        if (!str_contains($format, '{SEQ}') || preg_match('/^[A-Za-z0-9{}\-_\/.]{4,60}$/', $format) !== 1) {
            $format = 'SSE-{PFX}-{YY}-{SEQ}';
        }

        return [$f[0], $format];
    }

    public static function render(string $type, int $year, int $seq): string
    {
        [$pfx, $format] = self::formatFor($type);

        return strtr($format, [
            '{PFX}' => $pfx,
            '{YY}' => substr((string) $year, -2),
            '{YYYY}' => (string) $year,
            '{SEQ}' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
        ]);
    }

    /** Attribue la référence suivante ; lève une exception si le compteur est indisponible (l'appelant garde l'ancien repli). */
    public function next(int $tenantId, string $type, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        [$pfx] = self::formatFor($type);
        $pdo = $this->pdo();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $seq = $this->bump($pdo, $tenantId, $pfx, $year);
            if ($own) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::render($type, $year, $seq);
    }

    private function bump(PDO $pdo, int $tenantId, string $pfx, int $year): int
    {
        $upd = $pdo->prepare('UPDATE sse_document_counters SET last_seq = last_seq + 1 WHERE tenant_id = :t AND prefix = :p AND year = :y');
        $params = ['t' => $tenantId, 'p' => $pfx, 'y' => $year];
        $upd->execute($params);
        if ($upd->rowCount() < 1) {
            try {
                $ins = $pdo->prepare('INSERT INTO sse_document_counters (tenant_id, prefix, year, last_seq) VALUES (:t, :p, :y, 1)');
                $ins->execute($params);
            } catch (\PDOException) {
                // Une autre requête vient de créer la ligne : on l'incrémente.
                $upd->execute($params);
            }
        }
        $sel = $pdo->prepare('SELECT last_seq FROM sse_document_counters WHERE tenant_id = :t AND prefix = :p AND year = :y');
        $sel->execute($params);
        $seq = (int) $sel->fetchColumn();
        if ($seq < 1) {
            throw new \RuntimeException('Compteur de références indisponible.');
        }

        return $seq;
    }
}
