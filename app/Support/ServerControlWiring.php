<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ce que chaque réglage de la page Contrôle de mission fait réellement en jeu.
 *
 * Tracé relevé dans le code (novembre 2026, ATAK natif 2.9.21 / COMSPEC Link 2.0.111) :
 *  - règles : AtakExperienceService → GET /api/atak/experience → DLL GetExperience (SimplifyExperienceJson :
 *    liste fixe + toutes les clés net_*) → connect fn_pollExperience (toutes les 60 s) → fn_applyTenantExperience
 *    (variables CBA d'Overwatch connect) ou lecture directe par l'ATAK natif (fn_tenantRule, fn_pref, fn_netCond).
 *  - fonctions reliées : AtakBridgeModulesService → GET /api/atak/mod-modules → DLL GetModModules →
 *    connect fn_pollModModules (toutes les 45 s) → fn_isModModuleEnabled ; trois sont lues par le site lui-même.
 *  - apps : clés net_apps_required / net_apps_forbidden → fn_appInstall (ATAK natif).
 * « reader » = fichier du mod (ou du site) qui lit la clé : le test ServerControlWiringTest vérifie qu'il la lit encore.
 *
 * state : live (actif en jeu) | partial | todo (pas encore branché ou plus lu par le mod maintenu).
 * delay : site (immédiat, appliqué par le site) | poll (joueurs en liaison, moins de 2 min) |
 *         connection (prochaine connexion d'un joueur) | mission (prochain relevé de carte, en début de mission).
 */
final class ServerControlWiring
{
    public const STATES = [
        'live' => 'Actif en jeu',
        'partial' => 'Partiel',
        'todo' => 'Pas encore branché',
    ];

    public const DELAYS = [
        'site' => 'Immédiat',
        'poll' => 'Moins de 2 minutes',
        'connection' => 'À la prochaine connexion d’un joueur',
        'mission' => 'Au prochain démarrage de mission',
    ];

    /** Thèmes de la page, dans l'ordre d'affichage. */
    public const THEMES = [
        'ambiance' => ['Ambiance et aides à l’écran', 'Le ton de la partie : aides affichées, bandeaux, alertes de suivi.'],
        'acces' => ['Accès au téléphone', 'Qui peut utiliser le téléphone, et ce qu’il risque au combat.'],
        'affichage' => ['Affichage du téléphone', 'Ce que montre la carte du téléphone. Une règle imposée verrouille le réglage chez le joueur.'],
        'reseau' => ['Réseau radio, GPS et batterie', 'La simulation radio du téléphone. « Laisser au serveur » : l’administrateur du serveur ou un Zeus règle en partie (menu Échap, Conditions réseau).'],
        'roleplay' => ['Effets roleplay', 'Zones de brouillage, effets du Zeus et dégradations visibles.'],
        'poste' => ['Carte du poste de commandement', 'Ce qui remonte du jeu vers la carte web du poste.'],
        'fonctions' => ['Fonctions de mission', 'Ordres, renseignement, temps de jeu et partage vers le site.'],
    ];

    private const ATAK = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/';
    private const CONNECT = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/';
    private const APPLY = self::CONNECT . 'fn_applyTenantExperience.sqf';
    private const NET = self::ATAK . 'network/fn_netCond.sqf';
    private const RETIRED = 'Lu seulement par l’ancien pack ATAK Enhanced / cTab, arrêté : aucun effet avec l’ATAK natif.';

    /**
     * Règles de mission (catalogue AtakExperienceService).
     *
     * @return array<string, array{state: string, theme: string, where: string, impact: string, delay: ?string, min: ?string, reader: ?string, note?: string}>
     */
    public static function rules(): array
    {
        $net = static fn (string $impact, string $where, string $min = '2.2.0'): array => [
            'state' => 'live', 'theme' => 'reseau', 'where' => $where, 'impact' => $impact, 'delay' => 'poll', 'min' => $min, 'reader' => self::NET,
        ];
        $phone = static fn (string $impact, string $reader, ?string $min = '2.1.0', string $where = 'Téléphone ATAK : carte'): array => [
            'state' => 'live', 'theme' => 'affichage', 'where' => $where, 'impact' => $impact, 'delay' => 'poll', 'min' => $min, 'reader' => $reader,
        ];

        return [
            'realism' => ['state' => 'live', 'theme' => 'ambiance', 'where' => 'Écran du jeu et suivi au poste', 'impact' => 'Activé : plus d’aides « confort » à l’écran, et le poste ne reçoit plus les alertes d’immobilité ou de saut de position.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_updatePosition.sqf'],
            'troll' => ['state' => 'live', 'theme' => 'ambiance', 'where' => 'Alertes de suivi au poste', 'impact' => 'Activé : le poste reçoit plus tôt les alertes d’immobilité (2 min au lieu de 3) et de déplacement suspect (150 m au lieu de 250 m).', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_updatePosition.sqf'],
            'screen_notifications' => ['state' => 'live', 'theme' => 'ambiance', 'where' => 'Bandeaux en bas de l’écran du jeu', 'impact' => 'Imposé : tous les joueurs voient, ou ne voient plus, les bandeaux d’information en bas de l’écran.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_shouldShowScreenNotification.sqf'],

            'require_equipment' => ['state' => 'live', 'theme' => 'acces', 'where' => 'Téléphone ATAK et envoi de position', 'impact' => 'Toujours exiger : un joueur sans téléphone ni GPS dans son inventaire ne peut plus ouvrir l’ATAK et n’envoie plus sa position.', 'delay' => 'poll', 'min' => null, 'reader' => self::ATAK . 'core/fn_hasDevice.sqf'],
            'sse_require_item' => ['state' => 'live', 'theme' => 'acces', 'where' => 'Téléphone ATAK : app SSE', 'impact' => 'Toujours exiger : sans terminal SEEK, BII-10 ou téléphone ATAK, impossible d’ouvrir une fiche de renseignement.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_sseHasTerminalItem.sqf'],
            'atak_realism' => ['state' => 'live', 'theme' => 'acces', 'where' => 'Téléphone ATAK', 'impact' => 'Une blessure au torse peut éteindre le téléphone, casser son écran ou le détruire, selon le niveau choisi.', 'delay' => 'poll', 'min' => null, 'reader' => self::ATAK . 'core/fn_deviceDamage.sqf'],

            'native_map_labels' => $phone('Imposé : les indicatifs des alliés sont affichés, ou masqués, sur la carte de tous les téléphones.', self::ATAK . 'map/fn_mapOnDraw.sqf'),
            'native_marker_tags' => $phone('Imposé : le nom et les coordonnées sous chaque repère sont affichés, ou masqués, pour tous.', self::ATAK . 'map/fn_mapOnDraw.sqf'),
            'native_compass' => $phone('Imposé : la boussole en haut de la carte est affichée, ou masquée, pour tous.', self::ATAK . 'pages/fn_pageMap.sqf'),
            'native_vibrate' => $phone('Imposé : le téléphone vibre et sonne, ou reste silencieux, à chaque message ou alerte.', self::ATAK . 'ui/fn_vibrate.sqf', '2.1.0', 'Téléphone ATAK : alertes'),
            'rp_data_bar' => $phone('Imposé : la barre vitesse, altitude, cap et grille sous la carte est affichée, ou masquée, pour tous.', self::ATAK . 'ui/fn_dataBarEnabled.sqf', null),
            'native_jtac_autofill' => $phone('Complétion automatique : le téléphone remplit pour le JTAC l’appareil, la cible laser, l’IP et les lignes du 9-LINE. Sinon, le JTAC saisit tout lui-même.', self::ATAK . 'tactical/fn_jtacLines.sqf', '1.87.0', 'Téléphone ATAK : app JTAC'),

            'net_sim' => $net('Couper la simulation : les envois partent à débit plein, sans délai ni perte, quel que soit le terrain.', 'Téléphone ATAK : réseau'),
            'net_profile' => $net('Fixe le niveau de réalisme de base de tous les téléphones. Chaque règle ci-dessous passe avant lui.', 'Téléphone ATAK : réseau, GPS, batterie', '2.3.0'),
            'net_realism' => $net('Change le calcul de la portée radio : relief, forêt et bâtiments comptent plus ou moins.', 'Téléphone ATAK : réseau'),
            'net_weather' => $net('Activé : pluie, orage et vent réduisent la portée et font perdre des envois.', 'Téléphone ATAK : réseau'),
            'net_weather_k' => $net('Renforce ou adoucit l’effet de la météo sur le réseau.', 'Téléphone ATAK : réseau'),
            'net_congestion' => $net('Activé : plus il y a de téléphones sur un même relais, plus les envois ralentissent.', 'Téléphone ATAK : réseau'),
            'net_bg_load' => $net('Réserve une part de chaque relais à un trafic fictif : il reste moins de débit pour les joueurs.', 'Téléphone ATAK : réseau'),
            'net_loss' => $net('Activé : des envois se perdent par rafales et repartent tout seuls.', 'Téléphone ATAK : réseau'),
            'net_loss_floor' => $net('Impose un minimum de pertes partout, même avec un excellent signal.', 'Téléphone ATAK : réseau'),
            'net_latency_add' => $net('Ajoute un délai fixe à chaque envoi, comme une liaison satellite.', 'Téléphone ATAK : réseau'),
            'net_relay_only' => $net('Activé : hors de portée d’un relais de la mission, le téléphone n’a plus de réseau.', 'Téléphone ATAK : réseau'),
            'net_gateways' => $net('Autorisé : l’équipage peut faire de son véhicule un relais ou un poste de commandement à mât.', 'Véhicules'),
            'net_offline_hold' => $net('Activé : sans réseau, le téléphone garde ses données et envoie tout au retour du signal.', 'Téléphone ATAK : réseau'),
            'net_video_relays' => $net('Activé : la qualité des flux vidéo dépend des relais de la mission.', 'Téléphone ATAK : vidéo'),
            'net_pli' => $net('Par points : les alliés avancent par bonds sur la carte et passent en gris quand leurs positions n’arrivent plus.', 'Téléphone ATAK : carte', '2.3.0'),
            'net_pli_rate' => $net('Temps entre deux positions envoyées par chaque téléphone.', 'Téléphone ATAK : carte', '2.3.0'),
            'net_gps' => $net('Activé : la position est moins précise en forêt, en ville et dans les bâtiments.', 'Téléphone ATAK : GPS', '2.3.0'),
            'net_gps_k' => $net('Renforce ou réduit les erreurs de position du GPS.', 'Téléphone ATAK : GPS', '2.3.0'),
            'net_gps_fix' => $net('Activé : au démarrage, le téléphone cherche les satellites jusqu’à environ 40 s avant d’avoir une position.', 'Téléphone ATAK : GPS', '2.3.0'),
            'net_gps_loss' => $net('À 100 % : plus de position dans un bâtiment ou en sous-sol, comme un vrai téléphone.', 'Téléphone ATAK : GPS', '2.5.0'),
            'net_gps_rel' => $net('Règle la fréquence des décrochages et des sauts de position du GPS.', 'Téléphone ATAK : GPS', '2.5.0'),
            'net_battery' => $net('Activé : la batterie se vide à l’usage et se recharge en véhicule ; à 0 % le téléphone s’éteint.', 'Téléphone ATAK : batterie', '2.3.0'),

            'rp_enabled' => ['state' => 'live', 'theme' => 'roleplay', 'where' => 'Téléphone ATAK : simulations roleplay', 'impact' => 'Désactivé : zones de brouillage, effets du Zeus et coupures roleplay ne touchent plus les téléphones.', 'delay' => 'poll', 'min' => null, 'reader' => self::ATAK . 'core/fn_tenantApply.sqf'],
            'rp_network_failures' => ['state' => 'live', 'theme' => 'roleplay', 'where' => 'Zones roleplay', 'impact' => 'Activé : dans les zones roleplay posées en mission, les téléphones perdent la liaison et des envois.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_applyZoneEffects.sqf'],
            'rp_visual_effects' => ['state' => 'live', 'theme' => 'roleplay', 'where' => 'Écran du téléphone', 'impact' => 'Activé : l’écran se brouille ou grésille quand la liaison faiblit ou qu’un Zeus le brouille.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_applyZeusAtakEffect.sqf'],
            'rp_link_degrade' => ['state' => 'live', 'theme' => 'roleplay', 'where' => 'Téléphone ATAK : envois', 'impact' => 'Activé : retards et petites coupures sur les envois, même en bonne couverture.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_isLinkDegradeSimActive.sqf'],
            'rp_sensor_failures' => ['state' => 'todo', 'theme' => 'roleplay', 'where' => 'Capteurs médicaux', 'impact' => 'Prévu : constantes médicales manquantes ou fausses. Aujourd’hui, aucune simulation ne lit ce réglage.', 'delay' => null, 'min' => null, 'reader' => null],

            'show_opfor' => ['state' => 'partial', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Masquer : les positions adverses n’apparaissent plus sur la carte web. Pris en compte quand un joueur se reconnecte ; un paramètre de mission peut l’emporter.', 'delay' => 'connection', 'min' => null, 'reader' => self::CONNECT . 'fn_sendFactionSettings.sqf'],
            'show_independent' => ['state' => 'partial', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Masquer : les positions indépendantes n’apparaissent plus sur la carte web. Pris en compte quand un joueur se reconnecte.', 'delay' => 'connection', 'min' => null, 'reader' => self::CONNECT . 'fn_sendFactionSettings.sqf'],
            'show_civilian' => ['state' => 'partial', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Masquer : les civils n’apparaissent plus sur la carte web. Pris en compte quand un joueur se reconnecte.', 'delay' => 'connection', 'min' => null, 'reader' => self::CONNECT . 'fn_sendFactionSettings.sqf'],
            'sync_map_markers' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Ne jamais transmettre : les repères posés sur la carte du jeu n’arrivent plus au poste.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_syncMapMarker.sqf'],
            'vehicle_detail' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Imposé : le poste voit, ou ne voit plus, le cap et la vitesse des joueurs embarqués.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_updatePosition.sqf'],

            'order_compose' => ['state' => 'live', 'theme' => 'fonctions', 'where' => 'Menu ACE et tablette', 'impact' => 'Interdire : les chefs d’unité ne peuvent plus rédiger un ordre ou un FRAGO depuis le terrain.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_initACE.sqf'],
            'playtime' => ['state' => 'live', 'theme' => 'fonctions', 'where' => 'Fiches des membres sur le site', 'impact' => 'Ne jamais transmettre : le temps passé en mission n’arrive plus sur le site (heures de jeu, présence).', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_playtimeTracker.sqf'],
            'radio_proximity' => ['state' => 'partial', 'theme' => 'fonctions', 'where' => 'Tablette Athena en jeu', 'impact' => 'Imposé : la liste des radios proches s’affiche, ou non, dans la tablette Athena. Sans effet sur le téléphone ATAK.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_webBrowserPageLoaded.sqf'],
            'ace_menus' => ['state' => 'todo', 'theme' => 'fonctions', 'where' => 'Menu ACE', 'impact' => 'Prévu : masquer les actions Overwatch du menu ACE. Aujourd’hui elles restent visibles quel que soit ce choix.', 'delay' => null, 'min' => null, 'reader' => null],
            'athena_feed' => ['state' => 'todo', 'theme' => 'fonctions', 'where' => 'Aperçus caméra', 'impact' => 'Servait à l’ancienne Live cam par captures, retirée : aucun effet en jeu.', 'delay' => null, 'min' => null, 'reader' => null, 'note' => 'retired'],
        ];
    }

    /**
     * Fonctions reliées (catalogue AtakBridgeModulesService). « Désactivé » est l'action de l'administrateur : l'impact le décrit.
     *
     * @return array<string, array{state: string, theme: string, where: string, impact: string, delay: ?string, min: ?string, reader: ?string, note?: string}>
     */
    public static function modules(): array
    {
        $retired = static fn (string $where): array => ['state' => 'todo', 'theme' => 'poste', 'where' => $where, 'impact' => self::RETIRED, 'delay' => null, 'min' => null, 'reader' => null, 'note' => 'retired'];

        return [
            'geo_places' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Carte web : recherche de lieux', 'impact' => 'Désactivé : les villes et villages de la carte ne remontent plus pour la recherche sur la carte web.', 'delay' => 'mission', 'min' => null, 'reader' => self::CONNECT . 'fn_sampleGeoNetwork.sqf'],
            'geo_roads' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Carte web : routes', 'impact' => 'Désactivé : les routes ne remontent plus, le calcul d’itinéraire du site n’a plus de réseau routier.', 'delay' => 'mission', 'min' => null, 'reader' => self::CONNECT . 'fn_sampleGeoNetwork.sqf'],
            'route_planning' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Désactivé : le poste ne peut plus calculer d’itinéraire qui suit les routes.', 'delay' => 'site', 'min' => null, 'reader' => 'app/Controllers/Api/AtakGeoNetworkApiController.php'],
            'gps_navigation' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Carte web du poste', 'impact' => 'Désactivé : le cap, la vitesse et l’heure d’arrivée estimée des joueurs ne remontent plus.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_updatePosition.sqf'],
            'wave_relay' => ['state' => 'live', 'theme' => 'poste', 'where' => 'Téléphone et poste', 'impact' => 'Désactivé : le relais le plus proche (débit, fiabilité, places) n’est plus envoyé avec la position.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_updatePosition.sqf'],
            'sse_person' => ['state' => 'live', 'theme' => 'fonctions', 'where' => 'Menu ACE', 'impact' => 'Désactivé : l’action « Terminal SEEK — enregistrer une personne » disparaît du menu ACE.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_initATAKMenu.sqf'],
            'ecoti_hud' => ['state' => 'live', 'theme' => 'fonctions', 'where' => 'Jumelles de vision nocturne', 'impact' => 'Désactivé : plus d’affichage situation sous JVN, même pour les joueurs qui l’ont activé.', 'delay' => 'poll', 'min' => null, 'reader' => self::CONNECT . 'fn_ecotiIsAvailable.sqf'],
            'report_routing' => ['state' => 'live', 'theme' => 'fonctions', 'where' => 'Site : rapports tactiques', 'impact' => 'Désactivé : les rapports envoyés du jeu ne suivent plus les règles de distribution.', 'delay' => 'site', 'min' => null, 'reader' => 'app/Controllers/Api/AtakApiController.php'],
            'jnet_bridge' => ['state' => 'live', 'theme' => 'fonctions', 'where' => 'Site : espace JNET', 'impact' => 'Désactivé : notes de reco, photos et rapports du terrain ne sont plus publiés dans JNET.', 'delay' => 'site', 'min' => null, 'reader' => 'app/Services/Jnet/JnetFieldReportBridge.php'],
            'weather' => $retired('Bandeau météo'),
            'drone' => $retired('Contacts drones'),
            'ctab_markers' => $retired('Repères cTab'),
            'route' => $retired('Itinéraire actif'),
            'jump' => $retired('Plans de saut'),
            'iceman_alerts' => $retired('Alertes'),
            'iceman_bda' => $retired('Bilans des dégâts'),
            'iceman_photo' => $retired('Photos terrain'),
            'iceman_group' => $retired('Messages de groupe'),
            'comspec_mirror' => $retired('Miroir vers cTab'),
        ];
    }

    /** Apps du téléphone : une seule règle de branchement pour tout l'App Store. */
    public const APPS = ['state' => 'live', 'where' => 'Téléphone ATAK : App Store', 'delay' => 'poll', 'min' => '2.5.0', 'reader' => self::ATAK . 'ui/fn_appInstall.sqf'];
}
