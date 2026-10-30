<?php

declare(strict_types=1);

namespace App\Support\Ops;

/**
 * Ticket à usage unique qui ouvre la console d'exécution des migrations (/run-migrations.php)
 * depuis la console d'exploitation, sans le mot de passe de secours.
 *
 * Le ticket est émis par un administrateur du site vérifié (double facteur) après un POST protégé
 * par CSRF. Il est rangé haché dans storage/cache/ops-tickets, vit 90 secondes et se consomme une fois.
 * Pas de dépendance à l'application : bootstrap/migrations_web_ui.php le charge directement.
 */
final class OpsMigrationTicket
{
    public const TTL_SECONDS = 90;

    /** @var list<string> */
    public const ACTIONS = ['run', 'run_step'];

    public static function dir(string $root): string
    {
        return rtrim($root, '/\\') . '/storage/cache/ops-tickets';
    }

    /**
     * @param array{action: string, step?: string, user_id?: int, snapshot?: string} $payload
     */
    public static function mint(string $root, array $payload, ?int $now = null): string
    {
        $now ??= time();
        $action = (string) ($payload['action'] ?? '');
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Action de migration inconnue.');
        }
        $dir = self::dir($root);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier storage/cache/ops-tickets impossible à créer.');
        }
        self::sweep($root, $now);
        $ticket = bin2hex(random_bytes(24));
        $payload['expires_at'] = $now + self::TTL_SECONDS;
        $payload['minted_at'] = $now;
        if (@file_put_contents(self::path($root, $ticket), json_encode($payload), LOCK_EX) === false) {
            throw new \RuntimeException('Ticket de migration impossible à écrire.');
        }

        return $ticket;
    }

    /**
     * Lit puis détruit le ticket. Null s'il est inconnu, déjà utilisé ou expiré.
     *
     * @return array<string, mixed>|null
     */
    public static function consume(string $root, string $ticket, ?int $now = null): ?array
    {
        $now ??= time();
        if (preg_match('/^[a-f0-9]{48}$/', $ticket) !== 1) {
            return null;
        }
        $path = self::path($root, $ticket);
        if (!is_file($path)) {
            return null;
        }
        $raw = (string) @file_get_contents($path);
        @unlink($path);
        if (is_file($path)) {
            // Impossible d'effacer : on refuse plutôt que de laisser un ticket rejouable.
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (int) ($data['expires_at'] ?? 0) < $now) {
            return null;
        }
        if (!in_array((string) ($data['action'] ?? ''), self::ACTIONS, true)) {
            return null;
        }

        return $data;
    }

    private static function path(string $root, string $ticket): string
    {
        return self::dir($root) . '/' . hash('sha256', $ticket) . '.json';
    }

    private static function sweep(string $root, int $now): void
    {
        foreach (glob(self::dir($root) . '/*.json') ?: [] as $file) {
            if (@filemtime($file) < $now - self::TTL_SECONDS * 4) {
                @unlink($file);
            }
        }
    }
}
