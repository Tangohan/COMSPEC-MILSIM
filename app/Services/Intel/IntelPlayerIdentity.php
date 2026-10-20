<?php

declare(strict_types=1);

namespace App\Services\Intel;

/**
 * Identité d'un joueur à travers toutes les remontées du terrain.
 *
 * Chaque table écrit l'émetteur à sa façon : compte (user_id), identifiant Steam, ou seulement un indicatif
 * (« callsign ») qui change d'une source à l'autre. On ramène tout à une seule clé :
 *   u{id}      compte de la communauté connu (directement, ou via le Steam / l'indicatif) ;
 *   s{steam}   identifiant Steam sans compte rattaché ;
 *   c-{slug}   indicatif seul, normalisé (casse, accents, espaces et ponctuation ignorés).
 *
 * Classe pure : elle reçoit les lignes déjà lues (users, operator_game_profiles, game_sessions), sans base de données.
 */
final class IntelPlayerIdentity
{
    public const UNKNOWN = 'inconnu';

    /** @var array<int, array{id: int, name: string, callsign: string, steam: string}> */
    private array $users = [];

    /** @var array<string, int> steam => user_id */
    private array $steamToUser = [];

    /** @var array<string, int> slug d'indicatif => user_id */
    private array $callsignToUser = [];

    /** @var array<string, string> slug d'indicatif => steam (profil de jeu sans compte) */
    private array $callsignToSteam = [];

    /** @var array<string, string> steam => indicatif du profil de jeu */
    private array $steamCallsign = [];

    /** @var array<int, string> user_id => steam */
    private array $userSteam = [];

    /** @var array<int, string> user_id => indicatif du profil de jeu */
    private array $userGameCallsign = [];

    /**
     * @param list<array<string, mixed>> $users    lignes users (id, display_name, callsign, steam_id)
     * @param list<array<string, mixed>> $profiles lignes operator_game_profiles (steam_id, user_id, callsign)
     * @param list<array<string, mixed>> $sessions lignes game_sessions (user_id, steam_id)
     */
    public function __construct(array $users = [], array $profiles = [], array $sessions = [])
    {
        foreach ($users as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $steam = self::normalizeSteam($u['steam_id'] ?? null);
            $cs = trim((string) ($u['callsign'] ?? ''));
            $this->users[$id] = ['id' => $id, 'name' => trim((string) ($u['display_name'] ?? '')), 'callsign' => $cs, 'steam' => $steam ?? ''];
            if ($steam !== null) {
                $this->steamToUser[$steam] = $id;
                $this->userSteam[$id] = $steam;
            }
            $slug = self::normalizeCallsign($cs);
            if ($slug !== '') {
                $this->callsignToUser[$slug] ??= $id;
            }
        }
        foreach ($sessions as $s) {
            $uid = (int) ($s['user_id'] ?? 0);
            $steam = self::normalizeSteam($s['steam_id'] ?? null);
            if ($uid > 0 && $steam !== null && isset($this->users[$uid])) {
                $this->steamToUser[$steam] ??= $uid;
                $this->userSteam[$uid] ??= $steam;
            }
        }
        foreach ($profiles as $p) {
            $steam = self::normalizeSteam($p['steam_id'] ?? null);
            $uid = (int) ($p['user_id'] ?? 0);
            $cs = trim((string) ($p['callsign'] ?? ''));
            $slug = self::normalizeCallsign($cs);
            if ($uid > 0 && !isset($this->users[$uid])) {
                $uid = 0;
            }
            if ($steam !== null) {
                if ($uid > 0) {
                    $this->steamToUser[$steam] ??= $uid;
                    $this->userSteam[$uid] ??= $steam;
                }
                if ($cs !== '') {
                    $this->steamCallsign[$steam] = $cs;
                }
            }
            if ($slug !== '') {
                if ($uid > 0) {
                    $this->callsignToUser[$slug] ??= $uid;
                    $this->userGameCallsign[$uid] ??= $cs;
                } elseif ($steam !== null) {
                    $this->callsignToSteam[$slug] ??= $steam;
                }
            }
        }
    }

