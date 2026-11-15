<?php

declare(strict_types=1);

namespace App\Services\DataCampaign;

use App\Core\Container;
use App\Repositories\DataCampaignRepository;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignCalendar;
use App\Support\DataCampaignProgress;
use App\Support\DataCampaignScope;
use App\Support\DataCampaignSnapshot;

/**
 * Campagne de fiabilisation des données : cycle de vie (brouillon, ouverte, close), parcours du membre, signalements,
 * relances et tâche planifiée.
 *
 * Règles qui ne bougent pas :
 *  - le module ne modifie jamais une fiche : un « à corriger » devient une demande de correction du circuit existant
 *    (PersonnelCorrectionRequestService::submit : avis du chef d'unité, décision des responsables) ou un signalement
 *    que l'encadrement traite ; le membre lit seulement sa propre fiche (identifiant venu de la session) ;
 *  - ce qu'un membre confirme est enregistré tel qu'il l'a vu (instantané et empreinte) ;
 *  - rien ne s'ouvre seul sans que la communauté ait activé l'ouverture automatique, et chaque rappel part une fois.
 *
 * Les effets de bord (journal d'audit, notification, e-mail, demande de correction) passent par des fonctions
 * injectables : les tests les remplacent, la production prend les services existants.
 */
class DataCampaignService
{
    public const NOTIFY_OPEN = 'data_campaign_open';
    public const NOTIFY_REMINDER = 'data_campaign_reminder';
    public const NOTIFY_FLAG_HANDLED = 'data_campaign_flag_handled';
    /** Avis à la gestion : Athena a ouvert une campagne (un seul avis par campagne). */
    public const NOTIFY_AUTO_OPENED = 'data_campaign_auto_opened';
    /** Rapport d'avancement à la gestion à la clôture d'une campagne à sa date limite. */
    public const NOTIFY_AUTO_CLOSED = 'data_campaign_auto_closed';

    /** Une communauté sans membre connecté depuis ce nombre de jours n'est pas concernée par l'ouverture d'Athena. */
    public const ACTIVE_DAYS = 90;

    /** Au-delà de cette part de fiches sans prénom ou sans nom, l'avis à la gestion ajoute « fiches incomplètes ». */
    public const INCOMPLETE_RATIO = 0.5;

    /** Une relance (manuelle) au plus par membre et par période de ce nombre d'heures. */
    public const REMINDER_COOLDOWN_HOURS = 72;

    /** Envois (notifications et e-mails) au plus par passage ou par relance groupée. */
    public const BATCH = 150;

    public const MESSAGE_MIN = 5;
    public const MESSAGE_MAX = 1000;
    public const TITLE_MAX = 120;

    /** @var (\Closure(string, int, int, int, array<string, mixed>): void)|null */
    private ?\Closure $audit;

    /** @var (\Closure(int, int, string, array<string, mixed>): void)|null */
    private ?\Closure $notifier;

    /** @var (\Closure(int, int, array<string, mixed>): bool)|null */
    private ?\Closure $mailer;

    /** @var (\Closure(int, int, array<string, string>, string): array{ok: bool, message: string, request_id?: int})|null */
    private ?\Closure $corrector;

    /** @var (\Closure(): int)|null */
    private ?\Closure $clock;

    /** @var (\Closure(int): list<int>)|null */
    private ?\Closure $managers;

    /** @var array{notified: int, mails: int} envois du passage en cours */
    private array $stats = ['notified' => 0, 'mails' => 0];

    public function __construct(
        private ?DataCampaignRepository $repo = null,
        private ?DataCampaignRecordReader $reader = null,
        ?\Closure $audit = null,
        ?\Closure $notifier = null,
        ?\Closure $mailer = null,
        ?\Closure $corrector = null,
        ?\Closure $clock = null,
        ?\Closure $managers = null,
    ) {
        $this->audit = $audit;
        $this->notifier = $notifier;
        $this->mailer = $mailer;
        $this->corrector = $corrector;
        $this->clock = $clock;
        $this->managers = $managers;
    }

    private function repo(): DataCampaignRepository
    {
        return $this->repo ??= new DataCampaignRepository();
    }

    private function reader(): DataCampaignRecordReader
    {
        return $this->reader ??= new DataCampaignRecordReader();
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }

    private function today(): string
    {
        return date('Y-m-d', $this->now());
    }

    // ------------------------------------------------------------------ gestion : cycle de vie

    /**
     * @param array<string, mixed> $input year, title, blocks, unit_ids, opens_on, due_on, reminder_days
     * @return array{ok: bool, message: string, id?: int}
     */
    public function createCampaign(int $tenantId, int $actorId, array $input, bool $auto = false): array
    {
        if ($tenantId < 1) {
            return ['ok' => false, 'message' => 'Communauté inconnue.'];
        }
        $year = (int) ($input['year'] ?? date('Y', $this->now()));
        if ($year < 2000 || $year > 2100) {
            return ['ok' => false, 'message' => 'Indiquez une année valide.'];
        }
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')));
        $title = $title !== '' ? mb_substr($title, 0, self::TITLE_MAX) : 'Fiabilisation des données ' . $year;
        $opens = self::date($input['opens_on'] ?? null) ?? $this->today();
        $due = self::date($input['due_on'] ?? null);
        if ($due === null) {
            return ['ok' => false, 'message' => 'Indiquez une date limite valide (jour/mois/année).'];
        }
        if ($due < $opens) {
            return ['ok' => false, 'message' => 'La date limite doit suivre la date d’ouverture.'];
        }
        $units = $this->repo()->units($tenantId);
        $unitIds = array_values(array_filter(DataCampaignRepository::ids($input['unit_ids'] ?? []), static fn (int $id): bool => isset($units[$id])));
        $id = $this->repo()->createCampaign($tenantId, [
            'year' => $year,
            'title' => $title,
            'blocks' => DataCampaignBlocks::normalize($input['blocks'] ?? null),
            'unit_ids' => $unitIds,
            'reminder_days' => DataCampaignCalendar::normalizeReminderDays($input['reminder_days'] ?? null),
            'opens_on' => $opens,
            'due_on' => $due,
            'auto' => $auto,
            'forced_on' => $auto ? self::date($input['forced_on'] ?? null) : null,
        ], $actorId);
        if ($id < 1) {
            return ['ok' => false, 'message' => $auto ? 'Athena a déjà ouvert une campagne pour cette année.' : 'La campagne n’a pas pu être créée.'];
        }
        $this->audit('data_campaign.created', $tenantId, $actorId, $id, ['year' => $year, 'auto' => $auto]);

        return ['ok' => true, 'message' => 'Campagne créée en brouillon.', 'id' => $id];
    }

