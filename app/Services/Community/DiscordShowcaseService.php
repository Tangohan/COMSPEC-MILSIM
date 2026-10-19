<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\PlatformSettingsRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Support\DiscordChannelInput;
use App\Support\HtmlTranslator;

/**
 * Vitrine Discord des communautés : une communauté (gestion) demande à être annoncée sur le serveur Discord
 * d'Athena, l'administrateur de la plateforme valide, Athena publie sa carte par webhook dans le salon dédié.
 *
 * - Un salon forum : un post par communauté, une mise à jour modifie le post existant (pas de nouveau post).
 * - Modération : rien ne part sans validation de l'administrateur de la plateforme ; blocage possible.
 * - Fréquence : une mise à jour au plus tous les N jours (7 par défaut) ; 24 h d'attente après un refus.
 * - Mode simulation par défaut : la validation enregistre la carte sans rien poster sur Discord.
 * - Pas de mention (@everyone, @here, rôles) ni de lien dans le texte libre : seuls la page publique sur Athena
 *   et l'invitation Discord de l'unité sont publiées comme liens.
 *
 * Réglages de la plateforme (platform_settings, jamais le .env) : lien webhook du salon, mode, salon forum,
 * délai entre deux mises à jour, identifiants des tags du forum. Le lien webhook n'est jamais réaffiché.
 */
final class DiscordShowcaseService
{
    public const WEBHOOK_KEY = 'discord.showcase_webhook';
    public const CONFIG_KEY = 'discord.showcase_config';
    public const ENTRIES_KEY = 'discord.showcase_entries';

    public const MODE_SIMULATION = 'simulation';
    public const MODE_LIVE = 'live';

    public const STATUS_NONE = '';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const DEFAULT_COOLDOWN_DAYS = 7;
    public const MIN_COOLDOWN_DAYS = 1;
    public const MAX_COOLDOWN_DAYS = 60;
    public const REJECT_WAIT_HOURS = 24;
    public const PITCH_MAX = 400;
    public const REASON_MAX = 300;

    /** Tags du forum posés automatiquement (identifiants numériques donnés par le script de création du salon). */
    public const TAGS = ['athena', 'recruiting', 'fr', 'en'];

    public const USERNAME = 'Athena Communities';
    public const DEFAULT_COLOR = 0x3B82F6;

    /**
     * @param (\Closure(string): string)|null $read lecture platform_settings
     * @param (\Closure(array<string, string>): void)|null $write écriture platform_settings
     * @param (\Closure(string, string, ?array<string, mixed>): array{status:int, body:string})|null $http appel Discord (méthode, URL, JSON)
     * @param (\Closure(): \DateTimeImmutable)|null $clock heure courante (tests)
     */
    /** @var list<array<string, mixed>>|null annuaire lu une fois par requête */
    private ?array $registryRows = null;

    public function __construct(
        private ?\Closure $read = null,
        private ?\Closure $write = null,
        private ?\Closure $http = null,
        private ?\Closure $clock = null,
    ) {
    }

    // ------------------------------------------------------------------ Réglages de la plateforme