    /** Steam 64 bits (chiffres seuls) ou null. */
    public static function normalizeSteam(mixed $steam): ?string
    {
        $s = preg_replace('/\D+/', '', (string) ($steam ?? '')) ?? '';
        $s = ltrim($s, '0');

        return strlen($s) >= 5 ? $s : null;
    }

    /** Indicatif comparable : minuscules, sans accents, espaces ni ponctuation (« Alpha 1-1 » = « ALPHA1-1 »). */
    public static function normalizeCallsign(mixed $callsign): string
    {
        $s = mb_strtolower(trim((string) ($callsign ?? '')));
        if ($s === '') {
            return '';
        }
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ù' => 'u', 'û' => 'u',
            'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
        ]);
        $slug = preg_replace('/[^a-z0-9_-]+/', '', $s) ?? '';
        if ($slug === '') {
            // Indicatif entièrement hors alphabet latin : on garde une empreinte stable.
            return 'h' . substr(sha1($s), 0, 10);
        }

        return substr($slug, 0, 48);
    }

    /** Clé du joueur : compte d'abord, puis Steam, puis indicatif. */
    public function resolve(mixed $userId = null, mixed $steam = null, mixed $callsign = null): string
    {
        $uid = (int) ($userId ?? 0);
        if ($uid > 0) {
            return 'u' . $uid;
        }
        $st = self::normalizeSteam($steam);
        if ($st !== null) {
            return isset($this->steamToUser[$st]) ? 'u' . $this->steamToUser[$st] : 's' . $st;
        }
        $slug = self::normalizeCallsign($callsign);
        if ($slug === '') {
            return self::UNKNOWN;
        }
        if (isset($this->callsignToUser[$slug])) {
            return 'u' . $this->callsignToUser[$slug];
        }
        if (isset($this->callsignToSteam[$slug])) {
            $st = $this->callsignToSteam[$slug];

            return isset($this->steamToUser[$st]) ? 'u' . $this->steamToUser[$st] : 's' . $st;
        }

        return 'c-' . $slug;
    }

    /** Une clé reçue de l'URL est-elle bien formée ? */
    public static function validKey(string $key): bool
    {
        return $key === self::UNKNOWN || preg_match('/^(u[1-9][0-9]{0,9}|s[1-9][0-9]{4,24}|c-[a-z0-9_-]{1,48})$/', $key) === 1;
    }

    /**
     * Fiche d'identité d'une clé (sans les indicatifs vus dans les données, ajoutés par l'appelant).
     *
     * @return array{key: string, kind: string, user_id: ?int, account: string, callsign: string, steam: string}
     */
    public function card(string $key): array
    {
        $out = ['key' => $key, 'kind' => 'unknown', 'user_id' => null, 'account' => '', 'callsign' => '', 'steam' => ''];
        if (str_starts_with($key, 'u')) {
            $uid = (int) substr($key, 1);
            $u = $this->users[$uid] ?? null;
            $out['kind'] = 'user';
            $out['user_id'] = $uid;
            $out['account'] = $u !== null ? ($u['name'] !== '' ? $u['name'] : 'Membre #' . $uid) : 'Compte #' . $uid;
            $out['callsign'] = (string) (($u['callsign'] ?? '') ?: ($this->userGameCallsign[$uid] ?? ''));
            $out['steam'] = (string) (($u['steam'] ?? '') ?: ($this->userSteam[$uid] ?? ''));
        } elseif (str_starts_with($key, 's')) {
            $steam = substr($key, 1);
            $out['kind'] = 'steam';
            $out['steam'] = $steam;
            $out['callsign'] = $this->steamCallsign[$steam] ?? '';
        } elseif (str_starts_with($key, 'c-')) {
            $out['kind'] = 'callsign';
        }

        return $out;
    }
}
