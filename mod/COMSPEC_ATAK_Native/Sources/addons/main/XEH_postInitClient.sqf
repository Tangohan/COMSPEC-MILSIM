if (!hasInterface) exitWith {};
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] Client PostInit complete";
missionNamespace setVariable ["COMSPEC_ATAK_LegacyBootstrapSuppressed", true, false];
// Avec Overwatch connect, sa DLL porte la session Athena : on n'ouvre pas une seconde session avec la DLL native.
if ([] call comspec_atak_native_fnc_bridge) then {
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] Overwatch connect présent : session Athena partagée, DLL native non initialisée";
} else {
    private _athenaUrl = profileNamespace getVariable ["COMSPEC_ATAK_Native_AthenaUrl", "https://athena.ttrd.fr/public"];
    private _extInit = "COMSPECATAKNativeExtension" callExtension ["Init", [_athenaUrl]];
    missionNamespace setVariable ["COMSPEC_ATAK_NativeExtensionInit", _extInit, false];
    private _restore = "COMSPECATAKNativeExtension" callExtension ["RestoreSession", [_athenaUrl, "1.0.0-native"]];
    missionNamespace setVariable ["COMSPEC_ATAK_NativeAuthRestore", _restore, false];
    diag_log "[COMSPEC ATAK NATIVE][INFO][EXT] COMSPECATAKNativeExtension initialization requested";
};
// Porté : le téléphone reste affiché dans le coin et l'on continue à jouer. En main : souris et clavier.
["COMSPEC ATAK", "PhoneCarry", "Sortir / ranger le téléphone (porté)", { [] call comspec_atak_native_fnc_hudToggle; true }, "", [0x16, [false,true,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhoneHold", "Prendre en main / reposer le téléphone", { [] call comspec_atak_native_fnc_interactToggle; true }, "", [0x16, [true,true,false]]] call CBA_fnc_addKeybind;
["COMSPEC ATAK", "PhonePanic", "Bouton PANIQUE (deux appuis)", { if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false }; ["panic"] call comspec_atak_native_fnc_alertsAction; if (diag_tickTime < ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["panicArmedUntil", -1])) then { ["WARNING", "PANIQUE : appuyez encore pour envoyer", 5, 60] call comspec_atak_native_fnc_notify; }; true }, "", [0, [false,false,false]]] call CBA_fnc_addKeybind;
["comspec_atak_native_p2p", { _this call comspec_atak_native_fnc_p2pReceive }] call CBA_fnc_addEventHandler;
private _eh = addMissionEventHandler ["ExtensionCallback", { _this call comspec_atak_native_fnc_extensionCallback }];
missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", _eh, false];
["COMSPEC_AthenaLinkChanged", { params ["_state"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["networkState", toUpper _state]; }] call CBA_fnc_addEventHandler;

// Overwatch connect (déjà installé chez les joueurs) n'ouvre ses boucles de synchro que s'il voit « son » terminal.
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

// Retour d'Athena sur les photos envoyées (via Overwatch connect) : reçue ou refusée.
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
    private _icon = profileNamespace getVariable ["COMSPEC_ATAK_SelfIcon", ""];
    if ((player getVariable ["COMSPEC_ATAK_Icon", ""]) isNotEqualTo _icon) then { player setVariable ["COMSPEC_ATAK_Icon", _icon, true]; };
}, 5] call CBA_fnc_addPerFrameHandler;

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
}] call CBA_fnc_addEventHandler;

// Goniométrie : émetteurs estimés par Athena, relus toutes les 15 s quand la couche est active.
[{ if (profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true]) then { [] spawn comspec_atak_native_fnc_sigintPoll; }; }, 15] call CBA_fnc_addPerFrameHandler;

// Action ACE : changer la batterie du téléphone (si une batterie de rechange est portée).
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _act = ["COMSPEC_ATAK_BatterySwap", "Changer la batterie ATAK", "", { [] call comspec_atak_native_fnc_batterySwap; }, {
        [player] call comspec_atak_native_fnc_hasDevice && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) < 95}
        && {((items player) findIf { (toLower _x) in (((missionNamespace getVariable ["comspec_atak_native_battery_items", "ACE_UAVBattery"]) splitString ", ") apply { toLower _x }) }) >= 0}
    }] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment"], _act, true] call ace_interact_menu_fnc_addActionToClass;
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
    private _sig = ([] call comspec_atak_native_fnc_briefingSignature) select [0, 4];
    if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BriefSig", []])) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_BriefSig", _sig];
    ["BRIEFING"] call comspec_atak_native_fnc_pageRender;
}, 1] call CBA_fnc_addPerFrameHandler;

// Live cam partagé vers Overwatch beta : une image toutes les N s si le joueur l'a activé.
[{ [] call comspec_atak_native_fnc_livecamShare; }, 2] call CBA_fnc_addPerFrameHandler;

// Débit simulé : la file d'envoi part dès que le réseau revient.
[{
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
player addEventHandler ["Respawn", { missionNamespace setVariable ["COMSPEC_ATAK_Device", createHashMap]; }];
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

// App Groupe : arrivées et changement de chef annoncés aux membres.
["comspec_atak_native_groupNotice", {
    params ["_text"];
    ["INFO", _text, 5, 30] call comspec_atak_native_fnc_notify;
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "GROUP") then { [{ ["GROUP"] call comspec_atak_native_fnc_pageRender; }, [], 0.5] call CBA_fnc_waitAndExecute; };
}] call CBA_fnc_addEventHandler;