    /**
     * @return array{mode:string, forum:bool, cooldown_days:int, tags:array<string, string>}
     */
    public function config(): array
    {
        $raw = json_decode($this->get(self::CONFIG_KEY), true);

        return self::normalizeConfig(is_array($raw) ? $raw : []);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{mode:string, forum:bool, cooldown_days:int, tags:array<string, string>}
     */
    public static function normalizeConfig(array $raw): array
    {
        $tags = [];
        $rawTags = is_array($raw['tags'] ?? null) ? $raw['tags'] : [];
        foreach (self::TAGS as $tag) {
            $id = trim((string) ($rawTags[$tag] ?? ''));
            $tags[$tag] = preg_match('/^\d{15,22}$/', $id) === 1 ? $id : '';
        }

        return [
            'mode' => ($raw['mode'] ?? '') === self::MODE_LIVE ? self::MODE_LIVE : self::MODE_SIMULATION,
            'forum' => !array_key_exists('forum', $raw) || !empty($raw['forum']),
            'cooldown_days' => max(self::MIN_COOLDOWN_DAYS, min(self::MAX_COOLDOWN_DAYS, (int) ($raw['cooldown_days'] ?? self::DEFAULT_COOLDOWN_DAYS))),
            'tags' => $tags,
        ];
    }

    /** Lien webhook enregistré ('' si aucun). Ne jamais l'afficher : voir maskWebhook(). */
    public function webhook(): string
    {
        return trim($this->get(self::WEBHOOK_KEY));
    }

    /** Forme affichable du lien : numéro du webhook tronqué, jeton masqué. */
    public static function maskWebhook(string $url): string
    {
        if (preg_match('#/webhooks/(\d{4})\d*/#', $url, $m) === 1) {
            return 'discord.com/api/webhooks/' . $m[1] . '…/••••';
        }

        return $url !== '' ? 'lien enregistré' : '';
    }

    /**
     * Enregistre les réglages. Lien webhook : vide = inchangé, sauf si $clearWebhook.
     *
     * @param array<string, mixed> $input mode, forum, cooldown_days, tags[], webhook, clear_webhook
     * @return array{ok:bool, message:string}
     */
    public function saveConfig(array $input): array
    {
        $pairs = [];
        $hook = trim((string) ($input['webhook'] ?? ''));
        if ($hook !== '') {
            $problem = DiscordChannelInput::problem($hook, '« Salon des communautés »');
            if ($problem !== null) {
                return ['ok' => false, 'message' => $problem];
            }
            $pairs[self::WEBHOOK_KEY] = DiscordChannelInput::webhookUrl($hook);
        } elseif (!empty($input['clear_webhook'])) {
            $pairs[self::WEBHOOK_KEY] = '';
        }
        $config = self::normalizeConfig($input);
        $pairs[self::CONFIG_KEY] = (string) json_encode($config, JSON_UNESCAPED_SLASHES);
        if ($config['mode'] === self::MODE_LIVE && ($pairs[self::WEBHOOK_KEY] ?? $this->webhook()) === '') {
            return ['ok' => false, 'message' => 'Collez d’abord le lien webhook du salon pour passer en publication réelle.'];
        }
        try {
            ($this->writer())($pairs);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Réglages non enregistrés (table platform_settings absente ?).'];
        }

        return ['ok' => true, 'message' => $config['mode'] === self::MODE_LIVE
            ? 'Réglages enregistrés : les validations publient maintenant sur Discord.'
            : 'Réglages enregistrés : mode simulation, rien n’est posté sur Discord.'];
    }

    /**
     * Vérifie le lien sans rien poster (lecture du webhook : nom et salon).
     *
     * @return array{ok:bool, message:string}
     */
    public function checkWebhook(): array
    {
        $url = $this->webhook();
        if ($url === '') {
            return ['ok' => false, 'message' => 'Aucun lien webhook enregistré.'];
        }
        $res = ($this->transport())('GET', self::baseWebhook($url), null);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return ['ok' => false, 'message' => $res['status'] === 0
                ? 'Discord injoignable pour le moment.'
                : 'Discord ne reconnaît pas ce lien (code ' . $res['status'] . ') : recréez le webhook du salon.'];
        }
        $data = json_decode($res['body'], true);
        $name = is_array($data) ? trim((string) ($data['name'] ?? '')) : '';
        $channel = is_array($data) ? trim((string) ($data['channel_id'] ?? '')) : '';

        return ['ok' => true, 'message' => 'Lien valide' . ($name !== '' ? ' : webhook « ' . $name . ' »' : '')
            . ($channel !== '' ? ', salon ' . $channel : '') . '. Rien n’a été posté.'];
    }

