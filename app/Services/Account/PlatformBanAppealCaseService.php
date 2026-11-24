<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Container;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformBanAppealRepository as Appeals;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Email\OpsMailDigestService;

/**
 * Traitement d'un dossier de contestation : échanges, statuts, décision. Toute action est journalisée avec la
 * référence du dossier. La personne est prévenue par e-mail sans le contenu des messages ; la modération voit un
 * marqueur « non lu » (carte « À traiter », liste des dossiers) et, par le récapitulatif d'exploitation existant,
 * un e-mail groupé. Aucune notification Discord : le projet n'a pas de canal Discord pour les contestations.
 */
final class PlatformBanAppealCaseService
{
    public const MIN_LENGTH = 2;
    public const MAX_LENGTH = 4000;

    public function __construct(
        private ?Appeals $appeals = null,
        private ?PlatformAccountBanRepository $bans = null,
        private ?PlatformAccountBanService $banService = null,
        private ?PlatformBanAppealMailer $mailer = null,
        private ?AuditService $audit = null,
    ) {
        $this->appeals ??= new Appeals();
        $this->bans ??= new PlatformAccountBanRepository();
    }

    private function mailer(): PlatformBanAppealMailer
    {
        return $this->mailer ??= new PlatformBanAppealMailer();
    }

    private function audit(): AuditService
    {
        return $this->audit ??= new AuditService();
    }

    /** @return array{ok: bool, error?: string} */
    private static function bodyError(string $body): array
    {
        $length = mb_strlen(trim($body));
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return ['ok' => false, 'error' => 'Le message doit contenir entre ' . self::MIN_LENGTH . ' et ' . self::MAX_LENGTH . ' caractères.'];
        }

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null dossier ouvert ou null */
    private function openCase(int $banId): ?array
    {
        $case = $this->appeals->find($banId);

        return Appeals::isOpen($case) ? $case : null;
    }

    private function log(string $action, array $case, ?int $actor, array $extra = []): void
    {
        try {
            $this->audit()->log(
                $action,
                null,
                $actor !== null && $actor > 0 ? $actor : null,
                'platform_account_ban',
                (int) $case['ban_id'],
                null,
                json_encode(['reference' => (string) ($case['reference'] ?? '')] + $extra, JSON_UNESCAPED_UNICODE) ?: null,
            );
        } catch (\Throwable $e) {
            error_log('[ban_appeal.audit] ' . $e->getMessage());
        }
    }

    private function tell(int $banId, string $kind, ?string $reference): void
    {
        $ban = $this->bans->findById($banId);
        if ($ban !== null) {
            $this->mailer()->notify($ban, $kind, $reference);
        }
    }

    private function alertModeration(): void
    {
        try {
            Container::get(OpsMailDigestService::class)->enqueueBanAppeal();
        } catch (\Throwable) {
            error_log('[ban_appeal] Impossible d’ajouter la contestation au récapitulatif.');
        }
    }

    /** Réponse de la personne : tant que le dossier est ouvert. */
    public function userReply(int $banId, string $body): array
    {
        $check = self::bodyError($body);
        $case = $check['ok'] ? $this->openCase($banId) : null;
        if (!$check['ok']) {
            return $check;
        }
        if ($case === null) {
            return ['ok' => false, 'error' => 'closed'];
        }
        $this->appeals->addMessage($banId, 'user', null, trim($body));
        $this->appeals->update($banId, [
            'status' => $case['assigned_to'] ? Appeals::STATUS_IN_REVIEW : Appeals::STATUS_RECEIVED,
            'unread_staff' => 1,
        ]);
        $this->log(AuditAction::USER_BAN_APPEAL_MESSAGE, $case, null, ['from' => 'user']);
        $this->alertModeration();
        $this->tell($banId, PlatformBanAppealMailer::RECEIVED, (string) $case['reference']);

        return ['ok' => true];
    }

