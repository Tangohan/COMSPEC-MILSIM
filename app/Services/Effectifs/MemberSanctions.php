<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Mesures disciplinaires de la communauté vues depuis la fiche membre.
 *
 * Les données viennent du module existant « Restrictions membres » (moderation_actions, niveau
 * communauté) : avertissement, restriction d’activité, suspension, exclusion. Une mesure est
 * « en vigueur » tant qu’elle n’est ni levée ni arrivée à échéance.
 */
final class MemberSanctions
{
    public const TYPE_LABELS = [
        'warn' => 'Avertissement',
        'mute' => 'Restriction d’activité',
        'suspend' => 'Suspension',
        'ban' => 'Exclusion',
    ];

    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const LIFTED = 'lifted';

    public const STATUS_LABELS = [
        self::ACTIVE => 'En vigueur',
        self::EXPIRED => 'Échue',
        self::LIFTED => 'Levée',
    ];

    /**
     * @param list<array<string, mixed>> $rows lignes moderation_actions (périmètre communauté)
     * @return array{items: list<array{id:int,type:string,label:string,reason:string,date:string,expires_at:string,actor:string,status:string,status_label:string,lifted_at:string,lifted_by:string}>, active:int, total:int}
     */
    public static function summarize(array $rows, \DateTimeImmutable $now): array
    {
        $items = [];
        $active = 0;
        foreach ($rows as $row) {
            $type = (string) ($row['action_type'] ?? 'warn');
            $revoked = trim((string) ($row['revoked_at'] ?? ''));
            $expires = trim((string) ($row['expires_at'] ?? ''));
            if ($revoked !== '') {
                $status = self::LIFTED;
            } elseif ($expires !== '' && ($ts = strtotime($expires)) !== false && $ts <= $now->getTimestamp()) {
                $status = self::EXPIRED;
            } else {
                $status = self::ACTIVE;
                $active++;
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'type' => $type,
                'label' => self::TYPE_LABELS[$type] ?? 'Mesure disciplinaire',
                'reason' => trim((string) ($row['reason'] ?? '')),
                'date' => (string) ($row['created_at'] ?? ''),
                'expires_at' => $expires,
                'actor' => trim((string) ($row['actor_display_name'] ?? '')),
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status],
                'lifted_at' => $revoked,
                'lifted_by' => trim((string) ($row['revoker_display_name'] ?? '')),
            ];
        }

        return ['items' => $items, 'active' => $active, 'total' => count($items)];
    }
}
