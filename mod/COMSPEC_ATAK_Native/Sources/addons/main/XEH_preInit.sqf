#include "script_component.hpp"
private _perfT0 = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_PerfPreInitAt", diag_tickTime];
COMSPEC_ATAK_UI_GENERATION = "native-rsc-v1";
// Plancher des petites tailles de texte, recalculé par fn_layoutGet (0 tant qu'aucun écran n'est dessiné).
COMSPEC_ATAK_FontMin = 0;
COMSPEC_ATAK_FontBody = 0; // taille de base du texte structuré des pages (fn_layoutGet, fn_fontApply)
// Police du téléphone (Réglages > Police) : familles courante, grasse et légère, avant tout dessin.
[] call comspec_atak_native_fnc_fontSet;
// Thème (fn_theme, 3.1.0) : jetons de couleurs COMSPEC_ATAK_Th posés avant tout dessin (pages et codes compilés).
[] call comspec_atak_native_fnc_theme;
// Constantes posées par chaque machine (preInit tourne partout) : rien à diffuser. En public, chaque arrivée
// renvoyait les deux valeurs à tous les joueurs et à la file JIP.
missionNamespace setVariable ["COMSPEC_ATAK_UI_GENERATION", COMSPEC_ATAK_UI_GENERATION];
missionNamespace setVariable ["COMSPEC_ATAK_NativeVersion", VERSION_STR];
// Versions compilées dans ce code : fn_integrityCheck les compare à la config chargée, à la DLL et au site.
missionNamespace setVariable ["COMSPEC_ATAK_CodeVersion", VERSION_STR];
missionNamespace setVariable ["COMSPEC_ATAK_LinkExpected", LINK_VERSION_STR];
// Version complète pour les humains (Réglages, Debug, journal, support) : « 2.0.1.AB - Denver · 2026.10.18 a1b2c3d ».
// Les comparaisons de versions n'utilisent que COMSPEC_ATAK_NativeVersion (MAJEUR.MINEUR.CORRECTIF).
missionNamespace setVariable ["COMSPEC_ATAK_NativeName", VERSION_NAME];
missionNamespace setVariable ["COMSPEC_ATAK_NativeBuild", VERSION_NAME + " · " + ([BUILD_STAMP, "dev"] select (BUILD_STAMP isEqualTo ""))];diag_log format ["[COMSPEC ATAK NATIVE][INFO][BOOT] COMSPEC ATAK %1 (COMSPEC Link attendu %2)", missionNamespace getVariable "COMSPEC_ATAK_NativeBuild", LINK_VERSION_STR];
diag_log "[COMSPEC ATAK NATIVE][BOOT][CANARY] native_client_v1_4_0_loaded";
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] UI generation: native-rsc-v1";
[] call comspec_atak_native_fnc_stateInit;
// File d'image du mod (fn_frameQueue) : rappels « image suivante » et « attendre puis exécuter » hors du gestionnaire de
// CBA, pour qu'une erreur de script dans un rappel ne soit jamais rejouée à chaque image (téléphone et Arsenal perdus).
["init"] call comspec_atak_native_fnc_frameQueue;
// Boucles périodiques des postInit (client et serveur, une cinquantaine) : chacune démarre avec un décalage tiré au
// hasard (5 s au plus) au lieu de toutes à la même image. Avant, les boucles de 1, 2, 5 et 10 s tombaient ensemble à
// chaque multiple de 10 s (à-coup régulier) et toutes partaient dans les premières secondes de l'arrivée en jeu.
// Mêmes paramètres que CBA_fnc_addPerFrameHandler ; ne renvoie pas d'identifiant (aucun appelant ne le garde).
COMSPEC_ATAK_PfhSpread = {
    params ["_code", ["_delay", 0], ["_args", []]];
    if (_delay <= 0) exitWith { [_code, _delay, _args] call CBA_fnc_addPerFrameHandler; nil };
    [{ _this call CBA_fnc_addPerFrameHandler; }, [_code, _delay, _args], random (_delay min 5)] call comspec_atak_native_fnc_waitAndExec;
    nil
};

// Langue du mod (par joueur) : téléphone, menus CBA COMSPEC (ATAK, Overwatch, SSE) et SSE. Sans sous-catégorie : CBA
// range ces réglages en tête de la liste COMSPEC, au-dessus des sections « ATAK · … ». Titre bilingue, jamais traduit.
// Le code reste en français, fn_tr traduit à l'affichage (dictionnaire i18n/en.txt) ; changer la langue redessine le
// téléphone et retraduit les menus CBA COMSPEC (fn_menuRelang), sans relancer la mission.
// Langues : English (par défaut depuis 2.1.0, TTRD), Français, celles des mods de traduction chargés (CfgComspecLanguages,
// fn_langList), puis Auto. Un joueur qui avait déjà choisi une langue la garde (valeur enregistrée par CBA).
private _langs = ([] call comspec_atak_native_fnc_langList) select { (_x select 0) isNotEqualTo "en" };
["comspec_atak_native_language", "LIST",
    ["Langue / Language", "COMSPEC mod language: ATAK phone, COMSPEC CBA menus and SSE. English by default; Auto follows the game language. More languages appear here when a COMSPEC translation mod is loaded. / Langue du mod COMSPEC : téléphone ATAK, menus CBA COMSPEC et SSE. Anglais par défaut ; Auto suit la langue du jeu. D'autres langues apparaissent ici quand un mod de traduction COMSPEC est chargé."],
    ["COMSPEC", ""], [["en", "fr"] + (_langs apply { _x select 0 }) + ["auto"], ["English", "Français"] + (_langs apply { _x select 1 }) + ["Auto (game language / langue du jeu)"], 0], 0,
    { [true] call comspec_atak_native_fnc_langApply; }] call CBA_fnc_addSetting;
[] call comspec_atak_native_fnc_langApply;
// Police du téléphone (par joueur), aussi dans le téléphone (Réglages > Affichage > Police). Réglage CBA, lisible dans
// la police du jeu. Polices embarquées retirées en 1.84.0 (mal affichées en jeu) : Roboto Condensed par défaut.
["comspec_atak_native_font", "LIST",
    ["Police du téléphone / Phone font", "Police de tout le téléphone ATAK : Roboto Condensed (par défaut) ou Purista, polices du jeu ; Barlow Condensed embarquée, en test. / Font for the whole ATAK phone: Roboto Condensed (default) or Purista, the game's own fonts; embedded Barlow Condensed, under test."],
    ["COMSPEC", ""], [["roboto", "purista", "barlow2"], ["Roboto Condensed", "Purista", "Barlow Condensed (test)"], 0], 0,
    {
        params ["_v"];
        [_v] call comspec_atak_native_fnc_fontSet;
        // Téléphone ouvert : redessiné dans la nouvelle police.
        [{
            private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""];
            if (_p isNotEqualTo "" && {!isNull ([] call comspec_atak_native_fnc_display)}) then { [] call comspec_atak_native_fnc_layoutApply; [_p] call comspec_atak_native_fnc_pageRender; };
        }] call comspec_atak_native_fnc_nextFrame;
    }] call CBA_fnc_addSetting;
// Langue dans laquelle les menus CBA COMSPEC sont déclarés (fn_addSetting, fn_trMenu) : fn_menuRelang ne retraduit
// que si elle change.
missionNamespace setVariable ["COMSPEC_ATAK_MenuLang", missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]];

// Intro au démarrage du jeu (fn_introSplash), désactivée par défaut (TTRD, 1.67.0 : buggée). Les deux réglages sont
// recopiés dans le profil : l'intro joue avant que CBA lise ses réglages.
["comspec_atak_native_intro_enabled", "CHECKBOX",
    ["Intro au lancement du jeu", "Décoché par défaut. Coché : le logo COMSPEC s'affiche sur l'écran de chargement d'Arma. Prend effet au prochain lancement du jeu."],
    ["COMSPEC", "ATAK · Intro"], false, 0,
    { profileNamespace setVariable ["COMSPEC_ATAK_IntroEnabled", _this]; [] call comspec_atak_native_fnc_saveProfile; }] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_intro_sound", "CHECKBOX",
    ["Son de l'intro", "Petit jingle joué avec le logo COMSPEC au lancement d'Arma (seulement si l'intro est activée). Prend effet au prochain lancement du jeu."],
    ["COMSPEC", "ATAK · Intro"], true, 0,
    { profileNamespace setVariable ["COMSPEC_ATAK_IntroSound", _this]; [] call comspec_atak_native_fnc_saveProfile; }] call comspec_atak_native_fnc_addSetting;

// Check de démarrage (fn_startupCheck), par joueur : résultat du contrôle du mod posté dans le salon de debug du Discord Athena.
["comspec_atak_native_startup_report", "CHECKBOX",
    ["Envoyer le rapport de démarrage", "Coché par défaut. Une seule fois, au premier lancement : quand vous vous connectez à Athena en jeu (ou si la connexion échoue), un rapport sur l'état du mod est posté dans le salon de debug du Discord Athena, avec votre pseudo et votre indicatif : versions, DLL, connexion, numéro et IMEI du téléphone, appareil porté, FPS, erreurs. Ça aide l'équipe à vous dépanner. Pour le renvoyer : menu Échap > Dépannage liaison > Renvoyer le check de démarrage. Aucun UID Steam ni adresse IP. Décochez pour ne rien envoyer."],
    ["COMSPEC", "ATAK · Debug"], true, 0] call comspec_atak_native_fnc_addSetting;
// Garde-fou du téléphone (fn_deviceGuard), par joueur : réparation à chaque lancement, pour un téléphone qui plante encore.
["comspec_atak_native_purge_camlinks", "CHECKBOX",
    ["Réparer mes données à chaque lancement", "Décoché par défaut. À chaque lancement de mission, le téléphone met de côté ses caches et efface sa dernière page au lieu de les rejouer. Ne touche jamais au mode compromis ni à l'identifiant de l'appareil."],
    ["COMSPEC", "ATAK · Debug"], false, 0] call comspec_atak_native_fnc_addSetting;
// Mode profilage (fn_profiler), par joueur, désactivé par défaut : temps de chaque tâche de fond dans le RPT toutes les 10 s.
["comspec_atak_native_profiling", "CHECKBOX",
    ["Profilage (saccades)", "Décoché par défaut. Coché : toutes les 10 s, le journal du jeu (fichier RPT) note le temps pris par chaque tâche de fond (COMSPEC et autres mods), les pics de plus de 5 ms et chaque saccade avec ce qui tournait à ce moment. Cochez-le si le jeu saccade, jouez 10 minutes, puis envoyez le RPT à l'équipe. Coûte un peu de performance : décochez ensuite."],
    ["COMSPEC", "ATAK · Debug"], false, 0,
    {
        if (!hasInterface) exitWith {};
        [{ time > 0 }, { [["stop", "start"] select _this] call comspec_atak_native_fnc_profiler; }, _this isEqualTo true] call CBA_fnc_waitUntilAndExecute;
    }] call comspec_atak_native_fnc_addSetting;

// Liaison Athena proposée à la première sortie du téléphone sans compte (fn_displayLoad), par joueur.
["comspec_atak_native_link_offer", "CHECKBOX",
    ["Proposer la liaison Athena à la sortie du téléphone", "Coché par défaut. Tant que votre jeu n'est pas relié à un compte Athena, la première sortie du téléphone de chaque mission ouvre l'app Athena avec un code à saisir sur le site. Décochez pour garder votre dernière app à l'ouverture."],
    ["COMSPEC", "ATAK · Interface"], true, 0] call comspec_atak_native_fnc_addSetting;

// Taille du téléphone en plein écran (par joueur) : 60 à 100 %, centré. Aussi dans Réglages > Emplacement du téléphone.
["comspec_atak_native_full_scale", "SLIDER",
    ["Taille du téléphone en main", "Taille du téléphone quand vous l'avez en main (plein écran). 100 % = taille normale ; en dessous, il rétrécit en restant centré, texte et boutons compris."],
    ["COMSPEC", "ATAK · Affichage"], [0.6, 1, 1, 0, true], 0,
    { if !(isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_layoutApply; }; }] call comspec_atak_native_fnc_addSetting;
// Reflet du verre de l'écran (4.2.1, fn_screenSurface) : par joueur, activé par défaut ; aussi dans Réglages > Thème et écran.
["comspec_atak_native_glass_glare", "CHECKBOX",
    ["Reflet du verre de l'écran", "Léger reflet de la vitre sur l'écran du téléphone, plus faible la nuit ; il ne gêne ni la lecture ni la carte. Désactivé : écran sans reflet. Exemple : en plein jour, une bande claire très discrète traverse le haut gauche de l'écran. Aussi dans Réglages > Thème et écran."],
    ["COMSPEC", "ATAK · Affichage"], true, 0,
    { if !(isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_deviceOverlay; }; }] call comspec_atak_native_fnc_addSetting;

// Animation de sortie et de rangement du téléphone en main (par joueur, 4.2.1, fn_fx « phoneIn » / « phoneOut »).
// Aussi dans Réglages > Animations et sons du téléphone.
["comspec_atak_native_phone_anim", "CHECKBOX",
    ["Animation de sortie et de rangement", "Coché par défaut. Le téléphone en main monte en fondu quand vous le sortez et redescend en s'effaçant quand vous le rangez, en 0,2 s. Exemple : Ctrl+Maj+U depuis le jeu, le téléphone glisse de quelques centimètres vers le haut en apparaissant. Décochez pour qu'il apparaisse et disparaisse d'un coup. La miniature portée n'est jamais animée ; Échap, la mort, l'inconscience ou un changement de véhicule le rangent toujours tout de suite."],
    ["COMSPEC", "ATAK · Affichage"], true, 0] call comspec_atak_native_fnc_addSetting;

// HUD des autres mods (boussole F-PANO / ECOTI, température, heure...) masqué sous le téléphone (par joueur, fn_foreignHud).
// Heure des alertes (fn_clockFmt) : heure de la mission, du PC, UTC, ou PC et UTC. Par joueur.
["comspec_atak_native_alert_clock", "LIST",
    ["Heure des alertes", "Heure affichée sur les alertes du téléphone (app Alertes, notifications, alertes plein écran, MEDEVAC, alertes santé). Heure de la mission (par défaut) : l'horloge du jeu. Heure locale : l'horloge de votre PC. Zulu : l'heure UTC, suivie de Z. Locale et Zulu : les deux. Les alertes déjà reçues gardent leur heure."],
    ["COMSPEC", "ATAK · Affichage"], [["game", "local", "zulu", "both"], ["Heure de la mission", "Heure locale (PC)", "Zulu (UTC)", "Locale et Zulu"], 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hide_foreign_hud", "CHECKBOX",
    ["Cacher le HUD des autres mods derrière le téléphone", "Coché par défaut. Quand le téléphone est à l'écran (porté ou en main), les infos que d'autres mods affichent au même endroit (boussole F-PANO / ECOTI, température, heure, distance...) sont cachées pour ne pas passer par-dessus. Elles reviennent dès que vous rangez le téléphone. Décochez pour toujours les voir."],
    ["COMSPEC", "ATAK · Affichage"], true, 0,
    { missionNamespace setVariable ["COMSPEC_ATAK_ForeignNext", 0]; }] call comspec_atak_native_fnc_addSetting;

// Réponses des IA : bulle de texte au-dessus de la tête de l'IA qui parle (par joueur, fn_aiBubble). Décoché : toast d'Arma.
["comspec_atak_native_ai_bubble", "CHECKBOX",
    ["Bulles de réponse des IA", "Coché par défaut. Ce que disent les IA (réponse à un ordre, parole radio, contrôle d'identité) s'affiche en texte au-dessus de leur tête, si vous êtes à portée et si vous les voyez. Hors de vue, ou décoché : vous la recevez en notification du jeu."],
    ["COMSPEC", "ATAK · Affichage"], true, 0,
    { if !(_this) then { ["clear"] call comspec_atak_native_fnc_aiBubble; }; }] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ai_bubble_range", "SLIDER",
    ["Portée des bulles de réponse", "Distance maximale, en mètres, à laquelle vous voyez la réponse au-dessus de la tête d'une IA."],
    ["COMSPEC", "ATAK · Affichage"], [10, 150, 50, 0], 0] call comspec_atak_native_fnc_addSetting;
