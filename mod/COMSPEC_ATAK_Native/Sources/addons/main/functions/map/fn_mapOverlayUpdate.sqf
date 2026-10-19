/*
    Met à jour les superpositions de la carte : boussole, panneau curseur, carte « moi », barre de données, outils actifs.
    Params : [curseur seulement, boussole seulement]
    Boussole seulement : appelée à chaque image par fn_mapOnDraw (mini, porté et plein écran) ; l'aiguille suit le cap
    sans attendre la seconde suivante (retour de jeu : « en mode mini, ça rame, ce n'est pas dynamique »). Coût minime :
    deux ctrlSetAngle, et le texte du cap ou du bandeau seulement quand le degré affiché change.
*/
params [["_cursorOnly", false], ["_compassOnly", false]];
disableSerialization;
private _ov = uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Points cardinaux dans la langue du téléphone (O / W) : « NO » ne passait pas par le dictionnaire.
private _cardinal = {
    params ["_a"];
    ([["N", "NE", "E", "SE", "S", "SW", "W", "NW"], ["N", "NE", "E", "SE", "S", "SO", "O", "NO"]] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isEqualTo "fr")) select ((round (_a / 45)) mod 8)
};
// Azimut sur trois chiffres (« 085° ») et distance en m puis en km.
private _az3 = { params ["_a"]; private _t = str ((round _a) mod 360); while {(count _t) < 3} do { _t = "0" + _t; }; _t };
private _distTxt = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 2]] select (_d >= 1000) };
if (_compassOnly) exitWith {
    private _h = getDirVisual (vehicle player);
    if ((abs (_h - (_ov getOrDefault ["compassH", -1]))) < 0.2) exitWith { false };
    _ov set ["compassH", _h];
    private _needle = _ov getOrDefault ["needle", controlNull];
    if (!isNull _needle) then { _needle ctrlSetAngle [_h, 0.5, 0.5]; };
    private _card = _ov getOrDefault ["compassCard", controlNull];
    if (!isNull _card) then { _card ctrlSetAngle [-_h, 0.5, 0.5]; };
    // Cap en texte et bandeau : redessinés au changement de degré seulement (passage complet, sans le curseur).
    if ((round _h) isNotEqualTo (_ov getOrDefault ["compassDeg", -1])) then {
        _ov set ["compassDeg", round _h];
        private _hd = _ov getOrDefault ["heading", controlNull];
        if (!isNull _hd) then { _hd ctrlSetText ((format ["%1° %2", (round _h) mod 360, [_h] call _cardinal]) call comspec_atak_native_fnc_tr); };
        if (!isNull (_ov getOrDefault ["compassTape", controlNull])) then { [true, false, true] call comspec_atak_native_fnc_mapOverlayUpdate; };
    };
    true
};
params ["", "", ["_tapeOnly", false]];

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
    // Bandeau d'information, case 1 : ligne 1 = libellé discret + valeur principale en grand, ligne 2 = détails en
    // petit gris ; case trop étroite (cursorWrap) : distance et azimut passent en ligne 2, le reste en ligne 3.
    private _wrap = _ov getOrDefault ["cursorWrap", false];
    private _lbl = { params ["_t", ["_c", "#5cc76b"]]; format ["<t size='0.78' color='%2'>%1</t> ", _t, _c] };
    private _big = { params ["_t"]; format ["<t font='EtelkaMonospaceProBold' size='1.2'>%1</t>", _t] };
    private _sub = { params ["_t"]; format ["<br/><t size='0.85' color='#b7c1bc'>%1</t>", _t] };
    private _grey = "<t color='#7f8c86'>%1</t>";
    private _text = if ((count _measure) >= 2) then {
        _measure params ["_a", "_b"];
        private _az = _a getDir _b;
        format ["<t font='EtelkaMonospacePro'>%1%2  %3° %4%5</t>", ["A-B", "#f2ab33"] call _lbl, [[_a distance2D _b] call _distTxt] call _big, [_az] call _az3, [_az] call _cardinal,
            [format [_grey + " %2 m", "Δ ALT", round ((getTerrainHeightASL _b) - (getTerrainHeightASL _a))]] call _sub]
    } else {
        if !(_s getOrDefault ["interactive", false]) exitWith {
            // Téléphone porté : pas de curseur, le bandeau donne la position du joueur.
            private _pp = getPosASL (vehicle player);
            private _dig = profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6];
            private _g8 = [[_pp, 8] call comspec_atak_native_fnc_gridRef, [_pp] call comspec_atak_native_fnc_gridRef] select (_dig isEqualTo 8);
            format ["<t font='EtelkaMonospacePro'>%1%2%3</t>", ["MOI"] call _lbl, [_g8] call _big,
                [format [_grey + " %2 %3 · " + _grey + " %4 m", "MGRS", _zone select 1, _g8, "ALT", round (_pp select 2)]] call _sub]
        };
        if ((count _p) < 2) then { format ["<t font='EtelkaMonospacePro'>%1<t color='#8a9a93'>survolez la carte</t></t>", ["CURSEUR"] call _lbl] } else {
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
                    _net = [format [" · " + _grey + " %2 Mbit/s %3 %%", "RÉSEAU", (_cv select 0) toFixed 1, _cv select 1], " · <t color='#e5483a'>ZONE BLANCHE</t>"] select ((_cv select 0) <= 0);
                };
            };
            private _da = format ["%1 · %2° %3", [player distance2D _p] call _distTxt, [_az] call _az3, [_az] call _cardinal];
            private _det = format [_grey + " %2 %3 · " + _grey + " %4 m%5", "MGRS", _zone select 1, _g8, "ALT", round _alt, _net];
            if (_wrap) then {
                format ["<t font='EtelkaMonospacePro'>%1%2%3%4</t>", ["CURSEUR"] call _lbl, [_g] call _big, [_da] call _sub, [_det] call _sub]
            } else {
                format ["<t font='EtelkaMonospacePro'>%1%2  %3%4</t>", ["CURSEUR"] call _lbl, [_g] call _big, _da, [_det] call _sub]
            }
        }
    };
    // Texte inchangé (souris immobile) : ni traduction ni texte structuré refaits.
    if ((_cur getVariable ["txt", ""]) isNotEqualTo _text) then {
        _cur setVariable ["txt", _text];
        _cur ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
    };
};
if (_cursorOnly && {!_tapeOnly}) exitWith { true };

