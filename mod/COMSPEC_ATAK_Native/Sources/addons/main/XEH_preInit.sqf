COMSPEC_ATAK_UI_GENERATION = "native-rsc-v1";
// Constantes posées par chaque machine (preInit tourne partout) : rien à diffuser. En public, chaque arrivée
// renvoyait les deux valeurs à tous les joueurs et à la file JIP.
missionNamespace setVariable ["COMSPEC_ATAK_UI_GENERATION", COMSPEC_ATAK_UI_GENERATION];
missionNamespace setVariable ["COMSPEC_ATAK_NativeVersion", "1.11.0"];
diag_log "[COMSPEC ATAK NATIVE][BOOT][CANARY] native_client_v1_4_0_loaded";
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] UI generation: native-rsc-v1";
[] call comspec_atak_native_fnc_stateInit;

// Langue du téléphone (par joueur). Titre bilingue : ce menu CBA n'est pas traduit. Le code reste en français,
// fn_tr traduit à l'affichage (dictionnaire i18n/en.txt).
["comspec_atak_native_language", "LIST",
    ["Langue du téléphone / Phone language", "Français, English, ou Auto : langue du jeu (français si Arma est en français, anglais sinon). / Auto follows the game language."],
    ["COMSPEC", "ATAK · Langue / Language"], [["fr", "en", "auto"], ["Français", "English", "Auto (langue du jeu / game language)"], 0], 0,
    { [true] call comspec_atak_native_fnc_langApply; }] call CBA_fnc_addSetting;
[] call comspec_atak_native_fnc_langApply;

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

// Gilets qui ont déjà un ATAK modélisé (fn_vestBuiltin) : notre téléphone n'y est pas accroché, sauf choix du joueur (menu ACE).
private _vestReset = { missionNamespace setVariable ["COMSPEC_ATAK_VestBuiltinCache", createHashMap]; };
["comspec_atak_native_vest_atak_items", "EDITBOX",
    ["Gilets avec ATAK intégré", "Classes exactes de gilets dont le modèle porte déjà un ATAK, séparées par des virgules. Chaque joueur peut aussi marquer son gilet dans le menu ACE « Téléphone ATAK > Gilet »."],
    ["COMSPEC", "ATAK · Gilet"], "", 1, _vestReset] call CBA_fnc_addSetting;
