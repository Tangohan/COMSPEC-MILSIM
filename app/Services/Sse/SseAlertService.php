<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAlertRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseSheetRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseAccessCatalog as Rights;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseSheetCatalog as D;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedRealNames;

/**
 * Alertes SSE côté site : suivi des fiches de vigilance émises (en cours, expirée, levée), levée avec motif, renouvellement,
 * adresse de signalement, signalements (site et jeu), propositions à valider. Sert le portail et le contrat du jeu
 * (ICD-SSE-ALERTES-001).
 *
 * Droits (rien de nouveau, sections existantes de SseAccessPolicy) :
 *  - émettre ou renouveler une fiche de vigilance : écrire dans « Fichiers et sujets » ET exporter de « Papiers » (SseSheetAccess::denial) ;
 *  - lever une fiche de vigilance : gérer « Fichiers et sujets » (SseWantedAccess::canAdmin), ou son émetteur s'il peut encore émettre ;
 *  - signaler : tout compte de la communauté qui lit « Fichiers et sujets » (jamais un invité) ;
 *  - traiter un signalement, décider une proposition : écrire dans « Fichiers et sujets » (SseWantedAccess::canManage).
 * La communauté est toujours celle de l'appelant (session ou clé du jeu), jamais un champ de la requête.
 */
final class SseAlertService
{
    public const REPORT_LIMIT = 12;

    public const REPORT_WINDOW_MINUTES = 10;

    public const BODY_MIN = 3;

    public const BODY_MAX = 600;

    /** @var \Closure(int, int, array<string, mixed>): void */
    private \Closure $notify;

    /** @var \Closure(int): list<int> */
    private \Closure $managers;

    /**
     * @param (\Closure(int, int, array<string, mixed>): void)|null $notify (communauté, compte, charge utile) : notification du site
     * @param (\Closure(int): list<int>)|null $managers comptes des gestionnaires de la communauté
     */
    public function __construct(
        private ?SseAlertRepository $alerts = null,
        private ?SseSheetRepository $sheets = null,
        private ?SseWantedSettingsRepository $settings = null,
        ?\Closure $notify = null,
        ?\Closure $managers = null,
    ) {
        $this->alerts ??= new SseAlertRepository();
        $this->sheets ??= new SseSheetRepository();
        $this->settings ??= new SseWantedSettingsRepository();
        $this->notify = $notify ?? static function (int $tenantId, int $userId, array $payload): void {
            (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, 'sse_wanted_notice', $payload);
        };
        $this->managers = $managers ?? static function (int $tenantId): array {
            return (new \App\Repositories\UserRepository())->listActiveUserIdsWithAnyPermissionSlug($tenantId, ['atak.sse.case.manage', 'atak.sse.files.admin', 'atak.sse.grant']);
        };
    }

    public function alerts(): SseAlertRepository
    {
        return $this->alerts;
    }

    /**
     * Droits d'un compte hors session (jeton du jeu) : mêmes sections que le site, lues dans la politique d'accès.
     *
     * @param string $level habilitation du compte (SseMemberClearance)
     */
    public static function accessForUser(int $tenantId, int $userId, string $level, string $label = 'Téléphone ATAK', ?SseAccessPolicy $policy = null): SseSheetAccess
    {
        $policy ??= SseAccessPolicy::forSession();
        $allows = static function (string $section, string $right) use ($policy, $tenantId, $userId): bool {
            try {
                return $userId > 0 && $policy->allows($userId, $section, $right, ['tenant_id' => $tenantId, 'guest' => false]);
            } catch (\Throwable) {
                return false;
            }
        };
        $read = $allows('fichiers', Rights::LEVEL_READ);
        $manage = $allows('fichiers', Rights::LEVEL_WRITE);
        $rank = SseRedactionService::levelRank($level);
        $wanted = new SseWantedAccess(
            $userId < 1,
            $manage,
            $manage && $allows('papiers', Rights::LEVEL_WRITE) && $allows('papiers', Rights::LEVEL_EXPORT) && $rank >= SseRedactionService::levelRank(SseCaseRepository::CLASS_COMMAND),
            $rank >= SseRedactionService::levelRank(SseCaseRepository::CLASS_CONFIDENTIAL),
            $tenantId,
            $userId > 0 ? $userId : null,
            $label,
            $read,
            $manage && $allows('fichiers', Rights::LEVEL_MANAGE)
        );

        return new SseSheetAccess($wanted, $allows, $userId > 0, isset(SseRedactionService::LEVELS[$level]) ? $level : SseCaseRepository::CLASS_INTERNAL);
    }

