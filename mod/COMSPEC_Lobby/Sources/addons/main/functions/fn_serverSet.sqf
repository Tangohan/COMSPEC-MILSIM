/*
    SERVEUR. Un Zeus pose, change ou retire le code d'un poste (événement comspec_lobby_pinSet, envoyé par le module ZEN).
    [étiquette, code] ; code vide : le poste n'a plus de code (venu de Zeus ; un code de fichier ou d'Athena reste, voir pinLoad).
    L'expéditeur doit être curateur (Zeus) : un autre joueur est refusé et noté. Appel local (serveur, hôte) : accepté.
*/
params [["_tag", "", [""]], ["_pin", "", [""]]];
if (!isServer) exitWith {};
private _u = [] call comspec_lobby_fnc_eventSender;
if (!isNull _u && {isNull (getAssignedCuratorLogic _u)}) exitWith {
    (format ["[COMSPEC LOBBY] code refusé : %1 n'est pas Zeus", name _u]) call { diag_log _this };
};
_tag = toUpper (trim _tag);
if (_tag isEqualTo "") exitWith {};
private _table = missionNamespace getVariable ["comspec_lobby_pins", createHashMap];
if (_pin isEqualTo "") then {
    if (((_table getOrDefault [_tag, ["", ""]]) select 1) isEqualTo "ZEUS") then { _table deleteAt _tag; };
} else {
    if !((count _pin) isEqualTo 4 && {(_pin splitString "0123456789") isEqualTo []}) exitWith {};
    _table set [_tag, [_pin, "ZEUS"]];
};
missionNamespace setVariable ["comspec_lobby_pins", _table];
[] call comspec_lobby_fnc_pinSave;
private _verb = ["retiré", "posé"] select (_pin isNotEqualTo "");
(format ["[COMSPEC LOBBY] poste %1 : code %2 par Zeus", _tag, _verb]) call { diag_log _this };
