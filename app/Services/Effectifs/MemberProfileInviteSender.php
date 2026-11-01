<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserDiscordLinkRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Integrations\DiscordWebhookDirectory;
use App\Services\Integrations\DiscordWebhookService;
use App\Services\Security\FileRateLimiter;
use App\Support\DiscordWebhookCatalog;
use App\Support\ProfileInviteDiscordMessage;
use App\Support\RosterOnlyMember;

/**
 * Envoi d'un lien « compléter son profil », toujours à la demande du gestionnaire (jamais automatique) :
 * - Discord : message posté côté serveur sur un salon de la communauté (le lien du webhook ne quitte pas le serveur) ;
 * - e-mail : seulement si le gestionnaire le choisit (quota d'envoi limité).
 * Limites : 30 envois Discord par heure et par communauté, 3 envois par lien et par 10 minutes, 1 e-mail par lien
 * et par 10 minutes.
 */
final class MemberProfileInviteSender
{
    public function __construct(
        private MemberProfileInviteService $invites,
        private ?UserRepository $users = null,
        private ?TenantRepository $tenants = null,
        private ?DiscordWebhookDirectory $directory = null,
        private ?DiscordWebhookService $discord = null,
        private ?FileRateLimiter $limiter = null,
    ) {
        $this->users ??= \App\Core\Container::get(UserRepository::class);
        $this->tenants ??= new TenantRepository();
        $this->directory ??= new DiscordWebhookDirectory($this->tenants);
        $this->discord ??= new DiscordWebhookService();
        $this->limiter ??= new FileRateLimiter();
    }

    /**
     * Salons proposés pour l'envoi, le salon prévu pour cet usage en premier.
     *
     * @return list<array{id:string, label:string, preferred:bool}>
     */
    public function destinations(int $tenantId): array
    {
        $state = (new DiscordEventRelayService($this->tenants, $this->discord, $this->directory))->state($tenantId);
        $row = $state['events'][DiscordWebhookCatalog::KEY_MEMBER_INVITES] ?? ['mode' => 'default', 'webhook' => ''];
        $preferred = $row['mode'] === DiscordWebhookCatalog::MODE_CUSTOM && $row['webhook'] !== '' ? $row['webhook'] : DiscordWebhookDirectory::ORGANISATION;
        $out = [];
        $org = $this->directory->organisation($tenantId);
        if ($org['configured']) {
            $out[] = ['id' => DiscordWebhookDirectory::ORGANISATION, 'label' => 'Salon de l’organisation' . ($org['channel'] !== '' ? ' (' . $org['channel'] . ')' : ''), 'preferred' => false];
        }
        foreach ($this->directory->list($tenantId) as $w) {
            $out[] = ['id' => $w['id'], 'label' => $w['name'] . ($w['channel'] !== '' ? ' (' . $w['channel'] . ')' : ''), 'preferred' => false];
        }
        $found = false;
        foreach ($out as $i => $d) {
            if ($d['id'] === $preferred) {
                $out[$i]['preferred'] = true;
                $found = true;
            }
        }
        if (!$found && $out !== []) {
            $out[0]['preferred'] = true;
        }
        usort($out, static fn (array $a, array $b): int => (int) $b['preferred'] <=> (int) $a['preferred']);

        return $out;
    }

    /**
     * @param array<string, mixed> $invite
     * @return array{ok:bool, message:string}
     */
    public function sendDiscord(int $tenantId, array $invite, ?int $actorId, string $destinationId = ''): array
    {
        $link = $this->invites->linkFor($invite);
        if ($link === null) {
            return ['ok' => false, 'message' => 'Ce lien n’est plus valable. Régénérez-le avant de l’envoyer.'];
        }
        $destinationId = $destinationId !== '' ? $destinationId : $this->preferredDestination($tenantId);
        $url = $destinationId !== '' ? $this->directory->urlFor($tenantId, $destinationId) : null;
        if ($url === null) {
            return ['ok' => false, 'message' => 'Aucun salon Discord n’est relié. Ajoutez le salon de l’organisation dans Intégrations › Discord.'];
        }
        if ($this->limiter->tooManyAttempts('profile_invite_discord|t' . $tenantId, 30, 3600)
            || $this->limiter->tooManyAttempts('profile_invite_discord|i' . (int) $invite['id'], 3, 600)) {
            return ['ok' => false, 'message' => 'Trop d’envois en peu de temps. Patientez quelques minutes avant de renvoyer ce lien.'];
        }
        $payload = ProfileInviteDiscordMessage::payload($this->context($tenantId, $invite, $link));
        try {
            $result = $this->discord->sendMessage($url, $payload);
        } catch (\Throwable) {
            $result = ['ok' => false, 'error' => 'Discord injoignable pour le moment.'];
        }
        if (empty($result['ok'])) {
            return ['ok' => false, 'message' => (string) ($result['error'] ?? 'Discord a refusé le message.')];
        }
        $this->invites->recordSent($invite, 'discord', $actorId);

        return ['ok' => true, 'message' => 'Lien publié sur Discord.'];
    }

