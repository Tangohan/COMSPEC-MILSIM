/*
    SMS reçu (événement CBA comspec_atak_native_p2p, ou alerte du poste via athenaSignal).
    Params : [expéditeur, texte, heure, id (""), téléphone expéditeur (objNull), date "AAAA-MM-JJ" ("")]
    Avec un id, l'expéditeur reçoit un accusé « distribué » (puis « lu » quand la conversation est ouverte).
    Téléphone sans réseau, éteint (batterie vide) ou absent : le SMS attend chez l'opérateur
    (COMSPEC_ATAK_P2pInbox) et n'arrive, accusé compris, qu'au retour du signal (boucle de XEH_postInitClient).
    7e paramètre interne : true pour livrer sans revérifier le signal (vidage de l'attente).
*/
params [["_from", ""], ["_body", ""], ["_time", "--:--"], ["_id", ""], ["_sender", objNull], ["_date", ""], ["_force", false]];
if (!hasInterface || {_from isEqualTo ""}) exitWith {};
// Contrôles à l'arrivée (le circuit est de téléphone à téléphone : aucun serveur au milieu, c'est donc au destinataire de se protéger).
// Expéditeur connu : il doit être un joueur et porter le nom annoncé (pas d'usurpation) ; 8 SMS par 10 s au plus par expéditeur
// (anti-spam, le surplus est jeté) ; texte plafonné à 400 caractères. Livraison de l'attente (_force) : déjà contrôlé à l'arrivée.
if (!_force && {!isNull _sender}) then {
    private _drop = !(isPlayer _sender) || {(name _sender) isNotEqualTo _from};
    if (!_drop) then {
        private _rate = missionNamespace getVariable ["COMSPEC_ATAK_P2pRate", createHashMap];
        private _key = getPlayerUID _sender;
        private _stamps = (_rate getOrDefault [_key, []]) select { time - _x < 10 };
        if ((count _stamps) >= 8) then { _drop = true; } else { _stamps pushBack time; };
        _rate set [_key, _stamps];
        missionNamespace setVariable ["COMSPEC_ATAK_P2pRate", _rate];
    };
    if (_drop) then { _from = ""; };
};
if (_from isEqualTo "") exitWith {};
if !(_body isEqualType "") then { _body = str _body; };
if ((count _body) > 400) then { _body = _body select [0, 400]; };
if (!_force && {!([] call comspec_atak_native_fnc_p2pReachable)} && {(["peer", [_sender]] call comspec_atak_native_fnc_meshGraph) isEqualTo []}) exitWith {
    private _inbox = missionNamespace getVariable ["COMSPEC_ATAK_P2pInbox", []];
    if (_id isEqualTo "" || {(_inbox findIf { (_x param [3, ""]) isEqualTo _id }) < 0}) then { _inbox pushBack [_from, _body, _time, _id, _sender, _date]; };
    while {(count _inbox) > 100} do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_P2pInbox", _inbox];
    // L'expéditeur voit « destinataire hors réseau, remis à son retour » au lieu de « non transmis ».
    if (_id isNotEqualTo "" && {!isNull _sender}) then { ["comspec_atak_native_p2pAck", [_id, "HELD", [dayTime, "HH:MM"] call BIS_fnc_timeToString], _sender] call CBA_fnc_targetEvent; };
};
if (_date isEqualTo "") then { _date = [] call comspec_atak_native_fnc_p2pDate; };
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _p2p = _data getOrDefault ["p2p", []];
if (_id isNotEqualTo "" && {(_p2p findIf { (_x getOrDefault ["id", ""]) isEqualTo _id }) >= 0}) exitWith {};
_p2p pushBack createHashMapFromArray [["peer", _from], ["dir", "in"], ["body", _body], ["time", _time], ["date", _date], ["read", false], ["id", _id], ["sender", _sender]];
while {(count _p2p) > 200} do { _p2p deleteAt 0; };
_data set ["p2p", _p2p];
if (_id isNotEqualTo "" && {!isNull _sender}) then { ["comspec_atak_native_p2pAck", [_id, "DELIVERED", [dayTime, "HH:MM"] call BIS_fnc_timeToString], _sender] call CBA_fnc_targetEvent; };
private _preview = [_body, (_body select [0, 60]) + "…"] select ((count _body) > 60);
// Notification : ouvre l'app SMS si elle est installée, sinon Messages.
private _smsOn = (([] call comspec_atak_native_fnc_appList) findIf { (_x get "page") isEqualTo "SMS" && {[_x] call comspec_atak_native_fnc_appVisible} }) >= 0;
["MESSAGE", format ["%1 : %2", _from, _preview], 5, 40, ["CHAT", "SMS"] select _smsOn] call comspec_atak_native_fnc_notify;
// Conversation ouverte sur ce fil : seul le fil est redessiné (fn_pageChat [true]). Liste des conversations : ligne du contact sur place, remontée en tête (fn_chatListRow), redessin complet s'il n'y est pas ; autre fil ouvert : seule la liste voisine (paysage) change.
if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) in ["CHAT", "SMS"]) then {
    [{
        params ["_thisPeer"];
        private _open = !isNull (uiNamespace getVariable ["COMSPEC_ATAK_ChatThread", controlNull]);
        if (_open && {(uiNamespace getVariable ["COMSPEC_ATAK_ChatThreadPeer", ""]) isEqualTo _thisPeer}) exitWith { [true] call comspec_atak_native_fnc_pageChat; };
        private _inPlace = [_thisPeer] call comspec_atak_native_fnc_chatListRow;
        if (!_open && {!_inPlace}) then { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "CHAT"]] call comspec_atak_native_fnc_pageRender; };
    }, [_from]] call comspec_atak_native_fnc_nextFrame;
};
