/*
    SERVEUR. Contrôle du code d'un joueur qui vient d'arriver sur son poste (événement comspec_lobby_pinCheck envoyé par fn_clientCheck).
    Le poste est celui du JOUEUR, lu par le serveur dans la description de son rôle ; le joueur ne dit que son code.
    - poste sans étiquette ou sans code configuré : libre (un poste marqué [..|P] sans code configuré ne bloque jamais personne,
      mais le serveur le note : l'administrateur a oublié le code) ;
    - code juste : le joueur est marqué (COMSPEC_LOBBY_Ok = étiquette) ;
    - code faux : essai compté ; au troisième (réglage), blocage de 10 minutes ; le joueur reçoit la réponse et sort de la mission ;
      fn_serverTick l'expulse s'il ne le fait pas (client modifié).
    Hôte du serveur (propriétaire 2) : jamais contrôlé.
    [code, unité annoncée] : l'unité annoncée ne sert que si le serveur ne reconnaît pas l'expéditeur (voir eventSender).
*/
params [["_pin", "", [""]], ["_claim", objNull, [objNull]]];
if (!isServer || {!(missionNamespace getVariable ["comspec_lobby_enabled", true])}) exitWith {};
private _u = [] call comspec_lobby_fnc_eventSender;
if (isNull _u) then { _u = _claim; };
if (isNull _u || {!isPlayer _u}) exitWith {};
if ((owner _u) isEqualTo 2) exitWith { _u setVariable ["COMSPEC_LOBBY_Ok", "*", true]; };
([roleDescription _u] call comspec_lobby_fnc_tagParse) params ["_tag", "_flag"];
_tag = toUpper _tag;
private _entry = (missionNamespace getVariable ["comspec_lobby_pins", createHashMap]) getOrDefault [_tag, []];
private _reply = {
    params ["_ok", "_left"];
    ["comspec_lobby_pinResult", [_ok, _left], _u] call CBA_fnc_targetEvent;
};
if (_tag isEqualTo "" || {_entry isEqualTo []}) exitWith {
    _u setVariable ["COMSPEC_LOBBY_Ok", _tag, true];
    if (_flag) then { (format ["[COMSPEC LOBBY] poste %1 marqué protégé mais sans code configuré : accès libre", _tag]) call { diag_log _this }; };
    [true, 0] call _reply;
};
private _uid = getPlayerUID _u;
private _max = (missionNamespace getVariable ["comspec_lobby_attempts", 3]) max 1;
private _tries = missionNamespace getVariable ["comspec_lobby_tries", createHashMap];
private _locks = missionNamespace getVariable ["comspec_lobby_locks", createHashMap];
missionNamespace setVariable ["comspec_lobby_tries", _tries];
missionNamespace setVariable ["comspec_lobby_locks", _locks];
if (serverTime < (_locks getOrDefault [_uid, 0])) exitWith {
    (format ["[COMSPEC LOBBY] %1 bloqué jusqu'à la fin du délai (poste %2)", name _u, _tag]) call { diag_log _this };
    [false, 0] call _reply;
    [_u] call comspec_lobby_fnc_serverTick;
};
if (_pin isEqualTo (_entry select 0)) exitWith {
    _tries set [_uid, 0];
    _u setVariable ["COMSPEC_LOBBY_Ok", _tag, true];
    (format ["[COMSPEC LOBBY] %1 : poste %2 déverrouillé (source %3)", name _u, _tag, _entry select 1]) call { diag_log _this };
    [true, 0] call _reply;
};
private _n = (_tries getOrDefault [_uid, 0]) + 1;
_tries set [_uid, _n];
private _left = (_max - _n) max 0;
(format ["[COMSPEC LOBBY] %1 : code refusé pour le poste %2 (essai %3 sur %4)", name _u, _tag, _n, _max]) call { diag_log _this };
if (_left isEqualTo 0) then { _locks set [_uid, serverTime + 600]; _tries set [_uid, 0]; };
[false, _left] call _reply;
if (_left isEqualTo 0) then { [_u] call comspec_lobby_fnc_serverTick; };
