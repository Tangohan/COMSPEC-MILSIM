if (!hasInterface) exitWith {};
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] Client PostInit complete";
missionNamespace setVariable ["COMSPEC_ATAK_LegacyBootstrapSuppressed", true, false];
// Avec COMSPEC Link, sa DLL porte la session Athena : on n'ouvre pas une seconde session avec la DLL native.
if ([] call comspec_atak_native_fnc_bridge) then {
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] COMSPEC Link présent : session Athena partagée, DLL native non initialisée";
} else {
    private _athenaUrl = profileNamespace getVariable ["COMSPEC_ATAK_Native_AthenaUrl", "https://athena.ttrd.fr/public"];
    private _extInit = "COMSPECATAKNativeExtension" callExtension ["Init", [_athenaUrl]];
    missionNamespace setVariable ["COMSPEC_ATAK_NativeExtensionInit", _extInit, false];
    private _restore = "COMSPECATAKNativeExtension" callExtension ["RestoreSession", [_athenaUrl, "1.0.0-native"]];
    missionNamespace setVariable ["COMSPEC_ATAK_NativeAuthRestore", _restore, false];
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] COMSPECATAKNativeExtension initialization requested";
};
// Porté : le téléphone reste affiché dans le coin et l'on continue à jouer. En main : souris et clavier.
// Raccourcis (Options > Contrôles > Configurer les addons > COMSPEC ATAK), sur le modèle des interfaces principale / secondaire / tertiaire.
// Les identifiants PhoneHold, PhoneCarry, PhoneZoomIn et PhoneZoomOut sont relus par fn_displayLoad (téléphone en main) ;
// PhoneMain, PhoneOrient et PhonePosition par le gestionnaire de touches ajouté dans fn_open.
["COMSPEC ATAK", "PhoneMain", ["Ouvrir / fermer l'ATAK (principal)", "Sort le téléphone en main (dernier mode : mini ou plein écran) ou le range complètement."], { [] call (missionNamespace getVariable ["COMSPEC_ATAK_KeyMain", {}]); true }, "", [0x16, [false, false, true]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneCarry", ["Porter l'ATAK en miniature (secondaire)", "Affiche ou range le téléphone dans un coin de l'écran : on continue à jouer."], { [] call comspec_atak_native_fnc_hudToggle; true }, "", [0x16, [false,true,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneHold", ["Prendre l'ATAK en main (tertiaire)", "Prend ou relâche la souris sur le téléphone ; relâché, il reste affiché en miniature."], { [] call comspec_atak_native_fnc_interactToggle; true }, "", [0x16, [true,true,false]]] call CBA_fnc_addKeybind;
// Zoom de la carte sans prendre le téléphone en main (aussi en marchant ou en conduisant).
["COMSPEC ATAK", "PhoneZoomIn", ["Zoomer (carte du téléphone)", "Zoom avant sur la carte, aussi téléphone porté en marchant ou en conduisant."], { if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false }; [0.7] call comspec_atak_native_fnc_mapZoom; true }, "", [0xC9, [false, true, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneZoomOut", ["Dézoomer (carte du téléphone)", "Zoom arrière sur la carte, aussi téléphone porté."], { if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false }; [1 / 0.7] call comspec_atak_native_fnc_mapZoom; true }, "", [0xD1, [false, true, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneOrient", ["Permuter l'orientation (vertical / horizontal)", "Téléphone en miniature : vertical ou horizontal."], { if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { false }; [] call comspec_atak_native_fnc_orientationToggle; true }, "", [0x16, [false, true, true]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhonePosition", ["Permuter la position de l'interface", "Déplace la miniature : bas droit, bas gauche, haut gauche, haut droit, milieu droit, milieu gauche."], { [] call (missionNamespace getVariable ["COMSPEC_ATAK_KeyPosition", {}]); true }, "", [0x16, [true, false, true]]] call CBA_fnc_addKeybind;
// Principal : rangé -> en main ; affiché (porté ou en main) -> rangé complètement.
missionNamespace setVariable ["COMSPEC_ATAK_KeyMain", {
    if (isNull ([] call comspec_atak_native_fnc_display)) then {
        private _why = [] call comspec_atak_native_fnc_canUse;
        if (_why isNotEqualTo "") exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
        [true] call comspec_atak_native_fnc_open;
    } else {
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
        [] call comspec_atak_native_fnc_close;
    };
}];
// Position de la miniature : coin suivant (réglage profil COMSPEC_ATAK_MiniAnchor, aussi dans Réglages).
missionNamespace setVariable ["COMSPEC_ATAK_KeyPosition", {
    private _list = ["BR", "BL", "TL", "TR", "MR", "ML"];
    private _cur = _list find toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
    private _next = _list select ((_cur max 0) + 1) mod (count _list);
    profileNamespace setVariable ["COMSPEC_ATAK_MiniAnchor", _next];
    saveProfileNamespace;
    if (!isNull ([] call comspec_atak_native_fnc_display)) then {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
    };
}];
// Menu d'actions d'Arma masqué pendant que le téléphone est affiché (réglage « Masquer le menu d'actions ») :
// molette (PrevAction / NextAction) bloquée ; validation (Action) bloquée seulement téléphone en main.
// Téléphone rangé : le gestionnaire renvoie false, le jeu (et ACE) se comporte normalement. Réinstallé à chaque ouverture (fn_open).
missionNamespace setVariable ["COMSPEC_ATAK_ActionBlock", {
    params [["_activate", false]];
    private _mode = missionNamespace getVariable ["comspec_atak_native_action_menu", 2];
    if (_mode isEqualTo 0 || {isNull ([] call comspec_atak_native_fnc_display)}) exitWith { false };
    private _inHand = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false];
    if (_activate) exitWith { _inHand };
    _inHand || {_mode isEqualTo 2}
}];
["COMSPEC ATAK", "PhonePanic", "Bouton PANIQUE (deux appuis)", { if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false }; ["panic"] call comspec_atak_native_fnc_alertsAction; if (diag_tickTime < ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["panicArmedUntil", -1])) then { ["WARNING", "PANIQUE : appuyez encore pour envoyer", 5, 60] call comspec_atak_native_fnc_notify; }; true }, "", [0, [false,false,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneNight", "Mode nuit du téléphone (normal / rouge / sombre)", {
    private _m = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
    private _n = ["OFF", "RED", "DIM"] select ((((["OFF", "RED", "DIM"] find _m) max 0) + 1) mod 3);
    profileNamespace setVariable ["COMSPEC_ATAK_NightMode", _n];
    ["INFO", format ["Mode nuit : %1", ["normal", "filtre rouge", "écran sombre"] select (["OFF", "RED", "DIM"] find _n)], 2, 10] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_deviceOverlay;
    true
}, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneSilent", "Mode discrétion (sons coupés)", {
    private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]);
    profileNamespace setVariable ["COMSPEC_ATAK_Silent", _on];
    ["INFO", ["Mode discrétion désactivé", "Mode discrétion : aucun son"] select _on, 2, 10] call comspec_atak_native_fnc_notify;
    true
}, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
// Batterie : consommation aussi téléphone rangé (la barre d'état la met à jour chaque seconde quand il est ouvert).
[{ if (isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_battery; }; }, 10] call CBA_fnc_addPerFrameHandler;
["comspec_atak_native_p2p", { _this call comspec_atak_native_fnc_p2pReceive }] call CBA_fnc_addEventHandler;
["comspec_atak_native_p2pAck", { _this call comspec_atak_native_fnc_p2pAck }] call CBA_fnc_addEventHandler;
// MEDEVAC du camp : demandes et suivi (app Médical, onglet MEDEVAC).
["comspec_atak_native_medevac", { ["recv", _this] call comspec_atak_native_fnc_medicalAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_bda", { ["recv", _this] call comspec_atak_native_fnc_bdaAction; }] call CBA_fnc_addEventHandler;
// Comptes rendus du camp (app Comptes rendus) : tous les types, rejoués par la synchro serveur.
["comspec_atak_native_report", { ["recv", _this] call comspec_atak_native_fnc_reportAction; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevacStatus", { ["statusRecv", _this] call comspec_atak_native_fnc_medicalAction; }] call CBA_fnc_addEventHandler;
private _eh = addMissionEventHandler ["ExtensionCallback", { _this call comspec_atak_native_fnc_extensionCallback }];
missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", _eh, false];
["COMSPEC_AthenaLinkChanged", { params ["_state"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["networkState", toUpper _state]; }] call CBA_fnc_addEventHandler;

// COMSPEC Link (déjà installé chez les joueurs) n'ouvre ses boucles de synchro que s'il voit « son » terminal.
// Tant que sa version ne délègue pas au natif, on lui indique ici si le téléphone natif est autorisé.
[{
    if !([] call comspec_atak_native_fnc_bridge) exitWith {};
    private _allowed = [player] call comspec_atak_native_fnc_hasDevice;
    if ((missionNamespace getVariable ["comspec_overwatch_require_item", true]) isEqualTo _allowed) then {
        missionNamespace setVariable ["comspec_overwatch_require_item", !_allowed];
    };
}, 2] call CBA_fnc_addPerFrameHandler;

// Réglages de réalisme de la communauté (Athena) : appliqués à tous dès qu'ils changent.
[{
    private _raw = missionNamespace getVariable ["COMSPEC_TenantExperienceRaw", ""];
    if (_raw isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_TenantApplied", "-"])) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_TenantApplied", _raw];
    [] call comspec_atak_native_fnc_tenantApply;
    ["INFO", "TENANT", "Réglages communauté appliqués"] call comspec_atak_native_fnc_log;
}, 5] call CBA_fnc_addPerFrameHandler;

// Retour d'Athena sur les photos envoyées (via COMSPEC Link) : reçue ou refusée.
[{
    private _up = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]);
    private _ko = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]);
    (missionNamespace getVariable ["COMSPEC_ATAK_PhotoSeen", [_up, _ko]]) params ["_up0", "_ko0"];
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoSeen", [_up, _ko]];
    if ((_up != _up0 || {_ko != _ko0}) && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PHOTOS"}) then { ["PHOTOS"] call comspec_atak_native_fnc_pageRender; };
    if (_up > _up0) then { ["SUCCESS", format ["%1 photo(s) reçue(s) par Athena", _up - _up0], 4, 30] call comspec_atak_native_fnc_notify; };
    if (_ko > _ko0) then { ["WARNING", format ["Photo refusée par Athena : %1", (missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []]) param [1, "voir l'app Photos"]], 6, 40] call comspec_atak_native_fnc_notify; };
}, 2] call CBA_fnc_addPerFrameHandler;

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
    if ((player getVariable ["COMSPEC_ATAK_Pub", []]) isNotEqualTo _pub) then { player setVariable ["COMSPEC_ATAK_Pub", _pub, true]; };
}, 5] call CBA_fnc_addPerFrameHandler;