    // ------------------------------------------------------------------ Demandes des communautés

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalizeEntry(array $raw): array
    {
        $status = (string) ($raw['status'] ?? '');
        $known = [self::STATUS_PENDING, self::STATUS_PUBLISHED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];
        $digits = static fn (mixed $v): string => preg_match('/^\d{15,22}$/', (string) $v) === 1 ? (string) $v : '';

        return [
            'tenant_id' => max(0, (int) ($raw['tenant_id'] ?? 0)),
            'status' => in_array($status, $known, true) ? $status : self::STATUS_NONE,
            'pitch' => self::sanitizePitch((string) ($raw['pitch'] ?? '')),
            'requested_at' => (string) ($raw['requested_at'] ?? ''),
            'requested_by' => max(0, (int) ($raw['requested_by'] ?? 0)),
            'decided_at' => (string) ($raw['decided_at'] ?? ''),
            'decided_by' => max(0, (int) ($raw['decided_by'] ?? 0)),
            'reason' => mb_substr(trim((string) ($raw['reason'] ?? '')), 0, self::REASON_MAX),
            'published_at' => (string) ($raw['published_at'] ?? ''),
            'simulated' => !empty($raw['simulated']),
            'thread_id' => $digits($raw['thread_id'] ?? ''),
            'message_id' => $digits($raw['message_id'] ?? ''),
            'blocked' => !empty($raw['blocked']),
            'block_reason' => mb_substr(trim((string) ($raw['block_reason'] ?? '')), 0, self::REASON_MAX),
            'blocked_at' => (string) ($raw['blocked_at'] ?? ''),
        ];
    }