    /**
     * Ouvre la campagne : les membres du périmètre y sont inscrits (leur fiche n'est pas touchée) et reçoivent la notification
     * d'ouverture, par lots (le reste part aux passages suivants de la tâche planifiée, une seule fois chacun).
     *
     * @param array{email?: bool, budget?: int} $options
     * @return array{ok: bool, message: string, enrolled?: int, served?: int}
     */
    public function openCampaign(int $tenantId, int $actorId, int $campaignId, array $options = []): array
    {
        $c = $this->repo()->findCampaign($campaignId, $tenantId);
        if ($c === null) {
            return ['ok' => false, 'message' => 'Campagne introuvable.'];
        }
        if ($c['status'] === DataCampaignRepository::STATUS_OPEN) {
            return ['ok' => false, 'message' => 'Cette campagne est déjà ouverte.'];
        }
        $units = $this->repo()->units($tenantId);
        $enroll = [];
        foreach ($this->repo()->roster($tenantId) as $m) {
            if (DataCampaignScope::memberInCampaign($m['unit_id'], $c['unit_ids'], $units)) {
                $enroll[] = ['user_id' => $m['user_id'], 'unit_id' => $m['unit_id']];
            }
        }
        $enrolled = $this->repo()->addMembers($campaignId, $tenantId, $enroll);
        if (!$this->repo()->setStatus($campaignId, $tenantId, DataCampaignRepository::STATUS_OPEN, $actorId)) {
            return ['ok' => false, 'message' => 'La campagne n’a pas pu être ouverte.'];
        }
        $opened = $this->repo()->findCampaign($campaignId, $tenantId) ?? $c;
        $served = $this->announceOpen($opened, (int) ($options['budget'] ?? self::BATCH), ($options['email'] ?? $this->mailsEnabled($tenantId)) !== false);
        $this->audit('data_campaign.opened', $tenantId, $actorId, $campaignId, ['members' => count($enroll), 'notified' => $served]);

        return ['ok' => true, 'message' => 'Campagne ouverte : ' . count($enroll) . ' membre(s) invité(s) à vérifier leur fiche.', 'enrolled' => $enrolled, 'served' => $served];
    }

    /**
     * Titre et date limite d'une campagne (la gestion peut corriger celle qu'Athena a ouverte ou une campagne manuelle).
     *
     * @return array{ok: bool, message: string}
     */
    public function updateCampaign(int $tenantId, int $actorId, int $campaignId, string $title, string $dueOn): array
    {
        $c = $this->repo()->findCampaign($campaignId, $tenantId);
        if ($c === null) {
            return ['ok' => false, 'message' => 'Campagne introuvable.'];
        }
        $due = self::date($dueOn);
        if ($due === null || $due < substr((string) ($c['opens_on'] ?? ''), 0, 10)) {
            return ['ok' => false, 'message' => 'Indiquez une date limite valide, après l’ouverture.'];
        }
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        $title = $title !== '' ? mb_substr($title, 0, self::TITLE_MAX) : (string) $c['title'];
        if (!$this->repo()->updateCampaign($campaignId, $tenantId, $title, $due)) {
            return ['ok' => false, 'message' => 'Une campagne close ne se modifie plus.'];
        }
        $this->audit('data_campaign.updated', $tenantId, $actorId, $campaignId, ['due_on' => $due]);

        return ['ok' => true, 'message' => 'Campagne modifiée.'];
    }

    /** @return array{ok: bool, message: string} */
    public function closeCampaign(int $tenantId, int $actorId, int $campaignId): array
    {
        $c = $this->repo()->findCampaign($campaignId, $tenantId);
        if ($c === null) {
            return ['ok' => false, 'message' => 'Campagne introuvable.'];
        }
        if ($c['status'] !== DataCampaignRepository::STATUS_OPEN) {
            return ['ok' => false, 'message' => 'Seule une campagne ouverte peut être clôturée.'];
        }
        if (!$this->repo()->setStatus($campaignId, $tenantId, DataCampaignRepository::STATUS_CLOSED, $actorId)) {
            return ['ok' => false, 'message' => 'La campagne n’a pas pu être clôturée.'];
        }
        $this->audit('data_campaign.closed', $tenantId, $actorId, $campaignId, ['auto' => $actorId < 1]);

        return ['ok' => true, 'message' => 'Campagne clôturée. Les réponses et instantanés sont conservés.'];
    }

    /**
     * Réglage « ouvrir automatiquement une campagne chaque année ».
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string}
     */
    public function saveSettings(int $tenantId, int $actorId, array $input): array
    {
        $units = $this->repo()->units($tenantId);
        $settings = [
            'auto_enabled' => (string) ($input['auto_enabled'] ?? '1') !== '0',
            'emails_enabled' => (string) ($input['emails_enabled'] ?? '1') !== '0',
            'open_md' => DataCampaignCalendar::normalizeMd(self::md((string) ($input['open_md'] ?? '')), DataCampaignCalendar::DEFAULT_OPEN_MD),
            'close_md' => DataCampaignCalendar::normalizeMd(self::md((string) ($input['close_md'] ?? '')), DataCampaignCalendar::DEFAULT_CLOSE_MD),
            'blocks' => DataCampaignBlocks::normalize($input['blocks'] ?? null),
            'unit_ids' => array_values(array_filter(DataCampaignRepository::ids($input['unit_ids'] ?? []), static fn (int $id): bool => isset($units[$id]))),
            'reminder_days' => DataCampaignCalendar::normalizeReminderDays($input['reminder_days'] ?? null),
        ];
        $this->repo()->saveSettings($tenantId, $settings, $actorId);
        $this->audit('data_campaign.settings', $tenantId, $actorId, 0, ['auto_enabled' => $settings['auto_enabled'], 'emails_enabled' => $settings['emails_enabled'], 'open_md' => $settings['open_md'], 'close_md' => $settings['close_md']]);

        return ['ok' => true, 'message' => $settings['auto_enabled']
            ? 'Réglage enregistré : Athena ouvrira une campagne chaque année à la date choisie.'
            : 'Réglage enregistré : Athena n’ouvrira pas de campagne seule ; vous pouvez toujours en créer une à la main.'];
    }

    // ------------------------------------------------------------------ membre

