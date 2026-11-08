<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Signaux des envois du mod, écrits sur le chemin d'ingestion : une ligne courte dans le tampon fichier, rien d'autre
 * (ni base, ni verrou, ni lecture). L'analyse se fait plus tard, par lots, dans WatchFlusher / ModAbuseDetector.
 *
 * Aucune adresse IP, aucun e-mail : l'identité d'un envoi se réduit à la communauté, au Steam déclaré et à une
 * empreinte tronquée du jeton (jamais le jeton ni la clé).
 *
 * Lignes (champs séparés par « | ») :
 *   S|ts|communauté|steam|genre|empreinte|steam lié|version UA|version déclarée|canal
 *   P|ts|communauté|steam|carte|x|y
 *   R|ts|communauté|steam|motif|canal
 */
final class ModSignals
{
    /** Genres d'identifiant : k = clé de communauté (partagée, jamais comparée), t = jeton de compte, s = session de jeu. */
    public const CRED_COMMUNITY_KEY = 'k';
    public const CRED_ACCOUNT_TOKEN = 't';
    public const CRED_GAME_SESSION = 's';

    /**
     * Un envoi du mod accepté par la garde d'écriture.
     *
     * @param string $credKind  CRED_*
     * @param string $credSecret clé ou jeton présenté (seule une empreinte hachée et tronquée est écrite)
     */
    public static function actor(
        int $tenantId,
        ?string $claimedSteam,
        string $credKind,
        string $credSecret,
        ?string $boundSteam,
        string $userAgentVersion,
        string $declaredVersion,
        string $channel,
        ?int $now = null,
    ): void {
        if ($tenantId < 1 || !WatchState::detectEnabled()) {
            return;
        }
        $fingerprint = $credKind === self::CRED_COMMUNITY_KEY || $credSecret === ''
            ? '-'
            : substr(hash('sha256', 'watch-cred|' . $credSecret), 0, 12);
        WatchSpool::append(WatchSpool::KIND_MOD, implode('|', [
            'S',
            $now ?? time(),
            $tenantId,
            WatchSpool::field((string) $claimedSteam, 24) ?: '-',
            $credKind,
            $fingerprint,
            WatchSpool::field((string) $boundSteam, 24) ?: '-',
            WatchSpool::field($userAgentVersion, 24) ?: '-',
            WatchSpool::field($declaredVersion, 24) ?: '-',
            WatchSpool::field($channel, 16) ?: '-',
        ]), $now);
    }

    /** Une position acceptée (coordonnées valides pour la garde). */
    public static function position(int $tenantId, ?string $steam, int $mapId, float $x, float $y, ?int $now = null): void
    {
        if ($tenantId < 1 || $steam === null || $steam === '' || !WatchState::detectEnabled()) {
            return;
        }
        if (!is_finite($x) || !is_finite($y)) {
            return;
        }
        WatchSpool::append(WatchSpool::KIND_MOD, implode('|', [
            'P',
            $now ?? time(),
            $tenantId,
            WatchSpool::field($steam, 24),
            max(1, $mapId),
            (int) round($x),
            (int) round($y),
        ]), $now);
    }

    /** Une écriture refusée par la garde (motif court : steam_spoof, rate_limited, coords_out_of_bounds…). */
    public static function refusal(int $tenantId, ?string $steam, string $reason, string $channel = '', ?int $now = null): void
    {
        if ($tenantId < 1 || !WatchState::detectEnabled()) {
            return;
        }
        WatchSpool::append(WatchSpool::KIND_MOD, implode('|', [
            'R',
            $now ?? time(),
            $tenantId,
            WatchSpool::field((string) $steam, 24) ?: '-',
            WatchSpool::field($reason, 40) ?: '-',
            WatchSpool::field($channel, 16) ?: '-',
        ]), $now);
    }
}
