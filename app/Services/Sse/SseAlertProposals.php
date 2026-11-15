<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseAccessCatalog as Rights;
use App\Support\SseAlertLevel;
use App\Support\SseSheetCatalog as D;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedCodes;
use App\Support\SseWantedRealNames;
use App\Support\SseWantedServices;

/**
 * Émission d'une alerte SSE demandée depuis le jeu (ICD-SSE-ALERTES-001 § 3.4) et file des propositions à valider.
 *
 *  - normalize() contrôle la saisie du jeu (sujet, type, menace, codes, durée, texte sans organisation réelle) ;
 *  - issue() crée l'alerte avec les droits d'un compte : avis de recherche (SseWantedService::place) ou fiche de vigilance
 *    (SseSheetService::issue). Aucun droit n'est créé ici : ce sont ceux du site ;
 *  - propose() range la saisie dans la file « À valider » du portail (sse_alert_proposals) et prévient les gestionnaires ;
 *  - decide() : un compte qui a le droit accepte (l'alerte est créée avec SES droits, sa référence est notée) ou refuse avec motif.
 *
 * Une identité de membre n'est jamais un sujet. La communauté est celle de l'appelant, jamais un champ de la saisie.
 */
final class SseAlertProposals
{
    public const UNKNOWN_MIN = 10;
    public const UNKNOWN_MAX = 400;
    public const TEXT_MAX = 400;
    public const MAX_CODES = 6;

    /** @var \Closure(int, int, array<string, mixed>): void */
    private \Closure $notify;

    /** @var \Closure(int): list<int> */
    private \Closure $managers;

    /** @var \Closure(int, ?int, string, string, int, string, array<string, mixed>): void */
    private \Closure $audit;

    private Database $db;

    /**
     * @param (\Closure(int, int, array<string, mixed>): void)|null $notify (communauté, compte, charge utile) : notification du site
     * @param (\Closure(int): list<int>)|null $managers comptes des gestionnaires de la communauté
     * @param (\Closure(int, ?int, string, string, int, string, array<string, mixed>): void)|null $audit (communauté, compte, libellé, action, proposition, motif, détail)
     */
    public function __construct(
        private ?SseAlertService $alerts = null,
        private ?SseWantedService $wanted = null,
        private ?SseSheetService $sheets = null,
        private ?SseSheetFactsService $facts = null,
        private ?SseWantedSettingsRepository $settings = null,
        ?\Closure $notify = null,
        ?\Closure $managers = null,
        ?\Closure $audit = null,
        ?Database $db = null,
    ) {
        $this->alerts ??= new SseAlertService();
        $this->settings ??= new SseWantedSettingsRepository();
        $this->db = $db ?? Database::getInstance();
        $this->notify = $notify ?? static function (int $tenantId, int $userId, array $payload): void {
            (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, 'sse_wanted_notice', $payload);
        };
        $this->managers = $managers ?? static function (int $tenantId): array {
            return (new \App\Repositories\UserRepository())->listActiveUserIdsWithAnyPermissionSlug($tenantId, ['atak.sse.case.manage', 'atak.sse.files.admin', 'atak.sse.grant']);
        };
        $alertService = $this->alerts;
        $this->audit = $audit ?? static function (int $tenantId, ?int $userId, string $label, string $action, int $proposalId, string $reason, array $after) use ($alertService): void {
            $alertService->audit($tenantId, $userId, $label, $action, 'sse_alert_proposal', $proposalId, $reason, $after);
        };
    }

    // -------------------------------------------------------------------- Saisie