    /**
     * Campagne ouverte qui concerne ce membre (la plus récente), avec sa ligne de suivi. Un membre arrivé après
     * l'ouverture y est inscrit à sa première visite s'il est dans le périmètre.
     *
     * @return array{campaign: array<string, mixed>, member: array<string, mixed>}|null
     */
    public function activeFor(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->repo()->schemaReady()) {
            return null;
        }
        foreach (array_reverse($this->repo()->openCampaigns($tenantId)) as $c) {
            $member = $this->repo()->memberRow($c['id'], $userId);
            if ($member === null) {
                $unitId = $this->repo()->primaryUnitId($userId);
                if (!DataCampaignScope::memberInCampaign($unitId, $c['unit_ids'], $this->repo()->units($tenantId))) {
                    continue;
                }
                $this->repo()->addMembers($c['id'], $tenantId, [['user_id' => $userId, 'unit_id' => $unitId]]);
                $member = $this->repo()->memberRow($c['id'], $userId);
            }
            if ($member !== null) {
                return ['campaign' => $c, 'member' => $member];
            }
        }

        return null;
    }

    /**
     * Ce que la page du membre affiche : chaque bloc de la campagne avec sa lecture, sa réponse et ses signalements.
     *
     * @param array<string, mixed> $campaign
     * @param array<string, mixed> $member
     * @return array{blocks: array<string, array<string, mixed>>, answered: int, total: int, complete: bool, flags: list<array<string, mixed>>, confirmed_at: string, changed: list<string>}
     */
    public function memberView(int $tenantId, int $userId, array $campaign, array $member): array
    {
        $keys = DataCampaignBlocks::normalize($campaign['blocks'] ?? null);
        $read = $this->reader()->read($tenantId, $userId, $keys);
        $answers = is_array($member['answers'] ?? null) ? $member['answers'] : [];
        $flags = $this->repo()->flagsForUser((int) $campaign['id'], $tenantId, $userId);
        $blocks = [];
        $answered = 0;
        foreach ($keys as $key) {
            $state = is_array($answers[$key] ?? null) ? (string) ($answers[$key]['state'] ?? '') : '';
            $state = in_array($state, ['ok', 'flag'], true) ? $state : '';
            // La réponse ne vaut que pour ce que le membre a vu : une donnée changée depuis demande une nouvelle lecture.
            $hash = DataCampaignSnapshot::hash($read[$key]['rows'] ?? []);
            $stale = $state !== '' && !DataCampaignBlocks::isVolatile($key) && (string) ($answers[$key]['hash'] ?? '') !== $hash;
            if ($stale) {
                $state = '';
            }
            if ($state !== '') {
                $answered++;
            }
            $blocks[$key] = ($read[$key] ?? ['rows' => [], 'portrait' => null, 'notice' => '', 'links' => []]) + [
                'key' => $key,
                'label' => DataCampaignBlocks::label($key),
                'lead' => DataCampaignBlocks::CATALOG[$key]['lead'],
                'state' => $state,
                'changed_since_answer' => $stale,
                'fields' => DataCampaignBlocks::correctionFields($key),
                'flags' => array_values(array_filter($flags, static fn (array $f): bool => (string) $f['block_key'] === $key)),
            ];
        }
        $changed = [];
        if (!empty($member['confirmed']) && is_array($member['snapshot'] ?? null)) {
            $changed = DataCampaignSnapshot::changedBlocks($member['snapshot'], array_map(static fn (array $b): string => DataCampaignSnapshot::hash($b['rows']), $read));
        }

        return [
            'blocks' => $blocks,
            'answered' => $answered,
            'total' => count($keys),
            'complete' => $answered === count($keys),
            'flags' => $flags,
            'confirmed_at' => (string) ($member['confirmed_at'] ?? ''),
            'changed' => $changed,
        ];
    }

    /**
     * Réponse du membre sur un bloc : « c'est exact » ou « à corriger » (avec précision, et éventuellement des valeurs
     * proposées, qui deviennent une demande de correction de fiche).
     *
     * @param array<string, mixed> $fields valeurs proposées par champ de correction (facultatif)
     * @return array{ok: bool, message: string, request_id?: int}
     */
    public function answer(int $tenantId, int $userId, int $campaignId, string $blockKey, string $verdict, string $message = '', array $fields = []): array
    {
        $ctx = $this->openContext($tenantId, $userId, $campaignId);
        if (isset($ctx['error'])) {
            return ['ok' => false, 'message' => $ctx['error']];
        }
        $campaign = $ctx['campaign'];
        $member = $ctx['member'];
        if (!DataCampaignBlocks::isBlock($blockKey) || !in_array($blockKey, $campaign['blocks'], true)) {
            return ['ok' => false, 'message' => 'Cette partie ne fait pas partie de la campagne.'];
        }
        if (!in_array($verdict, ['ok', 'flag'], true)) {
            return ['ok' => false, 'message' => 'Réponse inconnue.'];
        }
        $block = $this->reader()->read($tenantId, $userId, [$blockKey])[$blockKey] ?? null;
        if ($block === null) {
            return ['ok' => false, 'message' => 'Cette partie de votre fiche n’a pas pu être lue.'];
        }
        $hash = DataCampaignSnapshot::hash($block['rows']);
        $answers = is_array($member['answers'] ?? null) ? $member['answers'] : [];
        $existing = array_values(array_filter(
            $this->repo()->flagsForUser($campaignId, $tenantId, $userId),
            static fn (array $f): bool => (string) $f['block_key'] === $blockKey && (string) $f['status'] === DataCampaignRepository::FLAG_OPEN
        ));
        $linked = array_values(array_filter($existing, static fn (array $f): bool => (int) ($f['correction_request_id'] ?? 0) > 0 && (string) ($f['request_status'] ?? '') === 'pending'));
        if ($linked !== []) {
            return ['ok' => false, 'message' => 'Une demande de correction est déjà en cours pour cette partie : attendez la réponse de l’encadrement.'];
        }
        // Une réponse précédente non suivie d'une demande est remplacée : on retire l'ancien signalement.
        foreach ($existing as $f) {
            $this->repo()->setFlagStatus((int) $f['id'], $tenantId, DataCampaignRepository::FLAG_WITHDRAWN, $userId);
        }

        if ($verdict === 'ok') {
            $answers[$blockKey] = ['state' => 'ok', 'at' => date('Y-m-d H:i:s', $this->now()), 'hash' => $hash];
            $this->repo()->saveAnswers($campaignId, $userId, $answers);

            return ['ok' => true, 'message' => 'Merci : cette partie est confirmée exacte.'];
        }

        $text = trim((string) preg_replace('/[ \t]+/u', ' ', str_replace("\r", '', $message)));
        if (mb_strlen($text) < self::MESSAGE_MIN) {
            return ['ok' => false, 'message' => 'Expliquez en quelques mots ce qui n’est pas exact (' . self::MESSAGE_MIN . ' caractères au moins).'];
        }
        $text = mb_substr($text, 0, self::MESSAGE_MAX);
        $allowed = DataCampaignBlocks::correctionFields($blockKey);
        $proposal = [];
        foreach ($allowed as $field) {
            $value = $fields[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $proposal[$field] = mb_substr(trim((string) $value), 0, 200);
            }
        }
        $requestId = 0;
        $note = 'Fiabilisation ' . $campaign['campaign_year'] . ' — ' . DataCampaignBlocks::label($blockKey) . ' : ' . $text;
        $info = '';
        if ($proposal !== []) {
            $result = $this->correct($tenantId, $userId, $proposal, $note);
            if ($result['ok'] && (int) ($result['request_id'] ?? 0) > 0) {
                $requestId = (int) $result['request_id'];
            } else {
                $info = ' ' . $result['message'];
            }
        }
        $this->repo()->addFlag([
            'campaign_id' => $campaignId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'block_key' => $blockKey,
            'message' => $text,
            'correction_request_id' => $requestId > 0 ? $requestId : null,
        ]);
        $answers[$blockKey] = ['state' => 'flag', 'at' => date('Y-m-d H:i:s', $this->now()), 'hash' => $hash];
        $this->repo()->saveAnswers($campaignId, $userId, $answers);

        return [
            'ok' => true,
            'message' => $requestId > 0
                ? 'Signalement enregistré et demande de correction envoyée : vous suivez sa réponse sur cette page.'
                : 'Signalement enregistré : l’encadrement le traite et vous répond sur cette page.' . $info,
            'request_id' => $requestId,
        ];
    }

    /**
     * « Confirmer ma fiche » : possible quand chaque partie a une réponse. Enregistre la date et l'instantané de ce que le
     * membre voit à cet instant. Une partie dont la donnée a changé depuis sa réponse est à relire.
     *
     * @return array{ok: bool, message: string, hash?: string}
     */
    public function confirm(int $tenantId, int $userId, int $campaignId): array
    {
        $ctx = $this->openContext($tenantId, $userId, $campaignId);
        if (isset($ctx['error'])) {
            return ['ok' => false, 'message' => $ctx['error']];
        }
        $campaign = $ctx['campaign'];
        $member = $ctx['member'];
        $keys = DataCampaignBlocks::normalize($campaign['blocks'] ?? null);
        $answers = is_array($member['answers'] ?? null) ? $member['answers'] : [];
        $read = $this->reader()->read($tenantId, $userId, $keys);
        $rows = [];
        foreach ($keys as $key) {
            $state = is_array($answers[$key] ?? null) ? (string) ($answers[$key]['state'] ?? '') : '';
            if (!in_array($state, ['ok', 'flag'], true)) {
                return ['ok' => false, 'message' => 'Il reste une partie à vérifier : ' . DataCampaignBlocks::label($key) . '.'];
            }
            $rows[$key] = $read[$key]['rows'] ?? [];
            if (!DataCampaignBlocks::isVolatile($key) && (string) ($answers[$key]['hash'] ?? '') !== DataCampaignSnapshot::hash($rows[$key])) {
                unset($answers[$key]);
                $this->repo()->saveAnswers($campaignId, $userId, $answers);

                return ['ok' => false, 'message' => 'La partie « ' . DataCampaignBlocks::label($key) . ' » a changé depuis votre lecture : relisez-la avant de confirmer.'];
            }
        }
        $snapshot = DataCampaignSnapshot::build($rows);
        $hash = DataCampaignSnapshot::overallHash($snapshot);
        $this->repo()->confirm($campaignId, $userId, $answers, $snapshot, $hash);

        return ['ok' => true, 'message' => 'Votre fiche est confirmée le ' . date('d/m/Y', $this->now()) . '. Merci.', 'hash' => $hash];
    }

    /**
     * Mention « Fiabilisée le … » de la fiche : dernière confirmation, campagne, et parties modifiées depuis (instantané
     * différent). Lecture seule.
     *
     * @return array{confirmed_at: string, year: int, title: string, changed: list<string>}|null
     */
    public function ficheStatus(int $tenantId, int $userId): ?array
    {
        $last = $this->repo()->latestConfirmation($tenantId, $userId);
        if ($last === null) {
            return null;
        }
        $snapshot = is_array($last['snapshot'] ?? null) ? $last['snapshot'] : [];
        $changed = [];
        if ($snapshot !== []) {
            $keys = array_values(array_filter(array_map('strval', array_keys($snapshot)), static fn (string $k): bool => DataCampaignBlocks::isBlock($k)));
            $changed = DataCampaignSnapshot::changedBlocks($snapshot, $this->reader()->hashes($tenantId, $userId, $keys));
        }

        return [
            'confirmed_at' => (string) $last['confirmed_at'],
            'year' => (int) ($last['campaign_year'] ?? 0),
            'title' => (string) ($last['title'] ?? ''),
            'changed' => array_map(static fn (string $k): string => DataCampaignBlocks::label($k), $changed),
        ];
    }

    /** À faire pour la pastille du hub : une campagne ouverte attend la confirmation de ce membre. */
    public function pendingBadge(int $tenantId, int $userId): int
    {
        try {
            $active = $this->activeFor($tenantId, $userId);
        } catch (\Throwable) {
            return 0;
        }

        return $active !== null && empty($active['member']['confirmed']) ? 1 : 0;
    }

    /**
     * @return array{error: string}|array{campaign: array<string, mixed>, member: array<string, mixed>}
     */
    private function openContext(int $tenantId, int $userId, int $campaignId): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return ['error' => 'Session invalide.'];
        }
        $campaign = $this->repo()->findCampaign($campaignId, $tenantId);
        if ($campaign === null || $campaign['status'] !== DataCampaignRepository::STATUS_OPEN) {
            return ['error' => 'Cette campagne n’est plus ouverte.'];
        }
        $member = $this->repo()->memberRow($campaignId, $userId);
        if ($member === null) {
            $active = $this->activeFor($tenantId, $userId);
            if ($active === null || (int) $active['campaign']['id'] !== $campaignId) {
                return ['error' => 'Cette campagne ne vous concerne pas.'];
            }
            $member = $active['member'];
        }

        return ['campaign' => $campaign, 'member' => $member];
    }

    // ------------------------------------------------------------------ gestion : suivi

    /**
     * Avancement et listes d'une campagne, limités au périmètre du lecteur.
     *
     * @param array<string, mixed> $campaign
     * @param list<int>|null $viewerUnits null : toute la communauté
     * @return array{members: list<array<string, mixed>>, totals: array<string, int>, byUnit: list<array<string, mixed>>, flags: list<array<string, mixed>>, unitLabels: array<int, string>}
     */
    public function overview(int $tenantId, array $campaign, ?array $viewerUnits): array
    {
        $units = $this->repo()->units($tenantId);
        $labels = [];
        foreach ($units as $id => $u) {
            $labels[$id] = $u['name'];
        }
        $members = DataCampaignScope::filterRows($this->repo()->members((int) $campaign['id'], $tenantId), $viewerUnits);
        $keys = DataCampaignBlocks::normalize($campaign['blocks'] ?? null);
        foreach ($members as &$m) {
            $m['bucket'] = DataCampaignProgress::bucket(!empty($m['confirmed']), (int) $m['open_flags']);
            $answers = is_array($m['answers']) ? $m['answers'] : [];
            $m['answered'] = count(array_filter($keys, static fn (string $k): bool => is_array($answers[$k] ?? null) && in_array($answers[$k]['state'] ?? '', ['ok', 'flag'], true)));
            $m['unit_label'] = $labels[(int) $m['unit_id']] ?? '';
        }
        unset($m);
        $ids = array_map(static fn (array $m): int => (int) $m['user_id'], $members);
        $flags = $viewerUnits === null
            ? $this->repo()->flags((int) $campaign['id'], $tenantId)
            : $this->repo()->flags((int) $campaign['id'], $tenantId, null, $ids);

        return [
            'members' => $members,
            'totals' => DataCampaignProgress::totals($members),
            'byUnit' => DataCampaignProgress::byUnit($members, $labels),
            'flags' => $flags,
            'unitLabels' => $labels,
        ];
    }

    /**
     * Traitement d'un signalement par l'encadrement (hors circuit de correction) : réponse lue par le membre.
     *
     * @param list<int>|null $viewerUnits périmètre du lecteur
     * @return array{ok: bool, message: string}
     */
    public function handleFlag(int $tenantId, int $actorId, int $flagId, string $answer, ?array $viewerUnits): array
    {
        $flag = $this->repo()->findFlag($flagId, $tenantId);
        if ($flag === null) {
            return ['ok' => false, 'message' => 'Signalement introuvable.'];
        }
        if ($viewerUnits !== null && !in_array($this->repo()->primaryUnitId((int) $flag['user_id']), $viewerUnits, true)) {
            return ['ok' => false, 'message' => 'Ce membre n’est pas dans votre périmètre.'];
        }
        $answer = trim((string) preg_replace('/[ \t]+/u', ' ', str_replace("\r", '', $answer)));
        if (mb_strlen($answer) < 3) {
            return ['ok' => false, 'message' => 'Écrivez la réponse que le membre lira (3 caractères au moins).'];
        }
        if (!$this->repo()->setFlagStatus($flagId, $tenantId, DataCampaignRepository::FLAG_HANDLED, $actorId, mb_substr($answer, 0, self::MESSAGE_MAX))) {
            return ['ok' => false, 'message' => 'Ce signalement a déjà été traité.'];
        }
        $this->notify($tenantId, (int) $flag['user_id'], self::NOTIFY_FLAG_HANDLED, ['campaign_id' => (int) $flag['campaign_id'], 'block' => (string) $flag['block_key']]);
        $this->audit('data_campaign.flag_handled', $tenantId, $actorId, (int) $flag['campaign_id'], ['flag' => $flagId]);

        return ['ok' => true, 'message' => 'Signalement traité : le membre est prévenu.'];
    }

    /**
     * Relance manuelle (un membre ou plusieurs) : notification et e-mail, jamais plus d'une relance par membre toutes les
     * REMINDER_COOLDOWN_HOURS heures, jamais à qui a déjà confirmé ou signalé, et au plus BATCH envois.
     *
     * @param list<int>|null $onlyUserIds null : tous les membres sans réponse du périmètre
     * @param list<int>|null $viewerUnits
     * @return array{ok: bool, message: string, sent: int, skipped: int}
     */
    public function remind(int $tenantId, int $actorId, int $campaignId, ?array $onlyUserIds, ?array $viewerUnits): array
    {
        $c = $this->repo()->findCampaign($campaignId, $tenantId);
        if ($c === null || $c['status'] !== DataCampaignRepository::STATUS_OPEN) {
            return ['ok' => false, 'message' => 'La campagne doit être ouverte pour relancer.', 'sent' => 0, 'skipped' => 0];
        }
        $rows = DataCampaignScope::filterRows($this->repo()->members($campaignId, $tenantId), $viewerUnits);
        $now = $this->now();
        $slot = 'manual-' . date('Ymd', $now);
        $sent = 0;
        $skipped = 0;
        foreach ($rows as $m) {
            $uid = (int) $m['user_id'];
            if ($onlyUserIds !== null && !in_array($uid, $onlyUserIds, true)) {
                continue;
            }
            if (!empty($m['confirmed']) || (int) $m['open_flags'] > 0) {
                $skipped++;
                continue;
            }
            $last = (string) ($m['last_reminded_at'] ?? '');
            if ($last !== '' && ($ts = strtotime($last)) !== false && ($now - $ts) < self::REMINDER_COOLDOWN_HOURS * 3600) {
                $skipped++;
                continue;
            }
            if ($sent >= self::BATCH) {
                break;
            }
            if ($this->deliver($c, $uid, $slot, $actorId, $this->mailsEnabled($tenantId))) {
                $sent++;
            } else {
                $skipped++;
            }
        }
        $this->audit('data_campaign.reminded', $tenantId, $actorId, $campaignId, ['sent' => $sent, 'skipped' => $skipped, 'individual' => $onlyUserIds !== null && count($onlyUserIds) === 1]);
        if ($sent === 0) {
            return ['ok' => false, 'message' => 'Aucune relance envoyée : les membres ont déjà répondu ou ont été relancés il y a moins de ' . self::REMINDER_COOLDOWN_HOURS . ' h.', 'sent' => 0, 'skipped' => $skipped];
        }

        return ['ok' => true, 'message' => $sent . ' relance(s) envoyée(s)' . ($skipped > 0 ? ', ' . $skipped . ' ignorée(s) (déjà répondu ou relancé récemment).' : '.'), 'sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Export CSV : une ligne par membre du périmètre, colonnes documentées. Aucune donnée de la fiche : seulement le statut.
     *
     * @param array<string, mixed> $campaign
     * @param list<int>|null $viewerUnits
     * @return list<list<string>> première ligne : en-têtes
     */
    public function exportRows(int $tenantId, array $campaign, ?array $viewerUnits): array
    {
        $overview = $this->overview($tenantId, $campaign, $viewerUnits);
        $labels = ['confirmed' => 'Confirmé', 'flagged' => 'Avec signalements', 'none' => 'Sans réponse'];
        $rows = [self::exportHeaders()];
        foreach ($overview['members'] as $m) {
            $rows[] = [
                (string) $m['display_name'],
                (string) $m['callsign'],
                (string) ($m['unit_label'] ?? ''),
                $labels[(string) $m['bucket']] ?? '',
                (string) $m['answered'] . '/' . count(DataCampaignBlocks::normalize($campaign['blocks'] ?? null)),
                (string) $m['open_flags'],
                (string) ($m['confirmed_at'] ?? ''),
                (string) ($m['last_reminded_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    public static function exportHeaders(): array
    {
        return ['Membre', 'Indicatif', 'Unité', 'Statut', 'Parties répondues', 'Signalements ouverts', 'Confirmé le', 'Dernière relance'];
    }

    // ------------------------------------------------------------------ ouverture automatique, ouverture forcée, rappels

    /**
     * Un passage unique, utilisé tel quel par la tâche planifiée, par la commande data-campaign.php (--open-now) et par le
     * bouton « Ouvrir maintenant » : aucune logique n'est dupliquée.
     *
     *  - ouverture annuelle pour toute communauté qui ne l'a pas coupée, dans sa période (1er au 31 décembre par défaut) ;
     *    avec « force », elle se fait tout de suite, hors période ;
     *  - garde-fous communs : une seule campagne par communauté et par année (une campagne manuelle compte), jamais pour une
     *    communauté sans membre connecté récemment, au plus BATCH membres servis par passage ;
     *  - clôture des campagnes dont la date limite est passée, avec un rapport d'avancement pour la gestion ;
     *  - notification d'ouverture (puis rappels J-14, J-3 et jour J), une seule fois par membre ;
     *  - jamais la moindre modification d'une fiche. « dry_run » ne lit que : rien n'est écrit ni envoyé.
     *
     * @param array{force?: bool, tenant?: int|null, deadline?: string|null, blocks?: list<string>|null, email?: bool, dry_run?: bool, actor?: int} $options
     * @return array{opened: int, closed: int, reminders: int, notified: int, mails: int, tenants: int, errors: int, plan: list<array<string, mixed>>}
     */
    public function runScheduled(array $options = []): array
    {
        $out = ['opened' => 0, 'closed' => 0, 'reminders' => 0, 'notified' => 0, 'mails' => 0, 'tenants' => 0, 'errors' => 0, 'plan' => []];
        if (!$this->repo()->schemaReady()) {
            return $out;
        }
        $force = !empty($options['force']);
        $dry = !empty($options['dry_run']);
        $actor = (int) ($options['actor'] ?? 0);
        $today = $this->today();
        $year = (int) substr($today, 0, 4);
        $only = (int) ($options['tenant'] ?? 0);
        $tenants = $only > 0
            ? [$only]
            : array_values(array_unique(array_merge($this->repo()->tenantsWithAutoEnabled(), $this->repo()->tenantsWithOpenCampaign())));
        $this->stats = ['notified' => 0, 'mails' => 0];
        $budget = self::BATCH;
        foreach ($tenants as $tenantId) {
            try {
                $out['tenants']++;
                $settings = $this->repo()->settings($tenantId);
                $withMail = ($options['email'] ?? true) !== false && $settings['emails_enabled'];
                $plan = $this->planOpen($tenantId, $settings, $today, $year, $force, $options);
                if ($dry || $force || $plan['action'] === 'open') {
                    $out['plan'][] = $plan;
                }
                if ($dry) {
                    continue;
                }
                if ($plan['action'] === 'open' && $this->openAutomatically($tenantId, $plan, $settings, $force, $actor, $today, $withMail, $budget)) {
                    $out['opened']++;
                }
                foreach ($this->repo()->openCampaigns($tenantId) as $c) {
                    if ((string) ($c['due_on'] ?? '') !== '' && (string) $c['due_on'] < $today) {
                        $out['closed'] += $this->closeWithReport($c) ? 1 : 0;
                        continue;
                    }
                    if ($budget > 0) {
                        $budget -= $this->announceOpen($c, $budget, $withMail);
                    }
                    if ($budget > 0) {
                        $sent = $this->runReminders($c, $today, $budget, $withMail);
                        $budget -= $sent;
                        $out['reminders'] += $sent;
                    }
                }
            } catch (\Throwable $e) {
                $out['errors']++;
                error_log('[cron data_campaign] communauté ' . $tenantId . ' : ' . $e->getMessage());
            }
            if ($budget <= 0) {
                break;
            }
        }
        $out['notified'] = $this->stats['notified'];
        $out['mails'] = $this->stats['mails'];

        return $out;
    }

    /**
     * Ce que ferait un passage pour une communauté, sans rien écrire.
     *
     * @param array{auto_enabled: bool, emails_enabled: bool, open_md: string, close_md: string, blocks: list<string>, unit_ids: list<int>, reminder_days: list<int>} $settings
     * @param array<string, mixed> $options
     * @return array{tenant_id: int, action: string, reason: string, members: int, incomplete: bool, due_on: string, blocks: list<string>, unit_ids: list<int>, user_ids: list<int>}
     */
    private function planOpen(int $tenantId, array $settings, string $today, int $year, bool $force, array $options): array
    {
        $plan = ['tenant_id' => $tenantId, 'action' => 'skip', 'reason' => '', 'members' => 0, 'incomplete' => false, 'due_on' => '', 'blocks' => $settings['blocks'], 'unit_ids' => $settings['unit_ids'], 'user_ids' => []];
        if (!$force) {
            if (!$settings['auto_enabled']) {
                return ['reason' => 'Ouverture automatique coupée par la gestion.'] + $plan;
            }
            if (!DataCampaignCalendar::shouldAutoOpen($today, $year, $settings['open_md'], $settings['close_md'])) {
                return ['reason' => 'Hors de la période d’ouverture.'] + $plan;
            }
        }
        if ($this->repo()->campaignForYear($tenantId, $year) !== null) {
            return ['reason' => 'Une campagne existe déjà pour cette année.'] + $plan;
        }
        if ($force) {
            $due = self::date($options['deadline'] ?? null) ?? DataCampaignCalendar::dateIn($year, DataCampaignCalendar::DEFAULT_CLOSE_MD);
            if (is_array($options['blocks'] ?? null) && $options['blocks'] !== []) {
                $plan['blocks'] = DataCampaignBlocks::normalize($options['blocks']);
            }
        } else {
            $due = DataCampaignCalendar::window($year, $settings['open_md'], $settings['close_md'])['due_on'];
        }
        if ($due < $today) {
            return ['reason' => 'La date limite est déjà passée.', 'due_on' => $due] + $plan;
        }
        $plan['due_on'] = $due;
        $units = $this->repo()->units($tenantId);
        foreach ($this->repo()->roster($tenantId) as $m) {
            if (DataCampaignScope::memberInCampaign($m['unit_id'], $settings['unit_ids'], $units)) {
                $plan['user_ids'][] = $m['user_id'];
            }
        }
        $plan['members'] = count($plan['user_ids']);
        if ($plan['members'] === 0) {
            return ['reason' => 'Aucun membre actif.'] + $plan;
        }
        if ($this->repo()->recentlyActiveCount($plan['user_ids'], self::ACTIVE_DAYS) === 0) {
            return ['reason' => 'Aucun membre connecté depuis ' . self::ACTIVE_DAYS . ' jours.'] + $plan;
        }
        $plan['incomplete'] = $this->repo()->withoutCivilNameCount($plan['user_ids']) > $plan['members'] * self::INCOMPLETE_RATIO;
        $plan['action'] = 'open';

        return $plan;
    }

    /**
     * Crée et ouvre la campagne décidée par planOpen(), puis prévient les responsables (un seul avis).
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $settings
     */
    private function openAutomatically(int $tenantId, array $plan, array $settings, bool $force, int $actor, string $today, bool $withMail, int &$budget): bool
    {
        $created = $this->createCampaign($tenantId, $actor, [
            'year' => (int) substr($today, 0, 4),
            'blocks' => $plan['blocks'],
            'unit_ids' => $plan['unit_ids'],
            'reminder_days' => $settings['reminder_days'],
            'opens_on' => $today,
            'due_on' => $plan['due_on'],
            'forced_on' => $force ? $today : null,
        ], true);
        if (!$created['ok'] || (int) ($created['id'] ?? 0) < 1) {
            return false;
        }
        $opened = $this->openCampaign($tenantId, $actor, (int) $created['id'], ['email' => $withMail, 'budget' => $budget]);
        if (!$opened['ok']) {
            return false;
        }
        $budget -= (int) ($opened['served'] ?? 0);
        foreach ($this->managerIds($tenantId) as $managerId) {
            $this->notify($tenantId, $managerId, self::NOTIFY_AUTO_OPENED, [
                'campaign_id' => (int) $created['id'],
                'members' => (int) $plan['members'],
                'incomplete' => (bool) $plan['incomplete'],
                'forced' => $force,
            ]);
        }
        $this->audit('data_campaign.opened_by_athena', $tenantId, $actor, (int) $created['id'], ['members' => $plan['members'], 'forced' => $force, 'incomplete' => $plan['incomplete']]);

        return true;
    }

    /**
     * Clôt une campagne dont la date limite est passée et envoie à la gestion le rapport d'avancement.
     *
     * @param array<string, mixed> $c
     */
    private function closeWithReport(array $c): bool
    {
        $tenantId = (int) $c['tenant_id'];
        if (!$this->closeCampaign($tenantId, 0, (int) $c['id'])['ok']) {
            return false;
        }
        $totals = DataCampaignProgress::totals($this->repo()->members((int) $c['id'], $tenantId));
        foreach ($this->managerIds($tenantId) as $managerId) {
            $this->notify($tenantId, $managerId, self::NOTIFY_AUTO_CLOSED, ['campaign_id' => (int) $c['id']] + $totals);
        }

        return true;
    }

    /**
     * Notification d'ouverture (et e-mail, par la file existante) aux membres inscrits, par lots : chaque membre la reçoit une
     * seule fois, même si la commande ou la tâche est relancée. Retourne le nombre de membres servis.
     *
     * @param array<string, mixed> $c
     */
    private function announceOpen(array $c, int $budget, bool $withMail): int
    {
        $campaignId = (int) $c['id'];
        $tenantId = (int) $c['tenant_id'];
        if ($budget < 1 || in_array('open', $this->repo()->doneSlots($campaignId), true)) {
            return 0;
        }
        $served = array_flip($this->repo()->usersWithSlot($campaignId, 'open'));
        $sent = 0;
        $pending = 0;
        foreach ($this->repo()->members($campaignId, $tenantId) as $m) {
            $uid = (int) $m['user_id'];
            if (isset($served[$uid])) {
                continue;
            }
            if ($sent >= $budget) {
                $pending++;
                continue;
            }
            if (!$this->repo()->reserveReminder($campaignId, $tenantId, $uid, 'open')) {
                continue;
            }
            $this->notify($tenantId, $uid, self::NOTIFY_OPEN, ['campaign_id' => $campaignId, 'year' => $c['campaign_year'], 'due_on' => (string) ($c['due_on'] ?? '')]);
            if ($withMail && $this->mail($tenantId, $uid, $c)) {
                $this->stats['mails']++;
            }
            $this->stats['notified']++;
            $sent++;
        }
        if ($pending === 0) {
            $this->repo()->reserveReminder($campaignId, $tenantId, 0, 'open');
        }

        return $sent;
    }

    /**
     * Rappels dus aujourd'hui pour une campagne ouverte : chaque rappel (J-14, J-3, jour J) part une fois par membre.
     *
     * @param array<string, mixed> $c
     */
    private function runReminders(array $c, string $today, int $budget, bool $withMail): int
    {
        $tenantId = (int) $c['tenant_id'];
        $openedOn = substr((string) ($c['opened_at'] ?? $c['opens_on'] ?? $today), 0, 10);
        $due = (string) ($c['due_on'] ?? '');
        if ($due === '') {
            return 0;
        }
        $slots = DataCampaignCalendar::dueSlots($today, $openedOn, $due, $c['reminder_days'], $this->repo()->doneSlots($c['id']));
        $sent = 0;
        foreach ($slots as $slot) {
            $served = array_flip($this->repo()->usersWithSlot($c['id'], $slot));
            $pending = 0;
            foreach ($this->repo()->members($c['id'], $tenantId) as $m) {
                $uid = (int) $m['user_id'];
                if (!empty($m['confirmed']) || (int) $m['open_flags'] > 0 || isset($served[$uid])) {
                    continue;
                }
                if ($sent >= $budget) {
                    $pending++;
                    continue;
                }
                if ($this->deliver($c, $uid, $slot, 0, $withMail)) {
                    $sent++;
                }
            }
            if ($pending === 0) {
                // Tout le monde a été servi : le rappel est terminé, il ne repartira plus.
                $this->repo()->reserveReminder($c['id'], $tenantId, 0, $slot);
            }
        }

        return $sent;
    }

    /**
     * Envoie un rappel à un membre (notification, puis e-mail s'il l'accepte) : faux s'il est déjà parti (même rappel).
     *
     * @param array<string, mixed> $c
     */
    private function deliver(array $c, int $userId, string $slot, int $actorId, bool $withMail): bool
    {
        $tenantId = (int) $c['tenant_id'];
        if (!$this->repo()->reserveReminder($c['id'], $tenantId, $userId, $slot, $actorId)) {
            return false;
        }
        $this->notify($tenantId, $userId, self::NOTIFY_REMINDER, ['campaign_id' => $c['id'], 'year' => $c['campaign_year'], 'due_on' => (string) ($c['due_on'] ?? '')]);
        $this->stats['notified']++;
        if ($withMail && $this->mail($tenantId, $userId, $c)) {
            $this->stats['mails']++;
        }

        return true;
    }

    /**
     * Responsables à prévenir : ceux qui traitent les corrections de fiches (mêmes droits que la gestion de la campagne).
     *
     * @return list<int>
     */
    private function managerIds(int $tenantId): array
    {
        try {
            if ($this->managers !== null) {
                return array_values(array_map('intval', ($this->managers)($tenantId)));
            }
            $rows = Container::get(\App\Services\Personnel\PersonnelCorrectionRequestService::class)->listStaffRecipients($tenantId);

            return array_values(array_map(static fn (array $r): int => (int) $r['user_id'], $rows));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Les e-mails d'ouverture et de rappel sont-ils actifs pour cette communauté ? (réglage, activé par défaut) */
    private function mailsEnabled(int $tenantId): bool
    {
        return $this->repo()->settings($tenantId)['emails_enabled'];
    }

    // ------------------------------------------------------------------ effets de bord injectables

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $tenantId, int $actorId, int $campaignId, array $details): void
    {
        try {
            if ($this->audit !== null) {
                ($this->audit)($action, $tenantId, $actorId, $campaignId, $details);

                return;
            }
            Container::get(\App\Services\Audit\AuditService::class)->log(
                $action,
                $tenantId,
                $actorId > 0 ? $actorId : null,
                'data_campaign',
                $campaignId > 0 ? $campaignId : null,
                null,
                (string) json_encode($details, JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable) {
            // Le journal indisponible ne bloque pas l'action.
        }
    }

    /** @param array<string, mixed> $payload */
    private function notify(int $tenantId, int $userId, string $type, array $payload): void
    {
        try {
            if ($this->notifier !== null) {
                ($this->notifier)($tenantId, $userId, $type, $payload);

                return;
            }
            $repo = Container::get(\App\Repositories\MemberNotificationRepository::class);
            if ($repo->tableExists()) {
                $repo->create($tenantId, $userId, $type, $payload);
            }
        } catch (\Throwable $e) {
            error_log('[fiabilisation] notification : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $campaign */
    private function mail(int $tenantId, int $userId, array $campaign): bool
    {
        try {
            if ($this->mailer !== null) {
                return ($this->mailer)($tenantId, $userId, $campaign);
            }
            $prefs = Container::get(\App\Repositories\UserNotificationPreferencesRepository::class);
            if (!$prefs->isEmailEventEnabled($userId, \App\Services\Email\EmailEvents::DATA_CAMPAIGN_REMINDER)) {
                return false;
            }
            $user = Container::get(\App\Repositories\UserRepository::class)->findById($userId, $tenantId);
            $to = strtolower(trim((string) ($user['email'] ?? '')));
            if (!is_array($user) || ($user['status'] ?? 'active') !== 'active' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            $tenant = Container::get(\App\Repositories\TenantRepository::class)->findById($tenantId);
            $due = (string) ($campaign['due_on'] ?? '');

            return Container::get(\App\Services\EmailService::class)->sendTemplated(
                \App\Services\Email\EmailEvents::DATA_CAMPAIGN_REMINDER,
                'data_campaign_reminder',
                $to,
                'Vérifiez votre fiche avant le ' . ($due !== '' ? date('d/m/Y', (int) strtotime($due)) : 'terme de la campagne'),
                [
                    'displayName' => (string) ($user['display_name'] ?? ''),
                    'tenantName' => is_array($tenant) ? (string) ($tenant['name'] ?? '') : '',
                    'year' => (int) ($campaign['campaign_year'] ?? 0),
                    'dueOn' => $due,
                    'pageUrl' => jnet_moi_url('fiabilisation'),
                ],
                $tenantId,
                null,
                ['purpose' => 'data_campaign_reminder', 'campaign' => (int) ($campaign['id'] ?? 0)]
            );
        } catch (\Throwable $e) {
            error_log('[fiabilisation] e-mail : ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, string> $fields
     * @return array{ok: bool, message: string, request_id?: int}
     */
    private function correct(int $tenantId, int $userId, array $fields, string $note): array
    {
        try {
            if ($this->corrector !== null) {
                return ($this->corrector)($tenantId, $userId, $fields, $note);
            }

            return Container::get(\App\Services\Personnel\PersonnelCorrectionRequestService::class)->submit($tenantId, $userId, $userId, $fields, $note);
        } catch (\Throwable $e) {
            error_log('[fiabilisation] demande de correction : ' . $e->getMessage());

            return ['ok' => false, 'message' => 'La demande de correction n’a pas pu être créée.'];
        }
    }

    // ------------------------------------------------------------------ utilitaires

    /** Date saisie (AAAA-MM-JJ ou JJ/MM/AAAA) ramenée à AAAA-MM-JJ, null si invalide. */
    public static function date(mixed $raw): ?string
    {
        $s = is_scalar($raw) ? trim((string) $raw) : '';
        if ($s === '') {
            return null;
        }
        foreach (['!Y-m-d', '!d/m/Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $s);
            if ($dt !== false && $dt->format($format === '!Y-m-d' ? 'Y-m-d' : 'd/m/Y') === $s) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /** « 1/12 », « 01-12 » ou « 12-01 » → « MM-JJ » (jour/mois saisis comme sur le site : JJ/MM). */
    private static function md(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('#^(\d{1,2})[/.](\d{1,2})$#', $raw, $m) === 1) {
            return $m[2] . '-' . $m[1];
        }

        return $raw;
    }
}
