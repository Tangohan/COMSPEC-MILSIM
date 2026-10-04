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
if (!_force && {!([] call comspec_atak_native_fnc_p2pReachable)}) exitWith {
    private _inbox = missionNamespace getVariable ["COMSPEC_ATAK_P2pInbox", []];
    if (_id isEqualTo "" || {(_inbox findIf { (_x param [3, ""]) isEqualTo _id }) < 0}) then { _inbox pushBack [_from, _body, _time, _id, _sender, _date]; };
    while {(count _inbox) > 100} do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_P2pInbox", _inbox];
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
["MESSAGE", format ["%1 : %2", _from, _preview], 5, 40] call comspec_atak_native_fnc_notify;
if (isNull ([] call comspec_atak_native_fnc_display)) then { systemChat format ["[ATAK] Message de %1", _from]; };
if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then { [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
