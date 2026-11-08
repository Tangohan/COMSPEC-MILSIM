<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Services\Personnel\MemberNameService;
use App\Support\MemberName;
use PDO;

/**
 * Pilotes et JTAC d'une communauté, d'après les dossiers Athena : page /9-line.
 *
 * Sources, de la plus sûre à la moins sûre :
 * - qualifications du membre (personnel_qualifications, fiche liée personnel_qualification_definitions) ; une fiche
 *   utilisée par le cursus pilote (pilot_qualification_rules) compte comme pilote, quel que soit son nom ;
 * - fonctions attribuées (personnel_profile_job_roles, personnel_job_roles) ;
 * - heures aux commandes relevées par le mod (atak_pilot_time) : indication affichée, jamais une qualification.
 *
 * Qualification masquée (niveau de visibilité autre que normal) : ignorée ici, la page est ouverte à tous les membres.
 * Toute lecture est limitée à la communauté demandée. Table absente : la source est ignorée, sans erreur.
 */
final class AirCrewSpecialtyService
{
    public const KIND_PILOT = 'pilot';

    public const KIND_JTAC = 'jtac';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_ROLE = 'role';

    public const STATUS_TRAINING = 'training';

    public const STATUS_EXPIRED = 'expired';

    /** Rang d'une détection : la plus sûre est gardée quand un membre en a plusieurs. */
    private const RANK = [self::STATUS_QUALIFIED => 3, self::STATUS_ROLE => 2, self::STATUS_TRAINING => 1, self::STATUS_EXPIRED => 0];

    private const ADMIN_TRAINING = ['candidate', 'in_training', 'in_evaluation'];

    private const ADMIN_LOST = ['failed', 'suspended', 'revoked'];

    private ?MemberNameService $names = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /**
     * Spécialités lues dans un libellé de qualification ou de fonction (sans tenir compte des accents ni de la casse).
     * « FAC(A) pilot » donne les deux ; un télépilote ou un pilote de char ne sont pas des pilotes d'aéronef.
     *
     * @return list<string>
     */
    public static function classify(string $label): array
    {
        $t = self::normalize($label);
        if ($t === '') {
            return [];
        }
        $out = [];
        if (preg_match('/\b(jtac|jfo|fac|fac a|cas control|controleur aerien|controleur air|controleur avance|controleur d attaque|terminal attack|forward air)\b/u', $t)) {
            $out[] = self::KIND_JTAC;
        }
        $notAircraft = preg_match('/\b(drone|uav|rpa|rpas|tele ?pilote|tele ?pilot)\b/u', $t)
            || preg_match('/\bpilote (de |d )?(char|vab|vbci|vbl|vhm|blinde|vehicule|engin|leclerc|amx|camion|moto|bateau|embarcation)/u', $t);
        if (!$notAircraft && preg_match('/\b(pilote|pilot|pil h|pil a|copilote|co pilote|chef de bord|commandant de bord|aircraft commander|aviator|aviateur|helicopter crew)\b/u', $t)) {
            $out[] = self::KIND_PILOT;
        }

        return $out;
    }

    /**
     * Élève, stagiaire : la détection est signalée « en formation » même si la fiche est obtenue.
     */
    private static function isTraineeLabel(string $label): bool
    {
        return (bool) preg_match('/\b(eleve|stagiaire|student|trainee|en formation)\b/u', self::normalize($label));
    }

    /**
     * Pilote et JTAC détectés par membre. Clé absente : rien au dossier pour cette spécialité.
     *
     * @param list<int> $userIds
     * @return array<int, array<string, array{kind: string, status: string, label: string, source: string, expires_at: string, hours: ?float}>>
     */
    public function forMembers(int $tenantId, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($tenantId < 1 || $ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $this->readQualifications($tenantId, $chunk, $out);
            $this->readJobRoles($tenantId, $chunk, $out);
            $hours = $this->readFlightHours($tenantId, $chunk);
            foreach ($hours as $uid => $h) {
                if (isset($out[$uid][self::KIND_PILOT])) {
                    $out[$uid][self::KIND_PILOT]['hours'] = $h;
                }
            }
        }

        return $out;
    }

