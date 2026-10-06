COMSPEC_ATAK_UI_GENERATION = "native-rsc-v1";
missionNamespace setVariable ["COMSPEC_ATAK_UI_GENERATION", COMSPEC_ATAK_UI_GENERATION, true];
missionNamespace setVariable ["COMSPEC_ATAK_NativeVersion", "1.6.0", true];
diag_log "[COMSPEC ATAK NATIVE][BOOT][CANARY] native_client_v1_4_0_loaded";
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] UI generation: native-rsc-v1";
[] call comspec_atak_native_fnc_stateInit;

// Réglages serveur (forçables par l'admin dans les réglages CBA du serveur / de la mission).
// Tous les mods COMSPEC partagent une seule catégorie CBA « COMSPEC » ; sous-catégories « ATAK · … », « Overwatch · … », « SSE ».
private _cat = ["COMSPEC", "ATAK · Accès au téléphone"];
private _recompute = { [true] call comspec_atak_native_fnc_deviceCatalog; };
["comspec_atak_native_require_item", "CHECKBOX",
    ["Item obligatoire pour avoir l'ATAK", "Activé par défaut : il faut porter un téléphone (ItemAndroid de cTab ou équivalent d'un autre mod) pour sortir ou prendre le téléphone. Décocher pour donner l'ATAK à tout le monde."],
    _cat, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_photo_anim", "EDITBOX",
    ["Animation de l'appareil photo", "Jouée tant que l'appareil photo est ouvert (vue normale). Nom d'une action ou d'un geste (ex. gesturePoint), d'une animation (CfgMoves, ex. AmovPercMstpSrasWrflDnon_AinvPercMstpSrasWrflDnon), ou d'une fonction (ex. mon_fnc_photoPose, appelée avec [joueur, ""photo""]). Vide : aucune animation."],
    ["COMSPEC", "ATAK · Photo"], "gesturePoint", 1] call CBA_fnc_addSetting;
["comspec_atak_native_selfie_anim", "EDITBOX",
    ["Animation du selfie", "Jouée en mode selfie. Même format : action ou geste, animation CfgMoves, ou fonction appelée avec [joueur, ""selfie""] (puis [joueur, ""exit""] à la fermeture). Vide : on garde l'animation de l'appareil photo."],
    ["COMSPEC", "ATAK · Photo"], "gesturePoint", 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_items", "EDITBOX",
    ["Batteries de rechange", "Objets (classes exactes, séparées par des virgules) qui rechargent le téléphone à 100 % quand on change la batterie. L'objet est consommé. La « Batterie ATAK » (COMSPEC_ATAK_Battery) est toujours acceptée."],
    _cat, "COMSPEC_ATAK_Battery,ACE_UAVBattery", 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_patterns", "EDITBOX",
    ["Batteries d'autres mods (motifs)", "Tout objet porté dont le nom de classe contient un de ces mots sert aussi de batterie de rechange (piles, batteries de drone…). Séparer par des virgules ; vider pour n'accepter que la liste ci-dessus."],
    _cat, "battery,batterie,pile", 1] call CBA_fnc_addSetting;
["comspec_atak_native_device_patterns", "EDITBOX",
    ["Détection des autres mods (motifs)", "Tout objet chargé dont le nom de classe contient un de ces mots compte comme téléphone. Séparer par des virgules. Par défaut : android,atak,smartphone."],
    _cat, "android,atak,smartphone", 1, _recompute] call CBA_fnc_addSetting;
["comspec_atak_native_device_items", "EDITBOX",
    ["Objets acceptés en plus", "Noms de classes exacts, séparés par des virgules (ex. un téléphone d'un pack d'équipement qui n'a pas « android » dans son nom)."],
    _cat, "", 1, _recompute] call CBA_fnc_addSetting;
["comspec_atak_native_device_tablets", "CHECKBOX",
    ["Les tablettes et DAGR comptent aussi", "ItemcTab, MicroDAGR (cTab) et MicroDAGR (ACE) ouvrent aussi le téléphone."],
    _cat, false, 1, _recompute] call CBA_fnc_addSetting;

