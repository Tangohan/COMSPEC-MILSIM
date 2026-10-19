/*
    Mode « terminal compromis » de MON ATAK : tout reste dans l'écran du téléphone (jamais sur l'écran de jeu).
    Deux origines :
      - MANUELLE : avis de l'administration ATHENA, de la gestion de la communauté ou du poste de commandement
        (fn_adminNotices, genre « compromise », levé par « compromise_cleared » ou un certificat régénéré), ou effet
        posé en jeu par le Zeus (COMSPEC_CompromiseState) ;
      - AUTOMATIQUE : mon téléphone a été ramassé par l'ennemi et la capture validée par le camp (fn_captureServer,
        fn_compromise « validated ») : COMSPEC Link a révoqué le certificat de mon terminal Athena.
    Effets : logo COMSPEC Link rouge clignotant (fn_statusUpdate), carte dégradée (fn_mapOnDraw « draw ») : voile et
    hachures, bandeau LIAISON DE DONNÉES COUPÉE, console d'erreurs (codes, terminal, IMEI, état, origine, motif,
    dernière synchro), pistes amies figées à leur dernière position connue ; ma position GPS reste en direct.
    Gardé dans le profil (par terminal Athena ou par IMEI) jusqu'à la levée de la compromission.
    Params : [action, argument]
      "set" hashmap   entre en mode compromis : origin ("manual" | "auto"), source, state ("captured" | "compromised"),
                      reason, by, revoked (nombre de certificats), ref, match ("terminal" | "imei")
      "clear"         levée de la compromission
      "refresh"       à chaque tour de la barre d'état : état actif (COMSPEC_ATAK_CompActive) et détection automatique
      "get"           état actif (hashmap vide si aucun)
      "meta" texte    « clé=valeur;clé=valeur » (7e colonne d'AdminControl.Poll) -> hashmap
      "draw" carte    carte dégradée (fin de fn_mapOnDraw)
      "skip"          les pistes en direct sont-elles masquées ?
*/
params [["_act", ""], ["_arg", []]];
private _key = "COMSPEC_ATAK_CompromiseView";
private _myIds = {
    private _tuid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
    if !(_tuid isEqualType "") then { _tuid = ""; };
    [_tuid, ([player] call comspec_atak_native_fnc_phoneIdent) param [1, ""]]
};
// Pistes amies figées : dernière image du flux BFT reçue avant la coupure (40 au plus).
private _snapshot = {
    private _snap = [];
    {
        private _e = _y;
        private _p = _e getOrDefault ["position", []];
        if (!(_e getOrDefault ["self", false]) && {(count _p) >= 2} && {(_e getOrDefault ["affiliation", ""]) isNotEqualTo "hostile"} && {(count _snap) < 40}) then {
            _snap pushBack [[_p select 0, _p select 1, 0], _e getOrDefault ["callsign", ""]];
        };
    } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
    missionNamespace setVariable ["COMSPEC_ATAK_CompSnap", _snap];
    missionNamespace setVariable ["COMSPEC_ATAK_CompSnapAt", [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString];
};
switch (_act) do {
    case "meta": {
        private _h = createHashMap;
        if !(_arg isEqualType "") exitWith { _h };
        {
            private _i = _x find "=";
            if (_i > 0) then { _h set [toLower (_x select [0, _i]), _x select [_i + 1]]; };
        } forEach (_arg splitString ";");
        _h
    };
    case "set": {
        if (!hasInterface || {!(_arg isEqualType createHashMap)}) exitWith {};
        ([] call _myIds) params ["_tuid", "_imei"];
        private _old = createHashMapFromArray ((profileNamespace getVariable [_key, []]) param [1, []]);
        private _h = +_arg;
        if ((_h getOrDefault ["terminal", ""]) isEqualTo "") then { _h set ["terminal", _tuid]; };
        if ((_h getOrDefault ["imei", ""]) isEqualTo "") then { _h set ["imei", _imei]; };
        // Même compromission rejouée (avis non validé revu à l'arrivée en partie) : heure et pistes d'origine gardées.
        private _same = (_old getOrDefault ["ref", "~"]) isEqualTo (_h getOrDefault ["ref", ""]) && {(_h getOrDefault ["ref", ""]) isNotEqualTo ""};
        _h set ["at", [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _old getOrDefault ["at", ""]] select _same];
        if (!_same || {isNil "COMSPEC_ATAK_CompSnap"}) then { call _snapshot; };
        profileNamespace setVariable [_key, [_h getOrDefault ["match", "terminal"], _h toArray false]];
        saveProfileNamespace;
        ["WARN", "CAPTURE", format ["Mode compromis : %1 %2 (%3) · %4", _h getOrDefault ["origin", ""], _h getOrDefault ["state", ""], _h getOrDefault ["source", ""], _h getOrDefault ["reason", ""]]] call comspec_atak_native_fnc_log;
        ["refresh"] call comspec_atak_native_fnc_compromiseView;
    };
    case "clear": {
        if (isNil {profileNamespace getVariable _key}) exitWith {};
        profileNamespace setVariable [_key, nil];
        saveProfileNamespace;
        missionNamespace setVariable ["COMSPEC_ATAK_CompSnap", nil];
        missionNamespace setVariable ["COMSPEC_ATAK_CompActive", createHashMap];
        ["INFO", "CAPTURE", "Mode compromis levé"] call comspec_atak_native_fnc_log;
    };
    case "refresh": {
        if (!hasInterface || {isNull player}) exitWith {};
        private _on = createHashMap;
        if ([player, true] call comspec_atak_native_fnc_hasDevice) then {
            ([] call _myIds) params ["_tuid", "_imei"];
            private _st = profileNamespace getVariable [_key, []];
            if ((count _st) >= 2) then {
                private _h = createHashMapFromArray (_st select 1);
                private _ok = if ((_st select 0) isEqualTo "imei") then { (_h getOrDefault ["imei", ""]) isEqualTo _imei } else {
                    private _t = _h getOrDefault ["terminal", ""];
                    _t isEqualTo "" || {_t isEqualTo _tuid}
                };
                if (_ok) then { _on = _h; };
            };
            // Effet posé en jeu par le Zeus (COMSPEC Overwatch, remonté à Athena avec la position).
            private _z = toLower (missionNamespace getVariable ["COMSPEC_CompromiseState", "none"]);
            if ((count _on) isEqualTo 0 && {_z in ["captured", "compromised"]}) then {
                _on = createHashMapFromArray [["origin", "manual"], ["source", "zeus"], ["state", _z], ["terminal", _tuid], ["imei", _imei],
                    ["at", missionNamespace getVariable ["COMSPEC_ATAK_CompZeusAt", ""]]];
                if ((_on get "at") isEqualTo "") then {
                    missionNamespace setVariable ["COMSPEC_ATAK_CompZeusAt", [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString];
                    _on set ["at", missionNamespace getVariable ["COMSPEC_ATAK_CompZeusAt", ""]];
                };
            };
            if (_z isEqualTo "none") then { missionNamespace setVariable ["COMSPEC_ATAK_CompZeusAt", nil]; };
        };
        private _was = (count (missionNamespace getVariable ["COMSPEC_ATAK_CompActive", createHashMap])) > 0;
        missionNamespace setVariable ["COMSPEC_ATAK_CompActive", _on];
        // Pistes figées prises à l'entrée dans le mode (y compris à l'arrivée en partie).
        if ((count _on) > 0 && {!_was} && {isNil "COMSPEC_ATAK_CompSnap"}) then { call _snapshot; };
        if (_was isNotEqualTo ((count _on) > 0) && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP"}) then {
            [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
    case "get": { missionNamespace getVariable ["COMSPEC_ATAK_CompActive", createHashMap] };
    case "skip": { (count (missionNamespace getVariable ["COMSPEC_ATAK_CompActive", createHashMap])) > 0 };
    case "draw": {
        private _map = _arg;
        private _c = missionNamespace getVariable ["COMSPEC_ATAK_CompActive", createHashMap];
        if (isNull _map || {(count _c) isEqualTo 0}) exitWith {};
        (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
        private _w2s = { _map ctrlMapScreenToWorld _this };
        private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
        private _none = "#(argb,8,8,3)color(0,0,0,0)";
        // Rectangle à l'écran -> rectangle du monde (la carte n'est jamais tournée).
        private _rect = {
            params ["_sx", "_sy", "_sw", "_sh", "_rgba", ["_tex", _fill]];
            private _a = [_sx, _sy] call _w2s;
            private _b = [_sx + _sw, _sy + _sh] call _w2s;
            _map drawRectangle [[((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2, 0], abs ((_b select 0) - (_a select 0)) / 2, abs ((_b select 1) - (_a select 1)) / 2, 0, _rgba, _tex];
        };
        private _text = {
            params ["_sx", "_sy", "_txt", "_rgba", "_size", ["_font", "EtelkaMonospacePro"], ["_align", "right"]];
            _map drawIcon [_none, _rgba, ([_sx, _sy] call _w2s) + [0], 0, 0, 0, _txt call comspec_atak_native_fnc_tr, 0, _size, _font, _align];
        };
        private _blink = 0.55 + 0.45 * (abs sin (diag_tickTime * 220));
        private _kRaw = _mh / (safeZoneH * 0.7);
        private _kt = (_kRaw max 0.8) min 1.25;
        // Fonds de carte et couches réseau grisés, hachures rouges.
        [_mx, _my, _mw, _mh, [0.06, 0.06, 0.06, 0.55]] call _rect;
        [_mx, _my, _mw, _mh, [0.75, 0.10, 0.08, 0.22], "\A3\ui_f\data\map\markerbrushes\bdiagonal_ca.paa"] call _rect;
        // Pistes amies figées : gris, croix, « dernière position connue ».
        private _snapAt = missionNamespace getVariable ["COMSPEC_ATAK_CompSnapAt", _c getOrDefault ["at", ""]];
        private _sz = 18 * _kt;
        {
            _x params ["_p", "_cs"];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [0.62, 0.64, 0.63, 0.75], _p, _sz * 0.7, _sz * 0.7, 0, "", 0];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\unknown_CA.paa", [0.86, 0.30, 0.24, 0.65], _p, _sz, _sz, 0, "", 0];
            if (_cs isNotEqualTo "" && {ctrlMapScale _map < 0.2}) then {
                _map drawIcon [_none, [0.78, 0.80, 0.79, 0.8], _p, _sz, _sz, 0, format ["%1 · gelé %2", _cs, _snapAt select [0, 5]] call comspec_atak_native_fnc_tr, 2, 0.022 * _kt, "EtelkaMonospacePro", "right"];
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_CompSnap", []]);
        // Ma position : GPS local, toujours en direct (au-dessus du voile).
        private _me = getPosASL (vehicle player);
        _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [0.20, 0.90, 0.95, 0.9], [_me select 0, _me select 1, 0], _sz * 1.4, _sz * 1.4, 0, "", 0];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [0.20, 0.90, 0.95, 1], [_me select 0, _me select 1, 0], _sz * 0.8, _sz * 0.8, 0, ("GPS local") call comspec_atak_native_fnc_tr, 2, 0.022 * _kt, "EtelkaMonospaceProBold", "right"];
        // Bandeau d'état en haut de la carte.
        private _bw = _mw * 0.62;
        private _bx = _mx + (_mw - _bw) / 2;
        private _by = _my + _mh * 0.035;
        private _bh = 0.085 * _kt * 0.62;
        [_bx, _by, _bw, _bh, [0.55, 0.05, 0.04, 0.92]] call _rect;
        [_bx, _by, _bw * 0.012, _bh, [1, 0.85, 0.80, _blink]] call _rect;
        private _captured = (_c getOrDefault ["state", "compromised"]) isEqualTo "captured";
        [_bx + _bw / 2, _by + _bh * 0.32, "LIAISON DE DONNÉES COUPÉE", [1, 1, 1, 1], 0.040 * _kt, (COMSPEC_ATAK_FontSet select 1), "center"] call _text;
        [_bx + _bw / 2, _by + _bh * 0.74, ["CERTIFICATS RÉVOQUÉS · TERMINAL EXCLU DU RÉSEAU", "TERMINAL CAPTURÉ · MIS EN QUARANTAINE PAR ATHENA"] select _captured, [1, 0.86, 0.82, 1], 0.026 * _kt, "EtelkaMonospaceProBold", "center"] call _text;
        // HUD (petite carte du téléphone porté) : bandeau seul.
        if (_kRaw < 0.55) exitWith {};
        // Console d'erreurs.
        private _origin = _c getOrDefault ["origin", "manual"];
        private _src = _c getOrDefault ["source", ""];
        private _srcTxt = switch (_src) do {
            case "admin": { "Administration ATHENA" };
            case "gestion": { "Gestion de la communauté" };
            case "web": { "Poste de commandement" };
            case "game": { "Validée sur le terrain" };
            case "position": { "Remontée du terrain" };
            case "field": { "Capture validée sur le terrain" };
            case "zeus": { "Zeus de la mission" };
            default { "Athena" };
        };
        private _originTxt = format ["%1 · %2", ["MANUELLE", "AUTOMATIQUE"] select (_origin isEqualTo "auto"), _srcTxt];
        private _revoked = parseNumber (_c getOrDefault ["revoked", "0"]);
        private _tuid = _c getOrDefault ["terminal", ""];
        private _tShort = [_tuid, (_tuid select [0, 8]) + "…" + (_tuid select [(count _tuid) - 4])] select ((count _tuid) > 14);
        private _feed = count (missionNamespace getVariable ["COMSPEC_ATAK_CompSnap", []]);
        private _red = [1, 0.36, 0.30, 1];
        private _amb = [1, 0.72, 0.30, 1];
        private _dim = [0.62, 0.68, 0.65, 1];
        private _wht = [0.92, 0.95, 0.93, 1];
        private _grn = [0.36, 0.85, 0.42, 1];
        private _lines = [
            ["COMSPEC LINK · DIAGNOSTIC", _wht, "EtelkaMonospaceProBold"],
            ["", _dim],
            [[format ["E-401  TLS  certificat révoqué (keyCompromise) · %1 cert.", _revoked max 1], "E-403  ACL  terminal en quarantaine (capturé)"] select _captured, _red],
            [format ["E-503  BFT  flux amis perdus · 0/%1 piste(s) en direct", _feed], _red],
            ["E-504  CoT  routeur Athena injoignable · envoi refusé", _red],
            ["E-410  MAP  couches réseau gelées · fonds de carte en cache", _amb],
            ["  OK   GPS  position locale en direct", _grn],
            ["", _dim],
            [format ["TERMINAL   %1", [_tShort, "—"] select (_tShort isEqualTo "")], _wht],
            [format ["IMEI       %1", _c getOrDefault ["imei", "—"]], _wht],
            [format ["ÉTAT       %1", ["COMPROMIS", "CAPTURÉ"] select _captured], _red],
            [format ["ORIGINE    %1", _originTxt], [_amb, _red] select (_origin isEqualTo "auto")],
            [format ["MOTIF      %1", [_c getOrDefault ["reason", ""], "—"] select ((_c getOrDefault ["reason", ""]) isEqualTo "")], _wht],
            [format ["DÉCLARÉ    %1%2", (_c getOrDefault ["at", ""]) select [0, 5], [format [" · par %1", _c getOrDefault ["by", ""]], ""] select ((_c getOrDefault ["by", ""]) isEqualTo "")], _wht],
            [format ["DERNIÈRE SYNCHRO  %1", _snapAt], _amb]
        ];
        private _fsz = 0.0205 * _kt;
        private _lh = _fsz * 0.92;
        private _cw = _mw * 0.56;
        private _cx = _mx + _mw * 0.06;
        private _cy = _by + _bh + _mh * 0.05;
        private _ch = _lh * ((count _lines) + 0.8);
        if (_cy + _ch > _my + _mh * 0.80) then { _cy = (_my + _mh * 0.80 - _ch) max (_by + _bh); };
        [_cx, _cy, _cw, _ch, [0.015, 0.018, 0.016, 0.90]] call _rect;
        [_cx, _cy, _cw, _lh * 0.12, [0.80, 0.14, 0.10, 1]] call _rect;
        // Curseur clignotant de terminal à droite du titre.
        [_cx + _cw - _lh * 0.9, _cy + _lh * 0.45, _lh * 0.45, _lh * 0.55, [1, 0.36, 0.30, _blink]] call _rect;
        {
            _x params ["_t", "_rgba", ["_font", "EtelkaMonospacePro"]];
            if (_t isNotEqualTo "") then { [_cx + _lh * 0.5, _cy + _lh * (_forEachIndex + 0.75), _t, _rgba, _fsz, _font] call _text; };
        } forEach _lines;
    };
};
