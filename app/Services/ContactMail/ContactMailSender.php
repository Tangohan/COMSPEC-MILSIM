<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

use App\Services\Audit\AuditService;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi depuis la boîte de contact : SMTP avec les identifiants de la boîte, expéditeur = adresse de contact, fil de
 * discussion (In-Reply-To, References) reconstitué d'après la base, texte brut. Chaque adresse et l'objet sont refusés
 * s'ils portent un retour à la ligne. Plafond d'envois par heure, pièces jointes limitées, journal d'audit sans contenu.
 */
final class ContactMailSender
{
    public const MAX_BODY_CHARS = 20000;

    public const MAX_ATTACHMENTS = 5;

    public const MAX_ATTACHMENT_BYTES = 5242880;

    public const MAX_TOTAL_BYTES = 10485760;

    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'msi', 'js', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'jar', 'lnk', 'dll',
        'hta', 'cpl', 'reg', 'apk', 'sh', 'php', 'phtml',
    ];

    /** @var (callable(string): void)|null */
    private $sentCopy;

    /** @var (callable(PHPMailer): void)|null */
    private $transport;

    /**
     * @param (callable(string): void)|null $sentCopy dépose le message brut dans « Envoyés » (par défaut : IMAP, au mieux)
     * @param (callable(PHPMailer): void)|null $transport remplace l'envoi SMTP (essais) ; reçoit le message déjà préparé
     */
    public function __construct(
        private ContactMailConfig $config,
        private ContactMailRepository $repo,
        private ContactMailIngest $ingest,
        ?callable $sentCopy = null,
        ?callable $transport = null,
    ) {
        $this->sentCopy = $sentCopy;
        $this->transport = $transport;
    }

    /**
     * @param array{
     *   thread_id?: int|null, reply_to_message_id?: int|null, draft_id?: int|null,
     *   to?: string, cc?: string, subject?: string, body?: string,
     *   attachments?: list<array{name: string, content: string, mime?: string}>
     * } $input
     * @return array{ok: bool, error?: string, thread_id?: int}
     */
    public function send(array $input): array
    {
        if (!$this->config->isConfigured()) {
            return ['ok' => false, 'error' => 'Boîte non configurée : renseignez CONTACT_MAIL_PASSWORD dans le .env du serveur.'];
        }
        if (!$this->repo->ready()) {
            return ['ok' => false, 'error' => 'Les tables de la boîte de contact sont absentes (migration non passée).'];
        }
        try {
            $to = MailHeaderGuard::addressList((string) ($input['to'] ?? ''));
            if ($to === []) {
                throw new \InvalidArgumentException('Indiquez au moins un destinataire.');
            }
            $cc = MailHeaderGuard::addressList((string) ($input['cc'] ?? ''));
            if (count($to) + count($cc) > MailHeaderGuard::MAX_RECIPIENTS) {
                throw new \InvalidArgumentException('Trop de destinataires.');
            }
            $subject = MailHeaderGuard::subject((string) ($input['subject'] ?? ''));
            $body = str_replace(["\r\n", "\r"], "\n", (string) ($input['body'] ?? ''));
            if (trim($body) === '') {
                throw new \InvalidArgumentException('Le message est vide.');
            }
            if (mb_strlen($body) > self::MAX_BODY_CHARS) {
                throw new \InvalidArgumentException('Le message est trop long (' . self::MAX_BODY_CHARS . ' caractères au plus).');
            }
            $files = $this->checkAttachments($input['attachments'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($this->repo->sentCountSince(3600) >= $this->config->sendPerHour()) {
            return ['ok' => false, 'error' => 'Limite d’envois atteinte (' . $this->config->sendPerHour() . ' par heure). Réessayez plus tard.'];
        }

        $threadId = isset($input['thread_id']) ? (int) $input['thread_id'] : 0;
        $thread = $threadId > 0 ? $this->repo->thread($threadId) : null;
        if ($threadId > 0 && $thread === null) {
            return ['ok' => false, 'error' => 'Fil introuvable.'];
        }
        $inReplyTo = '';
        $references = '';
        if ($thread !== null) {
            $target = $this->targetMessage($threadId, (int) ($input['reply_to_message_id'] ?? 0));
            if ($target !== null) {
                $ctx = ReplyComposer::defaults($thread, $target, $this->config);
                $inReplyTo = $ctx['in_reply_to'];
                $references = $ctx['references'];
            }
        }

        $messageId = bin2hex(random_bytes(16)) . '@' . self::domainOf($this->config->address());
        try {
            $mail = $this->build($to, $cc, $subject, $body, $files, $messageId, $inReplyTo, $references);
            $mail->preSend();
            if ($this->transport !== null) {
                ($this->transport)($mail);
            } else {
                $mail->postSend();
            }
            $raw = $mail->getSentMIMEMessage();
        } catch (\Throwable $e) {
            $info = isset($mail) && $mail instanceof PHPMailer && $mail->ErrorInfo !== '' ? $mail->ErrorInfo : 'envoi impossible';
            error_log('[contact_mail] envoi refusé : ' . get_class($e));

            return ['ok' => false, 'error' => 'Envoi refusé par le serveur de messagerie : ' . mb_substr($info, 0, 200)];
        }

        $threadId = $this->record($thread, $threadId, $to, $cc, $subject, $body, $files, $messageId, $inReplyTo, $references, (int) ($input['draft_id'] ?? 0));
        $this->copyToSent($raw);
        AuditService::logPlatformAction('platform.contact_mail_sent', [
            'thread_id' => $threadId,
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'attachments' => count($files),
        ]);

        return ['ok' => true, 'thread_id' => $threadId];
    }

    /**
     * Message prêt à partir (non envoyé). Public pour que les essais lisent le message construit.
     *
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<array{name: string, content: string, mime: string}> $files
     */
    public function build(array $to, array $cc, string $subject, string $body, array $files, string $messageId, string $inReplyTo = '', string $references = ''): PHPMailer
    {
        if (!class_exists(PHPMailer::class)) {
            require_once \base_path('bootstrap/load_phpmailer.php');
        }
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->XMailer = ' ';
        $mail->isSMTP();
        $mail->Host = $this->config->smtpHost();
        $mail->Port = $this->config->smtpPort();
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->user();
        $mail->Password = $this->config->password();
        $mail->SMTPSecure = $this->config->smtpPort() === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = $this->config->timeout();
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];

        $mail->setFrom($this->config->address(), MailHeaderGuard::displayName($this->config->fromName()), false);
        foreach ($to as $a) {
            $mail->addAddress(MailHeaderGuard::address($a));
        }
        foreach ($cc as $a) {
            $mail->addCC(MailHeaderGuard::address($a));
        }
        $mail->Subject = MailHeaderGuard::subject($subject);
        $mail->isHTML(false);
        $mail->Body = $body;
        $mail->MessageID = '<' . $messageId . '>';
        if ($inReplyTo !== '' && preg_match('/^[^\s<>]+$/', $inReplyTo) === 1) {
            $mail->addCustomHeader('In-Reply-To', '<' . $inReplyTo . '>');
            $ids = array_filter(preg_split('/\s+/', trim($references)) ?: [], static fn (string $r): bool => preg_match('/^[^\s<>]+$/', $r) === 1);
            $mail->addCustomHeader('References', implode(' ', array_map(static fn (string $r): string => '<' . $r . '>', $ids)));
        }
        foreach ($files as $f) {
            $mail->addStringAttachment($f['content'], $f['name'], PHPMailer::ENCODING_BASE64, $f['mime']);
        }

        return $mail;
    }

    /**
     * @param mixed $attachments
     * @return list<array{name: string, content: string, mime: string}>
     * @throws \InvalidArgumentException
     */
    private function checkAttachments(mixed $attachments): array
    {
        $out = [];
        $total = 0;
        foreach (is_array($attachments) ? $attachments : [] as $a) {
            if (!is_array($a) || !isset($a['name'], $a['content'])) {
                continue;
            }
            if (count($out) >= self::MAX_ATTACHMENTS) {
                throw new \InvalidArgumentException('Pas plus de ' . self::MAX_ATTACHMENTS . ' pièces jointes.');
            }
            $content = (string) $a['content'];
            $name = MimeParser::cleanFilename((string) $a['name'], 'application/octet-stream', count($out) + 1);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                throw new \InvalidArgumentException('Type de fichier refusé en pièce jointe : .' . $ext);
            }
            if (strlen($content) > self::MAX_ATTACHMENT_BYTES) {
                throw new \InvalidArgumentException('Une pièce jointe dépasse ' . (int) (self::MAX_ATTACHMENT_BYTES / 1048576) . ' Mo.');
            }
            $total += strlen($content);
            if ($total > self::MAX_TOTAL_BYTES) {
                throw new \InvalidArgumentException('Les pièces jointes dépassent ' . (int) (self::MAX_TOTAL_BYTES / 1048576) . ' Mo au total.');
            }
            $out[] = ['name' => $name, 'content' => $content, 'mime' => self::detectMime($content)];
        }

        return $out;
    }

    private static function detectMime(string $content): string
    {
        if (function_exists('finfo_buffer')) {
            $f = new \finfo(FILEINFO_MIME_TYPE);
            $m = $f->buffer($content);
            if (is_string($m) && preg_match('~^[a-z0-9.+\-]+/[a-z0-9.+\-]+$~', $m) === 1) {
                return $m;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function targetMessage(int $threadId, int $messageId): ?array
    {
        $found = null;
        foreach ($this->repo->messages($threadId) as $m) {
            if ($messageId > 0 && (int) $m['id'] === $messageId) {
                return $m;
            }
            if ($m['direction'] === 'in') {
                $found = $m;
            }
        }

        return $found;
    }

    /**
     * @param array<string, mixed>|null $thread
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<array{name: string, content: string, mime: string}> $files
     */
    private function record(?array $thread, int $threadId, array $to, array $cc, string $subject, string $body, array $files, string $messageId, string $inReplyTo, string $references, int $draftId): int
    {
        if ($thread === null) {
            $threadId = $this->repo->createThread($subject, $to[0], '', 'compose');
        } elseif (($thread['status'] ?? '') === 'draft') {
            $this->repo->setStatus($threadId, 'inbox');
        }
        if ($draftId > 0) {
            $this->repo->deleteMessage($draftId);
        }
        $row = $this->repo->insertMessage($threadId, [
            'direction' => 'out',
            'state' => 'sent',
            'source' => $thread === null ? 'compose' : 'reply',
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'refs' => $references,
            'from_name' => $this->config->fromName(),
            'from_email' => strtolower($this->config->address()),
            'to' => array_map(static fn (string $e): array => ['name' => '', 'email' => $e], $to),
            'cc' => array_map(static fn (string $e): array => ['name' => '', 'email' => $e], $cc),
            'subject' => $subject,
            'body_text' => $body,
            'sent_at' => gmdate('Y-m-d H:i:s'),
            'is_read' => true,
            'has_attachments' => $files !== [],
        ]);
        $this->ingest->storeAttachments($row, array_map(
            static fn (array $f): ParsedAttachment => new ParsedAttachment($f['name'], $f['mime'], $f['content'], strlen($f['content'])),
            $files
        ));
        $this->repo->refreshThread($threadId);
        $this->repo->setTodo($threadId, false);

        return $threadId;
    }

    private function copyToSent(string $raw): void
    {
        try {
            if ($this->sentCopy !== null) {
                ($this->sentCopy)($raw);

                return;
            }
            $folder = $this->config->sentFolder();
            if ($folder === '') {
                return;
            }
            $client = ImapClient::connect($this->config);
            try {
                $client->login($this->config->user(), $this->config->password());
                $client->append($folder, str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)));
            } finally {
                $client->logout();
            }
        } catch (\Throwable $e) {
            // La copie dans « Envoyés » est un confort : le message est parti et il est dans le fil.
            error_log('[contact_mail] copie dans Envoyés impossible : ' . get_class($e));
        }
    }

    /**
     * Brouillon local (rien n'est envoyé). Un seul brouillon par fil ; sans fil, un fil « brouillon » est créé.
     *
     * @param array{thread_id?: int|null, to?: string, cc?: string, subject?: string, body?: string} $input
     * @return array{ok: bool, error?: string, thread_id?: int}
     */
    public function saveDraft(array $input): array
    {
        if (!$this->config->isConfigured() || !$this->repo->ready()) {
            return ['ok' => false, 'error' => 'Boîte non configurée.'];
        }
        $clean = static fn (string $v, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $v) ?? ''), 0, $max);
        $to = $clean((string) ($input['to'] ?? ''), 500);
        $cc = $clean((string) ($input['cc'] ?? ''), 500);
        $subject = $clean(str_replace(["\r", "\n"], ' ', (string) ($input['subject'] ?? '')), MailHeaderGuard::MAX_SUBJECT);
        $body = mb_substr(str_replace("\r\n", "\n", (string) ($input['body'] ?? '')), 0, self::MAX_BODY_CHARS);
        $threadId = (int) ($input['thread_id'] ?? 0);
        if ($threadId > 0 && $this->repo->thread($threadId) === null) {
            return ['ok' => false, 'error' => 'Fil introuvable.'];
        }
        if ($threadId === 0) {
            try {
                $first = MailHeaderGuard::addressList($to)[0] ?? '';
            } catch (\InvalidArgumentException) {
                $first = '';
            }
            $threadId = $this->repo->createThread($subject !== '' ? $subject : '(brouillon)', $first, '', 'compose', 'draft');
        }
        $asList = static fn (string $csv): array => array_map(
            static fn (string $e): array => ['name' => '', 'email' => $e],
            array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $csv) ?: [])))
        );
        $existing = $this->repo->draft($threadId);
        $fields = ['to' => $asList($to), 'cc' => $asList($cc), 'subject' => $subject, 'body_text' => $body];
        if ($existing !== null) {
            $this->repo->updateDraft((int) $existing['id'], $fields);
        } else {
            $this->repo->insertMessage($threadId, $fields + [
                'direction' => 'out',
                'state' => 'draft',
                'source' => 'compose',
                'from_email' => strtolower($this->config->address()),
                'sent_at' => gmdate('Y-m-d H:i:s'),
                'is_read' => true,
            ]);
        }

        return ['ok' => true, 'thread_id' => $threadId];
    }

    private static function domainOf(string $address): string
    {
        $at = strrpos($address, '@');

        return $at === false ? 'localhost' : substr($address, $at + 1);
    }
}