    /** @return array<int, array<string, mixed>> par tenant_id */
    public function entries(): array
    {
        $data = json_decode($this->get(self::ENTRIES_KEY), true);
        $out = [];
        foreach (is_array($data) ? $data : [] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $entry = self::normalizeEntry($raw);
            if ($entry['tenant_id'] >= 2) {
                $out[$entry['tenant_id']] = $entry;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function entry(int $tenantId): array
    {
        return $this->entries()[$tenantId] ?? self::normalizeEntry(['tenant_id' => $tenantId]);
    }

    /**
     * Ce que la communauté peut faire maintenant.
     *
     * @param array<string, mixed> $entry
     * @return array{can:bool, reason:string, next:?string, update:bool}
     */
    public static function requestState(array $entry, int $cooldownDays, \DateTimeImmutable $now): array
    {
        $update = !empty($entry['published_at']) && ($entry['status'] ?? '') === self::STATUS_PUBLISHED;
        if (!empty($entry['blocked'])) {
            return ['can' => false, 'reason' => 'blocked', 'next' => null, 'update' => $update];
        }
        $status = (string) ($entry['status'] ?? '');
        if ($status === self::STATUS_PENDING) {
            return ['can' => false, 'reason' => 'pending', 'next' => null, 'update' => $update];
        }
        $wait = null;
        if ($status === self::STATUS_PUBLISHED && ($entry['published_at'] ?? '') !== '') {
            $wait = self::parseDate((string) $entry['published_at'])?->modify('+' . max(1, $cooldownDays) . ' days');
        } elseif ($status === self::STATUS_REJECTED && ($entry['decided_at'] ?? '') !== '') {
            $wait = self::parseDate((string) $entry['decided_at'])?->modify('+' . self::REJECT_WAIT_HOURS . ' hours');
        }
        if ($wait !== null && $wait > $now) {
            return ['can' => false, 'reason' => 'cooldown', 'next' => $wait->format('Y-m-d H:i'), 'update' => $update];
        }

        return ['can' => true, 'reason' => '', 'next' => null, 'update' => $update];
    }

    /**
     * La communauté demande la publication (ou la mise à jour) de sa carte.
     *
     * @return array{ok:bool, message:string}
     */
    public function request(int $tenantId, int $userId, string $pitch, bool $listed): array
    {
        if (!$listed) {
            return ['ok' => false, 'message' => 'Votre communauté doit d’abord apparaître dans l’annuaire public (Page d’accueil publique › Visibilité).'];
        }
        $entry = $this->entry($tenantId);
        $state = self::requestState($entry, $this->config()['cooldown_days'], $this->now());
        if (!$state['can']) {
            return ['ok' => false, 'message' => match ($state['reason']) {
                'blocked' => 'L’administration du site a bloqué les annonces Discord de votre communauté.',
                'pending' => 'Une demande est déjà en attente de validation.',
                default => 'Prochaine demande possible le ' . self::frDate((string) $state['next']) . '.',
            }];
        }
        $entry['status'] = self::STATUS_PENDING;
        $entry['pitch'] = self::sanitizePitch($pitch);
        $entry['requested_at'] = $this->now()->format('Y-m-d H:i:s');
        $entry['requested_by'] = $userId;
        $entry['reason'] = '';
        if (!$this->store($tenantId, $entry)) {
            return ['ok' => false, 'message' => 'Demande non enregistrée, réessayez plus tard.'];
        }

        return ['ok' => true, 'message' => $state['update']
            ? 'Mise à jour demandée : l’administration du site la valide avant de modifier votre post sur Discord.'
            : 'Demande envoyée : l’administration du site la valide avant toute publication sur Discord.'];
    }

    /** @return array{ok:bool, message:string} */
    public function cancel(int $tenantId): array
    {
        $entry = $this->entry($tenantId);
        if ($entry['status'] !== self::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'Aucune demande en attente.'];
        }
        // Retour à l'état précédent : publiée si un post existe déjà, sinon rien.
        $entry['status'] = $entry['published_at'] !== '' ? self::STATUS_PUBLISHED : self::STATUS_NONE;
        $this->store($tenantId, $entry);

        return ['ok' => true, 'message' => 'Demande annulée.'];
    }

    // ------------------------------------------------------------------ Décisions de l'administration du site

    /**
     * Valide et publie (ou modifie le post existant). En simulation, rien ne part sur Discord.
     *
     * @param array<string, mixed> $card carte (self::card)
     * @return array{ok:bool, message:string}
     */
    public function approve(int $tenantId, int $adminId, array $card): array
    {
        $entry = $this->entry($tenantId);
        if ($entry['blocked']) {
            return ['ok' => false, 'message' => 'Communauté bloquée : débloquez-la d’abord.'];
        }
        if ($entry['status'] !== self::STATUS_PENDING && !($entry['status'] === self::STATUS_PUBLISHED && $entry['simulated'])) {
            return ['ok' => false, 'message' => 'Rien à valider pour cette communauté.'];
        }
        $config = $this->config();
        $now = $this->now();
        $payload = self::buildPayload($card, (string) $entry['pitch'], $config, $now);
        $hook = $this->webhook();
        $live = $config['mode'] === self::MODE_LIVE && $hook !== '';

        if ($live) {
            $sent = $this->send($hook, $payload, $entry, $config['forum']);
            if (!$sent['ok']) {
                return ['ok' => false, 'message' => $sent['message']];
            }
            $entry['thread_id'] = $sent['thread_id'];
            $entry['message_id'] = $sent['message_id'];
            $entry['simulated'] = false;
        } else {
            $entry['simulated'] = true;
        }
        $entry['status'] = self::STATUS_PUBLISHED;
        $entry['published_at'] = $now->format('Y-m-d H:i:s');
        $entry['decided_at'] = $now->format('Y-m-d H:i:s');
        $entry['decided_by'] = $adminId;
        $entry['reason'] = '';
        $this->store($tenantId, $entry);

        return ['ok' => true, 'message' => $live
            ? 'Validée et publiée sur Discord.'
            : 'Validée en simulation : rien n’a été posté sur Discord.'];
    }

    /** @return array{ok:bool, message:string} */
    public function reject(int $tenantId, int $adminId, string $reason): array
    {
        $entry = $this->entry($tenantId);
        if ($entry['status'] !== self::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'Aucune demande en attente.'];
        }
        // Un post déjà en ligne reste en ligne : seule la mise à jour est refusée.
        $hadPost = $entry['published_at'] !== '';
        $entry['status'] = $hadPost ? self::STATUS_PUBLISHED : self::STATUS_REJECTED;
        $entry['decided_at'] = $this->now()->format('Y-m-d H:i:s');
        $entry['decided_by'] = $adminId;
        $entry['reason'] = mb_substr(trim($reason), 0, self::REASON_MAX);
        $this->store($tenantId, $entry);

        return ['ok' => true, 'message' => $hadPost ? 'Mise à jour refusée, le post actuel reste en ligne.' : 'Demande refusée.'];
    }

    /** @return array{ok:bool, message:string} */
    public function block(int $tenantId, string $reason): array
    {
        $entry = $this->entry($tenantId);
        $entry['blocked'] = true;
        $entry['block_reason'] = mb_substr(trim($reason), 0, self::REASON_MAX);
        $entry['blocked_at'] = $this->now()->format('Y-m-d H:i:s');
        if ($entry['status'] === self::STATUS_PENDING) {
            $entry['status'] = $entry['published_at'] !== '' ? self::STATUS_PUBLISHED : self::STATUS_NONE;
        }
        $this->store($tenantId, $entry);

        return ['ok' => true, 'message' => 'Communauté bloquée : elle ne peut plus demander d’annonce. Retirez aussi son post s’il est en ligne.'];
    }

    /** @return array{ok:bool, message:string} */
    public function unblock(int $tenantId): array
    {
        $entry = $this->entry($tenantId);
        $entry['blocked'] = false;
        $entry['block_reason'] = '';
        $entry['blocked_at'] = '';
        $this->store($tenantId, $entry);

        return ['ok' => true, 'message' => 'Communauté débloquée.'];
    }

    /**
     * Retire le post (communauté ou administration). Sur Discord : suppression du message du post.
     *
     * @return array{ok:bool, message:string}
     */
    public function remove(int $tenantId): array
    {
        $entry = $this->entry($tenantId);
        if ($entry['published_at'] === '' && $entry['message_id'] === '') {
            return ['ok' => false, 'message' => 'Aucune annonce publiée.'];
        }
        $hook = $this->webhook();
        if ($entry['message_id'] !== '' && $hook !== '' && $this->config()['mode'] === self::MODE_LIVE) {
            $url = self::baseWebhook($hook) . '/messages/' . $entry['message_id'] . self::threadQuery($entry['thread_id']);
            $res = ($this->transport())('DELETE', $url, null);
            if ($res['status'] !== 204 && $res['status'] !== 404 && ($res['status'] < 200 || $res['status'] >= 300)) {
                return ['ok' => false, 'message' => $res['status'] === 0 ? 'Discord injoignable pour le moment.' : 'Discord a refusé le retrait (code ' . $res['status'] . ').'];
            }
        }
        $entry['status'] = self::STATUS_WITHDRAWN;
        $entry['published_at'] = '';
        $entry['message_id'] = '';
        $entry['thread_id'] = '';
        $entry['simulated'] = false;
        $this->store($tenantId, $entry);

        return ['ok' => true, 'message' => 'Annonce retirée de Discord.'];
    }

    // ------------------------------------------------------------------ Carte et message Discord

    /**
     * Carte d'une communauté listée dans l'annuaire (null si elle n'y apparaît pas).
     *
     * @return array<string, mixed>|null
     */
    public function loadCard(int $tenantId): ?array
    {
        $tenants = new TenantRepository();
        $this->registryRows ??= $tenants->listForRegistry();
        $row = null;
        foreach ($this->registryRows as $r) {
            if ((int) ($r['id'] ?? 0) === $tenantId) {
                $row = $r;
                break;
            }
        }
        if ($row === null) {
            return null;
        }
        $branding = [];
        try {
            $branding = (new TenantBrandingRepository())->listCardBrandingByTenant()[$tenantId] ?? [];
        } catch (\Throwable) {
        }
        $settings = $tenants->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];

        return self::card($row, $branding, $community, rtrim((string) url(''), '/'));
    }