    // ------------------------------------------------------------ Fiches de vigilance

    /**
     * Vue d'une fiche de vigilance émise : niveau, consignes, échéance, état, adresse de signalement.
     *
     * @param array<string, mixed> $issue document émis (SseSheetRepository::hydrateIssue)
     * @param array<string, mixed>|null $link lien de signalement (SseAlertRepository), null sans l'étape de base
     * @return array<string, mixed>
     */
    public static function vigilanceView(array $issue, ?array $link, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $data = is_array($issue['snapshot']['data'] ?? null) ? $issue['snapshot']['data'] : [];
        $facts = is_array($data['facts'] ?? null) ? $data['facts'] : [];
        $alert = is_array($data['alert'] ?? null) ? $data['alert'] : [];
        // Fiche émise avant le bloc d'alerte : niveau déduit de la menace et de l'armement imprimés.
        $level = SseAlertLevel::of((string) ($facts['threat'] ?? ''), (string) ($facts['armed'] ?? ''));
        $until = (string) ($link['valid_until'] ?? $data['valid_until'] ?? '');
        // Durée courte (6 h, 24 h, 72 h) : l'échéance exacte est dans l'instantané ; « jusqu'à levée » : aucune échéance.
        $exact = (string) ($data['expires_at'] ?? '');
        $untilLifted = !empty($data['until_lifted']);
        if ($untilLifted) {
            $until = '';
        }
        $lifted = ($link['lifted_at'] ?? null) !== null;
        $expired = $exact !== '' ? $exact <= gmdate('Y-m-d H:i:s') : ($until !== '' && $until < $today);
        $state = $lifted ? 'lifted' : ($expired ? 'expired' : 'active');
        $measures = array_values(array_filter(is_array($alert['measures'] ?? null) ? $alert['measures'] : [], 'is_array'));
        $report = SseAlertReportTo::read($link['report'] ?? [] ?: ($alert['report'] ?? []));
        $token = (string) ($link['token'] ?? $alert['token'] ?? '');
        $usable = $token !== '' && ($link['revoked_at'] ?? null) === null;

        return [
            'id' => (int) $issue['id'], 'ref' => (string) $issue['ref'], 'lang' => (string) $issue['lang'], 'classification' => (string) $issue['classification'],
            'person_id' => $issue['person_id'], 'identity_id' => $issue['identity_id'],
            'name' => (string) ($facts['display'] ?? ''), 'threat' => (string) ($facts['threat'] ?? ''), 'armed' => (string) ($facts['armed'] ?? ''),
            'level' => $level['key'], 'rank' => $level['rank'], 'title' => $level['title'], 'shape' => $level['shape'], 'hex' => SseAlertLevel::hex($level['key']),
            'order' => SseAlertLevel::order(array_column($measures, 'code')),
            'measures' => $measures, 'reasons' => array_values(array_filter(is_array($alert['reasons'] ?? null) ? $alert['reasons'] : [], 'is_array')),
            'donts' => is_array($alert['donts'] ?? null) ? $alert['donts'] : [[], []],
            'texts' => is_array($issue['texts'] ?? null) ? $issue['texts'] : [],
            'zone' => (string) ($facts['zone'] ?? ''), 'place' => (string) ($facts['last_seen_place'] ?? ''),
            'issued_at' => (string) $issue['issued_at'], 'issued_by' => (string) $issue['issued_by_label'], 'issued_by_user_id' => $issue['issued_by_user_id'],
            'until' => $until, 'expires_at' => $exact, 'until_lifted' => $untilLifted, 'state' => $state, 'perishable' => $state === 'active' && SseAlertLevel::perishable($exact !== '' ? $exact : $until),
            'lifted_at' => $link['lifted_at'] ?? null, 'lift_reason' => (string) ($link['lift_reason'] ?? ''), 'lifted_by' => (string) ($link['lifted_by_label'] ?? ''),
            'renewed_by' => $link['renewed_by_ref'] ?? null,
            'link_id' => (int) ($link['id'] ?? 0), 'token' => $usable ? $token : '', 'url' => $usable ? SseAlertReportTo::url($token) : '',
            'report' => $report, 'params' => is_array($issue['snapshot']['params'] ?? null) ? $issue['snapshot']['params'] : [],
        ];
    }

