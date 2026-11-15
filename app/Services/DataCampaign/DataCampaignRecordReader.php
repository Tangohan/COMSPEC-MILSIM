<?php

declare(strict_types=1);

namespace App\Services\DataCampaign;

use App\Core\Container;
use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\MemberNameService;
use App\Services\Personnel\MemberNameSettings;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Services\Personnel\PilotLogbookService;
use App\Services\Personnel\PilotQualificationService;
use App\Services\Platform\FeatureGateService;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignPage;
use App\Support\DataCampaignSnapshot;
use App\Support\JnetMemberPaths;

/**
 * Lecture, pour UN membre, de ce que la campagne de fiabilisation lui montre : les valeurs telles qu'elles sont
 * enregistrées, relues dans les services existants (fiche, matricules, affectations, qualifications, heures). Rien n'est
 * écrit. Aucune donnée SSE (identités d'emprunt, couvertures, papiers) n'est lue : seuls les blocs du catalogue le sont.
 *
 * Une source indisponible donne un bloc sans ligne et une mention, jamais une erreur : la campagne reste utilisable.
 */
class DataCampaignRecordReader
{
    private const QUALIFICATION_STATES = [
        'candidate' => 'Candidat',
        'in_training' => 'En formation',
        'in_evaluation' => 'En évaluation',
        'obtained' => 'Obtenue',
        'failed' => 'Échouée',
        'suspended' => 'Suspendue',
        'revoked' => 'Retirée',
    ];

    /**
     * @param list<string> $blocks blocs demandés (DataCampaignBlocks)
     * @return array<string, array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}>
     */
    public function read(int $tenantId, int $userId, array $blocks): array
    {
        $out = [];
        if ($tenantId < 1 || $userId < 1) {
            return $out;
        }
        $ctx = $this->context($tenantId, $userId);
        foreach (DataCampaignBlocks::normalize($blocks) as $key) {
            $block = ['rows' => [], 'portrait' => null, 'notice' => '', 'links' => []];
            try {
                $block = match ($key) {
                    DataCampaignBlocks::IDENTITY => $this->identity($ctx),
                    DataCampaignBlocks::IDENTIFIERS => $this->identifiers($tenantId, $userId, $ctx),
                    DataCampaignBlocks::ASSIGNMENT => $this->assignment($tenantId, $ctx),
                    DataCampaignBlocks::QUALIFICATIONS => $this->qualifications($tenantId, $userId),
                    DataCampaignBlocks::HOURS => $this->hours($tenantId, $userId),
                    DataCampaignBlocks::CONTACT => $this->contact($ctx),
                    default => $block,
                };
            } catch (\Throwable $e) {
                error_log('[fiabilisation] bloc ' . $key . ' illisible : ' . $e->getMessage());
                $block['notice'] = 'Cette partie de votre fiche n’a pas pu être lue pour le moment.';
            }
            $out[$key] = $block;
        }

        return $out;
    }

    /**
     * Empreintes actuelles par bloc (sans les heures, qui évoluent seules).
     *
     * @param list<string> $blocks
     * @return array<string, string>
     */
    public function hashes(int $tenantId, int $userId, array $blocks): array
    {
        $wanted = array_values(array_filter(DataCampaignBlocks::normalize($blocks), static fn (string $k): bool => !DataCampaignBlocks::isVolatile($k)));
        $out = [];
        foreach ($this->read($tenantId, $userId, $wanted) as $key => $block) {
            $out[$key] = DataCampaignSnapshot::hash($block['rows']);
        }

        return $out;
    }

    /**
     * @return array{user: array<string, mixed>, snapshot: array<string, mixed>, profile: array<string, mixed>, tenantId: int}
     */
    private function context(int $tenantId, int $userId): array
    {
        $user = $this->make(UserRepository::class)->findById($userId, $tenantId) ?? [];
        $profile = [];
        try {
            $profile = $this->make(\App\Repositories\PersonnelProfileRepository::class)->getByUserId($userId, $tenantId) ?? [];
        } catch (\Throwable) {
        }
        $snapshot = [];
        try {
            $snapshot = $this->make(PersonnelCorrectionRequestService::class)->currentSnapshot($userId);
        } catch (\Throwable $e) {
            error_log('[fiabilisation] fiche illisible : ' . $e->getMessage());
        }

        return ['user' => $user, 'snapshot' => $snapshot, 'profile' => $profile, 'tenantId' => $tenantId];
    }

