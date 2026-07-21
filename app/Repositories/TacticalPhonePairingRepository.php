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

    private function tableExists(): bool
    {
        static $exists = null;
        if ($exists === null) {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'tactical_phone_pairings'");
            $exists = (bool) ($stmt && $stmt->fetchColumn());
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
        if (!$this->tableExists()) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $code = $this->generateUniqueCode($tenantId);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);
        $stmt = $this->pdo->prepare(
            'INSERT INTO tactical_phone_pairings (tenant_id, token, code, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $token, $code, $expiresAt]);

        return ['token' => $token, 'code' => $code, 'expires_at' => $expiresAt];
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
                'SELECT 1 FROM tactical_phone_pairings WHERE tenant_id = ? AND code = ? AND expires_at > NOW() LIMIT 1'
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
            'SELECT * FROM tactical_phone_pairings WHERE token = ? AND expires_at > NOW() LIMIT 1'
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
            'SELECT * FROM tactical_phone_pairings WHERE code = ? AND expires_at > NOW() LIMIT 1'
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
        $stmt = $this->pdo->prepare(
            'UPDATE tactical_phone_pairings SET paired_at = NOW() WHERE token = ? AND paired_at IS NULL'
        );
        $stmt->execute([trim($token)]);
    }
}