// Tous les marqueurs de la carte vers le web (relais unique par camp).
[{ [] call comspec_atak_native_fnc_markerWebSweep; }, 15] call CBA_fnc_addPerFrameHandler;

// Heatmap : toutes les 20 s, chaque ennemi repéré par mon camp chauffe sa case de 200 m ; tout refroidit de 4 %.
[{
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    private _heat = missionNamespace getVariable ["COMSPEC_ATAK_Heat", createHashMap];
    { _y set [2, (_y select 2) * 0.96]; } forEach _heat;
    private _cold = (keys _heat) select { ((_heat get _x) select 2) < 0.2 };
    { _heat deleteAt _x; } forEach _cold;
    private _mySide = side group player;
    {
        if (alive _x && {(side group _x) isNotEqualTo _mySide} && {(side group _x) isNotEqualTo civilian} && {(_mySide knowsAbout _x) >= 1.5}) then {
            private _p = getPosATL _x;
            private _cx = (floor ((_p select 0) / 200)) * 200 + 100;
            private _cy = (floor ((_p select 1) / 200)) * 200 + 100;
            private _key = format ["%1_%2", _cx, _cy];
            private _c = _heat getOrDefault [_key, [_cx, _cy, 0]];
            _c set [2, (_c select 2) + 1];
            _heat set [_key, _c];
        };
    } forEach allUnits;
    missionNamespace setVariable ["COMSPEC_ATAK_Heat", _heat];
}, 20] call CBA_fnc_addPerFrameHandler;

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
            case (lifeState _x isEqualTo "INCAPACITATED"): { "DOWN" };
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
}, 2] call CBA_fnc_addPerFrameHandler;

