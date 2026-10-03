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