["comspec_atak_native_vest_atak_patterns", "EDITBOX",
    ["Gilets avec ATAK intégré (motifs)", "Mots cherchés dans la classe, le nom et le modèle du gilet. Séparer par des virgules ; vider pour n'utiliser que la liste."],
    ["COMSPEC", "ATAK · Gilet"], "atak,eud", 1, _vestReset] call CBA_fnc_addSetting;

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
    ["Dégâts du téléphone", "Selon où il est porté (en main, sur le gilet, en poche, dans le sac), la partie du corps touchée, la munition et ce que le gilet arrête : balles, éclats, souffle, chutes, accidents et eau peuvent ne rien faire, rayer ou fêler l'écran, tuer des pixels, abîmer la batterie, le haut-parleur, le GPS ou l'antenne, éteindre ou détruire le téléphone. Réparation : kit de réparation ou caisse à outils, ou nouvel appareil (actions ACE). Réalisme ATAK d'Overwatch : niveau 1 = extinction seulement, 2 = jamais détruit."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_dirt_sim", "CHECKBOX",
    ["Saleté et sang sur l'écran", "Poussière dehors (plus vite couché, en véhicule ouvert, après une explosion), gouttes de pluie, traces de doigts, sang sur l'écran et la coque après un soin. Nettoyage : action ACE « Nettoyer l'écran du téléphone » ou app Profil. Chaque joueur peut aussi le couper dans Réglages > Réalisme."],
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
// Batterie et mémoire vive du téléphone : réglés par l'admin du serveur ou de la mission.
private _pow = ["COMSPEC", "ATAK · Batterie et mémoire"];
["comspec_atak_native_battery_hours", "SLIDER",
    ["Autonomie écran en main (h)", "Durée d'une charge complète téléphone en main, écran allumé. Les autres usages (veille, appareil photo, live cam, GPS, brouilleur…) consomment dans la même proportion : 3 h en main donnent environ 14 h en veille."],
    _pow, [0.5, 24, 3, 1], 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_start", "SLIDER",
    ["Charge au début de la mission (%)", "Niveau de la batterie quand le joueur arrive en jeu. Les batteries de rechange sont toujours pleines."],
    _pow, [5, 100, 100, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_vehicle", "SLIDER",
    ["Recharge en véhicule (% par minute)", "Vitesse de recharge à bord d'un véhicule moteur allumé. 0 : pas de recharge en véhicule."],
    _pow, [0, 20, 3, 1], 1] call CBA_fnc_addSetting;
["comspec_atak_native_ram_total", "LIST",
    ["Mémoire vive du téléphone", "RAM du téléphone (le S7 a 4 Go). Android en prend environ 1,2 Go ; avec moins de mémoire, les apps en arrière-plan sont fermées plus tôt."],
    _pow, [[2048, 3072, 4096, 6144, 8192, 12288], ["2 Go", "3 Go", "4 Go (S7)", "6 Go", "8 Go", "12 Go"], 2], 1] call CBA_fnc_addSetting;
["comspec_atak_native_ram_bg", "SLIDER",
    ["Apps gardées en arrière-plan", "Nombre maximum d'apps qu'Android garde en mémoire derrière l'app ouverte. Au-delà, ou quand la mémoire est pleine, les plus anciennes sont fermées."],
    _pow, [0, 10, 6, 0], 1] call CBA_fnc_addSetting;
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
["comspec_atak_native_dating_specials", "LIST",
    ["Tinder : profils HVT et SSE", "Fréquence des profils tirés des IA de la carte (HVT et personnes SSE à moins de 5 km) entre deux joueurs. Le Zeus peut aussi marquer une IA avec le module « Tinder : profil HVT / SSE »."],
    _sim, [[0, 1, 2, 3], ["Jamais", "Rare (1 profil sur 10)", "Parfois (1 sur 4)", "Souvent (1 sur 2)"], 1], 1] call CBA_fnc_addSetting;

// Marquage au viseur (caméra du drone, appareil photo du téléphone) et cibles perdues.
private _lock = ["COMSPEC", "ATAK · Marquage au viseur"];
["comspec_atak_native_lock_enabled", "CHECKBOX",
    ["Marquage au viseur", "Réticule de la caméra du drone ou de l'appareil photo sur un ennemi, un appui : la cible est marquée sur la carte (LOCK) et suivie tant qu'elle est vue."],
    _lock, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_lock_live", "CHECKBOX",
    ["Le marqueur suit la cible", "Coché : tant que la source voit la cible, le marqueur se déplace avec elle (une fois par seconde). Décoché : il reste où la cible a été marquée."],
    _lock, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_lock_grace", "SLIDER",
    ["Délai avant cible perdue", "Secondes sans voir la cible (sortie du champ de la caméra, masquée, hors portée, drone hors liaison) avant qu'elle soit déclarée perdue."],
    _lock, [1, 30, 5, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_lock_lost", "LIST",
    ["Cible perdue", "Ce qui se passe quand la cible n'est plus vue. Le calque est le cadre LOCK dessiné sur l'image ; le marqueur est celui de la carte (et d'Athena)."],
    _lock, [[0, 1, 2, 3], ["Calque retiré, marqueur gardé à la dernière position", "Calque retiré, marqueur effacé après le délai ci-dessous", "Calque et marqueur effacés aussitôt", "Tout gardé (cadre gris à la dernière position)"], 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_lock_keep", "SLIDER",
    ["Durée de vie d'un marqueur perdu", "Secondes avant d'effacer le marqueur d'une cible perdue (réglage « effacé après le délai ») ou neutralisée."],
    _lock, [30, 1800, 300, 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_lock_reacquire", "CHECKBOX",
    ["Raccrochage automatique", "Une cible perdue qui revient dans le champ est raccrochée seule (même numéro LOCK). Décoché : il faut la marquer à nouveau."],
    _lock, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_lock_range", "SLIDER",
    ["Portée du marquage au téléphone", "Distance maximale en mètres pour marquer et suivre une cible à l'appareil photo du téléphone (la caméra du drone garde sa portée de capteur)."],
    _lock, [100, 2000, 800, 0], 1] call CBA_fnc_addSetting;

// Extraction de données au câble DL-7 (app Extraction, modules Zeus / Eden « ATAK · Données »).
private _data = ["COMSPEC", "ATAK · Extraction de données"];
["comspec_atak_native_data_cable", "CHECKBOX",
    ["Câble DL-7 obligatoire", "Coché : il faut l'objet « Câble de données DL-7 » dans l'équipement pour se brancher sur un support. Décoché : le téléphone suffit."],
    _data, false, 1] call CBA_fnc_addSetting;
["comspec_atak_native_data_length", "SLIDER",
    ["Longueur du câble (m)", "Distance au support au-delà de laquelle le câble est arraché (transfert coupé, fichiers déjà reçus gardés)."],
    _data, [1.5, 10, 3, 1], 1] call CBA_fnc_addSetting;
["comspec_atak_native_data_speed", "SLIDER",
    ["Vitesse des transferts", "Multiplie la vitesse de téléchargement et de forçage réglée par le mission maker (2 = deux fois plus rapide)."],
    _data, [0.25, 4, 1, 2], 1] call CBA_fnc_addSetting;
// Serveur de l'extraction : branché dès le preInit (les modules Eden posent leurs données au démarrage).
if (isServer) then { ["comspec_atak_native_dataSrv", { _this call comspec_atak_native_fnc_dataServer; }] call CBA_fnc_addEventHandler; };
// Passerelles de véhicule (fn_vehicleGateway) et messages courts du serveur vers des joueurs.
if (isServer) then { ["comspec_atak_native_gwSrv", { _this call comspec_atak_native_fnc_vehicleGateway; }] call CBA_fnc_addEventHandler; };
if (hasInterface) then { ["comspec_atak_native_note", { params [["_t", ""]]; ["INFO", _t, 5, 40] call comspec_atak_native_fnc_notify; }] call CBA_fnc_addEventHandler; };

// Données médicales affichées par l'ATAK : décocher masque la donnée (ou l'endroit) pour tous, sans choix possible (fn_medShow).
private _med = ["COMSPEC", "ATAK · Données médicales"];
{
    _x params ["_k", "_label"];
    [format ["comspec_atak_native_med_%1", _k], "CHECKBOX",
        [format ["Afficher : %1", _label], "Décoché : jamais affiché dans l'ATAK (app Médical, moniteur, liaison NFC et rapports, carte, alertes), pour tous les joueurs. Coché : chaque joueur peut encore le masquer dans Réglages > Réalisme."],
        _med, true, 1] call CBA_fnc_addSetting;
} forEach ((["fields"] call comspec_atak_native_fnc_medShow) + (["contexts"] call comspec_atak_native_fnc_medShow));

// Socle de recharge CS-10 (fn_dockServer).
private _dock = ["COMSPEC", "ATAK · Socle de recharge"];
["comspec_atak_native_dock_rate", "SLIDER",
    ["Vitesse de charge (% par minute)", "Charge rendue à chaque téléphone posé sur un socle CS-10. 1,5 % par minute : un téléphone vide est plein en un peu plus d'une heure."],
    _dock, [0.5, 10, 1.5, 1], 1] call CBA_fnc_addSetting;
["comspec_atak_native_dock_offline", "CHECKBOX",
    ["La charge continue entre deux parties", "Un téléphone laissé sur un socle se charge aussi pendant que le serveur est éteint (temps réel écoulé)."],
    _dock, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_dock_persist", "CHECKBOX",
    ["Mémoriser les socles posés", "Les socles posés avec le kit sont recréés à leur place au lancement suivant sur la même carte, avec leurs téléphones. Les téléphones des socles de la mission (Eden, Zeus) sont toujours mémorisés."],
    _dock, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_dock_anyone", "CHECKBOX",
    ["Tout le monde peut reprendre n'importe quel téléphone", "Sinon seul le propriétaire (identifiant Steam) reprend son téléphone ; Zeus et l'admin le peuvent toujours."],
    _dock, false, 1] call CBA_fnc_addSetting;
private _team = ["COMSPEC", "ATAK · Équipes et suivi"];
["comspec_atak_native_aircrew", "CHECKBOX",
    ["ATAK pour les équipages d'aéronef", "Pilote, copilote et postes de tourelle d'un hélicoptère ou d'un avion ont l'ATAK par la tablette de bord, même sans téléphone porté, téléphone vide ou cassé. Il se range en quittant l'appareil."],
    _team, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_vehicle_terminal", "LIST",
    ["Tablette des véhicules", "Conducteur, tireur, chef de bord et tourelles d'un véhicule terrestre ou d'un bateau ont l'ATAK sur une tablette durcie de bord, sans téléphone porté. « Kit » : seulement les véhicules où le kit terminal a été installé (action ACE)."],
    _team, [["all", "kit", "off"], ["Tous les véhicules", "Avec le kit installé", "Aucun"], 0], 1] call CBA_fnc_addSetting;
["comspec_atak_native_screen_glow", "CHECKBOX",
    ["Lueur des écrans la nuit", "Un téléphone allumé de nuit éclaire son porteur et se voit de loin (encore plus aux JVN). Mode nuit, véhicule et filtre d'écran JVN la réduisent."],
    _team, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_screen_time", "CHECKBOX",
    ["Temps d'écran et temps par rôle", "Compte le temps du téléphone allumé (en main, porté, par app) et le temps de jeu par rôle (pilote, chef d'équipe…). Visible dans l'app Temps d'écran et envoyé à Athena (fiche du membre, back-office)."],
    _team, true, 1] call CBA_fnc_addSetting;
// App Plan (carte de briefing) : qui modifie le plan partagé du camp ; le serveur seul l'écrit (fn_planServer).
["comspec_atak_native_plan_edit", "LIST",
    ["Plan de briefing : édition", "Qui peut dessiner sur le plan partagé du camp (app Plan). Les autres le voient et préparent leur brouillon, ajouté ensuite par un chef."],
    _team, [[0, 1, 2], ["Tout le monde", "Chefs de groupe, Zeus et admins", "Zeus et admins seulement"], 1], 1] call CBA_fnc_addSetting;
if (isServer) then { ["comspec_atak_native_planSrv", { _this call comspec_atak_native_fnc_planServer; }] call CBA_fnc_addEventHandler; };

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