    /**
     * @param array{user: array<string, mixed>, snapshot: array<string, mixed>, profile: array<string, mixed>, tenantId: int} $ctx
     * @return array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}
     */
    private function identity(array $ctx): array
    {
        $s = $ctx['snapshot'];
        $p = $ctx['profile'];
        $rows = [
            ['label' => 'Prénom', 'value' => self::text($s['first_name'] ?? ''), 'required' => true],
            ['label' => 'Nom', 'value' => self::text($s['last_name'] ?? ''), 'required' => true],
            ['label' => 'Nom affiché', 'value' => self::text($ctx['user']['display_name'] ?? '')],
            ['label' => 'Nom du personnage', 'value' => self::text($p['character_name'] ?? '')],
            ['label' => 'Indicatif', 'value' => self::text($s['callsign'] ?? ($ctx['user']['callsign'] ?? ''))],
            ['label' => 'Surnom principal', 'value' => self::text($s['nickname_primary'] ?? '')],
        ];
        $path = trim((string) ($p['character_portrait_path'] ?? ''));
        $rows[] = ['label' => 'Portrait', 'value' => $path !== '' ? 'Image enregistrée : ' . basename($path) : 'Aucune image enregistrée'];

        return [
            'rows' => self::filled($rows),
            'portrait' => $path !== '' && function_exists('personnel_operator_portrait_url') ? personnel_operator_portrait_url($p) : null,
            'notice' => '',
            'links' => [['label' => 'Envoyer un nouveau portrait', 'href' => url('account/portrait')]],
        ];
    }

