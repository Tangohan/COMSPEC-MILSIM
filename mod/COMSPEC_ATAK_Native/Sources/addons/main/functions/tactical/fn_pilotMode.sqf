/*
    Mode pilote : écran simplifié proposé en montant dans un aéronef (fn_pageQuickMap) et HUD discret, téléphone rangé compris.
    Réglage serveur comspec_atak_native_pilot_mode : 0 au choix du joueur (profil COMSPEC_ATAK_PilotOn, coupé par défaut),
    1 proposé à tous, 2 coupé. Réglages du joueur : COMSPEC_ATAK_PilotAuto (le téléphone s'ouvre sur l'écran pilote à bord),
    COMSPEC_ATAK_PilotHud (HUD, allumé par défaut).
    Proposition : une notification et une ligne du HUD, seulement à l'arrêt ou presque ; rien en plein vol, aucune fenêtre forcée.
    HUD : contrôle unique sur l'écran 46, réécrit une fois par seconde s'il change, supprimé hors de l'appareil. Il ne montre
    que ce que le téléphone a déjà : missions reçues, allié le plus proche (seulement avec du réseau et un téléphone).
    Params : [action, argument]
      "on" -> mode pilote permis et choisi ; "locked" -> imposé par le serveur ; "setOn", bool ; "crew" -> poste d'équipage d'un aéronef
      "wantView" -> le téléphone doit s'ouvrir sur l'écran pilote ; "open" : touche, ouvre l'écran pilote
      "tick" : boucle d'une seconde (XEH_postInitClient) ; "hudNow" : réécrit le HUD ; "hudOff" : supprime le HUD
*/
params [["_act", ""], ["_arg", ""]];
disableSerialization;
private _on = {
    switch (missionNamespace getVariable ["comspec_atak_native_pilot_mode", 0]) do {
        case 1: { true };
        case 2: { false };
        default { profileNamespace getVariable ["COMSPEC_ATAK_PilotOn", false] };
    }
};
private _crew = {
    private _v = objectParent player;
    !isNull _v && {_v isKindOf "Air"} && {!unitIsUAV _v} && {alive player} && {driver _v isEqualTo player || {gunner _v isEqualTo player} || {commander _v isEqualTo player} || {((assignedVehicleRole player) param [0, ""]) isEqualTo "Turret"}}
};
private _hudOff = {
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_PilotHud", controlNull];
    if (!isNull _c) then { ctrlDelete _c; };
    uiNamespace setVariable ["COMSPEC_ATAK_PilotHud", controlNull];
};
private _hud = {
    private _d = findDisplay 46;
    if (isNull _d) exitWith {};
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _inHand = !isNull ([] call comspec_atak_native_fnc_display) && {_s getOrDefault ["interactive", false]};
    if (!(profileNamespace getVariable ["COMSPEC_ATAK_PilotHud", true]) || {_inHand} || {visibleMap}) exitWith { call _hudOff; };
    private _tr = { _this call comspec_atak_native_fnc_tr };
    private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
    private _az = { params ["_p"]; [(round (player getDir _p)) mod 360, 3] call CBA_fnc_formatNumber };
    private _lines = [];
    private _hint = ["aimHint"] call comspec_atak_native_fnc_firesQuick;
    if (_hint isNotEqualTo "") then { _lines pushBack _hint; };
    // Mission la plus récente.
    private _b = (["missions"] call comspec_atak_native_fnc_firesQuick) param [0, createHashMap];
    if ((count _b) > 0) then {
        private _is5 = (_b getOrDefault ["fmt", "9"]) isEqualTo "5";
        private _tp = ["tpos", _b] call comspec_atak_native_fnc_firesQuick;
        private _ack = _b getOrDefault ["ack", ""];
        _lines pushBack format ["%1 %2 · %3 · %4%5", "FIRES", _b getOrDefault ["from", ""], (_b getOrDefault ["lines", []]) param [[4, 3] select _is5, "-"],
            if ((count _tp) >= 2) then { format ["%1° %2", [_tp] call _az, [player distance2D _tp] call _dist] } else { (_b getOrDefault ["lines", []]) param [[5, 2] select _is5, "-"] },
            ["", format [" · %1", (["label", _ack] call comspec_atak_native_fnc_airBrief) call _tr]] select (_ack isNotEqualTo "")];
    };
    // Allié le plus proche : relevé toutes les 5 s, seulement avec du réseau.
    if ((diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_PilotAirAt", -1e9])) >= 5) then {
        missionNamespace setVariable ["COMSPEC_ATAK_PilotAirAt", diag_tickTime];
        private _near = "";
        if ((([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0) then {
            private _a = (["airs", false] call comspec_atak_native_fnc_airBrief) select { (_x select 0) isNotEqualTo (vehicle player) };
            if ((count _a) > 0) then {
                (_a select 0) params ["_v", "_cs", "", "_dd"];
                _near = format ["%1 · %2° %3 · %4 m", _cs, [_v] call _az, [_dd] call _dist, round (((getPosATL _v) select 2) max 0)];
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_PilotAirTxt", _near];
    };
    private _near = missionNamespace getVariable ["COMSPEC_ATAK_PilotAirTxt", ""];
    if (_near isNotEqualTo "") then { _lines pushBack _near; };
    private _offer = missionNamespace getVariable ["COMSPEC_ATAK_PilotOffer", -1e9];
    if ((diag_tickTime - _offer) < 10) then { _lines pushBack (("Mode pilote disponible : ouvrez le téléphone ou utilisez sa touche") call _tr); };
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_PilotHud", controlNull];
    if ((count _lines) isEqualTo 0) exitWith { call _hudOff; };
    if (isNull _c) then {
        _c = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
        _c ctrlSetPosition [safeZoneX + safeZoneW * 0.3, safeZoneY + safeZoneH * 0.015, safeZoneW * 0.4, safeZoneH * 0.1];
        _c ctrlSetBackgroundColor [0, 0, 0, 0];
        _c ctrlSetFontHeight ((pixelH * 16) max (safeZoneH * 0.018));
        _c ctrlEnable false;
        _c ctrlCommit 0;
        uiNamespace setVariable ["COMSPEC_ATAK_PilotHud", _c];
    };
    // JVN : vert à faible luminance ; nuit : atténué ; jour : clair.
    private _col = switch (true) do {
        case ((currentVisionMode player) isEqualTo 1 || {(profileNamespace getVariable ["COMSPEC_ATAK_Theme", "DARK"]) isEqualTo "NVG"}): { "#3fae5a" };
        case (sunOrMoon < 0.3): { "#9aa59c" };
        default { "#e8efe9" };
    };
    private _html = format ["<t align='center' shadow='2' color='%1'>%2</t>", _col, (_lines apply { [[_x, "&", "&amp;"] call CBA_fnc_replace, "<", "&lt;"] call CBA_fnc_replace }) joinString "<br/>"];
    if ((_c getVariable ["txt", ""]) isNotEqualTo _html) then { _c setVariable ["txt", _html]; _c ctrlSetStructuredText parseText _html; }; // i18n:brut (déjà traduit)
};
switch (_act) do {
    case "on": { call _on };
    case "locked": { (missionNamespace getVariable ["comspec_atak_native_pilot_mode", 0]) isNotEqualTo 0 };
    case "setOn": {
        profileNamespace setVariable ["COMSPEC_ATAK_PilotOn", _arg isEqualTo true];
        [] call comspec_atak_native_fnc_saveProfile;
        true
    };
    case "crew": { call _crew };
    case "wantView": { (call _on) && {call _crew} && {profileNamespace getVariable ["COMSPEC_ATAK_PilotAuto", false]} && {(["state", "Pilot"] call comspec_atak_native_fnc_appInstall) isNotEqualTo "forbidden"} };
    case "open": {
        if !(call _on) exitWith { ["INFO", "Mode pilote coupé : Réglages, Applications", 4, 20] call comspec_atak_native_fnc_notify; false };
        private _why = [] call comspec_atak_native_fnc_canUse;
        if (_why isNotEqualTo "") exitWith { [_why] call comspec_atak_native_fnc_deviceDenied; false };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        ["PILOT"] call comspec_atak_native_fnc_openApp;
        true
    };
    case "hudNow": { if ((call _on) && {call _crew}) then { call _hud; }; };
    case "hudOff": { call _hudOff; };
    case "tick": {
        private _in = (call _on) && {call _crew};
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_PilotIn", false];
        if (!_in) exitWith {
            if (!_was) exitWith {};
            missionNamespace setVariable ["COMSPEC_ATAK_PilotIn", false];
            missionNamespace setVariable ["COMSPEC_ATAK_PilotOffer", -1e9];
            call _hudOff;
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            _s set ["qvCentered", false];
            if ((_s getOrDefault ["quickView", ""]) isEqualTo "PILOT") then {
                _s set ["quickView", ""];
                _s set ["mapFollow", false];
                if (!isNull ([] call comspec_atak_native_fnc_display) && {(_s getOrDefault ["activePage", ""]) isEqualTo "MAP"}) then {
                    [{ ["MAP", false] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
                };
            };
        };
        if (!_was) then {
            missionNamespace setVariable ["COMSPEC_ATAK_PilotIn", true];
            missionNamespace setVariable ["COMSPEC_ATAK_PilotAirAt", -1e9];
            // À l'arrêt ou au roulage seulement : pas d'interruption en plein vol.
            private _v = objectParent player;
            if (!(profileNamespace getVariable ["COMSPEC_ATAK_PilotAuto", false]) && {(abs speed _v) < 40} && {((getPos _v) select 2) < 5}
                && {(["state", "Pilot"] call comspec_atak_native_fnc_appInstall) isNotEqualTo "forbidden"}) then {
                missionNamespace setVariable ["COMSPEC_ATAK_PilotOffer", diag_tickTime];
                ["TACTICAL", "Mode pilote disponible : touchez pour l'ouvrir", 8, 40, "PILOT"] call comspec_atak_native_fnc_notify;
            };
        };
        call _hud;
    };
};
