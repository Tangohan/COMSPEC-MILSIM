<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\TenantAdminSettingsRepository;
use App\Repositories\TenantRepository;

/**
 * Première connexion d'un nouveau membre : tant que son jeu n'est pas relié (aucun compte Steam sur le compte),
 * les pages du site renvoient vers /lier, où il saisit le code affiché par ATAK dans Arma.
 *
 * Réglage de communauté `atak_defaults.require_game_link_first_login` (activé par défaut), modifiable dans
 * Configuration ATAK › Connexion en jeu. Ne concerne que les comptes créés à partir de NEW_MEMBER_SINCE :
 * les membres déjà inscrits ne sont pas bloqués. Les administrateurs de la communauté et de la plateforme
 * ne sont jamais retenus, pour qu'aucune communauté ne se retrouve sans accès.
 */
final class FirstLoginGameLinkGate
{
    /** Comptes créés avant cette date (UTC) : déjà membres, jamais retenus. */
    public const NEW_MEMBER_SINCE = '2026-10-27 00:00:00';

    /** Session : « user_id:tenant_id » déjà vérifié et libre pour toute la session. */
    public const SESSION_CLEAR = 'game_link_gate_clear';
    /** Session : la page /lier s'affiche comme étape obligatoire. */
    public const SESSION_REQUIRED = 'game_link_gate_required';
    /** Session : page demandée avant la redirection, rouverte une fois le jeu relié. */
    public const SESSION_AFTER = 'game_link_gate_after';

    /** Pages ouvertes pendant l'étape : liaison, aide à l'installation du mod, langue, données, déconnexion. */
    private const OPEN_PREFIXES = [
        '/lier',
        '/logout',
        '/locale/',
        '/login',
        '/legal',
        '/cgu',
        '/mentions-legales',
        '/account/donnees',
        '/atak/mod',
        '/atak/tuto',
        '/atak/premiere-liaison',
        '/community/switch',
        '/api/',
        '/assets/',
    ];

    public function __construct(
        private ?TenantAdminSettingsRepository $settings = null,
        private ?TenantRepository $tenants = null,
    ) {
    }

    public static function pathIsOpen(string $path): bool
    {
        foreach (self::OPEN_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le membre doit-il relier son jeu avant d'utiliser le site ?
     *
     * @param array<string, mixed> $user ligne users (steam_id, created_at)
     */
    public function mustLink(array $user, int $tenantId): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return false;
        }
        if (trim((string) ($user['steam_id'] ?? '')) !== '') {
            return false;
        }
        if (!self::isNewMember($user)) {
            return false;
        }
        if (self::isStaff()) {
            return false;
        }

        return $this->enabledForTenant($tenantId);
    }

    /** @param array<string, mixed> $user */
    public static function isNewMember(array $user): bool
    {
        $created = strtotime((string) ($user['created_at'] ?? '') . ' UTC');
        if ($created === false) {
            return false;
        }

        return $created >= (int) strtotime(self::NEW_MEMBER_SINCE . ' UTC');
    }

    private static function isStaff(): bool
    {
        $gate = Gate::getInstance();

        return $gate->isPlatformAdmin()
            || $gate->allows('admin.system')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
    }

    public function enabledForTenant(int $tenantId): bool
    {
        try {
            $this->tenants ??= new TenantRepository();
            $tenant = $this->tenants->findById($tenantId);
            // Communauté système (sans organisation) : rien à relier tant que le membre n'a pas rejoint une communauté.
            if (!is_array($tenant) || (string) ($tenant['slug'] ?? '') === 'default') {
                return false;
            }
            $this->settings ??= new TenantAdminSettingsRepository($this->tenants);
            $all = $this->settings->getForTenant($tenantId);
        } catch (\Throwable) {
            return false;
        }

        return !empty($all['atak_defaults']['require_game_link_first_login']);
    }

    /** Page à rouvrir une fois le jeu relié (chemin local uniquement), sinon le tableau de bord. */
    public static function continueUrl(): string
    {
        $after = (string) Session::get(self::SESSION_AFTER, '');
        if ($after !== '' && str_starts_with($after, '/') && !str_starts_with($after, '//') && !self::pathIsOpen($after)) {
            return url(ltrim($after, '/'));
        }

        return url('dashboard');
    }

    /** Le jeu vient d'être relié : l'étape est franchie pour toute la session. */
    public static function markLinked(int $userId, int $tenantId): void
    {
        Session::set(self::SESSION_CLEAR, $userId . ':' . $tenantId);
        Session::forget(self::SESSION_REQUIRED);
    }
}