    /**
     * Fiches de vigilance émises pour une personne et ses identités, de la plus récente à la plus ancienne.
     *
     * @param list<int> $identityIds
     * @return list<array<string, mixed>>
     */
    public function vigilancesOf(int $tenantId, ?int $personId, array $identityIds, int $limit = 40): array
    {
        if (!SseSheetRepository::ready()) {
            return [];
        }
        $issues = array_values(array_filter($this->sheets->listIssues($tenantId, $personId, $identityIds, 200), static fn (array $i): bool => $i['model'] === 'vigilance'));
        $issues = array_slice($issues, 0, max(1, $limit));
        $links = $this->alerts->linksFor($tenantId, SseAlertRepository::DOC_VIGILANCE, array_column($issues, 'id'));
        $today = gmdate('Y-m-d');

        return array_map(static fn (array $i): array => self::vigilanceView($i, $links[(int) $i['id']] ?? null, $today), $issues);
    }

    /**
     * La vigilance active la plus grave lisible à ce niveau d'habilitation (bandeau de la fiche de la personne), ou null.
     *
     * @param list<int> $identityIds
     * @return array<string, mixed>|null
     */
    public function activeVigilance(int $tenantId, ?int $personId, array $identityIds, string $readerLevel): ?array
    {
        $best = null;
        foreach ($this->vigilancesOf($tenantId, $personId, $identityIds) as $v) {
            if ($v['state'] !== 'active' || SseRedactionService::levelRank($v['classification']) > SseRedactionService::levelRank($readerLevel)) {
                continue;
            }
            if ($best === null || $v['rank'] > $best['rank']) {
                $best = $v;
            }
        }

        return $best;
    }

    /** @return array<string, mixed>|null vue de la fiche de vigilance de cette communauté */
    public function findVigilance(int $tenantId, int $issueId): ?array
    {
        if (!SseSheetRepository::ready()) {
            return null;
        }
        $issue = $this->sheets->findIssue($tenantId, $issueId);
        if ($issue === null || $issue['model'] !== 'vigilance') {
            return null;
        }

        return self::vigilanceView($issue, $this->alerts->linkFor($tenantId, SseAlertRepository::DOC_VIGILANCE, $issueId));
    }

    /** @param array<string, mixed> $view vue d'une fiche (vigilanceView) */
    public static function canLift(SseSheetAccess $access, array $view): bool
    {
        if ($access->wanted()->canAdmin()) {
            return true;
        }

        return $access->userId() !== null && $view['issued_by_user_id'] === $access->userId() && $access->can('vigilance');
    }