// Étiquettes de la carte du téléphone (par joueur, enregistrées dans le profil par CBA), aussi dans Réglages > Carte
// et CALQUES : style des noms de repères (fn_mapMarkerText) et des lieux (fn_mapPlaceText), catégories masquables.
["comspec_atak_native_map_marker_style", "LIST",
    ["Style des noms de marqueurs", "Présentation des noms de marqueurs et des cibles verrouillées (LOCK) sur la carte du téléphone. Plaque (par défaut) : étiquette sombre bordée de la couleur du marqueur. Pilule : étiquette sombre aux bords arrondis. Étiquette reliée : encadré à côté du marqueur, relié par un trait, avec distance et azimut. Contour : texte blanc avec contour noir. Classique : texte Arma d'origine."],
    ["COMSPEC", "ATAK · Carte"], [["plate", "pill", "card", "outline", "classic"], ["Plaque", "Pilule", "Étiquette reliée", "Contour", "Classique (Arma)"], 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_map_place_style", "LIST",
    ["Style des noms de lieux", "Présentation des villes, villages et lieux-dits sur la carte du téléphone. Tactique (par défaut) : majuscules en gras avec contour. Étiquette : étiquette sombre avec un losange de couleur. Discret : petit texte clair. Classique : comme la carte d'Arma. Capitales : petites capitales avec un point turquoise, le rendu de Tactique sur le fond Ardoise."],
    ["COMSPEC", "ATAK · Carte"], [["tactical", "tag", "quiet", "classic", "small"], ["Tactique", "Étiquette", "Discret", "Classique (Arma)", "Capitales"], 0], 0] call comspec_atak_native_fnc_addSetting;
// Diffusion des marqueurs posés depuis le téléphone (fn_markerNet) : canaux d'Arma ou réseau ATAK uniquement.
["comspec_atak_native_marker_net", "LIST",
    ["Diffusion des marqueurs ATAK", "Carte d'Arma (par défaut) : un marqueur posé sur le téléphone est un marqueur Arma du canal choisi, donc aussi sur la carte d'Arma de tous les joueurs du canal, avec ou sans ATAK, avec ou sans réseau. Réseau ATAK uniquement : il n'apparaît que sur les téléphones des joueurs du canal qui reçoivent le réseau (sans signal, il arrive au retour du réseau), jamais sur la carte d'Arma des autres. Global vaut alors Camp. Les canaux d'Arma peuvent rester fermés aux marqueurs."],
    ["COMSPEC", "ATAK · Carte"], [["arma", "atak"], ["Carte d'Arma (canaux du jeu)", "Réseau ATAK uniquement"], 0], 1] call comspec_atak_native_fnc_addSetting;
// Pose des marqueurs (fn_markerPlace) : menu ouvert par « Mettre un marqueur » (clic droit) et le double clic, canal.
["comspec_atak_native_marker_menu", "LIST",
    ["Fenêtre de création de marqueur", "Ce qui s'ouvre quand vous faites « Mettre un marqueur » (clic droit) ou un double-clic sur la carte. Palette d'icônes (par défaut) : vous choisissez une icône, sa couleur, sa taille et le canal, puis VALIDER la pose à l'endroit cliqué. Éditeur complet : formulaire avec titre, description, type, couleur, taille, orientation, opacité et canal."],
    ["COMSPEC", "ATAK · Carte"], [["palette", "editor"], ["Palette d'icônes", "Éditeur complet"], 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_marker_channel", "LIST",
    ["Canal des marqueurs", "Canal par défaut des marqueurs posés depuis le téléphone (palette, traits, LZ, éditeur), comme les canaux de la carte d'Arma. Global (par défaut) : visibles par tous les joueurs. Modifiable à chaque pose (ligne CANAL de la palette)."],
    ["COMSPEC", "ATAK · Carte"], [[0, 1, 2, 3, 4, 5], ["Global", "Camp", "Commandement", "Groupe", "Véhicule", "Direct"], 0], 0] call comspec_atak_native_fnc_addSetting;
// Nom et numéro des marqueurs posés depuis le téléphone (fn_markerDrop).
["comspec_atak_native_marker_name", "EDITBOX",
    ["Nom des marqueurs posés", "Modèle du nom donné aux marqueurs posés depuis la palette, quand le champ TEXTE de la palette est vide. Vide (par défaut) : numéro de repère (« REP 3 »). Mots remplacés : {camp} {type} {n} (numéro) {grille} {indicatif} {heure}. Exemple : {indicatif} {type} {n}."],
    ["COMSPEC", "ATAK · Carte"], "", 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_marker_numbering", "LIST",
    ["Numérotation des marqueurs", "À la suite (par défaut) : chaque marqueur prend le numéro suivant, même si on en a effacé. Premier numéro libre : un numéro effacé est repris (après 1 et 3, le suivant est 2)."],
    ["COMSPEC", "ATAK · Carte"], [["next", "fill"], ["À la suite", "Premier numéro libre"], 0], 0] call comspec_atak_native_fnc_addSetting;
{
    _x params ["_k", "_t", "_h"];
    [_k, "CHECKBOX", [_t, _h], ["COMSPEC", "ATAK · Carte"], true, 0] call comspec_atak_native_fnc_addSetting;
} forEach [
    ["comspec_atak_native_map_show_marker_text", "Noms des marqueurs", "Affiche le nom des marqueurs et des cibles verrouillées sur la carte du téléphone. Décoché, seules les icônes restent. Aussi réglable dans CALQUES."],
    ["comspec_atak_native_map_show_cities", "Noms des villes", "Affiche les villes et capitales sur la carte du téléphone. Aussi réglable dans CALQUES."],
    ["comspec_atak_native_map_show_villages", "Noms des villages", "Affiche les villages sur la carte du téléphone, à partir d'un zoom moyen. Aussi réglable dans CALQUES."],
    ["comspec_atak_native_map_show_locals", "Noms des lieux-dits", "Affiche les lieux-dits et les noms en mer sur la carte du téléphone, en zoom rapproché. Aussi réglable dans CALQUES."],
    ["comspec_atak_native_map_show_sites", "Noms des sites militaires", "Affiche les aéroports et les sites militaires (bases, camps, casernes...) sur la carte du téléphone, en zoom rapproché. Aussi réglable dans CALQUES."]
];

// App Routes (fn_routeAction) : partage, carte d'Arma et Athena, nombre d'étapes, rayon d'arrivée, vue 3D.
["comspec_atak_native_route_share", "CHECKBOX",
    ["Partage des routes", "Les joueurs peuvent envoyer leurs routes à leur équipe, leur groupe ou leur camp (app Routes), par le réseau du téléphone. Décoché : chaque route reste sur son téléphone."],
    ["COMSPEC", "ATAK · Routes"], true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_route_arma", "CHECKBOX",
    ["Routes sur la carte d'Arma et Athena", "Option « Carte d'Arma et Athena » d'une route : tracé et étapes posés en marqueurs sur le canal du groupe (du camp si la route est partagée au camp) et envoyés au site Athena comme les autres dessins. Décoché : les routes restent sur les téléphones."],
    ["COMSPEC", "ATAK · Routes"], true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_route_fpano", "CHECKBOX",
    ["Route suivie dans F-PANO ECOTI", "Si le mod F-PANO ECOTI est chargé : les 8 prochaines étapes de la route suivie s'affichent sous JVN dans sa vue, avec le nom et la distance. Sans ce mod, ce réglage ne fait rien."],
    ["COMSPEC", "ATAK · Routes"], true, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_route_max", "SLIDER",
    ["Étapes par route au plus", "Nombre d'étapes d'une route (40 par défaut)."],
    ["COMSPEC", "ATAK · Routes"], [5, 80, 40, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_route_arrive", "SLIDER",
    ["Rayon d'arrivée à une étape (à pied, m)", "Distance à laquelle « Suivre la route » annonce l'étape atteinte et passe à la suivante (25 m par défaut). Multiplié en véhicule (x2,4), sur l'eau (x3) et en vol (x8)."],
    ["COMSPEC", "ATAK · Routes"], [5, 100, 25, 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_route_3d", "CHECKBOX",
    ["Affichage 3D des routes (JVN uniquement)", "Option « Affichage 3D » d'une route : pendant « Suivre la route » et JVN allumée seulement, trait et étapes restantes affichés dans le monde (à moins de 1,5 km) ; une étape atteinte disparaît aussitôt. Décoché : jamais de 3D, même si une route la demande."],
    ["COMSPEC", "ATAK · Routes"], true, 0] call comspec_atak_native_fnc_addSetting;
// App Zones (fn_zoneAction, fn_zoneGen) : partage, carte d'Arma et Athena, plafonds du découpage, contacts, curseur.
["comspec_atak_native_zones_share", "CHECKBOX",
    ["Partage des plans de zones", "Les joueurs peuvent envoyer leurs plans de zones (app Zones) à leur équipe, leur groupe ou leur camp, avec les états et signalements des zones, par le réseau du téléphone. Décoché : chaque plan reste sur son téléphone."],
    ["COMSPEC", "ATAK · Plan / Zones"], true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zones_arma", "CHECKBOX",
    ["Zones sur la carte d'Arma et Athena", "Coché par défaut (2.9.38) : un plan a son option « Carte d'Arma et Athena » allumée ; cases, contours, traits, étiquettes, axes et points de contrôle deviennent des marqueurs de la carte d'Arma, visibles de tout le canal (le camp pour un plan partagé au camp), comme une carte de briefing, et partent au site Athena si le plan est partagé. Décoché (comme en 2.9.36) : les plans restent sur les téléphones."],
    ["COMSPEC", "ATAK · Plan / Zones"], true, 1] call comspec_atak_native_fnc_addSetting;
// Portée d'un plan de zones ou d'une route à sa création (2.9.38 : tout le camp par défaut, renvoyé à chaque
// modification validée) ; chaque plan ou route garde sa portée modifiable.
["comspec_atak_native_plan_scope", "LIST",
    ["Portée par défaut des plans et routes", "À la création d'un plan de zones (app Zones) ou d'une route (app Routes), qui le reçoit sans cliquer PARTAGER : tout le camp (par défaut), le groupe, l'équipe, ou personne (privé, comme en 2.9.36). L'état des zones ne change toujours que par une action d'un joueur."],
    ["COMSPEC", "ATAK · Plan / Zones"], [[3, 2, 1, 0], ["Camp", "Groupe", "Équipe", "Privé"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zones_maxcells", "SLIDER",
    ["Cases de grille au plus", "Nombre de cases d'une grille générée (200 par défaut). Au-delà, prendre des cases plus grandes."],
    ["COMSPEC", "ATAK · Plan / Zones"], [20, 600, 200, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zones_maxblocks", "SLIDER",
    ["Îlots au plus", "Nombre d'îlots gardés par le découpage par les routes (les plus grands ; 80 par défaut)."],
    ["COMSPEC", "ATAK · Plan / Zones"], [10, 200, 80, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zones_armamax", "SLIDER",
    ["Zones en marqueurs d'Arma au plus", "Au-delà (60 par défaut), seules les étiquettes des zones vont sur la carte d'Arma et Athena."],
    ["COMSPEC", "ATAK · Plan / Zones"], [10, 200, 60, 0], 1] call comspec_atak_native_fnc_addSetting;
// Ancien booléen (2.9.35, coché par défaut) : remplacé par une liste ; toute ancienne valeur repart sur « Manuel ».
["comspec_atak_native_zones_contact_mode", "LIST",
    ["Contact dans une zone", "Que se passe-t-il quand un contact ennemi est posé ou signalé depuis un téléphone (marqueur ennemi, TIC, SALUTE, compte rendu CONTACT / SPOTREP) dans une zone d'un plan. Manuel (par défaut, réaliste) : rien, l'opérateur met lui-même l'état de la zone. Proposer : celui qui a posé le contact et l'auteur du plan reçoivent une question (passer la zone en « ennemi signalé » ? OUI / NON, dans l'app Zones), sans effet sans réponse. Automatique (confort) : la zone change d'état toute seule et reçoit un signalement."],
    ["COMSPEC", "ATAK · Plan / Zones"], [["MANUAL", "ASK", "AUTO"], ["Manuel", "Proposer", "Automatique"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zones_cursor", "CHECKBOX",
    ["Zone sous le curseur", "Le bandeau du curseur de la carte donne la zone survolée (nom, plan, état)."],
    ["COMSPEC", "ATAK · Plan / Zones"], true, 0] call comspec_atak_native_fnc_addSetting;
// Réglages serveur (forçables par l'admin dans les réglages CBA du serveur / de la mission).
// Tous les mods COMSPEC partagent une seule catégorie CBA « COMSPEC » ; sous-catégories « ATAK · … », « Overwatch · … », « SSE ».
private _cat = ["COMSPEC", "ATAK · Accès au téléphone"];
private _recompute = { [true] call comspec_atak_native_fnc_deviceCatalog; };
["comspec_atak_native_require_item", "CHECKBOX",
    ["Item obligatoire pour utiliser l'ATAK", "Coché par défaut : il faut avoir un téléphone dans son équipement (ItemAndroid de cTab ou équivalent d'un autre mod) pour utiliser l'ATAK. Décochez pour donner l'ATAK à tout le monde."],
    _cat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_photo_anim", "EDITBOX",
    ["Animation de l'appareil photo", "Pour mission makers. Animation jouée tant que l'appareil photo est ouvert. Par défaut (comspec_atak_native_fnc_gestureSelfie) : le personnage tient le téléphone devant son visage et la photo est prise par l'objectif arrière. Vous pouvez mettre à la place un geste (ex. gestureGo), une animation CfgMoves (ex. AmovPercMstpSrasWrflDnon_AinvPercMstpSrasWrflDnon) ou une fonction (ex. mon_fnc_photoPose, appelée avec [joueur, ""photo""]). Vide : aucune animation."],
    ["COMSPEC", "ATAK · Photo"], "comspec_atak_native_fnc_gestureSelfie", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_photo_arm", "CHECKBOX",
    ["Photo : lever le téléphone", "Réglage personnel, décoché par défaut : pendant une photo, votre bras reste baissé et votre main ne gêne pas la vue. Coché : votre personnage lève le téléphone devant lui (animation ci-dessus), et les autres joueurs le voient. En selfie, le téléphone est toujours levé."],
    ["COMSPEC", "ATAK · Photo"], false, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_selfie_anim", "EDITBOX",
    ["Animation du selfie", "Pour mission makers. Animation jouée en selfie. Par défaut : bras droit tendu, téléphone tourné vers soi. Même format que l'animation de l'appareil photo : geste, animation CfgMoves ou fonction appelée avec [joueur, ""selfie""] (puis [joueur, ""exit""] à la fermeture). Vide : même animation que l'appareil photo."],
    ["COMSPEC", "ATAK · Photo"], "comspec_atak_native_fnc_gestureSelfie", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_selfie_invert_x", "CHECKBOX",
    ["Selfie : inverser gauche / droite", "Réglage personnel, décoché par défaut. Coché : en selfie, bouger la souris à droite fait tourner le cadrage vers la gauche, et inversement."],
    ["COMSPEC", "ATAK · Photo"], false, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_selfie_invert_y", "CHECKBOX",
    ["Selfie : inverser haut / bas", "Réglage personnel, décoché par défaut. Coché : en selfie, monter la souris fait descendre le cadrage, et inversement."],
    ["COMSPEC", "ATAK · Photo"], false, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_type", "LIST",
    ["Floutage automatique des visages", "Réglage personnel. Athena floute les visages des photos que vous envoyez, dès leur réception (l'original n'est pas conservé). Flou classique (par défaut) : flou doux. Flou de mouvement : effet de bougé dans une direction. Pixelisé : mosaïque. Bande noire : carré noir sur le visage. Aucun : photo telle quelle. En mode photo, la touche B change de type pour la partie en cours."],
    ["COMSPEC", "ATAK · Photo"], [["gauss", "motion", "pixel", "black", "off"], ["Flou classique", "Flou de mouvement", "Pixelisé", "Bande noire", "Aucun"], 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_target", "LIST",
    ["Visages à flouter", "Alliés (par défaut) : seulement les visages de votre camp et des camps amis, y compris le vôtre en selfie. Tout le monde : les ennemis et les civils aussi."],
    ["COMSPEC", "ATAK · Photo"], [["allies", "all"], ["Alliés", "Tout le monde"], 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_force", "SLIDER",
    ["Flou : intensité", "De 1 à 100 (60 par défaut). Plus c'est haut, plus le flou est fort, les pixels gros ou l'effet de bougé long."],
    ["COMSPEC", "ATAK · Photo"], [1, 100, 60, 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_opacity", "SLIDER",
    ["Flou : opacité", "De 0 à 100 % (100 par défaut). À 50 %, on devine encore le visage sous le flou ou la bande noire."],
    ["COMSPEC", "ATAK · Photo"], [0, 100, 100, 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_size", "SLIDER",
    ["Flou : taille de la zone", "De 50 à 300 % (130 par défaut) : taille de la zone floutée par rapport à la tête. Au-dessus de 100 %, le cou et le casque sont aussi floutés."],
    ["COMSPEC", "ATAK · Photo"], [50, 300, 130, 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_angle", "SLIDER",
    ["Flou de mouvement : direction", "De 0 à 179° (0 par défaut = horizontal) : direction de l'effet de bougé, pour le flou de mouvement uniquement."],
    ["COMSPEC", "ATAK · Photo"], [0, 179, 0, 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blur_range", "SLIDER",
    ["Flou : distance max", "Distance maximale des visages floutés, en mètres (150 par défaut). Plus loin, un visage est trop petit pour être reconnu."],
    ["COMSPEC", "ATAK · Photo"], [20, 600, 150, 0], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_dating_black", "CHECKBOX",
    ["Photos de profil sur fond noir", "Réglage personnel, coché par défaut. Dans Tinder (profils de joueurs, HVT et SSE), la personne est montrée sur fond noir, comme en studio. Décochez pour la voir en direct, là où elle se trouve sur la carte."],
    ["COMSPEC", "ATAK · Photo"], true, 0] call comspec_atak_native_fnc_addSetting;
// Dossier des captures d'écran (fn_shotDir), par joueur : là où les DLL cherchent les photos prises par le jeu.
["comspec_atak_native_shot_dir", "EDITBOX",
    ["Dossier des captures d'écran", "Réglage personnel, vide par défaut (automatique) : le téléphone cherche vos photos dans le dossier Screenshots de votre profil Arma (Documents\Arma 3\Screenshots, ou Documents\Arma 3 - Other Profiles\(nom du profil)\Screenshots). Si vos photos ne sont jamais trouvées (profil déplacé, lancement avec -profiles, Documents déplacé par OneDrive), collez ici le chemin complet du dossier où Arma range vos captures, par exemple D:\Arma\Profils\Screenshots. Dossier de ce PC uniquement (pas de dossier réseau) ; le téléphone n'y fait que lire. Pour voir le dossier utilisé : cochez « Vérifier le dossier des captures » ci-dessous, ou Réglages du téléphone › À propos."],
    ["COMSPEC", "ATAK · Photo"], "", 0,
    { [{ ["apply"] call comspec_atak_native_fnc_shotDir; }] call comspec_atak_native_fnc_nextFrame; }] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_shot_check", "CHECKBOX",
    ["Vérifier le dossier des captures", "Cochez puis validez (OK) : le dossier où le téléphone cherche vos photos s'affiche à l'écran, avec le nombre d'images et la dernière capture trouvée. La case se décoche toute seule."],
    ["COMSPEC", "ATAK · Photo"], false, 0,
    {
        if !(_this isEqualTo true) exitWith {};
        [{
            ["comspec_atak_native_shot_check", false, 0, "client"] call CBA_settings_fnc_set;
            ["check", true] spawn comspec_atak_native_fnc_shotDir;
        }] call comspec_atak_native_fnc_nextFrame;
    }] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_gesture_finger", "CHECKBOX",
    ["Geste du doigt d'honneur", "Décoché par défaut. Coché : les touches « Doigt d'honneur » et « Doigt d'honneur libre (maintenir) » font le geste. À assigner dans Options > Contrôles > Configurer les addons > COMSPEC ATAK."],
    ["COMSPEC", "ATAK · Gestes"], false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_battery_items", "EDITBOX",
    ["Batteries de rechange", "Items (noms de classe exacts, séparés par des virgules) qui servent de batterie de rechange : le téléphone repart à 100 % et l'item est consommé. La « Batterie ATAK » (COMSPEC_ATAK_Battery) marche toujours."],
    _cat, "COMSPEC_ATAK_Battery,ACE_UAVBattery", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_battery_patterns", "EDITBOX",
    ["Batteries d'autres mods (mots-clés)", "Tout item dont le nom de classe contient un de ces mots sert aussi de batterie de rechange (piles, batteries de drone...). Séparés par des virgules ; videz le champ pour n'accepter que la liste ci-dessus."],
    _cat, "battery,batterie,pile", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_device_patterns", "EDITBOX",
    ["Téléphones d'autres mods (mots-clés)", "Tout item dont le nom de classe contient un de ces mots compte comme un téléphone ATAK. Séparés par des virgules. Par défaut : android,atak,smartphone."],
    _cat, "android,atak,smartphone", 1, _recompute] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_device_items", "EDITBOX",
    ["Téléphones acceptés en plus", "Noms de classe exacts, séparés par des virgules. Utile pour un téléphone de votre pack d'équipement qui n'a pas « android » dans son nom."],
    _cat, "", 1, _recompute] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_device_tablets", "CHECKBOX",
    ["Les tablettes et MicroDAGR comptent aussi", "Coché : la tablette cTab et les MicroDAGR (cTab et ACE) donnent aussi accès à l'ATAK."],
    _cat, false, 1, _recompute] call comspec_atak_native_fnc_addSetting;

// Objet exigé par type d'interface (Réglages > Personnaliser > Modèle) : vide = n'importe quel terminal reconnu.
// Classes exactes ou mots cherchés dans la classe, séparés par des virgules ; sans l'objet, le modèle n'est pas
// proposé et le téléphone reprend le S7 (la tablette de bord des véhicules n'est pas concernée).
private _uiCat = ["COMSPEC", "ATAK · Modèle et items requis"];
// Modèle du téléphone en liste déroulante (retour de joueur : « l'objet exigé ne change pas le modèle »). Réglage
// joueur, le même que Réglages > Personnaliser > Modèle (COMSPEC_ATAK_ProfSet tient les deux à jour) ; vide = choix du
// téléphone conservé. Le S7 reste le défaut et reprend si l'objet exigé pour le modèle n'est pas porté.
["comspec_atak_native_phone_model", "LIST",
    ["Modèle du téléphone", "Utilisé seulement si vous ne portez pas un téléphone COMSPEC. Avec un Samsung S7, un iPhone ou un autre modèle dans les poches, c'est cet objet qui s'affiche, couleur comprise."],
    _uiCat, [["", "s7", "s23", "ip17", "iphone", "rugged", "sonim", "keys"],
        ["Celui choisi dans le téléphone", "Durci S7", "Samsung S23", "iPhone 17 Pro", "V2 Enhanced", "Xiaomi X3D", "Sonim XP8", "Tablette"], 0], 0,
    {
        params ["_v"];
        if (_v isEqualTo "" || {_v isEqualTo (profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"])}) exitWith {};
        profileNamespace setVariable ["COMSPEC_ATAK_PhoneModel", _v];
        [] call comspec_atak_native_fnc_saveProfile;
        if (!isNull player && {_v isNotEqualTo "s7"} && {!([_v, player] call (missionNamespace getVariable ["COMSPEC_ATAK_ModelOk", { true }]))}) then {
            // Dans le téléphone seulement, rien sur l'écran du jeu.
            ["WARNING", "ATAK : il vous manque l'item demandé pour ce modèle, le Durci S7 reste affiché.", 6, 40] call comspec_atak_native_fnc_notify;
        };
        [{
            private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""];
            if (_p isNotEqualTo "" && {!isNull ([] call comspec_atak_native_fnc_display)}) then { [] call comspec_atak_native_fnc_layoutApply; [_p] call comspec_atak_native_fnc_pageRender; };
        }] call comspec_atak_native_fnc_nextFrame;
    }] call comspec_atak_native_fnc_addSetting;
{
    _x params ["_k", "_name", "_def"];
    [format ["comspec_atak_native_ui_items_%1", _k], "EDITBOX",
        [format ["Item requis : %1", _name], format ["Item à avoir sur soi pour utiliser le modèle %1 : noms de classe ou mots-clés, séparés par des virgules. Vide : n'importe quel téléphone ATAK suffit. Ce réglage ne change pas le modèle affiché : choisissez-le dans « Modèle du téléphone » ci-dessus ou dans le téléphone (Réglages > Personnaliser).", _name]],
        _uiCat, _def, 1, { missionNamespace setVariable ["COMSPEC_ATAK_ModelOkCache", createHashMap]; }] call comspec_atak_native_fnc_addSetting;
} forEach [
    ["s7", "Durci S7", ""], ["s23", "Samsung S23", ""], ["ip17", "iPhone 17 Pro", ""], ["iphone", "V2 Enhanced", ""], ["rugged", "Xiaomi X3D", ""], ["sonim", "Sonim XP8", ""],
    ["keys", "Tablette", "tablet,tablette,ctab"]
];
// [modèle, unité] -> l'unité porte-t-elle l'objet exigé pour ce modèle ? (cache 2 s : fn_layoutGet l'appelle souvent).
missionNamespace setVariable ["COMSPEC_ATAK_ModelOk", {
    params [["_model", "s7"], ["_unit", player]];
    private _cache = missionNamespace getVariable ["COMSPEC_ATAK_ModelOkCache", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_ModelOkCache", _cache];
    (_cache getOrDefault [_model, [-10, true]]) params ["_t", "_ok"];
    if (diag_tickTime - _t < 2) exitWith { _ok };
    private _want = ((toLower (missionNamespace getVariable [format ["comspec_atak_native_ui_items_%1", _model], ""])) splitString ",; ") apply { trim _x } select { _x isNotEqualTo "" };
    private _have = ((assignedItems _unit) + (items _unit)) apply { toLower _x };
    _ok = (count _want) isEqualTo 0 || {(_have findIf { private _i = _x; (_want findIf { _i isEqualTo _x || {(_i find _x) >= 0} }) >= 0 }) >= 0};
    _cache set [_model, [diag_tickTime, _ok]];
    _ok
}];

// ATAK en 3D et lumières vues par les autres (retour de jeu : coupés par défaut).
["comspec_atak_native_phone_3d", "CHECKBOX",
    ["Téléphone visible en 3D", "Décoché par défaut. Coché : le téléphone peut être fixé sur le gilet (les autres joueurs le voient) et l'ATAK peut s'ouvrir sur la tablette du JPC (en test). Décoché : le téléphone n'apparaît jamais sur le personnage."],
    ["COMSPEC", "ATAK · Affichage"], false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_stowed_damage", "CHECKBOX",
    ["Téléphone rangé : peut être détruit", "Coché par défaut : même rangé (poche, gilet), le téléphone peut être abîmé ou détruit par les balles, les explosions et les chutes. Décoché : seul un téléphone sorti peut s'abîmer."],
    ["COMSPEC", "ATAK · Accès au téléphone"], true, 1] call comspec_atak_native_fnc_addSetting;

// Gilets qui ont déjà un ATAK modélisé (fn_vestBuiltin) : notre téléphone n'y est pas accroché, sauf choix du joueur (menu ACE).
private _vestReset = { missionNamespace setVariable ["COMSPEC_ATAK_VestBuiltinCache", createHashMap]; };
["comspec_atak_native_vest_atak_items", "EDITBOX",
    ["Gilets avec ATAK déjà modélisé", "Noms de classe exacts des gilets qui ont déjà un ATAK dessiné dessus, séparés par des virgules : notre téléphone 3D n'y est pas ajouté. Chaque joueur peut aussi le signaler pour son gilet dans le menu ACE « Téléphone ATAK > Gilet »."],
    ["COMSPEC", "ATAK · Gilet"], "", 1, _vestReset] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_vest_atak_patterns", "EDITBOX",
    ["Gilets avec ATAK déjà modélisé (mots-clés)", "Mots cherchés dans la classe, le nom et le modèle du gilet, séparés par des virgules. Videz le champ pour n'utiliser que la liste ci-dessus."],
    ["COMSPEC", "ATAK · Gilet"], "atak,eud", 1, _vestReset] call comspec_atak_native_fnc_addSetting;

private _mus = ["COMSPEC", "ATAK · Musique"];
["comspec_atak_native_music_enabled", "CHECKBOX",
    ["App Musique", "Active l'app Musique : vos fichiers, les musiques d'Arma, des mods et de la mission, et les liens audio."],
    _mus, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_music_speaker", "CHECKBOX",
    ["Haut-parleur", "Les joueurs proches entendent la musique d'un téléphone mis en haut-parleur."],
    _mus, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_music_range", "SLIDER",
    ["Portée du haut-parleur (m)", "Le son baisse avec la distance et ne s'entend plus au-delà de cette portée."],
    _mus, [5, 100, 30, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_music_urls", "CHECKBOX",
    ["Liens audio (URL)", "Autorise la lecture de fichiers audio et de webradios à partir d'une adresse web."],
    _mus, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_music_athena", "CHECKBOX",
    ["Bibliothèque Athena", "Onglet ATHENA de l'app Musique : playlist générale, bibliothèque de la communauté et de votre unité, playlists publiques du site (lues via COMSPEC Link)."],
    _mus, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_music_radio", "EDITBOX",
    ["Radio de la mission", "Liens audio proposés à tous dans l'onglet SERVEUR, au format Titre|https://...;Titre 2|https://..."],
    _mus, "", 1] call comspec_atak_native_fnc_addSetting;

private _sim = ["COMSPEC", "ATAK · Simulation"];
// Profil de réalisme (fn_netCond) : donne la valeur des réglages de simulation laissés à leur valeur par défaut ;
// tout réglage changé ici, au menu Échap (Réalisme et réseau) ou sur le site l'emporte, réglage par réglage.
["comspec_atak_native_realism_profile", "LIST",
    ["Profil de réalisme", "Milsim (par défaut) : toutes les simulations à leur valeur réelle (réseau radio, météo, saturation, pertes, positions BFT toutes les 10 s, GPS qui perd en précision à couvert, batterie). Standard : mêmes simulations en plus doux (météo et erreurs GPS à moitié, BFT toutes les 5 s, pas d'attente des satellites). Arcade : réseau parfait, BFT en direct, GPS exact, batterie infinie. Personnalisé : seuls les réglages ci-dessous comptent. Chaque réglage reste modifiable avec n'importe quel profil."],
    _sim, [[3, 2, 1, 0], ["Milsim", "Standard", "Arcade", "Personnalisé (réglages ci-dessous)"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_damage_sim", "CHECKBOX",
    ["Dégâts du téléphone", "Le téléphone peut être abîmé par les balles, éclats, explosions, chutes, accidents et l'eau. Le résultat dépend de l'endroit où il est porté (main, gilet, poche, sac), de la zone touchée, de la munition et de la protection du gilet : rien, écran rayé ou fêlé, pixels morts, batterie, haut-parleur, GPS ou antenne abîmés, téléphone éteint ou détruit. Réparation par action ACE avec un kit de réparation ou une caisse à outils, ou en changeant d'appareil. Avec le réglage Overwatch « Réalisme ATAK » : niveau 1 = le téléphone peut seulement s'éteindre, niveau 2 = jamais détruit."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_dirt_sim", "CHECKBOX",
    ["Saleté et sang sur l'écran", "L'écran se salit : poussière dehors (plus vite couché, dans un véhicule ouvert ou après une explosion), traces de doigts, sang sur l'écran et la coque (soins, blessures, mort à côté de vous, mains en sang). Nettoyage gratuit et illimité : action ACE « Nettoyer l'écran » ou « Nettoyage complet », app Profil ou Réglages. Chaque joueur peut aussi désactiver l'effet pour lui dans Réglages > Réalisme."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_blood_style", "LIST",
    ["Sang sur l'écran", "Discret : quelques taches pâles. Réaliste : éclaboussures, traces de doigts, coulures, marques de paume. Gore : taches plus grandes et plus nombreuses, flaques épaisses, mains entières."],
    _sim, [[0, 1, 2], ["Discret", "Réaliste", "Gore"], 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_clean_items", "EDITBOX",
    ["Kits de nettoyage (optionnel)", "On peut toujours nettoyer l'écran sans rien. Avec un de ces items sur soi (noms de classe exacts, séparés par des virgules), c'est deux fois plus rapide et tout part, sang compris. L'item n'est pas consommé. Vide : aucun kit."],
    _sim, "ACE_fieldDressing,ACE_elasticBandage,ACE_packingBandage,ACE_quikclot", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ew_open", "CHECKBOX",
    ["Guerre électronique pour tous", "Coché : tout joueur avec un téléphone peut brouiller et localiser des émetteurs (goniométrie). Décoché : réservé aux unités marquées COMSPEC_ATAK_EwOperator ou aux rôles « guerre électronique », « brouilleur » ou « SIGINT »."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zeus_crows", "CHECKBOX",
    ["Brouilleurs du mod Crows Electronic Warfare", "Si le mod Crows Electronic Warfare est chargé, ses brouilleurs (posés par Zeus) brouillent aussi l'ATAK : réseau, GPS et BFT, à plein effet dans leur rayon d'action."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_jam_tfar", "CHECKBOX",
    ["Brouillage des scripts TFAR", "Les scripts de brouillage radio TFAR (« TFAR Radio Jammer » et semblables) réduisent la portée d'émission radio du joueur. Coché : le téléphone ATAK est brouillé d'autant (débit, GPS et BFT)."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_jam_classes", "EDITBOX",
    ["Objets brouilleurs d'autres mods", "Objets ou véhicules qui brouillent l'ATAK tant qu'ils sont intacts, par exemple les brouilleurs d'un mod de guerre électronique. Format : Classe:rayon:force, séparés par des virgules (rayon en mètres, 1000 par défaut ; force 1-100, 80 par défaut). Classes parentes acceptées. Exemple : Land_DataTerminal_01_F:500:90. Vide : aucun."],
    _sim, "", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lase_link", "CHECKBOX",
    ["Télémètre vers l'ATAK", "Le point visé avec un désignateur laser allumé devient un marqueur sur la carte de l'ATAK (touche « Envoyer le point visé à l'ATAK »). Jumelles et optiques : voir « Marquer depuis des jumelles »."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_optic_mark", "CHECKBOX",
    ["Marquer depuis des jumelles ou une optique", "Décoché par défaut : aucune jumelle ni optique (ACE Vector 21 compris) ne pose de marqueur sur la carte de l'ATAK, il faut reporter l'ennemi à la main. Coché : la mesure du Vector 21 (azimut + distance) ou la touche « Envoyer le point visé à l'ATAK » dans une optique ou des jumelles pose un marqueur."],
    _sim, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mag_decl", "SLIDER",
    ["Déclinaison magnétique (°)", "Écart entre le nord vrai de la carte et le nord magnétique, en degrés (positif : déclinaison est). Les cartes Arma n'en ont pas : 0 par défaut. Sert au cap magnétique du 9-LINE de l'app JTAC et de l'outil carte « JTAC : IP » cible »."],
    _sim, [-30, 30, 0, 1], 1] call comspec_atak_native_fnc_addSetting;
// Contrôle Zeus des ATAK : le menu clic droit ZEN d'Overwatch (« ATAK — Infos / dégâts / brouillage », état du téléphone
// d'Overwatch) est remplacé par « Contrôler un téléphone » (fn_zeusMenu), qui agit sur l'ATAK natif.
missionNamespace setVariable ["COMSPEC_ZenAtakPlayerContextRegistered", true];
missionNamespace setVariable ["COMSPEC_AceAtakZeusInteractRegistered", true];
// Ctrl + double-clic sur un joueur en Zeus : plus l'ancien panneau ATAK d'Overwatch (fn_zeusShowPlayerAtak).
missionNamespace setVariable ["COMSPEC_ZeusAtakPlayerActionsRegistered", true];
// Modules Zeus d'Overwatch remplacés : « ATAK — Éditer joueur » et « Doter du terminal SEEK » (« Contrôler un téléphone », Centre Zeus, SSE).
// ace_zeus_fnc_addModule : ancienne voie qui recréait les catégories « COMSPEC ATAK » et « COMSPEC Outils ».
{ missionNamespace setVariable [_x, true]; } forEach ["COMSPEC_ZenAtakPlayerModulesRegistered", "COMSPEC_AceAtakPlayerModulesRegistered", "COMSPEC_AceTrackActionsRegistered", "COMSPEC_AceTheaterSurveyRegistered"];
private _geo = ["COMSPEC", "ATAK · Géolocalisation"];
["comspec_atak_native_geoloc_enabled", "CHECKBOX",
    ["Géolocalisation des téléphones", "App GE (guerre électronique), onglet GÉOLOC : localiser un téléphone avec son numéro, son IMEI ou son adresse MAC (roleplay)."],
    _geo, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_geoloc_ew_only", "CHECKBOX",
    ["Réservé aux opérateurs GE", "Coché : seuls les joueurs qui ont accès à la guerre électronique (voir ATAK · Simulation) peuvent géolocaliser."],
    _geo, false, 1] call comspec_atak_native_fnc_addSetting;
{
    _x params ["_k", "_label", "_def"];
    [format ["comspec_atak_native_geoloc_%1", _k], "CHECKBOX",
        [format ["Géolocalisables : %1", _label], format ["Les téléphones du camp %1 peuvent être géolocalisés par les autres camps.", _label]],
        _geo, _def, 1] call comspec_atak_native_fnc_addSetting;
} forEach [["west", "BLUFOR", true], ["east", "OPFOR", true], ["guer", "INDÉPENDANTS", true], ["civ", "CIVILS", true]];
["comspec_atak_native_geoloc_own", "CHECKBOX",
    ["Géolocaliser son propre camp", "Coché : on peut aussi géolocaliser un téléphone de son camp. Décoché : seulement les autres camps."],
    _geo, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_geoloc_ai", "CHECKBOX",
    ["IA géolocalisables", "Les IA qui ont un téléphone reçoivent un numéro et peuvent être géolocalisées."],
    _geo, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_geoloc_precision", "SLIDER",
    ["Précision (m)", "Marge d'erreur de la position trouvée, comme une triangulation par antennes relais."],
    _geo, [10, 1000, 150, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_geoloc_refresh", "SLIDER",
    ["Mise à jour du suivi (s)", "Temps entre deux positions quand vous suivez un téléphone en continu."],
    _geo, [10, 300, 30, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_geoloc_warn", "CHECKBOX",
    ["Prévenir la cible", "Le joueur géolocalisé reçoit une alerte discrète « activité réseau anormale »."],
    _geo, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_sim_sos", "CHECKBOX",
    ["Carte SIM localisable téléphone éteint", "Quand la batterie est vide, le téléphone s'éteint mais sa carte SIM continue d'être captée par les relais. On peut alors le retrouver par son numéro ou son IMEI (onglet GÉOLOC de l'app GE, RECHERCHER sur la carte), plus ou moins précisément selon le nombre de relais qui le captent."],
    _geo, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_sim_sos_own", "CHECKBOX",
    ["Téléphone éteint : retrouver un allié", "Coché : on peut retrouver le téléphone éteint d'un allié par sa carte SIM, même si « Géolocaliser son propre camp » est décoché."],
    _geo, true, 1] call comspec_atak_native_fnc_addSetting;
// IA ennemie qui ramasse les téléphones des joueurs et s'en sert pour les traquer (fn_captureServer, fn_compromise).
private _cap = ["COMSPEC", "ATAK · Capture par l'ennemi"];
["comspec_atak_native_capture_enabled", "CHECKBOX",
    ["L'IA ennemie ramasse les téléphones", "Une IA ennemie qui passe près du téléphone d'un joueur (sur un corps, un joueur à terre ou menotté, ou posé au sol) va le récupérer. Tant que le vol n'a pas été déclaré (compromission validée), elle voit sur le BFT les joueurs dont la balise est allumée."],
    _cap, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_radius", "SLIDER",
    ["Distance de ramassage (m)", "Une IA ennemie plus proche que cette distance va chercher le téléphone."],
    _cap, [10, 500, 100, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_crack", "SLIDER",
    ["Temps pour casser le code (s)", "Temps qu'il faut à l'IA pour déverrouiller un téléphone protégé par un code."],
    _cap, [0, 900, 120, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_hunt", "CHECKBOX",
    ["Chasse aux joueurs", "Coché : le groupe IA qui a le téléphone, et jusqu'à 3 groupes voisins, partent attaquer le joueur le plus proche. Décoché : ils connaissent vos positions mais ne bougent pas pour autant."],
    _cap, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_hunt_radius", "SLIDER",
    ["Portée de la chasse (m)", "Seuls les joueurs à moins de cette distance du téléphone volé sont repérés et pris en chasse."],
    _cap, [200, 10000, 3000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_share", "SLIDER",
    ["Partage aux groupes IA voisins (m)", "Les groupes IA du même camp à moins de cette distance reçoivent aussi vos positions (0 = seul le groupe qui a le téléphone)."],
    _cap, [0, 5000, 1500, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_refresh", "SLIDER",
    ["Mise à jour des positions (s)", "Temps entre deux lectures du BFT par l'IA qui a le téléphone volé."],
    _cap, [5, 300, 30, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_capture_validate", "LIST",
    ["Qui peut déclarer un téléphone volé", "Déclarer le vol (valider la compromission) coupe le téléphone volé du réseau et le bloque sur Athena."],
    _cap, [[0, 1], ["Chefs de groupe, propriétaire et Zeus", "Tout le camp"], 0], 1] call comspec_atak_native_fnc_addSetting;
// App Admin du téléphone (fn_adminAllowed) : en plus du Zeus et de l'admin connecté, joueurs désignés par le serveur.
["comspec_atak_native_admin_uids", "EDITBOX",
    ["Gestionnaires en jeu (UID Steam)", "UID Steam (64 bits) séparés par des virgules : ces joueurs ont l'app Admin du téléphone (ATAK des joueurs, effets Zeus, debug de l'IA) sans être Zeus. Le parc Athena de l'app suit les droits du compte sur le site."],
    ["COMSPEC", "ATAK · Admin"], "", 1] call comspec_atak_native_fnc_addSetting;
// Centre Zeus (fn_zeusCentre) : préférences de chaque Zeus.
["comspec_atak_native_zeus_centre_btn", "CHECKBOX",
    ["Centre Zeus : bouton", "Bouton CENTRE ZEUS en haut de l'écran Zeus. Décoché : seule la touche l'ouvre (Contrôles > Configurer les addons)."],
    ["COMSPEC", "ATAK · Admin"], true, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zeus_centre_3d", "CHECKBOX",
    ["Centre Zeus : étiquettes 3D", "Étiquettes au-dessus des joueurs à l'ouverture du Centre Zeus, panneau ouvert seulement. Un bouton du Centre les coupe aussi."],
    ["COMSPEC", "ATAK · Admin"], true, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_zeus_centre_dbl", "CHECKBOX",
    ["Double-clic : configurer l'objet", "Écran Zeus : un double-clic sur un écran, une preuve ou une caisse ouvre sa fenêtre de configuration à la place de la fenêtre d'attributs."],
    ["COMSPEC", "ATAK · Admin"], true, 0] call comspec_atak_native_fnc_addSetting;
// CR de fin d'opération imposé en jeu (fn_debriefServer) : hôte, admin, gestionnaires ci-dessus et Athena toujours ; Zeus selon ce réglage.
["comspec_atak_native_debrief_zeus", "CHECKBOX",
    ["Zeus peut imposer le CR de fin d'opération", "Un Zeus peut ouvrir le compte rendu de fin d'opération (à remplir ou à lire) dans le téléphone des joueurs, depuis l'app CR fin d'op ou le menu Zeus. L'hôte, l'admin connecté, les gestionnaires en jeu et les gestionnaires désignés sur Athena le peuvent toujours."],
    ["COMSPEC", "ATAK · Admin"], true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_battery_sim", "CHECKBOX",
    ["Batterie réaliste", "La batterie se vide selon l'utilisation (écran, appareil photo, caméra en direct, GPS, envoi de données, recherche de réseau, brouilleur) et se recharge dans un véhicule moteur allumé. À 0 %, le téléphone s'éteint et apparaît hors ligne sur le BFT."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
// Batterie et mémoire vive du téléphone : réglés par l'admin du serveur ou de la mission.
private _pow = ["COMSPEC", "ATAK · Batterie et mémoire"];
["comspec_atak_native_battery_hours", "SLIDER",
    ["Autonomie en main (heures)", "Combien de temps tient une batterie pleine avec le téléphone en main, écran allumé. Le reste (veille, appareil photo, caméra en direct, GPS, brouilleur...) suit la même échelle : 3 h en main = environ 14 h en veille."],
    _pow, [0.5, 24, 3, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_battery_start", "SLIDER",
    ["Batterie au début de la mission (%)", "Niveau de batterie quand le joueur arrive en jeu. Les batteries de rechange sont toujours pleines."],
    _pow, [5, 100, 100, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_battery_vehicle", "SLIDER",
    ["Recharge en véhicule (% par minute)", "Vitesse de recharge dans un véhicule moteur allumé. 0 : pas de recharge en véhicule."],
    _pow, [0, 20, 3, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_battery_cold", "CHECKBOX",
    ["Froid : batterie moins efficace", "Sous 5 °C (météo ACE si elle est chargée, sinon heure et ciel), la batterie se vide plus vite : de 0,06 % par minute à 5 °C jusqu'à 0,3 % par minute par grand froid, en plus de l'usage. Décochez pour ignorer la température."],
    _pow, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ram_total", "LIST",
    ["Mémoire vive (RAM) du téléphone", "RAM du téléphone (4 Go sur un S7). Le système en prend environ 1,2 Go ; avec moins de RAM, les apps restées ouvertes en fond se ferment plus vite."],
    _pow, [[2048, 3072, 4096, 6144, 8192, 12288], ["2 Go", "3 Go", "4 Go (S7)", "6 Go", "8 Go", "12 Go"], 2], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ram_bg", "SLIDER",
    ["Apps gardées ouvertes en fond", "Nombre maximum d'apps qui restent ouvertes derrière celle que vous utilisez. Au-delà, ou si la RAM est pleine, les plus anciennes se ferment."],
    _pow, [0, 10, 6, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_sim", "CHECKBOX",
    ["Réseau réaliste", "Le réseau dépend du terrain : bâtiments, relief, véhicule, météo, brouilleurs, relais et dégâts du téléphone réduisent le débit. Messages et photos peuvent mettre du temps à partir, se perdre et être renvoyés, ou attendre le retour du réseau. Décoché : réseau parfait partout."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
// Radio maillée MPU5 (objet d'inventaire, fn_mpu5).
["comspec_atak_native_mpu5_range", "SLIDER",
    ["Portée de la MPU5 (m)", "Portée dans le maillage Wave Relay d'un joueur qui porte une radio MPU5 (objet « Radio maillée MPU5 » ou toute classe contenant MPU5). Le téléphone seul porte à 3 000 m."],
    _sim, [1000, 15000, 6000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu5_gain", "SLIDER",
    ["Gain de la MPU5 (dB)", "Marge radio ajoutée à la liaison du téléphone vers les relais quand le joueur porte une MPU5 (plus puissante que le téléphone). 6 dB double à peu près la portée."],
    _sim, [0, 15, 8, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu5_mbps", "SLIDER",
    ["Débit relayé par une MPU5 (Mbit/s)", "Débit maximal qu'une MPU5 redistribue aux équipiers à sa portée, pris sur ce qu'elle reçoit elle-même d'un relais."],
    _sim, [1, 50, 20, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu5_items", "EDITBOX",
    ["Objets MPU5 d'autres mods", "Classes d'objets d'inventaire qui comptent comme une radio MPU5, séparées par des virgules. Les classes dont le nom contient MPU5 comptent déjà, comme l'objet « Radio maillée MPU5 »."],
    _sim, "", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_realism", "LIST",
    ["Niveau de réalisme radio", "Réaliste (par défaut) : calcul radio complet (relief, végétation, bâtiments, variations du signal, bord de couverture, brouillage selon la force du signal, envois un par un). Exigeant : comme Réaliste, mais forêts et bâtiments coupent beaucoup plus. Simplifié : ancien calcul, la portée est juste divisée par trois derrière le relief."],
    _sim, [[1, 2, 0], ["Réaliste", "Exigeant", "Simplifié (ancien calcul)"], 0], 1] call comspec_atak_native_fnc_addSetting;
// Conditions réseau (fn_netCond) : toutes les simulations actives par défaut. Le menu Échap (Conditions réseau) les
// applique au serveur en cours de partie ; une règle de la communauté sur le site l'emporte sur les deux.
["comspec_atak_native_net_weather", "CHECKBOX",
    ["La météo gêne le réseau", "La pluie, les orages et le vent fort affaiblissent le signal ; orages et vent font aussi perdre des envois par moments. Le brouillard ne change rien."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_weather_k", "LIST",
    ["Force de l'effet météo", "100 % : effet réaliste. Au-dessus, la météo gêne plus (scénario dégradé). 0 % : aucun effet."],
    _sim, [[0, 50, 100, 150, 200], ["0 %", "50 %", "100 % (réel)", "150 %", "200 %"], 2], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_congestion", "CHECKBOX",
    ["Saturation du réseau", "Les téléphones qui passent par le même relais se partagent sa capacité : plus ils sont nombreux, plus le réseau ralentit, la latence monte et des envois se perdent."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_bg_load", "LIST",
    ["Réseau déjà occupé", "Part de chaque relais déjà prise par d'autres utilisateurs fictifs (vidéos, synchronisations, unités hors jeu)."],
    _sim, [[0, 10, 25, 50, 75, 90], ["0 %", "10 %", "25 %", "50 %", "75 %", "90 %"], 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_loss", "CHECKBOX",
    ["Envois perdus", "Selon la qualité du signal, des envois se perdent, parfois plusieurs d'affilée, puis repartent tout seuls. Décoché : rien ne se perd, seuls la vitesse et le délai comptent."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_loss_floor", "LIST",
    ["Pertes minimum", "Part d'envois perdus partout, même avec un excellent signal (zone difficile, matériel usé)."],
    _sim, [[0, 1, 3, 5, 10, 20], ["0 %", "1 %", "3 %", "5 %", "10 %", "20 %"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_latency_add", "LIST",
    ["Délai ajouté", "Délai ajouté à chaque envoi, par exemple 600 ms pour simuler une liaison satellite vers le poste de commandement."],
    _sim, [[0, 50, 150, 300, 600, 1200], ["0 ms", "50 ms", "150 ms", "300 ms", "600 ms (satellite)", "1 200 ms"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_offline_hold", "CHECKBOX",
    ["Mode hors connexion", "Sans réseau, le téléphone garde ses dernières infos (fil Athena, ordres, alertes, unités) et met en attente les comptes rendus, MEDEVAC, alertes, demandes JTAC et itinéraires : tout arrive au retour du réseau, avec un résumé. Décoché : tout arrive même sans réseau."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_offline_save", "CHECKBOX",
    ["Garder les données après une déconnexion", "SMS, messages, ordres et alertes sont sauvegardés dans le profil du joueur pour la mission en cours : après un crash ou une reconnexion, on les retrouve, même sans réseau."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_vehicle_gateways", "CHECKBOX",
    ["Véhicules relais et PC mobile", "Décoché par défaut (peu réaliste). Coché : l'équipage peut transformer n'importe quel véhicule en relais ATAK (4 km) ou en poste de commandement avec mât (10 km). Décoché : seuls les relais posés par la mission (module Eden ou Zeus « Poser un relais ») font passer le réseau."],
    _sim, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_only", "CHECKBOX",
    ["Réseau uniquement par les relais", "Coché : le réseau ATAK passe uniquement par les relais de la mission (pylônes, module « Poser un relais »). Loin de tout relais, ou si la mission n'en a pas, plus de réseau du tout : messages, photos et positions attendent d'être à portée. Décoché : sans relais, le téléphone garde quand même du réseau."],
    _sim, false, 1] call comspec_atak_native_fnc_addSetting;
// Antennes du jeu converties en relais (fn_relayConvert).
["comspec_atak_native_relay_convert", "LIST",
    ["Antennes du jeu = relais ATAK", "Les pylônes, mâts et tours de transmission, antennes omnidirectionnelles et paraboles du jeu deviennent des relais du réseau ATAK (portée et débit selon leur hauteur : tour 6 km, mât 3 à 5 km, antenne 1,5 km, parabole 1 km). Objets de la mission : ceux posés dans Eden ou par le Zeus. Décor de la carte en plus : les antennes de la carte elle-même (60 au plus, les plus hautes) ; une antenne détruite coupe son relais."],
    _sim, [[0, 1, 2], ["Non", "Objets de la mission (Eden, Zeus)", "Mission et décor de la carte"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_mrh", "CHECKBOX",
    ["Antennes MRH Satellite = relais ATAK", "Si le mod MRH Satellite est chargé : une antenne SATCOM dépliée devient un relais du réseau ATAK (type terminal satellite), retiré dès qu'elle est repliée ou détruite. Sans ce mod : aucun effet."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
// Déclencheur réseau des explosifs (fn_exploNet) : le serveur décide, ces valeurs sont imposées à tous.
private _exploCat = ["COMSPEC", "ATAK · Explosifs"];
["comspec_atak_native_explo_net_range", "SLIDER",
    ["Portée du déclencheur réseau (m)", "Distance maximale, en liaison dégagée, entre la charge et le relais le plus proche ou le téléphone. Relief, météo et brouillage s'appliquent ensuite."],
    _exploCat, [100, 10000, 1500, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_explo_net_delay", "SLIDER",
    ["Délai de transmission (s)", "Temps entre l'ordre validé par le serveur et la détonation."],
    _exploCat, [0, 5, 0.5, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_explo_net_consume", "CHECKBOX",
    ["Un déclencheur réseau par charge", "Coché : l'objet reste sur la charge et sort de l'inventaire. Décoché : il suffit d'en porter un, il sert pour toutes vos charges."],
    _exploCat, true, 1] call comspec_atak_native_fnc_addSetting;
// Antennes transportables (fn_antennaKit) : valeurs de jeu plausibles, à régler selon la mission. Portée du relais = portée de base x 10^(gain / 20).
private _antCat = ["COMSPEC", "ATAK · Antennes transportables"];
["comspec_atak_native_antkit_max", "SLIDER",
    ["Antennes déployées par camp", "Nombre maximal d'antennes transportables en place en même temps dans un camp. Au-delà, le déploiement est refusé et l'antenne reste dans l'inventaire."],
    _antCat, [1, 60, 12, 0], 1] call comspec_atak_native_fnc_addSetting;
{
    _x params ["_id", "_label", "_r", "_g", "_h", "_m", "_t"];
    [format ["comspec_atak_native_antkit_%1_range", _id], "SLIDER", [format ["%1 : portée de base (m)", _label], "Portée sans le gain, sur un lien dégagé. Le bilan radio (relief, feuillage, bâtiments, météo) s'applique ensuite."],
        _antCat, [300, 20000, _r, 0], 1] call comspec_atak_native_fnc_addSetting;
    [format ["comspec_atak_native_antkit_%1_gain", _id], "SLIDER", [format ["%1 : gain (dB)", _label], "Multiplie la portée de base : 6 dB la doublent à peu près."],
        _antCat, [0, 20, _g, 1], 1] call comspec_atak_native_fnc_addSetting;
    [format ["comspec_atak_native_antkit_%1_height", _id], "SLIDER", [format ["%1 : hauteur d'antenne (m)", _label], "Hauteur au-dessus de l'objet posé, utilisée par le bilan radio : plus haut, le relief gêne moins. Le modèle affiché ne change pas."],
        _antCat, [0.1, 30, _h, 1], 1] call comspec_atak_native_fnc_addSetting;
    [format ["comspec_atak_native_antkit_%1_mbps", _id], "SLIDER", [format ["%1 : débit (Mbit/s)", _label], "Débit maximal du relais."],
        _antCat, [0.5, 100, _m, 1], 1] call comspec_atak_native_fnc_addSetting;
    [format ["comspec_atak_native_antkit_%1_time", _id], "SLIDER", [format ["%1 : temps de déploiement (s)", _label], "Durée de la barre de progression. Replier prend moitié moins de temps."],
        _antCat, [3, 180, _t, 0], 1] call comspec_atak_native_fnc_addSetting;
} forEach [["sat", "Antenne satellite", 1000, 3, 1.8, 10, 20], ["mast", "Antenne omnidirectionnelle", 1500, 6, 4, 8, 45], ["mini", "Mini-antenne de bâtiment", 500, 3, 0.3, 4, 8]];
["comspec_atak_native_antkit_mast_root", "CHECKBOX",
    ["Antenne omnidirectionnelle raccordée", "Décoché : elle répète seulement, il lui faut un relais raccordé à portée, par exemple une antenne satellite. Coché : elle porte le réseau seule."],
    _antCat, false, 1] call comspec_atak_native_fnc_addSetting;
// Relais par type (module « Poser un relais », fn_moduleRelay) : portée et débit quand le module garde la valeur du type.
["comspec_atak_native_relay_portable_range", "SLIDER",
    ["Relais radio portable : portée (m)", "Portée d'un relais « radio portable » posé avec la portée du type (module Eden ou Zeus « Poser un relais »)."],
    _sim, [300, 20000, 1500, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_portable_mbps", "SLIDER",
    ["Relais radio portable : débit (Mbit/s)", "Débit maximal d'un relais « radio portable » posé avec le débit du type."],
    _sim, [0.5, 100, 4, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_mat_range", "SLIDER",
    ["Relais mât tactique : portée (m)", "Portée d'un relais « mât tactique » posé avec la portée du type (module Eden ou Zeus « Poser un relais »)."],
    _sim, [300, 20000, 3000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_mat_mbps", "SLIDER",
    ["Relais mât tactique : débit (Mbit/s)", "Débit maximal d'un relais « mât tactique » posé avec le débit du type."],
    _sim, [0.5, 100, 8, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_tour_range", "SLIDER",
    ["Relais tour fixe : portée (m)", "Portée d'un relais « tour fixe » posé avec la portée du type (module Eden ou Zeus « Poser un relais »)."],
    _sim, [300, 20000, 6000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_tour_mbps", "SLIDER",
    ["Relais tour fixe : débit (Mbit/s)", "Débit maximal d'un relais « tour fixe » posé avec le débit du type."],
    _sim, [0.5, 100, 20, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_satellite_range", "SLIDER",
    ["Relais terminal satellite : portée (m)", "Portée d'un relais « terminal satellite » posé avec la portée du type (module Eden ou Zeus « Poser un relais »)."],
    _sim, [300, 20000, 1000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_satellite_mbps", "SLIDER",
    ["Relais terminal satellite : débit (Mbit/s)", "Débit maximal d'un relais « terminal satellite » posé avec le débit du type."],
    _sim, [0.5, 100, 10, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_damage", "LIST",
    ["Dégâts des relais", "Perte de débit d'un relais complètement endommagé (la portée baisse de moitié moins). Les dégâts dégradent le relais petit à petit ; détruit (95 %), il est coupé."],
    _sim, [[0, 30, 50, 70, 90], ["Aucun effet", "30 %", "50 %", "70 % (défaut)", "90 %"], 3], 1] call comspec_atak_native_fnc_addSetting;
// Téléphone branché sur une radio ACRE2 ou TFAR (fn_mpu5 "plug") : la radio relaie les données comme une MPU5.
["comspec_atak_native_radio_data", "CHECKBOX",
    ["Données par radio ACRE2 / TFAR", "Le joueur peut brancher son téléphone sur sa radio (app Wave Relay) : elle relaie ses données et celles de l'équipe, comme une MPU5, avec la portée et le débit ci-dessous. La radio personnelle 343 ne compte pas. Ignoré avec la « Liaison téléphone-radio réaliste » : seule une MPU5 relie alors le téléphone."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_lr_range", "SLIDER", ["Radio longue portée : portée données (m)", "Portée dans le maillage d'un téléphone branché sur une radio longue portée."],
    _sim, [500, 30000, 10000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_lr_gain", "SLIDER", ["Radio longue portée : gain (dB)", "Marge radio ajoutée à la liaison vers les relais. 6 dB double à peu près la portée."],
    _sim, [0, 15, 10, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_lr_mbps", "SLIDER", ["Radio longue portée : débit relayé (Mbit/s)", "Débit maximal relayé à l'équipe. Une radio ACRE2 / TFAR transporte bien moins qu'une MPU5 : les photos et vidéos passent lentement."],
    _sim, [0.1, 20, 2, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_sw_range", "SLIDER", ["Radio portative : portée données (m)", "Portée dans le maillage d'un téléphone branché sur une radio portative."],
    _sim, [500, 30000, 2000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_sw_gain", "SLIDER", ["Radio portative : gain (dB)", "Marge radio ajoutée à la liaison vers les relais. 6 dB double à peu près la portée."],
    _sim, [0, 15, 4, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_sw_mbps", "SLIDER", ["Radio portative : débit relayé (Mbit/s)", "Débit maximal relayé à l'équipe. Une radio ACRE2 / TFAR transporte bien moins qu'une MPU5 : les photos et vidéos passent lentement."],
    _sim, [0.1, 20, 0.5, 1], 1] call comspec_atak_native_fnc_addSetting;
// Liaison réaliste téléphone <-> radio de données (fn_mpuLink) : activée par défaut, le profil Arcade la coupe. Réglages
// serveur, une mission peut les imposer (cba_settings.sqf de la mission). Les vraies radios ACRE2 et TFAR d'abord (fn_mpu5 "gear").
private _mpuCat = ["COMSPEC", "ATAK · Radio de données (MPU5)"];
["comspec_atak_native_mpu_real", "CHECKBOX",
    ["Liaison téléphone-radio réaliste", "Le téléphone passe par la radio du joueur : ACRE2 ou TFAR d'abord, reliée toute seule avec sa portée réelle, ou une MPU5. Les radios sur le même canal forment un maillage avec les relais de la mission. Sans radio, le téléphone garde son réseau, sauf si « Radio de données obligatoire » est coché. Ne touche jamais à la voix. Décoché, ou profil Arcade : comportement d'avant."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_require", "CHECKBOX",
    ["Radio de données obligatoire", "Coché : sans radio de données, pas de réseau de données (le Bluetooth entre téléphones et les données déjà reçues restent). À réserver aux missions préparées. Décoché : sans radio, le téléphone garde son réseau d'avant ; la radio ne fait que l'améliorer."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
// Objet obligatoire pour participer au réseau (fn_netRequire), quel que soit le modèle de réseau. « Radio de données obligatoire » ci-dessus ne vise que la
// MPU5 en liaison réaliste, « Kit obligatoire » (app Communauté) ne coupe rien : ni l'un ni l'autre ne pouvait porter cette règle.
["comspec_atak_native_net_required_items", "EDITBOX",
    ["Objet obligatoire pour le réseau", "Sans cet objet, le joueur n'est plus un nœud du réseau : pas de données, pas de relais. Le téléphone reste utilisable et l'app Wave Relay dit ce qui manque. Noms de classe séparés par des virgules (tous requis). Au choix : classeA|classeB. Nombre : classe*2. Exemple : ACRE_MPU5|COMSPEC_ATAK_MPU5. Les radios ACRE2 et TFAR sont reconnues par leur classe de base. Une classe inconnue du modset est ignorée (voir le RPT). Vide : aucun."],
    _mpuCat, "", 1] call comspec_atak_native_fnc_addSetting;
// Case « RELAIS ATAK » près du cadre de transmission d'ACRE2 (fn_relayTxBadge) : choix du joueur.
["comspec_atak_native_relay_tx_badge", "CHECKBOX",
    ["Case RELAIS ATAK pendant l'émission", "Affichée près du cadre de transmission d'ACRE2 quand votre voix passe réellement par le réseau (voix relayée activée sur le serveur, radio reliée à la MPU5)."],
    _mpuCat, true, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_relay_tx_badge_pos", "LIST",
    ["Position de la case RELAIS ATAK", "Le cadre d'ACRE2 se déplace dans la disposition de l'interface d'Arma : les deux premiers choix le suivent."],
    _mpuCat, [[0, 1, 2, 3], ["À gauche du cadre ACRE", "Au-dessus du cadre ACRE", "En haut à droite de l'écran", "En bas à gauche de l'écran"], 0], 0] call comspec_atak_native_fnc_addSetting;
// Notification MPU à l'écran pendant l'alternat (fn_mpuHud), juste au-dessus du cadre ACRE : choix du joueur, même valeur que l'interrupteur
// du téléphone (Réglages > Matériel et réseau).
["comspec_atak_native_mpu_hud", "CHECKBOX",
    ["Notification MPU à l'écran (alternat)", "Pendant que vous parlez à la radio (alternat ACRE2 ou TFAR), un petit encadré juste au-dessus du cadre ACRE, en bas à droite de l'écran, dit si votre voix passe par le maillage MPU5, et qui vous entendez par lui. Exemple : « MPU · voix par le maillage », puis « 2 sauts · 3 correspondants » ; en réception : « DUPONT via MPU », puis « 3 sauts ». Rien ne s'affiche quand la voix ne passe pas par la MPU5. Aussi dans le téléphone : Réglages > Matériel et réseau."],
    _mpuCat, true, 0, { if (!(_this isEqualTo true) && {hasInterface}) then { ["stop"] call comspec_atak_native_fnc_mpuHud; }; }] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_hud_why", "CHECKBOX",
    ["Dire pourquoi la MPU n'est pas utilisée", "Coché : pendant l'alternat, quand votre voix ne passe pas par la MPU5, l'encadré le dit en une ligne grise. Exemple : « MPU : non utilisée (aucun relais) ». Décoché (par défaut) : rien ne s'affiche dans ce cas."],
    _mpuCat, false, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_hud_dy", "SLIDER",
    ["Notification MPU : décalage vertical (%)", "Monte (valeur positive) ou descend (valeur négative) l'encadré, en pourcentage de la hauteur de l'écran, s'il gêne un autre affichage. Exemple : 5 le monte d'un vingtième de l'écran. 0 : juste au-dessus du cadre ACRE."],
    _mpuCat, [-20, 60, 0, 0], 0] call comspec_atak_native_fnc_addSetting;
// Tutoriel de l'app Wave Relay (fn_waveRelayTuto) : le bouton « ? » reste toujours là.
["comspec_atak_native_wr_tuto_offer", "CHECKBOX",
    ["Proposer le tutoriel du réseau", "Une seule fois, à la première ouverture de l'app Wave Relay. Le bouton « ? » de l'app l'ouvre à tout moment."],
    _mpuCat, true, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_cable", "CHECKBOX",
    ["Câble EUD obligatoire", "Il faut l'objet « Câble EUD » dans l'inventaire pour relier le téléphone à la MPU5 par câble."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_wifi", "CHECKBOX",
    ["Wi-Fi de la MPU5 autorisé", "Le téléphone peut se relier sans câble au Wi-Fi d'une MPU5 portée ou posée au sol, à quelques mètres."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_wifi_range", "SLIDER",
    ["Portée du Wi-Fi de la MPU5 (m)", "Au-delà, la liaison Wi-Fi tombe (elle revient seule en se rapprochant de la radio)."],
    _mpuCat, [2, 50, 8, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_hours", "SLIDER",
    ["Autonomie de la MPU5 (heures)", "Durée d'une batterie pleine à pleine puissance (6 W). À 3 W elle tient environ 1,3 fois plus, à 1 W presque 2 fois plus. Batterie vide : radio coupée, plus de maillage."],
    _mpuCat, [0.5, 48, 10, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mpu_hop_ms", "SLIDER",
    ["Latence par saut du maillage (ms)", "Délai ajouté par chaque radio traversée jusqu'à la passerelle. La portée (6 W) et le débit d'une MPU5 sont les réglages « Portée de la MPU5 » et « Débit relayé par une MPU5 » (section Simulation)."],
    _mpuCat, [0, 300, 15, 0], 1] call comspec_atak_native_fnc_addSetting;
// Radio du joueur : source et coupure (fn_radioGuard). Coupure : effet immédiat, sans redémarrage.
["comspec_atak_native_radio_source", "LIST",
    ["Radio utilisée", "Détectée automatiquement : ACRE2 ou TFAR s'ils sont chargés. Aucune : le mod ne s'appuie sur aucune radio d'un mod, seulement sur notre MPU5."],
    _mpuCat, [[0, 1, 2, 3], ["Détectée automatiquement", "ACRE2", "TFAR", "Aucune"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_radio_cut", "CHECKBOX",
    ["Couper tout lien avec la radio", "Coché : le mod ne lit plus aucune radio et rend tout de suite la fonction de signal d'ACRE2 et les multiplicateurs de TFAR. La voix reste celle d'ACRE2 ou de TFAR, sans le mod. Sans redémarrage."],
    _mpuCat, false, 1, { if (_this && {hasInterface}) then { [{ ["applyCut"] call comspec_atak_native_fnc_radioGuard; }] call comspec_atak_native_fnc_nextFrame; }; }] call comspec_atak_native_fnc_addSetting;
// Assistant de configuration (fn_setupWizard) : proposé une fois à l'admin.
["comspec_atak_native_wizard_offer", "CHECKBOX",
    ["Proposer l'assistant de configuration", "Une seule fois, à un admin ou un Zeus. L'assistant reste dans le menu Échap, Réalisme et réseau."],
    _mpuCat, true, 0] call comspec_atak_native_fnc_addSetting;
// Voix ACRE2 / TFAR relayée par le maillage MPU5 (fn_mpuVoice) : passerelle radio sur IP. Reste décochée par défaut : jamais jouée, et la voix des joueurs passe avant (voir fn_radioGuard).
["comspec_atak_native_mpu_voice", "CHECKBOX",
    ["Voix ACRE/TFAR relayée par le maillage MPU5", "Comme une passerelle radio sur IP : un joueur relie sa radio ACRE2 ou TFAR à sa MPU5 par câble audio (menu ACE Téléphone ATAK > Radio de données, ou app Réseau). Entre deux radios ainsi reliées, réglées sur la même fréquence, la voix passe par le maillage (MPU5, relais, pylônes, véhicules) : la distance et le relief ne la coupent plus. Hors maillage : portée radio normale. Nécessite la liaison téléphone-radio réaliste. TFAR : la portée de réception de la radio reliée s'allonge jusqu'au correspondant relié le plus lointain."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
// Voix par le maillage : exige au moins un relais en service dans la mission (fn_mpuVoice « _relayOk »).
["comspec_atak_native_mpu_voice_needRelay", "CHECKBOX",
    ["Voix par le maillage : au moins un relais", "Coché : la voix ne passe par les MPU5 que si la mission a au moins un relais en service pour votre camp (module « Poser un relais », pylône, véhicule passerelle, poste de commandement). Exemple : carte sans aucun relais, deux joueurs avec une MPU5 chacun : leurs radios gardent leur portée normale. Décoché : le maillage d'équipe du chef de groupe et les MPU5 voisines suffisent."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
// Graphe unique du maillage (fn_meshGraph) : calculé sur le serveur par défaut, plafond de sauts réglable.
["comspec_atak_native_mesh_server", "CHECKBOX",
    ["Graphe du maillage calculé sur le serveur", "Coché : une seule vérité pour tous, calculée par le serveur et diffusée sur changement, au plus toutes les 2 s. Décoché : chaque client calcule le graphe de son camp, sans diffusion mais avec des écarts possibles. N'agit qu'avec la liaison réaliste."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_hops", "SLIDER",
    ["Plafond de sauts du maillage", "Au-delà de ce nombre de radios traversées, le nœud est hors du maillage. Choix de jeu : les sources publiques de la MPU5 ne chiffrent aucun plafond."],
    _mpuCat, [1, 16, 6, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_gravity", "CHECKBOX",
    ["Gravité de coupure du maillage", "Le signal et les pertes suivent le pire lien du chemin : nominal, dégradé, limite, puis coupure nette vers la portée normale de la radio. Décoché : signal fixe quel que soit le lien."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_tfar_ptt", "CHECKBOX",
    ["TFAR : rallonge pendant l'émission seulement", "Portée d'émission allongée à l'appui sur l'alternat de la radio pontée, portée de réception allongée pendant l'émission d'un correspondant ponté. Décoché : réception allongée en permanence, comme avant. Sans TFAR : sans effet."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_hop_curve", "LIST",
    ["Perte de débit par saut", "Une radio à une seule voie émet et relaie sur le même canal. Choix de jeu : aucune source publique de la MPU5 ne chiffre cette perte."],
    _mpuCat, [[0, 1, 2], ["Selon le profil (Milsim : moitié puis tiers ; sinon x 0,8)", "Moitié puis tiers", "x 0,8 par saut"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_airtime", "CHECKBOX",
    ["Temps d'antenne partagé", "Les nœuds voisins d'un même canal se partagent le temps d'émission : changer de canal a un vrai intérêt."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_posture", "CHECKBOX",
    ["Antenne selon la posture (Milsim)", "Profil Milsim : hauteur d'antenne selon la posture, et un porteur couché derrière un obstacle proche perd la liaison."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_body_mask", "CHECKBOX",
    ["Masque du corps (Milsim)", "Profil Milsim : perte de 3 dB quand le porteur tourne le dos à son correspondant."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_mast", "CHECKBOX",
    ["Mât d'antenne de la MPU5", "Une MPU5 posée reliée en Wi-Fi peut déployer un mât (30 s, 8 m), visible de loin."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_traffic_power", "CHECKBOX",
    ["Batterie selon le trafic relayé", "Un nœud qui relaie beaucoup se vide plus vite, jusqu'à deux fois."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
// Réseau relais plus réaliste (4.2.1, fn_meshGraph) : bâtiments entre deux radios, reconvergence après la perte d'un relais, lien manquant sur la carte.
["comspec_atak_native_mesh_buildings", "SLIDER",
    ["Bâtiments dans le maillage (%)", "Liaison réaliste : chaque bâtiment traversé entre deux radios du maillage coûte du signal (relief seul avant). 0 : comportement d'avant. À 50 %, quatre maisons entre deux équipiers à 300 m dégradent le lien sans le couper ; à 100 %, il faut un relais sur un toit."],
    _mpuCat, [0, 100, 50, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_reconverge_s", "SLIDER",
    ["Reconvergence après la perte d'un relais (s)", "Liaison réaliste : quand un relais de votre route tombe (détruit, ramassé, batterie vide), le réseau se coupe ce temps-là avant de repasser par un autre chemin. 0 : bascule immédiate, comme avant."],
    _mpuCat, [0, 15, 3, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_miss_map", "CHECKBOX",
    ["Lien manquant sur la carte", "Liaison réaliste, hors maillage : un trait rouge en tirets relie votre groupe au nœud le plus proche à joindre, avec la cause (relief, distance, bâtiments, canal). Calque Wave Relay de la carte."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_stale_s", "SLIDER",
    ["Position périmée : gris après (s)", "Liaison réaliste : un ami sans nouvelle position passe en gris. 0 : comportement d'avant."],
    _mpuCat, [0, 600, 30, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_hide_s", "SLIDER",
    ["Position périmée : retirée après (s)", "Liaison réaliste : passé ce délai, le marqueur disparaît de la carte. 0 : comportement d'avant."],
    _mpuCat, [0, 1800, 180, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_pli_adaptive", "CHECKBOX",
    ["Cadence de position adaptative", "Liaison réaliste : position envoyée moins souvent à l'arrêt et sur un chemin dégradé."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_chunks", "CHECKBOX",
    ["Gros fichiers par blocs", "Liaison réaliste : photos et paquets partent par blocs de 128 Ko, avec progression et reprise après coupure."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_p2p", "CHECKBOX",
    ["Messages par le maillage", "Liaison réaliste : un message passe de joueur à joueur par le maillage, même sans passerelle."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_groups", "CHECKBOX",
    ["Groupes de conversation du maillage", "16 groupes : abonnement par la fréquence de la radio pontée ou dans l'app Wave Relay. Pas de rallonge voix hors groupe commun, messages de groupe aux seuls abonnés."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_keys", "CHECKBOX",
    ["Clés du maillage (mécanique de jeu)", "Clés de réseau et de groupe simulées : étiquettes et époques, aucune sécurité réelle. Clé absente ou périmée : hors du graphe, message chiffré à l'écran, voix sans rallonge."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_keys_auto", "CHECKBOX",
    ["Clés chargées automatiquement", "Coché : chaque MPU5 reçoit les clés de son camp au démarrage. Décoché : chargeur de clés ou poste de commandement."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_compromise_auto", "CHECKBOX",
    ["Compromission automatique des clés", "Une clé passe compromise quand un joueur d'un autre camp ramasse une MPU5 ou un chargeur. Le camp est prévenu ; une nouvelle époque l'exclut."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_tfar_code", "CHECKBOX",
    ["TFAR : clé de groupe en code de radio", "Radio pontée réglée sur le code de la clé de son groupe : sans la clé, rien ne s'entend, même en direct. Peut gêner une mission qui gère déjà ses codes. À confirmer en jeu."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_ew", "CHECKBOX",
    ["Goniomètre et analyseur de spectre", "Outils de guerre électronique du maillage dans l'app Guerre électronique, onglet GONIO."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_ew_role", "CHECKBOX",
    ["Goniomètre réservé au spécialiste", "Coché : rôle de mission de guerre électronique, ou variable d'unité COMSPEC_ATAK_EwOperator. Décoché : ouvert à tous."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_backup_channel", "SLIDER",
    ["Canal de secours (0 : aucun)", "Sous brouillage de son canal, la MPU5 bascule toute seule sur ce canal."],
    _mpuCat, [0, 16, 0, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_backup_delay", "SLIDER",
    ["Délai avant le canal de secours (s)", "Temps de brouillage subi avant la bascule."],
    _mpuCat, [2, 60, 10, 0], 1] call comspec_atak_native_fnc_addSetting;
// Chiffrement plus réaliste (fn_meshKeys) : sans effet tant que « Clés du maillage » est décoché ; nouveaux réglages décochés, sauf le coût discret. Le profil Arcade les coupe.
["comspec_atak_native_mesh_keys_fill", "CHECKBOX",
    ["Chargeur de clés : chargement manuel", "Coché : CHARGER LES CLÉS (app Wave Relay ou menu ACE Téléphone ATAK > Radio de données) demande un chargeur de clés dans l'inventaire ou le poste de commandement, et dure 6 s. Le chargement au démarrage suit toujours « Clés chargées automatiquement ». Décoché : comportement d'avant."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_keys_period", "LIST",
    ["Période cryptographique", "Durée de vie d'une clé chargée. Alerte 10 min avant ; ensuite le cadenas passe en PÉRIMÉE, les messages partent en clair et la MPU5 quitte le maillage chiffré jusqu'au rechargement (chargeur, poste de commandement ou OTAR). Aucune : une clé ne périme jamais, comme avant."],
    _mpuCat, [[0, 30, 60, 120, 240, 480], ["Aucune", "30 min", "1 h", "2 h", "4 h", "8 h"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_keys_otar", "CHECKBOX",
    ["Rechargement à distance (OTAR)", "Coché : le chef de groupe (ou le poste de commandement), avec une clé valide, renouvelle les clés des équipiers joints par le maillage : bouton RENOUVELER LE GROUPE (OTAR) de l'app Wave Relay. Un équipier hors maillage passe au chargeur."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_crypto_cost", "LIST",
    ["Coût du chiffrement", "Tant que la liaison est chiffrée : un peu de débit en moins, de délai et de batterie en plus. Visible dans l'app Réseau, Débit du téléphone. Sans clés du maillage : sans effet."],
    _mpuCat, [[0, 1, 2], ["Aucun", "Discret (-5 % de débit, +20 ms)", "Marqué (-15 % de débit, +60 ms)"], 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_intercept", "CHECKBOX",
    ["Interception du trafic en clair", "Coché : un spécialiste ennemi avec un goniomètre intercepte les messages de groupe émis à portée. En clair : position de l'émetteur à 100 m près et une partie du texte. Chiffré : une émission et sa direction, jamais le contenu. App Guerre électronique, onglet GONIO."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_mesh_tools", "CHECKBOX",
    ["Outils du maillage (Zeus et joueurs)", "Clic droit Zeus « ATAK · Maillage » ; topologie, ping et prévision de couverture dans l'app Wave Relay."],
    _mpuCat, true, 1] call comspec_atak_native_fnc_addSetting;
// Outils de développement du maillage (fn_devMesh) : rien n'existe pour un joueur tant que ce réglage serveur est décoché.
["comspec_atak_native_mesh_dev", "CHECKBOX",
    ["Outils de développement du maillage", "Nœuds simulés, superposition 3D, inspecteur de lien, tableau de performance et export du graphe (app Debug, onglet MAILLAGE), pour les seuls admins et Zeus. À laisser décoché en partie normale."],
    _mpuCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_aar", "CHECKBOX",
    ["Rejeu de mission", "Chaque téléphone enregistre les positions de son camp toutes les 10 s, et les pertes alliées, pour revoir la mission dans l'app Rejeu. Décochez pour ne rien enregistrer."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_drone_range", "SLIDER", ["Portée de la liaison drone (m)", "Distance maximale entre le téléphone du pilote et son drone, divisée par trois si le relief les sépare. Au-delà, le drone rentre seul à son point de décollage. Avec le mod Mavic, sa propre portée (mavic_setting_maxConnectionDistance) s'applique si elle est plus grande."], _sim, [500, 12000, 6000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_video_relays", "CHECKBOX",
    ["Vidéo dépendante des relais", "Si la mission a des relais ATAK, les vidéos (drone, caméra casque, caméra en direct, caméras fixes) passent par eux : qualité (de 1080p à image par image), fluidité, délai et parasites dépendent de la distance aux relais, du nombre de relais traversés et de la perte d'un relais. Sans relais dans la mission, rien ne change. Décochez pour toujours avoir une vidéo parfaite."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
// BFT par points de position (fn_pli) et GPS naturel (fn_gpsFix).
["comspec_atak_native_net_pli", "CHECKBOX",
    ["Positions BFT par points (PLI)", "Comme un vrai ATAK : chaque téléphone envoie sa position à intervalle régulier, telle que son GPS la voit (avec son erreur). Les alliés avancent par bonds sur la carte, un allié mal couvert apparaît en retard ou en gris même si vous avez du réseau. Décoché : positions exactes en direct."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_net_pli_rate", "LIST",
    ["Intervalle des positions BFT", "Temps entre deux envois de position d'un téléphone. Un joueur qui bouge vite envoie un peu plus souvent (au plus deux fois plus)."],
    _sim, [[2, 5, 10, 15, 30, 60], ["2 s", "5 s", "10 s", "15 s", "30 s", "60 s"], 2], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_gps_sim", "CHECKBOX",
    ["GPS réaliste", "La précision du GPS dépend du ciel visible : quelques mètres à découvert, plus en forêt dense, dans une rue étroite ou au fond d'une vallée, beaucoup plus dans un bâtiment, aucun fix en sous-sol (dernière position gardée). Décoché : GPS exact (hors brouillage et dégâts)."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_gps_k", "LIST",
    ["Force des erreurs GPS", "100 % : erreurs réalistes. 50 % : moitié moins. Au-dessus : GPS plus capricieux."],
    _sim, [[50, 100, 150, 200], ["50 %", "100 % (réel)", "150 %", "200 %"], 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_gps_fix", "CHECKBOX",
    ["Attente des satellites", "Au démarrage du téléphone ou après un changement de batterie, le GPS met de quelques secondes à environ 40 s à trouver les satellites ; en attendant, position approximative par le réseau. Décoché : position tout de suite."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_gps_loss", "LIST",
    ["Pertes de fix GPS", "Part des satellites masqués (toit, forêt, immeubles, relief) qui compte vraiment. 100 % : comme un vrai téléphone, plus de fix dans un bâtiment ou en sous-sol. 0 % : le fix tient partout, seule la précision baisse. Au-dessus de 100 % : le fix se perd plus vite."],
    _sim, [[0, 50, 100, 150, 200], ["0 % (fix partout)", "50 %", "100 % (réel)", "150 %", "200 %"], 2], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_gps_rel", "LIST",
    ["Fiabilité du GPS", "Décrochages du fix (5 à 25 s, dernière position gardée puis position par le réseau) et sauts de position de 15 à 60 m (reflets du signal) qui reviennent en quelques secondes. Parfaite : jamais. Bonne : environ une fois toutes les 20 min. Moyenne : toutes les 6 min. Médiocre : toutes les 2 min, et satellites plus longs à trouver."],
    _sim, [[0, 1, 2, 3], ["Parfaite", "Bonne (réel)", "Moyenne", "Médiocre"], 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_camera_share", "LIST",
    ["Partage des caméras", "Partage obligatoire (par défaut) : une caméra fixe n'est visible que par celui qui l'a posée, et la caméra d'un téléphone que par son propriétaire, tant qu'ils ne l'ont pas partagée (app Vidéo live > PARTAGER, par NFC, Bluetooth ou relais, à un joueur ou à TOUS). Tout le camp voit tout : chaque ATAK voit toutes les caméras de son camp sans rien faire."],
    _sim, [[0, 1], ["Partage obligatoire", "Tout le camp voit tout"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_bt_range", "SLIDER",
    ["Portée Bluetooth (m)", "Distance maximale entre deux téléphones pour se jumeler, s'envoyer des sons ou partager une caméra en Bluetooth. Le NFC, lui, marche à 2 m (téléphones dos à dos)."],
    _sim, [5, 100, 15, 0], 1] call comspec_atak_native_fnc_addSetting;
// Réglages lus par le code sans entrée dans le menu (valeur fixe jusqu'ici) : portée Wi-Fi des caméras fixes (fn_feedLink,
// fn_camAction, fn_camShare), portée du son joué en Bluetooth (fn_btAction), capteur du détecteur de drones (fn_droneDetectScan).
["comspec_atak_native_camera_range", "SLIDER",
    ["Portée Wi-Fi des caméras fixes (m)", "Distance jusqu'à laquelle une caméra fixe envoie son image directement au téléphone le plus proche, sans passer par le réseau. Au-delà, il faut un relais ou la liaison Athena."],
    _sim, [100, 2000, 600, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_bt_sound_range", "SLIDER",
    ["Portée du son envoyé en Bluetooth (m)", "Distance à laquelle les joueurs autour entendent un son joué par un téléphone après un envoi Bluetooth."],
    _sim, [5, 100, 20, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_drone_sensor", "CHECKBOX",
    ["Anti-drone : capteur dédié", "Coché par défaut : l'app Anti-drone a un vrai capteur radio, portée normale. Décoché : elle n'a que l'antenne du téléphone, portée divisée par deux. Une mission peut aussi le régler par joueur (variable COMSPEC_ATAK_DroneSensor)."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_drone_strike", "CHECKBOX",
    ["Drones armés", "Permet d'accrocher une charge sous un drone (roquette de RPG, charge de démolition, grenade) et de l'envoyer frapper : sur une cible désignée (tire et oublie) ou en patrouille qui attaque ce qu'elle trouve (recherche et frappe)."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_drone_autospot", "CHECKBOX",
    ["Repérage automatique par les drones", "Décoché par défaut : le pilote repère et marque lui-même. Coché : la caméra du drone repère seule les ennemis qu'elle voit (onglet CIBLES, traces sur la carte) et les drones d'escorte préviennent le pilote quand des ennemis sont proches."],
    _sim, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_apps_off", "EDITBOX", ["Apps retirées", "Apps à retirer de tous les téléphones : leurs noms de classe séparés par des virgules (ex. Dating, Food, MonModule). Marche aussi pour les apps ajoutées par d'autres mods."], _sim, "", 1] call comspec_atak_native_fnc_addSetting;
// App Store (fn_appInstall) : apps imposées à tous les joueurs du serveur. La mission, le Zeus (app Admin, onglet APPS)
// et la communauté Athena (Contrôle serveur) peuvent en ajouter ; une app interdite par l'un d'eux l'emporte.
["comspec_atak_native_apps_required", "EDITBOX", ["Apps obligatoires", "Apps toujours installées sur tous les téléphones : le joueur ne peut pas les désinstaller de l'App Store. Noms de classe séparés par des virgules (ex. Map, Chat, Bft)."], _sim, "", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_apps_forbidden", "EDITBOX", ["Apps interdites", "Apps que personne ne peut installer ni ouvrir : elles restent visibles dans l'App Store avec la mention INTERDITE. Noms de classe séparés par des virgules (ex. Dating, Food). Pour qu'une app n'existe plus du tout, utilisez « Apps retirées »."], _sim, "", 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_civil_apps", "CHECKBOX",
    ["Apps civiles", "UberEats (rations livrées par drone) et Tinder (rencontres entre joueurs). Décochez pour les retirer de tous les téléphones."],
    _sim, true, 1] call comspec_atak_native_fnc_addSetting;
// Lanceur en apps regroupées et mode pilote (fn_appSpaces, fn_pilotMode) : rien ne change tant que personne ne les active.
private _spc = ["COMSPEC", "ATAK · Lanceur et mode pilote"];
["comspec_atak_native_launcher_spaces", "LIST",
    ["Lanceur : apps regroupées", "Le lanceur présente 5 grandes apps (Navigation et feux, Renseignement, Spécialités, Messages, Mission et téléphone), avec des sous-groupes qui ouvrent les apps existantes. Une app retirée ou interdite n'y apparaît pas. Au choix du joueur : coupé tant qu'il ne l'active pas dans Réglages, Applications."],
    _spc, [[0, 1, 2], ["Au choix du joueur (désactivé par défaut)", "Activé pour tous", "Désactivé pour tous"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_pilot_mode", "LIST",
    ["Mode pilote", "Écran simplifié proposé en montant dans un aéronef : grande carte, appareils alliés avec altitude et cap, FIRES en un geste, HUD discret. Au choix du joueur : coupé tant qu'il ne l'active pas dans Réglages, Applications."],
    _spc, [[0, 1, 2], ["Au choix du joueur (désactivé par défaut)", "Proposé à tous", "Coupé pour tous"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_dating_specials", "LIST",
    ["Tinder : profils HVT et SSE", "À quelle fréquence Tinder glisse, entre deux joueurs, un profil d'IA de la carte (HVT ou personne SSE à moins de 5 km). Zeus peut aussi désigner une IA avec le module « Tinder : profil HVT / SSE »."],
    _sim, [[0, 1, 2, 3], ["Jamais", "Rarement (1 profil sur 10)", "Parfois (1 sur 4)", "Souvent (1 sur 2)"], 1], 1] call comspec_atak_native_fnc_addSetting;
// App Communauté (fn_tenantAction) : kit obligatoire, comparé toutes les 30 s.
["comspec_atak_native_kit_required", "EDITBOX",
    ["Kit obligatoire", "Objets exigés, noms de classe séparés par des virgules. Nombre : classe*2. Au choix : classeA|classeB. Vide : aucun. Une mission peut définir COMSPEC_ATAK_RequiredKit à la place."],
    ["COMSPEC", "ATAK · Communauté"], "", 1] call comspec_atak_native_fnc_addSetting;
// Téléphones personnels (fn_phoneOwn, fn_phoneServer) : propriétaire, lecture du téléphone d'un autre, code.
private _phones = ["COMSPEC", "ATAK · Téléphones personnels"];
["comspec_atak_native_phone_owner", "CHECKBOX",
    ["Téléphones personnels", "Chaque téléphone a un propriétaire. Celui qui ramasse le téléphone d'un autre lit ce téléphone (code demandé s'il en a un), sans l'ATAK ni le compte du lecteur. Décoché : n'importe quel téléphone donne accès à l'ATAK, comme avant."],
    _phones, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_pin_attempts", "SLIDER",
    ["Code du téléphone : essais", "Essais avant un blocage temporaire. Un admin ou un Zeus retire le code depuis l'app Admin."],
    _phones, [3, 10, 5, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_pin_block", "SLIDER",
    ["Code du téléphone : blocage (s)", "Durée du blocage après le dernier essai. Elle n'est jamais définitive."],
    _phones, [10, 1800, 120, 0], 1] call comspec_atak_native_fnc_addSetting;
// Synchroniser le téléphone avec le web (fn_phoneSync, ICD-SSE-PHONE-001). Accord du joueur : réglage du joueur seul (2),
// qu'aucun serveur ni aucune mission ne peut cocher à sa place. Le MJ peut l'interdire pour la mission.
["comspec_atak_native_phone_sync", "CHECKBOX",
    ["Autoriser la synchronisation du téléphone avec Athena", "Décoché par défaut : rien n'est envoyé. Coché : l'action Synchroniser avec le web envoie aux fichiers d'Athena le contenu du téléphone de votre personnage (contacts, messages, notes, noms des photos, apps, positions, repères, numéro et IMEI du jeu), jamais rien de votre PC. Une fois par minute au plus. Exemple : cochez, puis menu ACE, Téléphone ATAK, Synchroniser avec le web : la référence EXT-000123 s'affiche dans le téléphone."],
    _phones, false, 2] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_phone_sync_off", "CHECKBOX",
    ["Interdire la synchronisation des téléphones pendant cette mission", "Coché : aucun téléphone n'est envoyé au web pendant cette mission, même si le joueur l'a autorisé. Exemple : cochez pour une mission d'entraînement dont les téléphones ne doivent pas remplir les fichiers d'Athena. Un Zeus peut aussi l'interdire en cours de partie avec la variable de mission COMSPEC_ATAK_PhoneSyncDisabled à true."],
    _phones, false, 1] call comspec_atak_native_fnc_addSetting;
// Tinder : chasse aux HVT (fn_hvtIntel). Chaque cible peut aussi être réglée à part (module Eden / Zeus « Tinder : profil HVT / SSE »).
private _hvt = ["COMSPEC", "ATAK · Tinder : chasse aux HVT"];
// Traces civiles (fn_hvtTrace) : décochées par défaut, jamais de position exacte ni de suivi en direct.
["comspec_atak_native_hvt_trace", "CHECKBOX",
    ["Traces civiles des HVT", "Les apps civiles (Tinder, UberEats) révèlent quand et dans quelle zone d'1 km une HVT était active, avec du retard. Jamais sa position du moment. La mission ou le Zeus peut aussi les activer."],
    _hvt, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_trace_role", "CHECKBOX",
    ["Traces civiles : renseignement seul", "Coché : réservé aux rôles de renseignement (renseign, intel, s2, g2 dans le rôle), à COMSPEC_ATAK_IntelOperator, au Zeus et à l'admin."],
    _hvt, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_trace_every", "SLIDER",
    ["Traces civiles : intervalle (min)", "Temps moyen entre deux relevés d'une cible. Tous ne donnent pas une trace."],
    _hvt, [1, 60, 5, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_trace_delay", "SLIDER",
    ["Traces civiles : retard (min)", "Âge minimal d'une trace à sa publication. Jamais moins de 2 min."],
    _hvt, [2, 60, 10, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_tips", "LIST",
    ["Tinder : tuyaux sur les HVT", "Chasse aux HVT dans Tinder : à quelle fréquence les informateurs likent (envoient un tuyau, vrai ou faux) sur les HVT de la carte. Plus de civils autour du joueur, plus de tuyaux. Jamais = pas de tuyaux ni de dossiers."],
    _hvt, [[0, 1, 2, 3], ["Jamais", "Rarement", "Normal", "Souvent"], 2], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_false", "LIST",
    ["Part de faux tuyaux", "Combien d'informateurs se trompent ou mentent. Moins : chasse plus facile. Plus : il faut vraiment recouper. Une cible peut avoir sa propre part (module)."],
    _hvt, [[0, 1, 2], ["Moins", "Normale", "Plus"], 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_stale", "SLIDER",
    ["Péremption d'un tuyau (min)", "Après ce délai, un tuyau ne compte plus dans la position estimée du dossier."],
    _hvt, [10, 120, 45, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_drift", "SLIDER",
    ["Dérive de la cible (m/s)", "Vitesse à laquelle la zone d'un tuyau grossit avec le temps (la cible bouge). 0 = cible immobile ; 0,8 = à pied ; plus = en véhicule."],
    _hvt, [0, 3, 0.8, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_lie", "SLIDER",
    ["Faux rendez-vous d'un HVT matché (%)", "Chance qu'un HVT matché sur Tinder donne un faux rendez-vous (piège). Une cible peut avoir sa propre valeur (module)."],
    _hvt, [0, 100, 25, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_civ", "CHECKBOX",
    ["Les civils font parler", "Coché : plus il y a de civils autour du joueur, plus les informateurs envoient de tuyaux. Décoché : même fréquence partout."],
    _hvt, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_hvt_share", "CHECKBOX",
    ["Partage des tuyaux au groupe", "Coché : le bouton PARTAGER d'un dossier envoie ses tuyaux confirmés aux joueurs du groupe."],
    _hvt, true, 1] call comspec_atak_native_fnc_addSetting;

// Marquage au viseur (caméra du drone, appareil photo du téléphone) et cibles perdues.
private _lock = ["COMSPEC", "ATAK · Marquage de cible"];
["comspec_atak_native_lock_enabled", "CHECKBOX",
    ["Marquage de cible à la caméra", "Visez un ennemi avec la caméra du drone ou l'appareil photo et appuyez : la cible est marquée sur la carte (LOCK) et suivie tant qu'elle reste visible."],
    _lock, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lock_live", "CHECKBOX",
    ["Le marqueur suit la cible", "Coché : tant que la caméra voit la cible, le marqueur bouge avec elle (une fois par seconde). Décoché : il reste là où la cible a été marquée."],
    _lock, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lock_grace", "SLIDER",
    ["Délai avant cible perdue (s)", "Nombre de secondes sans voir la cible (hors champ, cachée, trop loin, drone sans liaison) avant qu'elle soit considérée comme perdue."],
    _lock, [1, 30, 5, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lock_lost", "LIST",
    ["Cible perdue", "Ce qui se passe quand la cible n'est plus visible. Le cadre est le carré LOCK dessiné sur l'image de la caméra ; le marqueur est celui de la carte (et d'Athena)."],
    _lock, [[0, 1, 2, 3], ["Cadre retiré, marqueur laissé à la dernière position", "Cadre retiré, marqueur effacé après le délai ci-dessous", "Cadre et marqueur effacés tout de suite", "Tout est gardé (cadre gris à la dernière position)"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lock_keep", "SLIDER",
    ["Durée d'un marqueur de cible perdue (s)", "Secondes avant d'effacer le marqueur d'une cible perdue (choix « effacé après le délai ») ou neutralisée."],
    _lock, [30, 1800, 300, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lock_reacquire", "CHECKBOX",
    ["Reprise automatique de la cible", "Une cible perdue qui réapparaît à l'image est reprise toute seule, avec le même numéro LOCK. Décoché : il faut la marquer de nouveau."],
    _lock, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_lock_range", "SLIDER",
    ["Portée du marquage à l'appareil photo (m)", "Distance maximale pour marquer et suivre une cible avec l'appareil photo du téléphone. La caméra du drone garde sa propre portée."],
    _lock, [100, 2000, 800, 0], 1] call comspec_atak_native_fnc_addSetting;

// App Garmin : la montre Garmin (objet COMSPEC_ATAK_GarminWatch) portée dans l'emplacement montre, ou en objet divers
// (COMSPEC_ATAK_GarminWatchItem, dans le sac ou la veste).
["comspec_atak_native_garmin_watch", "CHECKBOX",
    ["Montre Garmin obligatoire", "Coché par défaut : l'app Garmin ne mesure rien (pas, cardio, stress, sommeil, activités) et n'envoie rien au site si le joueur n'a pas la « Montre Garmin » (à la place de la montre, ou la « Montre Garmin (objet divers) » dans son équipement). Décochez pour que le téléphone mesure tout seul."],
    ["COMSPEC", "ATAK · Garmin"], true, 1] call comspec_atak_native_fnc_addSetting;
// Montre Garmin au poignet (touche Montre du jeu, O) : taille et place à l'écran (réglages du joueur).
["comspec_atak_native_watch_size", "LIST",
    ["Taille de la montre Garmin (touche O)", "Taille de la montre allumée avec la touche Montre du jeu."],
    ["COMSPEC", "ATAK · Garmin"], [[0, 1, 2], ["Petite", "Moyenne", "Grande"], 1], 0, { if ((uiNamespace getVariable ["COMSPEC_ATAK_Watch", createHashMap]) getOrDefault ["open", false]) then { ["tick"] call comspec_atak_native_fnc_watch; }; }] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_watch_side", "LIST",
    ["Place de la montre Garmin (touche O)", "En bas de l'écran : à gauche, au centre ou à droite."],
    ["COMSPEC", "ATAK · Garmin"], [[0, 1, 2], ["Gauche", "Centre", "Droite"], 0], 0] call comspec_atak_native_fnc_addSetting;

// Extraction de données au câble DL-7 (app Extraction, modules Zeus / Eden « ATAK · Données »).
private _data = ["COMSPEC", "ATAK · Extraction de données"];
["comspec_atak_native_data_cable", "CHECKBOX",
    ["Câble DL-7 obligatoire", "Coché : il faut le « Câble de données DL-7 » dans son équipement pour se brancher sur un appareil à exploiter. Décoché : le téléphone suffit."],
    _data, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_data_length", "SLIDER",
    ["Longueur du câble (m)", "Si vous vous éloignez plus que ça de l'appareil branché, le câble s'arrache : le transfert s'arrête, mais les fichiers déjà copiés sont gardés."],
    _data, [1.5, 10, 3, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_data_speed", "SLIDER",
    ["Vitesse des transferts", "Multiplie la vitesse de copie et de déverrouillage prévue par le mission maker (2 = deux fois plus rapide)."],
    _data, [0.25, 4, 1, 2], 1] call comspec_atak_native_fnc_addSetting;
// Serveur de l'extraction : branché dès le preInit (les modules Eden posent leurs données au démarrage).
// « code » (code de fin exécuté au serveur) refusé ici : il n'arrive que par fn_dataSetup, droits contrôlés.
if (isServer) then { ["comspec_atak_native_dataSrv", { if ((_this param [0, ""]) isNotEqualTo "code") then { _this call comspec_atak_native_fnc_dataServer; }; }] call CBA_fnc_addEventHandler; };
// Passerelles de véhicule (fn_vehicleGateway) et messages courts du serveur vers des joueurs.
if (isServer) then { ["comspec_atak_native_gwSrv", { _this call comspec_atak_native_fnc_vehicleGateway; }] call CBA_fnc_addEventHandler; };
// Ordres aux groupes IA (fn_aiOrder) : exécutés là où le groupe est local (serveur, client headless, joueur).
["comspec_atak_native_aiOrdExec", { ["exec", _this] call comspec_atak_native_fnc_aiOrder; }] call CBA_fnc_addEventHandler;
// Serveur (aussi en solo, Eden et partie hébergée) : demandes vérifiées ici, dernier état de chaque groupe gardé pour
// les arrivants. Branché dès le preInit, sans dépendre du reste de XEH_postInitServer. COMSPEC_ATAK_SrvAiOrdV dit aux
// clients que le serveur sait traiter les ordres (version 1 : 2.9.29) ; absent, le téléphone explique pourquoi rien ne part.
if (isServer) then {
    if (isNil "COMSPEC_ATAK_SrvAiOrd") then { COMSPEC_ATAK_SrvAiOrd = createHashMap; };    // camp -> (netId du groupe -> état)
    ["comspec_atak_native_aiOrdSrv", { ["srv", _this] call comspec_atak_native_fnc_aiOrder; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_aiOrdState", { ["keep", _this] call comspec_atak_native_fnc_aiOrder; }] call CBA_fnc_addEventHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_SrvAiOrdV", 1, true];
};
// Appui feu typé (fn_fireSupport) : tir là où le servant IA est local ; serveur : demandes vérifiées, batteries
// simulées, dernier état des missions gardé pour les arrivants. COMSPEC_ATAK_SrvFsV : le serveur sait les traiter.
["comspec_atak_native_fsExec", { ["exec", _this] call comspec_atak_native_fnc_fireSupport; }] call CBA_fnc_addEventHandler;
if (isServer) then {
    if (isNil "COMSPEC_ATAK_SrvFs") then { COMSPEC_ATAK_SrvFs = createHashMap; };      // camp -> (id -> état)
    if (isNil "COMSPEC_ATAK_SrvFsRun") then { COMSPEC_ATAK_SrvFsRun = createHashMap; };  // id -> tir simulé en cours
    ["comspec_atak_native_fsSrv", { ["srv", _this] call comspec_atak_native_fnc_fireSupport; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_fsState", { ["keep", _this] call comspec_atak_native_fnc_fireSupport; }] call CBA_fnc_addEventHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_SrvFsV", 1, true];
};
// Téléphones ramassés par l'IA ennemie : actions là où l'unité est locale, état au serveur, alertes aux clients.
["comspec_atak_native_capLocal", { ["local", _this] call comspec_atak_native_fnc_captureServer; }] call CBA_fnc_addEventHandler;
if (isServer) then { ["comspec_atak_native_captureSrv", { params [["_a", ""], ["_args", []]]; if (_a in ["dropped", "giveFail", "validate", "dismiss", "athena"]) then { [_a, _args] call comspec_atak_native_fnc_captureServer; }; }] call CBA_fnc_addEventHandler; };
if (hasInterface) then { ["comspec_atak_native_compromise", { params [["_k", ""], ["_v", []]]; if (_k in ["revoke", "ownerReset"]) then { [_k, _v] call comspec_atak_native_fnc_compromise; } else { ["event", [_k, _v]] call comspec_atak_native_fnc_compromise; }; }] call CBA_fnc_addEventHandler; };
// Centre Zeus : relevé à la demande, chaque joueur ne répond qu'au serveur (fn_zeusState).
if (hasInterface) then {
    ["comspec_atak_native_zeusStatePoll", { ["poll"] call comspec_atak_native_fnc_zeusState; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_zeusStateData", { ["data", _this select 0] call comspec_atak_native_fnc_zeusState; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_zeusObjectOpen", { ["open", _this] call comspec_atak_native_fnc_zeusObject; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_zeusStateDenied", { ["denied"] call comspec_atak_native_fnc_zeusState; }] call CBA_fnc_addEventHandler;
    // État du serveur (onglet SERVEUR du Centre Zeus) : réponse du serveur au seul Zeus demandeur.
    ["comspec_atak_native_zeusSrvData", { ["srvData", _this select 0] call comspec_atak_native_fnc_zeusState; }] call CBA_fnc_addEventHandler;
};
// App Admin : un admin demande l'état de la partie, chaque joueur lui répond directement (fn_adminClient).
if (hasInterface) then {
    ["comspec_atak_native_adminReport", { ["report", _this] call comspec_atak_native_fnc_adminClient; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_adminData", { ["data", _this] call comspec_atak_native_fnc_adminClient; }] call CBA_fnc_addEventHandler;
};
if (hasInterface) then { ["comspec_atak_native_note", { params [["_t", ""]]; ["INFO", _t, 5, 40] call comspec_atak_native_fnc_notify; }] call CBA_fnc_addEventHandler; };

// Données médicales affichées par l'ATAK : décocher masque la donnée (ou l'endroit) pour tous, sans choix possible (fn_medShow).
private _med = ["COMSPEC", "ATAK · Données médicales"];
{
    _x params ["_k", "_label"];
    [format ["comspec_atak_native_med_%1", _k], "CHECKBOX",
        [format ["Afficher : %1", _label], "Décoché : n'apparaît nulle part dans l'ATAK (app Médical, moniteur, échange NFC, rapports, carte, alertes), pour tout le monde. Coché : chaque joueur peut quand même le cacher pour lui dans Réglages > Réalisme."],
        _med, true, 1] call comspec_atak_native_fnc_addSetting;
} forEach ((["fields"] call comspec_atak_native_fnc_medShow) + (["contexts"] call comspec_atak_native_fnc_medShow));

// Socle de recharge CS-10 (fn_dockServer).
private _dock = ["COMSPEC", "ATAK · Socle de recharge"];
["comspec_atak_native_dock_rate", "SLIDER",
    ["Vitesse de charge (% par minute)", "Recharge de chaque téléphone posé sur un socle CS-10. À 1,5 % par minute, un téléphone vide est plein en un peu plus d'une heure."],
    _dock, [0.5, 10, 1.5, 1], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_dock_offline", "CHECKBOX",
    ["La charge continue entre deux parties", "Un téléphone laissé sur un socle continue de charger pendant que le serveur est éteint (selon le temps réel écoulé)."],
    _dock, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_dock_persist", "CHECKBOX",
    ["Garder les socles posés", "Les socles posés par les joueurs reviennent à leur place, avec leurs téléphones, à la prochaine partie sur la même carte. Pour les socles de la mission (Eden, Zeus), les téléphones sont toujours gardés."],
    _dock, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_dock_anyone", "CHECKBOX",
    ["Tout le monde peut reprendre n'importe quel téléphone", "Décoché : seul le propriétaire (compte Steam) peut reprendre son téléphone. Zeus et l'admin peuvent toujours."],
    _dock, false, 1] call comspec_atak_native_fnc_addSetting;
// Mode full local et PC portable de TOC (fn_localCfg, fn_tocPost, fn_orderLocal).
private _loc = ["COMSPEC", "ATAK · Mode local et poste TOC"];
["comspec_atak_native_local_only", "CHECKBOX",
    ["Mode full local (sans Athena)", "Coché : personne ne se connecte à Athena sur ce serveur, la question du service Athéna disparaît. Ordres et ordres aux IA, messagerie, messages du poste, marqueurs, comptes rendus, MEDEVAC, 9-line, diapositives de la mission, numéros de téléphone et carnet passent en jeu par ce serveur ; chaque joueur garde sa configuration dans Documents\Arma 3\COMSPEC_Local\config.json."],
    _loc, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_toc_enabled", "CHECKBOX",
    ["PC portables de TOC", "Un joueur à côté d'un PC de TOC (objet « PC portable de TOC (ATAK) » d'Eden ou de Zeus) a l'ATAK du poste et l'app Poste TOC : message à tout le camp, ordres aux groupes, situation, comptes rendus. Aussi sans Athena."],
    _loc, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_toc_anylaptop", "CHECKBOX",
    ["Tous les ordinateurs portables servent de PC de TOC", "Coché : les ordinateurs portables et postes informatiques du jeu posés dans la mission deviennent des PC de TOC (pour tous les camps). Décoché : seulement l'objet PC portable de TOC et les objets désignés par la mission (this setVariable [""COMSPEC_ATAK_Toc"", true, true])."],
    _loc, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_toc_persist", "CHECKBOX",
    ["Garder les écrans TOC", "Les écrans TOC (module Écran TOC) sont gardés par le serveur d'une partie à l'autre sur la même mission : écrans posés au Zeus recréés à leur place, affichage et cadrage des écrans de la mission remis, flux des comptes rendus repris."],
    _loc, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_toc_cam_max", "SLIDER",
    ["Caméras en direct sur les écrans TOC (par joueur)", "Nombre d'écrans TOC en affichage caméra que chaque joueur rend en direct en même temps (les plus proches). 0 : jamais d'image en direct, l'écran garde la carte et le flux."],
    _loc, [0, 4, 2, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_toc_cam_range", "SLIDER",
    ["Distance d'activation des caméras d'écran (m)", "Un écran TOC en affichage caméra ne montre l'image en direct qu'aux joueurs plus proches que cette distance et qui le regardent. Plus loin, il montre la carte et le flux du poste."],
    _loc, [10, 150, 40, 0], 1] call comspec_atak_native_fnc_addSetting;
// IA et ATAK (fn_aiAtak).
private _ai = ["COMSPEC", "ATAK · IA"];
["comspec_atak_native_ai_atak", "LIST",
    ["IA équipées d'un ATAK", "Le serveur donne un téléphone ATAK aux IA : aucune (la mission décide, module IA équipées), les chefs de groupe, ou toutes. Une IA équipée apparaît sur le BFT et les écrans TOC comme un nœud du réseau."],
    _ai, [["off", "leaders", "all"], ["Aucune (la mission décide)", "Chefs de groupe", "Toutes les IA"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ai_realism", "CHECKBOX",
    ["IA réalistes sur le réseau", "Une IA n'apparaît sur le BFT que si elle porte vraiment un ATAK (même quand les joueurs n'en ont pas besoin) ; ses positions sont envoyées par le serveur au rythme du réseau (PLI), avec une erreur GPS propre à chaque IA, identiques pour tous, et se perdent près d'un brouilleur."],
    _ai, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ai_atak_item", "EDITBOX",
    ["Objet donné aux IA", "Classe de l'objet ATAK mis dans l'inventaire des IA équipées (COMSPEC_ATAK_Phone par défaut)."],
    _ai, "COMSPEC_ATAK_Phone", 1] call comspec_atak_native_fnc_addSetting;
// Ordres aux IA (fn_aiOrder « contact ») : compte rendu de contact d'un groupe IA commandé, une fois par minute au plus.
["comspec_atak_native_ai_contact", "CHECKBOX",
    ["Compte rendu de contact des groupes IA", "Un groupe IA à qui l'on a donné un ordre (téléphone, PC du poste TOC) signale lui-même ses contacts : direction, distance, ennemis repérés et blessés du groupe, au plus une fois par minute, dans une bulle au-dessus de son chef et une notification au donneur d'ordre. Décoché : il se tait. Exemple : « Alpha 1-2 : Contact nord-est à 250 m, 3 ennemis repérés, 1 blessés »."],
    _ai, true, 1] call comspec_atak_native_fnc_addSetting;
// Appui feu typé (fn_fireSupport) : complète l'app CAS (APPEL DE FEU), la carte, Tâches, le poste TOC et l'app JTAC.
// Réglages serveur (forçables par la mission), sauf l'affichage des cercles (réglage personnel).
private _fs = ["COMSPEC", "ATAK · Appui feu"];
["comspec_atak_native_fs_enabled", "CHECKBOX",
    ["Appui feu typé", "Coché : l'appel de feu (app CAS, onglet APPEL DE FEU) choisit un moyen (mortier léger, moyen ou lourd, obusier, lance-roquettes, appui naval ou aérien) avec ses portées, délais, munitions, coups et sa distance de sécurité ; les pièces de la mission sont reconnues, l'IA du camp peut tirer et les servants joueurs reçoivent la mission. Décoché : appel de feu générique, comme avant."],
    _fs, true, 1, { if (_this && {hasInterface}) then { ["scan"] call comspec_atak_native_fnc_fireSupport; }; }] call comspec_atak_native_fnc_addSetting;
{
    _x params ["_k", "_t"];
    [format ["comspec_atak_native_fs_t_%1", toLower _k], "CHECKBOX", [_t, "Décoché : ce moyen n'est plus proposé dans l'appel de feu et le serveur refuse ses demandes."], _fs, true, 1] call comspec_atak_native_fnc_addSetting;
} forEach [["MORTAR_L", "Moyen autorisé : mortier léger 60 mm"], ["MORTAR_M", "Moyen autorisé : mortier moyen 81/82 mm"], ["MORTAR_H", "Moyen autorisé : mortier lourd 120 mm"],
    ["HOWITZER", "Moyen autorisé : obusier 105/155 mm"], ["MLRS", "Moyen autorisé : lance-roquettes multiples"], ["NAVAL", "Moyen autorisé : appui naval"], ["CAS", "Moyen autorisé : appui aérien (CAS)"]];
["comspec_atak_native_fs_ai", "CHECKBOX",
    ["Tir exécuté par l'IA", "Coché : une demande à une pièce de votre camp servie par l'IA est tirée pour de vrai (droits vérifiés par le serveur : observateur, JTAC, chef, commandant, poste TOC ou Zeus) ; un appareil IA reçoit l'ordre d'attaquer la zone. Décoché : la demande reste écrite."],
    _fs, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_fs_sim", "CHECKBOX",
    ["Batteries simulées", "Coché : chaque moyen autorisé propose une batterie simulée (hors carte, sans pièce réelle) : les coups tombent sur la cible après le délai et la durée de vol du moyen. Pratique quand la mission n'a pas de pièce."],
    _fs, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_fs_delay", "LIST",
    ["Délais de réaction", "Temps entre la demande et le départ des coups (pièce IA ou simulée) : réalistes (de 30 s pour un mortier léger à 3 min pour un appui aérien), réduits au tiers, ou aucun."],
    _fs, [[1, 0.35, 0], ["Réalistes", "Réduits", "Aucun"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_fs_rings", "CHECKBOX",
    ["Cercles de portée et zone dangereuse", "Réglage personnel : sur la carte du téléphone et du poste TOC, portées min et max de la pièce choisie et distance de sécurité (danger close) autour de la cible. Les missions de tir en cours restent affichées."],
    _fs, true, 0] call comspec_atak_native_fnc_addSetting;
// UCAV commandés depuis le téléphone (fn_ucav, fn_ucavCmd, vue UCAV de l'app Drone) : réglages serveur, puis événements.
private _ucavCat = ["COMSPEC", "ATAK · UCAV"];
["comspec_atak_native_ucav", "CHECKBOX",
    ["Frappes d'UCAV depuis le téléphone", "Coché : un drone armé du camp (Greyhawk, Fenghuang, Ababil, Sentinel, drones armés des mods) se prend en main depuis l'app Drone, sans terminal ni poste du servant : orbite, Rechercher et tuer, frappes sur des cibles marquées avec ses propres armes guidées. Ex. : le JTAC met le Greyhawk en orbite à 1 000 m au-dessus du village, marque un blindé, l'UCAV tire seul son missile. Décoché : rien de tout cela, le terminal UAV reste le seul moyen."],
    _ucavCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ucav_who", "LIST",
    ["Qui peut commander un UCAV", "Vérifié par le serveur à chaque ordre. Ex. : « Chefs, JTAC, opérateurs de drone, TOC et Zeus » laisse un chef de groupe ou un joueur au rôle JTAC prendre le Greyhawk, mais pas un fusilier ; « Tout le camp » le permet à tous ; « Poste TOC et Zeus » le réserve au poste de commandement."],
    _ucavCat, [[0, 1, 2], ["Chefs, JTAC, opérateurs de drone, TOC et Zeus", "Tout le camp", "Poste TOC et Zeus"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ucav_free", "CHECKBOX",
    ["Rechercher et tuer : engagement libre autorisé", "Coché : le contrôleur peut choisir « Engager librement » ; l'UCAV frappe alors seul les ennemis identifiés de la zone, jamais une cible à moins de la distance de sécurité de l'appui aérien (1 000 m par défaut) d'un allié ou d'un civil : celle-là lui est proposée. Ex. : un blindé ennemi seul dans la vallée est frappé sans attendre ; une patrouille ennemie à 300 m d'un groupe allié attend la validation. Décoché : toute cible est proposée au téléphone."],
    _ucavCat, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ucav_terminal", "CHECKBOX",
    ["UCAV : terminal UAV requis", "Coché : il faut un terminal UAV dans l'inventaire (en plus du téléphone) pour prendre la main sur un UCAV ; le poste TOC et Zeus en sont dispensés. Ex. : le JTAC qui a laissé son terminal dans le véhicule ne peut plus commander le Greyhawk avant de l'avoir repris. Décoché : le téléphone suffit."],
    _ucavCat, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ucav_range", "SLIDER",
    ["Portée de la liaison UCAV (m)", "Distance maximale entre le téléphone et l'UCAV, divisée par deux si le relief les sépare ; les relais de la mission l'allongent comme pour les petits drones. Hors liaison, l'UCAV poursuit seul son orbite ou sa recherche, mais aucun ordre ne part. Ex. : à 15 000 m, un JTAC à 12 km de l'orbite commande encore l'UCAV ; derrière une crête, plus au-delà de 7,5 km. Le poste TOC et Zeus sont toujours en liaison."],
    _ucavCat, [2000, 40000, 15000, 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_ucavExec", { _this call comspec_atak_native_fnc_ucavCmd; }] call CBA_fnc_addEventHandler;
if (isServer) then {
    ["comspec_atak_native_ucavSrv", { ["srv", _this] call comspec_atak_native_fnc_ucav; }] call CBA_fnc_addEventHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_SrvUcavV", 1, true];
};
if (hasInterface) then { ["comspec_atak_native_ucavEvt", { ["evt", _this] call comspec_atak_native_fnc_ucav; }] call CBA_fnc_addEventHandler; };
private _team = ["COMSPEC", "ATAK · Équipes et suivi"];
// Rôle affiché (fn_displayRole) : le joueur le choisit dans l'app Profil, sauf si le serveur impose la fonction Athena.
["comspec_atak_native_role_force_community", "CHECKBOX",
    ["Imposer la fonction de la communauté comme rôle", "Décoché par défaut : chaque joueur choisit son rôle affiché dans l'app Profil (fonction de la communauté, rôle du slot ou rôle personnalisé). Coché : la fonction tenue sur Athena est affichée partout (carte, BFT, fiche, Athena) dès qu'elle existe ; sans fonction, le choix du joueur s'applique."],
    _team, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_aircrew", "CHECKBOX",
    ["ATAK pour les équipages d'hélico et d'avion", "Pilote, copilote et tireurs d'un hélicoptère ou d'un avion ont l'ATAK sur la tablette de bord, même sans téléphone ou avec un téléphone vide ou cassé. Elle se range quand on descend."],
    _team, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_phone_autohover", "CHECKBOX",
    ["Vol stationnaire auto avec l'ATAK en main (hélico)", "Réglage personnel. Pilote d'un hélicoptère presque immobile en vol : prendre l'ATAK en main engage le vol stationnaire automatique, rendu à la fermeture du téléphone (la souris sert alors au téléphone, le clavier reste aux commandes)."],
    _team, true, 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_vehicle_terminal", "LIST",
    ["Tablette dans les véhicules", "Conducteur, tireur et chef de bord d'un véhicule ou d'un bateau ont l'ATAK sur une tablette fixée à bord, sans avoir besoin de téléphone. « Avec le kit installé » : seulement dans les véhicules équipés du kit tablette (action ACE)."],
    _team, [["all", "kit", "off"], ["Tous les véhicules", "Avec le kit installé", "Aucun"], 0], 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_screen_glow", "CHECKBOX",
    ["Écrans visibles la nuit", "Décoché par défaut : la lumière de l'ATAK n'est jamais visible par les autres. Coché : la nuit, un téléphone allumé éclaire le visage de son porteur et se voit de loin (encore plus aux JVN). Le mode nuit, un véhicule ou le filtre d'écran JVN réduisent cette lueur."],
    _team, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_screen_time", "CHECKBOX",
    ["Temps d'écran et temps par rôle", "Compte le temps passé sur le téléphone (en main, porté, par app) et le temps de jeu par rôle (pilote, chef d'équipe...). À voir dans l'app Temps d'écran et sur Athena (fiche du membre)."],
    _team, true, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_youtube", "CHECKBOX",
    ["Vidéos YouTube dans le téléphone", "Décoché par défaut. Coché : l'app Vidéo YouTube lit un lien YouTube collé par le joueur, dans un navigateur intégré. YouTube (Google) n'est contacté que lorsque le joueur lance une lecture ; le lien n'est partagé avec personne."],
    _team, false, 1] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_distance", "CHECKBOX",
    ["Distances parcourues", "Le serveur compte, pour chaque joueur, les pas, les kilomètres à pied, en véhicule, en vol et en parachute, et les heures de vol par appareil. À voir dans l'app Distances et sur Athena (fiche du membre)."],
    _team, true, 1] call comspec_atak_native_fnc_addSetting;
// App Plan (carte de briefing) : qui modifie le plan partagé du camp ; le serveur seul l'écrit (fn_planServer).
["comspec_atak_native_plan_edit", "LIST",
    ["Plan de briefing : qui peut dessiner", "Qui peut dessiner sur le plan commun du camp (app Plan). Les autres le voient et peuvent préparer un brouillon, qu'un chef ajoute ensuite."],
    _team, [[0, 1, 2], ["Tout le monde", "Chefs de groupe, Zeus et admins", "Zeus et admins seulement"], 1], 1] call comspec_atak_native_fnc_addSetting;
// App Plan : historique ANNULER / RÉTABLIR (ce que le serveur a changé pour moi) et salle collaborative créée.
if (hasInterface) then {
    ["comspec_atak_native_planDiff", { ["histPush", _this] call comspec_atak_native_fnc_planAction; }] call CBA_fnc_addEventHandler;
    ["comspec_atak_native_planRoom", { ["roomJoin", _this param [0, ""]] call comspec_atak_native_fnc_planAction; }] call CBA_fnc_addEventHandler;
    // Plan du camp : un trait arrive en différence (fn_planServer) ; révision manquée -> plan entier redemandé au serveur.
    COMSPEC_ATAK_PlanAskFull = {
        params ["_var"];
        private _ask = missionNamespace getVariable ["COMSPEC_ATAK_PlanAsk", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_PlanAsk", _ask];
        if (_ask getOrDefault [_var, false]) exitWith {};
        _ask set [_var, true];
        ["comspec_atak_native_planFull", [player, _var]] call CBA_fnc_serverEvent;
        // 3 s plus tard : encore en retard sur le dernier trait annoncé -> on redemande.
        [{
            params ["_var"];
            (missionNamespace getVariable ["COMSPEC_ATAK_PlanAsk", createHashMap]) set [_var, false];
            private _want = (missionNamespace getVariable ["COMSPEC_ATAK_PlanWant", createHashMap]) getOrDefault [_var, 0];
            if (((missionNamespace getVariable [_var, [0]]) param [0, 0]) < _want) then { [_var] call COMSPEC_ATAK_PlanAskFull; };
        }, [_var], 3] call comspec_atak_native_fnc_waitAndExec;
    };
    ["comspec_atak_native_planPatch", {
        params ["_var", "_rev", "_del", "_put", "_name", "_uid", "_creator"];
        private _cur = missionNamespace getVariable [_var, [0, [], ""]];
        private _have = _cur param [0, 0];
        if (_rev < _have) exitWith {};
        if (_rev > _have) exitWith {
            private _want = missionNamespace getVariable ["COMSPEC_ATAK_PlanWant", createHashMap];
            missionNamespace setVariable ["COMSPEC_ATAK_PlanWant", _want];
            _want set [_var, (_want getOrDefault [_var, 0]) max (_rev + 1)];
            [_var] call COMSPEC_ATAK_PlanAskFull;
        };
        missionNamespace setVariable [_var, [_rev + 1, [objNull, "patchList", [_cur param [1, []], _del, _put]] call comspec_atak_native_fnc_planServer, _name, _uid, _creator]];
    }] call CBA_fnc_addEventHandler;
};
if (isServer) then {
    ["comspec_atak_native_planSrv", { _this call comspec_atak_native_fnc_planServer; }] call CBA_fnc_addEventHandler;
    // Un client en retard d'une révision du plan du camp : lui renvoyer ce plan seul (pas tout syncReq).
    ["comspec_atak_native_planFull", {
        params [["_unit", objNull], ["_var", ""]];
        if (isNull _unit || {local _unit} || {!(_var isEqualType "")} || {(_var find "COMSPEC_ATAK_Plan_") != 0}) exitWith {};
        if (!isNil { missionNamespace getVariable _var }) then { (owner _unit) publicVariableClient _var; };
    }] call CBA_fnc_addEventHandler;
    // Le serveur a le mod : posé dès le preInit (aussi en solo et en partie hébergée), sans attendre XEH_postInitServer.
    missionNamespace setVariable ["COMSPEC_ATAK_PlanSrvOn", true, true];
};

// Interface (réglage propre à chaque joueur).
["comspec_atak_native_action_menu", "LIST",
    ["Bloquer le menu d'actions (molette)", "Empêche le menu d'actions d'Arma (la liste à la molette) de s'ouvrir quand le téléphone est à l'écran, pour que la molette serve au téléphone. « En main seulement » (par défaut) : uniquement quand vous tenez le téléphone ; en miniature, les actions à la molette (terminal UAV, portes...) restent libres. Téléphone rangé : rien ne change."],
    ["COMSPEC", "ATAK · Interface"], [[0, 1, 2], ["Jamais", "En main seulement", "En main et porté"], 1], 0] call comspec_atak_native_fnc_addSetting;
["comspec_atak_native_action_menu_vehicle", "CHECKBOX",
    ["Menu d'actions autorisé en véhicule", "Dans un véhicule, la molette et le menu d'actions d'Arma restent utilisables quand le téléphone est porté (en petit à l'écran) : démarrer le moteur, changer de place, descendre... Téléphone en main, la molette reste au téléphone."],
    ["COMSPEC", "ATAK · Interface"], true, 0] call comspec_atak_native_fnc_addSetting;
// Touches d'autres mods relayées quand le téléphone est en main (fn_keyPass) : alternat radio TFAR / ACRE2 par défaut.
["comspec_atak_native_pass_mods", "EDITBOX",
    ["Touches d'autres mods avec le téléphone en main", "Téléphone en main (plein écran), les raccourcis des autres mods ne passent plus, sauf ceux des mods listés ici : noms comme dans Options > Contrôles > Configurer les addons, séparés par des virgules. Par défaut « TFAR, ACRE2 » : alternat radio et autres touches radio. Rien ne part pendant que vous tapez dans un champ. Vide : aucune touche relayée."],
    ["COMSPEC", "ATAK · Interface"], "TFAR, ACRE2", 0, { ["reset"] call comspec_atak_native_fnc_keyPass; }] call comspec_atak_native_fnc_addSetting;

// Mode drone : les ordres s'exécutent là où le drone est local (pilote, serveur ou client qui l'a posé).
["comspec_atak_native_droneCmd", { _this call comspec_atak_native_fnc_droneCmd; }] call CBA_fnc_addEventHandler;
// Retour du drone vers le pilote : ennemi repéré ou engagé en mode recherche.
["comspec_atak_native_droneEvent", {
    params ["_d", "_kind", "_pos", "_what"];
    if (!hasInterface) exitWith {};
    ["WARNING", format ["Drone : %1 %2 en %3", ["ennemi repéré,", "frappe sur"] select (_kind isEqualTo "ENGAGE"), _what, [_pos, 8] call comspec_atak_native_fnc_gridRef], 6, 60] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
}] call CBA_fnc_addEventHandler;
diag_log format ["[COMSPEC ATAK NATIVE][PERF] %1 : %2 ms", "preInit", round ((diag_tickTime - _perfT0) * 1000)];
