<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Méta d'un avis de compromission envoyé à l'ATAK du joueur (AdminControl.Poll, 7e colonne de COMSPEC Link) :
 * « source=admin;state=captured;revoked=2;ref=E12;terminal=…;by=… ». L'ATAK natif s'en sert pour son mode
 * « terminal compromis » (origine manuelle ou automatique, état, certificats révoqués).
 * L'événement retenu est le plus récent de l'historique de l'ATAK enregistré au plus deux minutes après l'avis
 * (l'administration inscrit l'événement avant l'avis, le poste de commandement juste après).
 */
final class AtakCompromiseNoticeMeta
{
    private const KINDS = ['compromise', 'compromise_cleared'];

    /**
     * @param array<string, mixed> $message ligne atak_admin_messages (kind, terminal_uid, created_at)
     * @param list<array<string, mixed>> $events atak_compromise_events de l'ATAK, plus récents d'abord
     */
    public static function build(array $message, array $events): string
    {
        if (!in_array((string) ($message['kind'] ?? ''), self::KINDS, true)) {
            return '';
        }
        $at = UtcDateTime::timestamp($message['created_at'] ?? null);
        $event = null;
        foreach ($events as $e) {
            $t = UtcDateTime::timestamp($e['created_at'] ?? null);
            if ($at === null || $t === null || $t <= $at + 120) {
                $event = $e;
                break;
            }
        }
        $pairs = [
            'terminal' => (string) ($message['terminal_uid'] ?? ''),
        ];
        if ($event !== null) {
            $ids = array_filter(explode(',', (string) ($event['certificate_ids'] ?? '')), static fn (string $id): bool => (int) $id > 0);
            $pairs += [
                'source' => (string) ($event['source'] ?? ''),
                'state' => (string) ($event['state'] ?? ''),
                'revoked' => (string) count($ids),
                'ref' => 'E' . (int) ($event['id'] ?? 0),
                'by' => (string) ($event['actor_label'] ?? ''),
            ];
        }
        $out = [];
        foreach ($pairs as $k => $v) {
            // Séparateurs du format (et ceux que COMSPEC Link remplace) retirés des valeurs.
            $v = trim(str_replace([';', '=', "\t", "\r", "\n", '|'], ' ', $v));
            if ($v !== '' && $v !== 'E0') {
                $out[] = $k . '=' . mb_substr($v, 0, 80);
            }
        }

        return implode(';', $out);
    }
}
