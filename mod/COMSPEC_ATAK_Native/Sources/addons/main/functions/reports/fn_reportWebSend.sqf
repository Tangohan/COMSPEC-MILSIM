/*
    Envoie un compte rendu ou un BDA à Athena (POST /api/atak/reports, table atak_tactical_reports) et garde le résultat
    sur la fiche, sans rien afficher à l'écran du jeu : la liste de l'app lit l'état (fn_reportWebState).
    Params : [fiche (HashMap), type, priorité, résumé, détails, données structurées (HashMap), position, page à redessiner,
              identifiant client (texte, facultatif), code rappelé avec [fiche] quand le résultat est connu (facultatif)]
    L'identifiant client part dans les données structurées (client_id) : le serveur reconnaît un renvoi et ne crée pas de doublon.
    La fiche reçoit « web » : "EN ATTENTE" puis "OK", "ÉCHEC" (avec « webWhy », raison courte) ou "HORS LIGNE" ; "LOCAL" sans Athena.
    Un envoi déjà en cours n'est pas relancé. Retour : true si l'envoi est parti, false sinon.
*/
params [["_rec", createHashMap], ["_type", "OTHER"], ["_prio", "ROUTINE"], ["_summary", ""], ["_details", ""], ["_sd", createHashMap], ["_pos", []], ["_page", ""], ["_cid", ""], ["_done", {}]];
if ((count _rec) isEqualTo 0 || {(_rec getOrDefault ["web", ""]) isEqualTo "EN ATTENTE"}) exitWith { false };
_rec set ["webWhy", ""];
if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith { _rec set ["web", "LOCAL"]; false };
if (isNil "comspec_overwatch_connect_fnc_hashMapToJson" || {isNil "comspec_overwatch_connect_fnc_extAwait"} || {isNil "comspec_overwatch_connect_fnc_parseAtakExtResponse"} || {isNil "comspec_overwatch_connect_fnc_atakExtFailMessage"} || {isNil "comspec_overwatch_connect_fnc_getCallsign"} || {isNil "comspec_overwatch_connect_fnc_formatTimestamp"}) exitWith { _rec set ["web", "HORS LIGNE"]; false };
if (_cid isNotEqualTo "") then { _sd set ["client_id", _cid]; };
_rec set ["web", "EN ATTENTE"];
[_rec, _type, _prio, _summary, _details, _sd, _pos, _page, _done] spawn {
    params ["_rec", "_type", "_prio", "_summary", "_details", "_sd", "_pos", "_page", "_done"];
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    if ((count _pos) < 2) then { _pos = getPosWorld player; };
    private _data = createHashMapFromArray [
        ["report_type", _type], ["priority", _prio], ["submitter_callsign", _cs], ["submitter_unit", groupId (group player)],
        ["submitter_steam_id", getPlayerUID player], ["pos_x", _pos select 0], ["pos_y", _pos select 1], ["grid_reference", mapGridPosition _pos],
        ["summary", _summary], ["details", _details], ["report_timestamp", [systemTime] call comspec_overwatch_connect_fnc_formatTimestamp]
    ];
    private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", ""];
    if (_mk isEqualType "" && {_mk isNotEqualTo ""}) then { _data set ["mission_key", _mk]; };
    if ((count _sd) > 0) then { _data set ["structured_data", _sd]; };
    private _json = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
    private _parsed = [["COMSPECExtension", "SubmitTacticalReport", [_json]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
    _parsed params ["_ok", "", "_detail"];
    if (_ok) then {
        _rec set ["web", "OK"];
        _rec set ["webWhy", ""];
    } else {
        _rec set ["web", ["ÉCHEC", "HORS LIGNE"] select ((toLower (str _detail)) find "not_connected" >= 0)];
        _rec set ["webWhy", [_detail, "Athena n'a pas répondu"] call comspec_overwatch_connect_fnc_atakExtFailMessage];
    };
    [_rec] call _done;
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo _page) then { [_page] call comspec_atak_native_fnc_pageRender; };
};
true