    /**
     * Lève une fiche de vigilance en cours : motif obligatoire, journal d'audit et chronologie de l'identité.
     *
     * @return array{ok: bool, error: string, errors: list<string>, view: ?array<string, mixed>}
     */
    public function liftVigilance(int $tenantId, int $issueId, string $reason, SseSheetAccess $access, ?string $renewedByRef = null): array
    {
        $fail = static fn (string $code, string $msg, ?array $view = null): array => ['ok' => false, 'error' => $code, 'errors' => [$msg], 'view' => $view];
        if ($access->tenantId() !== $tenantId) {
            return $fail('notfound', 'Fiche de vigilance introuvable.');
        }
        if (!SseAlertRepository::ready()) {
            return $fail('unavailable', 'La levée d’une fiche de vigilance sera disponible dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.');
        }
        $view = $this->findVigilance($tenantId, $issueId);
        if ($view === null || SseRedactionService::levelRank($view['classification']) > SseRedactionService::levelRank($access->level())) {
            return $fail('notfound', 'Fiche de vigilance introuvable.');
        }
        if (!self::canLift($access, $view)) {
            return $fail('forbidden', 'Droit manquant : gérer « ' . Rights::sectionLabel('fichiers') . ' », ou être l’émetteur de cette fiche. Demandez-le à un gestionnaire des accès SSE.', $view);
        }
        $reason = C::text($reason, 500);
        if (mb_strlen($reason) < self::BODY_MIN) {
            return $fail('reason', 'Le motif de la levée est obligatoire.', $view);
        }
        if ($view['state'] === 'lifted') {
            return $fail('closed', 'Cette fiche de vigilance est déjà levée.', $view);
        }
        $link = $this->alerts->ensureLink($tenantId, SseAlertRepository::DOC_VIGILANCE, $issueId, ['valid_until' => $view['until'], 'report' => $view['report'], 'user_id' => $access->userId()]);
        if ($link === null || !$this->alerts->lift($tenantId, (int) $link['id'], $reason, $access->userId(), $access->actorLabel(), $renewedByRef)) {
            return $fail('closed', 'Cette fiche de vigilance est déjà levée.', $view);
        }
        $this->trace($tenantId, $view, 'vigilance.lifted', sprintf('Fiche de vigilance %s levée.', $view['ref']), ['ref' => $view['ref'], 'reason' => $reason, 'renewed_by' => $renewedByRef], $access->userId(), $access->actorLabel(), $reason);

        return ['ok' => true, 'error' => '', 'errors' => [], 'view' => $this->findVigilance($tenantId, $issueId)];
    }

