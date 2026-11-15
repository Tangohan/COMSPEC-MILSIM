<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\ContactMail\ContactMailRepository;
use App\Services\ContactMail\MailHeaderGuard;

/** Dépôt de la boîte de contact en mémoire (aucune base) pour les essais du rangement, de la synchronisation et de l'envoi. */
final class InMemoryContactMailRepository extends ContactMailRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $threads = [];

    /** @var array<int, array<string, mixed>> */
    public array $messages = [];

    /** @var list<array<string, mixed>> */
    public array $attachments = [];

    /** @var array<string, array<string, mixed>> */
    public array $state = [];

    public bool $isReady = true;

    private int $nextThread = 1;

    private int $nextMessage = 1;

    public function __construct() {}

    public function ready(): bool
    {
        return $this->isReady;
    }

    public function thread(int $id): ?array
    {
        return $this->threads[$id] ?? null;
    }

    public function messages(int $threadId, bool $withDrafts = false): array
    {
        $out = [];
        foreach ($this->messages as $m) {
            if ($m['thread_id'] === $threadId && ($withDrafts || $m['state'] !== 'draft')) {
                $out[] = $m;
            }
        }

        return $out;
    }

    public function draft(int $threadId): ?array
    {
        foreach ($this->messages as $m) {
            if ($m['thread_id'] === $threadId && $m['state'] === 'draft') {
                return $m;
            }
        }

        return null;
    }

    public function threadIdByMessageIds(array $messageIds): ?int
    {
        $ids = array_filter($messageIds, static fn (string $s): bool => $s !== '');
        $found = null;
        foreach ($this->messages as $m) {
            if ($m['state'] !== 'draft' && in_array($m['message_id'], $ids, true)) {
                $found = (int) $m['thread_id'];
            }
        }

        return $found;
    }

    public function threadIdBySubject(string $subjectNorm, string $email, int $days = 45): ?int
    {
        foreach ($this->threads as $t) {
            if ($t['subject_norm'] === $subjectNorm && $t['participant_email'] === $email && $t['status'] !== 'spam') {
                return (int) $t['id'];
            }
        }

        return null;
    }

    public function messageExistsByImap(int $uidValidity, int $uid): bool
    {
        foreach ($this->messages as $m) {
            if ($m['imap_uidvalidity'] === $uidValidity && $m['imap_uid'] === $uid) {
                return true;
            }
        }

        return false;
    }

    public function inboundMessageIdExists(string $messageId): bool
    {
        foreach ($this->messages as $m) {
            if ($messageId !== '' && $m['message_id'] === $messageId && $m['direction'] === 'in') {
                return true;
            }
        }

        return false;
    }

    public function createThread(string $subject, string $email, string $name, string $source, string $status = 'inbox'): int
    {
        $id = $this->nextThread++;
        $this->threads[$id] = [
            'id' => $id, 'subject' => $subject, 'subject_norm' => MailHeaderGuard::normalizeSubject($subject),
            'participant_email' => $email, 'participant_name' => $name, 'source' => $source, 'status' => $status,
            'todo' => 0, 'starred' => 0, 'message_count' => 0, 'unread_count' => 0, 'last_direction' => 'in', 'last_snippet' => '',
            'last_message_at' => gmdate('Y-m-d H:i:s'),
        ];

        return $id;
    }

    public function insertMessage(int $threadId, array $m): int
    {
        $id = $this->nextMessage++;
        $this->messages[$id] = [
            'id' => $id, 'thread_id' => $threadId,
            'direction' => (string) ($m['direction'] ?? 'in'), 'state' => (string) ($m['state'] ?? 'received'),
            'source' => (string) ($m['source'] ?? 'mail'), 'message_id' => (string) ($m['message_id'] ?? ''),
            'in_reply_to' => (string) ($m['in_reply_to'] ?? ''), 'refs' => (string) ($m['refs'] ?? ''),
            'from_name' => (string) ($m['from_name'] ?? ''), 'from_email' => (string) ($m['from_email'] ?? ''),
            'reply_to_email' => (string) ($m['reply_to_email'] ?? ''),
            'to_json' => json_encode($m['to'] ?? []), 'cc_json' => json_encode($m['cc'] ?? []),
            'subject' => (string) ($m['subject'] ?? ''), 'body_text' => (string) ($m['body_text'] ?? ''),
            'body_html' => $m['body_html'] ?? null, 'sent_at' => (string) ($m['sent_at'] ?? gmdate('Y-m-d H:i:s')),
            'is_read' => !empty($m['is_read']) ? 1 : 0, 'auto_reply' => !empty($m['auto_reply']) ? 1 : 0,
            'imap_uidvalidity' => $m['imap_uidvalidity'] ?? null, 'imap_uid' => $m['imap_uid'] ?? null,
            'snippet' => self::snippet((string) ($m['body_text'] ?? '')),
        ];

        return $id;
    }

    public function insertAttachment(int $messageId, string $filename, string $mime, int $size, string $contentId, bool $inline, ?string $path, string $sha256): void
    {
        $this->attachments[] = ['message_id' => $messageId, 'filename' => $filename, 'mime' => $mime, 'size' => $size, 'content_id' => $contentId, 'storage_path' => $path];
    }

    public function refreshThread(int $threadId): void
    {
        $count = 0;
        $unread = 0;
        $last = null;
        foreach ($this->messages($threadId) as $m) {
            $count++;
            $unread += $m['direction'] === 'in' && (int) $m['is_read'] === 0 ? 1 : 0;
            $last = $m;
        }
        $this->threads[$threadId]['message_count'] = $count;
        $this->threads[$threadId]['unread_count'] = $unread;
        if ($last !== null) {
            $this->threads[$threadId]['last_direction'] = $last['direction'];
            $this->threads[$threadId]['last_snippet'] = $last['snippet'];
        }
    }

    public function setTodo(int $threadId, bool $todo): void
    {
        $this->threads[$threadId]['todo'] = $todo ? 1 : 0;
    }

    public function setStatus(int $threadId, string $status): void
    {
        $this->threads[$threadId]['status'] = $status;
    }

    public function reopenForInbound(int $threadId): void
    {
        if (in_array($this->threads[$threadId]['status'], ['archived', 'trash', 'draft'], true)) {
            $this->threads[$threadId]['status'] = 'inbox';
        }
        if ($this->threads[$threadId]['status'] === 'inbox') {
            $this->threads[$threadId]['todo'] = 1;
        }
    }

    public function deleteMessage(int $messageId): void
    {
        unset($this->messages[$messageId]);
    }

    public function updateDraft(int $messageId, array $fields): void
    {
        $this->messages[$messageId]['subject'] = (string) ($fields['subject'] ?? '');
        $this->messages[$messageId]['body_text'] = (string) ($fields['body_text'] ?? '');
    }

    public function sentCountSince(int $seconds): int
    {
        $n = 0;
        foreach ($this->messages as $m) {
            $n += $m['direction'] === 'out' && $m['state'] === 'sent' ? 1 : 0;
        }

        return $n;
    }

    public function syncState(string $folder): array
    {
        $s = $this->state[$folder] ?? [];

        return [
            'uidvalidity' => (int) ($s['uidvalidity'] ?? 0), 'last_uid' => (int) ($s['last_uid'] ?? 0),
            'last_sync_at' => null, 'last_ok_at' => null, 'last_error' => (string) ($s['last_error'] ?? ''), 'imported_total' => 0,
        ];
    }

    public function saveSyncState(string $folder, int $uidValidity, int $lastUid, bool $ok, string $error, int $importedDelta): void
    {
        $this->state[$folder] = ['uidvalidity' => $uidValidity, 'last_uid' => $lastUid, 'last_error' => $error];
    }
}
