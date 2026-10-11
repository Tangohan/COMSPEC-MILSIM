/*
    Lueur de l'écran la nuit : un téléphone allumé éclaire le visage et se voit de loin (reflet, point lumineux),
    encore plus aux JVN. Chaque client publie son niveau (variable publique COMSPEC_ATAK_Glow = [niveau 0-1, rouge])
    et dessine pour lui la lueur des joueurs proches (lumière locale accrochée au torse, éclat visible de loin).
    Atténuée par le mode nuit (rouge, sombre), dans un véhicule, et surtout par le filtre d'écran JVN
    (objet COMSPEC_ATAK_NvgFilter posé : profil COMSPEC_ATAK_NvgFilter) qui coupe le proche infrarouge.
    Réglage serveur comspec_atak_native_screen_glow.
    Params : [action]  "filter" (filtre posé ?) | "toggle" (poser / retirer) | "level" | "tick" (client, chaque seconde)
*/
params [["_act", "tick"]];
private _item = "COMSPEC_ATAK_NvgFilter";
switch (_act) do {
    case "filter": { (profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false]) && {_item in (items player)} };
    case "toggle": {
        private _on = profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false];
        if (!_on && {!(_item in (items player))}) exitWith { ["WARNING", "Aucun filtre d'écran JVN dans l'inventaire", 3, 20] call comspec_atak_native_fnc_notify; false };
        profileNamespace setVariable ["COMSPEC_ATAK_NvgFilter", !_on];
        saveProfileNamespace;
        playSound "ClickSoft";
        ["INFO", ["Filtre d'écran JVN posé : lueur de l'écran fortement réduite", "Filtre d'écran JVN retiré"] select _on, 3, 20] call comspec_atak_native_fnc_notify;
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_deviceOverlay; };
        !_on
    };
    case "level": {
        if !(missionNamespace getVariable ["comspec_atak_native_screen_glow", true]) exitWith { 0 };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d || {!alive player} || {sunOrMoon >= 0.5}) exitWith { 0 };
        if ((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"]) exitWith { 0 };
        private _lvl = [0.55, 1] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]));
        _lvl = _lvl * (switch (profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"]) do { case "RED": { 0.45 }; case "DIM": { 0.4 }; default { 1 }; });
        // Luminosité réglée (volet rapide) : écran baissé, lueur réduite d'autant.
        _lvl = _lvl * ((profileNamespace getVariable ["COMSPEC_ATAK_Brightness", 1]) max 0.2 min 1);
        if (!isNull objectParent player) then { _lvl = _lvl * 0.3; };
        if (["filter"] call comspec_atak_native_fnc_screenGlow) then { _lvl = _lvl * 0.12; };
        _lvl
    };
    case "tick": {
        if (!hasInterface) exitWith {};
        private _lvl = (round ((["level"] call comspec_atak_native_fnc_screenGlow) * 20)) / 20;
        private _pub = [_lvl, (profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"]) isEqualTo "RED"];
        if ((player getVariable ["COMSPEC_ATAK_Glow", [0, false]]) isNotEqualTo _pub) then { player setVariable ["COMSPEC_ATAK_Glow", _pub, true]; };
        // Lumières locales : une par joueur proche dont l'écran luit.
        private _lights = uiNamespace getVariable ["COMSPEC_ATAK_GlowLights", createHashMap];
        private _keep = [];
        private _who = allPlayers select { (_x distance player) < 1500 };
        _who pushBackUnique player;
        {
            (_x getVariable ["COMSPEC_ATAK_Glow", [0, false]]) params [["_l", 0], ["_red", false]];
            if (_l <= 0 || {!alive _x}) then { continue };
            private _k = hashValue _x;
            _keep pushBack _k;
            (_lights getOrDefault [_k, [objNull, []]]) params ["_lp", "_sig"];
            if (isNull _lp) then {
                _lp = "#lightpoint" createVehicleLocal (getPosATL _x);
                _lp attachTo [_x, [0, 0.38, 0.12], "spine3"];
                _sig = [];
            };
            if (_sig isNotEqualTo [_l, _red]) then {
                private _c = [[0.55, 0.72, 1], [1, 0.12, 0.05]] select _red;
                _lp setLightColor _c;
                _lp setLightAmbient (_c apply { _x * 0.35 });
                _lp setLightBrightness (0.05 * _l);
                _lp setLightAttenuation [0.05, 0, 0, 6];
                _lp setLightDayLight false;
                // Éclat vu de loin (pas pour soi : on ne voit pas son propre écran de dos).
                _lp setLightUseFlare (_x isNotEqualTo player);
                _lp setLightFlareSize (0.15 + 0.5 * _l);
                _lp setLightFlareMaxDistance (40 + 1100 * _l);
                _sig = [_l, _red];
            };
            _lights set [_k, [_lp, _sig]];
        } forEach _who;
        { if !(_x in _keep) then { deleteVehicle ((_y select 0)); _lights deleteAt _x; }; } forEach +_lights;
        uiNamespace setVariable ["COMSPEC_ATAK_GlowLights", _lights];
    };
    default { false };
};