private _mus = ["COMSPEC", "ATAK · Musique"];
["comspec_atak_native_music_enabled", "CHECKBOX",
    ["App Musique", "Lecture de fichiers locaux, de pistes du serveur (musiques d'Arma, des mods et de la mission) et de liens audio."],
    _mus, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_music_speaker", "CHECKBOX",
    ["Haut-parleur", "Les joueurs proches entendent la musique d'un téléphone en haut-parleur."],
    _mus, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_music_range", "SLIDER",
    ["Portée du haut-parleur (m)", "Au-delà, on n'entend plus rien ; le son baisse en s'éloignant."],
    _mus, [5, 100, 30, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_music_urls", "CHECKBOX",
    ["Lecture de liens (URL)", "Autorise la lecture de fichiers audio et webradios par adresse web."],
    _mus, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_music_radio", "EDITBOX",
    ["Radio de la mission", "Liens proposés à tous dans l'onglet SERVEUR, sous la forme Titre|https://...;Titre 2|https://..."],
    _mus, "", 1] call CBA_fnc_addSetting;

private _sim = ["COMSPEC", "ATAK · Simulation"];
["comspec_atak_native_damage_sim", "CHECKBOX",
    ["Dégâts du téléphone", "Balles au torse ou aux bras, explosions proches et eau fêlent l'écran, éteignent ou détruisent le téléphone. Réparation : trousse à outils ou nouvel appareil (actions ACE). Si le réalisme ATAK d'Overwatch est actif, c'est lui qui décide."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_ew_open", "CHECKBOX",
    ["Guerre électronique ouverte à tous", "Coché : tout porteur de téléphone peut brouiller et goniométrer. Décoché : réservé aux unités COMSPEC_ATAK_EwOperator ou au rôle « guerre électronique / brouilleur / SIGINT »."],
    _sim, true, 1] call CBA_fnc_addSetting;
private _geo = ["COMSPEC", "ATAK · Géolocalisation (GEOLOC)"];
["comspec_atak_native_geoloc_enabled", "CHECKBOX",
    ["Traçage des téléphones", "App GE (guerre électronique), onglet GÉOLOC : localiser un téléphone à partir de son numéro, de son IMEI ou de son adresse MAC (roleplay)."],
    _geo, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_geoloc_ew_only", "CHECKBOX",
    ["Réservé aux opérateurs GE", "Coché : seuls ceux qui ont droit à la guerre électronique (voir Simulation) peuvent lancer une géolocalisation."],
    _geo, false, 1] call CBA_fnc_addSetting;
{
    _x params ["_k", "_label", "_def"];
    [format ["comspec_atak_native_geoloc_%1", _k], "CHECKBOX",
        [format ["Traçables : %1", _label], format ["Les téléphones des unités %1 peuvent être géolocalisés par les autres camps.", _label]],
        _geo, _def, 1] call CBA_fnc_addSetting;
} forEach [["west", "BLUFOR", true], ["east", "OPFOR", true], ["guer", "INDÉPENDANTS", true], ["civ", "CIVILS", true]];
["comspec_atak_native_geoloc_own", "CHECKBOX",
    ["Tracer son propre camp", "Coché : on peut aussi géolocaliser un téléphone allié (sinon, ennemis et autres camps seulement)."],
    _geo, false, 1] call CBA_fnc_addSetting;
["comspec_atak_native_geoloc_ai", "CHECKBOX",
    ["IA traçables", "Les IA qui portent un téléphone ont un numéro et peuvent être géolocalisées."],
    _geo, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_geoloc_precision", "SLIDER",
    ["Précision (m)", "Rayon d'incertitude de la position renvoyée (triangulation par antennes)."],
    _geo, [10, 1000, 150, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_geoloc_refresh", "SLIDER",
    ["Rafraîchissement du suivi (s)", "Intervalle entre deux positions quand un suivi est actif."],
    _geo, [10, 300, 30, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_geoloc_warn", "CHECKBOX",
    ["Prévenir la cible", "La cible reçoit une alerte discrète « activité réseau anormale » quand elle est géolocalisée."],
    _geo, false, 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_sim", "CHECKBOX",
    ["Batterie simulée", "La batterie du téléphone se vide selon l'usage (écran, appareil photo, live cam, GPS, envois de données, recherche de réseau, brouilleur) et se recharge en véhicule moteur allumé. À 0 % le téléphone s'éteint et passe hors ligne dans le BFT."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_drain", "SLIDER", ["Vitesse de décharge", "Multiplicateur de consommation de la batterie (1 = environ 3 h écran en main)."], _sim, [0.25, 4, 1, 2], 1] call CBA_fnc_addSetting;
["comspec_atak_native_net_sim", "CHECKBOX",
    ["Débit réseau simulé", "Bâtiments, relief, véhicule, météo, brouilleurs, relais et dégâts réduisent le débit : messages et photos partent avec un délai, se perdent et repartent, ou attendent le retour du réseau."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_aar", "CHECKBOX",
    ["Rejeu de mission", "Chaque téléphone enregistre les positions de son camp toutes les 10 s (et les pertes amies) pour l'app Rejeu. Décocher pour ne rien enregistrer."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_drone_range", "SLIDER", ["Portée de la liaison drone", "Distance en mètres entre le téléphone du pilote et son drone (divisée par trois derrière le relief). Au-delà, le drone rentre seul à son point de décollage."], _sim, [500, 8000, 2500, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_drone_strike", "CHECKBOX",
    ["Drones armés", "Permet de fixer une charge (roquette RPG, charge de démolition, grenade) sur un drone et de l'envoyer en tir et oublie ou en recherche et frappe."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_apps_off", "EDITBOX", ["Apps désactivées", "Noms de classe d'apps à retirer de tous les téléphones, séparés par des virgules (ex. Dating, Food, MonModule). Vaut aussi pour les modules externes."], _sim, "", 1] call CBA_fnc_addSetting;
["comspec_atak_native_civil_apps", "CHECKBOX",
    ["Apps civiles", "UberEats (rations livrées par drone) et Tinder (rencontres entre joueurs). Décocher pour les retirer de tous les téléphones."],
    _sim, true, 1] call CBA_fnc_addSetting;

private _team = ["COMSPEC", "ATAK · Équipes et suivi"];
["comspec_atak_native_aircrew", "CHECKBOX",
    ["ATAK pour les équipages d'aéronef", "Pilote, copilote et postes de tourelle d'un hélicoptère ou d'un avion ont l'ATAK par la tablette de bord, même sans téléphone porté, téléphone vide ou cassé. Il se range en quittant l'appareil."],
    _team, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_screen_time", "CHECKBOX",
    ["Temps d'écran et temps par rôle", "Compte le temps du téléphone allumé (en main, porté, par app) et le temps de jeu par rôle (pilote, chef d'équipe…). Visible dans l'app Temps d'écran et envoyé à Athena (fiche du membre, back-office)."],
    _team, true, 1] call CBA_fnc_addSetting;

// Interface (réglage propre à chaque joueur).
["comspec_atak_native_action_menu", "LIST",
    ["Masquer le menu d'actions", "Le menu d'actions d'Arma (liste en haut à gauche, molette) ne s'ouvre plus tant que le téléphone est affiché. « En main » : seulement quand on a la souris sur le téléphone. Téléphone rangé : rien ne change (ACE et actions normales)."],
    ["COMSPEC", "ATAK · Interface"], [[0, 1, 2], ["Jamais", "En main seulement", "En main et porté"], 2], 0] call CBA_fnc_addSetting;

// Mode drone : les ordres s'exécutent là où le drone est local (pilote, serveur ou client qui l'a posé).
["comspec_atak_native_droneCmd", { _this call comspec_atak_native_fnc_droneCmd; }] call CBA_fnc_addEventHandler;
// Retour du drone vers le pilote : ennemi repéré ou engagé en mode recherche.
["comspec_atak_native_droneEvent", {
    params ["_d", "_kind", "_pos", "_what"];
    if (!hasInterface) exitWith {};
    ["WARNING", format ["Drone : %1 %2 en %3", ["ennemi repéré,", "frappe sur"] select (_kind isEqualTo "ENGAGE"), _what, [_pos, 8] call comspec_atak_native_fnc_gridRef], 6, 60] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
}] call CBA_fnc_addEventHandler;