    /**
     * E-mail explicite (case « Envoyer aussi par e-mail » ou bouton dédié).
     *
     * @param array<string, mixed> $invite
     * @return array{ok:bool, message:string}
     */
    public function sendEmail(int $tenantId, array $invite, ?int $actorId, ?EmailService $mailer = null): array
    {
        $link = $this->invites->linkFor($invite);
        if ($link === null) {
            return ['ok' => false, 'message' => 'Ce lien n’est plus valable. Régénérez-le avant de l’envoyer.'];
        }
        $user = $this->users->findById((int) $invite['user_id'], $tenantId) ?? [];
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || RosterOnlyMember::isRosterEmail($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Aucune adresse e-mail connue pour ce membre : copiez le lien ou envoyez-le sur Discord.'];
        }
        if ($this->limiter->tooManyAttempts('profile_invite_email|i' . (int) $invite['id'], 1, 600)) {
            return ['ok' => false, 'message' => 'Un e-mail vient déjà de partir pour ce lien. Patientez dix minutes avant de le renvoyer.'];
        }
        $hoursLeft = max(1, (int) ceil(((strtotime((string) $invite['expires_at']) ?: time()) - $this->invites->now()->getTimestamp()) / 3600));
        $tenant = $this->tenants->findById($tenantId) ?? [];
        $mailer ??= \App\Core\Container::get(EmailService::class);
        $sent = $mailer->sendTenantUserSetupInvite($email, $link, $hoursLeft, (string) ($tenant['name'] ?? 'Communauté'), $tenantId);
        if (!$sent) {
            return ['ok' => false, 'message' => 'L’e-mail n’a pas pu partir. Copiez le lien ou envoyez-le sur Discord.'];
        }
        $this->invites->recordSent($invite, 'email', $actorId);

        return ['ok' => true, 'message' => 'Lien envoyé par e-mail à ' . $email . '.'];
    }

    private function preferredDestination(int $tenantId): string
    {
        foreach ($this->destinations($tenantId) as $d) {
            if ($d['preferred']) {
                return $d['id'];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $invite
     * @return array{template:string, callsign:string, name:string, link:string, expires_unix:int, expires_label:string, community:string, unit:string, grade:string, discord_user_id:?string, discord_handle:string}
     */
    public function context(int $tenantId, array $invite, string $link): array
    {
        $userId = (int) $invite['user_id'];
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $tenant = $this->tenants->findById($tenantId) ?? [];
        $grade = '';
        $unit = '';
        $handle = '';
        $linkedId = null;
        try {
            $g = (int) ($user['grade_id'] ?? 0) > 0 ? \App\Core\Container::get(GradeRepository::class)->findById((int) $user['grade_id'], $tenantId) : null;
            $grade = trim((string) ($g['label_long'] ?? $g['name'] ?? ''));
        } catch (\Throwable) {
        }
        try {
            $profile = \App\Core\Container::get(PersonnelProfileRepository::class)->getByUserId($userId) ?? [];
            $unitId = (int) ($profile['primary_unit_id'] ?? 0);
            if ($unitId > 0) {
                $u = \App\Core\Container::get(UnitRepository::class)->findById($unitId, $tenantId);
                $code = (new UnitCodeService())->forUnit($tenantId, $unitId);
                $unit = trim((string) ($u['name'] ?? '')) . (is_array($code) && ($code['code'] ?? '') !== '' ? ' · ' . $code['code'] : '');
            }
        } catch (\Throwable) {
        }
        try {
            $handle = trim((string) ((new UserProfileRepository())->getByUserId($userId)['discord_handle'] ?? ''));
        } catch (\Throwable) {
        }
        try {
            $links = new UserDiscordLinkRepository();
            $linkedId = $links->schemaReady() ? (string) ($links->find($userId)['discord_user_id'] ?? '') : null;
        } catch (\Throwable) {
        }
        $expires = strtotime((string) $invite['expires_at']) ?: time();

        return [
            'template' => $this->directory->inviteTemplate($tenantId),
            'callsign' => trim((string) ($user['callsign'] ?? '')),
            'name' => trim((string) ($user['display_name'] ?? '')),
            'link' => $link,
            'expires_unix' => $expires,
            'expires_label' => date('d/m/Y à H:i', $expires),
            'community' => trim((string) ($tenant['name'] ?? '')),
            'unit' => $unit,
            'grade' => $grade,
            'discord_user_id' => ProfileInviteDiscordMessage::discordUserId($linkedId, $handle),
            'discord_handle' => $handle,
        ];
    }
}
