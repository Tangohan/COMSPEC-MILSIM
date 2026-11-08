/*
    FIRES court : cible pointée sur la carte, munition, envoi en un geste, à côté de l'app CAS et du 9-LINE, inchangés.
    L'envoi réutilise les briefs air (fn_airBrief, 5-LINE en saisie libre, ref « Q… ») : même liaison (fn_netSend, file hors
    réseau), même boîte du pilote, mêmes accusés (REÇU, LU, WILCO, UNABLE, plus DONE), même page /9-line sur Athena.
    À l'envoi : ping du camp (fn_mapPing), marqueur (carte du téléphone, carte d'Arma selon le réglage de diffusion des
    marqueurs, web par fn_markerWeb), demande aux pilotes et JTAC du camp en ligne (fn_appSpaces "kindOf").
    État : uiNamespace COMSPEC_ATAK_FiresQ (target, open, aim) ; munition gardée dans le profil COMSPEC_ATAK_FiresQAmmo.
    Demandes envoyées : missionNamespace COMSPEC_ATAK_FiresQSent, ref -> [marqueur, [unités]].
    Params : [action, argument]
      "can" -> FIRES court permis (app ni retirée ni interdite)
      "armed" -> un clic sur la carte pose la cible ; "open", position ou [] : ouvre l'écran ; "panel" : montre ou cache le panneau
      "target", position ; "ammo", clé ; "ammoList" -> [[clé, libellé]...] ; "ammoNow" -> clé
      "send" : envoie ; "full" : ouvre le 9-LINE complet avec la cible ; "recipients" -> [[indicatif, unité]...]
      "missions" -> briefs à traiter, du plus récent au plus ancien (hors clôturés) ; "tpos", brief -> position de la cible ou []
      "mine" -> demandes envoyées encore ouvertes ; "cancel", ref ; "closed", ref : la demande est terminée, marqueur retiré
      "aim" : touche FIRES au viseur (téléphone rangé) ; "keyAck" : touche mission, accepte puis clôture
      "tick" : labels de la carte (une fois par seconde) ; "draw", carte : dessin de l'écran (une image, très peu d'appels)
*/
params [["_act", ""], ["_arg", []]];
private _st = uiNamespace getVariable "COMSPEC_ATAK_FiresQ";
if (isNil "_st") then { _st = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_FiresQ", _st]; };
private _sent = missionNamespace getVariable "COMSPEC_ATAK_FiresQSent";
if (isNil "_sent") then { _sent = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_FiresQSent", _sent]; };
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _redraw = {
    [{
        private _u = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_u getOrDefault ["activePage", ""]) isEqualTo "MAP" && {(_u getOrDefault ["quickView", ""]) isNotEqualTo ""}) then { ["MAP"] call comspec_atak_native_fnc_pageRender; };
    }] call comspec_atak_native_fnc_nextFrame;
};
private _ammoList = [["CANON", "Canon"], ["ROQUETTES", "Roquettes"], ["MISSILE", "Missile"], ["BOMBE", "Bombe"], ["LIBRE", "Au choix"]];
private _ammoNow = {
    private _a = profileNamespace getVariable ["COMSPEC_ATAK_FiresQAmmo", "LIBRE"];
    if (_a isEqualType "" && {(_ammoList findIf { (_x select 0) isEqualTo _a }) >= 0}) then { _a } else { "LIBRE" }
};
private _ammoName = { params ["_k"]; ((_ammoList select { (_x select 0) isEqualTo _k }) param [0, ["", _k]]) select 1 };
private _recipients = {
    private _out = [];
    private _me = group player;
    private _seenV = [];
    {
        private _u = _x;
        if (_u isEqualTo player || {!alive _u} || {side group _u isNotEqualTo side _me} || {!([_u] call comspec_atak_native_fnc_hasDevice)}) then { continue };
        if ((["kindOf", _u] call comspec_atak_native_fnc_appSpaces) isEqualTo "") then { continue };
        private _v = vehicle _u;
        if (_v isNotEqualTo _u && {_v isKindOf "Air"}) then {
            if (_v in _seenV) then { continue };
            _seenV pushBack _v;
            _out pushBack [["airCs", _v] call comspec_atak_native_fnc_airBrief, _v];
        } else {
            _out pushBack [[_u] call comspec_atak_native_fnc_unitCallsign, _u];
        };
    } forEach allPlayers;
    _out
};
private _send = {
    params ["_pos", "_ammo"];
    private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
    private _ammoTxt = ([_ammo] call _ammoName) call comspec_atak_native_fnc_tr;
    private _cs = [player, true] call comspec_atak_native_fnc_unitCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    ["send", _pos] call comspec_atak_native_fnc_mapPing;
    // Marqueur : même schéma que les marqueurs posés à la main (canal Camp, sinon le canal courant), effacé à la clôture ou après 30 min.
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _i];
    private _m = "";
    {
        _m = ["create", [format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _i, _x], _pos, _x]] call comspec_atak_native_fnc_markerNet;
        if (_m isNotEqualTo "") exitWith {};
    } forEach ([1, currentChannel] arrayIntersect [1, currentChannel]);
    if (_m isNotEqualTo "") then {
        _m setMarkerTypeLocal "mil_destroy";
        _m setMarkerColorLocal "ColorRed";
        _m setMarkerText (format ["FIRES %1 · %2", _ammoTxt, _cs] select [0, 60]);
        [_m] call comspec_atak_native_fnc_markerWeb;
    };
    private _dest = call _recipients;
    private _none = (count _dest) isEqualTo 0;
    // Personne en ligne : la demande reste visible sur le web et la carte, sous un indicatif générique.
    if (_none) then { _dest = [["PILOTES", objNull]]; };
    private _ref = format ["Q%1%2", getPlayerUID player, round (diag_tickTime * 1000)];
    private _lines = [_cs, [player, 8] call comspec_atak_native_fnc_gridRef, _grid, _ammoTxt, "FIRES"];
    ["send", ["5", _lines, "", _dest, _ref, true]] call comspec_atak_native_fnc_airBrief;
    _sent set [_ref, [_m, (_dest apply { _x select 1 }) select { !isNull _x }, _grid, _ammo]];
    [{ ["closed", _this] call comspec_atak_native_fnc_firesQuick; }, _ref, 1800] call comspec_atak_native_fnc_waitAndExec;
    if (_none) then { ["WARNING", "Aucun pilote ni JTAC en ligne : demande posée sur la carte et le site", 5, 40] call comspec_atak_native_fnc_notify; };
    ["INFO", "FIRES", format ["FIRES court %1 %2 vers %3", _grid, _ammo, count _dest]] call comspec_atak_native_fnc_log;
    _ref
};
private _closeMine = {
    params ["_ref"];
    private _e = _sent getOrDefault [_ref, []];
    if ((count _e) isEqualTo 0) exitWith { false };
    _e params ["_m"];
    if (_m isNotEqualTo "" && {(markerShape _m) isNotEqualTo ""}) then { [_m, true] call comspec_atak_native_fnc_markerWeb; deleteMarker _m; };
    _sent deleteAt _ref;
    true
};
switch (_act) do {
    case "can": {
        private _a = ((([] call comspec_atak_native_fnc_appList) select { (_x get "id") isEqualTo "FiresQuick" }) param [0, createHashMap]);
        (count _a) > 0 && {(["state", "FiresQuick"] call comspec_atak_native_fnc_appInstall) isNotEqualTo "forbidden"}
    };
    case "armed": { ((_ui getOrDefault ["quickView", ""]) isEqualTo "FIRES" || {_st getOrDefault ["open", false]}) && {["can"] call comspec_atak_native_fnc_firesQuick} };
    case "open": {
        if !(["can"] call comspec_atak_native_fnc_firesQuick) exitWith { ["WARNING", "FIRES court n'est pas disponible sur ce serveur", 3, 20] call comspec_atak_native_fnc_notify; false };
        if ((count _arg) >= 2) then { _st set ["target", [_arg select 0, _arg select 1, 0]]; };
        [{ ["FIRESQ"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "panel": {
        _st set ["open", !(_st getOrDefault ["open", false])];
        call _redraw;
        true
    };
    case "target": {
        _st set ["target", [_arg select 0, _arg select 1, 0]];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "ammo": {
        profileNamespace setVariable ["COMSPEC_ATAK_FiresQAmmo", _arg];
        call _redraw;
        true
    };
    case "ammoList": { _ammoList };
    case "ammoNow": { call _ammoNow };
    case "ammoName": { [_arg] call _ammoName };
    case "recipients": { call _recipients };
    case "send": {
        private _t = _st getOrDefault ["target", []];
        if ((count _t) < 2) exitWith { ["WARNING", "Touchez d'abord la cible sur la carte", 3, 30] call comspec_atak_native_fnc_notify; "" };
        if ((diag_tickTime - (_st getOrDefault ["sentAt", -1e9])) < 3) exitWith { "" };
        _st set ["sentAt", diag_tickTime];
        private _ref = [_t, call _ammoNow] call _send;
        _st set ["target", []];
        _st set ["open", false];
        [] call comspec_atak_native_fnc_saveProfile;
        call _redraw;
        _ref
    };
    case "full": {
        private _t = _st getOrDefault ["target", []];
        private _f = [] call comspec_atak_native_fnc_firesState;
        _f set ["tab", "nine"];
        if ((count _t) >= 2) exitWith { ["picked", _t] call comspec_atak_native_fnc_firesAction; };
        [{ ["FIRES"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "missions": {
        (missionNamespace getVariable ["COMSPEC_ATAK_AirInbox", []]) select { (_x getOrDefault ["ack", ""]) isNotEqualTo "DONE" }
    };
    case "tpos": {
        private _b = _arg;
        private _c = _b getOrDefault ["tpos", -1];
        if (_c isEqualType []) exitWith { _c };
        private _g = (_b getOrDefault ["lines", []]) param [[5, 2] select ((_b getOrDefault ["fmt", "9"]) isEqualTo "5"), ""];
        private _p = ([_g] call comspec_atak_native_fnc_gridParse) param [0, []];
        _b set ["tpos", _p];
        _p
    };
    case "mine": { keys _sent };
    case "mineInfo": { _sent getOrDefault [_arg, []] };
    case "cancel": {
        private _e = _sent getOrDefault [_arg, []];
        if ((count _e) isEqualTo 0) exitWith { false };
        private _units = _e select 1;
        if ((count _units) > 0) then {
            [{ params ["_ref", "_units"]; ["comspec_atak_native_airBrief", ["close", _ref, [], [player] call comspec_atak_native_fnc_unitCallsign, player], _units] call CBA_fnc_targetEvent; }, [_arg, _units], "Fin de demande FIRES", 1] call comspec_atak_native_fnc_netSend;
        };
        [_arg] call _closeMine;
        ["INFO", "Demande FIRES close", 3, 30] call comspec_atak_native_fnc_notify;
        call _redraw;
        true
    };
    case "closed": { [_arg] call _closeMine; call _redraw; };
    case "aim": {
        if !(["can"] call comspec_atak_native_fnc_firesQuick) exitWith { false };
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false };
        private _armAt = _st getOrDefault ["aimAt", -1e9];
        // Second appui dans les 5 s : envoi. Le premier ne fait que proposer le point (rien ne part par mégarde).
        if ((diag_tickTime - _armAt) < 5 && {(count (_st getOrDefault ["target", []])) >= 2}) exitWith {
            _st set ["aimAt", -1e9];
            ["send"] call comspec_atak_native_fnc_firesQuick;
            ["hudNow"] call comspec_atak_native_fnc_pilotMode;
            true
        };
        private _lt = laserTarget (vehicle player);
        private _p = if (!isNull _lt) then { getPosATL _lt } else { screenToWorld [0.5, 0.5] };
        if ((_p distance2D [0, 0, 0]) < 1) exitWith { false };
        _st set ["target", [_p select 0, _p select 1, 0]];
        _st set ["aimAt", diag_tickTime];
        playSound "ClickSoft";
        ["hudNow"] call comspec_atak_native_fnc_pilotMode;
        true
    };
    case "aimHint": {
        if ((diag_tickTime - (_st getOrDefault ["aimAt", -1e9])) >= 5) exitWith { "" };
        format [("FIRES %1 · %2 : rappuyez pour envoyer") call comspec_atak_native_fnc_tr, ([call _ammoNow] call _ammoName) call comspec_atak_native_fnc_tr, [_st getOrDefault ["target", [0, 0, 0]], 8] call comspec_atak_native_fnc_gridRef]
    };
    case "keyAck": {
        private _b = (["missions"] call comspec_atak_native_fnc_firesQuick) param [0, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        ["ack", [_b get "ref", ["WILCO", "DONE"] select ((_b getOrDefault ["ack", ""]) isEqualTo "WILCO")]] call comspec_atak_native_fnc_airBrief;
        ["hudNow"] call comspec_atak_native_fnc_pilotMode;
        true
    };
    case "tick": {
        // Appareils alliés déjà connus du téléphone (pistes de fn_localDataRefresh : liaison, PLI et règles de camp respectées),
        // une ligne par appareil. Altitude et cap viennent de la dernière position reçue, jamais de l'objet en direct.
        private _rows = [];
        private _seenV = [];
        {
            if ((_y getOrDefault ["affiliation", ""]) isNotEqualTo "friend" || {(_y getOrDefault ["self", false])}) then { continue };
            private _o = _y getOrDefault ["object", objNull];
            if (isNull _o) then { continue };
            private _v = vehicle _o;
            if !(_v isKindOf "Air") then { continue };
            if (_v in _seenV || {_v isEqualTo (vehicle player)}) then { continue };
            _seenV pushBack _v;
            private _p = _y getOrDefault ["position", [0, 0, 0]];
            if !(_p isEqualType [] && {(count _p) >= 2}) then { continue };
            private _alt = round (((_p param [2, 0]) - (getTerrainHeightASL _p)) max 0);
            private _hd = [(round (_y getOrDefault ["heading", 0])) mod 360, 3] call CBA_fnc_formatNumber;
            _rows pushBack [_p, format ["%1  %2 m  %3°", ["airCs", _v] call comspec_atak_native_fnc_airBrief, _alt, _hd], (_y getOrDefault ["freshness", "LIVE"]) isEqualTo "LIVE"];
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
        uiNamespace setVariable ["COMSPEC_ATAK_QvAir", _rows];
        // Cibles des briefs reçus : [position, texte, acceptée].
        private _tg = [];
        {
            private _p = ["tpos", _x] call comspec_atak_native_fnc_firesQuick;
            if ((count _p) >= 2) then { _tg pushBack [_p, _x getOrDefault ["from", ""], (_x getOrDefault ["ack", ""]) isEqualTo "WILCO"]; };
        } forEach (["missions"] call comspec_atak_native_fnc_firesQuick);
        uiNamespace setVariable ["COMSPEC_ATAK_QvTgt", _tg];
    };
    case "draw": {
        private _map = _arg;
        private _th = COMSPEC_ATAK_Th;
        private _none = "#(argb,8,8,3)color(0,0,0,0)";
        private _sz = COMSPEC_ATAK_FontMin max (pixelH * 15);
        private _font = COMSPEC_ATAK_FontSet select 1;
        private _dy = _sz * 1.1;
        {
            _x params ["_p", "_label", "_live"];
            private _sc = _map ctrlMapWorldToScreen _p;
            private _at = _map ctrlMapScreenToWorld [_sc select 0, (_sc select 1) + _dy];
            _map drawIcon [_none, [_th get "mapDim", _th get "mapText"] select _live, _at, 28, 28, 0, _label, 2, _sz, _font, "right"];
        } forEach (uiNamespace getVariable ["COMSPEC_ATAK_QvAir", []]);
        private _ic = "\A3\ui_f\data\map\markers\military\destroy_CA.paa";
        private _ring = "\A3\ui_f\data\map\markers\military\circle_CA.paa";
        {
            _x params ["_p", "_label", "_acc"];
            _map drawIcon [_ic, _th get "mapAlert", _p, 30, 30, 0, _label, 2, _sz, _font, "right"];
            if (_acc) then { _map drawLine [uiNamespace getVariable ["COMSPEC_ATAK_SelfGpsPos", getPosASL player], _p, _th get "mapAlert"]; };
        } forEach (uiNamespace getVariable ["COMSPEC_ATAK_QvTgt", []]);
        private _tgt = _st getOrDefault ["target", []];
        if ((count _tgt) >= 2) then {
            private _ph = (diag_tickTime % 1) * 12;
            _map drawIcon [_ring, _th get "mapWarn", _tgt, 26 + _ph, 26 + _ph, 0, "", 0];
            _map drawIcon [_ic, _th get "mapWarn", _tgt, 26, 26, 0, "", 0];
        };
    };
};
