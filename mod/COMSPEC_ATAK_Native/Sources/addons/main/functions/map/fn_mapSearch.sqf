/*
    Carte : bouton « RECHERCHER ». Localise un téléphone (IMEI / MAC ou numéro), une balise GPS ou un allié
    (indicatif / nom), centre la carte, pose un marqueur pulsant et une fiche (propriétaire, vu à, précision, SUIVRE).
    État : uiNamespace COMSPEC_ATAK_MapSearch (HashMap) : open, type (IMEI | NUM | GPS | CS), q, msg, hit, pfh.
    Résultat (hit) : kind (PHONE | GPS | CS), sub, q, qn, obj, id, owner, pos, rad, t, hour, status, why, follow, checked.
    Règles :
      - téléphone (IMEI, MAC, numéro) : règles GÉOLOC de l'app GE (réglages CBA « Géolocalisation (GEOLOC) ») :
        serveur, réservé GE, camps traçables, son propre camp, IA ; requête opérateur de 3 à 7 s, recharge de 10 s
        partagée avec l'app GE ; position dans un cercle d'incertitude (réglage Précision, plus large à 1 barre) ;
        il faut du réseau sur MON téléphone ; échec si la cible est éteinte, en mode avion, sans signal ou brouillée ;
        suivi : nouvelle triangulation à l'intervalle « Rafraîchissement du suivi » ;
      - balise GPS (COMSPEC_GpsBeacon d'Overwatch, Eden / Zeus) : position exacte tant qu'elle est allumée
        (objet intact, balise active, hors brouillage) ; il faut du réseau sur mon téléphone ;
      - indicatif / nom : image BFT de mon camp (COMSPEC_ATAK_Data units), figée si l'allié est hors ligne.
    Aucun balayage par image : résolution à la demande, suivi relu toutes les 2 s (PFH tant qu'un résultat existe).
    Params : [action, argument]
      "toggle" | "open" | "close"    "type" k    "go"    "filter" (frappe dans le champ)    "pick" (liste)
      "follow"    "center"    "clear"    "tick"    "panel" rect    "card" rect    "draw" carte    "known" type
*/
params [["_act", ""], ["_arg", ""]];
disableSerialization;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MapSearch", _st];
private _type = _st getOrDefault ["type", "IMEI"];
private _hit = _st getOrDefault ["hit", createHashMap];
private _now = [time, serverTime] select isMultiplayer;
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _norm = {
    params ["_q"];
    private _n = toString ((toArray toUpper _q) select { (_x >= 48 && _x <= 57) || {_x >= 65 && _x <= 90} });
    // Numéro international (+33 6…, 0033 6…) ramené au format national.
    if ((_n find "0033") isEqualTo 0) then { _n = _n select [2]; };
    if ((_n find "33") isEqualTo 0 && {(count _n) isEqualTo 11}) then { _n = "0" + (_n select [2]); };
    _n
};
private _myBars = { (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) };
// Brouilleur actif sur cette position (le filtrage ami épargne le camp du brouilleur).
private _jammedAt = {
    params ["_pos", "_side"];
    ((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) findIf {
        _x params [["_jp", [0, 0, 0]], ["_jr", 500], "", ["_js", ""], ["_ju", 1e9], ["_jx", false]];
        if (_jp isEqualType objNull) then { _jp = if (isNull _jp) then { [] } else { getPosATL _jp }; };
        (count _jp) >= 2 && {_ju >= _now} && {!(_jx && {_js isEqualTo _side})} && {(_pos distance2D [_jp select 0, _jp select 1]) < _jr}
    }) >= 0
};
private _isBeacon = {
    params ["_o"];
    if (isNull _o) exitWith { false };
    private _v = _o getVariable ["COMSPEC_GpsBeacon", false];
    if (_v isEqualType true) exitWith { _v };
    if (_v isEqualType 0) exitWith { _v > 0 };
    if (_v isEqualType "") exitWith { (toLower (trim _v)) in ["1", "true", "yes", "oui"] };
    false
};
private _beaconId = {
    params ["_o"];
    private _id = _o getVariable ["COMSPEC_GpsBeaconId", ""];
    if !(_id isEqualType "") then { _id = str _id; };
    if (_id isEqualTo "") then { _id = format ["GPS-%1", ((netId _o) splitString ":") joinString "-"]; };
    _id
};
private _beaconName = {
    params ["_o"];
    private _n = _o getVariable ["COMSPEC_GpsCallsign", ""];
    if !(_n isEqualType "") then { _n = str _n; };
    if (_n isEqualTo "") then { _n = getText (configOf _o >> "displayName"); };
    [_n, typeOf _o] select (_n isEqualTo "")
};
// Balises connues : liste tenue par COMSPEC Link (Overwatch) + véhicules marqués, relue au plus toutes les 10 s.
private _beacons = {
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_MapSearchBeacons", [-1e9, []]];
    if (diag_tickTime - (_c select 0) < 10) exitWith { (_c select 1) select { !isNull _x } };
    private _l = missionNamespace getVariable ["COMSPEC_GpsBeaconObjects", []];
    if !(_l isEqualType []) then { _l = []; };
    _l = (_l select { _x isEqualType objNull && {!isNull _x} }) + (vehicles select { !isNil { _x getVariable "COMSPEC_GpsBeacon" } });
    _l = _l arrayIntersect _l;
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearchBeacons", [diag_tickTime, _l]];
    _l
};
private _bftUnits = { (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap] };
private _hourNow = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _reasonTxt = {
    params ["_code"];
    switch (_code) do {
        case "OFF": { "Téléphone éteint" };
        case "AIR": { "Hors couverture (mode avion)" };
        case "JAM": { "Hors couverture (brouillage)" };
        case "NOSIG": { "Hors couverture" };
        case "LOST": { "Inconnu (abonné introuvable)" };
        case "GPSOFF": { "Balise éteinte" };
        case "GPSDEAD": { "Balise détruite" };
        case "BFTOFF": { "Téléphone éteint ou sans réseau" };
        case "BFTGONE": { "Inconnu (hors du suivi BFT)" };
        case "MYNET": { "Pas de réseau sur votre téléphone" };
        case "PENDING": { "Requête opérateur en cours…" };
        default { "" };
    }
};
// Nouvelle position du résultat. Renvoie true si la position a été mise à jour.
private _fix = {
    params ["_h"];
    private _kind = _h get "kind";
    private _o = _h getOrDefault ["obj", objNull];
    private _code = "OK";
    private _pos = [];
    private _rad = 0;
    switch (_kind) do {
        case "PHONE": {
            private _bars = 4;
            _code = switch (true) do {
                case ((call _myBars) <= 0): { "MYNET" };
                case (isNull _o || {!alive _o}): { "LOST" };
                case !((_h get "qn") in ([_o, "norm"] call comspec_atak_native_fnc_phoneIdent)): { "LOST" };
                case !([_o] call comspec_atak_native_fnc_hasDevice): { "OFF" };
                case (isPlayer _o && {_o getVariable ["COMSPEC_ATAK_Airplane", false]}): { "AIR" };
                case (isPlayer _o && {((_o getVariable ["COMSPEC_ATAK_Pub", []]) param [2, ""]) in ["OFF", "BROKEN"]}): { "OFF" };
                case ([getPosATL _o, str side group _o] call _jammedAt): { "JAM" };
                case (isPlayer _o && {_bars = (_o getVariable ["COMSPEC_ATAK_Pub", []]) param [1, 4]; _bars <= 0 || {!(_o getVariable ["COMSPEC_ATAK_Beacon", true])}}): { "NOSIG" };
                default { "OK" };
            };
            if (_code isEqualTo "OK") then {
                // Triangulation par antennes : position décalée dans le rayon d'incertitude, plus large à 1 barre.
                private _prec = (missionNamespace getVariable ["comspec_atak_native_geoloc_precision", 150]) max 10;
                _rad = round (_prec * (0.6 + random 0.8) * ([1, 1.6] select (_bars <= 1)));
                private _p = (getPosATL _o) getPos [random (_rad * 0.8), random 360];
                _pos = [_p select 0, _p select 1, 0];
                if (isPlayer _o && {missionNamespace getVariable ["comspec_atak_native_geoloc_warn", false]}) then {
                    ["comspec_atak_native_geoWarn", [], _o] call CBA_fnc_targetEvent;
                };
            };
        };
        case "GPS": {
            _code = switch (true) do {
                case ((call _myBars) <= 0): { "MYNET" };
                case (isNull _o): { "LOST" };
                case !(alive _o): { "GPSDEAD" };
                case !([_o] call _isBeacon): { "GPSOFF" };
                case ([getPosATL _o, str side _o] call _jammedAt): { "JAM" };
                default { "OK" };
            };
            if (_code isEqualTo "OK") then { private _p = getPosASL _o; _pos = [_p select 0, _p select 1, 0]; _rad = 5; };
        };
        case "CS": {
            private _e = (call _bftUnits) getOrDefault [_h get "id", createHashMap];
            if ((count _e) isEqualTo 0) exitWith { _code = "BFTGONE"; };
            private _p = _e getOrDefault ["position", []];
            if ((count _p) < 2) exitWith { _code = "BFTGONE"; };
            if ((_e getOrDefault ["freshness", "LIVE"]) isEqualTo "OFFLINE") then {
                // Hors ligne : dernière position BFT gardée, mise à jour une seule fois.
                _code = "BFTOFF";
                if ((count (_h getOrDefault ["pos", []])) < 2) then { _h set ["pos", [_p select 0, _p select 1, 0]]; _h set ["rad", 15]; };
            } else {
                _pos = [_p select 0, _p select 1, 0];
                _rad = 10;
            };
        };
    };
    _h set ["status", _code];
    _h set ["why", [_code] call _reasonTxt];
    _h set ["checked", time];
    if (_code isNotEqualTo "OK") exitWith { false };
    _h set ["pos", _pos];
    _h set ["rad", _rad];
    _h set ["t", time];
    _h set ["hour", call _hourNow];
    true
};
private _center = {
    params ["_h"];
    private _p = _h getOrDefault ["pos", []];
    if ((count _p) < 2) exitWith {};
    private _sc = (0.015 + ((_h getOrDefault ["rad", 10]) / 12000)) min 0.08;
    [_p, _sc] call comspec_atak_native_fnc_mapCenter;
};
private _startPfh = {
    if ((_st getOrDefault ["pfh", -1]) >= 0) exitWith {};
    _st set ["pfh", [{ ["tick"] call comspec_atak_native_fnc_mapSearch; }, 2] call CBA_fnc_addPerFrameHandler];
};
private _stopPfh = {
    private _h = _st getOrDefault ["pfh", -1];
    if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
    _st set ["pfh", -1];
};
// Identifiants connus pour l'autocomplétion : [[libellé, valeur, propriétaire]...], gardés 5 s par type.
private _known = {
    params ["_t"];
    private _cache = (uiNamespace getVariable ["COMSPEC_ATAK_MapSearchKnown", createHashMap]) getOrDefault [_t, [-1e9, []]];
    if (diag_tickTime - (_cache select 0) < 5) exitWith { _cache select 1 };
    private _out = [];
    private _seen = [];
    private _add = {
        params ["_val", "_src", ["_owner", ""]];
        private _k = [_val] call _norm;
        if ((count _k) < 4 || {_k in _seen}) exitWith {};
        _seen pushBack _k;
        _out pushBack [format ["%1  ·  %2", _val, _src], _val, _owner];
    };
    private _idx = parseNumber (_t isNotEqualTo "NUM");
    private _rxs = [["\d{2}-\d{6}-\d{6}-\d", "\b35\d{13}\b"], ["\b0[67](?: ?\d{2}){4}\b", "\(\d{3}\) \d{3}-\d{4}"]] select (_t isEqualTo "NUM");
    private _scan = {
        params ["_txt", "_src"];
        if !(_txt isEqualType "") exitWith {};
        { { [(_x select 0) select 0, _src] call _add; } forEach (_txt regexFind [_x, 0]); } forEach _rxs;
    };
    switch (_t) do {
        case "IMEI";
        case "NUM": {
            // Historique des recherches, mon groupe, GÉOLOC de l'app GE, SMS, fichiers extraits (câble DL-7), messages.
            { if ((_x select 0) isEqualTo _t) then { [_x select 1, "Recherche", _x select 2] call _add; }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MapSearchHist", []]);
            {
                if ([_x] call comspec_atak_native_fnc_hasDevice) then {
                    private _cs = [_x] call comspec_atak_native_fnc_unitCallsign;
                    [([_x] call comspec_atak_native_fnc_phoneIdent) select _idx, ["Mon groupe · " + _cs, "Moi"] select (_x isEqualTo player), _cs] call _add;
                };
            } forEach (units group player);
            {
                private _kd = _x getOrDefault ["kind", ""];
                if ((_t isEqualTo "NUM") isEqualTo (_kd isEqualTo "NUMÉRO")) then { [_x get "q", "GÉOLOC"] call _add; };
            } forEach (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]);
            private _p2p = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["p2p", []];
            if (_t isEqualTo "NUM") then {
                private _peers = [];
                { _peers pushBackUnique (_x getOrDefault ["peer", ""]); } forEach _p2p;
                { if ((name _x) in _peers) then { [([_x] call comspec_atak_native_fnc_phoneIdent) select 0, "SMS · " + name _x, name _x] call _add; }; } forEach allPlayers;
            };
            {
                _x params [["_srcName", ""], ["_fname", ""], "", ["_content", ""]];
                [_srcName, "Extraction"] call _scan;
                [_content, format ["Extraction · %1", _srcName]] call _scan;
            } forEach (missionNamespace getVariable ["COMSPEC_ATAK_DataLib", []]);
            { [_x getOrDefault ["text", ""], "SMS · " + (_x getOrDefault ["peer", "?"])] call _scan; } forEach (_p2p select [0 max ((count _p2p) - 60), 60]);
        };
        case "GPS": {
            { [[_x] call _beaconId, [_x] call _beaconName, [_x] call _beaconName] call _add; } forEach (call _beacons);
        };
        case "CS": {
            {
                private _cs = _y getOrDefault ["callsign", ""];
                if (_cs isNotEqualTo "" && {(_y getOrDefault ["affiliation", "friend"]) isEqualTo "friend"}) then {
                    private _o = _y getOrDefault ["object", objNull];
                    _out pushBack [format ["%1  ·  %2", _cs, [name _o, "Athena"] select (isNull _o)], _cs, _cs];
                };
            } forEach (call _bftUnits);
            _out sort true;
        };
    };
    private _all = uiNamespace getVariable ["COMSPEC_ATAK_MapSearchKnown", createHashMap];
    _all set [_t, [diag_tickTime, _out]];
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearchKnown", _all];
    _out
};
private _fillList = {
    params ["_combo", "_q"];
    if (isNull _combo) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearchFilling", true];
    private _items = [_type] call _known;
    private _qn = [_q] call _norm;
    private _ql = toLower _q;
    if (_q isNotEqualTo "") then {
        _items = _items select { (_qn isNotEqualTo "" && {([_x select 1] call _norm) find _qn >= 0}) || {(toLower (_x select 0)) find _ql >= 0} };
    };
    lbClear _combo;
    _combo lbSetData [_combo lbAdd ((format ["Identifiants connus (%1)", count _items]) call comspec_atak_native_fnc_tr), ""];
    { _combo lbSetData [_combo lbAdd (((_x select 0)) call comspec_atak_native_fnc_tr), _x select 1]; } forEach (_items select [0, 40]);
    _combo lbSetCurSel 0;
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearchFilling", false];
};
private _setMsg = { params ["_t", ["_ok", false]]; _st set ["msg", [_t, _ok]]; };
private _hist = {
    params ["_val", "_owner"];
    private _h = (missionNamespace getVariable ["COMSPEC_ATAK_MapSearchHist", []]) select { !((_x select 0) isEqualTo _type && {(_x select 1) isEqualTo _val}) };
    _h pushBack [_type, _val, _owner];
    while { (count _h) > 12 } do { _h deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_MapSearchHist", _h];
};

private _ret = true;
switch (_act) do {
    case "toggle": { [["open", "close"] select (_st getOrDefault ["open", false])] call comspec_atak_native_fnc_mapSearch; };
    case "open": {
        _st set ["open", true];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["mapToolsOpen", false];
        call _rerender;
    };
    case "close": { _st set ["open", false]; call _rerender; };
    case "type": {
        if (_arg isEqualTo _type) exitWith {};
        _st set ["type", _arg];
        _st set ["q", ""];
        _st set ["msg", []];
        call _rerender;
    };
    case "filter": {
        (uiNamespace getVariable ["COMSPEC_ATAK_MapSearchCtl", [controlNull, controlNull]]) params ["_e", "_c"];
        if (isNull _e) exitWith {};
        _st set ["q", ctrlText _e];
        [_c, ctrlText _e] call _fillList;
    };
    case "pick": {
        if (uiNamespace getVariable ["COMSPEC_ATAK_MapSearchFilling", false]) exitWith {};
        (uiNamespace getVariable ["COMSPEC_ATAK_MapSearchCtl", [controlNull, controlNull]]) params ["_e", "_c"];
        if (isNull _c) exitWith {};
        private _v = _c lbData (lbCurSel _c);
        if (_v isEqualTo "") exitWith {};
        _e ctrlSetText ((_v) call comspec_atak_native_fnc_tr);
        _st set ["q", _v];
        ["go"] call comspec_atak_native_fnc_mapSearch;
    };
    case "go": {
        (uiNamespace getVariable ["COMSPEC_ATAK_MapSearchCtl", [controlNull, controlNull]]) params ["_e"];
        private _q = trim ([_st getOrDefault ["q", ""], ctrlText _e] select !isNull _e);
        _st set ["q", _q];
        if (_q isEqualTo "") exitWith { ["Saisissez ou choisissez un identifiant"] call _setMsg; call _rerender; };
        private _items = [_type] call _known;
        private _qn = [_q] call _norm;
        // Saisie partielle : complétée si un seul identifiant connu correspond.
        private _match = _items select { ([_x select 1] call _norm) isEqualTo _qn || {(toLower (_x select 1)) isEqualTo (toLower _q)} };
        if ((count _match) isEqualTo 0) then {
            private _part = _items select { (_qn isNotEqualTo "" && {([_x select 1] call _norm) find _qn >= 0}) || {(toLower (_x select 0)) find (toLower _q) >= 0} };
            if ((count _part) isEqualTo 1) then { _match = _part; _q = (_part select 0) select 1; _qn = [_q] call _norm; };
        };
        private _owner = (_match param [0, ["", "", ""]]) param [2, ""];
        private _h = createHashMapFromArray [["q", _q], ["qn", _qn], ["owner", _owner], ["pos", []], ["rad", 0], ["t", -1], ["hour", ""], ["follow", false], ["status", ""], ["why", ""]];
        switch (_type) do {
            case "IMEI";
            case "NUM": {
                (["geoAllowed"] call comspec_atak_native_fnc_ewAction) params ["_ok", "_why"];
                if !(_ok) exitWith { [_why] call _setMsg; };
                if ((count _qn) < 8) exitWith { [["Saisissez un IMEI (35-…) ou une adresse MAC complète", "Saisissez un numéro complet (06 12 34 56 78)"] select (_type isEqualTo "NUM")] call _setMsg; };
                if ((call _myBars) <= 0) exitWith { ["Pas de réseau sur votre téléphone : requête opérateur impossible"] call _setMsg; };
                private _next = uiNamespace getVariable ["COMSPEC_ATAK_GeoNext", 0];
                if (diag_tickTime < _next) exitWith { [format ["Requête opérateur en cours : réessayez dans %1 s", ceil (_next - diag_tickTime)]] call _setMsg; };
                uiNamespace setVariable ["COMSPEC_ATAK_GeoNext", diag_tickTime + 10];
                [_q, _owner] call _hist;
                // Recherche de l'abonné, une fois, à la demande (comme GÉOLOC de l'app GE).
                private _ai = missionNamespace getVariable ["comspec_atak_native_geoloc_ai", true];
                private _target = objNull; private _sub = "";
                {
                    private _k = ([_x, "norm"] call comspec_atak_native_fnc_phoneIdent) find _qn;
                    if (_k >= 0) exitWith { _target = _x; _sub = ["NUMÉRO", "IMEI", "MAC"] select _k; };
                } forEach (allUnits select { alive _x && {isPlayer _x || _ai} && {[_x] call comspec_atak_native_fnc_hasDevice} });
                if (isNull _target || {!(_target getVariable ["COMSPEC_ATAK_Traceable", true])}) exitWith { [format ["Inconnu : aucun abonné ne correspond à %1", _q]] call _setMsg; };
                private _ts = side group _target;
                private _mine = _ts isEqualTo (side group player);
                if (_mine && {_target isNotEqualTo player} && {!(missionNamespace getVariable ["comspec_atak_native_geoloc_own", false])}) exitWith { ["Numéro de votre camp : traçage non autorisé sur cette mission (cherchez par INDICATIF)"] call _setMsg; };
                private _sideKey = switch (_ts) do { case west: { "west" }; case east: { "east" }; case resistance: { "guer" }; default { "civ" }; };
                if !(missionNamespace getVariable [format ["comspec_atak_native_geoloc_%1", _sideKey], true]) exitWith { ["Opérateur hors d'atteinte : ce camp n'est pas traçable sur cette mission"] call _setMsg; };
                if (_mine) then { _h set ["owner", [_target] call comspec_atak_native_fnc_unitCallsign]; };
                _h set ["kind", "PHONE"]; _h set ["sub", _sub]; _h set ["obj", _target];
                _h set ["status", "PENDING"]; _h set ["why", ["PENDING"] call _reasonTxt];
                _st set ["hit", _h];
                _st set ["msg", []];
                _st set ["open", false];
                private _token = diag_tickTime;
                _h set ["token", _token];
                // Délai de la requête opérateur.
                [{
                    params ["_h", "_token"];
                    private _st = uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap];
                    if !((_st getOrDefault ["hit", createHashMap]) isEqualTo _h && {(_h getOrDefault ["token", -1]) isEqualTo _token}) exitWith {};
                    ["resolved"] call comspec_atak_native_fnc_mapSearch;
                }, [_h, _token], 3 + random 4] call CBA_fnc_waitAndExecute;
                call _startPfh;
            };
            case "GPS": {
                if ((call _myBars) <= 0) exitWith { ["Pas de réseau sur votre téléphone : balises injoignables"] call _setMsg; };
                private _obj = objNull;
                { if (([[_x] call _beaconId] call _norm) isEqualTo _qn || {(toLower ([_x] call _beaconName)) isEqualTo (toLower _q)}) exitWith { _obj = _x; }; } forEach (call _beacons);
                if (isNull _obj) exitWith { [format ["Inconnu : aucune balise GPS %1", _q]] call _setMsg; };
                [_q, [_obj] call _beaconName] call _hist;
                _h set ["kind", "GPS"]; _h set ["sub", "BALISE GPS"]; _h set ["obj", _obj];
                _h set ["q", [_obj] call _beaconId]; _h set ["owner", [_obj] call _beaconName];
                _st set ["hit", _h];
                ["resolved"] call comspec_atak_native_fnc_mapSearch;
                call _startPfh;
            };
            case "CS": {
                private _ql = toLower _q;
                private _id = "";
                private _best = 99;
                {
                    private _cs = toLower (_y getOrDefault ["callsign", ""]);
                    private _o = _y getOrDefault ["object", objNull];
                    private _nm = [toLower name _o, ""] select (isNull _o);
                    private _score = switch (true) do {
                        case (_cs isEqualTo _ql || {_nm isEqualTo _ql}): { 0 };
                        case ((_cs find _ql) isEqualTo 0 || {(_nm find _ql) isEqualTo 0}): { 1 };
                        case ((_cs find _ql) > 0 || {(_nm find _ql) > 0}): { 2 };
                        default { 99 };
                    };
                    if (_score < _best && {(_y getOrDefault ["affiliation", "friend"]) isEqualTo "friend"}) then { _best = _score; _id = _x; };
                } forEach (call _bftUnits);
                if (_id isEqualTo "") exitWith { [format ["Inconnu : aucun indicatif ni nom « %1 » dans le suivi BFT de votre camp", _q]] call _setMsg; };
                private _e = (call _bftUnits) get _id;
                _h set ["kind", "CS"]; _h set ["sub", "INDICATIF"]; _h set ["id", _id]; _h set ["obj", _e getOrDefault ["object", objNull]];
                _h set ["q", _e getOrDefault ["callsign", _q]]; _h set ["owner", _e getOrDefault ["callsign", _q]];
                [_h get "q", _h get "owner"] call _hist;
                _st set ["hit", _h];
                ["resolved"] call comspec_atak_native_fnc_mapSearch;
                call _startPfh;
            };
        };
        call _rerender;
    };
    case "resolved": {
        if ((count _hit) isEqualTo 0) exitWith {};
        private _ok = [_hit] call _fix;
        _st set ["open", false];
        if (_ok) then {
            [_hit] call _center;
            ["SUCCESS", format ["%1 %2 localisé : %3, à %4 m près", _hit get "sub", _hit get "q", [_hit get "pos", 6] call comspec_atak_native_fnc_gridRef, _hit get "rad"], 4, 30] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        } else {
            if ((count (_hit getOrDefault ["pos", []])) >= 2) then { [_hit] call _center; };
            ["WARNING", format ["%1 %2 : %3", _hit get "sub", _hit get "q", _hit get "why"], 5, 30] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    case "follow": {
        if ((count _hit) isEqualTo 0) exitWith {};
        _hit set ["follow", !(_hit getOrDefault ["follow", false])];
        if (_hit get "follow") then {
            // Le suivi de ma position recentrerait la carte sur moi à chaque seconde.
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapFollow", false];
            [_hit] call _center;
        };
        call _rerender;
    };
    case "center": { if ((count _hit) > 0) then { [_hit] call _center; }; };
    case "clear": {
        call _stopPfh;
        _st set ["hit", createHashMap];
        call _rerender;
    };
    case "tick": {
        if ((count _hit) isEqualTo 0) exitWith { call _stopPfh; };
        if ((_hit getOrDefault ["status", ""]) isEqualTo "PENDING") exitWith {};
        if (_hit getOrDefault ["follow", false]) then {
            // Téléphone : nouvelle triangulation à l'intervalle GÉOLOC ; balise et BFT : relus toutes les 2 s.
            private _every = [2, (missionNamespace getVariable ["comspec_atak_native_geoloc_refresh", 30]) max 10] select ((_hit get "kind") isEqualTo "PHONE");
            if ((time - (_hit getOrDefault ["checked", -1e9])) >= (_every - 0.1)) then {
                private _was = _hit getOrDefault ["status", ""];
                private _old = +(_hit getOrDefault ["pos", []]);
                [_hit] call _fix;
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                if ((_hit get "pos") isNotEqualTo _old && {(_s getOrDefault ["activePage", ""]) isEqualTo "MAP"} && {_s getOrDefault ["interactive", false]}) then {
                    [_hit get "pos"] call comspec_atak_native_fnc_mapCenter;
                };
                if ((_hit get "status") isNotEqualTo _was) then {
                    if ((_hit get "status") isEqualTo "OK") then {
                        ["INFO", format ["%1 %2 : de nouveau localisé", _hit get "sub", _hit get "q"], 4, 30] call comspec_atak_native_fnc_notify;
                    } else {
                        ["WARNING", format ["%1 %2 : %3, dernière position conservée", _hit get "sub", _hit get "q", _hit get "why"], 5, 30] call comspec_atak_native_fnc_notify;
                    };
                };
            };
        };
        // Fiche : texte relu sans reconstruire la page.
        private _card = uiNamespace getVariable ["COMSPEC_ATAK_MapSearchCard", controlNull];
        if (!isNull _card) then { _card ctrlSetStructuredText parseText (((["cardText"] call comspec_atak_native_fnc_mapSearch)) call comspec_atak_native_fnc_tr); };
    };
    case "cardText": {
        private _ok = (_hit getOrDefault ["status", ""]) isEqualTo "OK";
        private _col = ["#9aa39e", "#5cc76b"] select _ok;
        private _age = if ((_hit getOrDefault ["t", -1]) < 0) then { "jamais" } else {
            private _a = round (time - (_hit get "t"));
            format ["%1 (il y a %2)", _hit get "hour", [format ["%1 s", _a], format ["%1 min", floor (_a / 60)]] select (_a >= 120)]
        };
        private _p = _hit getOrDefault ["pos", []];
        _ret = format ["<t font='COMSPEC_BarlowBold' color='#e9a53a'>%1</t> <t font='EtelkaMonospacePro' size='0.9'>%2</t>  <t size='0.85' color='%3'>%4</t><br/><t size='0.85'>Propriétaire : %5</t><br/><t size='0.85' color='#cfd8d3'>Vu à %6 · ± %7 m%8</t>",
            _hit getOrDefault ["sub", ""], _hit getOrDefault ["q", ""], _col,
            [_hit getOrDefault ["why", ""], ["Suivi actif", "Localisé"] select !(_hit getOrDefault ["follow", false])] select _ok,
            [_hit get "owner", "Inconnu"] select ((_hit getOrDefault ["owner", ""]) isEqualTo ""),
            _age, _hit getOrDefault ["rad", 0],
            ["", format [" · %1", [_p, 8] call comspec_atak_native_fnc_gridRef]] select ((count _p) >= 2)];
    };
    case "known": { _ret = [_arg] call _known; };
    case "panel": {
        _arg params ["_px", "_py", "_pw", "_ph"];
        private _types = [["IMEI", "IMEI", "IMEI ou adresse MAC d'un téléphone (règles GÉOLOC)"], ["NUM", "NUMÉRO", "Numéro de téléphone (règles GÉOLOC)"], ["GPS", "BALISE GPS", "Balise GPS posée sur un véhicule ou un objet (position exacte)"], ["CS", "INDICATIF", "Indicatif ou nom d'un allié suivi par le BFT"]];
        private _label = switch (_type) do {
            case "NUM": { "Numéro (06 12 34 56 78, +33 6…)" };
            case "GPS": { "Identifiant de balise (GPS-…) ou nom" };
            case "CS": { "Indicatif ou nom" };
            default { "IMEI (35-…) ou adresse MAC (A4:5E:…)" };
        };
        private _rule = switch (_type) do {
            case "GPS": { "Position exacte tant que la balise est alimentée. Il faut du réseau." };
            case "CS": { "Position du suivi BFT de votre camp (dernière position si l'allié est hors ligne)." };
            default {
                format ["Requête opérateur : quelques secondes, cercle d'environ %1 m. Il faut du réseau. Échec si le téléphone est éteint, en mode avion, sans signal ou brouillé.%2",
                    round ((missionNamespace getVariable ["comspec_atak_native_geoloc_precision", 150]) max 10),
                    ["", " Votre camp n'est pas traçable sur cette mission."] select !(missionNamespace getVariable ["comspec_atak_native_geoloc_own", false])]
            };
        };
        private _msg = _st getOrDefault ["msg", []];
        private _rows = [
            ["title", "Rechercher sur la carte"],
            ["segment", "", _types apply { [_x select 1, compile format ["['type', %1] call comspec_atak_native_fnc_mapSearch;", str (_x select 0)], (_x select 0) isEqualTo _type, _x select 2] }],
            ["edit", "mapSearchQ", _label, _st getOrDefault ["q", ""]],
            ["combo", "mapSearchList", "Choisir (autocomplétion)", [["Identifiants connus", ""]], ""],
            ["buttons", [["LOCALISER", { ["go"] call comspec_atak_native_fnc_mapSearch; }, true], ["FERMER", { ["close"] call comspec_atak_native_fnc_mapSearch; }]]]
        ];
        if ((count _msg) > 0) then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", ["#e9a53a", "#5cc76b"] select (_msg param [1, false]), _msg select 0]]; };
        _rows pushBack ["text", format ["<t size='0.75' color='#8a9a93'>%1</t>", _rule]];
        [_rows, [_px, _py, _pw, _ph], false, [0.025, 0.030, 0.027, 0.97]] call comspec_atak_native_fnc_formRender;
        private _f = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
        private _e = _f getOrDefault ["mapSearchQ", controlNull];
        private _c = _f getOrDefault ["mapSearchList", controlNull];
        uiNamespace setVariable ["COMSPEC_ATAK_MapSearchCtl", [_e, _c]];
        [_c, _st getOrDefault ["q", ""]] call _fillList;
        _e ctrlAddEventHandler ["KeyDown", {
            params ["", "_key"];
            if (_key in [28, 156]) exitWith { [{ ["go"] call comspec_atak_native_fnc_mapSearch; }] call CBA_fnc_execNextFrame; true };
            false
        }];
        _e ctrlAddEventHandler ["KeyUp", { ["filter"] call comspec_atak_native_fnc_mapSearch; false }];
        _c ctrlAddEventHandler ["LBSelChanged", { [{ ["pick"] call comspec_atak_native_fnc_mapSearch; }] call CBA_fnc_execNextFrame; }];
        [{ [{ ctrlSetFocus ((uiNamespace getVariable ["COMSPEC_ATAK_MapSearchCtl", [controlNull]]) select 0); }] call CBA_fnc_execNextFrame; }] call CBA_fnc_execNextFrame;
    };
    case "card": {
        if ((count _hit) isEqualTo 0) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_MapSearchCard", controlNull]; };
        _arg params ["_px", "_py", "_pw", "_ph"];
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _fs = _l get "fontSmall";
        private _pad = (_l get "pad") * 2;
        private _bg = ["COMSPEC_RscMapPanel", [_px, _py, _pw, _ph], "", false] call comspec_atak_native_fnc_pageCtrl;
        _bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.94];
        private _edge = ["COMSPEC_RscText", [_px, _py, _pw, pixelH * 2], "", false] call comspec_atak_native_fnc_pageCtrl;
        _edge ctrlSetBackgroundColor [0.91, 0.65, 0.23, 0.8];
        private _bh = _fs * 1.4;
        private _t = ["COMSPEC_RscStructuredText", [_px + _pad / 2, _py + _pad / 3, _pw - _pad, _ph - _bh - _pad], "", false] call comspec_atak_native_fnc_pageCtrl;
        _t ctrlSetFontHeight (_fs * 0.95);
        _t ctrlSetStructuredText parseText (((["cardText"] call comspec_atak_native_fnc_mapSearch)) call comspec_atak_native_fnc_tr);
        uiNamespace setVariable ["COMSPEC_ATAK_MapSearchCard", _t];
        private _btns = [
            [["SUIVRE", "ARRÊTER LE SUIVI"] select (_hit getOrDefault ["follow", false]), { ["follow"] call comspec_atak_native_fnc_mapSearch; }, !(_hit getOrDefault ["follow", false])],
            ["CENTRER", { ["center"] call comspec_atak_native_fnc_mapSearch; }, false],
            ["FERMER", { ["clear"] call comspec_atak_native_fnc_mapSearch; }, false]
        ];
        private _bw = (_pw - _pad * 1.5) / 3;
        {
            _x params ["_lbl", "_code", "_main"];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _main, [_px + _pad / 2 + _forEachIndex * (_bw + _pad / 4), _py + _ph - _bh - _pad / 3, _bw, _bh], _lbl, false] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            _b ctrlAddEventHandler ["ButtonClick", _code];
        } forEach _btns;
    };
    case "draw": {
        private _map = _arg;
        if ((count _hit) isEqualTo 0 || {isNull _map}) exitWith {};
        private _p = _hit getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        private _ok = (_hit getOrDefault ["status", ""]) isEqualTo "OK";
        private _rgb = if (_ok) then { switch (_hit get "kind") do { case "GPS": { [0.20, 0.90, 0.95] }; case "CS": { [0.28, 0.70, 1] }; default { [0.95, 0.67, 0.20] }; } } else { [0.62, 0.64, 0.63] };
        private _r = (_hit getOrDefault ["rad", 10]) max 5;
        private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
        _map drawEllipse [_p, _r, _r, 0, _rgb + [0.95], ""];
        _map drawEllipse [_p, _r, _r, 0, _rgb + [0.12], _fill];
        // Anneau pulsant (taille écran) : repère visible à toutes les échelles.
        private _ph = (diag_tickTime mod 1.4) / 1.4;
        private _ts = uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _rgb + [(1 - _ph) * ([0.5, 0.95] select _ok)], _p, 18 + 46 * _ph, 18 + 46 * _ph, 0, "", 0];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _rgb + [1], _p, 16, 16, 0,
            format ["%1%2", [_hit get "owner", _hit get "q"] select ((_hit getOrDefault ["owner", ""]) isEqualTo ""), ["", format [" · %1", _hit get "hour"]] select ((_hit getOrDefault ["hour", ""]) isNotEqualTo "")],
            1, 0.028 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    };
};
_ret
