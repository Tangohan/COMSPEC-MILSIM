<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Session;

/**
 * Adresses e-mail : visibles en clair uniquement pour l’administration du site
 * (`admin.system`), jamais pour un administrateur de communauté.
 */
final class EmailPrivacy
{
    private const EMAIL_PATTERN = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/';

    /** Clés de vue à ne pas parcourir (noms de templates, HTML brut). */
    private const SKIP_KEYS = [
        'content' => true,
        'title' => true,
        'html' => true,
        'body' => true,
        'csrf' => true,
        '_csrf_token' => true,
        'old' => true,
        'input' => true,
        'form' => true,
        'errors' => true,
    ];

    public static function viewerCanSeeEmails(): bool
    {
        try {
            return Gate::getInstance()->allows('admin.system');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function mask(string $email): string
    {
        $email = trim($email);
        if (RosterOnlyMember::isRosterEmail($email)) {
            return RosterOnlyMember::label();
        }
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return $email === '' ? '—' : $email;
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $n = strlen($local);
        $keep = min(2, $n);
        $prefix = $keep > 0 ? substr($local, 0, $keep) : '';

        return $prefix . '***@' . $domain;
    }

    public static function display(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return '—';
        }
        if (RosterOnlyMember::isRosterEmail($email)) {
            return RosterOnlyMember::label();
        }
        if (self::viewerCanSeeEmails()) {
            return $email;
        }

        return '—';
    }

    /**
     * Retire toute adresse e-mail d’une chaîne (libellés du type « Nom · prenom@exemple.fr »).
     * Sans effet pour l’administration du site.
     */
    public static function scrub(string $text): string
    {
        if (!str_contains($text, '@') || self::viewerCanSeeEmails()) {
            return $text;
        }

        return self::scrubAlways($text);
    }

    private static function scrubAlways(string $text): string
    {
        if (!str_contains($text, '@')) {
            return $text;
        }
        $out = preg_replace(self::EMAIL_PATTERN, '', $text);
        $out = is_string($out) ? $out : $text;

        // Reste « Nom · » ou « Nom () » après retrait.
        return trim((string) preg_replace(['/\s*[·—\-–]\s*$/u', '/\(\s*\)/u', '/\s{2,}/u'], ['', '', ' '], $out));
    }

    /**
     * Nettoie une réponse JSON destinée à un navigateur : aucune adresse e-mail hors administration
     * du site, sauf pour le titulaire sur ses propres routes (compte, préférences, connexion).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function maskJsonData(array $data): array
    {
        if (self::viewerCanSeeEmails()) {
            return $data;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        if (preg_match('#/(api/)?(me|account|auth|login|register|register-|password|verify|mfa|enlistment|invitations?|invite|newsletter)(/|$|-)#i', $path) === 1) {
            return $data;
        }
        $viewerId = 0;
        try {
            $viewerId = (int) Session::get('user_id');
        } catch (\Throwable) {
            $viewerId = 0;
        }

        return self::walk($data, $viewerId);
    }

    /**
     * Masque les e-mails dans les données passées aux vues HTML.
     * Le titulaire du compte continue de voir la sienne (réglages, fiche personnelle).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function maskViewData(array $data): array
    {
        if (self::viewerCanSeeEmails()) {
            return $data;
        }
        $viewerId = 0;
        $content = (string) ($data['content'] ?? '');
        $allowOwnEmail = str_starts_with($content, 'account.') || str_starts_with($content, 'personnel.');
        try {
            $viewerId = (int) Session::get('user_id');
        } catch (\Throwable) {
            $viewerId = 0;
        }

        return self::walk($data, $allowOwnEmail ? $viewerId : 0);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function walk(mixed $value, int $viewerId, string $key = ''): mixed
    {
        if (!is_array($value)) {
            if (is_string($value) && $key !== '' && !isset(self::SKIP_KEYS[$key]) && str_contains($value, '@')) {
                return self::scrubAlways($value);
            }

            return $value;
        }

        $skipEmailKeys = $viewerId > 0 && self::isOwnPersonRecord($value, $viewerId);
        $out = [];
        foreach ($value as $k => $child) {
            $kStr = is_string($k) || is_int($k) ? (string) $k : '';
            if (isset(self::SKIP_KEYS[$kStr])) {
                $out[$k] = $child;
                continue;
            }
            if ($skipEmailKeys && is_string($child)) {
                $out[$k] = $child;
                continue;
            }
            if (!$skipEmailKeys && is_string($child) && self::shouldMaskKey($kStr, $value) && self::stringIsEmail($child)) {
                $out[$k] = '';
                continue;
            }
            // Champ « email » hors fiche de personne : valeur saisie par l’utilisateur (préremplissage de formulaire).
            if (is_string($child) && in_array(strtolower($kStr), ['email', 'mail'], true)) {
                $out[$k] = $child;
                continue;
            }
            $out[$k] = self::walk($child, $viewerId, $kStr);
        }

        return $out;
    }

    /**
     * @param array<mixed> $row
     */
    private static function isOwnPersonRecord(array $row, int $viewerId): bool
    {
        if ($viewerId < 1) {
            return false;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id === $viewerId) {
            return true;
        }

        return (int) ($row['user_id'] ?? 0) === $viewerId;
    }

    /**
     * @param array<mixed> $siblings
     */
    private static function shouldMaskKey(string $key, array $siblings): bool
    {
        $k = strtolower($key);
        if ($k === 'email_confirmation' || str_contains($k, 'confirm') || str_contains($k, 'masked')
            || str_contains($k, 'verified') || str_contains($k, 'otp') || str_contains($k, 'enabled')
            || str_contains($k, 'count') || str_contains($k, 'mask_email') || str_contains($k, 'share_email')) {
            return false;
        }
        if ($k === 'email' || str_ends_with($k, '_email') || $k === 'mail') {
            if ($k === 'email') {
                return self::looksLikePersonRecord($siblings);
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<mixed> $row
     */
    private static function looksLikePersonRecord(array $row): bool
    {
        return isset($row['id']) || isset($row['user_id']) || isset($row['display_name'])
            || isset($row['callsign']) || isset($row['status']) || isset($row['tenant_id']);
    }

    private static function stringIsEmail(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || !str_contains($value, '@') || str_contains($value, ' ')) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
