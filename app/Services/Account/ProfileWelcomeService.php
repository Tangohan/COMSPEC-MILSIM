<?php

declare(strict_types=1);

namespace App\Services\Account;

/**
 * Accueil du profil joueur (/account/bienvenue) : parcours en étapes que le membre peut passer et reprendre.
 *
 * Logique pure (aucune base, aucune session) : le contrôleur fournit l'état réel du compte (« faits ») et l'état
 * enregistré du parcours (lignes user_ui_tours « profile_welcome… »). Une étape est faite quand le compte le montre
 * (indicatif rempli, Discord relié…), jamais sur simple déclaration, sauf la page publique qu'on peut juste
 * avoir regardée. Rien n'est bloquant : seule la liaison du jeu imposée par la communauté (FirstLoginGameLinkGate)
 * l'est déjà, en dehors de ce parcours.
 */
final class ProfileWelcomeService
{
    public const STEP_IDENTITY = 'identite';
    public const STEP_DISCORD = 'discord';
    public const STEP_STEAM = 'steam';
    public const STEP_PORTRAIT = 'portrait';
    public const STEP_BANNER = 'banniere';
    public const STEP_SECURITY = 'securite';
    public const STEP_PUBLIC_PAGE = 'page-publique';

    /** Écran de fin (récapitulatif), après la dernière étape. */
    public const END = 'fin';

    /** Préfixe des lignes user_ui_tours qui gardent la progression (aucune colonne dédiée). */
    public const TOUR_PREFIX = 'profile_welcome';

    /** Session : état du parcours quand la table des guides manque encore sur le serveur. */
    public const SESSION_STATE = 'profile_welcome_state';

    /** Session : étape à rouvrir au retour d'une page du compte (Discord, Steam, double vérification…). */
    public const SESSION_RETURN = 'profile_welcome_return';

    /** @var list<string> */
    public const ORDER = [
        self::STEP_IDENTITY,
        self::STEP_DISCORD,
        self::STEP_STEAM,
        self::STEP_PORTRAIT,
        self::STEP_BANNER,
        self::STEP_SECURITY,
        self::STEP_PUBLIC_PAGE,
    ];

    /**
     * Libellés affichés au joueur.
     *
     * @return array<string, array{title: string, short: string, lead: string}>
     */
    public static function definitions(): array
    {
        return [
            self::STEP_IDENTITY => [
                'title' => 'Votre identité de jeu',
                'short' => 'Indicatif',
                'lead' => 'L’indicatif est le nom court qu’on vous donne à la radio, sur la carte ATAK et dans les effectifs.',
            ],
            self::STEP_DISCORD => [
                'title' => 'Reliez votre Discord',
                'short' => 'Discord',
                'lead' => 'Le serveur Discord vous reconnaît comme membre et vous donne les rôles de vos communautés. Rien n’est publié en votre nom.',
            ],
            self::STEP_STEAM => [
                'title' => 'Reliez votre compte Steam',
                'short' => 'Steam',
                'lead' => 'Le serveur de jeu et COMSPEC ATAK vous reconnaissent en partie grâce à votre compte Steam.',
            ],
            self::STEP_PORTRAIT => [
                'title' => 'Votre portrait opérateur',
                'short' => 'Portrait',
                'lead' => 'Il remplace la silhouette « inconnu » sur le portail, votre fiche et l’organigramme.',
            ],
            self::STEP_BANNER => [
                'title' => 'Votre bannière',
                'short' => 'Bannière',
                'lead' => 'Une image large affichée en haut de votre menu profil. Une capture de jeu fait très bien l’affaire.',
            ],
            self::STEP_SECURITY => [
                'title' => 'Protégez votre compte',
                'short' => 'Sécurité',
                'lead' => 'La double vérification demande un second code à la connexion : un mot de passe volé ne suffit plus.',
            ],
            self::STEP_PUBLIC_PAGE => [
                'title' => 'Votre page publique',
                'short' => 'Page publique',
                'lead' => 'Un C.V. de joueur à partager : parcours, qualifications, unités. Vous choisissez ce qui s’affiche, et elle reste fermée tant que vous ne l’ouvrez pas.',
            ],
        ];
    }

    public static function isStep(string $key): bool
    {
        return in_array($key, self::ORDER, true);
    }

