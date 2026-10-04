/*
    Compte rendu d'une commande du poste web (COMSPEC Athena) : remonte par le POST des charges
    (DLL SubmitExplosiveTimer → /api/atak/explosive-timers, clé web_cmd_ack ; file persistante de la DLL).
    Params : [id (chaîne), statut "accepted" | "done" | "failed", message (texte FR court), données (HashMap, facultatif)]
    Retour : true si la DLL a mis l'envoi en file.
*/
params [["_id", ""], ["_status", "done"], ["_msg", ""], ["_data", createHashMap]];
if (!(_id isEqualType "") || {_id isEqualTo ""}) exitWith { false };
if (!(_data isEqualType createHashMap)) then { _data = createHashMap; };
// Pas de retour ligne ni de caractère de contrôle dans le JSON maison.
if (!(_msg isEqualType "")) then { _msg = str _msg; };
_msg = (_msg splitString (toString [10, 13, 9])) joinString " ";
if ((count _msg) > 240) then { _msg = _msg select [0, 240]; };

private _ack = createHashMapFromArray [
    ["id", parseNumber _id],
    ["status", _status],
    ["ok", _status isNotEqualTo "failed"],
    ["msg", _msg]
];
if ((count _data) > 0) then { _ack set ["data", _data]; };
private _json = [createHashMapFromArray [["web_cmd_ack", _ack]]] call comspec_overwatch_connect_fnc_hashMapToJson;
private _r = "COMSPECExtension" callExtension ["SubmitExplosiveTimer", [_json]];
["INFO", "WebCmd", format ["Commande %1 : %2 — %3", _id, _status, _msg]] call comspec_overwatch_connect_fnc_log;
(_r isEqualType []) && {(_r param [0, ""]) isEqualTo "OK"}
