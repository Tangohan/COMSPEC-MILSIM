/*
    Plan de briefing partagé du camp vers le site (calque « Plan de briefing » de la carte Athena, lecture seule) :
    COMSPEC Link « BriefingPlan.Push » (2.0.84), POST /api/atak/briefing-plan, tout le plan à chaque envoi.
    Envoie le téléphone qui a fait la dernière modification (UID écrit par fn_planServer). Si personne n'a confirmé
    l'envoi après 30 à 60 s (auteur sans liaison Athena, déconnecté), un autre téléphone du camp relié le fait.
    Révision arrivée au site : missionNamespace COMSPEC_ATAK_PlanWeb_<camp> (publique, une seule remontée par révision).
    Serveur sans le mod natif (pas de plan partagé) : chaque joueur envoie son brouillon, affiché comme plan personnel.
    Params : ["tick"] (toutes les 5 s, XEH_postInitClient) | ["force"] envoi immédiat | ["state"] -> texte pour l'app Plan
    État : missionNamespace COMSPEC_ATAK_PlanWebLast = [heure, état, révision, nombre d'éléments].
*/
params [["_op", "tick"]];
if (_op isEqualTo "state") exitWith {
    (missionNamespace getVariable ["COMSPEC_ATAK_PlanWebLast", []]) params [["_t", -1], ["_st", ""], ["_rev", 0], ["_n", 0]];
    if (_t < 0) exitWith { ("Site Athena : le plan partagé y apparaît (calque Plan de briefing) dès qu'il change") call comspec_atak_native_fnc_tr };
    format [("Site Athena : %1 (%2 élément(s), %3)") call comspec_atak_native_fnc_tr, (_st) call comspec_atak_native_fnc_tr, _n, [_t, "HH:MM"] call BIS_fnc_timeToString]
};
if (!hasInterface || {isNull player}) exitWith { false };
private _side = side group player;
private _force = _op isEqualTo "force";
private _webVar = "";
private _owner = "";
private _rev = 0;
private _items = [];
private _name = "";
private _mine = true;
// Plan partagé possible : serveur avec le mod (drapeau), machine serveur (solo, hébergé) ou plan déjà écrit.
private _srvOn = isServer || {missionNamespace getVariable ["COMSPEC_ATAK_PlanSrvOn", false]} || {!isNil { missionNamespace getVariable (format ["COMSPEC_ATAK_Plan_%1", _side]) }};
if (!_srvOn) then {
    // Serveur sans le mod natif : pas de plan partagé, chaque joueur envoie son brouillon (plan personnel sur le site).
    _items = missionNamespace getVariable ["COMSPEC_ATAK_PlanDraft", []];
    _owner = getPlayerUID player;
    (missionNamespace getVariable ["COMSPEC_ATAK_PlanDraftWeb", [0, "", -1e9]]) params ["_dRev", "_dSig", "_dAt"];
    private _sig = hashValue _items;
    if (!_force && {_sig isEqualTo _dSig || {(diag_tickTime - _dAt) < 10}}) exitWith { _rev = -1; };
    // Brouillon jamais touché dans cette mission : rien à envoyer (le site garde le dernier, daté).
    if (!_force && {_dRev isEqualTo 0} && {(count _items) isEqualTo 0}) exitWith { _rev = -1; };
    _rev = _dRev + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_PlanDraftWeb", [_rev, _sig, diag_tickTime]];
} else {
    private _plan = missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", _side], []];
    // Plan jamais écrit dans cette mission : rien à envoyer (le site garde le dernier, daté).
    if ((count _plan) isEqualTo 0) exitWith { _rev = -1; };
    _plan params [["_pRev", 0], ["_pItems", []], ["_pName", ""], ["_actor", ""]];
    _rev = _pRev; _items = _pItems; _name = _pName;
    _webVar = format ["COMSPEC_ATAK_PlanWeb_%1", _side];
    if (!_force && {(missionNamespace getVariable [_webVar, 0]) >= _rev}) exitWith { _rev = -1; };
    (missionNamespace getVariable ["COMSPEC_ATAK_PlanWebSeen", [-1, 0, -1e9]]) params ["_seenRev", "_seenAt", "_triedAt"];
    if (_seenRev isNotEqualTo _rev) then { _seenAt = diag_tickTime; _triedAt = -1e9; };
    missionNamespace setVariable ["COMSPEC_ATAK_PlanWebSeen", [_rev, _seenAt, _triedAt]];
    if (isNil { missionNamespace getVariable "COMSPEC_ATAK_PlanWebDelay" }) then { missionNamespace setVariable ["COMSPEC_ATAK_PlanWebDelay", 30 + random 30]; };
    _mine = _actor isEqualTo (getPlayerUID player);
    if (!_force && {!_mine} && {(diag_tickTime - _seenAt) < (missionNamespace getVariable ["COMSPEC_ATAK_PlanWebDelay", 45])}) exitWith { _rev = -1; };
    // Une tentative par révision toutes les 30 s au plus (envoi en file chez COMSPEC Link).
    if (!_force && {(diag_tickTime - _triedAt) < 30}) exitWith { _rev = -1; };
    missionNamespace setVariable ["COMSPEC_ATAK_PlanWebSeen", [_rev, _seenAt, diag_tickTime]];
};
if (_rev < 0) exitWith { false };
private _n = count _items;
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    if (_mine || _force) then { missionNamespace setVariable ["COMSPEC_ATAK_PlanWebLast", [dayTime, ["pas de liaison Athena, un autre téléphone l'enverra", "pas de liaison Athena"] select (_owner isNotEqualTo ""), _rev, _n]]; };
    false
};
// Tracés à main levée allégés : 6000 sommets au plus pour tout le plan (premier et dernier gardés).
private _total = 0;
{ _total = _total + count (_x select 2); } forEach _items;
private _step = ceil (_total / 6000) max 1;
private _js = comspec_atak_native_fnc_json;
private _parts = _items apply {
    _x params [["_id", ""], ["_kind", ""], ["_pts", []], ["_text", ""], ["_ck", "WHITE"], ["_dashed", false], ["_arrow", false], ["_w", 2], ["_num", 0], ["_author", ""], ["_desc", ""]];
    private _keep = _pts;
    if (_step > 1 && {(count _pts) > 8}) then {
        _keep = [];
        { if ((_forEachIndex mod _step) isEqualTo 0) then { _keep pushBack _x; }; } forEach _pts;
        if ((_keep select -1) isNotEqualTo (_pts select -1)) then { _keep pushBack (_pts select -1); };
    };
    format ['{"id":%1,"kind":%2,"text":%3,"color":%4,"dashed":%5,"arrow":%6,"width":%7,"num":%8,"author":%9,"desc":%10,"pts":',
        [_id] call _js, [_kind] call _js, [_text] call _js, [_ck] call _js, [_dashed] call _js, [_arrow] call _js, [_w] call _js, [_num] call _js, [_author] call _js, [_desc] call _js]
        + (str (_keep apply { [round (_x select 0), round (_x select 1)] })) + "}"
};
// Concaténation (et non format, borné à quelques milliers de caractères) : le plan peut peser plusieurs dizaines de Ko.
private _json = format ['{"mission_key":%1,"world":%2,"side":%3,"rev":%4,"name":%5,"reported_by":%6,"owner":%7,"items":[',
    [missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]]] call _js,
    [worldName] call _js, [str _side] call _js, _rev, [_name] call _js, [[player] call comspec_atak_native_fnc_unitCallsign] call _js, [_owner] call _js]
    + (_parts joinString ",") + "]}";
[_json, _rev, _n, _webVar] spawn {
    params ["_json", "_rev", "_n", "_webVar"];
    private _r = "COMSPECExtension" callExtension ["BriefingPlan.Push", [_json]];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    // Vide : COMSPEC Link plus ancien que 2.0.84 (commande inconnue).
    private _ok = (_r select [0, 3]) isEqualTo "OK|";
    private _state = switch (true) do {
        case _ok: { "plan envoyé" };
        case (_r isEqualTo ""): { "COMSPEC Link 2.0.84 requis" };
        case ((_r find "unauthorized") >= 0): { "non connecté à Athena" };
        default { "erreur " + (_r select [4]) };
    };
    if (_ok && {_webVar isNotEqualTo ""} && {(missionNamespace getVariable [_webVar, 0]) < _rev}) then { missionNamespace setVariable [_webVar, _rev, true]; };
    missionNamespace setVariable ["COMSPEC_ATAK_PlanWebLast", [dayTime, _state, _rev, _n]];
    ["INFO", "PLAN", format ["BriefingPlan.Push rév. %1, %2 élément(s), %3 octets : %4", _rev, _n, count _json, _state]] call comspec_atak_native_fnc_log;
};
true
