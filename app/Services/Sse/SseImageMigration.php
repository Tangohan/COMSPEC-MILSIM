<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseImageStorage;
use PDO;
use Throwable;

/**
 * Déplace les images SSE sensibles EXISTANTES de public/uploads/<dossier> vers l'espace privé
 * (storage/private/sse-images/<dossier>) et met à jour le chemin gardé en base.
 *
 * Moteur de tools/sse-move-images-private.php (la ligne de commande ne contient que l'analyse des options).
 *
 * Garanties :
 *  - SIMULATION par défaut : plan() et run(apply: false) ne touchent ni disque ni base ;
 *  - par fichier : copie vers un nom temporaire, contrôle d'intégrité (taille, SHA-256, vrai type d'image), renommage,
 *    mise à jour du chemin en base dans UNE transaction (toutes les lignes qui pointent ce fichier), et SEULEMENT ENSUITE
 *    suppression de la copie publique, si plus aucune ligne ne la référence ;
 *  - une étape qui échoue ne supprime rien : le fichier public reste lisible, la base garde l'ancien chemin ;
 *  - idempotent et reprenable : une copie privée identique est réutilisée, une copie privée DIFFÉRENTE (collision) n'est
 *    jamais écrasée et le fichier est signalé ; relancer ne refait que ce qui reste ;
 *  - journal (une ligne JSON par fichier) qui sert aussi au retour arrière (rollback()).
 *
 * Colonnes traitées : voir TARGETS. Ce moteur ne lit aucun fichier de configuration : on lui donne la connexion PDO.
 */
final class SseImageMigration
{
    /** @var list<array{table: string, column: string}> */
    public const TARGETS = [
        ['table' => 'sse_identities', 'column' => 'photo_path'],
        ['table' => 'sse_identities', 'column' => 'signature_path'],
        ['table' => 'sse_id_documents', 'column' => 'background_path'],
        ['table' => 'sse_file_photos', 'column' => 'path'],
        ['table' => 'sse_seek_records', 'column' => 'signature_image'],
        ['table' => 'sse_persons', 'column' => 'signature_image'],
    ];

    private string $publicDir;
    private string $privateDir;

    /** @var list<string> tables absentes (ancienne communauté) */
    private array $missingTables = [];

    public function __construct(
        private PDO $pdo,
        ?string $publicDir = null,
        ?string $privateDir = null,
        private ?string $logFile = null,
    ) {
        $this->publicDir = rtrim($publicDir ?? base_path('public'), '/\\');
        $this->privateDir = SseImageStorage::privateRoot($privateDir);
    }

    /**
     * Fichiers à déplacer : un élément par chemin hérité distinct.
     *
     * @return list<array{from: string, to: string, rows: list<array{table: string, column: string, id: int, tenant: int}>}>
     */
    public function collect(?int $tenant = null): array
    {
        $groups = [];
        $this->missingTables = [];
        foreach (self::TARGETS as $t) {
            $sql = 'SELECT id, tenant_id, ' . $t['column'] . ' AS p FROM ' . $t['table']
                . ' WHERE ' . $t['column'] . " LIKE 'uploads/sse-id-%'" . ($tenant !== null ? ' AND tenant_id = :t' : '') . ' ORDER BY id';
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute($tenant !== null ? ['t' => $tenant] : []);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                $this->missingTables[] = $t['table'] . '.' . $t['column'];
                continue;
            }
            foreach ($rows as $r) {
                $from = (string) $r['p'];
                $to = SseImageStorage::toPrivatePath($from);
                if ($to === null) {
                    continue; // chemin étranger à la forme attendue : jamais touché
                }
                $groups[$from]['from'] = $from;
                $groups[$from]['to'] = $to;
                $groups[$from]['rows'][] = ['table' => $t['table'], 'column' => $t['column'], 'id' => (int) $r['id'], 'tenant' => (int) $r['tenant_id']];
            }
        }
        ksort($groups);

