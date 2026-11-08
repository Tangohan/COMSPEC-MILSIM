<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\AtakAdminControlRepository;

/**
 * Message imposé aux joueurs, relevé par l'ATAK de chacun à sa prochaine présence en jeu (AdminControl.Poll).
 * Un message par joueur, même s'il a plusieurs ATAK : utilisé par le parc ATAK (message imposé, message global)
 * et par le centre de diffusion du back-office. Chaque message laisse une ligne dans le journal du parc.
 */
final class AtakPlayerMessageSender
{
    public function __construct(private AtakAdminControlRepository $fleet)
    {
    }

    /**
     * @param list<array<string, mixed>> $terminals fiches ATAK (tenant_id, user_id, steam_id, terminal_uid)
     * @param array{title: string, body: string, level: string, require_ack: bool, expires_at: ?string, broadcast_key?: ?string} $message
     * @return int messages enregistrés
     */
    public function send(array $terminals, array $message, ?int $actorId, string $actorLabel): int
    {
        $done = 0;
        $seen = [];
        foreach ($terminals as $t) {
            if ((int) ($t['tenant_id'] ?? 0) < 1) {
                continue;
            }
            $who = self::playerKey($t);
            if (isset($seen[$who])) {
                continue;
            }
            $seen[$who] = true;
            $id = $this->fleet->createMessage([
                'tenant_id' => (int) $t['tenant_id'],
                'user_id' => ((int) ($t['user_id'] ?? 0)) ?: null,
                'steam_uid' => trim((string) ($t['steam_id'] ?? '')) ?: null,
                'terminal_uid' => (string) ($t['terminal_uid'] ?? '') !== '' ? (string) $t['terminal_uid'] : null,
                'title' => $message['title'],
                'body' => $message['body'],
                'level' => $message['level'],
                'require_ack' => $message['require_ack'],
                'expires_at' => $message['expires_at'],
                'actor_id' => $actorId,
                'broadcast_key' => $message['broadcast_key'] ?? null,
            ]);
            if ($id > 0) {
                $done++;
                $this->fleet->log($t, 'message', $message['title'], $actorId, $actorLabel);
            }
        }

        return $done;
    }

    /** Un joueur : son compte, sinon son ATAK, sinon son Steam. */
    public static function playerKey(array $terminal): string
    {
        return (int) ($terminal['tenant_id'] ?? 0) . ':' . ((int) ($terminal['user_id'] ?? 0) ?: ((string) ($terminal['terminal_uid'] ?? '') ?: (string) ($terminal['steam_id'] ?? '')));
    }
}