    /**
     * Renouvelle une fiche de vigilance : une NOUVELLE fiche (nouvelle référence, nouvelle échéance, mêmes niveau, consignes, motifs,
     * textes et service à informer) ; la précédente est levée avec la mention du renouvellement.
     *
     * @return array{ok: bool, error: string, errors: list<string>, id: int, ref: string, bytes: ?string, filename: string, until: string, url: string, token: string}
     */
    public function renewVigilance(int $tenantId, int $issueId, int $days, SseSheetAccess $access, ?SseSheetService $service = null, ?SseSheetFactsService $facts = null, ?string $cid = null): array
    {
        $fail = static fn (string $code, array $errors): array => ['ok' => false, 'error' => $code, 'errors' => $errors, 'id' => 0, 'ref' => '', 'bytes' => null, 'filename' => '', 'until' => '', 'url' => '', 'token' => ''];
        if ($access->tenantId() !== $tenantId) {
            return $fail('notfound', ['Fiche de vigilance introuvable.']);
        }
        $issue = SseSheetRepository::ready() ? $this->sheets->findIssue($tenantId, $issueId) : null;
        if ($issue === null || $issue['model'] !== 'vigilance' || SseRedactionService::levelRank((string) $issue['classification']) > SseRedactionService::levelRank($access->level())) {
            return $fail('notfound', ['Fiche de vigilance introuvable.']);
        }
        $denial = $access->denial('vigilance');
        if ($denial !== null) {
            return $fail('forbidden', [$denial]);
        }
        $view = self::vigilanceView($issue, $this->alerts->linkFor($tenantId, SseAlertRepository::DOC_VIGILANCE, $issueId));
        if ($view['state'] === 'lifted') {
            return $fail('closed', ['Une fiche levée ne se renouvelle pas : émettez-en une nouvelle.']);
        }
        $facts ??= new SseSheetFactsService();
        $service ??= new SseSheetService($facts);
        $subject = $issue['identity_id'] !== null && $issue['person_id'] === null
            ? $facts->subject($tenantId, 'identity', (int) $issue['identity_id'], $access)
            : ($issue['person_id'] !== null ? $facts->subject($tenantId, 'person', (int) $issue['person_id'], $access) : null);
        if ($subject === null) {
            return $fail('notfound', ['La fiche de la personne n’existe plus : la vigilance ne peut pas être renouvelée.']);
        }
        $p = $view['params'];
        $input = [
            'lang' => $issue['lang'], 'classification' => $issue['classification'], 'faces' => $issue['faces'], 'validity' => $days > 0 ? D::validity($days) : (!empty($p['until_lifted']) ? 'until_lifted' : ($p['validity'] ?? D::DEFAULT_VALIDITY)), 'validity_hours' => $days > 0 ? null : ($p['validity_hours'] ?? null),
            'threat' => $view['threat'], 'armed' => $view['armed'], 'texts' => $issue['texts'],
            'measures' => array_column($view['measures'], 'code'), 'reasons' => array_column($view['reasons'], 'code'), 'zone' => $view['zone'],
            'service' => $view['report']['code'], 'service_other' => $view['report']['code'] === '' ? $view['report']['fr'] : '',
            'report_delay' => $view['report']['delay'], 'report_channel' => $view['report']['channel'], 'cid' => $cid,
        ];
        $res = $service->issue($tenantId, $subject, 'vigilance', $input, $access);
        if (!$res['ok']) {
            return $fail($res['error'], $res['errors']);
        }
        if (SseAlertRepository::ready()) {
            $link = $this->alerts->ensureLink($tenantId, SseAlertRepository::DOC_VIGILANCE, $issueId, ['valid_until' => $view['until'], 'report' => $view['report'], 'user_id' => $access->userId()]);
            if ($link !== null) {
                $this->alerts->lift($tenantId, (int) $link['id'], 'Renouvelée par la fiche ' . $res['ref'] . '.', $access->userId(), $access->actorLabel(), $res['ref']);
            }
        }
        $new = $res['id'] > 0 ? $this->findVigilance($tenantId, (int) $res['id']) : null;
        $this->trace($tenantId, $view, 'vigilance.renewed', sprintf('Fiche de vigilance %s renouvelée par %s.', $view['ref'], $res['ref']), ['ref' => $res['ref'], 'previous_ref' => $view['ref']], $access->userId(), $access->actorLabel());

        return ['ok' => true, 'error' => '', 'errors' => [], 'id' => (int) $res['id'], 'ref' => (string) $res['ref'], 'bytes' => $res['bytes'], 'filename' => (string) $res['filename'], 'until' => (string) ($new['until'] ?? ''), 'url' => (string) ($new['url'] ?? ''), 'token' => (string) ($new['token'] ?? '')];
    }

    // ------------------------------------------------------------------ Avis de recherche

    /**
     * Lien de signalement d'un avis de recherche, créé au premier besoin avec son service à informer (instantané du service de l'avis).
     *
     * @param array<string, mixed> $notice avis (SseWantedNoticeRepository::hydrate)
     * @return array<string, mixed>|null null sans l'étape de base
     */
    public function noticeLink(int $tenantId, array $notice): ?array
    {
        if (!SseAlertRepository::ready() || (int) ($notice['tenant_id'] ?? $tenantId) !== $tenantId) {
            return null;
        }
        $found = $this->alerts->linkFor($tenantId, SseAlertRepository::DOC_NOTICE, (int) $notice['id']);
        if ($found !== null) {
            return $found;
        }

        return $this->alerts->ensureLink($tenantId, SseAlertRepository::DOC_NOTICE, (int) $notice['id'], ['report' => $this->noticeReport($tenantId, $notice)]);
    }