        return array_values($groups);
    }

    /** @return list<string> colonnes introuvables lors du dernier collect() */
    public function missingTables(): array
    {
        return $this->missingTables;
    }

    /**
     * Plan (simulation) ou exécution.
     *
     * @return array{
     *     apply: bool, files: int, bytes: int, moved: int, reused: int, skipped: int, errors: int, collisions: int, missing: int,
     *     items: list<array<string, mixed>>
     * }
     */
    public function run(bool $apply, ?int $tenant = null, ?int $limit = null): array
    {
        $report = ['apply' => $apply, 'files' => 0, 'bytes' => 0, 'moved' => 0, 'reused' => 0, 'skipped' => 0, 'errors' => 0, 'collisions' => 0, 'missing' => 0, 'items' => []];
        $groups = $this->collect($tenant);
        if ($limit !== null && $limit > 0) {
            $groups = array_slice($groups, 0, $limit);
        }
        foreach ($groups as $g) {
            $item = $this->one($g, $apply);
            ++$report['files'];
            $report['bytes'] += (int) ($item['bytes'] ?? 0);
            match ($item['status']) {
                'moved' => ++$report['moved'],
                'reused' => ++$report['reused'],
                'collision' => ++$report['collisions'],
                'missing' => ++$report['missing'],
                'error' => ++$report['errors'],
                default => ++$report['skipped'],
            };
            $report['items'][] = $item;
            if ($apply) {
                $this->log($item);
            }
        }

        return $report;
    }

    /**
     * @param array{from: string, to: string, rows: list<array{table: string, column: string, id: int, tenant: int}>} $g
     * @return array<string, mixed>
     */
    private function one(array $g, bool $apply): array
    {
        $item = ['from' => $g['from'], 'to' => $g['to'], 'rows' => $g['rows'], 'bytes' => 0, 'status' => 'planned', 'detail' => ''];
        $src = SseImageStorage::absolute($g['from'], $this->publicDir, $this->privateDir);
        $dst = $this->privateDir . DIRECTORY_SEPARATOR . substr($g['to'], strlen(SseImageStorage::PATH_PREFIX));
        $dstExists = is_file($dst);
        if ($src === null && !$dstExists) {
            $item['status'] = 'missing';
            $item['detail'] = 'fichier public introuvable (chemin laissé tel quel)';

            return $item;
        }
        $item['bytes'] = $src !== null ? (int) filesize($src) : (int) filesize($dst);
        $hashSrc = $src !== null ? (string) hash_file('sha256', $src) : null;
        if ($dstExists && $src !== null && hash_file('sha256', $dst) !== $hashSrc) {
            $item['status'] = 'collision';
            $item['detail'] = 'une copie privée DIFFÉRENTE existe déjà : rien n’est écrasé ni modifié';

            return $item;
        }
        if (!$apply) {
            $item['detail'] = $dstExists ? 'copie privée identique déjà présente (reprise)' : 'serait copié, vérifié, puis le chemin mis à jour et la copie publique supprimée';

            return $item;
        }

        try {
            if (!$dstExists) {
                $copied = $this->copyVerified((string) $src, $dst, (string) $hashSrc);
                if ($copied !== null) {
                    $item['status'] = 'error';
                    $item['detail'] = $copied;

                    return $item;
                }
            } elseif (SseImageStorage::verifiedMime($dst) === null) {
                $item['status'] = 'error';
                $item['detail'] = 'la copie privée présente n’est pas une image valide';

                return $item;
            }
            $this->pdo->beginTransaction();
            $updated = 0;
            foreach ($g['rows'] as $r) {
                $st = $this->pdo->prepare('UPDATE ' . $r['table'] . ' SET ' . $r['column'] . ' = :n WHERE id = :id AND ' . $r['column'] . ' = :o');
                $st->execute(['n' => $g['to'], 'id' => $r['id'], 'o' => $g['from']]);
                $updated += $st->rowCount();
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $item['status'] = 'error';
            $item['detail'] = 'base non modifiée : ' . $e->getMessage();

            return $item;
        }
        $item['updated'] = $updated;
        $item['status'] = $dstExists ? 'reused' : 'moved';
        // Suppression de la copie publique : seulement si le chemin n'est plus référencé nulle part et si la copie privée est intacte.
        if ($src !== null) {
            if ($this->stillReferenced($g['from'])) {
                $item['detail'] = 'copie publique conservée : une autre ligne (autre communauté ou filtre --tenant) la référence encore';
            } elseif (is_file($dst) && hash_file('sha256', $dst) === $hashSrc && @unlink($src)) {
                $item['detail'] = 'copie publique supprimée';
            } else {
                $item['detail'] = 'copie publique NON supprimée (à vérifier à la main)';
            }
        }

        return $item;
    }

    /** Copie vers un nom temporaire, contrôle (taille, SHA-256, type d'image), renommage. Retourne un message d'erreur ou null. */
    private function copyVerified(string $src, string $dst, string $hash): ?string
    {
        $dir = dirname($dst);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'dossier privé impossible à créer';
        }
        $tmp = $dst . '.tmp-' . bin2hex(random_bytes(4));
        if (!@copy($src, $tmp)) {
            @unlink($tmp);

            return 'copie impossible';
        }
        if (filesize($tmp) !== filesize($src) || hash_file('sha256', $tmp) !== $hash || SseImageStorage::verifiedMime($tmp) === null) {
            @unlink($tmp);

            return 'contrôle d’intégrité échoué (taille, empreinte ou type d’image) : copie annulée';
        }
        if (!@rename($tmp, $dst)) {
            @unlink($tmp);

            return 'renommage de la copie impossible';
        }

        return null;
    }

    private function stillReferenced(string $path): bool
    {
        foreach (self::TARGETS as $t) {
            try {
                $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $t['table'] . ' WHERE ' . $t['column'] . ' = :p');
                $st->execute(['p' => $path]);
                if ((int) $st->fetchColumn() > 0) {
                    return true;
                }
            } catch (Throwable) {
                // table absente : aucune référence
            }
        }

        return false;
    }

    /**
     * Retour arrière d'après le journal : remet la copie publique (si absente) et l'ancien chemin en base. L'espace privé n'est pas vidé.
     *
     * @return array{restored: int, errors: int, items: list<array<string, mixed>>}
     */
    public function rollback(string $journal): array
    {
        $out = ['restored' => 0, 'errors' => 0, 'items' => []];
        if (!is_file($journal)) {
            return $out + ['error' => 'journal introuvable'];
        }
        foreach ((array) file($journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $e = json_decode((string) $line, true);
            if (!is_array($e) || !in_array($e['status'] ?? '', ['moved', 'reused'], true) || !isset($e['from'], $e['to'])) {
                continue;
            }
            $from = (string) $e['from'];
            $to = (string) $e['to'];
            if (SseImageStorage::toPrivatePath($from) !== $to) {
                continue; // journal altéré : jamais de chemin hors forme
            }
            $priv = SseImageStorage::absolute($to, $this->publicDir, $this->privateDir);
            $pub = $this->publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . substr($from, strlen('uploads/'));
            try {
                if ($priv === null) {
                    throw new \RuntimeException('copie privée absente');
                }
                if (!is_file($pub)) {
                    $dir = dirname($pub);
                    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                        throw new \RuntimeException('dossier public impossible à créer');
                    }
                    if (!@copy($priv, $pub) || hash_file('sha256', $pub) !== hash_file('sha256', $priv)) {
                        @unlink($pub);
                        throw new \RuntimeException('copie publique impossible ou différente');
                    }
                }
                $this->pdo->beginTransaction();
                foreach ((array) ($e['rows'] ?? []) as $r) {
                    $table = (string) ($r['table'] ?? '');
                    $column = (string) ($r['column'] ?? '');
                    if (!in_array(['table' => $table, 'column' => $column], self::TARGETS, true)) {
                        continue;
                    }
                    $st = $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $column . ' = :o WHERE id = :id AND ' . $column . ' = :n');
                    $st->execute(['o' => $from, 'id' => (int) $r['id'], 'n' => $to]);
                }
                $this->pdo->commit();
                ++$out['restored'];
                $out['items'][] = ['from' => $from, 'status' => 'restored'];
            } catch (Throwable $ex) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                ++$out['errors'];
                $out['items'][] = ['from' => $from, 'status' => 'error', 'detail' => $ex->getMessage()];
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $item */
    private function log(array $item): void
    {
        if ($this->logFile === null) {
            return;
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($this->logFile, json_encode(['at' => gmdate('c')] + $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}
