<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Colonne « Rôle » du tableur des effectifs : le rôle effectif d'un membre.
 *
 * 1. Le rôle saisi sur son affectation (personnel_assignments.role_name), sauf le libellé posé d'office (« Membre ») et
 *    un rôle qui ne fait que reprendre le nom de l'unité (poste créé avec l'unité, ex. « 24th STS Gold Team SOF TACP »).
 * 2. Sinon le rôle lié à son emploi principal : le nom de cet emploi (Rifleman, Pararescue Jumper…), marqué « hérité ».
 * 3. Sinon rien (« — »).
 *
 * Avant, la colonne ne lisait que le rôle de l'affectation et masquait celui qui répétait l'unité ou l'emploi : un membre
 * affecté sans rôle saisi, ou dont le rôle reprenait le nom de son unité, restait vide alors que son emploi disait son rôle.
 */
final class RosterEffectiveRole
{
    public const SOURCE_MEMBER = 'member';
    public const SOURCE_JOB = 'job';
    public const SOURCE_NONE = 'none';

    /**
     * @param list<string> $unitNames nom de l'unité et son libellé court
     * @param list<array<string, mixed>> $jobs emplois du membre (label, primary), dans l'ordre du tableur
     * @return array{label: string, source: string, raw: string, hint: string}
     */
    public static function resolve(string $assignmentRole, array $unitNames, array $jobs): array
    {
        $raw = trim($assignmentRole);
        $hint = '';
        if ($raw !== '' && !self::isGeneric($raw)) {
            if (!self::echoesUnit($raw, $unitNames)) {
                return ['label' => $raw, 'source' => self::SOURCE_MEMBER, 'raw' => $raw, 'hint' => ''];
            }
            $hint = $raw;
        }
        $job = self::primaryJobName($jobs);
        if ($job !== '') {
            return ['label' => $job, 'source' => self::SOURCE_JOB, 'raw' => $raw, 'hint' => $hint];
        }

        return ['label' => '', 'source' => self::SOURCE_NONE, 'raw' => $raw, 'hint' => $hint];
    }

    /** « Membre » est posé d'office quand aucun rôle n'est indiqué : ce n'est pas un rôle saisi. */
    public static function isGeneric(string $role): bool
    {
        $n = mb_strtolower(trim($role), 'UTF-8');

        return $n === '' || $n === 'membre' || $n === 'member';
    }

    /** @param list<string> $unitNames */
    public static function echoesUnit(string $role, array $unitNames): bool
    {
        $r = self::norm($role);
        if ($r === '') {
            return false;
        }
        foreach ($unitNames as $unit) {
            $u = self::norm((string) $unit);
            if ($u === '') {
                continue;
            }
            if ($u === $r || (min(strlen($u), strlen($r)) >= 6 && (str_contains($r, $u) || str_contains($u, $r)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nom de l'emploi principal (le premier si aucun n'est marqué principal), sans le détail « — … ».
     *
     * @param list<array<string, mixed>> $jobs
     */
    public static function primaryJobName(array $jobs): string
    {
        $pick = null;
        foreach ($jobs as $job) {
            if (is_array($job) && !empty($job['primary'])) {
                $pick = $job;
                break;
            }
        }
        $pick ??= is_array($jobs[0] ?? null) ? $jobs[0] : null;
        if ($pick === null) {
            return '';
        }
        $label = trim((string) ($pick['name'] ?? $pick['label'] ?? ''));
        $cut = mb_strpos($label, ' — ');

        return trim($cut !== false ? mb_substr($label, 0, $cut) : $label);
    }

    private static function norm(string $v): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($v, 'UTF-8')));
    }
}