    /**
     * État enregistré, nettoyé (lignes de user_ui_tours ou session).
     *
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function normalizeState(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        $raw = is_array($raw) ? $raw : [];
        $keys = static function (mixed $list): array {
            if (!is_array($list)) {
                return [];
            }
            $out = [];
            foreach ($list as $key) {
                if (is_string($key) && self::isStep($key) && !in_array($key, $out, true)) {
                    $out[] = $key;
                }
            }

            return $out;
        };
        $date = static fn (mixed $v): ?string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) === 1 ? $v : null;
        $current = is_string($raw['current'] ?? null) && (self::isStep($raw['current']) || $raw['current'] === self::END)
            ? $raw['current']
            : null;

        return [
            'started_at' => $date($raw['started_at'] ?? null),
            'current' => $current,
            'skipped' => $keys($raw['skipped'] ?? []),
            'seen' => $keys($raw['seen'] ?? []),
            'finished_at' => $date($raw['finished_at'] ?? null),
        ];
    }

    /**
     * Lecture des lignes user_ui_tours du parcours (une ligne principale + une par étape passée, vue, en cours).
     *
     * @param array<string, array{created_at?: ?string, completed_at?: ?string}> $rows
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function stateFromRows(array $rows): array
    {
        $raw = ['skipped' => [], 'seen' => []];
        foreach ($rows as $key => $dates) {
            if ($key === self::TOUR_PREFIX) {
                $raw['started_at'] = $dates['created_at'] ?? null;
                $raw['finished_at'] = $dates['completed_at'] ?? null;
                continue;
            }
            $parts = explode('.', (string) $key, 3);
            if (count($parts) !== 3 || $parts[0] !== self::TOUR_PREFIX) {
                continue;
            }
            match ($parts[1]) {
                'skip' => $raw['skipped'][] = $parts[2],
                'seen' => $raw['seen'][] = $parts[2],
                'at' => $raw['current'] = $parts[2],
                default => null,
            };
        }

        return self::normalizeState($raw);
    }

    /**
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return array<string, array{created_at: ?string, completed_at: ?string}>
     */
    public static function stateToRows(array $state): array
    {
        if ($state['started_at'] === null && $state['finished_at'] === null && $state['skipped'] === [] && $state['seen'] === []) {
            return [];
        }
        $rows = [self::TOUR_PREFIX => ['created_at' => $state['started_at'], 'completed_at' => $state['finished_at']]];
        foreach ($state['skipped'] as $key) {
            $rows[self::TOUR_PREFIX . '.skip.' . $key] = ['created_at' => null, 'completed_at' => null];
        }
        foreach ($state['seen'] as $key) {
            $rows[self::TOUR_PREFIX . '.seen.' . $key] = ['created_at' => null, 'completed_at' => null];
        }
        if ($state['current'] !== null) {
            $rows[self::TOUR_PREFIX . '.at.' . $state['current']] = ['created_at' => null, 'completed_at' => null];
        }

        return $rows;
    }

    /**
     * Étapes du parcours pour ce compte, avec leur état.
     *
     * Faits attendus (bool) : callsign, discord_available, discord_linked, steam_linked, portrait, portrait_locked,
     * banner_available, banner, second_factor, public_page.
     *
     * @param array<string, mixed> $facts
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return list<array{key: string, title: string, short: string, lead: string, status: string, number: int}>
     */
    public static function steps(array $facts, array $state): array
    {
        $defs = self::definitions();
        $f = static fn (string $k): bool => !empty($facts[$k]);
        $out = [];
        $n = 0;
        foreach (self::ORDER as $key) {
            $applicable = match ($key) {
                self::STEP_DISCORD => $f('discord_available') || $f('discord_linked'),
                self::STEP_PORTRAIT => !$f('portrait_locked') || $f('portrait'),
                self::STEP_BANNER => $f('banner_available'),
                default => true,
            };
            if (!$applicable) {
                continue;
            }
            $done = match ($key) {
                self::STEP_IDENTITY => $f('callsign'),
                self::STEP_DISCORD => $f('discord_linked'),
                self::STEP_STEAM => $f('steam_linked'),
                self::STEP_PORTRAIT => $f('portrait'),
                self::STEP_BANNER => $f('banner'),
                self::STEP_SECURITY => $f('second_factor'),
                self::STEP_PUBLIC_PAGE => $f('public_page') || in_array($key, $state['seen'], true),
                default => false,
            };
            $status = $done ? 'done' : (in_array($key, $state['skipped'], true) ? 'skipped' : 'todo');
            $out[] = ['key' => $key, 'status' => $status, 'number' => ++$n] + $defs[$key];
        }

        return $out;
    }