private _h = getDirVisual (vehicle player);
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
if (_tapeOnly) exitWith { true };
private _hd = _ov getOrDefault ["heading", controlNull];
if (!isNull _hd) then { _hd ctrlSetText ((format ["%1° %2", round _h, [_h] call _cardinal]) call comspec_atak_native_fnc_tr); };

private _me = _ov getOrDefault ["me", controlNull];
if (!isNull _me) then {
    // Case 2 du bandeau : mon cap en grand ; en dessous mon indicatif à la couleur de mon équipe de feu (celle de
    // l'anneau autour de mon symbole) avec son nom, et ma grille en main (porté : déjà dans la case 1 ; mini : sans).
    private _ft = [player] call comspec_atak_native_fnc_ftInfo;
    private _inFt = (_ft get "id") isNotEqualTo "";
    private _l2 = format ["<t font='COMSPEC_BarlowBold' color='%2'>%1</t>", [player] call comspec_atak_native_fnc_unitCallsign, ["#5cc76b", _ft get "hex"] select _inFt];
    if (_inFt) then { _l2 = _l2 + format [" <t size='0.85' color='%2'>%1</t>", toUpper (_ft get "name"), _ft get "hex"]; };
    if ((_s getOrDefault ["interactive", false]) && {!(([] call comspec_atak_native_fnc_layoutGet) get "mini")}) then {
        _l2 = _l2 + format [" <t color='#7f8c86'>·</t> %1", [player] call comspec_atak_native_fnc_gridRef];
    };
    private _meTxt = format ["<t font='EtelkaMonospacePro'><t font='EtelkaMonospaceProBold' size='1.2'>%1°</t> <t size='0.85' color='#b7c1bc'>%2</t><br/><t size='0.85' color='#b7c1bc'>%3</t></t>",
        [_h] call _az3, [_h] call _cardinal, _l2];
    if ((_me getVariable ["txt", ""]) isNotEqualTo _meTxt) then {
        _me setVariable ["txt", _meTxt];
        _me ctrlSetStructuredText parseText ((_meTxt) call comspec_atak_native_fnc_tr);
    };
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
