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
private _lbl = { params ["_t", ["_c", (COMSPEC_ATAK_Th get "hexMapOk")]]; format ["<t size='0.78' color='%2'>%1</t> ", (_t) call comspec_atak_native_fnc_tr, _c] };
private _key = { params ["_t"]; format ["<t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t> ", (_t) call comspec_atak_native_fnc_tr] };
private _sepDot = "<t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'> · </t>";
// Azimut sur trois chiffres (« 085° ») et distance en m puis en km.
private _az3 = { params ["_a"]; private _t = str ((round _a) mod 360); while {(count _t) < 3} do { _t = "0" + _t; }; _t };
private _distTxt = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 2]] select (_d >= 1000) };
// Bandeau choisi par le joueur (fn_mapPrefs « band ») : style NEW | CLASSIC | COMPACT | HIDDEN et éléments affichés.
private _bc = ["band"] call comspec_atak_native_fnc_mapPrefs;
private _bStyle = _bc get "style";
private _bShow = _bc get "show";
// Case 2 du bandeau (NEW) : ligne 1 « CAP 317° NNO » (mon cap, étiqueté comme tel) ; ligne 2 mon indicatif à la couleur
// de mon équipe de feu (celle de l'anneau autour de mon symbole) avec son nom, et ma grille en main (porté : déjà dans la
// case 1 ; mini : sans). CLASSIC (rendu d'avant 2.9.34) : « 317° NO » en grand, l'indicatif dessous, dans un seul texte.
// Cap (HDG) ou indicatif (ME) masqués : l'autre prend la ligne 1. Appelée aussi par la boussole à chaque degré : le cap
// suit en direct. Boussole (_fast) : indicatif repris du dernier passage complet (une fois par seconde) ; seul le cap est refait.
private _meUpdate = {
    params ["_h", ["_fast", false]];
    private _me = _ov getOrDefault ["me", controlNull];
    if (isNull _me) exitWith {};
    private _l2 = _ov getOrDefault ["meL2", ""];
    if (("ME" in _bShow) && {!_fast || {_l2 isEqualTo ""}}) then {
        private _ft = [player] call comspec_atak_native_fnc_ftInfo;
        private _inFt = (_ft get "id") isNotEqualTo "";
        // Indicatif tel quel (jamais traduit), raccourci proprement s'il est long.
        private _cs = ([player] call comspec_atak_native_fnc_unitCallsign) regexReplace ["[<>&]", ""];
        if ((count _cs) > 12) then { _cs = (_cs select [0, 11]) + "…"; };
        _l2 = format ["<t font='COMSPEC_BarlowBold' color='%2'>%1</t>", _cs, [(COMSPEC_ATAK_Th get "hexMapOk"), _ft get "hex"] select _inFt];
        if (_inFt) then {
            private _fn = (toUpper ((_ft get "name") call comspec_atak_native_fnc_tr)) regexReplace ["[<>&]", ""];
            if ((count _fn) > 8) then { _fn = _fn select [0, 8]; };
            _l2 = _l2 + format [" <t color='%2'>%1</t>", _fn, _ft get "hex"];
        };
        if ((_s getOrDefault ["interactive", false]) && {!(([] call comspec_atak_native_fnc_layoutGet) get "mini")}) then {
            _l2 = _l2 + _sepDot + format ["<t font='EtelkaMonospacePro' color='" + (COMSPEC_ATAK_Th get "hexMapText") + "'>%1</t>", ["grid", [getPosASL player, ""]] call comspec_atak_native_fnc_mapPrefs];
        };
        _ov set ["meL2", _l2];
    };
    if !("ME" in _bShow) then { _l2 = ""; };
    private _hdgOn = "HDG" in _bShow;
    if (_bStyle isEqualTo "CLASSIC") exitWith {
        private _hd = if (_hdgOn) then { format ["<t font='EtelkaMonospaceProBold' size='1.2'>%1°</t> <t font='EtelkaMonospacePro' size='0.85' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%2</t>", [_h] call _az3, [_h, 8] call comspec_atak_native_fnc_cardinal] } else { "" };
        [_me, ([_hd, format ["<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t>", _l2]] select { _x isNotEqualTo "" && {_x isNotEqualTo "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'></t>"} }) joinString "<br/>"] call _put;
    };
    private _hd = if (_hdgOn) then {
        format ["%1<t font='EtelkaMonospaceProBold' size='1.2'>%2°</t> <t font='EtelkaMonospacePro' size='0.95' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%3</t>",
            format ["<t size='0.78' color='" + (COMSPEC_ATAK_Th get "hexMapOk") + "'>%1</t> ", ["HDG", "CAP"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr")], [_h] call _az3, [_h] call _cardinal]
    } else { _l2 };
    // NOUVEAU sans CURDIST : le cap (celui de ma vue) est déjà à gauche avec la flèche ; cette case garde l'indicatif.
    if !("CURDIST" in _bShow) then { _hd = _l2; _hdgOn = false; };
    [_me, _hd] call _put;
    [_ov getOrDefault ["me2", controlNull], ["", _l2] select _hdgOn] call _put;
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
// Bandeau compact : une ligne refaite au passage complet (le cap y suit une fois par seconde, pas à chaque degré).
// Bandeau de la boussole seul (à chaque degré) : panneau curseur / MOI laissé au passage complet (une fois par seconde)
// et aux mouvements de souris (grilles, réseau sous le curseur et textes refaits presque à chaque image sinon).
if (!isNull _cur && {!_tapeOnly}) then {
    private _p = _s getOrDefault ["cursorPos", []];
    private _measure = _s getOrDefault ["mapMeasure", []];
    private _inter = _s getOrDefault ["interactive", false];
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
    private _on = { _this in _bShow };
    private _mgrs = (_bc get "grid") isEqualTo "MGRS";
    private _dT = { ["dist", _this select 0] call comspec_atak_native_fnc_mapPrefs };
    private _aT = { ["alt", _this select 0] call comspec_atak_native_fnc_mapPrefs };
    // Grille du point (format choisi) ; porté : 8 chiffres au moins en MGRS.
    private _gT = {
        params ["_q", ["_dig", -1]];
        if (_mgrs && {_dig > 0}) exitWith { [_q, _dig] call comspec_atak_native_fnc_gridRef };
        ["grid", [_q, ""]] call comspec_atak_native_fnc_mapPrefs
    };
    private _big = { format ["<t font='EtelkaMonospaceProBold' size='1.2'>%1</t>", _this select 0] };
    private _zoneTxt = ["", format ["<t font='EtelkaMonospacePro' size='0.85' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t> ", _zone select 1]] select _mgrs;
    private _val = { format ["<t font='EtelkaMonospaceProBold' color='" + (COMSPEC_ATAK_Th get "hexMapText") + "'>%1</t>", _this select 0] };
    private _brg = { params ["_az"]; format ["%1 %2", [format ["%1°", [_az] call _az3]] call _val, format ["<t font='EtelkaMonospacePro' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t>", [_az] call _cardinal]] };
    private _nl = "<br/>";
    // Zone de l'app Zones sous le curseur (ou sous moi en porté) : nom à la couleur de son état, plan, état (fn_zoneAction).
    private _zq = [_p, getPosASL (vehicle player)] select !_inter;
    private _zn = if (("ZONE" call _on) && {(count _measure) < 2} && {(count _zq) >= 2}) then { ["atText", [_zq]] call comspec_atak_native_fnc_zoneAction } else { "" };
    // Couverture réseau calculée (app Wave Relay) et calque affiché : débit et perte sous le curseur.
    private _net = "";
    if (_inter && {(count _p) >= 2} && {(count _measure) < 2} && {profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]}) then {
        private _cv = ["at", _p] call comspec_atak_native_fnc_relayNet;
        if (_cv isNotEqualTo []) then {
            _net = [
                (["RÉSEAU"] call _key) + ([format ["%1 Mbit/s", (_cv select 0) toFixed 1]] call _val) + format [" <t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1 %%</t>", _cv select 1],
                format ["<t color='" + (COMSPEC_ATAK_Th get "hexMapAlert") + "'>%1</t>", ("ZONE BLANCHE") call comspec_atak_native_fnc_tr]
            ] select ((_cv select 0) <= 0);
        };
    };
    private _az = -1;
    switch (_bStyle) do {
        case "COMPACT": {
            // Une ligne : grille, distance et gisement, altitude, cap, zone.
            private _parts = [];
            private _h = getDirVisual (vehicle player);
            switch (true) do {
                case ((count _measure) >= 2): {
                    _measure params ["_a", "_b"];
                    _parts pushBack ((["A-B", (COMSPEC_ATAK_Th get "hexMapWarn")] call _lbl) + ([[_a distance2D _b] call _dT] call _val) + " " + ([_a getDir _b] call _brg));
                };
                case (!_inter): {
                    private _pp = getPosASL (vehicle player);
                    if ("GRID" call _on) then { _parts pushBack ((["MOI"] call _lbl) + _zoneTxt + ([[_pp, (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) max 8] call _gT] call _val)); };
                    if ("ALT" call _on) then { _parts pushBack ((["ALT"] call _key) + ([[_pp select 2] call _aT] call _val)); };
                };
                case ((count _p) < 2): { _parts pushBack ((["CURSEUR"] call _lbl) + format ["<t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t>", ("survolez la carte") call comspec_atak_native_fnc_tr]); };
                default {
                    if ("GRID" call _on) then { _parts pushBack (_zoneTxt + ([[_p] call _gT] call _val)); };
                    if ("DIST" call _on) then { _parts pushBack (([[player distance2D _p] call _dT] call _val) + " " + ([player getDir _p] call _brg)); };
                    if ("ALT" call _on) then { _parts pushBack ((["ALT"] call _key) + ([[getTerrainHeightASL _p] call _aT] call _val)); };
                    if (_net isNotEqualTo "") then { _parts pushBack _net; };
                };
            };
            if ("HDG" call _on) then { _parts pushBack ((["CAP"] call _key) + ([format ["%1°", [_h] call _az3]] call _val)); };
            if (_zn isNotEqualTo "") then { _parts pushBack _zn; };
            [_cur, _parts joinString _sepDot] call _put;
        };
        case "CLASSIC": {
            // Rendu d'avant 2.9.34, un seul texte : valeur en grand puis détails gris (corrigé : libellés seuls traduits,
            // « ALT 17 m », point cardinal jamais pris pour un mot).
            private _wrap = _ov getOrDefault ["cursorWrap", false];
            private _sub = { format ["<br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t>", _this select 0] };
            private _card8 = { [_this select 0, 8] call comspec_atak_native_fnc_cardinal };
            private _txt = switch (true) do {
                case ((count _measure) >= 2): {
                    _measure params ["_a", "_b"];
                    private _azm = _a getDir _b;
                    private _dAlt = round ((getTerrainHeightASL _b) - (getTerrainHeightASL _a));
                    format ["<t font='EtelkaMonospacePro'>%1%2  %3° %4%5</t>", ["A-B", (COMSPEC_ATAK_Th get "hexMapWarn")] call _lbl, [[_a distance2D _b] call _dT] call _big, [_azm] call _az3, [_azm] call _card8,
                        [(["Δ ALT"] call _key) + format ["%1%2", ["", "+"] select (_dAlt > 0), [_dAlt] call _aT]] call _sub]
                };
                case (!_inter): {
                    private _pp = getPosASL (vehicle player);
                    private _g8 = [_pp, (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) max 8] call _gT;
                    private _det = [];
                    if ("GRID" call _on && {_mgrs}) then { _det pushBack ((["MGRS"] call _key) + format ["%1 %2", _zone select 1, _g8]); };
                    if ("ALT" call _on) then { _det pushBack ((["ALT"] call _key) + ([_pp select 2] call _aT)); };
                    if (_zn isNotEqualTo "") then { _det pushBack _zn; };
                    format ["<t font='EtelkaMonospacePro'>%1%2%3</t>", ["MOI"] call _lbl, [["", _g8] select ("GRID" call _on)] call _big, ["", [_det joinString " · "] call _sub] select ((count _det) > 0)]
                };
                case ((count _p) < 2): { format ["<t font='EtelkaMonospacePro'>%1<t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%2</t></t>", ["CURSEUR"] call _lbl, ("survolez la carte") call comspec_atak_native_fnc_tr] };
                default {
                    _az = player getDir _p;
                    private _g = [_p] call _gT;
                    private _da = ["", format ["%1 · %2° %3", [player distance2D _p] call _dT, [_az] call _az3, [_az] call _card8]] select ("DIST" call _on);
                    private _det = [];
                    if ("GRID" call _on && {_mgrs}) then { _det pushBack ((["MGRS"] call _key) + format ["%1 %2", _zone select 1, [_p, 8] call comspec_atak_native_fnc_gridRef]); };
                    if ("ALT" call _on) then { _det pushBack ((["ALT"] call _key) + ([getTerrainHeightASL _p] call _aT)); };
                    if (_net isNotEqualTo "") then { _det pushBack _net; };
                    if (_zn isNotEqualTo "") then { _det pushBack _zn; };
                    private _head = (["CURSEUR"] call _lbl) + ([["", _g] select ("GRID" call _on)] call _big);
                    private _detT = ["", [_det joinString " · "] call _sub] select ((count _det) > 0);
                    if (_wrap) then {
                        format ["<t font='EtelkaMonospacePro'>%1%2%3</t>", _head, ["", [_da] call _sub] select (_da isNotEqualTo ""), _detT]
                    } else {
                        format ["<t font='EtelkaMonospacePro'>%1  %2%3</t>", _head, _da, _detT]
                    }
                };
            };
            [_cur, _txt] call _put;
        };
        default {
            // Bandeau d'information, case 1 (contrôles de fn_pageMap) :
            //   ligne 1 (cursor)  : libellé discret + position en grand : « CURSEUR 33T XE 1513 1736 » (grille MGRS complète,
            //                       une seule fois, à la précision des Réglages) ; porté : « MOI … » ; mesure : « A-B 2.13 km » ;
            //                       grille masquée : la distance prend sa place ;
            //   ligne 2 (cursor2) : derrière la flèche du gisement (cursorArrow, nord en haut) « 94 m · 317° NO · ALT 17 m »
            //                       et la zone de l'app Zones ; case étroite (cursorWrap) : altitude, réseau et zone en ligne 3 ;
            //                       très étroite (cursorNarrow) : la zone MGRS passe aussi en ligne 3.
            private _wrap = _ov getOrDefault ["cursorWrap", false];
            private _narrow = _ov getOrDefault ["cursorNarrow", false];
            private _cur2 = _ov getOrDefault ["cursor2", controlNull];
            private _line1 = "";
            private _line2 = "";
            switch (true) do {
                case ((count _measure) >= 2): {
                    _measure params ["_a", "_b"];
                    _az = _a getDir _b;
                    private _dAlt = round ((getTerrainHeightASL _b) - (getTerrainHeightASL _a));
                    _line1 = (["A-B", (COMSPEC_ATAK_Th get "hexMapWarn")] call _lbl) + ([[_a distance2D _b] call _dT] call _big);
                    _line2 = ([_az] call _brg) + _sepDot + (["Δ ALT"] call _key) + ([format ["%1%2", ["", "+"] select (_dAlt > 0), [_dAlt] call _aT]] call _val);
                };
                case (!_inter): {
                    // Téléphone porté : pas de curseur, la case donne ma position (grille à 8 chiffres au moins).
                    private _pp = getPosASL (vehicle player);
                    _line1 = (["MOI"] call _lbl) + ([
                        (["", _zoneTxt] select !_narrow) + ([[_pp, (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) max 8] call _gT] call _big),
                        [[_pp select 2] call _aT] call _big
                    ] select !("GRID" call _on));
                    private _l2 = [];
                    if (_narrow && {"GRID" call _on} && {_zoneTxt isNotEqualTo ""}) then { _l2 pushBack _zoneTxt; };
                    if ("ALT" call _on && {"GRID" call _on}) then { _l2 pushBack ((["ALT"] call _key) + ([[_pp select 2] call _aT] call _val)); };
                    if (_zn isNotEqualTo "") then { _l2 pushBack _zn; };
                    _line2 = _l2 joinString _sepDot;
                };
                case ((count _p) < 2): {
                    _line1 = (["CURSEUR"] call _lbl) + format ["<t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t>", ("survolez la carte") call comspec_atak_native_fnc_tr];
                };
                default {
                    _az = player getDir _p;
                    private _gOn = "GRID" call _on;
                    private _dOn = "DIST" call _on;
                    _line1 = (["CURSEUR"] call _lbl) + (switch (true) do {
                        case (_gOn): { (["", _zoneTxt] select !_narrow) + ([[_p] call _gT] call _big) };
                        case (_dOn): { [[player distance2D _p] call _dT] call _big };
                        default { "" };
                    });
                    // Sans CURDIST (défaut 2.9.36) : flèche et cap = direction de la vue du personnage, pas du curseur.
                    private _curDist = "CURDIST" call _on;
                    private _da = if (_dOn && {_curDist}) then { [([[player distance2D _p] call _dT] call _val) + _sepDot, ""] select !_gOn } else { "" };
                    if (_dOn && {_curDist}) then { _da = _da + ([_az] call _brg); };
                    private _viewAz = -1;
                    if (!_curDist && {"HDG" call _on}) then {
                        private _vd = getCameraViewDirection player;
                        _viewAz = (((_vd select 0) atan2 (_vd select 1)) + 360) mod 360;
                        _da = (["CAP"] call _key) + ([_viewAz] call _brg);
                    };
                    private _rest = [];
                    if (_narrow && {_gOn} && {_zoneTxt isNotEqualTo ""}) then { _rest pushBack _zoneTxt; };
                    if ("ALT" call _on) then { _rest pushBack ((["ALT"] call _key) + ([[getTerrainHeightASL _p] call _aT] call _val)); };
                    if (_net isNotEqualTo "") then { _rest pushBack _net; };
                    if (_zn isNotEqualTo "") then { _rest pushBack _zn; };
                    _line2 = if (_wrap && {_da isNotEqualTo ""}) then {
                        _da + (["", _nl + (_rest joinString _sepDot)] select ((count _rest) > 0))
                    } else {
                        (([_da] select { _x isNotEqualTo "" }) + _rest) joinString _sepDot
                    };
                    _az = [[-1, _az] select _dOn, _viewAz] select !_curDist;
                };
            };
            [_cur, _line1] call _put;
            [_cur2, _line2] call _put;
        };
    };
    // Flèche du gisement (bandeau NEW) : tournée seulement quand le degré change.
    private _arw = _ov getOrDefault ["cursorArrow", controlNull];
    if (!isNull _arw) then {
        if (_az < 0 || {_bStyle isNotEqualTo "NEW"}) exitWith { if (ctrlShown _arw) then { _arw ctrlShow false; }; };
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
        (_ov getOrDefault ["wpText", controlNull]) ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t> <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%2/%3</t><br/><t size='0.95'>%4</t> <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%5°</t>",
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
            case 0: { "<t color='" + (COMSPEC_ATAK_Th get "hexMapAlert") + "' font='COMSPEC_BarlowBold'>N</t>" };
            case 90: { "<t font='COMSPEC_BarlowBold'>E</t>" };
            case 180: { "<t font='COMSPEC_BarlowBold'>S</t>" };
            case 270: { format ["<t font='COMSPEC_BarlowBold'>%1</t>", _west] };
            default { format ["<t color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t>", _a] };
        };
    };
    private _lo = (floor (_h / 10)) * 10;
    private _hi = _lo + 10;
    private _left = [_lo - 20, _lo - 10, _lo] apply { [(_x + 360) mod 360] call _lbl };
    private _right = [_hi, _hi + 10, _hi + 20] apply { [_x mod 360] call _lbl };
    // Graduations et lettres déjà dans la langue (O / W) : pas de traduction, une lettre seule y serait prise pour un mot.
    _tape ctrlSetStructuredText parseText (format [ // i18n:brut
        "<t align='center' font='EtelkaMonospaceProBold' size='0.85'>%1  %2  %3 <t color='" + (COMSPEC_ATAK_Th get "hexMapOk") + "' size='1.05'>│%4°│</t> %5  %6  %7</t>",
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
private _on = (COMSPEC_ATAK_Th get "mapOk");
private _off = (COMSPEC_ATAK_Th get "mapText");
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
