<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Synchronisation entrante : lit la boîte IMAP de contact par UID croissants et range chaque message dans la base.
 * Incrémentale (dernier UID traité retenu après chaque message) et idempotente (UID et Message-ID déjà connus ignorés).
 * Sans configuration complète, aucune connexion n'est ouverte et aucune requête n'est faite.
 */
final class ContactMailSyncService
{
    public const FOLDER = 'INBOX';

    /** @var (callable(): ImapClient)|null */
    private $clientFactory;

    /**
     * @param (callable(): ImapClient)|null $clientFactory null : connexion TLS réelle d'après la configuration
     */
    public function __construct(
        private ContactMailConfig $config,
        private ContactMailRepository $repo,
        private ContactMailIngest $ingest,
        ?callable $clientFactory = null,
        private ?string $lockFile = null,
    ) {
        $this->clientFactory = $clientFactory;
    }

    /**
     * @return array{available: bool, ok: bool, busy: bool, imported: int, skipped: int, errors: int, reason: string}
     */
    public function run(int $maxMessages = 100, int $budgetSeconds = 40): array
    {
        $result = ['available' => false, 'ok' => true, 'busy' => false, 'imported' => 0, 'skipped' => 0, 'errors' => 0, 'reason' => ''];
        if (!$this->config->isConfigured()) {
            $result['reason'] = 'boîte de contact non configurée (CONTACT_MAIL_PASSWORD absent)';

            return $result;
        }
        if (!$this->repo->ready()) {
            $result['reason'] = 'tables de la boîte de contact absentes (étape de base « contact_mailbox »)';

            return $result;
        }
        $result['available'] = true;

        $lock = $this->acquireLock();
        if ($lock === false) {
            $result['busy'] = true;
            $result['reason'] = 'une synchronisation est déjà en cours';

            return $result;
        }

        $state = $this->repo->syncState(self::FOLDER);
        $uidValidity = $state['uidvalidity'];
        $lastUid = $state['last_uid'];
        $client = null;
        try {
            $client = $this->clientFactory !== null ? ($this->clientFactory)() : ImapClient::connect($this->config);
            $client->login($this->config->user(), $this->config->password());
            $info = $client->select(self::FOLDER);
            if ($uidValidity !== 0 && $info['uidvalidity'] !== $uidValidity) {
                // Boîte reconstruite côté serveur : les UID repartent de zéro, les doublons sont écartés par Message-ID.
                $lastUid = 0;
            }
            $uidValidity = $info['uidvalidity'];

            $uids = array_slice($client->uidsFrom($lastUid + 1), 0, max(1, $maxMessages));
            $summaries = $uids !== [] ? $client->summaries($uids) : [];
            $deadline = time() + max(5, $budgetSeconds);
            foreach ($uids as $uid) {
                if (time() > $deadline) {
                    break;
                }
                $this->importOne($client, $uid, $summaries[$uid] ?? ['size' => 0, 'flags' => []], $uidValidity, $result);
                $lastUid = $uid;
                // Position retenue après chaque message : une coupure ne fait pas relire ce qui est déjà rangé.
                $this->repo->saveSyncState(self::FOLDER, $uidValidity, $lastUid, true, '', 0);
            }
            $this->repo->saveSyncState(self::FOLDER, $uidValidity, $lastUid, true, '', $result['imported']);
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $safe = $e instanceof ImapException ? $e->getMessage() : 'Erreur interne pendant la synchronisation.';
            $result['reason'] = $safe;
            // Jamais le message d'une exception de base : il peut citer des valeurs du courrier.
            error_log('[contact_mail] synchronisation : ' . ($e instanceof ImapException ? $safe : get_class($e)));
            try {
                $this->repo->saveSyncState(self::FOLDER, $uidValidity, $lastUid, false, $safe, $result['imported']);
            } catch (\Throwable) {
                // État illisible : la prochaine passe réessaie.
            }
        } finally {
            if ($client !== null) {
                $client->logout();
            }
            $this->releaseLock($lock);
        }

        return $result;
    }

    /**
     * @param array{size: int, flags: list<string>} $summary
     * @param array{available: bool, ok: bool, busy: bool, imported: int, skipped: int, errors: int, reason: string} $result
     */
    private function importOne(ImapClient $client, int $uid, array $summary, int $uidValidity, array &$result): void
    {
        $fetched = $client->fetchMessage($uid, $summary['size']);
        if ($fetched === null) {
            $result['skipped']++;

            return;
        }
        try {
            $parsed = MimeParser::parse($fetched['raw']);
            if ($fetched['truncated']) {
                $parsed = new ParsedMessage(
                    $parsed->messageId, $parsed->inReplyTo, $parsed->references, $parsed->fromName, $parsed->fromEmail,
                    $parsed->replyToEmail, $parsed->to, $parsed->cc, $parsed->subject, $parsed->sentAt,
                    '(Message trop volumineux : son contenu n’a pas été importé. Ouvrez-le dans la messagerie du serveur.)',
                    '', [], $parsed->autoReply
                );
            }
            $read = in_array('\\Seen', $fetched['flags'], true) || in_array('\\Seen', $summary['flags'], true);
            $id = $this->ingest->ingest($parsed, ['uidvalidity' => $uidValidity, 'uid' => $uid, 'read' => $read]);
            if ($id === null) {
                $result['skipped']++;
            } else {
                $result['imported']++;
            }
        } catch (\Throwable $e) {
            $result['errors']++;
            error_log('[contact_mail] message UID ' . $uid . ' non lisible : ' . get_class($e));
            // Pièce illisible : un message de remplacement garde la trace sans bloquer la suite. Si la base est
            // injoignable, l'insertion échoue aussi et la synchronisation s'arrête sans avancer.
            $this->ingest->ingest(new ParsedMessage(
                '', '', [], '', '', '', [], [], '(Message illisible)', gmdate('Y-m-d H:i:s'),
                'Ce message n’a pas pu être lu. Ouvrez-le dans la messagerie du serveur.', '', []
            ), ['uidvalidity' => $uidValidity, 'uid' => $uid, 'read' => false]);
        }
    }

    /** @return resource|false */
    private function acquireLock()
    {
        $path = $this->lockFile ?? \base_path('storage/locks/contact-mail-sync.lock');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $h = @fopen($path, 'c');
        if ($h === false) {
            // Pas de verrou possible : on continue, l'unicité (UID, Message-ID) protège des doublons.
            return fopen('php://memory', 'r+');
        }
        if (!flock($h, LOCK_EX | LOCK_NB)) {
            fclose($h);

            return false;
        }

        return $h;
    }

    /** @param resource $lock */
    private function releaseLock($lock): void
    {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
