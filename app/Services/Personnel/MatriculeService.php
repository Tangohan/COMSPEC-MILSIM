<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantMatriculeConfigRepository;

class MatriculeService
{
    public function __construct(
        private TenantMatriculeConfigRepository $configRepo,
        private PersonnelExtrasRepository $personnelExtrasRepo,
        private PersonnelProfileRepository $personnelProfileRepo
    ) {}

    /**
     * Génère le prochain matricule pour le tenant selon le format configuré.
     * Placeholders: {prefix}, {seq}, {seq:N} (N = padding avec zéros, ex. {seq:5} => 00042)
     */
    public function generateNext(int $tenantId): string
    {
        $config = $this->configRepo->getOrCreate($tenantId);
        $seq = $this->configRepo->consumeNextNumber($tenantId);
        if ($seq === null) {
            $seq = 1;
        }
        return $this->format($config['format_pattern'], $config['prefix'], $seq);
    }

    public function format(string $pattern, string $prefix, int $seq): string
    {
        $out = $pattern;
        $out = str_replace('{prefix}', $prefix, $out);
        if (preg_match('/\{seq:(\d+)\}/', $pattern, $m)) {
            $pad = (int) $m[1];
            $out = preg_replace('/\{seq:\d+\}/', str_pad((string) $seq, $pad, '0', STR_PAD_LEFT), $out);
        } else {
            $out = str_replace('{seq}', (string) $seq, $out);
        }
        return $out;
    }

    /** Attribue un nouveau matricule à l'utilisateur si il n'en a pas encore. Retourne le matricule ou null. */
    public function assignNextForUser(int $userId, int $tenantId): ?string
    {
        $extras = $this->personnelExtrasRepo->getByUserId($userId);
        if ($extras && !empty(trim((string) ($extras['service_number'] ?? '')))) {
            return null;
        }
        $matricule = $this->generateNext($tenantId);
        $this->personnelExtrasRepo->updateServiceNumber($userId, $matricule);
        $this->personnelProfileRepo->updateMatricule($userId, $matricule);
        return $matricule;
    }
}