    /**
     * Service à informer d'un avis : son service demandeur (instantané du jour de la pose), ses coordonnées, le délai de la communauté.
     *
     * @param array<string, mixed> $notice
     * @return array<string, string>
     */
    public function noticeReport(int $tenantId, array $notice): array
    {
        $config = $this->settings->get($tenantId);
        $snap = [];
        try {
            if (\App\Repositories\SseWantedMetaRepository::ready()) {
                $meta = (new \App\Repositories\SseWantedMetaRepository())->forNotice($tenantId, (int) $notice['id']);
                $snap = is_array($meta['service'] ?? null) ? $meta['service'] : [];
            }
        } catch (\Throwable) {
            $snap = [];
        }
        $label = trim((string) ($notice['contact_label'] ?? ''));
        $channel = trim((string) ($notice['contact_detail'] ?? '')) !== '' ? (string) $notice['contact_detail'] : (string) ($snap['contact'] ?? '');
        $current = ($snap['code'] ?? '') !== '' ? \App\Support\SseWantedServices::find((string) $snap['code'], $config) : null;

        return SseAlertReportTo::read([
            'code' => (string) ($snap['code'] ?? ''),
            'fr' => (string) ($snap['fr'] ?? '') !== '' ? (string) $snap['fr'] : ($label !== '' ? $label : (string) $config['contact_label']),
            'en' => (string) ($snap['en'] ?? '') !== '' ? (string) $snap['en'] : ($label !== '' ? $label : (string) $config['contact_label']),
            'ac' => (string) ($snap['ac'] ?? ''),
            'auth_fr' => (string) ($snap['auth_fr'] ?? '') !== '' ? (string) $snap['auth_fr'] : (string) (($notice['authority'] ?? '') !== '' ? $notice['authority'] : $config['authority_fr']),
            'auth_en' => (string) ($snap['auth_en'] ?? '') !== '' ? (string) $snap['auth_en'] : (string) $config['authority_en'],
            'channel' => $channel !== '' ? $channel : ((string) $config['report_channel'] !== '' ? (string) $config['report_channel'] : SseAlertReportTo::FALLBACK_CHANNEL),
            'delay' => (string) $config['report_delay'], 'color' => (string) ($snap['color'] ?? ''), 'emblem' => (string) ($snap['emblem'] ?? ''),
            'desc_fr' => (string) ($current['desc_fr'] ?? ''), 'desc_en' => (string) ($current['desc_en'] ?? ''), 'address' => (string) ($current['print_address'] ?? ''),
        ]);
    }

    // -------------------------------------------------------------------- Signalements