    /**
     * @param array{user: array<string, mixed>, snapshot: array<string, mixed>, profile: array<string, mixed>, tenantId: int} $ctx
     * @return array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}
     */
    private function identifiers(int $tenantId, int $userId, array $ctx): array
    {
        $u = $ctx['user'];
        $s = $ctx['snapshot'];
        $service = '';
        try {
            $extras = $this->make(PersonnelExtrasRepository::class)->getByUserId($userId, $tenantId) ?? [];
            $service = self::text($extras['service_number'] ?? '');
        } catch (\Throwable) {
        }
        $trigram = '';
        try {
            $trigram = $this->make(MemberNameService::class)->trigramFor($tenantId, $userId);
        } catch (\Throwable) {
        }
        $secondary = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['extra_callsigns'] ?? '')) ?: [])));
        $rows = [
            ['label' => 'Numéro de membre de la communauté', 'value' => self::text($u['tenant_member_number'] ?? '')],
            ['label' => 'Identifiant Athena', 'value' => self::text($u['athena_identifier'] ?? '')],
            ['label' => 'Matricule de service', 'value' => $service],
            ['label' => 'Matricule du dossier', 'value' => self::text($s['matricule_internal'] ?? '')],
            ['label' => 'Trigramme', 'value' => self::text($trigram), 'required' => self::trigramsUsed($tenantId)],
            ['label' => 'Indicatif radio', 'value' => self::text($s['callsign'] ?? ($u['callsign'] ?? ''))],
            ['label' => 'Indicatifs radio secondaires', 'value' => implode(' · ', $secondary)],
        ];

        return ['rows' => self::filled($rows), 'portrait' => null, 'notice' => '', 'links' => []];
    }

    /**
     * @param array{user: array<string, mixed>, snapshot: array<string, mixed>, profile: array<string, mixed>, tenantId: int} $ctx
     * @return array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}
     */
    private function assignment(int $tenantId, array $ctx): array
    {
        $s = $ctx['snapshot'];
        $corr = $this->make(PersonnelCorrectionRequestService::class);
        $grade = $corr->displayFieldValue('grade_id', $s['grade_id'] ?? '', $tenantId);
        $units = $corr->displayFieldLines('unit_assignments', $s['unit_assignments'] ?? '', $tenantId);
        $jobs = $corr->displayFieldLines('job_roles', $s['job_roles'] ?? '', $tenantId);
        $since = self::text($s['enlistment_date'] ?? '');
        $rows = [
            ['label' => 'Grade', 'value' => self::text($grade), 'required' => true],
            ['label' => 'Grade affiché', 'value' => self::text($s['rank_display'] ?? '')],
            ['label' => 'Unités', 'value' => implode(' · ', $units), 'required' => true],
            ['label' => 'Fonctions', 'value' => implode(' · ', $jobs)],
            ['label' => 'Date d’engagement', 'value' => self::frenchDate($since)],
        ];

        return [
            'rows' => self::filled($rows),
            'portrait' => null,
            'notice' => '',
            'links' => [['label' => 'Mon dossier de carrière', 'href' => jnet_moi_url('carriere')]],
        ];
    }

    /**
     * @return array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}
     */
    private function qualifications(int $tenantId, int $userId): array
    {
        $awards = $this->make(QualificationAwardRepository::class)->listForUser($userId, $tenantId);
        $rows = [];
        foreach ($awards as $a) {
            $name = self::text($a['definition_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $level = self::text($a['level_name'] ?? '');
            $state = self::QUALIFICATION_STATES[\App\Support\QualificationAdminStatus::normalize((string) ($a['admin_status'] ?? $a['status'] ?? ''))] ?? '';
            $parts = array_values(array_filter([
                $state,
                self::text($a['obtained_at'] ?? '') !== '' ? 'le ' . self::frenchDate(self::text($a['obtained_at'] ?? '')) : '',
                self::text($a['expires_at'] ?? '') !== '' ? 'expire le ' . self::frenchDate(self::text($a['expires_at'] ?? '')) : 'sans date d’expiration',
            ], static fn (string $v): bool => $v !== ''));
            $rows[] = ['label' => $level !== '' ? $name . ' — ' . $level : $name, 'value' => implode(', ', $parts)];
        }

        return [
            'rows' => $rows,
            'portrait' => null,
            'notice' => $rows === [] ? 'Aucune qualification n’est inscrite à votre dossier.' : '',
            'links' => [['label' => 'Mes qualifications', 'href' => jnet_moi_url('qualifications')]],
        ];
    }

    /**
     * @return array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}
     */
    private function hours(int $tenantId, int $userId): array
    {
        $rows = [];
        $summary = $this->make(ArmaPlaytimeRepository::class)->summariesForUsers($tenantId, [$userId])[$userId] ?? null;
        if (is_array($summary)) {
            $rows[] = ['label' => 'Heures de jeu', 'value' => self::duration((int) ($summary['total_seconds'] ?? 0))];
            foreach (['server_seconds' => 'dont sur le serveur', 'zeus_seconds' => 'dont en Zeus', 'editor_seconds' => 'dont dans l’éditeur'] as $col => $label) {
                if ((int) ($summary[$col] ?? 0) > 0) {
                    $rows[] = ['label' => $label, 'value' => self::duration((int) $summary[$col])];
                }
            }
        }
        $links = [];
        try {
            $pilot = $this->pilotSummary($tenantId, $userId);
            if ($pilot !== null) {
                $rows[] = ['label' => 'Heures de vol comptées', 'value' => self::duration($pilot['total'])];
                foreach (array_slice($pilot['by_aircraft'], 0, 5) as $a) {
                    $rows[] = ['label' => 'Vol : ' . self::text($a['label']), 'value' => self::duration((int) $a['seconds'])];
                }
                $links[] = ['label' => 'Mon carnet de vol', 'href' => jnet_moi_url('carnet-de-vol')];
            }
        } catch (\Throwable) {
        }

        return [
            'rows' => $rows,
            'portrait' => null,
            'notice' => $rows === [] ? 'Aucune heure n’est enregistrée pour vous.' : 'Ces heures grandissent à chaque partie : elles ne sont pas comparées après votre confirmation.',
            'links' => $links,
        ];
    }

    /** @return array{total: int, by_aircraft: list<array{label: string, kind: string, seconds: int}>}|null */
    private function pilotSummary(int $tenantId, int $userId): ?array
    {
        if (!$this->make(FeatureGateService::class)->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE)) {
            return null;
        }
        $logbook = $this->make(PilotLogbookService::class);
        if (!$logbook->schemaReady()) {
            return null;
        }
        $legacy = $this->make(\App\Repositories\PilotQualificationRuleRepository::class)->pilotRowsByUser($tenantId)[$userId] ?? [];
        $summary = PilotLogbookService::summary($logbook->entries($tenantId, $userId), $legacy);

        return $summary['total'] > 0 || $summary['by_aircraft'] !== [] ? ['total' => $summary['total'], 'by_aircraft' => $summary['by_aircraft']] : null;
    }

    /**
     * @param array{user: array<string, mixed>, snapshot: array<string, mixed>, profile: array<string, mixed>, tenantId: int} $ctx
     * @return array{rows: list<array{label: string, value: string, missing?: bool, required?: bool}>, portrait: ?string, notice: string, links: list<array{label: string, href: string}>}
     */
    private function contact(array $ctx): array
    {
        return [
            'rows' => self::filled([['label' => 'Adresse e-mail du compte', 'value' => self::text($ctx['user']['email'] ?? ''), 'required' => true]]),
            'portrait' => null,
            'notice' => 'Votre adresse e-mail ne se corrige pas ici : elle se change dans votre compte.',
            'links' => [['label' => 'Mon compte', 'href' => url('account')]],
        ];
    }

    /**
     * @param list<array{label: string, value: string, missing?: bool, required?: bool}> $rows
     * @return list<array{label: string, value: string, missing?: bool, required?: bool}>
     */
    private static function filled(array $rows): array
    {
        return array_map(static function (array $r): array {
            $missing = DataCampaignPage::isMissingValue($r['value']);

            return [
                'label' => $r['label'],
                'value' => $missing ? DataCampaignPage::MISSING : $r['value'],
                'missing' => $missing,
                'required' => (bool) ($r['required'] ?? false),
            ];
        }, $rows);
    }

    /** Réglage de la communauté : trigrammes en usage ? Sans réglage lisible, oui (valeur par défaut). */
    private static function trigramsUsed(int $tenantId): bool
    {
        try {
            return (bool) MemberNameSettings::forTenant($tenantId)['trigrams_enabled'];
        } catch (\Throwable) {
            return true;
        }
    }

    private static function text(mixed $v): string
    {
        return is_scalar($v) ? trim((string) preg_replace('/\s+/u', ' ', (string) $v)) : '';
    }

    public static function duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ' min';
    }

    public static function frenchDate(string $ymd): string
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($ymd, 0, 10));

        return $dt !== false ? $dt->format('d/m/Y') : $ymd;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function make(string $class): object
    {
        try {
            $service = Container::get($class);
            if ($service instanceof $class) {
                return $service;
            }
        } catch (\InvalidArgumentException) {
        }

        return new $class();
    }
}