    /**
     * Saisie du jeu contrôlée et rangée : ce qui sera émis, ou gardé dans la proposition.
     *
     * @param array<string, mixed> $input corps de l'opération « create »
     * @return array{payload: array<string, mixed>, errors: list<string>}
     */
    public function normalize(int $tenantId, array $input): array
    {
        $config = $this->settings->get($tenantId);
        $errors = [];
        $doc = ($input['doc'] ?? '') === SseAlertRepository::DOC_NOTICE ? SseAlertRepository::DOC_NOTICE : (($input['doc'] ?? '') === SseAlertRepository::DOC_VIGILANCE ? SseAlertRepository::DOC_VIGILANCE : '');
        if ($doc === '') {
            $errors[] = 'Genre d’alerte inconnu : « avis » ou « vigilance ».';
            $doc = SseAlertRepository::DOC_VIGILANCE;
        }
        $subject = strtoupper(C::line($input['subject'] ?? '', 40));
        $unknown = $subject === '' ? C::text($input['unknown'] ?? '', self::UNKNOWN_MAX + 200) : '';
        if ($subject === '' && $unknown === '') {
            $errors[] = 'Indiquez le sujet : sa référence au registre, ou la description d’un inconnu.';
        } elseif ($subject === '' && (mb_strlen($unknown) < self::UNKNOWN_MIN || mb_strlen($unknown) > self::UNKNOWN_MAX)) {
            $errors[] = sprintf('La description d’un inconnu fait de %d à %d caractères.', self::UNKNOWN_MIN, self::UNKNOWN_MAX);
        }
        $threat = is_string($input['threat'] ?? null) ? trim($input['threat']) : '';
        if ($threat !== '' && !isset(C::THREATS[$threat])) {
            $errors[] = 'Niveau de menace inconnu.';
            $threat = '';
        }
        $armed = is_string($input['armed'] ?? null) ? trim($input['armed']) : '';
        if ($armed !== '' && !isset(C::ARMED[$armed])) {
            $errors[] = 'État « armé » inconnu.';
            $armed = '';
        }
        $reasons = self::codes($input['reasons'] ?? '', SseWantedCodes::REASONS, $config);
        $measures = self::codes($input['measures'] ?? '', SseWantedCodes::MEASURES, $config);
        $rawText = C::text($input['text'] ?? '', self::TEXT_MAX + 200);
        if (mb_strlen($rawText) > self::TEXT_MAX) {
            $errors[] = sprintf('Le texte fait %d caractères au plus.', self::TEXT_MAX);
        }
        $text = mb_substr($rawText, 0, self::TEXT_MAX);
        $type = '';
        if ($doc === SseAlertRepository::DOC_NOTICE) {
            $type = is_string($input['type'] ?? null) ? trim($input['type']) : '';
            if (!isset(C::types($config)[$type])) {
                $errors[] = 'Choisissez un type d’avis.';
                $type = '';
            }
            if (mb_strlen($text) < SseWantedService::MIN_TEXT && $reasons === []) {
                $errors[] = 'Indiquez les motifs de l’avis : un code de motif ou quelques mots.';
            }
        }
        $service = strtoupper(C::line($input['service'] ?? '', 30));
        if ($service !== '' && SseWantedServices::find($service, $config) === null) {
            $errors[] = 'Service à informer inconnu : choisissez un service de la liste.';
            $service = '';
        }
        $zone = C::line($input['zone'] ?? '', 100);
        $place = C::line($input['place'] ?? '', 120);
        $grid = C::line($input['grid'] ?? '', 24);
        foreach ([$text, $unknown, $zone, $place] as $free) {
            $hit = SseWantedRealNames::find($free);
            if ($hit !== null) {
                $errors[] = 'Une alerte ne cite pas d’organisation réelle (« ' . $hit . ' ») : utilisez un nom fictif.';
                break;
            }
        }
        $days = is_numeric($input['days'] ?? null) ? (int) $input['days'] : D::DEFAULT_VALIDITY;
        if (!in_array($days, D::VALIDITY_DAYS, true)) {
            $errors[] = 'Durée de validité inconnue : ' . implode(', ', D::VALIDITY_DAYS) . ' jours.';
            $days = D::DEFAULT_VALIDITY;
        }
        // Durées courtes (additif) : `validity_hours` 6/24/72 ou `validity: until_lifted` ; elles priment sur `days`.
        $hours = is_numeric($input['validity_hours'] ?? null) ? (int) $input['validity_hours'] : 0;
        if ($hours !== 0 && !in_array($hours, D::VALIDITY_HOURS, true)) {
            $errors[] = 'Durée courte inconnue : ' . implode(', ', D::VALIDITY_HOURS) . ' heures.';
            $hours = 0;
        }
        $lifted = ($input['validity'] ?? '') === 'until_lifted';
        $delay = is_string($input['delay'] ?? null) && isset(SseAlertLevel::DELAYS[$input['delay']]) ? $input['delay'] : '';

        return [
            'payload' => [
                'doc' => $doc, 'subject' => $subject, 'unknown' => $unknown, 'type' => $type, 'threat' => $threat, 'armed' => $armed,
                'reasons' => $reasons, 'measures' => $measures, 'zone' => $zone, 'grid' => $grid, 'place' => $place, 'days' => $days, 'hours' => $hours > 0 && !$lifted ? $hours : 0, 'lifted' => $lifted,
                'text' => $text, 'service' => $service, 'delay' => $delay, 'lang' => ($input['lang'] ?? '') === 'en' ? 'en' : 'fr',
            ],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Le sujet d'une référence : identité du registre (« IDT-000012 ») ou personne (« IDN-00012 », ou son identifiant de sujet).
     * Null : inconnu, d'une autre communauté, identité d'un MEMBRE, ou identité que ce compte n'a pas à connaître.
     *
     * @return array{person: ?array<string, mixed>, identity: ?array<string, mixed>, identities: list<array<string, mixed>>, person_id: ?int, identity_id: ?int, ref: string}|null
     */
    public function subject(int $tenantId, string $ref, SseSheetAccess $access): ?array
    {
        $ref = strtoupper(trim($ref));
        if ($ref === '' || $tenantId < 1) {
            return null;
        }
        $facts = $this->facts ??= new SseSheetFactsService();
        $subject = null;
        try {
            if (preg_match('/^IDT-0*(\d{1,9})$/', $ref, $m) === 1) {
                $subject = $facts->subject($tenantId, 'identity', (int) $m[1], $access);
            } elseif (preg_match('/^IDN-0*(\d{1,9})$/', $ref, $m) === 1) {
                $subject = $facts->subject($tenantId, 'person', (int) $m[1], $access);
            } else {
                $row = $this->db->fetchOne('SELECT id FROM sse_persons WHERE tenant_id = :t AND UPPER(subject_id) = :s ORDER BY id LIMIT 1', ['t' => $tenantId, 's' => $ref]);
                $subject = $row !== null ? $facts->subject($tenantId, 'person', (int) $row['id'], $access) : null;
            }
        } catch (\Throwable) {
            return null;
        }
        if ($subject === null) {
            return null;
        }
        // Jamais l'identité d'un membre : ni comme sujet, ni parmi les identités de la personne.
        foreach (array_merge([$subject['identity']], $subject['identities']) as $identity) {
            if (is_array($identity) && (int) ($identity['user_id'] ?? 0) > 0) {
                return null;
            }
        }

        return $subject;
    }

    /** Ce compte peut-il émettre lui-même ce genre d'alerte (droits du site) ? */
    public static function mayIssue(string $doc, SseSheetAccess $access): bool
    {
        return $doc === SseAlertRepository::DOC_NOTICE ? $access->wanted()->canManage() : $access->can('vigilance');
    }

    /**
     * Pourquoi la saisie part en proposition plutôt qu'en alerte active, ou null si elle peut être émise tout de suite.
     *
     * @param array<string, mixed> $payload saisie de normalize()
     * @return 'unknown'|'right'|'validation'|null
     */
    public function proposalCause(int $tenantId, array $payload, SseSheetAccess $access): ?string
    {
        if ((string) ($payload['subject'] ?? '') === '') {
            return 'unknown';
        }
        if (!self::mayIssue((string) $payload['doc'], $access)) {
            return 'right';
        }
        if ($payload['doc'] === SseAlertRepository::DOC_NOTICE && !empty($this->settings->get($tenantId)['process']['validation'])) {
            return 'validation';
        }

        return null;
    }

    // ------------------------------------------------------------------ Émission

    /**
     * Crée l'alerte avec les droits de ce compte.
     *
     * @param array<string, mixed> $payload saisie de normalize()
     * @return array{ok: bool, error: string, errors: list<string>, doc: string, id: int, ref: string, state: string}
     */
    public function issue(int $tenantId, array $payload, SseSheetAccess $access, ?string $cid = null): array
    {
        $doc = (string) ($payload['doc'] ?? '');
        $fail = static fn (string $code, array $errors): array => ['ok' => false, 'error' => $code, 'errors' => array_values($errors), 'doc' => $doc, 'id' => 0, 'ref' => '', 'state' => ''];
        if ($access->tenantId() !== $tenantId || !in_array($doc, SseAlertRepository::DOCS, true)) {
            return $fail('forbidden', ['Action non autorisée.']);
        }
        if (!self::mayIssue($doc, $access)) {
            return $fail('forbidden', [$doc === SseAlertRepository::DOC_NOTICE
                ? 'Droit manquant : écrire dans « ' . Rights::sectionLabel('fichiers') . ' ». Demandez-le à un gestionnaire des accès SSE.'
                : (string) $access->denial('vigilance')]);
        }
        $subject = $this->subject($tenantId, (string) ($payload['subject'] ?? ''), $access);
        if ($subject === null) {
            return $fail('subject', ['Sujet introuvable au registre de la communauté.']);
        }
        $cid = SseAlertRepository::validClientId($cid) ? $cid : null;
        $lang = ($payload['lang'] ?? '') === 'en' ? 'en' : 'fr';
        $place = trim((string) ($payload['place'] ?? '') . ((string) ($payload['grid'] ?? '') !== '' ? ' (' . $payload['grid'] . ')' : ''));
        $days = D::validity($payload['days'] ?? D::DEFAULT_VALIDITY);
        $hours = (int) ($payload['hours'] ?? 0);
        $lifted = !empty($payload['lifted']);
        $reasons = array_values(array_map('strval', (array) ($payload['reasons'] ?? [])));
        $measures = array_values(array_map('strval', (array) ($payload['measures'] ?? [])));
        $text = (string) ($payload['text'] ?? '');

        if ($doc === SseAlertRepository::DOC_NOTICE) {
            $identityId = (int) ($subject['identity_id'] ?? 0);
            if ($identityId < 1) {
                return $fail('subject', ['Ce sujet n’a pas d’identité au registre : un avis de recherche se pose sur une identité.']);
            }
            $res = ($this->wanted ??= new SseWantedService())->place($tenantId, $identityId, [
                'notice_type' => (string) ($payload['type'] ?? ''), 'threat_level' => (string) ($payload['threat'] ?? '') !== '' ? (string) $payload['threat'] : 'medium',
                'armed' => (string) ($payload['armed'] ?? '') !== '' ? (string) $payload['armed'] : 'unknown',
                'codes_reasons' => $reasons, 'codes_measures' => $measures, 'reason' => $text, 'zone' => (string) ($payload['zone'] ?? ''),
                'last_seen_place' => $place, 'service_code' => (string) ($payload['service'] ?? ''),
                'expires_at' => $lifted ? '' : gmdate('Y-m-d H:i:s', time() + ($hours > 0 ? $hours * 3600 : $days * 86400)), 'lang' => $lang,
            ], $access->wanted());
            if (!$res['ok']) {
                return $fail((string) $res['error'], $res['errors']);
            }
            // Adresse de signalement de l'avis, créée tout de suite : elle porte l'identifiant client (idempotence du jeu).
            try {
                $notice = (new SseWantedNoticeRepository($this->db))->find($tenantId, (int) $res['id']);
                if ($notice !== null) {
                    $this->alerts->alerts()->ensureLink($tenantId, SseAlertRepository::DOC_NOTICE, (int) $res['id'], [
                        'report' => $this->alerts->noticeReport($tenantId, $notice), 'client_id' => $cid, 'user_id' => $access->userId(),
                    ]);
                }
            } catch (\Throwable $e) {
                error_log('Alertes SSE : adresse de signalement de l’avis non enregistrée : ' . $e->getMessage());
            }

            return ['ok' => true, 'error' => '', 'errors' => [], 'doc' => $doc, 'id' => (int) $res['id'], 'ref' => (string) $res['ref'], 'state' => (string) ($res['state'] ?? 'live')];
        }

        $config = $this->settings->get($tenantId);
        $input = [
            'lang' => $lang, 'classification' => SseSheetService::clampLevel($config['default_classification'] ?? '', $access), 'faces' => 'auto', 'validity' => $lifted ? 'until_lifted' : $days, 'validity_hours' => $hours > 0 ? $hours : null,
            'measures' => $measures !== [] ? $measures : null, 'reasons' => $reasons, 'zone' => (string) ($payload['zone'] ?? ''),
            'service' => (string) ($payload['service'] ?? ''), 'report_delay' => (string) ($payload['delay'] ?? ''), 'cid' => $cid,
        ];
        foreach (['threat', 'armed'] as $k) {
            if ((string) ($payload[$k] ?? '') !== '') {
                $input[$k] = (string) $payload[$k];
            }
        }
        $reason = trim($text . ($place !== '' ? ($text !== '' ? "\n" : '') . ($lang === 'en' ? 'Last seen: ' : 'Dernier lieu connu : ') . $place : ''));
        if ($reason !== '') {
            $input['texts'] = ['reason' => $reason];
        }
        $res = ($this->sheets ??= new SseSheetService($this->facts ??= new SseSheetFactsService()))->issue($tenantId, $subject, 'vigilance', $input, $access);
        if (!$res['ok']) {
            return $fail((string) $res['error'], $res['errors']);
        }

        return ['ok' => true, 'error' => '', 'errors' => [], 'doc' => $doc, 'id' => (int) $res['id'], 'ref' => (string) $res['ref'], 'state' => 'live'];
    }

    // -------------------------------------------------------------- Propositions

    /**
     * Range la saisie dans la file « À valider » et prévient les gestionnaires. Un renvoi avec le même identifiant client rend la
     * proposition déjà créée.
     *
     * @param array<string, mixed> $payload saisie de normalize()
     * @param string $cause pourquoi ce n'est pas une alerte active (proposalCause)
     * @return array<string, mixed>|null la proposition (SseAlertRepository::hydrateProposal), null si elle n'a pas pu être écrite
     */
    public function propose(int $tenantId, array $payload, ?string $cid, int $userId, string $label, string $cause = 'right', string $source = 'game'): ?array
    {
        if (!SseAlertRepository::ready() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $repo = $this->alerts->alerts();
        $cid = SseAlertRepository::validClientId($cid) ? $cid : null;
        if ($cid !== null) {
            $same = $repo->proposalByClient($tenantId, $cid);
            if ($same !== null) {
                return $same;
            }
        }
        $proposal = $repo->createProposal($tenantId, (string) $payload['doc'], $payload + ['cause' => $cause], $cid, $userId, $label, $source);
        if ($proposal === null) {
            return null;
        }
        ($this->audit)($tenantId, $userId, $label, 'alert.proposal', (int) $proposal['id'], '', ['ref' => $proposal['ref'], 'doc' => $proposal['doc'], 'cause' => $cause, 'subject' => (string) ($payload['subject'] ?? '')]);
        try {
            foreach (array_slice(array_values(array_unique(array_map('intval', ($this->managers)($tenantId)))), 0, 60) as $manager) {
                if ($manager > 0 && $manager !== $userId) {
                    ($this->notify)($tenantId, $manager, ['event' => 'proposal', 'ref' => $proposal['ref'], 'doc' => $proposal['doc'], 'label' => (string) ($payload['subject'] ?? ''), 'path' => 'atak/sse/avis']);
                }
            }
        } catch (\Throwable $e) {
            error_log('Alertes SSE : notification de proposition non envoyée : ' . $e->getMessage());
        }

        return $proposal;
    }

    /**
     * Propositions de la communauté (file « À valider » du portail), de la plus récente à la plus ancienne.
     *
     * @return list<array<string, mixed>>
     */
    public function listProposals(int $tenantId, string $state = 'pending', int $limit = 100): array
    {
        try {
            return $this->alerts->alerts()->listProposals($tenantId, $state, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Décision sur une proposition en attente : écrire dans « Fichiers et sujets ». Acceptée, l'alerte est créée avec les droits de
     * CELUI QUI ACCEPTE (un compte qui ne peut pas émettre ce document ne peut pas l'accepter) et sa référence est notée ;
     * refusée, le motif est obligatoire. L'auteur est prévenu.
     *
     * @param string|null $subjectRef sujet à retenir quand la proposition décrit un inconnu (référence d'identité ou de personne)
     * @return array{ok: bool, error: string, errors: list<string>, ref: string}
     */
    public function decide(int $tenantId, int $proposalId, bool $accept, string $note, SseSheetAccess $access, ?string $subjectRef = null): array
    {
        $fail = static fn (string $code, array $errors): array => ['ok' => false, 'error' => $code, 'errors' => array_values($errors), 'ref' => ''];
        if ($access->tenantId() !== $tenantId || !$access->wanted()->canManage()) {
            return $fail('forbidden', ['Droit manquant : écrire dans « ' . Rights::sectionLabel('fichiers') . ' ». Demandez-le à un gestionnaire des accès SSE.']);
        }
        if (!SseAlertRepository::ready()) {
            return $fail('unavailable', ['Les propositions seront disponibles dès que l’étape de base de données « alertes SSE » aura été passée par l’équipe technique.']);
        }
        $repo = $this->alerts->alerts();
        $proposal = $repo->findProposal($tenantId, $proposalId);
        if ($proposal === null) {
            return $fail('notfound', ['Proposition introuvable.']);
        }
        if ($proposal['state'] !== 'pending') {
            return $fail('state', ['Cette proposition a déjà été traitée.']);
        }
        $note = C::text($note, 500);
        $author = $proposal['created_by_user_id'];
        if (!$accept) {
            if (mb_strlen($note) < SseAlertService::BODY_MIN) {
                return $fail('reason', ['Le motif du refus est obligatoire.']);
            }
            if (!$repo->decideProposal($tenantId, $proposalId, false, $note, $access->userId())) {
                return $fail('state', ['Cette proposition a déjà été traitée.']);
            }
            ($this->audit)($tenantId, $access->userId(), $access->actorLabel(), 'alert.proposal_rejected', $proposalId, $note, ['ref' => $proposal['ref'], 'doc' => $proposal['doc']]);
            $this->tellAuthor($tenantId, $author, $access->userId(), ['event' => 'proposal_rejected', 'ref' => $proposal['ref'], 'label' => '', 'path' => 'atak/sse/avis']);

            return ['ok' => true, 'error' => '', 'errors' => [], 'ref' => ''];
        }
        $payload = $proposal['payload'];
        $payload['doc'] = $proposal['doc'];
        if ($subjectRef !== null && trim($subjectRef) !== '') {
            $payload['subject'] = strtoupper(C::line($subjectRef, 40));
        }
        if ((string) ($payload['subject'] ?? '') === '') {
            return $fail('subject', ['Cette proposition décrit un inconnu : créez sa fiche, puis acceptez la proposition en indiquant sa référence.']);
        }
        $res = $this->issue($tenantId, $payload, $access);
        if (!$res['ok']) {
            return $fail($res['error'], $res['errors']);
        }
        $repo->decideProposal($tenantId, $proposalId, true, $note, $access->userId(), $res['ref']);
        ($this->audit)($tenantId, $access->userId(), $access->actorLabel(), 'alert.proposal_accepted', $proposalId, $note, ['ref' => $proposal['ref'], 'doc' => $proposal['doc'], 'result_ref' => $res['ref']]);
        $this->tellAuthor($tenantId, $author, $access->userId(), ['event' => 'proposal_accepted', 'ref' => $res['ref'], 'label' => '', 'path' => 'atak/sse/avis']);

        return ['ok' => true, 'error' => '', 'errors' => [], 'ref' => $res['ref']];
    }

    // --------------------------------------------------------------------- Outils

    /** @param array<string, mixed> $payload */
    private function tellAuthor(int $tenantId, ?int $author, ?int $actor, array $payload): void
    {
        if ($author === null || $author < 1 || $author === $actor) {
            return;
        }
        try {
            ($this->notify)($tenantId, $author, $payload);
        } catch (\Throwable $e) {
            error_log('Alertes SSE : notification de décision non envoyée : ' . $e->getMessage());
        }
    }

    /**
     * Codes du catalogue de la communauté (actifs), séparés par des virgules ou en liste : MAX_CODES au plus, inconnus ignorés.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function codes(mixed $raw, string $kind, array $config): array
    {
        $list = is_array($raw) ? $raw : (is_string($raw) ? explode(',', $raw) : []);
        $known = SseWantedCodes::active($config, $kind);
        $out = [];
        foreach ($list as $code) {
            $code = strtoupper(trim(is_scalar($code) ? (string) $code : ''));
            if ($code !== '' && isset($known[$code]) && !in_array($code, $out, true) && count($out) < self::MAX_CODES) {
                $out[] = $code;
            }
        }

        return $out;
    }
}