    /**
     * Carte publiée : uniquement des données déjà publiques sur la fiche de l'annuaire.
     *
     * @param array<string, mixed> $row ligne de TenantRepository::listForRegistry
     * @param array<string, string> $branding banner_url, logo_url, primary_color, accent_color
     * @param array<string, mixed> $community réglages « community » du tenant
     * @return array<string, mixed>
     */
    public static function card(array $row, array $branding, array $community, string $baseUrl): array
    {
        $slug = trim((string) ($row['slug'] ?? ''));
        $name = trim((string) ($row['name'] ?? $slug));
        if (function_exists('name_mask_text')) {
            $name = name_mask_text($name);
        }
        $color = self::colorInt((string) ($branding['accent_color'] ?? ''))
            ?? self::colorInt((string) ($branding['primary_color'] ?? ''))
            ?? self::DEFAULT_COLOR;
        $logo = self::absoluteUrl((string) ($branding['logo_url'] ?? ''), $baseUrl);
        if ($logo === '') {
            $logo = self::absoluteUrl((string) ($row['logo_url'] ?? ''), $baseUrl);
        }
        $locale = strtolower(trim((string) ($row['registry_locale'] ?? '')));
        $styles = [];
        foreach (is_array($row['registry_style_badge_labels'] ?? null) ? $row['registry_style_badge_labels'] : [] as $label) {
            if (is_string($label) && trim($label) !== '') {
                $styles[] = trim($label);
            }
        }

        return [
            'tenant_id' => (int) ($row['id'] ?? 0),
            'name' => mb_substr($name, 0, 100),
            'slug' => $slug,
            'public_url' => $slug !== '' ? rtrim($baseUrl, '/') . '/c/' . rawurlencode($slug) . '?ref=discord' : '',
            'logo' => $logo,
            'banner' => self::absoluteUrl((string) ($branding['banner_url'] ?? ''), $baseUrl),
            'color' => $color,
            'country' => trim((string) ($row['registry_country_label'] ?? '')),
            'language' => trim((string) ($row['registry_locale_label'] ?? '')),
            'locale' => $locale,
            'game' => trim((string) ($row['game_label'] ?? '')),
            'recruiting' => !empty($row['registry_recruitment_open']),
            'styles' => array_slice($styles, 0, 4),
            'excerpt' => self::sanitizePitch((string) ($row['registry_excerpt'] ?? '')),
            'discord_invite' => self::discordInvite((string) ($community['contact_discord_url'] ?? '')),
        ];
    }

