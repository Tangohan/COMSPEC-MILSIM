<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Connexion téléphone (module ATAK, inspiré de cTab) : token/QR généré en jeu, code court
 * lisible en secours, consommés par un navigateur mobile sans compte pour consulter la
 * diapositive de briefing en cours (voir AtakPhoneConnectController).
 */
class TacticalPhonePairingRepository
{
    private const TTL_MINUTES = 15;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * False tant que la migration bootstrap/tactical_phone_pairing_migration.php n’a pas été jouée.
     */
    public function isReady(): bool
    {
        return $this->tableExists();
    }

    private function tableExists(): bool
    {
        static $exists = null;
        if ($exists === true) {
            return true;
        }
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'tactical_phone_pairings'");
            $exists = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            $exists = false;
        }

        return $exists;
    }

    /**
     * Crée un nouveau pairing (token + code), expirant après TTL_MINUTES.
     *
     * @return array{token: string, code: string, expires_at: string}|null
     */
    public function create(int $tenantId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return null;
        }
        try {
            $token = bin2hex(random_bytes(16));
            $code = $this->generateUniqueCode($tenantId);
            $ttl = (int) self::TTL_MINUTES;
            // UTC_TIMESTAMP() : même piège que game_link (NOW() hébergeur ≠ PHP UTC).
            $stmt = $this->pdo->prepare(
                "INSERT INTO tactical_phone_pairings (tenant_id, token, code, expires_at, created_at)
                 VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$ttl} MINUTE), UTC_TIMESTAMP())"
            );
            $stmt->execute([$tenantId, $token, $code]);

            $read = $this->pdo->prepare(
                'SELECT token, code, expires_at FROM tactical_phone_pairings WHERE token = ? LIMIT 1'
            );
            $read->execute([$token]);
            $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'token' => (string) $row['token'],
                'code' => (string) $row['code'],
                'expires_at' => (string) $row['expires_at'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateUniqueCode(int $tenantId): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM tactical_phone_pairings WHERE tenant_id = ? AND code = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1'
            );
            $stmt->execute([$tenantId, $code]);
            if (!$stmt->fetchColumn()) {
                return $code;
            }
        }

        return strtoupper(bin2hex(random_bytes(3)));
    }

    public function findValidByToken(string $token): ?array
    {
        if (!$this->tableExists() || trim($token) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tactical_phone_pairings WHERE token = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1'
        );
        $stmt->execute([trim($token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Saisie manuelle du code (sans QR) : le téléphone n’a pas de contexte tenant, donc
     * recherche globale — collision quasi nulle vu l’alphabet (33^6) sur le seul jeu de
     * codes valides à un instant donné.
     */
    public function findValidByCode(string $code): ?array
    {
        if (!$this->tableExists() || trim($code) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tactical_phone_pairings WHERE code = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1'
        );
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markPaired(string $token): void
    {
        if (!$this->tableExists()) {
            return;
        }
        // Prolonge la fenêtre de validité pour laisser le temps du briefing (navigation + commentaires).
        $stmt = $this->pdo->prepare(
            'UPDATE tactical_phone_pairings
             SET paired_at = COALESCE(paired_at, UTC_TIMESTAMP()),
                 expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 120 MINUTE)
             WHERE token = ?'
        );
        $stmt->execute([trim($token)]);
    }

    /**
     * Pairing encore utilisable pour le briefing (non expiré), après scan ou saisie de code.
     *
     * @return array<string, mixed>|null
     */
    public function findBriefingSessionByToken(string $token): ?array
    {
        return $this->findValidByToken($token);
    }

    /**
     * Dernière liaison téléphone réussie pour la communauté (paired_at renseigné).
     *
     * @return array{paired_at: string, code: string}|null
     */
    public function latestPairedForTenant(int $tenantId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT paired_at, code FROM tactical_phone_pairings
             WHERE tenant_id = ? AND paired_at IS NOT NULL
             ORDER BY paired_at DESC
             LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['paired_at'])) {
            return null;
        }

        return [
            'paired_at' => (string) $row['paired_at'],
            'code' => (string) ($row['code'] ?? ''),
        ];
    }

    /**
     * Pairing par token (même expiré) — pour le statut après scan, limité au tenant appelant.
     *
     * @return array<string, mixed>|null
     */
    public function findByTokenForTenant(string $token, int $tenantId): ?array
    {
        if (!$this->tableExists() || trim($token) === '' || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tactical_phone_pairings WHERE token = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([trim($token), $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