// Vibreur BFT : un allié fait vibrer mon téléphone (signal discret de ralliement).
// GÉOLOC subie (réglage « Prévenir la cible ») : alerte discrète, une fois par minute au plus.
["comspec_atak_native_geoWarn", {
    if (diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_GeoWarnNext", 0])) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_GeoWarnNext", diag_tickTime + 60];
    ["WARNING", "Activité réseau anormale sur votre téléphone", 5, 40] call comspec_atak_native_fnc_notify;
}] call CBA_fnc_addEventHandler;
["comspec_atak_native_buzz", {
    params ["_from", "_grid"];
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"]) exitWith {};
    ["WARNING", format ["VIBREUR · %1 vous appelle · %2", _from, _grid], 8, 70] call comspec_atak_native_fnc_notify;
    private _buzz = {
        if (isNull ([] call comspec_atak_native_fnc_display) && {!(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false])} && {!isNil "comspec_overwatch_connect_fnc_playAtakVibrate"}) then { [0.9] call comspec_overwatch_connect_fnc_playAtakVibrate; } else { [] call comspec_atak_native_fnc_vibrate; };
    };
    [] call _buzz;
    [_buzz, [], 0.9] call CBA_fnc_waitAndExecute;
    [_buzz, [], 1.8] call CBA_fnc_waitAndExecute;
}] call CBA_fnc_addEventHandler;