    /**
     * Enregistre un signalement sur une alerte (page de signalement du site ou jeu). Le lien a déjà été résolu et contrôlé par
     * l'appelant (communauté, habilitation). Débit limité par compte, texte sans organisation réelle, alerte en cours seulement.
     *
     * @param array<string, mixed> $link lien de l'alerte
     * @param array<string, mixed> $input what, when, place, grid, pos (liste [x, y]), text, dir, vehicle, sure, photo_path, cid
     * @param array{user: ?int, label: string, source: string} $actor
     * @param array{ref: string, closed: bool, label?: string} $alert référence de l'alerte et « close » (levée ou expirée)
     * @return array{ok: bool, error: string, errors: list<string>, report: ?array<string, mixed>, replay: bool}
     */
    public function report(int $tenantId, array $link, array $input, array $actor, array $alert): array
    {
        $fail = static fn (string $code, array $errors): array => ['ok' => false, 'error' => $code, 'errors' => $errors, 'report' => null, 'replay' => false];
        if (!SseAlertRepository::ready() || (int) ($link['tenant_id'] ?? 0) !== $tenantId) {
            return $fail('unavailable', ['Les signalements seront disponibles dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.']);
        }
        $cid = is_string($input['cid'] ?? null) && SseAlertRepository::validClientId($input['cid']) ? (string) $input['cid'] : null;
        if ($cid !== null) {
            $same = $this->alerts->reportByClient($tenantId, $cid);
            if ($same !== null) {
                return ['ok' => true, 'error' => '', 'errors' => [], 'report' => $same, 'replay' => true];
            }
        }
        if (!empty($alert['closed']) || ($link['lifted_at'] ?? null) !== null || ($link['revoked_at'] ?? null) !== null) {
            return $fail('closed', ['Cette alerte n’est plus en cours : elle ne reçoit plus de signalement.']);
        }
        $userId = $actor['user'] ?? null;
        if ($userId === null || $userId < 1) {
            return $fail('account_required', ['Un compte de la communauté est nécessaire pour signaler.']);
        }
        if ($this->alerts->countReportsSince($tenantId, $userId, gmdate('Y-m-d H:i:s', time() - self::REPORT_WINDOW_MINUTES * 60)) >= self::REPORT_LIMIT) {
            return $fail('rate_limited', [sprintf('Trop de signalements en peu de temps (%d en %d minutes) : patientez avant d’en envoyer un autre.', self::REPORT_LIMIT, self::REPORT_WINDOW_MINUTES)]);
        }
        $errors = [];
        $body = C::text($input['text'] ?? '', self::BODY_MAX);
        if (mb_strlen($body) < self::BODY_MIN) {
            $errors[] = 'Décrivez ce que vous avez vu (quelques mots au moins).';
        }
        $whenRaw = trim(is_scalar($input['when'] ?? null) ? (string) $input['when'] : '');
        $when = $whenRaw === '' ? gmdate('Y-m-d H:i:s') : C::dateTime($whenRaw);
        if ($when === null) {
            $errors[] = 'Date de l’observation invalide.';
        } elseif ($when > gmdate('Y-m-d H:i:s', time() + 600)) {
            $errors[] = 'La date de l’observation est dans le futur.';
        }
        $place = C::line($input['place'] ?? '', 160);
        $dir = C::line($input['dir'] ?? '', 120);
        $vehicle = C::line($input['vehicle'] ?? '', 160);
        foreach ([$body, $place, $dir, $vehicle] as $text) {
            $hit = SseWantedRealNames::find($text);
            if ($hit !== null) {
                $errors[] = 'Un signalement ne cite pas d’organisation réelle (« ' . $hit . ' »).';
                break;
            }
        }
        if ($errors !== []) {
            return $fail('invalid', $errors);
        }
        $pos = is_array($input['pos'] ?? null) ? array_values($input['pos']) : [];
        $report = $this->alerts->createReport($tenantId, (int) $link['id'], [
            'what' => ($input['what'] ?? '') === 'info' ? 'info' : 'seen', 'seen_at' => $when, 'place' => $place, 'grid' => C::line($input['grid'] ?? '', 24),
            'pos_x' => is_numeric($pos[0] ?? null) ? (float) $pos[0] : null, 'pos_y' => is_numeric($pos[1] ?? null) ? (float) $pos[1] : null,
            'body' => $body, 'direction' => $dir, 'vehicle' => $vehicle, 'certainty' => (string) ($input['sure'] ?? 'medium'),
            'photo_path' => is_string($input['photo_path'] ?? null) ? $input['photo_path'] : null,
            'source' => $actor['source'] === 'game' ? 'game' : 'web', 'client_id' => $cid, 'user_id' => $userId, 'label' => $actor['label'],
        ]);
        if ($report === null) {
            return $fail('conflict', ['Le signalement n’a pas pu être enregistré : réessayez.']);
        }
        $this->audit($tenantId, $userId, $actor['label'], 'alert.report', 'sse_alert_report', (int) $report['id'], '', ['ref' => $report['ref'], 'alert' => $alert['ref'], 'doc' => $link['doc'], 'source' => $report['source'], 'what' => $report['what']]);
        // Notification du site aux gestionnaires (jamais bloquant).
        try {
            foreach (array_slice(array_values(array_unique(array_map('intval', ($this->managers)($tenantId)))), 0, 60) as $manager) {
                if ($manager > 0 && $manager !== $userId) {
                    ($this->notify)($tenantId, $manager, ['event' => 'report', 'ref' => $alert['ref'], 'report' => $report['ref'], 'label' => (string) ($alert['label'] ?? ''), 'path' => 'atak/sse/avis/signalements']);
                }
            }
        } catch (\Throwable $e) {
            error_log('Signalement SSE : notification non envoyée : ' . $e->getMessage());
        }

        return ['ok' => true, 'error' => '', 'errors' => [], 'report' => $report, 'replay' => false];
    }