    /**
     * @param list<array{key: string, status: string}> $steps
     * @return array{done: int, skipped: int, todo: int, total: int, percent: int}
     */
    public static function progress(array $steps): array
    {
        $count = ['done' => 0, 'skipped' => 0, 'todo' => 0];
        foreach ($steps as $s) {
            $count[$s['status']] = ($count[$s['status']] ?? 0) + 1;
        }
        $total = count($steps);

        return $count + [
            'total' => $total,
            'percent' => $total > 0 ? (int) round($count['done'] * 100 / $total) : 100,
        ];
    }

    /**
     * Le parcours est-il fini ? Toutes les étapes faites, ou le joueur a choisi « Terminer » (les étapes passées
     * restent alors proposées dans Mon compte, sans rappel).
     *
     * @param list<array{key: string, status: string}> $steps
     * @param array{finished_at: ?string} $state
     */
    public static function isFinished(array $steps, array $state): bool
    {
        return $state['finished_at'] !== null || self::progress($steps)['done'] === count($steps);
    }

    /**
     * Étape où reprendre : celle laissée en cours si elle reste à faire, sinon la première à faire, sinon la
     * première passée, sinon l'écran de fin.
     *
     * @param list<array{key: string, status: string}> $steps
     * @param array{current: ?string} $state
     */
    public static function resumeKey(array $steps, array $state): string
    {
        foreach ($steps as $s) {
            if ($s['key'] === $state['current'] && $s['status'] !== 'done') {
                return $s['key'];
            }
        }
        foreach (['todo', 'skipped'] as $wanted) {
            foreach ($steps as $s) {
                if ($s['status'] === $wanted) {
                    return $s['key'];
                }
            }
        }

        return self::END;
    }

    /**
     * Étape suivante après $key : la prochaine encore à faire (puis la prochaine passée), sinon l'écran de fin.
     *
     * @param list<array{key: string, status: string}> $steps
     */
    public static function nextKey(array $steps, string $key): string
    {
        $index = null;
        foreach ($steps as $i => $s) {
            if ($s['key'] === $key) {
                $index = $i;
                break;
            }
        }
        $after = $index === null ? $steps : array_slice($steps, $index + 1);
        foreach (['todo', 'skipped'] as $wanted) {
            foreach ($after as $s) {
                if ($s['status'] === $wanted) {
                    return $s['key'];
                }
            }
        }

        return self::END;
    }

    /**
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function skip(array $state, string $key, string $next): array
    {
        if (self::isStep($key) && !in_array($key, $state['skipped'], true)) {
            $state['skipped'][] = $key;
        }

        return self::moveTo($state, $next);
    }

    /**
     * Page publique regardée : l'étape compte comme vue même si le joueur la laisse fermée.
     *
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function markSeen(array $state, string $key): array
    {
        if (self::isStep($key) && !in_array($key, $state['seen'], true)) {
            $state['seen'][] = $key;
        }
        $state['skipped'] = array_values(array_diff($state['skipped'], [$key]));

        return $state;
    }

    /**
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function moveTo(array $state, string $key, ?string $now = null): array
    {
        if (self::isStep($key) || $key === self::END) {
            $state['current'] = $key;
        }
        $state['started_at'] ??= $now ?? gmdate('Y-m-d H:i:s');

        return $state;
    }

    /**
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function finish(array $state, ?string $now = null): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $state['started_at'] ??= $now;
        $state['finished_at'] = $now;
        $state['current'] = self::END;

        return $state;
    }

    /**
     * Rouvrir le parcours depuis Mon compte : le rappel revient tant qu'il reste quelque chose à faire.
     *
     * @param array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} $state
     * @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string}
     */
    public static function reopen(array $state): array
    {
        $state['finished_at'] = null;
        $state['current'] = null;

        return $state;
    }

    /**
     * Première ouverture de Mon compte par un nouveau membre : le parcours s'ouvre une seule fois de lui-même.
     * Les membres déjà inscrits ne sont jamais redirigés ; ils voient la carte « Complétez votre profil ».
     *
     * @param list<array{key: string, status: string}> $steps
     * @param array{started_at: ?string, finished_at: ?string} $state
     */
    public static function shouldAutoOpen(array $steps, array $state, bool $isNewMember): bool
    {
        return $isNewMember
            && $state['started_at'] === null
            && $state['finished_at'] === null
            && self::progress($steps)['done'] < count($steps);
    }
}
