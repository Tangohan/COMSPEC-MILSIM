<?php

declare(strict_types=1);

namespace App\Services\DemoNda;

use App\Core\Session;
use App\Repositories\DemoNdaVisitRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Support\MaintenanceGuard;
use DateTimeImmutable;

final class DemoNdaGateService
{
    public const GATE_PATH = '/acces-demonstration';

    private const SETTING_ACCESS_CODE = 'demo_nda.access_code';
    private const SETTING_BYPASS_IPS = 'demo_nda.bypass_ips';
    private const SESSION_TOKEN = 'demo_nda_access_token';
    private const SESSION_EXPIRES = 'demo_nda_expires_at';
    private const SESSION_INTENDED = 'demo_nda_intended';

    public function __construct(
        private DemoNdaVisitRepository $visits,
        private PlatformSettingsRepository $settings,
    ) {}

    public function isEnabled(): bool
    {
        if (!filter_var((string) env('DEMO_NDA_GATE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // Sans code configuré : ne jamais enfermer le site (évite le lock-out admin).
        return $this->peekAccessCode() !== '';
    }

    public function ttlHours(): int
    {
        $h = (int) env('DEMO_NDA_GATE_TTL_HOURS', 3);

        return max(1, min(168, $h));
    }

    public function clientIp(): string
    {
        return MaintenanceGuard::resolveClientIp();
    }

    /**
     * Déblocage d’urgence via ?demo_nda_unlock=CLE (DEMO_NDA_GATE_UNLOCK_KEY).
     * Réouvre la visite de l’IP courante même si la gate est active.
     */
    public function tryUnlockFromRequest(\App\Core\Request $request): bool
    {
        $key = trim((string) env('DEMO_NDA_GATE_UNLOCK_KEY', ''));
        if ($key === '') {
            return false;
        }
        $provided = trim((string) $request->query('demo_nda_unlock', ''));
        if ($provided === '' || !hash_equals($key, $provided)) {
            return false;
        }

        $ip = $this->clientIp();
        if ($ip === '' || $ip === '0.0.0.0') {
            return false;
        }

        $visit = $this->visits->findByIp($ip);
        if ($visit !== null) {
            $this->resetVisit((int) $visit['id']);
        }
        $this->clearSessionGrant();

        return true;
    }

    public function isPublicAssetPath(string $path): bool
    {
        if ($path === '/manifest.webmanifest' || $path === '/sw.js') {
            return true;
        }
        $prefixes = [
            '/assets/',
            '/favicon',
            '/robots.txt',
        ];
        foreach ($prefixes as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isExemptPath(string $path): bool
    {
        if ($this->isPublicAssetPath($path) || $path === self::GATE_PATH) {
            return true;
        }
        $exact = [
            '/api/stripe/webhook',
            '/api/health',
        ];
        if (in_array($path, $exact, true)) {
            return true;
        }

        return str_starts_with($path, '/calendrier/abonnement/');
    }

    public function isGatePath(string $path): bool
    {
        return $path === self::GATE_PATH;
    }

    /**
     * @return list<string>
     */
    public function envBypassIps(): array
    {
        return $this->parseIpList((string) env('DEMO_NDA_GATE_BYPASS_IPS', ''));
    }

    /**
     * @return list<string>
     */
    public function adminBypassIps(): array
    {
        return $this->parseIpList($this->settings->get(self::SETTING_BYPASS_IPS, ''));
    }

    /**
     * @return list<string>
     */
    public function allBypassIps(): array
    {
        return array_values(array_unique(array_merge($this->envBypassIps(), $this->adminBypassIps())));
    }

    public function isBypassIp(string $ip): bool
    {
        if ($ip === '' || $ip === '0.0.0.0') {
            return false;
        }

        return in_array($ip, $this->allBypassIps(), true);
    }

    /**
     * @param list<string> $ips
     */
    public function saveAdminBypassIps(array $ips): void
    {
        $clean = [];
        foreach ($ips as $ip) {
            $ip = trim((string) $ip);
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $clean[$ip] = $ip;
        }
        $this->settings->setMany([
            self::SETTING_BYPASS_IPS => implode(',', array_values($clean)),
        ]);
    }

    public function addAdminBypassIp(string $ip): bool
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $list = $this->adminBypassIps();
        if (!in_array($ip, $list, true)) {
            $list[] = $ip;
        }
        $this->saveAdminBypassIps($list);

        return true;
    }

    public function removeAdminBypassIp(string $ip): void
    {
        $ip = trim($ip);
        $list = array_values(array_filter(
            $this->adminBypassIps(),
            static fn (string $x): bool => $x !== $ip
        ));
        $this->saveAdminBypassIps($list);
    }

    public function envAccessCode(): string
    {
        return trim((string) env('DEMO_NDA_GATE_ACCESS_CODE', ''));
    }

    public function isAccessCodeFromEnv(): bool
    {
        return $this->envAccessCode() !== '';
    }

    /**
     * Code effectif : .env en priorité, sinon code stocké côté admin.
     */
    public function peekAccessCode(): string
    {
        $fromEnv = $this->envAccessCode();
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return trim($this->settings->get(self::SETTING_ACCESS_CODE, ''));
    }

    public function getAccessCode(): string
    {
        $code = $this->peekAccessCode();
        if ($code !== '') {
            return $code;
        }

        return $this->regenerateAccessCode();
    }

    /**
     * @throws \RuntimeException si le code est fixé dans le .env
     */
    public function regenerateAccessCode(): string
    {
        if ($this->isAccessCodeFromEnv()) {
            throw new \RuntimeException(
                'Le code est défini dans le fichier d’environnement (DEMO_NDA_GATE_ACCESS_CODE). Modifiez-le là-bas.'
            );
        }

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = 8;
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        // Format lisible à l’oral : XXXX-XXXX
        $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4);
        $this->settings->setMany([self::SETTING_ACCESS_CODE => $formatted]);

        return $formatted;
    }

    public function normalizeAccessCode(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        $raw = preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';

        return $raw;
    }

    public function accessCodeMatches(string $submitted): bool
    {
        $expected = $this->normalizeAccessCode($this->peekAccessCode());
        $got = $this->normalizeAccessCode($submitted);
        if ($expected === '' || $got === '') {
            return false;
        }

        return hash_equals($expected, $got);
    }

    /**
     * Enregistre l’IP au premier hit (hors assets / exemptions techniques).
     *
     * @return array<string, mixed>|null
     */
    public function registerFirstHit(string $ip, ?string $userAgent): ?array
    {
        if (!$this->visits->tableExists() || $ip === '') {
            return null;
        }
        $existing = $this->visits->findByIp($ip);
        if ($existing !== null) {
            return $this->refreshStatus($existing);
        }

        $now = new DateTimeImmutable('now');
        $claimExpires = $now->modify('+' . $this->ttlHours() . ' hours');

        return $this->visits->createPending(
            $ip,
            $now->format('Y-m-d H:i:s'),
            $claimExpires->format('Y-m-d H:i:s'),
            $userAgent
        );
    }

    /**
     * @param array<string, mixed> $visit
     * @return array<string, mixed>
     */
    public function refreshStatus(array $visit): array
    {
        $id = (int) ($visit['id'] ?? 0);
        $status = (string) ($visit['status'] ?? 'pending');
        if ($id < 1 || $status === 'expired') {
            return $visit;
        }

        $now = new DateTimeImmutable('now');

        if ($status === 'pending') {
            $claimExpires = $this->parseDate((string) ($visit['claim_expires_at'] ?? ''));
            if ($claimExpires !== null && $now > $claimExpires) {
                $this->visits->markExpired($id);
                $visit['status'] = 'expired';
                $this->clearSessionGrant();

                return $visit;
            }
        }

        if ($status === 'granted') {
            $sessionExpires = $this->parseDate((string) ($visit['session_expires_at'] ?? ''));
            if ($sessionExpires !== null && $now > $sessionExpires) {
                $this->visits->markExpired($id);
                $visit['status'] = 'expired';
                $this->clearSessionGrant();

                return $visit;
            }
        }

        return $visit;
    }

    /**
     * @param array<string, mixed> $visit
     */
    public function hasValidSession(array $visit): bool
    {
        if ((string) ($visit['status'] ?? '') !== 'granted') {
            return false;
        }
        $token = (string) Session::get(self::SESSION_TOKEN, '');
        $expiresRaw = (string) Session::get(self::SESSION_EXPIRES, '');
        $hash = (string) ($visit['access_token_hash'] ?? '');
        if ($token === '' || $hash === '' || $expiresRaw === '') {
            return false;
        }
        $expires = $this->parseDate($expiresRaw);
        if ($expires === null || new DateTimeImmutable('now') > $expires) {
            return false;
        }
        $dbExpires = $this->parseDate((string) ($visit['session_expires_at'] ?? ''));
        if ($dbExpires === null || new DateTimeImmutable('now') > $dbExpires) {
            return false;
        }

        return hash_equals($hash, hash('sha256', $token));
    }

    /**
     * Clôture définitive d’une visite (fenêtre écoulée ou session invalide).
     *
     * @param array<string, mixed> $visit
     * @return array<string, mixed>
     */
    public function expireVisit(array $visit): array
    {
        $id = (int) ($visit['id'] ?? 0);
        if ($id > 0) {
            $this->visits->markExpired($id);
        }
        $this->clearSessionGrant();
        $visit['status'] = 'expired';

        return $visit;
    }

    /**
     * @param array<string, mixed> $visit
     */
    public function grantAccess(array $visit, string $submittedCode): bool
    {
        $visit = $this->refreshStatus($visit);
        if ((string) ($visit['status'] ?? '') !== 'pending') {
            return false;
        }
        if (!$this->accessCodeMatches($submittedCode)) {
            return false;
        }

        $now = new DateTimeImmutable('now');
        $sessionExpires = $now->modify('+' . $this->ttlHours() . ' hours');
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->visits->markGranted(
            (int) $visit['id'],
            $now->format('Y-m-d H:i:s'),
            $sessionExpires->format('Y-m-d H:i:s'),
            $hash
        );

        Session::regenerate();
        Session::set(self::SESSION_TOKEN, $token);
        Session::set(self::SESSION_EXPIRES, $sessionExpires->format('Y-m-d H:i:s'));

        return true;
    }

    public function clearSessionGrant(): void
    {
        Session::forgetMany([self::SESSION_TOKEN, self::SESSION_EXPIRES]);
    }

    /**
     * Fin de session démo active (null si pas de session / bypass / gate off).
     */
    public function activeSessionExpiresAt(): ?DateTimeImmutable
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $ip = $this->clientIp();
        if ($this->isBypassIp($ip)) {
            return null;
        }
        $expiresRaw = (string) Session::get(self::SESSION_EXPIRES, '');
        $token = (string) Session::get(self::SESSION_TOKEN, '');
        if ($expiresRaw === '' || $token === '') {
            return null;
        }
        $expires = $this->parseDate($expiresRaw);
        if ($expires === null || new DateTimeImmutable('now') >= $expires) {
            return null;
        }
        $visit = $this->visits->findByIp($ip);
        if ($visit === null) {
            return null;
        }
        $visit = $this->refreshStatus($visit);
        if (!$this->hasValidSession($visit)) {
            return null;
        }

        return $expires;
    }

    /**
     * Secondes restantes pour le widget (null si rien à afficher).
     */
    public function activeSessionRemainingSeconds(): ?int
    {
        $expires = $this->activeSessionExpiresAt();
        if ($expires === null) {
            return null;
        }
        $sec = $expires->getTimestamp() - time();

        return $sec > 0 ? $sec : null;
    }

    public function rememberIntendedPath(string $path): void
    {
        if ($path === '' || $path === self::GATE_PATH || $this->isExemptPath($path)) {
            return;
        }
        Session::set(self::SESSION_INTENDED, $path);
    }

    public function consumeIntendedPath(): string
    {
        $path = (string) Session::get(self::SESSION_INTENDED, '/');
        Session::forget(self::SESSION_INTENDED);
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentVisits(int $limit = 80): array
    {
        $rows = $this->visits->listRecent($limit);
        foreach ($rows as $i => $row) {
            $rows[$i] = $this->refreshStatus($row);
        }

        return $rows;
    }

    public function resetVisit(int $id): bool
    {
        $row = $this->visits->findById($id);
        if ($row === null) {
            return false;
        }
        $now = new DateTimeImmutable('now');
        $claimExpires = $now->modify('+' . $this->ttlHours() . ' hours');
        $this->visits->resetToPending(
            $id,
            $now->format('Y-m-d H:i:s'),
            $claimExpires->format('Y-m-d H:i:s')
        );

        return true;
    }

    /**
     * @return list<string>
     */
    private function parseIpList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $parts = $decoded;
        } else {
            $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ($parts as $part) {
            $ip = trim((string) $part);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $out[$ip] = $ip;
            }
        }

        return array_values($out);
    }

    private function parseDate(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