// PANIQUE d'un allié : alerte rouge, vibration, repère local sur la carte pendant 10 min.
["comspec_atak_native_panic", {
    params ["_who", "_pos", "_grid"];
    if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
    ["WARNING", format ["PANIQUE · %1 · %2", _who, _grid], 12, 90] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    private _m = createMarkerLocal [format ["COMSPEC_PANIC_%1_%2", _who, round diag_tickTime], _pos];
    _m setMarkerTypeLocal "mil_warning";
    _m setMarkerColorLocal "ColorRed";
    _m setMarkerTextLocal format ["PANIQUE %1", _who];
    [{ deleteMarkerLocal _this; }, _m, 600] call CBA_fnc_waitAndExecute;
    ["PANIC", _who, _pos, _grid, "En détresse"] call comspec_atak_native_fnc_alertsLog;
}] call CBA_fnc_addEventHandler;

// Alertes rapides et SALUTE d'un allié (aussi sans Athena) : notification, vibreur, journal de l'app Alertes, repère 10 min.
["comspec_atak_native_alert", {
    params ["_type", "_who", "_pos", "_grid", ["_text", ""], ["_side", ""]];
    if (_side isNotEqualTo str side group player || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
    private _label = createHashMapFromArray [["TIC", "CONTACT"], ["TIC_CLEAR", "FIN DE CONTACT"], ["EAGLE_DOWN", "APPAREIL ABATTU"], ["SALUTE", "SALUTE"]] getOrDefault [_type, _type];
    ["TACTICAL", format ["%1 · %2 · %3", _label, _who, _grid], 8, 70] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    private _m = createMarkerLocal [format ["COMSPEC_ALERT_%1_%2", _type, round (diag_tickTime * 10)], _pos];
    _m setMarkerTypeLocal (createHashMapFromArray [["TIC", "mil_warning"], ["TIC_CLEAR", "mil_flag"], ["EAGLE_DOWN", "mil_destroy"]] getOrDefault [_type, "mil_unknown"]);
    _m setMarkerColorLocal (createHashMapFromArray [["TIC", "ColorOrange"], ["TIC_CLEAR", "ColorGreen"], ["EAGLE_DOWN", "ColorRed"]] getOrDefault [_type, "ColorYellow"]);
    _m setMarkerTextLocal format ["%1 %2", _label, _who];
    [{ deleteMarkerLocal _this; }, _m, 600] call CBA_fnc_waitAndExecute;
    [_type, _who, _pos, _grid, _text] call comspec_atak_native_fnc_alertsLog;
}] call CBA_fnc_addEventHandler;

// Goniométrie : émetteurs estimés par Athena, relus toutes les 15 s quand la couche est active.
[{ if (profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true]) then { [] spawn comspec_atak_native_fnc_sigintPoll; }; }, 15] call CBA_fnc_addPerFrameHandler;

