<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Support\LocalizedCalendar;
use DateTimeImmutable;

/**
 * Propositions automatiques d’avancement : moteur déterministe, sans IA, à partir des données réelles du membre.
 *
 * Deux étages :
 * 1. Les règles du grade visé (AdvancementEligibilityService : grade précédent, filière, temps minimum,
 *    qualification, PASS). Si une règle manque, le membre n’est « pas encore éligible » et la raison est donnée.
 * 2. Un score sur 100, détaillé critère par critère : temps dans le grade, ancienneté dans la communauté,
 *    présences et heures de jeu validées sur la période, activité récente, conduite (sanctions).
 *    Un critère sans donnée dans la communauté (aucune session enregistrée, module absent…) n’est pas compté :
 *    le score est ramené sur les seuls critères mesurés.
 *
 * Statuts : propose (score suffisant, rien ne bloque), review (à examiner : sous le seuil ou un minimum manque),
 * blocked (écarté : sanction ou inactivité), ineligible (règles du grade non réunies).
 * Rien n’est appliqué : un responsable valide ou écarte chaque proposition.
 */
final class AdvancementProposalEngine
{
    public const SETTINGS_KEY = 'advancement_proposals';

    public const STATUS_PROPOSE = 'propose';
    public const STATUS_REVIEW = 'review';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_INELIGIBLE = 'ineligible';

    /** Seuils par défaut (0 = critère non utilisé, sauf min_score). */
    public const DEFAULTS = [
        'window_days' => 90,
        'min_presences' => 3,
        'min_hours' => 6,
        'inactivity_days' => 30,
        'sanction_window_days' => 180,
        'min_score' => 60,
        'grade_bonus_months' => 12,
        'seniority_full_months' => 24,
        'near_months' => 2,
    ];

    /** Bornes de saisie : [min, max]. */
    private const BOUNDS = [
        'window_days' => [30, 365],
        'min_presences' => [0, 100],
        'min_hours' => [0, 500],
        'inactivity_days' => [0, 365],
        'sanction_window_days' => [0, 730],
        'min_score' => [0, 100],
        'grade_bonus_months' => [1, 120],
        'seniority_full_months' => [1, 240],
        'near_months' => [0, 12],
    ];

    /** Points maximum par critère (total 100 quand tout est mesuré). */
    public const WEIGHTS = [
        'grade_time' => 25,
        'seniority' => 10,
        'presences' => 25,
        'hours' => 20,
        'activity' => 10,
        'conduct' => 10,
    ];

    private const HEAVY_SANCTIONS = ['mute', 'suspend', 'ban'];

    /** Phrases complètes : le texte fixe reste traduisible à l’affichage. */
    private const ACTIVE_SANCTION = [
        'warn' => 'Avertissement en vigueur',
        'mute' => 'Restriction d’activité en vigueur',
        'suspend' => 'Suspension en vigueur',
        'ban' => 'Exclusion en vigueur',
    ];

    private const RECENT_SANCTION = [
        'mute' => 'Restriction d’activité le ',
        'suspend' => 'Suspension le ',
        'ban' => 'Exclusion le ',
    ];

    private const SANCTION_LABELS = [
        'warn' => 'Avertissement',
        'mute' => 'Restriction d’activité',
        'suspend' => 'Suspension',
        'ban' => 'Exclusion',
    ];

    /**
     * @param array<string, mixed> $tenantSettings
     * @return array<string, int>
     */
    public static function settings(array $tenantSettings): array
    {
        $raw = is_array($tenantSettings[self::SETTINGS_KEY] ?? null) ? $tenantSettings[self::SETTINGS_KEY] : [];

        return self::sanitize($raw);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, int>
     */
    public static function sanitize(array $raw): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = $raw[$key] ?? null;
            if ($value === null || $value === '' || !is_numeric($value)) {
                $out[$key] = $default;
                continue;
            }
            [$min, $max] = self::BOUNDS[$key];
            $out[$key] = max($min, min($max, (int) $value));
        }