    /**
     * Message du webhook : un embed, aucune mention possible, titre du post forum = nom de la communauté.
     * Libellés en anglais (le serveur Discord est en anglais) ; le texte de la communauté reste dans sa langue.
     *
     * @param array<string, mixed> $card
     * @param array{mode:string, forum:bool, cooldown_days:int, tags:array<string, string>} $config
     * @return array<string, mixed>
     */
    public static function buildPayload(array $card, string $pitch, array $config, \DateTimeImmutable $now): array
    {
        $en = static fn (string $fr): string => $fr !== '' ? HtmlTranslator::translateText($fr) : '';
        $description = self::sanitizePitch($pitch);
        if ($description === '') {
            $description = (string) ($card['excerpt'] ?? '');
        }
        $fields = [];
        $add = static function (string $name, string $value, bool $inline = true) use (&$fields): void {
            $value = trim($value);
            if ($value !== '') {
                $fields[] = ['name' => $name, 'value' => mb_substr($value, 0, 1024), 'inline' => $inline];
            }
        };
        $add('Country', $en((string) ($card['country'] ?? '')));
        $add('Language', $en((string) ($card['language'] ?? '')));
        $add('Game', (string) ($card['game'] ?? ''));
        $add('Recruitment', !empty($card['recruiting']) ? '🟢 Open' : '🔴 Closed');
        $styles = array_map(static fn (string $s): string => $en($s), is_array($card['styles'] ?? null) ? $card['styles'] : []);
        $add('Style', implode(' · ', $styles));
        $links = [];
        if (($card['public_url'] ?? '') !== '') {
            $links[] = '[Public page on Athena](' . $card['public_url'] . ')';
        }
        if (($card['discord_invite'] ?? '') !== '') {
            $links[] = '[Join their Discord](' . $card['discord_invite'] . ')';
        }
        $add('Links', implode(' · ', $links), false);

        $embed = [
            'title' => self::escapeMarkdown((string) ($card['name'] ?? '')),
            'color' => (int) ($card['color'] ?? self::DEFAULT_COLOR),
            'fields' => $fields,
            'footer' => ['text' => 'Athena · Community directory · reviewed by the Athena team'],
            'timestamp' => $now->format('c'),
        ];
        if (($card['public_url'] ?? '') !== '') {
            $embed['url'] = (string) $card['public_url'];
        }
        if ($description !== '') {
            $embed['description'] = $description;
        }
        if (($card['logo'] ?? '') !== '') {
            $embed['thumbnail'] = ['url' => (string) $card['logo']];
        }
        if (($card['banner'] ?? '') !== '') {
            $embed['image'] = ['url' => (string) $card['banner']];
        }

        $payload = [
            'username' => self::USERNAME,
            'embeds' => [$embed],
            'allowed_mentions' => ['parse' => []],
        ];
        if (!empty($config['forum'])) {
            $payload['thread_name'] = mb_substr(trim((string) ($card['name'] ?? '')) ?: 'Community', 0, 100);
            $tags = [];
            $tagIds = is_array($config['tags'] ?? null) ? $config['tags'] : [];
            $wanted = ['athena'];
            if (!empty($card['recruiting'])) {
                $wanted[] = 'recruiting';
            }
            if (in_array($card['locale'] ?? '', ['fr', 'en'], true)) {
                $wanted[] = (string) $card['locale'];
            }
            foreach ($wanted as $tag) {
                if (($tagIds[$tag] ?? '') !== '') {
                    $tags[] = (string) $tagIds[$tag];
                }
            }
            if ($tags !== []) {
                $payload['applied_tags'] = $tags;
            }
        }

        return $payload;
    }

