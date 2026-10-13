/* Met à jour les superpositions de la carte : boussole, panneau curseur, carte « moi », barre de données, outils actifs. */
params [["_cursorOnly", false]];
disableSerialization;
private _ov = uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cardinal = {
    params ["_a"];
    ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_a / 45)) mod 8)
};

private _cur = _ov getOrDefault ["cursor", controlNull];
if (!isNull _cur) then {
    private _p = _s getOrDefault ["cursorPos", []];
    private _measure = _s getOrDefault ["mapMeasure", []];
    // Zone MGRS de la carte (zone UTM + bande, carré de 100 km si ACE le fournit), calculée une fois par carte.
    private _zone = uiNamespace getVariable ["COMSPEC_ATAK_MgrsZone", ["", ""]];
    if ((_zone select 0) isNotEqualTo worldName) then {
        private _z = "";
        if (!isNil "ace_common_fnc_getMGRSdata") then {
            private _r = [] call ace_common_fnc_getMGRSdata;
            if (_r isEqualType [] && {(count _r) >= 2} && {(_r select 0) isEqualType ""} && {(_r select 1) isEqualType ""}) then { _z = format ["%1 %2", _r select 0, _r select 1]; };
        };
        if (_z isEqualTo "") then {
            // Sans ACE : zone et bande d'après la latitude / longitude de la carte (CfgWorlds, latitude comptée au sud).
            private _cfg = configFile >> "CfgWorlds" >> worldName;
            private _lat = ((-(getNumber (_cfg >> "latitude"))) max -80) min 83.9;
            private _lon = getNumber (_cfg >> "longitude");
            _z = format ["%1%2", ((floor ((_lon + 180) / 6)) mod 60) + 1, "CDEFGHJKLMNPQRSTUVWX" select [floor ((_lat + 80) / 8), 1]];
        };
        _zone = [worldName, _z];
        uiNamespace setVariable ["COMSPEC_ATAK_MgrsZone", _zone];
    };
    private _br = [" · ", "<br/>"] select (_ov getOrDefault ["cursorWrap", false]);
    private _text = if ((count _measure) >= 2) then {
        _measure params ["_a", "_b"];
        private _az = _a getDir _b;
        format ["<t font='EtelkaMonospacePro'><t color='#f2ab33'>A-B</t> %1 m · %2° %3 · <t color='#8a9a93'>Δ ALT</t> %4 m</t>", round (_a distance2D _b), round _az, [_az] call _cardinal, round ((getTerrainHeightASL _b) - (getTerrainHeightASL _a))]
    } else {
        if !(_s getOrDefault ["interactive", false]) exitWith {
            // Téléphone porté : pas de curseur, la barre donne la position du joueur.
            private _pp = getPosASL (vehicle player);
            private _dig = profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6];
            private _g8 = [[_pp, 8] call comspec_atak_native_fnc_gridRef, [_pp] call comspec_atak_native_fnc_gridRef] select (_dig isEqualTo 8);
            format ["<t font='EtelkaMonospacePro'><t color='#5cc76b'>MOI</t> %1%4<t color='#8a9a93'>MGRS</t> %2 %1 · <t color='#8a9a93'>ALT</t> %3 m</t>",
                _g8, _zone select 1, round (_pp select 2), _br]
        };
        if ((count _p) < 2) then { "<t font='EtelkaMonospacePro' color='#8a9a93'>CURSEUR · survolez la carte</t>" } else {
            private _az = player getDir _p;
            private _alt = getTerrainHeightASL _p;
            private _dig = profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6];
            private _g = [_p] call comspec_atak_native_fnc_gridRef;
            private _g8 = [[_p, 8] call comspec_atak_native_fnc_gridRef, _g] select (_dig isEqualTo 8);
            // Couverture réseau calculée (app Wave Relay) et calque affiché : débit et perte sous le curseur.
            private _net = "";
            if (profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then {
                private _cv = ["at", _p] call comspec_atak_native_fnc_relayNet;
                if (_cv isNotEqualTo []) then {
                    _net = [format [" · <t color='#8a9a93'>RÉSEAU</t> %1 Mbit/s %2 %%", (_cv select 0) toFixed 1, _cv select 1], " · <t color='#e5483a'>ZONE BLANCHE</t>"] select ((_cv select 0) <= 0);
                };
            };
            format ["<t font='EtelkaMonospacePro'><t color='#5cc76b'>CURSEUR</t> %1 · %2 m · %3° %4%8<t color='#8a9a93'>MGRS</t> %5 %6 · <t color='#8a9a93'>ALT</t> %7 m%9</t>",
                _g, round (player distance2D _p), (round _az) mod 360, [_az] call _cardinal, _zone select 1, _g8, round _alt, _br, _net]
        }
    };
    // Texte inchangé (souris immobile) : ni traduction ni texte structuré refaits.
    if ((_cur getVariable ["txt", ""]) isNotEqualTo _text) then {
        _cur setVariable ["txt", _text];
        _cur ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
    };
};
if (_cursorOnly) exitWith { true };