        return $out;
    }

    /**
     * @param array<string, int> $settings sanitize()
     * @param array<string, mixed> $facts
     *   eligibility: résultat d’AdvancementEligibilityService::evaluate (is_eligible, eligibility_reason, months_in_grade,
     *     months_required, due_on, conditions)
     *   seniority_months: ?int (null = date d’arrivée inconnue)
     *   presences: ?int jours de présence sur la période (null = non mesuré dans la communauté)
     *   hours: ?float heures de jeu validées sur la période (null = non mesuré)
     *   last_activity: ?string date de la dernière activité (null = aucune trace), last_activity_source: string
     *   activity_measured: bool (une source d’activité existe)
     *   sanctions: ?list<array{type:string, created_at:string, expires_at?:?string, revoked_at?:?string}> (null = non mesuré)
     *   absent_until: ?string absence déclarée en cours (« » = sans date de fin, null = pas d’absence)
     * @return array{
     *   status:string, score:int, points:int, max:int,
     *   criteria:list<array{key:string,label:string,points:int,max:int,met:?bool,blocking:bool,detail:string,hint:string}>,
     *   missing:list<string>, strengths:list<string>, justification:string, near:bool
     * }
     */
    public function evaluate(array $settings, array $facts, ?DateTimeImmutable $today = null): array
    {
        $settings = self::sanitize($settings);
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $eligibility = is_array($facts['eligibility'] ?? null) ? $facts['eligibility'] : [];
        $eligible = !empty($eligibility['is_eligible']);
        $window = $settings['window_days'];

        $criteria = [];
        $missing = [];
        $blockers = [];
        $strengths = [];
        $near = false;

        // Règles du grade visé : chaque condition non remplie devient une phrase « manque … ».
        if (!$eligible) {
            $gradeMissing = $this->eligibilityMissing($eligibility, $today);
            $missing = array_merge($missing, $gradeMissing['missing']);
            $near = $gradeMissing['near_months'] !== null && $gradeMissing['near_months'] <= $settings['near_months'] && $gradeMissing['only_time'];
        }

        // Candidat éligible : une qualification manquante reste affichée au staff, sans changer le statut.
        if ($eligible) {
            $missing = array_merge($missing, $this->qualificationNotices($eligibility));
        }

        // 1. Temps dans le grade.
        $months = isset($eligibility['months_in_grade']) ? (int) $eligibility['months_in_grade'] : null;
        $required = isset($eligibility['months_required']) && $eligibility['months_required'] !== null ? max(0, (int) $eligibility['months_required']) : 0;
        if ($months !== null && !empty($eligibility['conditions'])) {
            $target = $required + $settings['grade_bonus_months'];
            $pts = $this->scale($months, $target, self::WEIGHTS['grade_time']);
            $detail = $required > 0 ? $months . ' mois dans le grade (' . $required . ' requis)' : $months . ' mois dans le grade';
            $criteria[] = $this->criterion('grade_time', 'Temps dans le grade', $pts, self::WEIGHTS['grade_time'], $required > 0 ? $months >= $required : true, false, $detail);
            if ($required > 0 && $months >= $required) {
                $strengths[] = $detail;
            }
        }

        // 2. Ancienneté dans la communauté.
        $seniority = $facts['seniority_months'] ?? null;
        if ($seniority !== null) {
            $seniority = max(0, (int) $seniority);
            $pts = $this->scale($seniority, $settings['seniority_full_months'], self::WEIGHTS['seniority']);
            $detail = $seniority . ' mois dans la communauté';
            $criteria[] = $this->criterion('seniority', 'Ancienneté dans la communauté', $pts, self::WEIGHTS['seniority'], null, false, $detail);
            $strengths[] = $detail;
        }

        // 3. Présences sur la période.
        $presences = $facts['presences'] ?? null;
        if ($presences !== null && $settings['min_presences'] > 0) {
            $presences = max(0, (int) $presences);
            $min = $settings['min_presences'];
            $met = $presences >= $min;
            $pts = $this->scale($presences, $min * 2, self::WEIGHTS['presences']);
            $detail = $presences > 1
                ? $presences . ' présences sur ' . $window . ' jours (minimum ' . $min . ')'
                : $presences . ' présence sur ' . $window . ' jours (minimum ' . $min . ')';
            $criteria[] = $this->criterion('presences', 'Présences', $pts, self::WEIGHTS['presences'], $met, false, $detail);
            if ($met) {
                $strengths[] = $presences > 1 ? $presences . ' présences sur ' . $window . ' jours' : $presences . ' présence sur ' . $window . ' jours';
            } else {
                $gap = $min - $presences;
                $missing[] = $gap > 1
                    ? 'Manque ' . $gap . ' présences sur ' . $window . ' jours (' . $presences . ' sur ' . $min . ')'
                    : 'Manque 1 présence sur ' . $window . ' jours (' . $presences . ' sur ' . $min . ')';
            }
        }

        // 4. Heures de jeu validées sur la période.
        $hours = $facts['hours'] ?? null;
        if ($hours !== null && $settings['min_hours'] > 0) {
            $hours = max(0.0, (float) $hours);
            $min = $settings['min_hours'];
            $met = $hours + 1e-9 >= $min;
            $pts = $this->scale($hours, $min * 2, self::WEIGHTS['hours']);
            $detail = $this->hours($hours) . ' de jeu validées sur ' . $window . ' jours (minimum ' . $min . ' h)';
            $criteria[] = $this->criterion('hours', 'Heures de jeu', $pts, self::WEIGHTS['hours'], $met, false, $detail);
            if ($met) {
                $strengths[] = $this->hours($hours) . ' de jeu validées';
            } else {
                $missing[] = 'Manque ' . $this->hours($min - $hours) . ' de jeu validées sur ' . $window . ' jours (' . $this->hours($hours) . ' sur ' . $min . ' h)';
            }
        }

        // 5. Activité récente.
        $blocked = false;
        if (!empty($facts['activity_measured']) && $settings['inactivity_days'] > 0) {
            $limit = $settings['inactivity_days'];
            $absentUntil = $facts['absent_until'] ?? null;
            $last = $this->date((string) ($facts['last_activity'] ?? ''));
            $source = trim((string) ($facts['last_activity_source'] ?? ''));
            if ($absentUntil !== null) {
                $detail = $absentUntil === ''
                    ? 'Absence déclarée en cours : l’inactivité n’est pas retenue'
                    : 'Absence déclarée jusqu’au ' . self::formatDate($absentUntil) . ' : l’inactivité n’est pas retenue';
                $criteria[] = $this->criterion('activity', 'Activité récente', (int) round(self::WEIGHTS['activity'] / 2), self::WEIGHTS['activity'], true, false, $detail);
            } elseif ($last === null) {
                $criteria[] = $this->criterion('activity', 'Activité récente', 0, self::WEIGHTS['activity'], false, true, 'Aucune activité enregistrée');
                $blockers[] = 'Aucune activité enregistrée (session, jeu ou connexion)';
                $blocked = true;
            } else {
                $days = max(0, (int) $last->diff($today)->format('%r%a'));
                if ($days <= $limit) {
                    $detail = match (true) {
                        $days === 0 => 'Dernière activité aujourd’hui',
                        $days === 1 => 'Dernière activité hier',
                        default => 'Dernière activité il y a ' . $days . ' jours',
                    };
                    $pts = (int) round(self::WEIGHTS['activity'] * (1 - 0.5 * $days / $limit));
                    $criteria[] = $this->criterion('activity', 'Activité récente', $pts, self::WEIGHTS['activity'], true, false, $detail, $source);
                    $strengths[] = match (true) {
                        $days === 0 => 'actif aujourd’hui',
                        $days === 1 => 'actif hier',
                        default => 'actif il y a ' . $days . ' jours',
                    };
                } else {
                    $criteria[] = $this->criterion('activity', 'Activité récente', 0, self::WEIGHTS['activity'], false, true, 'Aucune activité depuis ' . $days . ' jours (seuil : ' . $limit . ' jours)', $source);
                    $blockers[] = 'Inactif depuis ' . $days . ' jours (au-delà de ' . $limit . ' jours)';
                    $blocked = true;
                }
            }
        }

        // 6. Conduite : sanctions de la communauté.
        $sanctions = $facts['sanctions'] ?? null;
        if (is_array($sanctions)) {
            $conduct = $this->conduct($sanctions, $settings['sanction_window_days'], $today);
            $criteria[] = $this->criterion('conduct', 'Conduite', $conduct['points'], self::WEIGHTS['conduct'], $conduct['met'], $conduct['blocking'], $conduct['detail'], $conduct['hint']);
            if ($conduct['blocking']) {
                $blockers[] = $conduct['detail'];
                $blocked = true;
            } elseif ($conduct['met'] === true) {
                $strengths[] = 'aucune sanction récente';
            }
        }

        // Ce qui bloque d’abord (après les règles du grade), puis les minimums manquants.
        $gradeCount = $eligible ? 0 : count($this->eligibilityMissing($eligibility, $today)['missing']);
        $missing = array_merge(array_slice($missing, 0, $gradeCount), $blockers, array_slice($missing, $gradeCount));

        $points = 0;
        $max = 0;
        foreach ($criteria as $c) {
            $points += $c['points'];
            $max += $c['max'];
        }
        $score = $max > 0 ? (int) round(100 * $points / $max) : 0;

        $minimumMissing = false;
        foreach ($criteria as $c) {
            if (in_array($c['key'], ['presences', 'hours'], true) && $c['met'] === false) {
                $minimumMissing = true;
            }
        }
        if (!$eligible) {
            $status = self::STATUS_INELIGIBLE;
        } elseif ($blocked) {
            $status = self::STATUS_BLOCKED;
        } elseif ($minimumMissing || $score < $settings['min_score']) {
            $status = self::STATUS_REVIEW;
            if (!$minimumMissing) {
                $missing[] = 'Score ' . $score . ' sur 100, sous le seuil de proposition (' . $settings['min_score'] . ')';
            }
        } else {
            $status = self::STATUS_PROPOSE;
        }

        return [
            'status' => $status,
            'score' => $score,
            'points' => $points,
            'max' => $max,
            'criteria' => $criteria,
            'missing' => array_values(array_unique($missing)),
            'strengths' => $strengths,
            'justification' => $this->justification($score, $strengths),
            'near' => $near,
        ];
    }

    /**
     * Ordre de présentation : proposés, à examiner, écartés, pas encore éligibles ; puis score, temps de grade, nom.
     *
     * @param list<array<string, mixed>> $rows lignes portant 'proposal' (evaluate()) et 'name'
     * @return list<array<string, mixed>>
     */
    public function rank(array $rows): array
    {
        $order = [self::STATUS_PROPOSE => 0, self::STATUS_REVIEW => 1, self::STATUS_BLOCKED => 2, self::STATUS_INELIGIBLE => 3];
        usort($rows, static function (array $a, array $b) use ($order): int {
            $pa = is_array($a['proposal'] ?? null) ? $a['proposal'] : [];
            $pb = is_array($b['proposal'] ?? null) ? $b['proposal'] : [];
            $sa = $order[(string) ($pa['status'] ?? '')] ?? 9;
            $sb = $order[(string) ($pb['status'] ?? '')] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            if (($pa['near'] ?? false) !== ($pb['near'] ?? false)) {
                return !empty($pa['near']) ? -1 : 1;
            }
            $score = (int) ($pb['score'] ?? 0) <=> (int) ($pa['score'] ?? 0);
            if ($score !== 0) {
                return $score;
            }
            $months = (int) ($b['months_in_grade'] ?? 0) <=> (int) ($a['months_in_grade'] ?? 0);
            if ($months !== 0) {
                return $months;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PROPOSE => 'Proposé',
            self::STATUS_REVIEW => 'À examiner',
            self::STATUS_BLOCKED => 'Écarté par la détection',
            self::STATUS_INELIGIBLE => 'Pas encore éligible',
            default => $status,
        };
    }

    /** « 12 mars 2027 » dans la langue du lecteur. */
    public static function formatDate(string $iso): string
    {
        $ts = strtotime(substr($iso, 0, 10));
        if ($ts === false) {
            return $iso;
        }

        return (int) date('j', $ts) . ' ' . LocalizedCalendar::month((int) date('n', $ts)) . ' ' . date('Y', $ts);
    }

    /**
     * @param array<string, mixed> $eligibility
     * @return array{missing:list<string>, near_months:?int, only_time:bool}
     */
    private function eligibilityMissing(array $eligibility, DateTimeImmutable $today): array
    {
        $missing = [];
        $nearMonths = null;
        $failed = 0;
        $timeFailed = false;
        foreach (is_array($eligibility['conditions'] ?? null) ? $eligibility['conditions'] : [] as $condition) {
            if (!is_array($condition) || !empty($condition['met'])) {
                continue;
            }
            $failed++;
            $key = (string) ($condition['key'] ?? '');
            if ($key === 'temps_de_grade') {
                $months = (int) ($eligibility['months_in_grade'] ?? 0);
                $required = (int) ($eligibility['months_required'] ?? 0);
                $due = trim((string) ($eligibility['due_on'] ?? ''));
                if ($required > 0 && $due !== '') {
                    $gap = max(1, $required - $months);
                    $nearMonths = $gap;
                    $timeFailed = true;
                    $missing[] = 'Manque ' . $gap . ' mois dans le grade (' . $months . ' sur ' . $required . ', possible à partir du ' . self::formatDate($due) . ')';
                } else {
                    $missing[] = 'Date d’obtention du grade actuel inconnue : le temps dans le grade ne peut pas être calculé';
                }
                continue;
            }
            if ($key === 'qualification') {
                $label = trim((string) preg_replace('/^Qualification\s+/u', '', (string) ($condition['label'] ?? '')));
                $detail = (string) ($condition['detail'] ?? '');
                $missing[] = str_contains($detail, 'Niveau')
                    ? 'Niveau requis non atteint pour la qualification ' . $label
                    : 'Manque la qualification ' . $label;
                continue;
            }
            if (str_starts_with($key, 'pass')) {
                $passDetail = trim((string) ($condition['detail'] ?? ''));
                $missing[] = 'Condition du PASS non remplie : ' . trim((string) preg_replace('/^PASS\s*·\s*/u', '', (string) ($condition['label'] ?? '')))
                    . ($passDetail !== '' ? ' (' . $passDetail . ')' : '');
                continue;
            }
            $detail = trim((string) ($condition['detail'] ?? ''));
            if ($detail !== '') {
                $missing[] = $detail;
            }
        }
        if ($missing === [] && trim((string) ($eligibility['eligibility_reason'] ?? '')) !== '') {
            $missing[] = trim((string) $eligibility['eligibility_reason']);
            $failed = max(1, $failed);
        }

        return ['missing' => $missing, 'near_months' => $nearMonths, 'only_time' => $timeFailed && $failed === 1];
    }

    /**
     * @param list<array<string, mixed>> $sanctions
     * @return array{points:int, met:bool, blocking:bool, detail:string, hint:string}
     */
    private function conduct(array $sanctions, int $windowDays, DateTimeImmutable $today): array
    {
        $now = $today->getTimestamp() + 86399;
        $from = $windowDays > 0 ? $today->getTimestamp() - $windowDays * 86400 : null;
        $active = null;
        $heavyRecent = null;
        $warnRecent = null;
        foreach ($sanctions as $row) {
            if (!is_array($row) || trim((string) ($row['revoked_at'] ?? '')) !== '') {
                continue;
            }
            $type = (string) ($row['type'] ?? $row['action_type'] ?? 'warn');
            $created = strtotime((string) ($row['created_at'] ?? ''));
            $expires = trim((string) ($row['expires_at'] ?? ''));
            $expiresTs = $expires !== '' ? strtotime($expires) : false;
            $isActive = $expires === '' ? in_array($type, self::HEAVY_SANCTIONS, true) : ($expiresTs !== false && $expiresTs > $now);
            $entry = ['type' => $type, 'created' => $created !== false ? $created : null];
            if ($isActive && $active === null) {
                $active = $entry;
            }
            $recent = $created !== false && ($from === null ? false : $created >= $from);
            if ($recent && in_array($type, self::HEAVY_SANCTIONS, true) && $heavyRecent === null) {
                $heavyRecent = $entry;
            } elseif ($recent && $type === 'warn' && $warnRecent === null) {
                $warnRecent = $entry;
            }
        }
        $date = static fn (array $e): string => $e['created'] !== null ? self::formatDate(date('Y-m-d', (int) $e['created'])) : '';
        $hint = static fn (array $e): string => self::SANCTION_LABELS[$e['type']] ?? 'Mesure disciplinaire';
        if ($active !== null) {
            $base = self::ACTIVE_SANCTION[$active['type']] ?? 'Mesure disciplinaire en vigueur';
            $text = $active['created'] !== null ? $base . ' depuis le ' . $date($active) : $base;

            return ['points' => 0, 'met' => false, 'blocking' => true, 'detail' => $text, 'hint' => $hint($active)];
        }
        if ($heavyRecent !== null) {
            $text = (self::RECENT_SANCTION[$heavyRecent['type']] ?? 'Mesure disciplinaire le ') . $date($heavyRecent) . ' (moins de ' . $windowDays . ' jours)';

            return ['points' => 0, 'met' => false, 'blocking' => true, 'detail' => $text, 'hint' => $hint($heavyRecent)];
        }
        if ($warnRecent !== null) {
            return ['points' => 0, 'met' => false, 'blocking' => false, 'detail' => 'Avertissement le ' . $date($warnRecent) . ' (moins de ' . $windowDays . ' jours)', 'hint' => ''];
        }

        return ['points' => self::WEIGHTS['conduct'], 'met' => true, 'blocking' => false, 'detail' => $windowDays > 0 ? 'Aucune sanction depuis ' . $windowDays . ' jours' : 'Aucune sanction en vigueur', 'hint' => ''];
    }

    /** @param list<string> $strengths */
    /**
     * Qualifications non remplies d'un candidat éligible (suivi indicatif, ne bloque rien).
     *
     * @param array<string, mixed> $eligibility
     * @return list<string>
     */
    private function qualificationNotices(array $eligibility): array
    {
        $notices = [];
        foreach (is_array($eligibility['conditions'] ?? null) ? $eligibility['conditions'] : [] as $condition) {
            if (!is_array($condition) || !empty($condition['met']) || (string) ($condition['key'] ?? '') !== 'qualification') {
                continue;
            }
            $label = trim((string) preg_replace('/^Qualification\s+/u', '', (string) ($condition['label'] ?? '')));
            $notices[] = (str_contains((string) ($condition['detail'] ?? ''), 'Niveau')
                ? 'Niveau requis non atteint pour la qualification ' . $label
                : 'Manque la qualification ' . $label) . ' (pour information, n’empêche pas la proposition)';
        }

        return $notices;
    }

    private function justification(int $score, array $strengths): string
    {
        $text = 'Proposition de la détection automatique (score ' . $score . ' sur 100)';
        if ($strengths !== []) {
            $text .= ' : ' . implode(', ', $strengths);
        }

        return $text . '.';
    }

    /**
     * @return array{key:string,label:string,points:int,max:int,met:?bool,blocking:bool,detail:string,hint:string}
     */
    private function criterion(string $key, string $label, int $points, int $max, ?bool $met, bool $blocking, string $detail, string $hint = ''): array
    {
        return [
            'hint' => $hint,
            'key' => $key,
            'label' => $label,
            'points' => max(0, min($max, $points)),
            'max' => $max,
            'met' => $met,
            'blocking' => $blocking,
            'detail' => $detail,
        ];
    }

    private function scale(float|int $value, float|int $target, int $weight): int
    {
        if ($target <= 0) {
            return $weight;
        }

        return (int) round($weight * min(1.0, max(0.0, $value / $target)));
    }

    private function date(string $raw): ?DateTimeImmutable
    {
        $raw = substr(trim($raw), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1 || $raw === '0000-00-00') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }


    private function hours(float $hours): string
    {
        $rounded = round($hours, 1);
        $text = abs($rounded - round($rounded)) < 0.05 ? (string) (int) round($rounded) : number_format($rounded, 1, ',', '');

        return $text . ' h';
    }

}