// Action ACE : changer la batterie du téléphone (si une batterie de rechange est portée).
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _act = ["COMSPEC_ATAK_BatterySwap", "Changer la batterie ATAK", "\z\comspec_atak_native\addons\main\data\item_battery.paa", {
        if (isNil "ace_common_fnc_progressBar") exitWith { [] call comspec_atak_native_fnc_batterySwap; };
        [5, [], { [] call comspec_atak_native_fnc_batterySwap; }, {}, "Changement de la batterie…"] call ace_common_fnc_progressBar;
    }, {
        [player] call comspec_atak_native_fnc_hasDevice && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) < 95}
        && {([player] call comspec_atak_native_fnc_batteryItem) isNotEqualTo ""}
    }] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment"], _act, true] call ace_interact_menu_fnc_addActionToClass;
} else {
    // Sans ACE : action de la molette (visible seulement avec une batterie de rechange et le téléphone sous 95 %).
    player addAction ["<t color='#7fd0b0'>Changer la batterie ATAK</t>", { [] call comspec_atak_native_fnc_batterySwap; }, nil, 1, false, true, "",
        "_this isEqualTo _target && {[_this] call comspec_atak_native_fnc_hasDevice} && {(missionNamespace getVariable ['COMSPEC_ATAK_Battery', 100]) < 95} && {([_this] call comspec_atak_native_fnc_batteryItem) isNotEqualTo ''}"];
};

// Mission de tir reçue (servant d'une pièce) : notification, vibration, cible sur la carte du téléphone.
["comspec_atak_native_fireMission", {
    params ["_summary", "_tgt", "_from"];
    private _f = [] call comspec_atak_native_fnc_firesState;
    _f set ["target", _tgt];
    private _log = _f getOrDefault ["log", []];
    _log pushBack format ["<t color='#f2ab33'>%1</t> REÇU de %2 · %3", [dayTime, "HH:MM"] call BIS_fnc_timeToString, _from, _summary];
    _f set ["log", _log];
    ["WARNING", format ["MISSION DE TIR : %1", _summary], 8, 80] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
}] call CBA_fnc_addEventHandler;

// Briefing : la page suit la diapositive en cours (deck Google du présentateur, image chargée, liste Athena).
[{
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "BRIEFING" || {(_s getOrDefault ["briefTab", "SLIDES"]) isEqualTo "MISSION"}) exitWith {};
    if (isNull ([] call comspec_atak_native_fnc_display)) exitWith {};
    private _sig = (([] call comspec_atak_native_fnc_briefingSignature) select [0, 4]) + [["get"] call comspec_atak_native_fnc_briefingLive, count (["attendees"] call comspec_atak_native_fnc_briefingLive)];
    if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BriefSig", []])) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_BriefSig", _sig];
    ["BRIEFING"] call comspec_atak_native_fnc_pageRender;
}, 1] call CBA_fnc_addPerFrameHandler;

// Question posée pendant ma présentation : alerte sur mon téléphone.
["comspec_atak_native_briefQ", {
    params ["_who", "_txt"];
    ["MESSAGE", format ["Question de %1 : %2", _who, [_txt, (_txt select [0, 80]) + "…"] select ((count _txt) > 80)], 8, 50] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    private _q = uiNamespace getVariable ["COMSPEC_ATAK_BriefQ", createHashMap];
    _q set ["slide", -2];
}] call CBA_fnc_addEventHandler;

// Briefing présenté en direct : suivre le présentateur de mon camp, signaler ma présence.
[{ ["tick"] call comspec_atak_native_fnc_briefingLive; }, 2] call CBA_fnc_addPerFrameHandler;

// App Musique : son du lecteur et des haut-parleurs voisins ; coupé en quittant la partie (la DLL jouerait encore au menu).
[{ [] call comspec_atak_native_fnc_musicTick; }, 0.5] call CBA_fnc_addPerFrameHandler;
addMissionEventHandler ["Ended", { ["MusicStop"] call comspec_atak_native_fnc_extensionCall; }];
[{ !isNull (findDisplay 46) }, { (findDisplay 46) displayAddEventHandler ["Unload", { ["MusicStop"] call comspec_atak_native_fnc_extensionCall; }]; }] call CBA_fnc_waitUntilAndExecute;

// Live cam partagé vers COMSPEC Overwatch : une image toutes les N s si le joueur l'a activé.
[{ [] call comspec_atak_native_fnc_livecamShare; }, 2] call CBA_fnc_addPerFrameHandler;