private _h = getDir (vehicle player);
// Flèche de cap vers l'étape active (points de passage).
private _wa = _ov getOrDefault ["wpArrow", controlNull];
if (!isNull _wa) then {
    private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
    private _pts = _w getOrDefault ["pts", []];
    if ((count _pts) > 0) then {
        ((_pts select (_w getOrDefault ["idx", 0]))) params ["_p", "_n"];
        private _b = player getDir _p;
        _wa ctrlSetAngle [_b - _h, 0.5, 0.5];
        private _d = player distance2D _p;
        (_ov getOrDefault ["wpText", controlNull]) ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t> <t size='0.8' color='#8a9a93'>%2/%3</t><br/><t size='0.95'>%4</t> <t size='0.8' color='#8a9a93'>%5°</t>",
            _n, (_w getOrDefault ["idx", 0]) + 1, count _pts, [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000), round _b]) call comspec_atak_native_fnc_tr);
    };
};
private _needle = _ov getOrDefault ["needle", controlNull];
if (!isNull _needle) then { _needle ctrlSetAngle [_h, 0.5, 0.5]; };
private _card = _ov getOrDefault ["compassCard", controlNull];
if (!isNull _card) then { _card ctrlSetAngle [-_h, 0.5, 0.5]; };
private _tape = _ov getOrDefault ["compassTape", controlNull];
if (!isNull _tape) then {
    // Bandeau : trois dizaines de chaque côté du cap, points cardinaux en lettres (N rouge), cap exact au centre.
    private _west = ["O", "W"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isNotEqualTo "fr");
    private _lbl = {
        params ["_a"];
        _a = (round _a) mod 360;
        switch (_a) do {
            case 0: { "<t color='#e5483a' font='COMSPEC_BarlowBold'>N</t>" };
            case 90: { "<t font='COMSPEC_BarlowBold'>E</t>" };
            case 180: { "<t font='COMSPEC_BarlowBold'>S</t>" };
            case 270: { format ["<t font='COMSPEC_BarlowBold'>%1</t>", _west] };
            default { format ["<t color='#c5ccc8'>%1</t>", _a] };
        };
    };
    private _lo = (floor (_h / 10)) * 10;
    private _hi = _lo + 10;
    private _left = [_lo - 20, _lo - 10, _lo] apply { [(_x + 360) mod 360] call _lbl };
    private _right = [_hi, _hi + 10, _hi + 20] apply { [_x mod 360] call _lbl };
    _tape ctrlSetStructuredText parseText ((format [
        "<t align='center' font='EtelkaMonospaceProBold' size='0.85'>%1  %2  %3 <t color='#5cc76b' size='1.05'>│%4°│</t> %5  %6  %7</t>",
        _left select 0, _left select 1, _left select 2, round _h, _right select 0, _right select 1, _right select 2]) call comspec_atak_native_fnc_tr);
};
private _hd = _ov getOrDefault ["heading", controlNull];
if (!isNull _hd) then { _hd ctrlSetText ((format ["%1° %2", round _h, [_h] call _cardinal]) call comspec_atak_native_fnc_tr); };

private _me = _ov getOrDefault ["me", controlNull];
if (!isNull _me) then {
    // Mini : indicatif et cap seulement ; plein écran : avec la grille.
    _me ctrlSetStructuredText parseText (((if (([] call comspec_atak_native_fnc_layoutGet) get "mini") then {
        format ["<t align='right'><t font='COMSPEC_BarlowBold' color='#5cc76b'>%1</t><br/>%2 %3°</t>", [player] call comspec_atak_native_fnc_unitCallsign, [_h] call _cardinal, round _h]
    } else {
        format ["<t align='right'><t font='COMSPEC_BarlowBold' color='#5cc76b'>%1</t><br/>%2 %3°<br/>%4</t>",
            [player] call comspec_atak_native_fnc_unitCallsign, [_h] call _cardinal, round _h, [player] call comspec_atak_native_fnc_gridRef]
    })) call comspec_atak_native_fnc_tr);
};

private _bar = _ov getOrDefault ["databar", controlNull];
if (!isNull _bar) then {
    private _v = vehicle player;
    _bar ctrlSetStructuredText parseText ((format [
        "<t align='center' size='0.9'>VIT %1 km/h   ALT %2 m   CAP %3°   %4   %5   %6</t>",
        round (speed _v) max 0, round ((getPosASL _v) select 2), round _h, [player] call comspec_atak_native_fnc_gridRef,
        [dayTime, "HH:MM"] call BIS_fnc_timeToString, toUpper (_s getOrDefault ["networkState", "OFFLINE"])
    ]) call comspec_atak_native_fnc_tr);
};

// Outils bascule : vert quand actifs.
private _on = [0.36, 0.78, 0.42, 1];
private _off = [0.90, 0.94, 0.91, 1];
private _mode = _s getOrDefault ["mapMode", "SELECT"];
{
    _x params ["_key", "_active"];
    private _b = _ov getOrDefault ["tool_" + _key, controlNull];
    if (!isNull _b) then { _b ctrlSetTextColor ([_off, _on] select _active); };
} forEach [
    ["follow", _s getOrDefault ["mapFollow", false]],
    ["MARKER", _mode isEqualTo "MARKER"],
    ["menu", _s getOrDefault ["mapToolsOpen", false]],
    ["labels", (["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 0]
];
true
