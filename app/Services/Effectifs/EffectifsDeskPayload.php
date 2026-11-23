<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Support\MemberName;
use App\Support\RosterMemberColumns;

/**
 * Données du Bureau des effectifs plein écran (vue « logiciel » en lecture seule) : met en forme, pour le script de la
 * page, les fiches de la liste des membres, leur état de compte (fiche sans compte, invitation, compte actif) et les
 * unités de la communauté. Calcul pur, sans lecture en base : EffectifsDeskController fournit les lignes.
 */
final class EffectifsDeskPayload
{
    /** Compte utilisable, ni fiche ni invitation. */
    public const ACCOUNT_ACTIVE = 'actif';

    public const ACCOUNT_LABELS = [
        NoAccountDirectory::STATUS_FICHE => 'Sans compte',
        NoAccountDirectory::STATUS_INVITEE => 'Invitation envoyée',
        NoAccountDirectory::STATUS_PENDING => 'Compte à activer',
        NoAccountDirectory::STATUS_ACCOUNT => 'Compte actif',
        self::ACCOUNT_ACTIVE => 'Compte actif',
    ];

    public const STATUS_LABELS = [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'pending_verification' => 'E-mail à vérifier',
    ];

    /**
     * @param list<array<string, mixed>> $rows lignes de EffectifsWorkspaceController::deskRoster()
     * @param array<int, array<string, mixed>> $identities identités affichables (MemberNameService)
     * @param list<array<string, mixed>> $noAccount lignes de NoAccountDirectory::list($tenantId, true)
     * @return list<array<string, mixed>>
     */
    public static function members(array $rows, array $identities, array $noAccount): array
    {
        $byUser = [];
        foreach ($noAccount as $n) {
            $byUser[(int) ($n['id'] ?? 0)] = $n;
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $nameRow = MemberName::withIdentity(array_diff_key($row, ['first_name' => 1, 'last_name' => 1]), $identities[$id] ?? null);
            $cols = RosterMemberColumns::split(
                $nameRow,
                trim((string) ($row['tenant_member_number'] ?? '')),
                trim((string) ($row['athena_identifier'] ?? '')),
                function_exists('personnel_decode_extra_callsigns') ? personnel_decode_extra_callsigns($row['extra_callsigns_json'] ?? null) : []
            );
            $last = $cols['last'];
            if ($last === '' && $cols['first'] === '') {
                $last = $cols['callsign'] !== '' ? $cols['callsign'] : $cols['fallback'];
            }
            $path = trim((string) ($row['assignment_path'] ?? ''));
            $unitName = trim((string) ($row['unit_name'] ?? ''));
            if ($path === '' && $unitName !== '') {
                $path = $unitName;
            }
            $parts = $path !== '' ? array_values(array_filter(array_map('trim', explode(' / ', $path)), static fn (string $p): bool => $p !== '')) : [];
            $account = isset($byUser[$id]) ? (string) $byUser[$id]['status'] : self::ACCOUNT_ACTIVE;
            $status = (string) ($row['status'] ?? '');
            $grade = function_exists('personnel_assigned_grade_label') ? personnel_assigned_grade_label($row) : '';
            $short = trim((string) ($row['grade_short'] ?? ''));
            $emploi = trim((string) ($row['job_role_display'] ?? ''));
            if ($emploi === '') {
                $emploi = trim((string) ($row['assignment_role_name'] ?? ''));
            }
            $items = [];
            foreach (is_array($row['qualifications_items'] ?? null) ? $row['qualifications_items'] : [] as $q) {
                $items[] = [
                    'name' => (string) ($q['name'] ?? ''),
                    'level' => (string) ($q['level'] ?? ''),
                    'temporal' => (string) ($q['temporal'] ?? ''),
                ];
            }
            $out[] = [
                'id' => $id,
                'callsign' => $cols['callsign'] !== '' ? $cols['callsign'] : $cols['fallback'],
                'hasCallsign' => $cols['callsign'] !== '',
                'first' => $cols['first'],
                'last' => $last,
                'matricule' => $cols['matricule'],
                'grade' => $short !== '' ? $short : $grade,
                'gradeLong' => $grade,
                'unitId' => (int) ($row['unit_id'] ?? 0),
                'unit' => $parts !== [] ? (string) end($parts) : '',
                'unitPath' => $path,
                'parent' => count($parts) > 1 ? implode(' / ', array_slice($parts, 0, -1)) : '',
                'emploi' => $emploi,
                'status' => $status,
                'statusLabel' => self::STATUS_LABELS[$status] ?? ($status !== '' ? $status : '—'),
                'account' => $account,
                'accountLabel' => self::ACCOUNT_LABELS[$account] ?? self::ACCOUNT_LABELS[self::ACCOUNT_ACTIVE],
                'email' => isset($byUser[$id]) ? (string) ($byUser[$id]['email'] ?? '') : '',
                'inviteId' => isset($byUser[$id]) ? (int) ($byUser[$id]['invite_id'] ?? 0) : 0,
                'inviteAt' => isset($byUser[$id]) ? (string) ($byUser[$id]['invite_at'] ?? '') : '',
                'inviteExpired' => isset($byUser[$id]) && !empty($byUser[$id]['invite_expired']),
                'source' => isset($byUser[$id]) ? (string) ($byUser[$id]['source_label'] ?? '') : '',
                'lastActivity' => isset($byUser[$id]) ? (string) ($byUser[$id]['last_activity'] ?? '') : '',
                'seniorityDays' => (int) ($row['seniority_days'] ?? 0),
                'seniority' => trim((string) ($row['seniority_label'] ?? '')),
                'presence' => (int) ($row['presence_score'] ?? 0),
                'availability' => (int) ($row['availability_score'] ?? 0),
                'completion' => (int) ($row['completion_score'] ?? 0),
                'qualCount' => (int) ($row['qualifications_count'] ?? 0),
                'qualSummary' => (string) ($row['qualifications_summary'] ?? '—'),
                'qualAlert' => trim((string) ($row['qualifications_alert'] ?? '')),
                'qualItems' => $items,
                'playSeconds' => (int) ($row['arma_playtime_seconds'] ?? 0),
                'pilotSeconds' => (int) ($row['pilot_seconds'] ?? 0),
                'ficheUrl' => effectifs_workspace_url('membres/' . $id),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $units lignes units avec assignment_path
     * @param array<int, array<string, mixed>> $codes code d'unité par id (UnitCodeService)
     * @param array<int, string> $commanders libellé du chef par id d'unité
     * @return list<array{id: int, parentId: int, name: string, path: string, code: string, commander: string, order: int}>
     */
    public static function units(array $units, array $codes, array $commanders): array
    {
        $ids = [];
        foreach ($units as $u) {
            $ids[(int) ($u['id'] ?? 0)] = true;
        }
        $out = [];
        foreach ($units as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $parent = (int) ($u['parent_id'] ?? 0);
            $out[] = [
                'id' => $id,
                'parentId' => isset($ids[$parent]) && $parent !== $id ? $parent : 0,
                'name' => trim((string) ($u['name'] ?? '')),
                'path' => trim((string) ($u['assignment_path'] ?? ($u['name'] ?? ''))),
                'code' => trim((string) ($codes[$id]['code'] ?? '')),
                'commander' => trim((string) ($commanders[$id] ?? '')),
                'order' => (int) ($u['display_order'] ?? 0),
            ];
        }

        return $out;
    }
}