// Détecteur de drones : balayage automatique en fond (réglage de l'app).
[{ if (profileNamespace getVariable ["COMSPEC_ATAK_DroneAuto", false]) then { ["auto"] call comspec_atak_native_fnc_droneDetectScan; }; }, 5] call CBA_fnc_addPerFrameHandler;
// Drones pilotés au téléphone : terminal UAV interdit sauf au pilote en mode manuel (COMSPEC_DroneLock).
// Appliqué toutes les 0,5 s pour primer sur les mods qui réactivent la connexion (Mavic).
[{
    private _blocked = missionNamespace getVariable ["COMSPEC_ATAK_UavBlocked", []];
    private _uid = getPlayerUID player;
    {
        private _lock = _x getVariable "COMSPEC_DroneLock";
        if (!isNil "_lock" && {_lock isNotEqualTo _uid}) then {
            player disableUAVConnectability [_x, true];
            if ((getConnectedUAV player) isEqualTo _x) then {
                player connectTerminalToUAV objNull;
                ["WARNING", "Drone piloté depuis un téléphone : terminal déconnecté", 4, 40] call comspec_atak_native_fnc_notify;
            };
            _blocked pushBackUnique _x;
        };
    } forEach allUnitsUAV;
    // Verrou levé ou rendu à ce joueur : connexion rétablie une fois.
    {
        private _lock = _x getVariable "COMSPEC_DroneLock";
        if (isNull _x || {isNil "_lock"} || {_lock isEqualTo _uid}) then { if (!isNull _x) then { player enableUAVConnectability [_x, true]; }; _blocked set [_forEachIndex, objNull]; };
    } forEach _blocked;
    missionNamespace setVariable ["COMSPEC_ATAK_UavBlocked", _blocked - [objNull]];
}, 0.5] call CBA_fnc_addPerFrameHandler;
// Rejeu de mission : une image toutes les 10 s, et les pertes amies avec leur position.
[{ [] call comspec_atak_native_fnc_aarRecord; }, 10] call CBA_fnc_addPerFrameHandler;
addMissionEventHandler ["EntityKilled", {
    params ["_unit"];
    if (!(_unit isKindOf "CAManBase") || {!(missionNamespace getVariable ["comspec_atak_native_aar", true])}) exitWith {};
    if ((side group _unit) isNotEqualTo (side group player)) exitWith {};
    private _ev = missionNamespace getVariable ["COMSPEC_ATAK_AarEvents", []];
    private _p = getPosASL _unit;
    _ev pushBack [time, round (_p select 0), round (_p select 1), name _unit];
    if ((count _ev) > 300) then { _ev deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_AarEvents", _ev];
}];

// Débit simulé : la file d'envoi part dès que le réseau revient, et les SMS restés chez l'opérateur arrivent.
[{
    private _inbox = missionNamespace getVariable ["COMSPEC_ATAK_P2pInbox", []];
    if ((count _inbox) > 0 && {[] call comspec_atak_native_fnc_p2pReachable}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_P2pInbox", []];
        { (_x + [true]) call comspec_atak_native_fnc_p2pReceive; } forEach _inbox;
    };
    private _queue = missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    if ((count _queue) isEqualTo 0) exitWith {};
    if ((([] call comspec_atak_native_fnc_linkQuality) get "bars") isEqualTo 0) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", []];
    ["SUCCESS", format ["Réseau revenu : %1 envoi(s) en attente partent", count _queue], 4, 30] call comspec_atak_native_fnc_notify;
    { _x call comspec_atak_native_fnc_netSend; } forEach _queue;
}, 2] call CBA_fnc_addPerFrameHandler;

// Dégâts du téléphone : balles (surtout torse et bras, où il est porté), explosions, eau.
if (isClass (configFile >> "CfgPatches" >> "ace_medical_engine")) then {
    ["ace_medical_woundReceived", {
        params ["_unit", ["_damages", []]];
        if (_unit isNotEqualTo player) exitWith {};
        {
            _x params [["_d", 0], ["_part", ""]];
            private _carry = (toLower _part) in ["body", "leftarm", "rightarm"];
            if (_d > 0.05 && {random 1 < ([0.08, 0.45] select _carry)}) then {
                [(_d * 0.6) min 0.6, "Impact", [0, 15] select (random 1 < 0.35)] call comspec_atak_native_fnc_deviceDamage;
            };
        } forEach _damages;
    }] call CBA_fnc_addEventHandler;
} else {
    player addEventHandler ["Hit", { params ["_unit", "", "_d"]; if (_d > 0.05 && {random 1 < 0.3}) then { [(_d * 0.6) min 0.6, "Impact", [0, 15] select (random 1 < 0.35)] call comspec_atak_native_fnc_deviceDamage; }; }];
};
player addEventHandler ["Explosion", { params ["", "_d"]; if (_d > 0.03 && {random 1 < 0.6}) then { [(_d * 1.5) min 0.7, "Explosion", [0, 20] select (random 1 < 0.5)] call comspec_atak_native_fnc_deviceDamage; }; }];
player addEventHandler ["Respawn", { missionNamespace setVariable ["COMSPEC_ATAK_Device", createHashMap]; missionNamespace setVariable ["COMSPEC_ATAK_Battery", 100]; }];
[{
    if (alive player && {((eyePos player) select 2) < -0.2} && {(vehicle player) isEqualTo player}) then { [0.08, "Téléphone noyé", 30] call comspec_atak_native_fnc_deviceDamage; };
}, 3] call CBA_fnc_addPerFrameHandler;

// Actions ACE : réparer l'écran (trousse à outils) ou passer sur un téléphone de rechange.
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _fix = ["COMSPEC_ATAK_Repair", "Réparer le téléphone ATAK", "", {
        private _go = { [{ ["repair"] call comspec_atak_native_fnc_deviceRepair; }] call CBA_fnc_execNextFrame; };
        if (isNil "ace_common_fnc_progressBar") exitWith { [] call _go; };
        [15, [], { ["repair"] call comspec_atak_native_fnc_deviceRepair; }, {}, "Réparation du téléphone…"] call ace_common_fnc_progressBar;
    }, {
        ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["damage", 0]) > 0
        && {(((items player) apply { toLower _x }) findIf { _x in ["toolkit", "ace_toolkit"] }) >= 0}
    }] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment"], _fix, true] call ace_interact_menu_fnc_addActionToClass;
    private _swap = ["COMSPEC_ATAK_Swap", "Changer de téléphone ATAK", "", {
        private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
        private _spare = (items player) select { (toLower _x) in _cat };
        if ((count _spare) > 0) then { player removeItem (_spare select 0); ["swap"] call comspec_atak_native_fnc_deviceRepair; };
    }, {
        ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["damage", 0]) >= 0.45
        && {private _cat = [] call comspec_atak_native_fnc_deviceCatalog; ((((assignedItems player) + (items player)) select { (toLower _x) in _cat }) param [1, ""]) isNotEqualTo "" && {((items player) findIf { (toLower _x) in _cat }) >= 0}}
    }] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment"], _swap, true] call ace_interact_menu_fnc_addActionToClass;
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
        [4, [_t], { ["pair", (_this select 0) select 0] call comspec_atak_native_fnc_droneAction; }, {}, "Appairage du drone…"] call ace_common_fnc_progressBar;
    }, { [_target] call (missionNamespace getVariable ["COMSPEC_ATAK_DroneCan", { false }]) }] call ace_interact_menu_fnc_createAction;
    ["Air", 0, ["ACE_MainActions"], _con, true] call ace_interact_menu_fnc_addActionToClass;
    private _dis = ["COMSPEC_ATAK_DroneUnpair", "Déconnecter l'ATAK du drone", "", { ["unpair"] call comspec_atak_native_fnc_droneAction; },
        { [_target] call (missionNamespace getVariable ["COMSPEC_ATAK_DroneIsMine", { false }]) }] call ace_interact_menu_fnc_createAction;
    ["Air", 0, ["ACE_MainActions"], _dis, true] call ace_interact_menu_fnc_addActionToClass;
};
// Molette : les actions sont posées sur chaque drone proche, une seule fois par drone.
[{
    if (isNull player || {!alive player}) exitWith {};
    {
        if (unitIsUAV _x && {!(_x getVariable ["COMSPEC_ATAK_DroneActs", false])}) then {
            _x setVariable ["COMSPEC_ATAK_DroneActs", true];
            _x addAction ["<t color='#7fd0b0'>Connecter l'ATAK au drone</t>", { ["pair", _this select 0] call comspec_atak_native_fnc_droneAction; }, nil, 5, false, true, "",
                "[_target] call (missionNamespace getVariable ['COMSPEC_ATAK_DroneCan', { false }])", 4];
            _x addAction ["<t color='#e0a050'>Déconnecter l'ATAK du drone</t>", { ["unpair"] call comspec_atak_native_fnc_droneAction; }, nil, 5, false, true, "",
                "[_target] call (missionNamespace getVariable ['COMSPEC_ATAK_DroneIsMine', { false }])", 4];
        };
    } forEach (player nearEntities [["Air", "LandVehicle"], 15]);
}, 2] call CBA_fnc_addPerFrameHandler;

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
[{ [] call comspec_atak_native_fnc_squadSync; }, 30] call CBA_fnc_addPerFrameHandler;
// Temps d'écran et temps par rôle (envoi à Athena toutes les 5 min et en fin de mission).
[{ ["tick"] call comspec_atak_native_fnc_screenTime; }, 5] call CBA_fnc_addPerFrameHandler;
addMissionEventHandler ["Ended", { ["flush"] call comspec_atak_native_fnc_screenTime; [true] call comspec_atak_native_fnc_squadSync; }];
// Arrivée en cours de partie : mon équipe de feu d'un ancien groupe ne me suit pas.
[{ ["comspec_atak_native_ftCheck", [player]] call CBA_fnc_serverEvent; }, [], 5] call CBA_fnc_waitAndExecute;

