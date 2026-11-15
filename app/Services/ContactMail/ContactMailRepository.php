<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

use App\Core\Database;
use PDO;

/**
 * Lecture et écriture des tables contact_mail_* (fils, messages, pièces jointes, état de la synchronisation).
 * Toutes les dates sont en UTC. Aucune méthode ne journalise de contenu de message.
 */
class ContactMailRepository
{
    public const FOLDERS = ['inbox', 'unread', 'todo', 'starred', 'archived', 'draft', 'spam', 'trash'];

    public const PER_PAGE = 30;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Les quatre tables existent (migration « contact_mailbox » passée). */
    public function ready(): bool
    {
        try {
            foreach (['contact_mail_threads', 'contact_mail_messages', 'contact_mail_attachments', 'contact_mail_sync_state'] as $t) {
                $this->db()->query('SELECT 1 FROM ' . $t . ' LIMIT 1');
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $row = $this->db()->query(
            "SELECT
                COALESCE(SUM(status = 'inbox'), 0) AS inbox,
                COALESCE(SUM(status = 'inbox' AND unread_count > 0), 0) AS unread,
                COALESCE(SUM(status = 'inbox' AND todo = 1), 0) AS todo,
                COALESCE(SUM(starred = 1 AND status NOT IN ('trash', 'spam')), 0) AS starred,
                COALESCE(SUM(status = 'archived'), 0) AS archived,
                COALESCE(SUM(status = 'spam'), 0) AS spam,
                COALESCE(SUM(status = 'trash'), 0) AS trash
             FROM contact_mail_threads"
        )?->fetch(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach (['inbox', 'unread', 'todo', 'starred', 'archived', 'spam', 'trash'] as $k) {
            $out[$k] = (int) ($row[$k] ?? 0);
        }
        $draft = $this->db()->query(
            "SELECT COUNT(*) FROM contact_mail_threads t WHERE t.status = 'draft'
                OR EXISTS (SELECT 1 FROM contact_mail_messages m WHERE m.thread_id = t.id AND m.state = 'draft')"
        );
        $out['draft'] = $draft ? (int) $draft->fetchColumn() : 0;

        return $out;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listThreads(string $folder, string $query, int $page): array
    {
        [$where, $params] = $this->filter($folder, $query);
        $page = max(1, $page);
        $count = $this->db()->prepare('SELECT COUNT(*) FROM contact_mail_threads t WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $st = $this->db()->prepare(
            'SELECT t.* FROM contact_mail_threads t WHERE ' . $where
            . ' ORDER BY t.last_message_at DESC, t.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE)
        );
        $st->execute($params);

        return ['rows' => array_values($st->fetchAll(PDO::FETCH_ASSOC) ?: []), 'total' => $total];
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function filter(string $folder, string $query): array
    {
        $where = match ($folder) {
            'unread' => "t.status = 'inbox' AND t.unread_count > 0",
            'todo' => "t.status = 'inbox' AND t.todo = 1",
            'starred' => "t.starred = 1 AND t.status NOT IN ('trash', 'spam')",
            'archived' => "t.status = 'archived'",
            'spam' => "t.status = 'spam'",
            'trash' => "t.status = 'trash'",
            'draft' => "(t.status = 'draft' OR EXISTS (SELECT 1 FROM contact_mail_messages d WHERE d.thread_id = t.id AND d.state = 'draft'))",
            default => "t.status = 'inbox'",
        };
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $like = '%' . addcslashes(mb_substr($query, 0, 100), '\\%_') . '%';
            $where .= ' AND (t.subject LIKE ? OR t.participant_email LIKE ? OR t.participant_name LIKE ? OR t.last_snippet LIKE ?'
                . ' OR EXISTS (SELECT 1 FROM contact_mail_messages s WHERE s.thread_id = t.id AND (s.body_text LIKE ? OR s.from_email LIKE ?)))';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        return [$where, $params];
    }

    /** @return array<string, mixed>|null */
    public function thread(int $id): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM contact_mail_threads WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function messages(int $threadId, bool $withDrafts = false): array
    {
        $st = $this->db()->prepare(
            'SELECT * FROM contact_mail_messages WHERE thread_id = ?' . ($withDrafts ? '' : " AND state <> 'draft'") . ' ORDER BY sent_at ASC, id ASC'
        );
        $st->execute([$threadId]);

        return array_values($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function draft(int $threadId): ?array
    {
        $st = $this->db()->prepare("SELECT * FROM contact_mail_messages WHERE thread_id = ? AND state = 'draft' ORDER BY id DESC LIMIT 1");
        $st->execute([$threadId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $messageIds
     * @return array<int, list<array<string, mixed>>> pièces jointes par message
     */
    public function attachmentsFor(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($messageIds), '?'));
        $st = $this->db()->prepare('SELECT * FROM contact_mail_attachments WHERE message_id IN (' . $in . ') ORDER BY id ASC');
        $st->execute(array_values(array_map('intval', $messageIds)));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['message_id']][] = $row;
        }

        return $out;
    }

    /** @return array<string, mixed>|null pièce jointe et fil de son message */
    public function attachment(int $id): ?array
    {
        $st = $this->db()->prepare(
            'SELECT a.*, m.thread_id FROM contact_mail_attachments a JOIN contact_mail_messages m ON m.id = a.message_id WHERE a.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Fil qui contient un des messages dont l'identifiant est donné (le plus récent d'abord).
     *
     * @param list<string> $messageIds
     */
    public function threadIdByMessageIds(array $messageIds): ?int
    {
        $ids = array_values(array_filter(array_unique($messageIds), static fn (string $s): bool => $s !== ''));
        if ($ids === []) {
            return null;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db()->prepare('SELECT thread_id FROM contact_mail_messages WHERE message_id IN (' . $in . ") AND state <> 'draft' ORDER BY id DESC LIMIT 1");
        $st->execute($ids);
        $v = $st->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    /** Repli : un fil du même correspondant et du même objet, actif depuis moins de $days jours. */
    public function threadIdBySubject(string $subjectNorm, string $email, int $days = 45): ?int
    {
        if ($subjectNorm === '' || $email === '') {
            return null;
        }
        $st = $this->db()->prepare(
            "SELECT id FROM contact_mail_threads WHERE subject_norm = ? AND participant_email = ? AND status <> 'spam'
                AND last_message_at >= ? ORDER BY last_message_at DESC LIMIT 1"
        );
        $st->execute([$subjectNorm, $email, gmdate('Y-m-d H:i:s', time() - $days * 86400)]);
        $v = $st->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    public function messageExistsByImap(int $uidValidity, int $uid): bool
    {
        $st = $this->db()->prepare('SELECT 1 FROM contact_mail_messages WHERE imap_uidvalidity = ? AND imap_uid = ? LIMIT 1');
        $st->execute([$uidValidity, $uid]);

        return $st->fetchColumn() !== false;
    }

    /** Un message reçu porte déjà cet identifiant (double livraison, UIDVALIDITY changé). */
    public function inboundMessageIdExists(string $messageId): bool
    {
        if ($messageId === '') {
            return false;
        }
        $st = $this->db()->prepare("SELECT 1 FROM contact_mail_messages WHERE message_id = ? AND direction = 'in' LIMIT 1");
        $st->execute([$messageId]);

        return $st->fetchColumn() !== false;
    }

    public function createThread(string $subject, string $email, string $name, string $source, string $status = 'inbox'): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $st = $this->db()->prepare(
            'INSERT INTO contact_mail_threads (subject, subject_norm, participant_email, participant_name, source, status, last_message_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            mb_substr($subject, 0, 250),
            MailHeaderGuard::normalizeSubject($subject),
            mb_substr($email, 0, 254),
            mb_substr($name, 0, 160),
            $source,
            $status,
            $now,
            $now,
            $now,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $m colonnes de contact_mail_messages
     */
    public function insertMessage(int $threadId, array $m): int
    {
        $text = (string) ($m['body_text'] ?? '');
        $st = $this->db()->prepare(
            'INSERT INTO contact_mail_messages (thread_id, direction, state, source, message_id, in_reply_to, refs, from_name, from_email,
                reply_to_email, to_json, cc_json, subject, body_text, body_html, snippet, sent_at, is_read, auto_reply, has_attachments,
                imap_uidvalidity, imap_uid, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $threadId,
            (string) ($m['direction'] ?? 'in'),
            (string) ($m['state'] ?? 'received'),
            (string) ($m['source'] ?? 'mail'),
            mb_substr((string) ($m['message_id'] ?? ''), 0, 255),
            mb_substr((string) ($m['in_reply_to'] ?? ''), 0, 255),
            (string) ($m['refs'] ?? ''),
            mb_substr((string) ($m['from_name'] ?? ''), 0, 160),
            mb_substr((string) ($m['from_email'] ?? ''), 0, 254),
            mb_substr((string) ($m['reply_to_email'] ?? ''), 0, 254),
            json_encode($m['to'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($m['cc'] ?? [], JSON_UNESCAPED_UNICODE),
            mb_substr((string) ($m['subject'] ?? ''), 0, 250),
            $text,
            $m['body_html'] ?? null,
            self::snippet($text),
            (string) ($m['sent_at'] ?? gmdate('Y-m-d H:i:s')),
            !empty($m['is_read']) ? 1 : 0,
            !empty($m['auto_reply']) ? 1 : 0,
            !empty($m['has_attachments']) ? 1 : 0,
            $m['imap_uidvalidity'] ?? null,
            $m['imap_uid'] ?? null,
            gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateDraft(int $messageId, array $fields): void
    {
        $st = $this->db()->prepare(
            "UPDATE contact_mail_messages SET to_json = ?, cc_json = ?, subject = ?, body_text = ?, snippet = ?, sent_at = ? WHERE id = ? AND state = 'draft'"
        );
        $st->execute([
            json_encode($fields['to'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($fields['cc'] ?? [], JSON_UNESCAPED_UNICODE),
            mb_substr((string) ($fields['subject'] ?? ''), 0, 250),
            (string) ($fields['body_text'] ?? ''),
            self::snippet((string) ($fields['body_text'] ?? '')),
            gmdate('Y-m-d H:i:s'),
            $messageId,
        ]);
    }

    public function deleteMessage(int $messageId): void
    {
        $st = $this->db()->prepare('DELETE FROM contact_mail_messages WHERE id = ?');
        $st->execute([$messageId]);
    }

    public function insertAttachment(int $messageId, string $filename, string $mime, int $size, string $contentId, bool $inline, ?string $path, string $sha256): void
    {
        $st = $this->db()->prepare(
            'INSERT INTO contact_mail_attachments (message_id, filename, mime, size, content_id, is_inline, storage_path, sha256, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$messageId, mb_substr($filename, 0, 190), mb_substr($mime, 0, 120), max(0, $size), mb_substr($contentId, 0, 200), $inline ? 1 : 0, $path, $sha256, gmdate('Y-m-d H:i:s')]);
    }

    /** Recalcule compteurs, dernier message et extrait d'un fil à partir de ses messages (brouillons exclus). */
    public function refreshThread(int $threadId): void
    {
        $st = $this->db()->prepare(
            "SELECT COUNT(*) AS c, COALESCE(SUM(is_read = 0 AND direction = 'in'), 0) AS u, MAX(sent_at) AS last_at
             FROM contact_mail_messages WHERE thread_id = ? AND state <> 'draft'"
        );
        $st->execute([$threadId]);
        $agg = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $last = $this->db()->prepare(
            "SELECT direction, snippet FROM contact_mail_messages WHERE thread_id = ? AND state <> 'draft' ORDER BY sent_at DESC, id DESC LIMIT 1"
        );
        $last->execute([$threadId]);
        $l = $last->fetch(PDO::FETCH_ASSOC) ?: ['direction' => 'in', 'snippet' => ''];
        $up = $this->db()->prepare(
            'UPDATE contact_mail_threads SET message_count = ?, unread_count = ?, last_direction = ?, last_snippet = ?,
                last_message_at = COALESCE(?, last_message_at), updated_at = ? WHERE id = ?'
        );
        $up->execute([
            (int) ($agg['c'] ?? 0),
            (int) ($agg['u'] ?? 0),
            (string) $l['direction'],
            (string) $l['snippet'],
            $agg['last_at'] ?? null,
            gmdate('Y-m-d H:i:s'),
            $threadId,
        ]);
    }

    public function setStatus(int $threadId, string $status): void
    {
        if (!in_array($status, ['inbox', 'archived', 'spam', 'trash', 'draft'], true)) {
            return;
        }
        $st = $this->db()->prepare('UPDATE contact_mail_threads SET status = ?, updated_at = ? WHERE id = ?');
        $st->execute([$status, gmdate('Y-m-d H:i:s'), $threadId]);
    }

    public function setStarred(int $threadId, bool $starred): void
    {
        $st = $this->db()->prepare('UPDATE contact_mail_threads SET starred = ? WHERE id = ?');
        $st->execute([$starred ? 1 : 0, $threadId]);
    }

    public function setTodo(int $threadId, bool $todo): void
    {
        $st = $this->db()->prepare('UPDATE contact_mail_threads SET todo = ? WHERE id = ?');
        $st->execute([$todo ? 1 : 0, $threadId]);
    }

    public function setThreadRead(int $threadId, bool $read): void
    {
        $st = $this->db()->prepare("UPDATE contact_mail_messages SET is_read = ? WHERE thread_id = ? AND direction = 'in'");
        $st->execute([$read ? 1 : 0, $threadId]);
        $this->refreshThread($threadId);
    }

    /** Un message reçu redonne vie au fil : archivé ou à la corbeille, il revient dans la boîte (le spam y reste). */
    public function reopenForInbound(int $threadId): void
    {
        $st = $this->db()->prepare("UPDATE contact_mail_threads SET status = 'inbox', todo = 1 WHERE id = ? AND status IN ('archived', 'trash', 'draft')");
        $st->execute([$threadId]);
        $st = $this->db()->prepare("UPDATE contact_mail_threads SET todo = 1 WHERE id = ? AND status = 'inbox'");
        $st->execute([$threadId]);
    }

    /** Suppression définitive d'un fil de la corbeille : retourne les fichiers à effacer. */
    public function purgeThread(int $threadId): array
    {
        $st = $this->db()->prepare(
            'SELECT a.storage_path FROM contact_mail_attachments a JOIN contact_mail_messages m ON m.id = a.message_id WHERE m.thread_id = ? AND a.storage_path IS NOT NULL'
        );
        $st->execute([$threadId]);
        $paths = array_values(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
        $del = $this->db()->prepare("DELETE FROM contact_mail_threads WHERE id = ? AND status IN ('trash', 'draft')");
        $del->execute([$threadId]);

        return $del->rowCount() > 0 ? $paths : [];
    }

    public function sentCountSince(int $seconds): int
    {
        $st = $this->db()->prepare("SELECT COUNT(*) FROM contact_mail_messages WHERE direction = 'out' AND state = 'sent' AND created_at >= ?");
        $st->execute([gmdate('Y-m-d H:i:s', time() - $seconds)]);

        return (int) $st->fetchColumn();
    }

    /** @return array{uidvalidity: int, last_uid: int, last_sync_at: ?string, last_ok_at: ?string, last_error: string, imported_total: int} */
    public function syncState(string $folder): array
    {
        $st = $this->db()->prepare('SELECT * FROM contact_mail_sync_state WHERE folder = ?');
        $st->execute([$folder]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'uidvalidity' => (int) ($r['uidvalidity'] ?? 0),
            'last_uid' => (int) ($r['last_uid'] ?? 0),
            'last_sync_at' => isset($r['last_sync_at']) ? (string) $r['last_sync_at'] : null,
            'last_ok_at' => isset($r['last_ok_at']) ? (string) $r['last_ok_at'] : null,
            'last_error' => (string) ($r['last_error'] ?? ''),
            'imported_total' => (int) ($r['imported_total'] ?? 0),
        ];
    }

    public function saveSyncState(string $folder, int $uidValidity, int $lastUid, bool $ok, string $error, int $importedDelta): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $st = $this->db()->prepare(
            'INSERT INTO contact_mail_sync_state (folder, uidvalidity, last_uid, last_sync_at, last_ok_at, last_error, imported_total)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE uidvalidity = VALUES(uidvalidity), last_uid = VALUES(last_uid), last_sync_at = VALUES(last_sync_at),
                last_ok_at = IF(? = 1, VALUES(last_sync_at), last_ok_at), last_error = VALUES(last_error),
                imported_total = imported_total + ?'
        );
        $st->execute([$folder, $uidValidity, $lastUid, $now, $ok ? $now : null, mb_substr($error, 0, 250), max(0, $importedDelta), $ok ? 1 : 0, max(0, $importedDelta)]);
    }

    /** Extrait d'un corps texte : une ligne, citations (« > ») retirées. */
    public static function snippet(string $text): string
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $t = trim($line);
            if ($t === '' || $t[0] === '>') {
                continue;
            }
            $lines[] = $t;
            if (count($lines) >= 4) {
                break;
            }
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', $lines)) ?? ''), 0, 200);
    }
}