    /**
     * Change l'état d'un signalement (pris en compte, recoupé, écarté) : écrire dans « Fichiers et sujets ».
     *
     * @return array{ok: bool, error: string, errors: list<string>}
     */
    public function handleReport(int $tenantId, int $reportId, string $status, string $note, SseWantedAccess $access): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Droit manquant : écrire dans « ' . Rights::sectionLabel('fichiers') . ' ». Demandez-le à un gestionnaire des accès SSE.']];
        }
        $report = SseAlertRepository::ready() ? $this->alerts->findReport($tenantId, $reportId) : null;
        if ($report === null || !isset(SseAlertLevel::REPORT_STATUSES[$status])) {
            return ['ok' => false, 'error' => 'notfound', 'errors' => ['Signalement introuvable.']];
        }
        $note = C::text($note, 500);
        if ($status === 'dismissed' && mb_strlen($note) < self::BODY_MIN) {
            return ['ok' => false, 'error' => 'reason', 'errors' => ['Indiquez pourquoi le signalement est écarté.']];
        }
        $this->alerts->setReportStatus($tenantId, $reportId, $status, $note, $access->userId());
        $this->audit($tenantId, $access->userId(), $access->actorLabel(), 'alert.report_' . $status, 'sse_alert_report', $reportId, $note, ['ref' => $report['ref'], 'from' => $report['status'], 'to' => $status]);

        return ['ok' => true, 'error' => '', 'errors' => []];
    }

    // --------------------------------------------------------------------------- Outils

    /**
     * @param array<string, mixed> $view vue d'une fiche de vigilance
     * @param array<string, mixed> $details
     */
    private function trace(int $tenantId, array $view, string $action, string $summary, array $details, ?int $userId, string $label, string $reason = ''): void
    {
        $this->audit($tenantId, $userId, $label, $action, 'sse_doc_issue', (int) $view['id'], $reason, $details);
        $identityId = (int) ($view['identity_id'] ?? 0);
        if ($identityId < 1) {
            return;
        }
        try {
            if (SseIdentityEventRepository::ready()) {
                (new SseIdentityEventRepository())->add($tenantId, $identityId, 'audit', $summary, $details, $userId, $label, 'base', 'web', null);
            }
        } catch (\Throwable $e) {
            error_log('Fiche de vigilance : chronologie indisponible : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $after */
    public function audit(int $tenantId, ?int $userId, string $label, string $action, string $objectType, int $objectId, string $reason, array $after): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $userId, 'actor_label' => $label !== '' ? $label : 'Alertes SSE', 'action' => $action,
                'object_type' => $objectType, 'object_id' => $objectId, 'reason' => $reason, 'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Alertes SSE : audit indisponible : ' . $e->getMessage());
        }
    }
}
