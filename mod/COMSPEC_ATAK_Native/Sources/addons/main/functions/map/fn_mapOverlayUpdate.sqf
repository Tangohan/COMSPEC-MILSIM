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
// Points cardinaux sur 16 directions dans la langue du téléphone (fn_cardinal, même table partout).
private _cardinal = { [_this select 0, 16] call comspec_atak_native_fnc_cardinal };
// Textes des cases : seuls les libellés passent par la traduction (fn_tr), jamais le texte entier. Avant, un point
// cardinal seul dans sa balise (« NO ») était traduit comme un mot : fr.txt contient « No » -> « Non », d'où « 317° NON ».
// Texte inchangé (souris immobile, même degré) : ni police ni texte structuré refaits.
private _put = {
    params ["_c", "_txt"];
    if (isNull _c || {(_c getVariable ["txt", ""]) isEqualTo _txt}) exitWith {};
    _c setVariable ["txt", _txt];
    if (missionNamespace getVariable ["COMSPEC_ATAK_FontAlt", false]) then { _txt = _txt call comspec_atak_native_fnc_fontText; };
    _c ctrlSetStructuredText parseText _txt; // i18n:brut (libellés déjà traduits)
};
// Libellé discret (vert par défaut) devant une valeur, et sous-libellé gris des lignes de détail.
private _lbl = { params ["_t", ["_c", "#5cc76b"]]; format ["<t size='0.78' color='%2'>%1</t> ", (_t) call comspec_atak_native_fnc_tr, _c] };
private _key = { params ["_t"]; format ["<t color='#7f8c86'>%1</t> ", (_t) call comspec_atak_native_fnc_tr] };
private _sepDot = "<t color='#7f8c86'> · </t>";
// Azimut sur trois chiffres (« 085° ») et distance en m puis en km.
private _az3 = { params ["_a"]; private _t = str ((round _a) mod 360); while {(count _t) < 3} do { _t = "0" + _t; }; _t };
private _distTxt = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 2]] select (_d >= 1000) };
// Case 2 du bandeau : ligne 1 « CAP 317° NNO » (mon cap, étiqueté comme tel) ; ligne 2 mon indicatif à la couleur de
// mon équipe de feu (celle de l'anneau autour de mon symbole) avec son nom, et ma grille en main (porté : déjà dans la
// case 1 ; mini : sans). Appelée aussi par la boussole à chaque degré : le cap du bandeau suit en direct.
// Boussole (_fast) : ligne 2 reprise du dernier passage complet (une fois par seconde) ; seul le cap est refait.
private _meUpdate = {
    params ["_h", ["_fast", false]];
    private _me = _ov getOrDefault ["me", controlNull];
    if (isNull _me) exitWith {};
    private _l2 = _ov getOrDefault ["meL2", ""];
    if (!_fast || {_l2 isEqualTo ""}) then {
        private _ft = [player] call comspec_atak_native_fnc_ftInfo;
        private _inFt = (_ft get "id") isNotEqualTo "";
        // Indicatif tel quel (jamais traduit), raccourci proprement s'il est long.
        private _cs = ([player] call comspec_atak_native_fnc_unitCallsign) regexReplace ["[<>&]", ""];
        if ((count _cs) > 12) then { _cs = (_cs select [0, 11]) + "…"; };
        _l2 = format ["<t font='COMSPEC_BarlowBold' color='%2'>%1</t>", _cs, ["#5cc76b", _ft get "hex"] select _inFt];
        if (_inFt) then {
            private _fn = (toUpper ((_ft get "name") call comspec_atak_native_fnc_tr)) regexReplace ["[<>&]", ""];
            if ((count _fn) > 8) then { _fn = _fn select [0, 8]; };
            _l2 = _l2 + format [" <t color='%2'>%1</t>", _fn, _ft get "hex"];
        };
        if ((_s getOrDefault ["interactive", false]) && {!(([] call comspec_atak_native_fnc_layoutGet) get "mini")}) then {
            _l2 = _l2 + _sepDot + format ["<t font='EtelkaMonospacePro' color='#c9d2cd'>%1</t>", [player] call comspec_atak_native_fnc_gridRef];
        };
        _ov set ["meL2", _l2];
    };
    [_me, format ["%1<t font='EtelkaMonospaceProBold' size='1.2'>%2°</t> <t font='EtelkaMonospacePro' size='0.95' color='#b7c1bc'>%3</t>",
        format ["<t size='0.78' color='#5cc76b'>%1</t> ", ["HDG", "CAP"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr")], [_h] call _az3, [_h] call _cardinal]] call _put;
    [_ov getOrDefault ["me2", controlNull], _l2] call _put;
};
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
        if (!isNull _hd) then { _hd ctrlSetText (format ["%1° %2", [_h] call _az3, [_h] call _cardinal]); }; // i18n:brut (cap et point cardinal déjà dans la langue)
        [_h, true] call _meUpdate;
        if (!isNull (_ov getOrDefault ["compassTape", controlNull])) then { [true, false, true] call comspec_atak_native_fnc_mapOverlayUpdate; };
    };
    true
};
params ["", "", ["_tapeOnly", false]];

private _cur = _ov getOrDefault ["cursor", controlNull];
// Bandeau de la boussole seul (à chaque degré) : panneau curseur / MOI laissé au passage complet (une fois par seconde)
// et aux mouvements de souris (grilles, réseau sous le curseur et textes refaits presque à chaque image sinon).
if (!isNull _cur && {!_tapeOnly}) then {
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
    // Bandeau d'information, case 1 (contrôles de fn_pageMap) :
    //   ligne 1 (cursor)  : libellé discret + position en grand : « CURSEUR 33T XE 1513 1736 » (grille MGRS complète,
    //                       une seule fois, à la précision des Réglages) ; porté : « MOI … » ; mesure : « A-B 2.13 km » ;
    //   ligne 2 (cursor2) : derrière la flèche du gisement (cursorArrow, nord en haut) « 94 m · 317° NO · ALT 17 m » ;
    //                       case étroite (cursorWrap) : altitude et réseau en ligne 3 ; très étroite (cursorNarrow) : la
    //                       zone MGRS passe aussi en ligne 3.
    private _wrap = _ov getOrDefault ["cursorWrap", false];
    private _narrow = _ov getOrDefault ["cursorNarrow", false];
    private _cur2 = _ov getOrDefault ["cursor2", controlNull];
    private _arw = _ov getOrDefault ["cursorArrow", controlNull];
    private _big = { format ["<t font='EtelkaMonospaceProBold' size='1.2'>%1</t>", _this select 0] };
    private _zoneTxt = format ["<t font='EtelkaMonospacePro' size='0.85' color='#b7c1bc'>%1</t> ", _zone select 1];
    private _val = { format ["<t font='EtelkaMonospaceProBold' color='#eef2ef'>%1</t>", _this select 0] };
    private _brg = { params ["_az"]; format ["%1 %2", [format ["%1°", [_az] call _az3]] call _val, format ["<t font='EtelkaMonospacePro' color='#b7c1bc'>%1</t>", [_az] call _cardinal]] };
    private _nl = "<br/>";
    private _line1 = "";
    private _line2 = "";
    private _az = -1;
    switch (true) do {
        case ((count _measure) >= 2): {
            _measure params ["_a", "_b"];
            _az = _a getDir _b;
            private _dAlt = round ((getTerrainHeightASL _b) - (getTerrainHeightASL _a));
            _line1 = (["A-B", "#f2ab33"] call _lbl) + ([[_a distance2D _b] call _distTxt] call _big);
            _line2 = ([_az] call _brg) + _sepDot + (["Δ ALT"] call _key) + ([format ["%1%2 m", ["", "+"] select (_dAlt > 0), _dAlt]] call _val);
        };
        case (!(_s getOrDefault ["interactive", false])): {
            // Téléphone porté : pas de curseur, la case donne ma position (grille à 8 chiffres au moins).
            private _pp = getPosASL (vehicle player);
            private _dig = (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) max 8;
            _line1 = (["MOI"] call _lbl) + (["", _zoneTxt] select !_narrow) + ([[_pp, _dig] call comspec_atak_native_fnc_gridRef] call _big);
            _line2 = ([_zoneTxt + "<t color='#7f8c86'>· </t>", ""] select !_narrow) + (["ALT"] call _key) + ([format ["%1 m", round (_pp select 2)]] call _val);
        };
        case ((count _p) < 2): {
            _line1 = (["CURSEUR"] call _lbl) + format ["<t color='#8a9a93'>%1</t>", ("survolez la carte") call comspec_atak_native_fnc_tr];
        };
        default {
            _az = player getDir _p;
            _line1 = (["CURSEUR"] call _lbl) + (["", _zoneTxt] select !_narrow) + ([[_p] call comspec_atak_native_fnc_gridRef] call _big);
            // Couverture réseau calculée (app Wave Relay) et calque affiché : débit et perte sous le curseur.
            private _net = "";
            if (profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then {
                private _cv = ["at", _p] call comspec_atak_native_fnc_relayNet;
                if (_cv isNotEqualTo []) then {
                    _net = _sepDot + ([
                        (["RÉSEAU"] call _key) + ([format ["%1 Mbit/s", (_cv select 0) toFixed 1]] call _val) + format [" <t color='#b7c1bc'>%1 %%</t>", _cv select 1],
                        format ["<t color='#e5483a'>%1</t>", ("ZONE BLANCHE") call comspec_atak_native_fnc_tr]
                    ] select ((_cv select 0) <= 0));
                };
            };
            private _da = ([[player distance2D _p] call _distTxt] call _val) + _sepDot + ([_az] call _brg);
            private _alt = (["ALT"] call _key) + ([format ["%1 m", round (getTerrainHeightASL _p)]] call _val);
            _line2 = if (_wrap) then {
                _da + _nl + ([_zoneTxt + "<t color='#7f8c86'>· </t>", ""] select !_narrow) + _alt + _net
            } else {
                _da + _sepDot + _alt + _net
            };
        };
    };
    [_cur, _line1] call _put;
    [_cur2, _line2] call _put;
    // Flèche du gisement : tournée seulement quand le degré change.
    if (!isNull _arw) then {
        if (_az < 0) exitWith { if (ctrlShown _arw) then { _arw ctrlShow false; }; };
        if !(ctrlShown _arw) then { _arw ctrlShow true; };
        private _deg = round _az;
        if ((_arw getVariable ["deg", -1]) isNotEqualTo _deg) then { _arw setVariable ["deg", _deg]; _arw ctrlSetAngle [_deg, 0.5, 0.5]; };
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
    private _west = ["O", "W"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isNotEqualTo "fr");
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
    // Graduations et lettres déjà dans la langue (O / W) : pas de traduction, une lettre seule y serait prise pour un mot.
    _tape ctrlSetStructuredText parseText (format [ // i18n:brut
        "<t align='center' font='EtelkaMonospaceProBold' size='0.85'>%1  %2  %3 <t color='#5cc76b' size='1.05'>│%4°│</t> %5  %6  %7</t>",
        _left select 0, _left select 1, _left select 2, round _h, _right select 0, _right select 1, _right select 2]);
};
if (_tapeOnly) exitWith { true };
private _hd = _ov getOrDefault ["heading", controlNull];
if (!isNull _hd) then { _hd ctrlSetText (format ["%1° %2", [_h] call _az3, [_h] call _cardinal]); }; // i18n:brut

[_h] call _meUpdate;

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