    /**
     * Tags du forum voulus pour cette carte (libellés affichés dans l'aperçu ; posés si leur identifiant est réglé).
     *
     * @param array<string, mixed> $card
     * @return list<string>
     */
    public static function tagLabels(array $card): array
    {
        $out = ['Athena'];
        if (!empty($card['recruiting'])) {
            $out[] = 'Recruiting';
        }
        if (in_array($card['locale'] ?? '', ['fr', 'en'], true)) {
            $out[] = strtoupper((string) $card['locale']);
        }

        return $out;
    }

    /**
     * Texte libre de la communauté : sans mention, sans lien, sans titre markdown, 400 caractères au plus.
     */
    public static function sanitizePitch(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Liens masqués [texte](url) : on garde le texte.
        $text = (string) preg_replace('/\[([^\]]{0,200})\]\([^)]*\)/u', '$1', $text);
        // Mentions Discord <@id>, <@!id>, <@&id>, <#id>, et @everyone / @here.
        $text = (string) preg_replace('/<(?:@[!&]?|#)\d+>/', '', $text);
        $text = (string) preg_replace('/@(everyone|here)\b/iu', '$1', $text);
        $text = str_replace('@', '＠', $text);
        // Liens bruts : aucun lien dans le texte libre (pas d'URL GitHub, d'invitation sauvage…).
        $text = (string) preg_replace('#\b(?:https?://|www\.)\S+#iu', '', $text);
        $text = (string) preg_replace('#\b(?:discord(?:app)?\.(?:gg|com|io|me)|github\.com|dsc\.gg)/\S*#iu', '', $text);
        // Titres et citations markdown en début de ligne.
        $text = (string) preg_replace('/^\s*(?:#{1,6}|>{1,3}|-#)\s*/mu', '', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);
        $text = trim($text);
        if (mb_strlen($text) > self::PITCH_MAX) {
            $text = rtrim(mb_substr($text, 0, self::PITCH_MAX - 1)) . '…';
        }

        return $text;
    }

    /** Invitation Discord de l'unité, seulement sous forme discord.gg/… ou discord.com/invite/…, sinon ''. */
    public static function discordInvite(string $url): string
    {
        $url = trim($url);
        if (preg_match('#^https?://(?:www\.)?(discord\.gg|discord(?:app)?\.com/invite)/([A-Za-z0-9-]{2,32})/?$#i', $url, $m) !== 1) {
            return '';
        }

        return 'https://discord.gg/' . $m[2];
    }