// Tinder : un joueur m'a liké (match si c'est réciproque).
["comspec_atak_native_rencard", {
    params ["_uid", "_name"];
    private _by = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikedBy", []];
    _by pushBackUnique _uid;
    missionNamespace setVariable ["COMSPEC_ATAK_RencardLikedBy", _by];
    if !(player getVariable ["COMSPEC_ATAK_Rencard", true]) exitWith {};
    if (_uid in (missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []])) then {
        ["SUCCESS", format ["Tinder : c'est un match avec %1 !", _name], 6, 50] call comspec_atak_native_fnc_notify;
    } else {
        ["INFO", "Tinder : quelqu'un vous a liké", 4, 20] call comspec_atak_native_fnc_notify;
    };
    [] call comspec_atak_native_fnc_vibrate;
}] call CBA_fnc_addEventHandler;

// Logistique : demandes et statuts du camp ; guerre électronique : brouilleurs partagés.
["comspec_atak_native_logi", { ["recv", _this] call comspec_atak_native_fnc_logisticsAction; }] call CBA_fnc_addEventHandler;
// Décisions du poste web sur les demandes logistiques (mode passerelle).
[{ ["poll"] call comspec_atak_native_fnc_logiWeb; }, 20] call CBA_fnc_addPerFrameHandler;
[{ ["tick"] call comspec_atak_native_fnc_ewAction; }, 5] call CBA_fnc_addPerFrameHandler;

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
}, 1] call CBA_fnc_addPerFrameHandler;

