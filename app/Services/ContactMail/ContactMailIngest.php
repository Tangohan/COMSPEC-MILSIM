<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Range un message reçu (IMAP ou formulaire public) dans un fil : par En-têtes In-Reply-To / References d'abord, par objet
 * et correspondant ensuite (seulement si l'objet porte un « Re: »), sinon nouveau fil.
 */
final class ContactMailIngest
{
    public function __construct(
        private ContactMailRepository $repo,
        private AttachmentStorage $storage,
        private ContactMailConfig $config,
    ) {}

    /**
     * @param array{uidvalidity?: int, uid?: int, read?: bool} $meta
     * @return int|null identifiant du fil, null si le message était déjà connu
     */
    public function ingest(ParsedMessage $m, array $meta = []): ?int
    {
        $uidValidity = (int) ($meta['uidvalidity'] ?? 0);
        $uid = (int) ($meta['uid'] ?? 0);
        if ($uid > 0 && $this->repo->messageExistsByImap($uidValidity, $uid)) {
            return null;
        }

        $own = strtolower($this->config->address());
        $outgoing = $own !== '' && $m->fromEmail === $own;
        if (!$outgoing && $this->repo->inboundMessageIdExists($m->messageId)) {
            return null;
        }

        $counterpart = $outgoing ? ($m->to[0]['email'] ?? '') : $m->fromEmail;
        $counterpartName = $outgoing ? ($m->to[0]['name'] ?? '') : $m->fromName;
        $subject = $m->subject !== '' ? $m->subject : '(sans objet)';

        $threadId = $this->repo->threadIdByMessageIds(array_merge([$m->inReplyTo], $m->references));
        if ($threadId === null && MailHeaderGuard::baseSubject($subject) !== $subject) {
            $threadId = $this->repo->threadIdBySubject(MailHeaderGuard::normalizeSubject($subject), $counterpart);
        }
        $isNew = $threadId === null;
        if ($threadId === null) {
            $threadId = $this->repo->createThread($subject, $counterpart, $counterpartName, 'mail');
        }

        $messageRowId = $this->repo->insertMessage($threadId, [
            'direction' => $outgoing ? 'out' : 'in',
            'state' => $outgoing ? 'sent' : 'received',
            'source' => 'mail',
            'message_id' => $m->messageId,
            'in_reply_to' => $m->inReplyTo,
            'refs' => implode(' ', $m->references),
            'from_name' => $m->fromName,
            'from_email' => $m->fromEmail,
            'reply_to_email' => $m->replyToEmail,
            'to' => $m->to,
            'cc' => $m->cc,
            'subject' => $subject,
            'body_text' => $m->text,
            'body_html' => $m->html !== '' ? $m->html : null,
            'sent_at' => $m->sentAt,
            'is_read' => $outgoing || !empty($meta['read']),
            'auto_reply' => $m->autoReply,
            'has_attachments' => $m->attachments !== [],
            'imap_uidvalidity' => $uid > 0 ? $uidValidity : null,
            'imap_uid' => $uid > 0 ? $uid : null,
        ]);
        $this->storeAttachments($messageRowId, $m->attachments);
        $this->repo->refreshThread($threadId);
        if (!$outgoing && !$m->autoReply) {
            $this->repo->reopenForInbound($threadId);
        } elseif ($isNew && $outgoing) {
            $this->repo->setTodo($threadId, false);
        }

        return $threadId;
    }

    /**
     * Message déposé par le formulaire public « Nous contacter » : fil « Formulaire », adresse non vérifiée.
     */
    public function ingestForm(string $name, string $email, string $subject, string $message): int
    {
        $subject = trim($subject) !== '' ? trim($subject) : '(sans objet)';
        $threadId = $this->repo->createThread($subject, $email, $name, 'form');
        $this->repo->insertMessage($threadId, [
            'direction' => 'in',
            'state' => 'received',
            'source' => 'form',
            'from_name' => $name,
            'from_email' => $email,
            'to' => [['name' => '', 'email' => $this->config->address()]],
            'subject' => $subject,
            'body_text' => $message,
            'sent_at' => gmdate('Y-m-d H:i:s'),
            'is_read' => false,
        ]);
        $this->repo->refreshThread($threadId);
        $this->repo->setTodo($threadId, true);

        return $threadId;
    }

    /**
     * @param list<ParsedAttachment> $attachments
     */
    public function storeAttachments(int $messageRowId, array $attachments): void
    {
        foreach ($attachments as $a) {
            $path = null;
            $sha = '';
            if ($a->content !== null) {
                $saved = $this->storage->save($a->content);
                if ($saved !== null) {
                    $path = $saved['path'];
                    $sha = $saved['sha256'];
                }
            }
            $this->repo->insertAttachment($messageRowId, $a->filename, $a->mime, $a->size, $a->contentId, $a->inline, $path, $sha);
        }
    }
}