    /** URL absolue https/http, ou chemin du site complété avec l'adresse du site, sinon ''. */
    public static function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://[^\s"<>]+$#i', $url) === 1) {
            return $url;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && preg_match('#^https?://#i', $baseUrl) === 1) {
            $parts = parse_url($baseUrl);
            $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

            return $origin . $url;
        }

        return '';
    }

    // ------------------------------------------------------------------ Envoi Discord

    /**
     * Crée le post (forum : nouveau fil titré au nom de la communauté) ou modifie le message existant.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $entry
     * @return array{ok:bool, message:string, thread_id:string, message_id:string}
     */
    private function send(string $hook, array $payload, array $entry, bool $forum): array
    {
        $base = self::baseWebhook($hook);
        if ($entry['message_id'] !== '') {
            $edit = ['embeds' => $payload['embeds'], 'allowed_mentions' => $payload['allowed_mentions']];
            $res = ($this->transport())('PATCH', $base . '/messages/' . $entry['message_id'] . self::threadQuery($entry['thread_id']), $edit);
            if ($res['status'] >= 200 && $res['status'] < 300) {
                return ['ok' => true, 'message' => '', 'thread_id' => (string) $entry['thread_id'], 'message_id' => (string) $entry['message_id']];
            }
            if ($res['status'] !== 404) {
                return ['ok' => false, 'message' => self::httpError($res['status']), 'thread_id' => '', 'message_id' => ''];
            }
            // Post supprimé sur Discord : on en crée un nouveau.
        }
        if (!$forum) {
            unset($payload['thread_name'], $payload['applied_tags']);
        }
        $res = ($this->transport())('POST', $base . '?wait=true', $payload);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return ['ok' => false, 'message' => self::httpError($res['status']), 'thread_id' => '', 'message_id' => ''];
        }
        $data = json_decode($res['body'], true);
        $messageId = is_array($data) ? (string) ($data['id'] ?? '') : '';
        $channelId = is_array($data) ? (string) ($data['channel_id'] ?? '') : '';

        return [
            'ok' => true,
            'message' => '',
            'thread_id' => $forum && preg_match('/^\d{15,22}$/', $channelId) === 1 ? $channelId : '',
            'message_id' => preg_match('/^\d{15,22}$/', $messageId) === 1 ? $messageId : '',
        ];
    }

    private static function httpError(int $status): string
    {
        return match (true) {
            $status === 0 => 'Discord injoignable pour le moment : rien n’a été publié.',
            $status === 401, $status === 404 => 'Le lien webhook du salon n’est plus valide : recréez-le puis enregistrez-le.',
            $status === 429 => 'Discord limite les envois pour le moment : réessayez dans une minute.',
            default => 'Discord a refusé la publication (code ' . $status . ').',
        };
    }

    /** Lien webhook sans paramètre ni barre finale. */
    private static function baseWebhook(string $hook): string
    {
        $hook = trim($hook);
        $q = strpos($hook, '?');

        return rtrim($q === false ? $hook : substr($hook, 0, $q), '/');
    }

    private static function threadQuery(string $threadId): string
    {
        return $threadId !== '' ? '?thread_id=' . $threadId : '';
    }

    // ------------------------------------------------------------------ Outils

    private static function escapeMarkdown(string $s): string
    {
        return (string) preg_replace('/([*_~`|>\\\\])/', '\\\\$1', trim($s));
    }

    private static function colorInt(string $color): ?int
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m) !== 1) {
            return null;
        }
        $hex = strlen($m[1]) === 3 ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];

        return (int) hexdec($hex);
    }

    private static function parseDate(string $s): ?\DateTimeImmutable
    {
        try {
            return $s !== '' ? new \DateTimeImmutable($s) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** « 2026-10-26 14:05 » → « 26/10/2026 à 14:05 ». */
    public static function frDate(string $s): string
    {
        $d = self::parseDate($s);

        return $d !== null ? $d->format('d/m/Y \à H:i') : $s;
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new \DateTimeImmutable('now');
    }

    /** @param array<string, mixed> $entry */
    private function store(int $tenantId, array $entry): bool
    {
        $entries = $this->entries();
        $entry['tenant_id'] = $tenantId;
        $entries[$tenantId] = self::normalizeEntry($entry);
        try {
            ($this->writer())([self::ENTRIES_KEY => (string) json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function get(string $key): string
    {
        try {
            return ($this->read ??= static fn (string $k): string => (new PlatformSettingsRepository())->get($k, ''))($key);
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return \Closure(array<string, string>): void */
    private function writer(): \Closure
    {
        return $this->write ??= static function (array $pairs): void {
            $repo = new PlatformSettingsRepository();
            if (!$repo->tableExists()) {
                throw new \RuntimeException('platform_settings');
            }
            $repo->setMany($pairs);
        };
    }

    /** @return \Closure(string, string, ?array<string, mixed>): array{status:int, body:string} */
    private function transport(): \Closure
    {
        return $this->http ??= static function (string $method, string $url, ?array $payload): array {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ];
            if ($payload !== null) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
            }
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $status = curl_errno($ch) !== 0 ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ['status' => $status, 'body' => is_string($body) ? $body : ''];
        };
    }
}