// Points de passage : itinéraire reçu d'un membre du groupe, et passage automatique à l'étape suivante.
["comspec_atak_native_waypoints", { ["receive", _this] call comspec_atak_native_fnc_wpAction; }] call CBA_fnc_addEventHandler;
// Synchro à l'arrivée : le serveur rejoue les demandes logistiques / MEDEVAC du camp et l'itinéraire du groupe (sans alerte).
["comspec_atak_native_syncData", {
    params [["_log", []], ["_route", []]];
    missionNamespace setVariable ["COMSPEC_ATAK_Replaying", true];
    { _x params ["_ev", "_args"]; [_ev, _args] call CBA_fnc_localEvent; } forEach _log;
    if ((count _route) >= 2 && {(count ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []])) isEqualTo 0}) then {
        ["receive", [_route select 0, _route select 1, false]] call comspec_atak_native_fnc_wpAction;
    };
    missionNamespace setVariable ["COMSPEC_ATAK_Replaying", false];
    ["INFO", "SYNC", format ["Synchro serveur : %1 événement(s) rejoué(s)", count _log]] call comspec_atak_native_fnc_log;
}] call CBA_fnc_addEventHandler;
[{ ["comspec_atak_native_syncReq", [player]] call CBA_fnc_serverEvent; }, [], 5] call CBA_fnc_waitAndExecute;
[{ if ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["nav", false]) then { ["tick"] call comspec_atak_native_fnc_wpAction; }; }, 1] call CBA_fnc_addPerFrameHandler;
// Breacher : top de brèche reçu du chef de colonne.
["comspec_atak_native_breach", { _this call comspec_atak_native_fnc_breachTop; }] call CBA_fnc_addEventHandler;
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