    /**
     * Pilotes et JTAC de la communauté pour la page /9-line : membres actifs, hors fiches masquées, pilotes d'abord
     * puis du plus sûr au moins sûr. Une qualification expirée seule n'y figure pas.
     *
     * @return list<array{label: string, name: string, callsign: string, specs: list<array<string, mixed>>}>
     */
    public function roster(int $tenantId): array
    {
        $names = $this->names ??= new MemberNameService($this->db());
        try {
            $ids = $names->memberIds($tenantId);
        } catch (\Throwable) {
            return [];
        }
        $ids = array_diff($ids, $this->hiddenMembers($tenantId, $ids));
        $byUser = $this->forMembers($tenantId, array_values($ids));
        $byUser = array_filter(array_map(
            static fn (array $specs): array => array_filter($specs, static fn (array $s): bool => $s['status'] !== self::STATUS_EXPIRED),
            $byUser
        ));
        if ($byUser === []) {
            return [];
        }
        try {
            $identities = $names->identitiesFor($tenantId, array_keys($byUser));
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($byUser as $uid => $specs) {
            $identity = $identities[$uid] ?? null;
            if ($identity === null) {
                continue;
            }
            $label = MemberName::c2($identity);
            $short = MemberName::short($identity);
            $list = array_values($specs);
            usort($list, static fn (array $a, array $b): int => strcmp($b['kind'], $a['kind']) ?: self::RANK[$b['status']] <=> self::RANK[$a['status']]);
            $out[] = [
                'label' => $label,
                'name' => $short !== $label ? $short : '',
                'callsign' => MemberName::callsign($identity),
                'specs' => $list,
            ];
        }
        usort($out, static function (array $a, array $b): int {
            $ka = $a['specs'][0];
            $kb = $b['specs'][0];

            return strcmp($kb['kind'], $ka['kind']) ?: (self::RANK[$kb['status']] <=> self::RANK[$ka['status']]) ?: strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * @param list<int> $ids
     * @param array<int, array<string, array<string, mixed>>> $out
     */
    private function readQualifications(int $tenantId, array $ids, array &$out): void
    {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $today = date('Y-m-d');
        $pilotDefs = $this->pilotCurriculumDefinitions($tenantId);
        foreach ([true, false] as $withVisibility) {
            try {
                $st = $this->db()->prepare(
                    'SELECT pq.user_id, pq.qualification_name, pq.level, pq.status, pq.expires_at, pq.admin_status, pq.definition_id,'
                    . ' d.name AS def_name, d.code AS def_code, d.short_name AS def_short'
                    . ' FROM personnel_qualifications pq'
                    . ' LEFT JOIN personnel_qualification_definitions d ON d.id = pq.definition_id AND d.tenant_id = ?'
                    . ' WHERE (pq.tenant_id = ? OR pq.tenant_id IS NULL) AND pq.user_id IN (' . $ph . ')'
                    . ($withVisibility ? " AND (pq.visibility_level IS NULL OR pq.visibility_level IN ('', 'normal'))" : '')
                );
                $st->execute(array_merge([$tenantId, $tenantId], $ids));
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $rows = null;
            }
            if ($rows !== null) {
                break;
            }
        }
        foreach ($rows ?? [] as $r) {
            $uid = (int) $r['user_id'];
            $label = trim((string) ($r['def_name'] ?? '')) ?: trim((string) ($r['qualification_name'] ?? ''));
            $kinds = self::classify($label . ' ' . (string) ($r['def_code'] ?? '') . ' ' . (string) ($r['def_short'] ?? ''));
            if (isset($pilotDefs[(int) ($r['definition_id'] ?? 0)]) && !in_array(self::KIND_PILOT, $kinds, true)) {
                $kinds[] = self::KIND_PILOT;
            }
            if ($kinds === []) {
                continue;
            }
            $expires = trim((string) ($r['expires_at'] ?? ''));
            $status = self::qualificationStatus((string) ($r['status'] ?? ''), (string) ($r['admin_status'] ?? ''), $expires, $today, $label);
            if ($status === null) {
                continue;
            }
            foreach ($kinds as $kind) {
                self::keep($out, $uid, $kind, $status, $label, 'qualification', $expires);
            }
        }
    }

    /** État d'une qualification ; null : elle ne dit plus rien (échouée, retirée). */
    public static function qualificationStatus(string $status, string $adminStatus, string $expires, string $today, string $label = ''): ?string
    {
        if (in_array($adminStatus, self::ADMIN_LOST, true)) {
            return null;
        }
        if (in_array($adminStatus, self::ADMIN_TRAINING, true) || $status === 'in_progress') {
            return self::STATUS_TRAINING;
        }
        if ($status === 'expired' || ($expires !== '' && !str_starts_with($expires, '0000') && substr($expires, 0, 10) < $today)) {
            return self::STATUS_EXPIRED;
        }

        return self::isTraineeLabel($label) ? self::STATUS_TRAINING : self::STATUS_QUALIFIED;
    }

    /**
     * @param list<int> $ids
     * @param array<int, array<string, array<string, mixed>>> $out
     */
    private function readJobRoles(int $tenantId, array $ids, array &$out): void
    {
        try {
            $st = $this->db()->prepare(
                'SELECT pj.user_id, r.name, r.slug FROM personnel_profile_job_roles pj
                 INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = pj.tenant_id
                 WHERE pj.tenant_id = ? AND pj.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            );
            $st->execute(array_merge([$tenantId], $ids));
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return;
        }
        foreach ($rows as $r) {
            $label = trim((string) ($r['name'] ?? ''));
            foreach (self::classify($label . ' ' . (string) ($r['slug'] ?? '')) as $kind) {
                $status = self::isTraineeLabel($label) ? self::STATUS_TRAINING : self::STATUS_ROLE;
                self::keep($out, (int) $r['user_id'], $kind, $status, $label, 'fonction', '');
            }
        }
    }

    /**
     * @param list<int> $ids
     * @return array<int, float>
     */
    private function readFlightHours(int $tenantId, array $ids): array
    {
        try {
            $st = $this->db()->prepare(
                'SELECT user_id, SUM(seconds) AS total FROM atak_pilot_time WHERE tenant_id = ? AND user_id IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY user_id'
            );
            $st->execute(array_merge([$tenantId], $ids));
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['user_id']] = round((int) $r['total'] / 3600, 1);
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, true> fiches de qualification du cursus pilote de cette communauté */
    private function pilotCurriculumDefinitions(int $tenantId): array
    {
        try {
            $st = $this->db()->prepare('SELECT DISTINCT definition_id FROM pilot_qualification_rules WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                $out[(int) $id] = true;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Membres dont le dossier n'apparaît pas dans les listes (visibilité autre que normale).
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private function hiddenMembers(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            try {
                $st = $this->db()->prepare(
                    'SELECT user_id FROM personnel_profiles WHERE (tenant_id = ? OR tenant_id IS NULL) AND user_id IN ('
                    . implode(',', array_fill(0, count($chunk), '?')) . ")
                     AND visibility_level IS NOT NULL AND visibility_level NOT IN ('', 'normal')"
                );
                $st->execute(array_merge([$tenantId], $chunk));
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                    $out[] = (int) $id;
                }
            } catch (\Throwable) {
                return [];
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<string, array<string, mixed>>> $out
     */
    private static function keep(array &$out, int $uid, string $kind, string $status, string $label, string $source, string $expires): void
    {
        $cur = $out[$uid][$kind] ?? null;
        if ($cur !== null && self::RANK[$cur['status']] >= self::RANK[$status]) {
            return;
        }
        $out[$uid][$kind] = [
            'kind' => $kind,
            'status' => $status,
            'label' => $label,
            'source' => $source,
            'expires_at' => $expires !== '' && !str_starts_with($expires, '0000') ? substr($expires, 0, 10) : '',
            'hours' => null,
        ];
    }

    private static function normalize(string $raw): string
    {
        $t = mb_strtolower(trim($raw));
        $t = strtr($t, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ô' => 'o', 'ö' => 'o', 'î' => 'i', 'ï' => 'i', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', '’' => ' ', "'" => ' ',
            '–' => ' ', '—' => ' ', '_' => ' ', '-' => ' ', '(' => ' ', ')' => ' ', '/' => ' ', '.' => ' ',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    private function db(): PDO
    {
        return $this->pdo ??= \App\Core\Database::getPdo();
    }
}
