<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Où chaque réglage de la configuration réalisme (config/realism-schema.json) produit réellement un effet.
 *
 * Relevé dans le code : le pack Overwatch lit la configuration par ATHENA_fnc_getRealismParam (DLL
 * GetRealismConfig → GET /api/atak/realism/config), la Tacmap web par atak-overwatch-ops.js, le portail par
 * AtakRealismApiController (certificats). Les autres réglages sont enregistrés mais aucun code ne les lit
 * encore : l'écran le dit au lieu de laisser croire qu'ils agissent. À tenir à jour quand un réglage est branché.
 */
final class AtakRealismWiring
{
    public const GAME = 'game';
    public const WEB = 'web';
    public const PARTIAL = 'partial';
    public const ELSEWHERE = 'elsewhere';
    public const NONE = 'none';

    /** @var array<string, array{0: string, 1: string}> « domaine.paramètre » => [statut, précision] */
    private const MAP = [
        'radio_relays.relay_range_m' => [self::GAME, 'Portée des relais posés en jeu et cercle de portée sur la Tacmap.'],
        'radio_relays.relay_range_min_m' => [self::GAME, 'Borne basse appliquée aux relais posés (module Zeus et pose manuelle).'],
        'radio_relays.relay_range_max_m' => [self::GAME, 'Borne haute appliquée aux relais posés.'],
        'radio_relays.relay_throughput_kbps' => [self::GAME, 'Débit attribué aux relais posés en jeu.'],
        'radio_relays.max_relay_connections' => [self::GAME, 'Nombre de terminaux qu’un relais accepte en jeu.'],
        'radio_relays.weather_effects_enabled' => [self::GAME, 'Active le recalcul de portée selon la météo sur chaque relais.'],
        'radio_relays.rain_range_multiplier' => [self::PARTIAL, 'Utilisé par le calcul du portail ; le pack applique encore ses propres coefficients météo.'],
        'radio_relays.fog_range_multiplier' => [self::PARTIAL, 'Utilisé par le calcul du portail ; le pack applique encore ses propres coefficients météo.'],
        'radio_relays.storm_range_multiplier' => [self::PARTIAL, 'Utilisé par le calcul du portail ; le pack applique encore ses propres coefficients météo.'],
        'radio_relays.wind_threshold_kmh' => [self::PARTIAL, 'Utilisé par le calcul du portail ; le pack applique encore ses propres coefficients météo.'],
        'radio_relays.wind_range_penalty_per_10kmh' => [self::PARTIAL, 'Utilisé par le calcul du portail ; le pack applique encore ses propres coefficients météo.'],
        'radio_relays.link_via_relays' => [self::ELSEWHERE, 'La liaison par relais obligatoire se règle dans Roleplay (réglage lu par le jeu).'],
        'zones_roleplay.zones_enabled' => [self::ELSEWHERE, 'Les zones roleplay s’activent dans Roleplay.'],
        'network_simulation.disconnect_enabled' => [self::ELSEWHERE, 'Les coupures réseau se règlent dans Roleplay.'],
        'network_simulation.latency_ms' => [self::ELSEWHERE, 'La latence simulée se règle dans Roleplay et dans Contrôle serveur → Réseau.'],
        'network_simulation.packet_loss_percent' => [self::ELSEWHERE, 'La perte de paquets se règle dans Roleplay et dans Contrôle serveur → Réseau.'],
        'certificates.certificate_required' => [self::GAME, 'Le pack exige un certificat valide pour relier un terminal à un relais.'],
        'certificates.certificate_duration_days' => [self::WEB, 'Validité par défaut des certificats délivrés depuis le portail.'],
    ];

    public const LABELS = [
        self::GAME => 'Appliqué en jeu',
        self::WEB => 'Appliqué sur le portail',
        self::PARTIAL => 'Partiellement appliqué',
        self::ELSEWHERE => 'Réglé ailleurs',
        self::NONE => 'Pas encore pris en compte',
    ];

    public const TONES = [
        self::GAME => 'ok',
        self::WEB => 'info',
        self::PARTIAL => 'warn',
        self::ELSEWHERE => 'neut',
        self::NONE => 'neut',
    ];

    /** @return array{status: string, label: string, tone: string, note: string} */
    public static function of(string $domain, string $param): array
    {
        [$status, $note] = self::MAP[$domain . '.' . $param] ?? [self::NONE, 'Enregistré, mais aucun code du jeu ni du portail ne lit encore ce réglage.'];

        return ['status' => $status, 'label' => self::LABELS[$status], 'tone' => self::TONES[$status], 'note' => $note];
    }

    /**
     * Compte par statut pour un schéma donné.
     *
     * @param array<string, mixed> $schema
     * @return array<string, int>
     */
    public static function counts(array $schema): array
    {
        $out = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($schema['domains'] ?? [] as $dk => $domain) {
            foreach (array_keys($domain['parameters'] ?? []) as $pk) {
                $out[self::of((string) $dk, (string) $pk)['status']]++;
            }
        }

        return $out;
    }
}
