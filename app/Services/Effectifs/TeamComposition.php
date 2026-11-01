<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Composition d’une unité de l’ORBAT : postes prévus, postes pourvus, postes vacants.
 *
 * Les postes sont ceux de l’unité (tableau d’effectifs) avec leur effectif prévu. Une place est
 * pourvue par, dans l’ordre :
 * 1. un titulaire ou un intérimaire désigné sur le poste ;
 * 2. à défaut, un membre affecté à l’unité qui exerce l’emploi rattaché au poste.
 * Une même personne n’occupe qu’une place. Les postes gelés ne sont pas comptés.
 * Les membres de l’unité sans poste sont listés à part.
 */
final class TeamComposition
{
    /**
     * @param list<array<string, mixed>> $billets postes de l’unité (id, title, status, authorized, job_role_id, holders)
     * @param list<array{user_id:int,label:string,job_role_ids:list<int>,job_label?:string}> $unitMembers membres affectés à l’unité
     * @return array{
     *   authorized:int, filled:int, vacant:int,
     *   seats: list<array{billet_id:int,title:string,user_id:int,label:string,is_me:bool,vacant:bool}>,
     *   others: list<array{user_id:int,label:string,job_label:string,is_me:bool}>
     * }
     */
    public static function compose(array $billets, array $unitMembers, int $memberId): array
    {
        $placed = [];
        $seats = [];
        $authorized = 0;
        $filled = 0;
        foreach ($billets as $b) {
            if (strtolower((string) ($b['status'] ?? 'active')) === 'frozen') {
                continue;
            }
            $billetId = (int) ($b['id'] ?? 0);
            $title = trim((string) ($b['title'] ?? '')) ?: 'Poste';
            $slots = max(1, (int) ($b['authorized'] ?? $b['authorized_slots'] ?? 1));
            $occupants = [];
            foreach (is_array($b['holders'] ?? null) ? $b['holders'] : [] as $h) {
                $occ = strtolower((string) ($h['occupancy_type'] ?? 'primary'));
                $uid = (int) ($h['user_id'] ?? 0);
                if ($uid < 1 || isset($placed[$uid]) || !in_array($occ, ['primary', 'acting'], true) || count($occupants) >= $slots) {
                    continue;
                }
                $occupants[] = ['user_id' => $uid, 'label' => trim((string) ($h['label'] ?? '')) ?: 'Membre'];
                $placed[$uid] = true;
            }
            $jobRoleId = (int) ($b['job_role_id'] ?? 0);
            if ($jobRoleId > 0) {
                foreach ($unitMembers as $m) {
                    $uid = (int) ($m['user_id'] ?? 0);
                    if (count($occupants) >= $slots) {
                        break;
                    }
                    if ($uid < 1 || isset($placed[$uid]) || !in_array($jobRoleId, array_map('intval', $m['job_role_ids'] ?? []), true)) {
                        continue;
                    }
                    $occupants[] = ['user_id' => $uid, 'label' => trim((string) ($m['label'] ?? '')) ?: 'Membre'];
                    $placed[$uid] = true;
                }
            }
            $authorized += $slots;
            $filled += count($occupants);
            foreach ($occupants as $o) {
                $seats[] = ['billet_id' => $billetId, 'title' => $title, 'user_id' => $o['user_id'], 'label' => $o['label'], 'is_me' => $o['user_id'] === $memberId, 'vacant' => false];
            }
            for ($i = count($occupants); $i < $slots; $i++) {
                $seats[] = ['billet_id' => $billetId, 'title' => $title, 'user_id' => 0, 'label' => 'Poste vacant', 'is_me' => false, 'vacant' => true];
            }
        }

        $others = [];
        foreach ($unitMembers as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            if ($uid < 1 || isset($placed[$uid])) {
                continue;
            }
            $placed[$uid] = true;
            $others[] = [
                'user_id' => $uid,
                'label' => trim((string) ($m['label'] ?? '')) ?: 'Membre',
                'job_label' => trim((string) ($m['job_label'] ?? '')),
                'is_me' => $uid === $memberId,
            ];
        }

        return [
            'authorized' => $authorized,
            'filled' => $filled,
            'vacant' => max(0, $authorized - $filled),
            'seats' => $seats,
            'others' => $others,
        ];
    }
}
