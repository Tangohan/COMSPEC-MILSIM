if (!hasInterface) exitWith {};
private _perfT0 = diag_tickTime;
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] Client PostInit complete";
// Version complète en tête du journal de session COMSPEC (le .log que les joueurs envoient au support).
if (!isNil "comspec_overwatch_connect_fnc_log") then {
    ["INFO", "VERSION", format ["COMSPEC ATAK %1 · COMSPEC Link attendu %2 · mission %3", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", "?"], missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", "?"], missionName]] call comspec_overwatch_connect_fnc_log;
};
// PBO ou DLL périmés, versions plus récentes sur Athena : signalés dans les notifications du téléphone.
["start"] call comspec_atak_native_fnc_integrityCheck;
// Check de démarrage posté dans le salon Discord de debug d'Athena (une seule fois, au premier lancement ; réglage CBA pour refuser).
[] call comspec_atak_native_fnc_startupCheck;
// Arsenal ACE : bouton COMSPEC (colonne de droite) qui regroupe les objets de nos mods (ATAK, Overwatch, SSE).
if (!isNil "ace_arsenal_fnc_addRightPanelButton") then {
    // Liste faite au lancement du jeu (XEH_preStart) : plus de parcours de tout CfgWeapons à l'arrivée.
    private _items = uiNamespace getVariable "COMSPEC_ATAK_ArsenalItems";
    if (isNil "_items") then {
        _items = ("getNumber (_x >> 'scope') >= 2 && {((toLower configName _x) find 'comspec_') isEqualTo 0}" configClasses (configFile >> "CfgWeapons")) apply { configName _x };
        uiNamespace setVariable ["COMSPEC_ATAK_ArsenalItems", _items];
    };
    if (_items isNotEqualTo []) then {
        // 4e paramètre d'ACE = numéro de bouton à remplacer (NOMBRE) : un texte y faisait échouer l'appel (erreur dans le RPT, pas de bouton).
        [_items, "COMSPEC", "\z\comspec_atak_native\addons\main\data\arsenal_comspec.paa"] call ace_arsenal_fnc_addRightPanelButton;
    };
};
missionNamespace setVariable ["COMSPEC_ATAK_LegacyBootstrapSuppressed", true, false];
// Avec COMSPEC Link, sa DLL porte la session Athena : on n'ouvre pas une seconde session avec la DLL native.
if ([] call comspec_atak_native_fnc_bridge) then {
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] COMSPEC Link présent : session Athena partagée, DLL native non initialisée";
} else {
    // Sans COMSPEC Link : la DLL native ne contacte Athena qu'une fois « Oui » choisi au service Athéna (Réglages > Service Athéna).
    // Dans un spawn, par extAwait : la reprise de session (jusqu'à trois requêtes HTTP) figeait l'arrivée en jeu.
    [{ ([] call comspec_atak_native_fnc_athenaMode) isEqualTo "on" }, {
        [] spawn {
            private _t0 = diag_tickTime;
            private _athenaUrl = "https://athena-comspec.eu/public";
            private _extInit = ["COMSPECATAKNativeExtension", "Init", [_athenaUrl]] call comspec_atak_native_fnc_extAwait;
            missionNamespace setVariable ["COMSPEC_ATAK_NativeExtensionInit", _extInit, false];
            private _restore = ["COMSPECATAKNativeExtension", "RestoreSession", [_athenaUrl, "1.0.0-native"]] call comspec_atak_native_fnc_extAwait;
            missionNamespace setVariable ["COMSPEC_ATAK_NativeAuthRestore", _restore, false];
            diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] COMSPECATAKNativeExtension initialization requested";
            diag_log format ["[COMSPEC ATAK NATIVE][PERF] Init + reprise de session DLL native : %1 ms", round ((diag_tickTime - _t0) * 1000)];
        };
    }] call CBA_fnc_waitUntilAndExecute;
};
// Porté : le téléphone reste affiché dans le coin et l'on continue à jouer. En main : souris et clavier.
// Raccourcis (Options > Contrôles > Configurer les addons > COMSPEC ATAK), sur le modèle des interfaces principale / secondaire / tertiaire.
// Les identifiants PhoneHold, PhoneCarry, PhoneZoomIn et PhoneZoomOut sont relus par fn_displayLoad (téléphone en main) ;
// PhoneMain, PhoneOrient et PhonePosition par le gestionnaire de touches ajouté dans fn_open.
["COMSPEC ATAK", "PhoneMain", ["Prendre en main / interagir avec l'ATAK (principal)", "Miniature ou rangé : le téléphone passe en grand (souris). En grand : il redevient la miniature."], { [] call (uiNamespace getVariable ["COMSPEC_ATAK_KeyMain", {}]); true }, "", [0x16, [false, false, true]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneCarry", ["Porter l'ATAK en miniature (secondaire)", "Affiche ou range la miniature dans un coin de l'écran (on continue à jouer). En grand : réduit en miniature."], { [] call comspec_atak_native_fnc_hudToggle; true }, "", [0x16, [false,true,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneJpc", ["Tablette sur le JPC (ouvrir / rabattre)", "Relève la tablette durcie du porte-plaques et affiche l'ATAK sur son écran 3D : on baisse les yeux pour lire, vue libre, déplacement et arme disponibles. Choisit aussi le style « porté » JPC."], { if (["wip"] call comspec_atak_native_fnc_jpcScreen) exitWith { false }; ["toggle"] call comspec_atak_native_fnc_jpcScreen; true }, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneHold", ["Prendre en main / interagir (touche alternative)", "Même effet que la touche principale : miniature -> grand, grand -> miniature."], { [] call comspec_atak_native_fnc_interactToggle; true }, "", [0x16, [true,true,false]]] call CBA_fnc_addKeybind;
// Zoom de la carte sans prendre le téléphone en main (aussi en marchant ou en conduisant).
["COMSPEC ATAK", "PhoneLase", ["Envoyer le point visé à l'ATAK", "Dans une optique ou des jumelles, ou avec un désignateur laser allumé : le point visé devient un marqueur sur la carte de l'ATAK (palette du mode MARQUEUR). Avec ACE, une mesure du Vector 21 (azimut + distance) l'envoie aussi toute seule."], { ["aim"] call comspec_atak_native_fnc_lasePoint; true }, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneZoomIn", ["Zoomer (carte du téléphone)", "Zoom avant sur la carte, aussi téléphone porté en marchant ou en conduisant."], { if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false }; [0.7] call comspec_atak_native_fnc_mapZoom; true }, "", [0xC9, [false, true, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneZoomOut", ["Dézoomer (carte du téléphone)", "Zoom arrière sur la carte, aussi téléphone porté."], { if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false }; [1 / 0.7] call comspec_atak_native_fnc_mapZoom; true }, "", [0xD1, [false, true, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneOrient", ["Permuter l'orientation (vertical / horizontal)", "Téléphone en miniature : vertical ou horizontal."], { if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false }; [] call comspec_atak_native_fnc_orientationToggle; true }, "", [0x16, [false, true, true]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhonePosition", ["Permuter la position de l'interface", "Déplace la miniature : bas droit, bas gauche, haut gauche, haut droit, milieu droit, milieu gauche."], { [] call (uiNamespace getVariable ["COMSPEC_ATAK_KeyPosition", {}]); true }, "", [0x16, [true, false, true]]] call CBA_fnc_addKeybind;
// Principal = interagir (comme le mod d'origine) : miniature ou rangé -> grand ; grand -> miniature (fn_interactToggle).
// Ranger complètement : touche « porter » depuis la miniature.
uiNamespace setVariable ["COMSPEC_ATAK_KeyMain", { [] call comspec_atak_native_fnc_interactToggle; }];
// Mort ou inconscient : le téléphone se range tout de suite (miniature et grand) et ne revient pas en miniature.
// fn_canUse renvoie aussi "down" : fn_schedulerTick le range au plus tard en une seconde (inconscience sans ACE).
uiNamespace setVariable ["COMSPEC_ATAK_StowDown", {
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
    if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_close; };
}];
// Mesure ACE Vector 21 transmise (azimut + distance) : marqueur sur la carte de l'ATAK (fn_lasePoint).
["ace_vector_rangefinderData", { params [["_d", 0], ["_az", 0], ["_inc", 0]]; ["vector", [_d, _az, _inc]] call comspec_atak_native_fnc_lasePoint; }] call CBA_fnc_addEventHandler;
["ace_unconscious", { params ["_unit", "_state"]; if (_state && {_unit isEqualTo player}) then { call (uiNamespace getVariable ["COMSPEC_ATAK_StowDown", {}]); }; }] call CBA_fnc_addEventHandler;
addMissionEventHandler ["EntityKilled", { params ["_unit"]; if (_unit isEqualTo player) then { call (uiNamespace getVariable ["COMSPEC_ATAK_StowDown", {}]); }; }];
// Cartouches ENI de la carte (fn_mapLabels) : heure de pose et auteur de chaque marqueur, notés à sa création
// (aucun envoi réseau : chaque client voit l'événement). Table locale bornée, nettoyée à la suppression.
addMissionEventHandler ["MarkerCreated", {
    params ["_m", "", ["_owner", objNull]];
    if (isNull _owner) exitWith {};
    private _meta = missionNamespace getVariable "COMSPEC_ATAK_MarkerMeta";
    if (isNil "_meta") then { _meta = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MarkerMeta", _meta]; };
    if ((count _meta) > 600) then { { if ((markerShape _x) isEqualTo "") then { _meta deleteAt _x; }; } forEach (keys _meta); };
    _meta set [_m, [(([dayTime, "HH:MM:SS"] call BIS_fnc_timeToString) splitString ":") joinString "", [_owner] call comspec_atak_native_fnc_unitCallsign]];
}];
addMissionEventHandler ["MarkerDeleted", { params ["_m"]; (missionNamespace getVariable ["COMSPEC_ATAK_MarkerMeta", createHashMap]) deleteAt _m; }];
// Marqueurs « réseau ATAK uniquement » (fn_markerNet) : reçus par le réseau du téléphone, effacés partout quand on les
// efface ici (téléphone, durée écoulée ou carte d'Arma) ; à l'arrivée, ceux de mes canaux sont redemandés au serveur,
// avec l'heure et l'auteur des marqueurs posés avant moi.
["comspec_atak_native_tacMk", { ["recv", _this] call comspec_atak_native_fnc_markerNet; }] call CBA_fnc_addEventHandler;
addMissionEventHandler ["MarkerDeleted", { params ["_m"]; ["deleted", _m] call comspec_atak_native_fnc_markerNet; }];
[{ ["comspec_atak_native_tacMkSrv", ["sync", player]] call CBA_fnc_serverEvent; }, [], 8] call CBA_fnc_waitAndExecute;
// Compteur de changements des marqueurs (créé, supprimé, modifié) : la carte du téléphone ne relit les marqueurs de la
// mission que lorsqu'il bouge (fn_mapGameMarkers, fn_localDataRefresh), au lieu de tout relire chaque seconde.
// Noms des marqueurs changés, un carnet par lecteur (fn_mapGameMarkers, fn_localDataRefresh, fn_strokeDraw) : chacun ne
// relit que ces marqueurs. Plus de 300 changements non lus (téléphone rangé) : clé "" = tout relire.
{ addMissionEventHandler [_x, {
    params ["_m"];
    COMSPEC_ATAK_MkRev = (missionNamespace getVariable ["COMSPEC_ATAK_MkRev", 0]) + 1;
    {
        private _h = missionNamespace getVariable [_x, createHashMap];
        if ((count _h) < 300) then { _h set [_m, true]; } else { _h set ["", true]; };
        missionNamespace setVariable [_x, _h];
    } forEach ["COMSPEC_ATAK_MkDirtyGm", "COMSPEC_ATAK_MkDirtyLd", "COMSPEC_ATAK_MkDirtySk"];
}]; } forEach ["MarkerCreated", "MarkerDeleted", "MarkerUpdated"];
// Position de la miniature : coin suivant (réglage profil COMSPEC_ATAK_MiniAnchor, aussi dans Réglages).
uiNamespace setVariable ["COMSPEC_ATAK_KeyPosition", {
    private _list = ["BR", "BC", "BL", "TL", "TC", "TR", "MR", "ML"];
    private _cur = _list find toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
    private _next = _list select ((_cur max 0) + 1) mod (count _list);
    profileNamespace setVariable ["COMSPEC_ATAK_MiniAnchor", _next];
    [] call comspec_atak_native_fnc_saveProfile;
    if (!isNull ([] call comspec_atak_native_fnc_display)) then {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
    };
}];
// Menu d'actions d'Arma masqué pendant que le téléphone est affiché (réglage « Masquer le menu d'actions ») :
// molette (PrevAction / NextAction) bloquée ; validation (Action) bloquée seulement téléphone en main.
// Téléphone rangé, ou porté en véhicule : le gestionnaire renvoie false, le jeu (et ACE) se comporte normalement. Réinstallé à chaque ouverture (fn_open).
uiNamespace setVariable ["COMSPEC_ATAK_ActionBlock", {
    params [["_activate", false]];
    private _mode = missionNamespace getVariable ["comspec_atak_native_action_menu", 1];
    if (_mode isEqualTo 0 || {isNull ([] call comspec_atak_native_fnc_display)}) exitWith { false };
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _inHand = _st getOrDefault ["interactive", false];
    if (_activate) exitWith { _inHand };
    // En véhicule (conducteur, passager, tireur) : le menu d'actions reste libre (démarrer le moteur, monter, descendre…)
    // sauf téléphone en main (réglage « Menu d'actions libre en véhicule », activé par défaut).
    if (!isNull objectParent player && {missionNamespace getVariable ["comspec_atak_native_action_menu_vehicle", true]}) exitWith { _inHand };
    // Appareil photo : la molette zoome, le menu reste fermé.
    if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) exitWith { true };
    // Écran sur le JPC : le joueur garde son arme et son menu d'actions.
    _inHand || {_mode isEqualTo 2 && {!(_st getOrDefault ["texture", false])}}
}];
["COMSPEC ATAK", "PhonePanic", "Bouton PANIQUE (deux appuis)", { if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false }; ["panic"] call comspec_atak_native_fnc_alertsAction; if (diag_tickTime < ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["panicArmedUntil", -1])) then { ["WARNING", "PANIQUE : appuyez encore pour envoyer", 5, 60] call comspec_atak_native_fnc_notify; }; true }, "", [0, [false,false,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "GestureFinger", ["Doigt d'honneur", "Geste du bras gauche (marche aussi fusil en main). Il faut cocher le réglage d'addon « Gestes doigt d'honneur »."], { [player] call comspec_atak_native_fnc_gestureFinger }, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "GestureFingerAim", ["Doigt d'honneur libre (maintenir)", "Tant que la touche est maintenue : bras gauche tendu, majeur levé, qui suit le joueur visé ou le plus proche du centre de l'écran, sinon le curseur."], { ["start"] call comspec_atak_native_fnc_gestureFingerAim; true }, { ["stop"] call comspec_atak_native_fnc_gestureFingerAim; true }, [0, [false, false, false]], false] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneNight", "Mode nuit du téléphone (normal / rouge / sombre)", {
    private _m = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
    private _n = ["OFF", "RED", "DIM"] select ((((["OFF", "RED", "DIM"] find _m) max 0) + 1) mod 3);
    profileNamespace setVariable ["COMSPEC_ATAK_NightMode", _n];
    // Téléphone affiché : toast Android ; rangé : notification classique.
    private _msg = format ["Mode nuit : %1", ["normal", "filtre rouge", "écran sombre"] select (["OFF", "RED", "DIM"] find _n)];
    if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
    [] call comspec_atak_native_fnc_deviceOverlay;
    true
}, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneSilent", "Mode discrétion (sons coupés)", {
    private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]);
    profileNamespace setVariable ["COMSPEC_ATAK_Silent", _on];
    private _msg = ["Mode discrétion désactivé", "Mode discrétion : aucun son"] select _on;
    if !(([_msg, 2] call comspec_atak_native_fnc_toast)) then { ["INFO", _msg, 2, 10] call comspec_atak_native_fnc_notify; };
    true
}, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
// Marquage au viseur : caméra du drone (vue drone ou app Drone à l'écran), sinon appareil photo du téléphone.
["COMSPEC ATAK", "LockMark", ["Marquer la cible (réticule)", "Accroche la cible sous le réticule : caméra du drone si sa vue est à l'écran (carte ou app Drone), sinon l'appareil photo du téléphone (touche T en mode photo)."], {
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false };
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _page = _s getOrDefault ["activePage", ""];
    private _droneOnScreen = !isNull ([] call comspec_atak_native_fnc_display) && {!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])}
        && {_page isEqualTo "DRONE" || {_page isEqualTo "MAP" && {(_s getOrDefault ["droneView", "OFF"]) isNotEqualTo "OFF"}}};
    if (_droneOnScreen) then { ["lock"] call comspec_atak_native_fnc_droneAction; } else {
        if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) then { ["mark", "PHONE"] call comspec_atak_native_fnc_lockAction; } else {
            ["INFO", "Marquer : ouvrez l'appareil photo ou la vue drone pour viser", 3, 20] call comspec_atak_native_fnc_notify;
        };
    };
    true
}, "", [0x14, [false, true, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "DroneView", ["Vue drone sur la carte", "Carte, vue drone à la place de la carte, ou vue incrustée (aussi en miniature)."], {
    if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) exitWith { false };
    ["view"] call comspec_atak_native_fnc_droneAction;
    true
}, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
// Ordres rapides au drone appairé (sans ouvrir le téléphone) : touches libres par défaut, à choisir dans les options CBA.
{
    _x params ["_id", "_lbl", "_tip", "_mode"];
    ["COMSPEC ATAK", _id, [_lbl, _tip], compile format ["
        if (isNull (missionNamespace getVariable ['COMSPEC_ATAK_Drone', objNull])) exitWith { ['WARNING', 'Aucun drone appairé', 3, 20] call comspec_atak_native_fnc_notify; true };
        ['quick', %1] call comspec_atak_native_fnc_droneAction;
        true
    ", str _mode], "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
} forEach [
    ["DroneHunt", "Drone : RECHERCHE", "Le drone appairé passe en recherche autour de lui (rayon réglé dans l'app Drone).", "hunt"],
    ["DroneFollowMe", "Drone : ME SUIVRE", "Le drone appairé vous suit.", "me"],
    ["DroneRtb", "Drone : RTB", "Le drone appairé revient à son point de décollage et s'y pose.", "rth"],
    ["DroneLand", "Drone : ATTERRIR", "Le drone appairé se pose sur place.", "land"]
];
// Suivi des cibles marquées : en vue, perdues, neutralisées (deux fois par seconde).
[{ private _t = diag_tickTime; ["tick"] call comspec_atak_native_fnc_lockAction; ["marquage suivi", _t] call comspec_atak_native_fnc_perfAcc; }, 0.5] call comspec_atak_native_fnc_pfhSpread;
// Contrôle Zeus des ATAK : effets reçus du serveur (fn_zeusClient), boucle d'une seconde, menu clic droit ZEN du Zeus.
["comspec_atak_native_zeusFx", { _this call comspec_atak_native_fnc_zeusClient; }] call CBA_fnc_addEventHandler;
// App Store : apps imposées ou interdites changées par la mission ou le Zeus (fn_appInstall), ou par les réglages CBA du
// serveur : l'app ouverte se ferme si elle devient interdite, l'écran d'accueil et le dock se redessinent.
["comspec_atak_native_appPolicy", { ["applied"] call comspec_atak_native_fnc_appInstall; }] call CBA_fnc_addEventHandler;
["CBA_SettingChanged", { params [["_k", ""]]; if (_k in ["comspec_atak_native_apps_required", "comspec_atak_native_apps_forbidden"]) then { ["applied"] call comspec_atak_native_fnc_appInstall; }; }] call CBA_fnc_addEventHandler;
[{ ["tick"] call comspec_atak_native_fnc_zeusClient; }, 1] call comspec_atak_native_fnc_pfhSpread;
// Positions BFT par points (fn_pli) : un point de position envoyé aux alliés à l'intervalle choisi (10 s en Milsim).
[{ private _t = diag_tickTime; ["tick"] call comspec_atak_native_fnc_pli; ["positions BFT", _t] call comspec_atak_native_fnc_perfAcc; }, 1] call comspec_atak_native_fnc_pfhSpread;
// Radio maillée MPU5 et voix ACRE2 (fn_mpu5) : détection de l'objet, réseau voix publié, qui émet.
["init"] call comspec_atak_native_fnc_mpu5;
["register"] call comspec_atak_native_fnc_zeusMenu;
// Attributs ZEN des relais (fenêtre Éditer d'un pylône ou relais) et menu clic droit « ATAK · Relais ».
["register"] call comspec_atak_native_fnc_relayZeus;
// Module Zeus (ZEN) « Capture de la carte » : tuiles satellite, 3D ou plan de la carte (fn_mapCapture, tools/map_stitch.py).
if (!isNil "zen_custom_modules_fnc_register") then {
    ["COMSPEC ATAK", "Capture de la carte", { params ["_pos"]; ["zeus", _pos] call comspec_atak_native_fnc_mapCapture; }, "\a3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa"] call comspec_atak_native_fnc_zenModule;
};
// Batterie : consommation aussi téléphone rangé (la barre d'état la met à jour chaque seconde quand il est ouvert).
[{ if (isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_battery; }; }, 10] call comspec_atak_native_fnc_pfhSpread;
["comspec_atak_native_p2p", { _this call comspec_atak_native_fnc_p2pReceive }] call CBA_fnc_addEventHandler;
["comspec_atak_native_p2pAck", { _this call comspec_atak_native_fnc_p2pAck }] call CBA_fnc_addEventHandler;
// MEDEVAC du camp : demandes et suivi (app Médical, onglet MEDEVAC).
// Caméra partagée avec moi (fn_camShare : NFC, Bluetooth ou relais).
["comspec_atak_native_camShare", { ["recv", _this] call comspec_atak_native_fnc_camShare; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevac", { if (["hold", ["comspec_atak_native_medevac", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["recv", _this] call comspec_atak_native_fnc_medicalAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_bda", { if (["hold", ["comspec_atak_native_bda", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["recv", _this] call comspec_atak_native_fnc_bdaAction; }] call CBA_fnc_addEventHandler;
// Comptes rendus du camp (app Comptes rendus) : tous les types, rejoués par la synchro serveur.
["comspec_atak_native_report", { if (["hold", ["comspec_atak_native_report", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["recv", _this] call comspec_atak_native_fnc_reportAction; }] call CBA_fnc_addEventHandler;
// Accusés de lecture et de réception des comptes rendus (l'auteur les garde).
["comspec_atak_native_reportRcpt", { if (["hold", ["comspec_atak_native_reportRcpt", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["rcpt", _this] call comspec_atak_native_fnc_reportAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevacStatus", { if (["hold", ["comspec_atak_native_medevacStatus", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["statusRecv", _this] call comspec_atak_native_fnc_medicalAction; }] call CBA_fnc_addEventHandler;
// Ordres : réponses des joueurs du camp (app Tâches, qui a vu / reçu / exécute).
["comspec_atak_native_orderAck", { if (["hold", ["comspec_atak_native_orderAck", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["recv", _this] call comspec_atak_native_fnc_orderAck; }] call CBA_fnc_addEventHandler;
// App JTAC : brief, appels du JTAC et réponses de l'équipage (CAS), envoyés aux seuls intéressés.
["comspec_atak_native_cas", { if (["hold", ["comspec_atak_native_cas", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["recv", _this] call comspec_atak_native_fnc_jtacAction; }] call CBA_fnc_addEventHandler;
// App JTAC ouverte en contrôle (ou vue équipage) : position et géométrie de l'appareil rafraîchies.
[{ ["tick"] call comspec_atak_native_fnc_jtacAction; }, 3] call comspec_atak_native_fnc_pfhSpread;
private _eh = addMissionEventHandler ["ExtensionCallback", { _this call comspec_atak_native_fnc_extensionCallback }];
missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", _eh, false];
// Service Athéna changé (Oui / Non) : apps Athena affichées ou masquées tout de suite.
["COMSPEC_AthenaModeChanged", { [{ if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_dockRender; }; }] call CBA_fnc_execNextFrame; }] call CBA_fnc_addEventHandler;
["COMSPEC_AthenaLinkChanged", { params ["_state"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["networkState", toUpper _state]; }] call CBA_fnc_addEventHandler;

// COMSPEC Link (déjà installé chez les joueurs) n'ouvre ses boucles de synchro que s'il voit « son » terminal.
// Tant que sa version ne délègue pas au natif, on lui indique ici si le téléphone natif est autorisé.
[{
    if !([] call comspec_atak_native_fnc_bridge) exitWith {};
    private _allowed = [player] call comspec_atak_native_fnc_hasDevice;
    if ((missionNamespace getVariable ["comspec_overwatch_require_item", true]) isEqualTo _allowed) then {
        missionNamespace setVariable ["comspec_overwatch_require_item", !_allowed];
    };
}, 2] call comspec_atak_native_fnc_pfhSpread;

// Surcharge du réseau : trafic émis par ce téléphone, publié toutes les 10 s s'il change (fn_netLoad).
[{ ["publish"] call comspec_atak_native_fnc_netLoad; }, 10] call comspec_atak_native_fnc_pfhSpread;

// Réglages de réalisme de la communauté (Athena) : appliqués à tous dès qu'ils changent.
[{
    private _raw = missionNamespace getVariable ["COMSPEC_TenantExperienceRaw", ""];
    if (_raw isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_TenantApplied", "-"])) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_TenantApplied", _raw];
    [] call comspec_atak_native_fnc_tenantApply;
    ["INFO", "TENANT", "Réglages communauté appliqués"] call comspec_atak_native_fnc_log;
}, 5] call comspec_atak_native_fnc_pfhSpread;

// Retour d'Athena sur les photos envoyées (via COMSPEC Link) : reçue ou refusée.
[{
    private _up = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]);
    private _ko = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]);
    (missionNamespace getVariable ["COMSPEC_ATAK_PhotoSeen", [_up, _ko]]) params ["_up0", "_ko0"];
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoSeen", [_up, _ko]];
    if ((_up != _up0 || {_ko != _ko0}) && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "GALLERY"}) then { ["GALLERY"] call comspec_atak_native_fnc_pageRender; };
    if (_up > _up0) then { ["SUCCESS", format ["%1 photo(s) reçue(s) par Athena", _up - _up0], 4, 30] call comspec_atak_native_fnc_notify; };
    if (_ko > _ko0) then {
        private _last = missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []];
        // Refus de la formule (403 feature_unavailable) : une alerte claire DANS le téléphone (fn_fullAlert, attend la
        // prochaine ouverture), une fois par session, au lieu d'une simple notification. Jamais de fenêtre sur l'écran de jeu.
        if (((_last param [2, ""]) find "feature_unavailable") >= 0) then {
            if !(missionNamespace getVariable ["COMSPEC_ATAK_PlanModalShown", false]) then {
                missionNamespace setVariable ["COMSPEC_ATAK_PlanModalShown", true];
                private _msg = format ["%1 %2 %3",
                    "Ta photo n'a pas été envoyée à Athena." call comspec_atak_native_fnc_tr,
                    "La formule de ta communauté n'inclut pas la carte et les liaisons Athena, donc Athena refuse les envois du jeu." call comspec_atak_native_fnc_tr,
                    "Préviens l'administrateur de ta communauté sur Athena. Ce n'est pas un bug, inutile de le signaler." call comspec_atak_native_fnc_tr];
                ["Carte Athena non incluse" call comspec_atak_native_fnc_tr, _msg, "Athena", [0.55, 0.36, 0.06, 0.94], 30] call comspec_atak_native_fnc_fullAlert;
                ["WARNING", "Ta photo n'a pas été envoyée à Athena.", 6, 40] call comspec_atak_native_fnc_notify;
            };
        } else {
            ["WARNING", format ["Photo refusée par Athena : %1", _last param [1, "voir l'app Galerie"]], 6, 40] call comspec_atak_native_fnc_notify;
        };
    };
}, 2] call comspec_atak_native_fnc_pfhSpread;

// Rattachement ORBAT (Athena) et icône choisie : partagés pour le filtre et l'affichage des autres téléphones.
[{
    private _orbat = missionNamespace getVariable ["comspec_profile_unit", ""];
    if !(_orbat isEqualType "") then { _orbat = str _orbat; };
    if ((player getVariable ["COMSPEC_ATAK_Orbat", ""]) isNotEqualTo _orbat) then { player setVariable ["COMSPEC_ATAK_Orbat", _orbat, true]; };
    // Abrégé saisi dans Athena (COMSPEC Link 2.0.62+) : les autres téléphones l'affichent à la place du sigle automatique.
    private _orbatShort = missionNamespace getVariable ["comspec_profile_unit_short", ""];
    if !(_orbatShort isEqualType "") then { _orbatShort = ""; };
    if ((player getVariable ["COMSPEC_ATAK_OrbatShort", ""]) isNotEqualTo _orbatShort) then { player setVariable ["COMSPEC_ATAK_OrbatShort", _orbatShort, true]; };
    // Téléphone gardé dans Athena : partagé pour que la GE des autres voie le même numéro, IMEI et MAC.
    private _ph = missionNamespace getVariable ["comspec_profile_phone", []];
    if ((player getVariable ["COMSPEC_ATAK_IdentDb", []]) isNotEqualTo _ph) then { player setVariable ["COMSPEC_ATAK_IdentDb", _ph, true]; };
    private _icon = profileNamespace getVariable ["COMSPEC_ATAK_SelfIcon", ""];
    if ((player getVariable ["COMSPEC_ATAK_Icon", ""]) isNotEqualTo _icon) then { player setVariable ["COMSPEC_ATAK_Icon", _icon, true]; };
    // Balise BFT : en ligne si j'ai un téléphone allumé avec du signal (diffusée seulement quand elle change).
    // Équipage d'aéronef : tablette de bord, même téléphone éteint ou cassé.
    private _on = (([player] call comspec_atak_native_fnc_aircrewTerminal) || {([player] call comspec_atak_native_fnc_hasDevice)
        && {!((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"])}})
        && {(([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0};
    if ((player getVariable ["COMSPEC_ATAK_Beacon", true]) isNotEqualTo _on) then { player setVariable ["COMSPEC_ATAK_Beacon", _on, true]; };
    // Fiche vue par les alliés au clic sur la carte : batterie (par 10 %), barres de signal, état de l'appareil.
    private _pub = [
        (round ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) / 10)) * 10,
        ([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1],
        ([] call comspec_atak_native_fnc_deviceHealth) get "state"
    ];
    // Une barre de plus ou de moins seulement (évanouissement près d'un seuil) : diffusée si elle tient deux tours de suite
    // (comme fn_feedLink) ; batterie, état ou perte du signal : tout de suite.
    private _pubOld = player getVariable ["COMSPEC_ATAK_Pub", []];
    if (_pubOld isEqualTo _pub) then { uiNamespace setVariable ["COMSPEC_ATAK_PubPend", []]; } else {
        private _soft = (count _pubOld) isEqualTo 3 && {(_pubOld select 0) isEqualTo (_pub select 0)} && {(_pubOld select 2) isEqualTo (_pub select 2)}
            && {(_pubOld select 1) > 0} && {(_pub select 1) > 0} && {(abs ((_pubOld select 1) - (_pub select 1))) isEqualTo 1};
        if (_soft && {_pub isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_PubPend", []])}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_PubPend", _pub];
        } else {
            uiNamespace setVariable ["COMSPEC_ATAK_PubPend", []];
            player setVariable ["COMSPEC_ATAK_Pub", _pub, true];
        };
    };
    // Batterie vide : carte SIM en mode secours, encore localisable par les relais (fn_simSos).
    ["publish"] call comspec_atak_native_fnc_simSos;
    // Étiquette du téléphone (numéro, IMEI, terminal Athena) : lue par le serveur si l'IA ennemie le ramasse (fn_captureServer).
    ["publish"] call comspec_atak_native_fnc_compromise;
}, 5] call comspec_atak_native_fnc_pfhSpread;

// Session Athena perdue en cours de partie : un nouveau code est proposé tout de suite (fn_athenaLink « watch »).
[{ ["watch"] call comspec_atak_native_fnc_athenaLink; }, 10] call comspec_atak_native_fnc_pfhSpread;

// Tous les marqueurs de la carte vers le web (relais unique par camp).
[{ [] call comspec_atak_native_fnc_markerWebSweep; }, 15] call comspec_atak_native_fnc_pfhSpread;

// Heatmap : altitudes du terrain en couleur, calculées au dessin (fn_mapOnDraw) ; aucune donnée ennemie.

// Alertes BFT de mon groupe : un équipier passe hors ligne, tombe inconscient ou meurt (et revient en ligne).
[{
    if !(profileNamespace getVariable ["COMSPEC_ATAK_BftAlerts", true]) exitWith {};
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    if ((([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) <= 0) exitWith {};
    private _prev = missionNamespace getVariable ["COMSPEC_ATAK_BftPrev", createHashMap];
    private _now = createHashMap;
    {
        if (_x isEqualTo player || {!isPlayer _x}) then { continue; };
        private _st = switch (true) do {
            case (!alive _x): { "DEAD" };
            // État médical masqué pour les alliés ou les alertes (Réglages > Réalisme, fn_medShow) : pas d'alerte « inconscient ».
            case (lifeState _x isEqualTo "INCAPACITATED" && {["state", "allies"] call comspec_atak_native_fnc_medShow} && {["ctx", "alerts"] call comspec_atak_native_fnc_medShow}): { "DOWN" };
            case !(_x getVariable ["COMSPEC_ATAK_Beacon", true]): { "OFF" };
            default { "OK" };
        };
        private _id = netId _x;
        _now set [_id, _st];
        private _was = _prev getOrDefault [_id, ""];
        if (_was isEqualTo "" || {_was isEqualTo _st}) then { continue; };
        private _cs = [_x] call comspec_atak_native_fnc_unitCallsign;
        private _grid = [getPosASL _x, 6] call comspec_atak_native_fnc_gridRef;
        if (_st isEqualTo "OFF") then {
            private _lp = ((uiNamespace getVariable ["COMSPEC_ATAK_BftLast", createHashMap]) getOrDefault [_id, [getPosASL _x]]) select 0;
            _grid = [_lp, 6] call comspec_atak_native_fnc_gridRef;
        };
        switch (_st) do {
            case "DEAD": { ["WARNING", format ["BFT · %1 ne répond plus · %2", _cs, _grid], 10, 80] call comspec_atak_native_fnc_notify; [] call comspec_atak_native_fnc_vibrate; };
            case "DOWN": { ["WARNING", format ["BFT · %1 inconscient · %2", _cs, _grid], 10, 80] call comspec_atak_native_fnc_notify; [] call comspec_atak_native_fnc_vibrate; };
            case "OFF": { ["WARNING", format ["BFT · %1 hors ligne · dernière position %2", _cs, _grid], 8, 60] call comspec_atak_native_fnc_notify; [] call comspec_atak_native_fnc_vibrate; };
            default { if (_was isEqualTo "OFF") then { ["INFO", format ["BFT · %1 de nouveau en ligne", _cs], 5, 30] call comspec_atak_native_fnc_notify; }; };
        };
    } forEach units group player;
    missionNamespace setVariable ["COMSPEC_ATAK_BftPrev", _now];
}, 2] call comspec_atak_native_fnc_pfhSpread;

// Vibreur BFT : un allié fait vibrer mon téléphone (signal discret de ralliement).
// GÉOLOC subie (réglage « Prévenir la cible ») : alerte discrète, une fois par minute au plus.
["comspec_atak_native_geoWarn", {
    if (diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_GeoWarnNext", 0])) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_GeoWarnNext", diag_tickTime + 60];
    ["WARNING", "Activité réseau anormale sur votre téléphone", 5, 40] call comspec_atak_native_fnc_notify;
}] call CBA_fnc_addEventHandler;
["comspec_atak_native_buzz", {
    params ["_from", "_grid", ["_txt", ""], ["_pos", []], ["_src", objNull]];
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"]) exitWith {};
    if !(_txt isEqualType "") then { _txt = ""; };
    ["WARNING", [format ["VIBREUR · %1 vous appelle · %2", _from, _grid], format ["VIBREUR · %1 : %2 · %3", _from, _txt, _grid]] select (_txt isNotEqualTo ""), 8, 70] call comspec_atak_native_fnc_notify;
    // Alerte visuelle à l'écran du téléphone (VU / CARTE / FERMER), affichée aussi à la prochaine ouverture.
    ["show", [_from, _grid, _txt, _pos, _src]] call comspec_atak_native_fnc_buzzAlert;
    private _buzz = {
        if (isNull ([] call comspec_atak_native_fnc_display) && {!(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false])} && {!isNil "comspec_overwatch_connect_fnc_playAtakVibrate"}) then { [0.9] call comspec_overwatch_connect_fnc_playAtakVibrate; } else { [] call comspec_atak_native_fnc_vibrate; };
    };
    [] call _buzz;
    [_buzz, [], 0.9] call CBA_fnc_waitAndExecute;
    [_buzz, [], 1.8] call CBA_fnc_waitAndExecute;
}] call CBA_fnc_addEventHandler;
// Accusé du vibreur : le destinataire a appuyé sur VU.
["comspec_atak_native_buzzAck", {
    params [["_who", "?"]];
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    ["INFO", format ["VIBREUR · %1 a vu votre message", _who], 5] call comspec_atak_native_fnc_notify;
}] call CBA_fnc_addEventHandler;

// PANIQUE d'un allié : alerte rouge, vibration, repère local sur la carte pendant 10 min.
["comspec_atak_native_panic", {
    if (["hold", ["comspec_atak_native_panic", _this]] call comspec_atak_native_fnc_offline) exitWith {};
    params ["_who", "_pos", "_grid"];
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    ["WARNING", format ["PANIQUE · %1 · %2", _who, _grid], 12, 90] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    private _m = createMarkerLocal [format ["COMSPEC_PANIC_%1_%2", _who, round diag_tickTime], _pos];
    _m setMarkerTypeLocal "mil_warning";
    _m setMarkerColorLocal "ColorRed";
    _m setMarkerTextLocal format [("PANIQUE %1" call comspec_atak_native_fnc_tr), _who];
    [{ deleteMarkerLocal _this; }, _m, 600] call CBA_fnc_waitAndExecute;
    ["PANIC", _who, _pos, _grid, "En détresse"] call comspec_atak_native_fnc_alertsLog;
}] call CBA_fnc_addEventHandler;

// Alertes rapides et SALUTE d'un allié (aussi sans Athena) : notification, vibreur, journal de l'app Alertes, repère 10 min.
["comspec_atak_native_alert", {
    if (["hold", ["comspec_atak_native_alert", _this]] call comspec_atak_native_fnc_offline) exitWith {};
    params ["_type", "_who", "_pos", "_grid", ["_text", ""], ["_side", ""]];
    if (_side isNotEqualTo str side group player || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
    // Bilan de blessé (liaison ATAK) : son état n'est pas gardé si je masque l'état médical dans les alertes (fn_medShow).
    if (_type isEqualTo "WIA" && {!(["state", "alerts"] call comspec_atak_native_fnc_medShow)}) then { _text = ""; };
    private _label = createHashMapFromArray [["TIC", "CONTACT"], ["TIC_CLEAR", "FIN DE CONTACT"], ["EAGLE_DOWN", "APPAREIL ABATTU"], ["SALUTE", "SALUTE"]] getOrDefault [_type, _type];
    ["TACTICAL", format ["%1 · %2 · %3", _label, _who, _grid], 8, 70] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    private _m = createMarkerLocal [format ["COMSPEC_ALERT_%1_%2", _type, round (diag_tickTime * 10)], _pos];
    _m setMarkerTypeLocal (createHashMapFromArray [["TIC", "mil_warning"], ["TIC_CLEAR", "mil_flag"], ["EAGLE_DOWN", "mil_destroy"]] getOrDefault [_type, "mil_unknown"]);
    _m setMarkerColorLocal (createHashMapFromArray [["TIC", "ColorOrange"], ["TIC_CLEAR", "ColorGreen"], ["EAGLE_DOWN", "ColorRed"]] getOrDefault [_type, "ColorYellow"]);
    _m setMarkerTextLocal format ["%1 %2", _label call comspec_atak_native_fnc_tr, _who];
    [{ deleteMarkerLocal _this; }, _m, 600] call CBA_fnc_waitAndExecute;
    [_type, _who, _pos, _grid, _text] call comspec_atak_native_fnc_alertsLog;
}] call CBA_fnc_addEventHandler;

// Mode local et poste TOC : messages d'un PC de TOC (fn_tocAction) et ordres émis sans Athena (fn_orderLocal).
["comspec_atak_native_tocMsg", {
    if (["hold", ["comspec_atak_native_tocMsg", _this]] call comspec_atak_native_fnc_offline) exitWith {};
    ["recv", _this] call comspec_atak_native_fnc_tocAction;
}] call CBA_fnc_addEventHandler;
["comspec_atak_native_localOrder", {
    if (["hold", ["comspec_atak_native_localOrder", _this]] call comspec_atak_native_fnc_offline) exitWith {};
    ["recv", _this] call comspec_atak_native_fnc_orderLocal;
}] call CBA_fnc_addEventHandler;

// Goniométrie : émetteurs estimés par Athena, relus toutes les 15 s quand la couche est active.
// Seulement avec un téléphone et une carte à l'écran (téléphone ou carte Arma) : inutile sinon.
[{
    if !(profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true]) exitWith {};
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    if (isNull ([] call comspec_atak_native_fnc_display) && {!visibleMap}) exitWith {};
    [] call comspec_atak_native_fnc_sigintPoll;
}, 15] call comspec_atak_native_fnc_pfhSpread;

// Menu ACE « Téléphone ATAK » (fn_aceMenu) : les actions d'entretien ci-dessous vont dans son sous-menu Entretien.
[] call comspec_atak_native_fnc_aceMenu;
// Socle de recharge CS-10 (fn_dockMenu) : poser, reprendre, état, replier, poser le kit.
[] call comspec_atak_native_fnc_dockMenu;
// PC portable de TOC (fn_tocPost) : action ACE / molette, poste le plus proche cherché chaque seconde.
["menu"] call comspec_atak_native_fnc_tocPost;
[{ ["tick"] call comspec_atak_native_fnc_tocPost; }, 1] call comspec_atak_native_fnc_pfhSpread;
// Configuration locale (config.json, fn_localCfg) : mes numéros publiés aux autres joueurs, aussi après une réapparition
// ou un changement du service Athéna.
[{ !isNull player }, { ["apply"] call comspec_atak_native_fnc_localCfg; }] call CBA_fnc_waitUntilAndExecute;
player addEventHandler ["Respawn", { [{ ["apply"] call comspec_atak_native_fnc_localCfg; }, [], 1] call CBA_fnc_waitAndExecute; }];
["COMSPEC_AthenaModeChanged", { [{ ["apply"] call comspec_atak_native_fnc_localCfg; }] call CBA_fnc_execNextFrame; }] call CBA_fnc_addEventHandler;
// Téléphone visible sur le gilet (fn_vestMount), téléphone extractible en cas de capture (fn_phoneSecurity).
[{ ["tick"] call comspec_atak_native_fnc_vestMount; ["tick"] call comspec_atak_native_fnc_jpcScreen; ["tick"] call comspec_atak_native_fnc_phoneSecurity; }, 2] call comspec_atak_native_fnc_pfhSpread;
// Terminal de bord (tablette du véhicule, PC mobile) gagné ou perdu : coque redessinée, message à l'arrivée.
[{
    private _k = [player] call comspec_atak_native_fnc_vehicleTerminal;
    if (_k isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_TermKind", ""])) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_TermKind", _k];
    if (_k in ["vehicle", "cp"] && {!([player, true] call comspec_atak_native_fnc_hasDevice)}) then { ["INFO", ["Tablette du véhicule disponible (ATAK)", "Terminal du poste de commandement disponible"] select (_k isEqualTo "cp"), 4, 20] call comspec_atak_native_fnc_notify; };
    if (_k isEqualTo "toc") then { ["INFO", format ["Poste TOC %1 : app Poste TOC disponible", ["callsign"] call comspec_atak_native_fnc_tocPost], 4, 20, "TOC"] call comspec_atak_native_fnc_notify; };
    if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_layoutApply; };
}, 2] call comspec_atak_native_fnc_pfhSpread;
// Lueur des écrans la nuit (la sienne publiée, celle des joueurs proches dessinée) et filtre JVN (fn_screenGlow).
[{ ["tick"] call comspec_atak_native_fnc_screenGlow; }, 1] call comspec_atak_native_fnc_pfhSpread;
// Action ACE : changer la batterie du téléphone (si une batterie de rechange est portée).
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _act = ["COMSPEC_ATAK_BatterySwap", "Changer la batterie", "\z\comspec_atak_native\addons\main\data\item_battery.paa", {
        if (isNil "ace_common_fnc_progressBar") exitWith { [] call comspec_atak_native_fnc_batterySwap; };
        [5, [], { [] call comspec_atak_native_fnc_batterySwap; }, {}, ("Changement de la batterie…" call comspec_atak_native_fnc_tr)] call ace_common_fnc_progressBar;
    }, {
        [player] call comspec_atak_native_fnc_hasDevice && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) < 95}
        && {([player] call comspec_atak_native_fnc_batteryItem) isNotEqualTo ""}
    }] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Care"], _act, true] call ace_interact_menu_fnc_addActionToClass;
} else {
    // Sans ACE : action de la molette (visible seulement avec une batterie de rechange et le téléphone sous 95 %).
    // Les actions ne suivent pas le joueur à la réapparition : remise sur la nouvelle unité.
    private _add = {
        params ["_u"];
        _u addAction [format ["<t color='#7fd0b0'>%1</t>", "Changer la batterie ATAK" call comspec_atak_native_fnc_tr], { [] call comspec_atak_native_fnc_batterySwap; }, nil, 1, false, true, "",
            "_this isEqualTo _target && {[_this] call comspec_atak_native_fnc_hasDevice} && {(missionNamespace getVariable ['COMSPEC_ATAK_Battery', 100]) < 95} && {([_this] call comspec_atak_native_fnc_batteryItem) isNotEqualTo ''}"];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_BatterySwapAction", _add];
    [player] call _add;
    player addEventHandler ["Respawn", { params ["_u"]; [_u] call (missionNamespace getVariable ["COMSPEC_ATAK_BatterySwapAction", {}]); }];
};

// Mission de tir reçue (servant d'une pièce) : notification, vibration, cible sur la carte du téléphone.
["comspec_atak_native_fireMission", {
    params ["_summary", "_tgt", "_from"];
    private _f = [] call comspec_atak_native_fnc_firesState;
    _f set ["target", _tgt];
    private _log = _f getOrDefault ["log", []];
    _log pushBack format ["<t color='#f2ab33'>%1</t> REÇU de %2 · %3", [dayTime, "HH:MM"] call BIS_fnc_timeToString, _from, _summary];
    while { (count _log) > 50 } do { _log deleteAt 0; };
    _f set ["log", _log];
    ["WARNING", format ["MISSION DE TIR : %1", _summary], 8, 80] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
}] call CBA_fnc_addEventHandler;

// Briefing : la page suit la diapositive en cours (deck Google du présentateur, image chargée, liste Athena).
[{
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    // Onglet PARAMÈTRES (engrenage) : pas de rendu automatique, il effacerait le lien en cours de saisie.
    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "BRIEFING" || {(_s getOrDefault ["briefTab", "SLIDES"]) in ["MISSION", "SETTINGS"]}) exitWith {};
    if (isNull ([] call comspec_atak_native_fnc_display)) exitWith {};
    // Actualisation automatique (engrenage) : diapositives Athena relues toutes les 60 s, sans revenir à la première.
    if ((profileNamespace getVariable ["COMSPEC_ATAK_BriefAuto", false]) && {[] call comspec_atak_native_fnc_bridge} && {diag_tickTime > (uiNamespace getVariable ["COMSPEC_ATAK_BriefAutoAt", 0])}) then {
        uiNamespace setVariable ["COMSPEC_ATAK_BriefAutoAt", diag_tickTime + 60];
        if ((([] call comspec_atak_native_fnc_briefingSignature) select 0) isEqualTo "ATHENA") then { [] spawn { [] call comspec_overwatch_connect_fnc_getBriefingSlides; }; };
    };
    private _sig = (([] call comspec_atak_native_fnc_briefingSignature) select [0, 4]) + [["get"] call comspec_atak_native_fnc_briefingLive, count (["attendees"] call comspec_atak_native_fnc_briefingLive)];
    // Présentations FENRIR à portée (onglet ÉCRANS) : nouvelle révision = redessin.
    _sig pushBack (["sig"] call comspec_atak_native_fnc_fenrir);
    if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BriefSig", []])) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_BriefSig", _sig];
    ["BRIEFING"] call comspec_atak_native_fnc_pageRender;
}, 1] call comspec_atak_native_fnc_pfhSpread;

// Question posée pendant ma présentation : alerte sur mon téléphone.
["comspec_atak_native_briefQ", {
    params ["_who", "_txt"];
    ["MESSAGE", format ["Question de %1 : %2", _who, [_txt, (_txt select [0, 80]) + "…"] select ((count _txt) > 80)], 8, 50] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    private _q = uiNamespace getVariable ["COMSPEC_ATAK_BriefQ", createHashMap];
    _q set ["slide", -2];
}] call CBA_fnc_addEventHandler;

// Briefing présenté en direct : suivre le présentateur de mon camp, signaler ma présence.
[{ ["tick"] call comspec_atak_native_fnc_briefingLive; }, 2] call comspec_atak_native_fnc_pfhSpread;
// Écrans COMSPEC proches : diapositive diffusée affichée sur l'objet, actions ACE ou molette (fn_screenCast).
[{ ["tick"] call comspec_atak_native_fnc_screenCast; }, 2] call comspec_atak_native_fnc_pfhSpread;
// Écrans TOC proches : réseau ATAK du camp affiché sur l'objet (texture UI), actions ACE ou molette (fn_tocScreen).
[{ ["tick"] call comspec_atak_native_fnc_tocScreen; }, 1] call comspec_atak_native_fnc_pfhSpread;

// App Musique : son du lecteur et des haut-parleurs voisins ; coupé en quittant la partie (la DLL jouerait encore au menu).
[{ [] call comspec_atak_native_fnc_musicTick; }, 0.5] call comspec_atak_native_fnc_pfhSpread;
addMissionEventHandler ["Ended", { ["MusicStop"] call comspec_atak_native_fnc_extensionCall; }];
[{ !isNull (findDisplay 46) }, { (findDisplay 46) displayAddEventHandler ["Unload", { ["MusicStop"] call comspec_atak_native_fnc_extensionCall; }]; }] call CBA_fnc_waitUntilAndExecute;

// Live cam partagé vers COMSPEC Overwatch : une image toutes les N s si le joueur l'a activé.
[{ [] call comspec_atak_native_fnc_livecamShare; }, 2] call comspec_atak_native_fnc_pfhSpread;
// Liaison montante publiée (débit, perte, relais) : les autres téléphones en déduisent la qualité de mon Live cam.
[{ ["publish"] call comspec_atak_native_fnc_feedLink; }, 10] call comspec_atak_native_fnc_pfhSpread;

// Détecteur de drones : balayage automatique en fond (réglage de l'app).
[{ if (profileNamespace getVariable ["COMSPEC_ATAK_DroneAuto", false]) then { ["auto"] call comspec_atak_native_fnc_droneDetectScan; }; }, 5] call comspec_atak_native_fnc_pfhSpread;
// Drones pilotés au téléphone : une seule main sur le drone (COMSPEC_DroneLock, fn_droneAction).
//   "" : le téléphone pilote ; les autres joueurs ne peuvent pas connecter de terminal UAV. Le pilote (COMSPEC_DroneOwner)
//        le peut toujours : connecter son terminal (UAV Terminal vanilla, tablette OTAN...) passe le drone en pilotage
//        manuel ; un ordre du téléphone donné ensuite reprend la main et déconnecte son terminal.
//   uid : seul ce joueur peut s'y connecter (pilotage manuel) ; absent : drone libre.
// Pilote parti de la partie : le verrou ne tient plus (avant : drone bloqué pour tous jusqu'à la fin de la mission) ; le
// premier joueur qui y connecte un terminal libère le drone (fn_droneCmd "release").
// Appliqué toutes les 0,5 s pour primer sur les mods qui réactivent la connexion (Mavic).
[{
    private _blocked = missionNamespace getVariable ["COMSPEC_ATAK_UavBlocked", []];
    private _uid = getPlayerUID player;
    private _conn = getConnectedUAV player;
    private _prev = missionNamespace getVariable ["COMSPEC_ATAK_UavPrev", objNull];
    missionNamespace setVariable ["COMSPEC_ATAK_UavPrev", _conn];
    // Vue regardée au terminal UAV, publiée pour les Live cam des autres (fn_livecamUav, DRONES DU CAMP) : siège
    // (DRIVER / GUNNER), champ de la caméra (zoom, 0,75 sans zoom) et vision (0 jour, 1 NV, 2 thermique). Diffusée
    // seulement quand elle change ; effacée quand l'opérateur quitte la vue du drone.
    private _vw = [];
    if (!isNull _conn && {cameraOn isEqualTo _conn}) then {
        private _ctl = UAVControl _conn;
        private _ci = _ctl find player;
        private _seat = if (_ci >= 0) then { _ctl param [_ci + 1, ""] } else { "" };
        if (_seat in ["DRIVER", "GUNNER"]) then {
            private _su = [driver _conn, gunner _conn] select (_seat isEqualTo "GUNNER");
            private _zoom = ((call CBA_fnc_getFov) param [1, 1]) max 0.05;
            _vw = [_seat, round (7500 / _zoom) / 10000, [0, currentVisionMode _su] select (!isNull _su)];
        };
    };
    (missionNamespace getVariable ["COMSPEC_ATAK_UavViewPub", [objNull, []]]) params ["_pubU", "_pubV"];
    if (_pubU isNotEqualTo _conn || {_pubV isNotEqualTo _vw}) then {
        if (!isNull _pubU && {_pubU isNotEqualTo _conn}) then { _pubU setVariable ["COMSPEC_UavView", nil, true]; };
        if (!isNull _conn) then { if ((count _vw) > 0) then { _conn setVariable ["COMSPEC_UavView", _vw, true]; } else { _conn setVariable ["COMSPEC_UavView", nil, true]; }; };
        missionNamespace setVariable ["COMSPEC_ATAK_UavViewPub", [_conn, _vw]];
    };
    private _online = allPlayers apply { getPlayerUID _x };
    {
        private _lock = _x getVariable "COMSPEC_DroneLock";
        private _owner = _x getVariable ["COMSPEC_DroneOwner", ""];
        private _stale = _owner isNotEqualTo "" && {!(_owner in _online)};
        if (!isNil "_lock" && {_lock isNotEqualTo _uid}) then {
            switch (true) do {
                case _stale: {
                    if (_conn isEqualTo _x) then {
                        _x setVariable ["COMSPEC_DroneOwner", "", true];
                        _x setVariable ["COMSPEC_DroneLock", nil, true];
                        ["comspec_atak_native_droneCmd", [_x, "release", []], _x] call CBA_fnc_targetEvent;
                    };
                };
                case (_owner isEqualTo _uid): {
                    if (_conn isNotEqualTo _x) exitWith {};
                    if (_prev isNotEqualTo _x) exitWith {
                        _x setVariable ["COMSPEC_DroneLock", _uid, true];
                        ["comspec_atak_native_droneCmd", [_x, "manual", []], _x] call CBA_fnc_targetEvent;
                        ["INFO", "Terminal UAV connecté : pilotage manuel. Un ordre du téléphone reprend la main.", 5, 40] call comspec_atak_native_fnc_notify;
                    };
                    player connectTerminalToUAV objNull;
                    ["WARNING", "Ordre du téléphone : terminal UAV déconnecté", 4, 40] call comspec_atak_native_fnc_notify;
                };
                default {
                    player disableUAVConnectability [_x, true];
                    if (_conn isEqualTo _x) then {
                        player connectTerminalToUAV objNull;
                        ["WARNING", "Drone piloté depuis un téléphone : terminal déconnecté", 4, 40] call comspec_atak_native_fnc_notify;
                    };
                    _blocked pushBackUnique _x;
                };
            };
        };
    } forEach allUnitsUAV;
    // Verrou levé, rendu à ce joueur, pilote parti ou c'est le mien : connexion rétablie une fois.
    {
        private _lock = _x getVariable "COMSPEC_DroneLock";
        private _owner = if (isNull _x) then { "" } else { _x getVariable ["COMSPEC_DroneOwner", ""] };
        if (isNull _x || {isNil "_lock"} || {_lock isEqualTo _uid} || {_owner isEqualTo _uid} || {_owner isNotEqualTo "" && {!(_owner in _online)}}) then {
            if (!isNull _x) then { player enableUAVConnectability [_x, true]; };
            _blocked set [_forEachIndex, objNull];
        };
    } forEach _blocked;
    missionNamespace setVariable ["COMSPEC_ATAK_UavBlocked", _blocked - [objNull]];
}, 0.5] call comspec_atak_native_fnc_pfhSpread;
// Rejeu de mission : une image toutes les 10 s, et les pertes amies avec leur position.
[{ [] call comspec_atak_native_fnc_aarRecord; }, 10] call comspec_atak_native_fnc_pfhSpread;
addMissionEventHandler ["EntityKilled", {
    params ["_unit"];
    if (!(_unit isKindOf "CAManBase") || {!(missionNamespace getVariable ["comspec_atak_native_aar", true])}) exitWith {};
    if ((side group _unit) isNotEqualTo (side group player)) exitWith {};
    private _ev = missionNamespace getVariable ["COMSPEC_ATAK_AarEvents", []];
    private _p = getPosASL _unit;
    _ev pushBack [time, round (_p select 0), round (_p select 1), name _unit];
    if ((count _ev) > 300) then { _ev deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_AarEvents", _ev];
    // Couche des pertes de la relecture Athena (télémétrie combat / kia). Un seul téléphone signale chaque mort :
    // le sien, le chef de groupe pour ses IA, sinon les témoins à moins de 300 m (doublons écartés par le site).
    private _lead = leader group _unit;
    private _mine = _unit isEqualTo player
        || {!isPlayer _unit && {_lead isEqualTo player || {!isPlayer _lead && {(player distance2D _unit) < 300}}}};
    // Données remontées choisies par la communauté (Athena, COMSPEC_DataFlows tenu par COMSPEC Link) : pertes non envoyées.
    if (_mine && {[] call comspec_atak_native_fnc_bridge} && {!(missionNamespace getVariable ["COMSPEC_DisconnectSent", false])}
        && {(missionNamespace getVariable ["COMSPEC_DataFlows", createHashMap]) getOrDefault ["combat", true]}) then {
        private _cs = [_unit] call comspec_atak_native_fnc_unitCallsign;
        private _pay = createHashMapFromArray [
            ["t", "combat"], ["kind", "kia"], ["victim", [_cs, name _unit] select (_cs isEqualTo "")], ["name", name _unit],
            ["x", round (_p select 0)], ["y", round (_p select 1)], ["call_sign", [player] call comspec_atak_native_fnc_unitCallsign],
            ["mission_key", missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", ""]]
        ];
        // Clé de regroupement propre à ce mort : deux pertes dans la même seconde ne s'écrasent pas dans la file.
        "COMSPECExtension" callExtension ["EmitTelemetry", ["1", "combat", format ["kia:%1:%2", name _unit, round time], [_pay] call comspec_atak_native_fnc_json]];
    };
}];

// Débit simulé : la file d'envoi part dès que le réseau revient, et les SMS restés chez l'opérateur arrivent.
// Hors connexion (fn_offline) : données figées, réceptions retenues, synchro et bilan au retour, sauvegarde du profil.
[{
    ["tick"] call comspec_atak_native_fnc_offline;
    ["flush"] call comspec_atak_native_fnc_markerNet;
    // Échéance unique d'écriture du profil (fn_profileSave : sauvegardes périodiques regroupées).
    ["tick"] call comspec_atak_native_fnc_profileSave;
    private _inbox = missionNamespace getVariable ["COMSPEC_ATAK_P2pInbox", []];
    if ((count _inbox) > 0 && {[] call comspec_atak_native_fnc_p2pReachable}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_P2pInbox", []];
        { (_x + [true]) call comspec_atak_native_fnc_p2pReceive; } forEach _inbox;
    };
    private _queue = missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    if ((count _queue) isEqualTo 0) exitWith {};
    // Réception seule (hors de portée d'émission) : la file attend aussi.
    private _lq = [] call comspec_atak_native_fnc_linkQuality;
    if ((_lq get "bars") isEqualTo 0 || {!(_lq getOrDefault ["tx", true])}) exitWith {};
    // Au plus 5 envois toutes les 2 s : vider toute la file d'un coup figeait le jeu au retour du réseau.
    private _batch = _queue select [0, 5];
    missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", _queue select [5]];
    if !(missionNamespace getVariable ["COMSPEC_ATAK_NetFlushing", false]) then { ["SUCCESS", format ["Réseau revenu : %1 envoi(s) en attente partent", count _queue], 4, 30] call comspec_atak_native_fnc_notify; };
    missionNamespace setVariable ["COMSPEC_ATAK_NetFlushing", (count _queue) > 5];
    { _x call comspec_atak_native_fnc_netSend; } forEach _batch;
}, 2] call comspec_atak_native_fnc_pfhSpread;

// Dégâts du téléphone (fn_deviceImpact) : selon où il est porté (main, gilet, poche, sac), la partie du corps touchée,
// la munition et ce que le gilet arrête ; éclats et souffle des explosions, chutes, accidents, eau.
// Les parties touchées d'un même coup sont regroupées et traitées à l'image suivante (un seul tirage par coup).
missionNamespace setVariable ["COMSPEC_ATAK_HitQueue", []];
uiNamespace setVariable ["COMSPEC_ATAK_HitPush", {
    params ["_part", "_dmg", "_raw", "_ammo"];
    private _q = missionNamespace getVariable ["COMSPEC_ATAK_HitQueue", []];
    if ((count _q) isEqualTo 0) then {
        [{
            private _q = missionNamespace getVariable ["COMSPEC_ATAK_HitQueue", []];
            missionNamespace setVariable ["COMSPEC_ATAK_HitQueue", []];
            if (alive player && {(count _q) > 0}) then {
                ["hits", _q] call comspec_atak_native_fnc_deviceImpact;
                // Sang sur l'écran et la coque (téléphone en main ou sur le gilet).
                ["hit", _q] call comspec_atak_native_fnc_screenDirt;
            };
        }] call CBA_fnc_execNextFrame;
    };
    _q pushBack [_part, _dmg, _raw, _ammo];
    missionNamespace setVariable ["COMSPEC_ATAK_HitQueue", _q];
}];
if (isClass (configFile >> "CfgPatches" >> "ace_medical_engine")) then {
    // ACE : [unité, [[dégâts après gilet, partie, dégâts sans gilet]...], tireur, munition] (munition « falling », « vehiclecrash »… pour les chocs).
    ["ace_medical_woundReceived", {
        params ["_unit", ["_damages", []], "", ["_ammo", ""]];
        if (_unit isNotEqualTo player) exitWith {};
        if !(_ammo isEqualType "") then { _ammo = ""; };
        {
            _x params [["_d", 0], ["_part", ""], ["_raw", -1]];
            if (_raw < 0) then { _raw = _d; };
            if ((_d max _raw) > 0.02) then { [_part, _d, _raw, _ammo] call (uiNamespace getVariable ["COMSPEC_ATAK_HitPush", {}]); };
        } forEach _damages;
    }] call CBA_fnc_addEventHandler;
} else {
    // Sans ACE : chaque point de dégâts qui augmente (Dammaged ne change pas les dégâts, contrairement à HandleDamage).
    player addEventHandler ["Dammaged", {
        params ["_unit", "_sel", "_dmg", "", "_hp", "", ["_proj", ""]];
        private _prev = missionNamespace getVariable ["COMSPEC_ATAK_HitPrev", createHashMap];
        private _k = toLower ([_hp, _sel] select (_hp isEqualTo ""));
        private _d = _dmg - (_prev getOrDefault [_k, 0]);
        _prev set [_k, _dmg];
        missionNamespace setVariable ["COMSPEC_ATAK_HitPrev", _prev];
        if (_d < 0.03 || {_k isEqualTo ""}) exitWith {};
        private _part = switch (true) do {
            case ((_k find "head") >= 0 || {(_k find "face") >= 0} || {(_k find "neck") >= 0}): { "head" };
            case ((_k find "hand") >= 0 || {(_k find "arm") >= 0}): { selectRandom ["leftarm", "rightarm"] };
            case ((_k find "leg") >= 0): { selectRandom ["leftleg", "rightleg"] };
            default { "body" };
        };
        if !(_proj isEqualType "") then { _proj = ""; };
        [_part, _d, _d, _proj] call (uiNamespace getVariable ["COMSPEC_ATAK_HitPush", {}]);
    }];
    player addEventHandler ["HandleHeal", { missionNamespace setVariable ["COMSPEC_ATAK_HitPrev", createHashMap]; }];
};
player addEventHandler ["Explosion", {
    params ["", "_d", ["_src", objNull]];
    if (_d < 0.02 || {diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_BlastAt", 0])}) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_BlastAt", diag_tickTime + 0.5];
    private _dist = [-1, player distance _src] select (!isNull _src);
    ["blast", [_d, _dist]] call comspec_atak_native_fnc_deviceImpact;
    ["blast", _d] call comspec_atak_native_fnc_screenDirt;
}];
// Réapparition : téléphone neuf (charge de départ du serveur comme fn_battery, données effacées oubliées).
player addEventHandler ["Respawn", { missionNamespace setVariable ["COMSPEC_ATAK_Device", createHashMap]; missionNamespace setVariable ["COMSPEC_ATAK_Battery", 5 max (missionNamespace getVariable ["comspec_atak_native_battery_start", 100]) min 100]; missionNamespace setVariable ["COMSPEC_ATAK_HitPrev", createHashMap]; missionNamespace setVariable ["COMSPEC_ATAK_HandsBlood", 0]; ["resync"] call comspec_atak_native_fnc_phoneSecurity; }];
// Eau : téléphone étanche quelques dizaines de secondes ; ensuite l'eau s'infiltre (haut-parleur, batterie, extinction…),
// détruit seulement après une longue immersion. Dans le sac, l'eau entre deux fois moins vite. L'eau lave aussi l'écran.
[{
    if (!alive player) exitWith {};
    private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
    private _wet = _n getOrDefault ["wet", 0];
    if (((eyePos player) select 2) < -0.2 && {(vehicle player) isEqualTo player}) then {
        _wet = _wet + ([3, 1.5] select ((["carry"] call comspec_atak_native_fnc_deviceImpact) isEqualTo "backpack"));
        _n set ["wet", _wet];
        missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
        ["water"] call comspec_atak_native_fnc_screenDirt;
        if (_wet > 25 && {random 1 < 0.12}) then { ["water", _wet] call comspec_atak_native_fnc_deviceImpact; };
    } else {
        if (_wet > 0) then { _n set ["wet", (_wet - 2) max 0]; missionNamespace setVariable ["COMSPEC_ATAK_Device", _n]; };
    };
}, 3] call comspec_atak_native_fnc_pfhSpread;

// Saleté de l'écran (fn_screenDirt, rendu fn_screenSurface) : poussière, pluie, traces de doigts, sang après un soin.
[{ ["tick"] call comspec_atak_native_fnc_screenDirt; }, 5] call comspec_atak_native_fnc_pfhSpread;
["ace_treatmentSucceded", {
    params [["_medic", objNull], ["_patient", objNull]];
    if (_medic isEqualTo player && {_patient isNotEqualTo player || {(player getVariable ["ace_medical_woundBleeding", 0]) > 0}}) then { ["treat", _patient] call comspec_atak_native_fnc_screenDirt; };
}] call CBA_fnc_addEventHandler;
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _clean = ["COMSPEC_ATAK_Clean", "Nettoyer l'écran", "", {
        [{ ["start"] call comspec_atak_native_fnc_screenClean; }] call CBA_fnc_execNextFrame;
    }, {
        ["can"] call comspec_atak_native_fnc_screenClean
    }] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Care"], _clean, true] call ace_interact_menu_fnc_addActionToClass;
    // Nettoyage complet (10 s) : tout part d'un coup, sang compris, sans kit ni objet.
    private _deep = ["COMSPEC_ATAK_CleanDeep", "Nettoyage complet (écran et coque)", "", {
        [{ ["deep"] call comspec_atak_native_fnc_screenClean; }] call CBA_fnc_execNextFrame;
    }, {
        ["can"] call comspec_atak_native_fnc_screenClean
    }] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Care"], _deep, true] call ace_interact_menu_fnc_addActionToClass;
    // Tension et SpO2 : brassard et oxymètre posés par un soignant sur un blessé (8 s) ; l'app Médical et la liaison NFC
    // n'affichent ces valeurs qu'après cette mesure (fn_medVitals « measure »). Pas d'action sur soi.
    private _measure = {
        params ["_target"];
        private _go = { params ["_t"]; [_t, "measure"] call comspec_atak_native_fnc_medVitals;
            ["SUCCESS", format ["Tension et SpO2 mesurées : %1", [_t, true] call comspec_atak_native_fnc_unitCallsign], 4, 30] call comspec_atak_native_fnc_notify; };
        if (isNil "ace_common_fnc_progressBar") exitWith { [_target] call _go; };
        [8, [_target, _go], { (_this select 0) params ["_t", "_go"]; [_t] call _go; }, {}, ("Brassard et oxymètre…" call comspec_atak_native_fnc_tr),
            { (_this select 0) params ["_t"]; alive _t && {(player distance _t) < 3} }] call ace_common_fnc_progressBar;
        player playActionNow "Medic";
    };
    private _bp = ["COMSPEC_ATAK_MeasureBp", "Mesurer tension et SpO2", "", { [_this select 0] call (_this select 2) }, {
        alive (_this select 0) && {isNull objectParent (_this select 1)}
    }, {}, _measure] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 0, ["ACE_MainActions"], _bp, true] call ace_interact_menu_fnc_addActionToClass;
};
// Sang projeté sur le téléphone sorti quand un homme est tué à moins de 3,5 m (fn_screenDirt « nearby »).
addMissionEventHandler ["EntityKilled", {
    params ["_dead"];
    if (!hasInterface || {!(_dead isKindOf "CAManBase")} || {_dead isEqualTo player} || {!alive player}) exitWith {};
    private _d = player distance _dead;
    if (_d < 3.5) then { ["nearby", [_d]] call comspec_atak_native_fnc_screenDirt; };
}];

// Actions ACE : réparer le téléphone (kit de réparation ATAK consommé, ou caisse à outils) ou passer sur un téléphone de rechange.
// La réparation couvre aussi l'état du réalisme Overwatch (écran cassé, appareil détruit) : fn_repairStart, fn_deviceRepair.
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _fix = ["COMSPEC_ATAK_Repair", "Réparer le téléphone", "\z\comspec_atak_native\addons\main\data\item_repairkit.paa", {
        [{ ["start"] call comspec_atak_native_fnc_repairStart; }] call CBA_fnc_execNextFrame;
    }, {
        ["can"] call comspec_atak_native_fnc_repairStart
    }] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Care"], _fix, true] call ace_interact_menu_fnc_addActionToClass;
    private _swap = ["COMSPEC_ATAK_Swap", "Passer au téléphone de rechange", "", {
        private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
        private _spare = (items player) select { (toLower _x) in _cat };
        if ((count _spare) > 0) then { player removeItem (_spare select 0); ["swap"] call comspec_atak_native_fnc_deviceRepair; };
    }, {
        ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["damage", 0]) >= 0.45
        && {private _cat = [] call comspec_atak_native_fnc_deviceCatalog; ((((assignedItems player) + (items player)) select { (toLower _x) in _cat }) param [1, ""]) isNotEqualTo "" && {((items player) findIf { (toLower _x) in _cat }) >= 0}}
    }] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Care"], _swap, true] call ace_interact_menu_fnc_addActionToClass;
};

// Drones : connecter / déconnecter le téléphone ATAK en étant au contact du drone (ACE et molette).
private _droneCan = { params ["_t"]; alive _t && {unitIsUAV _t} && {(player distance _t) < 4} && {[player] call comspec_atak_native_fnc_hasDevice} && {(missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]) isNotEqualTo _t} };
private _droneIsMine = { params ["_t"]; (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]) isEqualTo _t && {(player distance _t) < 4} };
missionNamespace setVariable ["COMSPEC_ATAK_DroneCan", _droneCan];
missionNamespace setVariable ["COMSPEC_ATAK_DroneIsMine", _droneIsMine];
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _con = ["COMSPEC_ATAK_DronePair", "Connecter l'ATAK au drone", "", {
        params ["_t"];
        if (isNil "ace_common_fnc_progressBar") exitWith { ["pair", _t] call comspec_atak_native_fnc_droneAction; };
        [4, [_t], { ["pair", (_this select 0) select 0] call comspec_atak_native_fnc_droneAction; }, {}, ("Appairage du drone…" call comspec_atak_native_fnc_tr)] call ace_common_fnc_progressBar;
    }, { [_target] call (missionNamespace getVariable ["COMSPEC_ATAK_DroneCan", { false }]) }] call comspec_atak_native_fnc_aceAction;
    ["Air", 0, ["ACE_MainActions"], _con, true] call ace_interact_menu_fnc_addActionToClass;
    private _dis = ["COMSPEC_ATAK_DroneUnpair", "Déconnecter l'ATAK du drone", "", { ["unpair"] call comspec_atak_native_fnc_droneAction; },
        { [_target] call (missionNamespace getVariable ["COMSPEC_ATAK_DroneIsMine", { false }]) }] call comspec_atak_native_fnc_aceAction;
    ["Air", 0, ["ACE_MainActions"], _dis, true] call ace_interact_menu_fnc_addActionToClass;
};
// Molette : les actions sont posées sur chaque drone proche, une seule fois par drone.
[{
    if (isNull player || {!alive player}) exitWith {};
    {
        if (unitIsUAV _x && {!(_x getVariable ["COMSPEC_ATAK_DroneActs", false])}) then {
            _x setVariable ["COMSPEC_ATAK_DroneActs", true];
            _x addAction [format ["<t color='#7fd0b0'>%1</t>", "Connecter l'ATAK au drone" call comspec_atak_native_fnc_tr], { ["pair", _this select 0] call comspec_atak_native_fnc_droneAction; }, nil, 5, false, true, "",
                "[_target] call (missionNamespace getVariable ['COMSPEC_ATAK_DroneCan', { false }])", 4];
            _x addAction [format ["<t color='#e0a050'>%1</t>", "Déconnecter l'ATAK du drone" call comspec_atak_native_fnc_tr], { ["unpair"] call comspec_atak_native_fnc_droneAction; }, nil, 5, false, true, "",
                "[_target] call (missionNamespace getVariable ['COMSPEC_ATAK_DroneIsMine', { false }])", 4];
        };
    } forEach (player nearEntities [["Air", "LandVehicle"], 15]);
}, 2] call comspec_atak_native_fnc_pfhSpread;

// App Groupe : arrivées et changement de chef annoncés aux membres.
["comspec_atak_native_groupNotice", {
    params ["_text"];
    ["INFO", _text, 5, 30] call comspec_atak_native_fnc_notify;
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "GROUP") then { [{ ["GROUP"] call comspec_atak_native_fnc_pageRender; }, [], 0.5] call CBA_fnc_waitAndExecute; };
}] call CBA_fnc_addEventHandler;

// Équipes de feu : un changement validé par le serveur (fn_ftServer) redessine Groupe, BFT et Inter-team ;
// l'annonce ne s'affiche que pour le groupe concerné.
["comspec_atak_native_ftChanged", {
    params ["_g", ["_msg", ""]];
    if (_g isEqualTo group player && {_msg isNotEqualTo ""}) then { ["INFO", _msg, 4, 30] call comspec_atak_native_fnc_notify; };
    private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
    if (_page in ["GROUP", "BFT", "INTERTEAM"] && {!isNull ([] call comspec_atak_native_fnc_display)}) then {
        [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page, 0.4] call CBA_fnc_waitAndExecute;
    };
}] call CBA_fnc_addEventHandler;
// Escouades et équipes de feu vers Athena (rapporteur du groupe seulement, si quelque chose a changé).
[{ [] call comspec_atak_native_fnc_squadSync; }, 30] call comspec_atak_native_fnc_pfhSpread;
// Plan de briefing partagé vers la carte du site (auteur de la modification, sinon un autre téléphone relié).
[{ ["tick"] call comspec_atak_native_fnc_planWeb; }, 5] call comspec_atak_native_fnc_pfhSpread;
// Temps d'écran et temps par rôle (envoi à Athena toutes les 5 min et en fin de mission).
[{ ["tick"] call comspec_atak_native_fnc_screenTime; }, 5] call comspec_atak_native_fnc_pfhSpread;
// Montre Garmin : pas, fréquence cardiaque, stress, Body Battery, sommeil et activités, gardés dans le profil du joueur.
[{ ["tick"] call comspec_atak_native_fnc_garminTick; }, 2] call comspec_atak_native_fnc_pfhSpread;
// Montre Garmin au poignet (fn_watch) : la touche « Montre » du jeu (O) l'allume à la place de la montre d'Arma quand le
// joueur la porte ; appui court : écran suivant, appui long : éteinte. Sans montre Garmin, la touche garde son effet.
[{ !isNull (findDisplay 46) }, {
    (findDisplay 46) displayAddEventHandler ["KeyDown", {
        params ["", "_key", "_shift", "_ctrl", "_alt"];
        if (_shift || {_ctrl} || {_alt} || {!(_key in (actionKeys "Watch"))}) exitWith { false };
        ["down"] call comspec_atak_native_fnc_watch
    }];
    (findDisplay 46) displayAddEventHandler ["KeyUp", {
        params ["", "_key"];
        if !(_key in (actionKeys "Watch")) exitWith { false };
        ["up"] call comspec_atak_native_fnc_watch
    }];
}] call CBA_fnc_waitUntilAndExecute;
// Boutons de la montre et actions directes (touches CBA, modifiables aussi dans Réglages > Accès et touches du téléphone ;
// fn_keybinds). Chaque touche n'est prise que si le joueur porte la montre Garmin (sinon elle garde son effet).
{
    _x params ["_id", "_title", "_help", "_down", "_up", "_def"];
    ["COMSPEC ATAK", _id, [_title, _help], _down, _up, _def, false] call CBA_fnc_addKeybind;
} forEach [
    ["GarminLight", "Montre Garmin : LIGHT", "Appui court : luminosité. Appui long : menu des commandes (vue agrandie, mode nuit, ne pas déranger, point, téléphone).", { ["lightDown"] call comspec_atak_native_fnc_watch }, { ["lightUp"] call comspec_atak_native_fnc_watch }, [0x18, [false, true, true]]],
    ["GarminPrev", "Montre Garmin : UP (écran précédent)", "Écran précédent ; dans un menu : remonter ; minuteur en réglage : +1 min.", { ["prev"] call comspec_atak_native_fnc_watch }, {}, [0x18, [false, true, false]]],
    ["GarminNext", "Montre Garmin : DOWN (écran suivant)", "Écran suivant ; dans un menu : descendre ; minuteur en réglage : -1 min. La touche Montre du jeu (O) fait la même chose.", { ["next"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminStart", "Montre Garmin : START·STOP", "Action de l'écran : chrono, minuteur, marquer la position, zoom de la carte, étalonner l'altimètre, faire sonner le téléphone ; dans un menu : valider.", { ["start"] call comspec_atak_native_fnc_watch }, {}, [0x18, [true, false, false]]],
    ["GarminBack", "Montre Garmin : BACK·LAP", "Retour au cadran, tour du chrono, remise à zéro ; dans un menu : fermer ; sur le cadran : éteindre.", { ["back"] call comspec_atak_native_fnc_watch }, {}, [0x18, [false, false, true]]],
    ["GarminBig", "Montre Garmin : vue agrandie", "Affiche la montre en grand au milieu de l'écran, ou la remet au poignet.", { ["big"] call comspec_atak_native_fnc_watch }, {}, [0x18, [true, true, false]]],
    ["GarminToggle", "Montre Garmin : allumer / éteindre", "Allume la montre sur le cadran, ou l'éteint.", { ["toggle"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminClose", "Montre Garmin : éteindre", "Éteint la montre Garmin (sinon : appui long sur la touche Montre du jeu, O).", { ["close"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminMenu", "Montre Garmin : menu des commandes", "Ouvre ou ferme le menu des commandes (comme l'appui long sur LIGHT).", { ["menu"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminChrono", "Montre Garmin : chrono départ / arrêt", "Lance ou arrête le chronomètre, montre allumée sur le chrono.", { ["chrono"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminLap", "Montre Garmin : tour du chrono", "Enregistre un tour, chrono lancé.", { ["lap"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminTimer", "Montre Garmin : minuteur", "Règle puis lance le minuteur, le met en pause ou le reprend.", { ["timer"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminMark", "Montre Garmin : marquer ma position", "Garde ma position GPS : la boussole et la carte de la montre montrent ensuite sa direction et sa distance.", { ["mark"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminNight", "Montre Garmin : mode nuit", "Écran de la montre en rouge, pour garder la vision de nuit.", { ["night"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminDnd", "Montre Garmin : ne pas déranger", "Plus de bandeau ni de vibration à l'arrivée des notifications du téléphone.", { ["dnd"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminFind", "Montre Garmin : trouver mon téléphone", "Fait sonner et vibrer le téléphone ATAK relié en Bluetooth.", { ["find"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminHHour", "Montre Garmin : heure H au groupe", "Chef de groupe : lance (ou annule) un compte à rebours de la durée du minuteur sur les montres de tout le groupe, qui vibrent ensemble au top.", { ["hhour"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminJump", "Montre Garmin : Jumpmaster", "Écran de saut (HALO / HAHO) : règle l'altitude d'ouverture et arme les alertes, ou les désarme.", { ["jump"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]],
    ["GarminStealth", "Montre Garmin : mode furtif", "Coupe le Bluetooth de la montre et met l'écran au minimum.", { ["stealth"] call comspec_atak_native_fnc_watch }, {}, [0, [false, false, false]]]
];
// App Distances : carnet des missions, carrière et records gardés sur ce PC (compteurs poussés par le serveur toutes les 15 s).
[{ ["tick"] call comspec_atak_native_fnc_distanceLog; }, 15] call comspec_atak_native_fnc_pfhSpread;
// Distances et heures de vol : relais du joueur quand le serveur n'arrive pas à envoyer à Athena (COMSPEC_ATAK_SrvPlayOK faux).
[{ ["self"] call comspec_atak_native_fnc_distanceServer; }, 3] call comspec_atak_native_fnc_pfhSpread;
addMissionEventHandler ["Ended", { ["selfFlush"] call comspec_atak_native_fnc_distanceServer; ["flush"] call comspec_atak_native_fnc_screenTime; [true] call comspec_atak_native_fnc_squadSync; ["save"] call comspec_atak_native_fnc_garminTick; ["save"] call comspec_atak_native_fnc_distanceLog; ["flush"] call comspec_atak_native_fnc_profileSave; }];
// Arrivée en cours de partie : mon équipe de feu d'un ancien groupe ne me suit pas.
[{ ["comspec_atak_native_ftCheck", [player]] call CBA_fnc_serverEvent; }, [], 5] call CBA_fnc_waitAndExecute;
// Rôles d'Athena et rôle mémorisé du joueur, réappliqué à l'arrivée et à la réapparition (fn_ftRoleSync).
["init"] call comspec_atak_native_fnc_ftRoleSync;
// Indicatif choisi dans C2 / Groupe (profil, hors ligne) publié aux autres joueurs (fn_callsignAction).
["init"] call comspec_atak_native_fnc_callsignAction;

// Serveur de la communauté : avertit le joueur relié à Athena qui joue ailleurs (vérifié dès que la liaison est prête,
// puis toutes les 10 min).
[{ ["check"] call comspec_atak_native_fnc_serverPresence; }, 60] call comspec_atak_native_fnc_pfhSpread;
[{ ["check"] call comspec_atak_native_fnc_serverPresence; }, [], 20] call CBA_fnc_waitAndExecute;
// Avis de l'administration ATHENA (compromission, annulation, données réinitialisées, certificat, message imposé).
[{ ["check"] call comspec_atak_native_fnc_adminNotices; }, 30] call comspec_atak_native_fnc_pfhSpread;
// CR de fin d'opération imposé (fn_debrief) : demandes du jeu, suivi du commandement, fiche ramenée toutes les 2 min tant
// qu'elle n'est pas remise ; à l'arrivée en cours de partie, le serveur renvoie les demandes qui visent le joueur.
["comspec_atak_native_debriefRecv", { ["recv", _this select 0] call comspec_atak_native_fnc_debrief; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_debriefData", {
    params [["_kind", ""], ["_data", []]];
    switch (_kind) do {
        case "track": { ["track", _data] call comspec_atak_native_fnc_debrief; };
        case "forced": { ["forced", _data] call comspec_atak_native_fnc_debrief; };
        case "lift": { ["lift", _data] call comspec_atak_native_fnc_debrief; };
        case "none": { ["Aucun joueur visé.", 3] call comspec_atak_native_fnc_toast; };
        case "denied": { ["Vous ne pouvez pas imposer le CR.", 3] call comspec_atak_native_fnc_toast; };
    };
}] call CBA_fnc_addEventHandler;
[{ ["tick"] call comspec_atak_native_fnc_debrief; }, 30] call comspec_atak_native_fnc_pfhSpread;
[{ if (!isNull player) then { ["comspec_atak_native_debrief", [player, "hello", []]] call CBA_fnc_serverEvent; }; }, [], 15] call CBA_fnc_waitAndExecute;
// App Admin : droit de gestion lu sur Athena (le lanceur ne la montre qu'à qui y a droit), 90 s après l'arrivée puis
// toutes les 15 min. Une seule petite requête, rien si le service Athena est coupé.
[{ ["probe"] call comspec_atak_native_fnc_adminClient; }, 900] call comspec_atak_native_fnc_pfhSpread;
[{ ["probe"] call comspec_atak_native_fnc_adminClient; }, [], 90] call CBA_fnc_waitAndExecute;

// Tinder : un joueur m'a liké (match si c'est réciproque).
["comspec_atak_native_rencard", {
    params ["_uid", "_name", ["_super", false]];
    private _by = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikedBy", []];
    _by pushBackUnique _uid;
    missionNamespace setVariable ["COMSPEC_ATAK_RencardLikedBy", _by];
    if (_super) then {
        private _sup = missionNamespace getVariable ["COMSPEC_ATAK_RencardSuperBy", []];
        _sup pushBackUnique _uid;
        missionNamespace setVariable ["COMSPEC_ATAK_RencardSuperBy", _sup];
    };
    if !(player getVariable ["COMSPEC_ATAK_Rencard", true]) exitWith {};
    switch (true) do {
        case (_uid in (missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []])): {
            ["SUCCESS", format ["Tinder : c'est un match avec %1 !", _name], 6, 50] call comspec_atak_native_fnc_notify;
        };
        // Un super like se montre : le nom est donné.
        case (_super): { ["SUCCESS", format ["Tinder : %1 vous a envoyé un super like !", _name], 5, 30] call comspec_atak_native_fnc_notify; };
        default { ["INFO", "Tinder : quelqu'un vous a liké", 4, 20] call comspec_atak_native_fnc_notify; };
    };
    [] call comspec_atak_native_fnc_vibrate;
}] call CBA_fnc_addEventHandler;

// Tinder · chasse aux HVT : tuyaux des informateurs et JACKPOT (toutes les 60 s), tuyaux partagés par un équipier.
[{ ["tick"] call comspec_atak_native_fnc_hvtIntel; }, 60] call comspec_atak_native_fnc_pfhSpread;
["comspec_atak_native_hvtTip", { ["recv", _this] call comspec_atak_native_fnc_hvtIntel; }] call CBA_fnc_addEventHandler;

// Logistique : demandes et statuts du camp ; guerre électronique : brouilleurs partagés.
["comspec_atak_native_logi", { if (["hold", ["comspec_atak_native_logi", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["recv", _this] call comspec_atak_native_fnc_logisticsAction; }] call CBA_fnc_addEventHandler;
// Décisions du poste web sur les demandes logistiques (mode passerelle).
[{ ["poll"] call comspec_atak_native_fnc_logiWeb; }, 20] call comspec_atak_native_fnc_pfhSpread;
[{ ["tick"] call comspec_atak_native_fnc_ewAction; }, 5] call comspec_atak_native_fnc_pfhSpread;

// OSINT : publication sur le fil public (tous les camps).
["comspec_atak_native_osintPost", {
    params ["_who", "_txt", "_time"];
    private _feed = missionNamespace getVariable ["COMSPEC_ATAK_OsintFeed", []];
    _feed pushBack [_who, _txt, _time];
    while { (count _feed) > 50 } do { _feed deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_OsintFeed", _feed];
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "OSINT") then { [{ ["OSINT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
}] call CBA_fnc_addEventHandler;

// GPS : le guidage (arrivée, recalcul) continue hors de l'app Carte, avec les consignes en notification.
[{
    if ((count (missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap])) isEqualTo 0) exitWith {};
    private _onMap = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP" && {!isNull ([] call comspec_atak_native_fnc_display)};
    if (_onMap) exitWith {};
    private _g = [] call comspec_atak_native_fnc_routeGuide;
    if ((count _g) isEqualTo 0) exitWith {};
    // Consigne vocale façon GPS : annoncée une fois à 200 m et à 50 m du virage.
    _g params ["_txt", "_dn"];
    private _key = format ["%1|%2", _txt, [0, 1] select (_dn < 60)];
    if (_dn < 220 && {_key isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_RouteSaid", ""])}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_RouteSaid", _key];
        ["INFO", format ["GPS : dans %1 m, %2", (round (_dn / 10)) * 10, toLower _txt], 4, 40] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
    };
}, 1] call comspec_atak_native_fnc_pfhSpread;

// Points de passage : itinéraire reçu d'un membre du groupe (envoi, ou mise à jour en direct), et passage automatique à
// l'étape suivante.
["comspec_atak_native_waypoints", { if (["hold", ["comspec_atak_native_waypoints", _this]] call comspec_atak_native_fnc_offline) exitWith {}; ["receive", _this] call comspec_atak_native_fnc_wpAction; }] call CBA_fnc_addEventHandler;
// Synchro à l'arrivée : le serveur rejoue les demandes logistiques / MEDEVAC du camp et l'itinéraire du groupe (sans alerte).
["comspec_atak_native_syncData", {
    params [["_log", []], ["_route", []]];
    missionNamespace setVariable ["COMSPEC_ATAK_Replaying", true];
    // Rejoué par paquets de 10 par image (jusqu'à plusieurs centaines d'événements : pas de saccade à l'arrivée) ;
    // itinéraire et fin du rejeu après le dernier paquet.
    private _step = {
        params ["_log", "_route", "_i", "_step"];
        missionNamespace setVariable ["COMSPEC_ATAK_Replaying", true];
        private _n = count _log;
        private _end = (_i + 10) min _n;
        for "_k" from _i to (_end - 1) do { (_log select _k) params ["_ev", "_args"]; [_ev, _args] call CBA_fnc_localEvent; };
        if (_end < _n) exitWith { [_step, [_log, _route, _end, _step]] call CBA_fnc_execNextFrame; };
        if ((count _route) >= 2 && {(count (_route select 0)) > 0} && {(count ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []])) isEqualTo 0}) then {
            ["receive", [_route select 0, _route select 1, false]] call comspec_atak_native_fnc_wpAction;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_Replaying", false];
        ["INFO", "SYNC", format ["Synchro serveur : %1 événement(s) rejoué(s)", _n]] call comspec_atak_native_fnc_log;
    };
    [_log, _route, 0, _step] call _step;
}] call CBA_fnc_addEventHandler;
[{ ["comspec_atak_native_syncReq", [player]] call CBA_fnc_serverEvent; }, [], 5] call CBA_fnc_waitAndExecute;
[{ if ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["nav", false]) then { ["tick"] call comspec_atak_native_fnc_wpAction; }; }, 1] call comspec_atak_native_fnc_pfhSpread;
// Breacher : top de brèche reçu du chef de colonne.
["comspec_atak_native_breach", { _this call comspec_atak_native_fnc_breachTop; }] call CBA_fnc_addEventHandler;
// Carte : ping d'un membre de mon camp (menu clic droit, fn_mapPing).
["comspec_atak_native_mapPing", { ["recv", _this] call comspec_atak_native_fnc_mapPing; }] call CBA_fnc_addEventHandler;
// App Explosifs : ordre et heure de pose de chaque charge du joueur (minuteries : temps restant).
["ace_explosives_place", {
    params ["_e", "", "", "_unit"];
    if (isNull _e || {_unit isNotEqualTo player}) exitWith {};
    _e setVariable ["COMSPEC_ATAK_PlacedAt", time];
    private _mine = (missionNamespace getVariable ["COMSPEC_ATAK_MyCharges", []]) select { !isNull _x && {alive _x} };
    _mine pushBack _e;
    missionNamespace setVariable ["COMSPEC_ATAK_MyCharges", _mine];
    uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
}] call CBA_fnc_addEventHandler;
// Signaux du poste (SMS, alerte plein écran, vibration) : Overwatch appelle ces crochets du module atak_athena,
// absent avec le mod natif ; on les fournit seulement s'ils ne sont pas déjà définis.
if (isNil "comspec_overwatch_atak_athena_fnc_athena_onNotify") then {
    comspec_overwatch_atak_athena_fnc_athena_onNotify = { params ["_o"]; ["notify", _o] call comspec_atak_native_fnc_athenaSignal; };
    comspec_overwatch_atak_athena_fnc_athena_onVibrate = { params ["_o"]; ["vibrate", _o] call comspec_atak_native_fnc_athenaSignal; };
};
// Alertes santé (inconscient, arrêt cardiaque, KIA) diffusées par Overwatch.
["COMSPEC_IcemanMedicalPanic", { ["health", _this] call comspec_atak_native_fnc_athenaSignal; }] call CBA_fnc_addEventHandler;
// App Liaison allié : événements, sauvegarde légère, action ACE.
[] call comspec_atak_native_fnc_linkAllyInit;
[] call comspec_atak_native_fnc_dataInit;
// Icônes PAA gardées en mémoire (sinon elles disparaissent un instant à chaque rafraîchissement de page).
[{ !isNull (findDisplay 46) }, { [] call comspec_atak_native_fnc_textureKeep; }] call CBA_fnc_waitUntilAndExecute;
// Fond PHOTO d'Athena (sans le mod @COMSPEC_ATAK_Photo) : meta demandée à la DLL une fois la liaison faite, pour que
// PHOTO soit proposé dès la première ouverture de la carte.
[{ ["prefetch"] call comspec_atak_native_fnc_photoMap; }, [], 15] call CBA_fnc_waitAndExecute;
// Profil : les écritures regroupées (fn_saveProfile, une par minute au plus) sont vidées en fin de mission.
{
    addMissionEventHandler [_x, { if (missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) then { [true] call comspec_atak_native_fnc_saveProfile; }; }];
} forEach ["Ended", "MPEnded"];
// Relais des distances : fin de mission côté serveur seulement (MPEnded), sinon perdu jusqu'à 10 min de compteurs.
addMissionEventHandler ["MPEnded", { ["selfFlush"] call comspec_atak_native_fnc_distanceServer; }];
// Quitter la partie (Échap > Quitter) ne déclenche pas Ended : l'écran de jeu qui se ferme vide aussi l'écriture
// (profil et relais des distances, l'équivalent du départ d'un joueur côté serveur).
[{ !isNull (findDisplay 46) }, {
    (findDisplay 46) displayAddEventHandler ["Unload", {
        ["selfFlush"] call comspec_atak_native_fnc_distanceServer;
        if (missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) then { [true] call comspec_atak_native_fnc_saveProfile; };
    }];
}] call CBA_fnc_waitUntilAndExecute;
// Mesures pour le journal ([PERF]) : durée de l'arrivée (preInit -> première image de jeu) et images figées
// (plus de 400 ms) avec ce qui était ouvert, pour retrouver ce qui fait ramer en jeu.
[{ time > 0 && {!isNull (findDisplay 46)} }, {
    diag_log format ["[COMSPEC ATAK NATIVE][PERF] Arrivée en jeu : %1 ms depuis le preInit", round ((diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PerfPreInitAt", diag_tickTime])) * 1000)];
    [{
        private _dt = diag_deltaTime;
        // Images lentes (plus de 50 ms, moins de 20 img/s) : comptées pour le bilan des 30 s (fn_perfAcc).
        if (_dt > 0.05) then {
            private _sl = missionNamespace getVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]];
            missionNamespace setVariable ["COMSPEC_ATAK_PerfSlow", [(_sl select 0) + 1, (_sl select 1) max _dt]];
        };
        if (_dt < 0.4) exitWith {};
        private _arsenal = !isNull (uiNamespace getVariable ["ace_arsenal_display", displayNull]) || {!isNull (uiNamespace getVariable ["BIS_fnc_arsenal_display", displayNull])};
        diag_log format ["[COMSPEC ATAK NATIVE][PERF] Image figée : %1 ms (arsenal %2, téléphone %3, carte %4, drone %5, cibles marquées %6, page %7)", round (_dt * 1000), ["non", "ouvert"] select _arsenal, ["rangé", "affiché"] select !isNull ([] call comspec_atak_native_fnc_display), ["non", "ouverte"] select visibleMap,
            ["aucun", "appairé"] select !isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]), count (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]),
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]];
    }, 0] call comspec_atak_native_fnc_pfhSpread;
    // Bilan des boucles du téléphone (drone, marquage, carte, pages) et des images lentes, toutes les 30 s.
    [{ ["report"] call comspec_atak_native_fnc_perfAcc; }, 30] call comspec_atak_native_fnc_pfhSpread;
}] call CBA_fnc_waitUntilAndExecute;
// Canaux de la messagerie : liste complète d'Athena (canaux web compris) relue dès la liaison prête, sans attendre la
// première ouverture de Messages (qui n'affichait d'abord que les canaux par défaut, les autres 20 s plus tard).
// Nouvel essai toutes les 3 s tant qu'Athena n'a rien rendu, puis relecture toutes les 60 s.
[{ (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) && {!isNil "comspec_overwatch_connect_fnc_pollChatChannels"} }, {
    [] spawn {
        private _ok = false;
        for "_i" from 1 to 40 do {
            if ([] call comspec_overwatch_connect_fnc_pollChatChannels) exitWith { _ok = true; };
            sleep 3;
        };
        diag_log format ["[COMSPEC ATAK NATIVE][CHAT] Canaux Athena : %1", [count (missionNamespace getVariable ["COMSPEC_Comms_Channels", []]), "non reçus"] select !_ok];
        uiNamespace setVariable ["COMSPEC_ATAK_ChatChannelsAt", diag_tickTime];
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then {
            [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
}] call CBA_fnc_waitUntilAndExecute;
// Arsenal ACE ouvert : repère dans le journal (l'ouverture elle-même apparaît en « Image figée … arsenal ouvert »).
// Touches COMSPEC traduites une fois toutes déclarées (ATAK natif ici, Overwatch et SSE dans leurs propres init) :
// image suivante, puis 5 s plus tard pour les addons déclarés après.
[{ [] call comspec_atak_native_fnc_keyRelang; }] call CBA_fnc_execNextFrame;
[{ [] call comspec_atak_native_fnc_keyRelang; }, [], 5] call CBA_fnc_waitAndExecute;
["ace_arsenal_displayOpened", { diag_log format ["[COMSPEC ATAK NATIVE][PERF] Arsenal ACE ouvert (%1 FPS)", round diag_fps]; }] call CBA_fnc_addEventHandler;
diag_log format ["[COMSPEC ATAK NATIVE][PERF] %1 : %2 ms", "postInit client", round ((diag_tickTime - _perfT0) * 1000)];