    /** Réponse de la modération (visible par la personne). */
    public function staffReply(int $banId, int $actor, string $body, bool $askForDetails = false): array
    {
        $check = self::bodyError($body);
        if (!$check['ok']) {
            return $check;
        }
        $case = $this->openCase($banId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'Ce dossier est clos.'];
        }
        $this->appeals->addMessage($banId, 'staff', $actor, trim($body));
        $this->appeals->update($banId, [
            'status' => $askForDetails ? Appeals::STATUS_INFO_REQUESTED : Appeals::STATUS_IN_REVIEW,
            'assigned_to' => $case['assigned_to'] ?: $actor,
            'unread_staff' => 0,
        ]);
        $this->log(AuditAction::USER_BAN_APPEAL_MESSAGE, $case, $actor, ['from' => 'staff', 'info_requested' => $askForDetails]);
        $this->tell($banId, $askForDetails ? PlatformBanAppealMailer::INFO : PlatformBanAppealMailer::REPLY, (string) $case['reference']);

        return ['ok' => true];
    }

    /** Note interne : jamais montrée à la personne, aucun e-mail. */
    public function note(int $banId, int $actor, string $body): array
    {
        $check = self::bodyError($body);
        if (!$check['ok']) {
            return $check;
        }
        $case = $this->appeals->find($banId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'Dossier introuvable.'];
        }
        $this->appeals->addMessage($banId, 'staff', $actor, trim($body), true);
        $this->log(AuditAction::USER_BAN_APPEAL_NOTE, $case, $actor);

        return ['ok' => true];
    }

    public function assign(int $banId, int $actor, ?int $assignee): array
    {
        $case = $this->openCase($banId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'Ce dossier est clos.'];
        }
        $this->appeals->update($banId, [
            'assigned_to' => $assignee,
            'status' => $assignee !== null && $case['status'] === Appeals::STATUS_RECEIVED ? Appeals::STATUS_IN_REVIEW : $case['status'],
            'unread_staff' => 0,
        ]);
        $this->log(AuditAction::USER_BAN_APPEAL_ASSIGNED, $case, $actor, ['assigned_to' => $assignee]);

        return ['ok' => true];
    }

    public function markRead(int $banId): void
    {
        $case = $this->appeals->find($banId);
        if ($case !== null && (int) ($case['unread_staff'] ?? 0) === 1) {
            $this->appeals->update($banId, ['unread_staff' => 0, 'updated_at' => $case['updated_at'] ?: gmdate('Y-m-d H:i:s')]);
        }
    }

    /**
     * Décision finale : accepter lève la sanction, maintenir la garde, clore ferme sans trancher.
     *
     * @param 'lift'|'uphold'|'closed' $decision
     */
    public function decide(int $banId, int $actor, string $decision, string $reply): array
    {
        $check = self::bodyError($reply);
        if (!$check['ok'] || !in_array($decision, ['lift', 'uphold', 'closed'], true)) {
            return $check['ok'] ? ['ok' => false, 'error' => 'Décision inconnue.'] : $check;
        }
        $case = $this->openCase($banId);
        if ($case === null) {
            return ['ok' => false, 'error' => 'Ce dossier est clos ou introuvable.'];
        }
        $status = match ($decision) {
            'lift' => Appeals::STATUS_ACCEPTED,
            'uphold' => Appeals::STATUS_UPHELD,
            default => Appeals::STATUS_CLOSED,
        };
        $ban = $this->bans->findById($banId);
        if ($decision === 'lift' && $ban !== null) {
            $this->banService ??= new PlatformAccountBanService();
            $this->banService->lift((string) $ban['email_norm'], $actor, 'Contestation acceptée (' . $case['reference'] . ') : ' . trim($reply), $banId);
        }
        $this->appeals->addMessage($banId, 'staff', $actor, trim($reply));
        $this->appeals->update($banId, [
            'status' => $status,
            'decision' => $decision,
            'public_reply' => trim($reply),
            'reviewed_at' => gmdate('Y-m-d H:i:s'),
            'reviewed_by' => $actor,
            'assigned_to' => $case['assigned_to'] ?: $actor,
            'unread_staff' => 0,
        ]);
        $this->log(AuditAction::USER_BAN_APPEAL_REVIEWED, $case, $actor, ['ban_id' => $banId, 'decision' => $decision]);
        $this->tell($banId, match ($decision) {
            'lift' => PlatformBanAppealMailer::LIFTED,
            'uphold' => PlatformBanAppealMailer::UPHELD,
            default => PlatformBanAppealMailer::CLOSED,
        }, (string) $case['reference']);

        return ['ok' => true];
    }
}
