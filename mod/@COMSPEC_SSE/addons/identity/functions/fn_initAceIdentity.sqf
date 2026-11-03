/*
    Menus ACE du contrôle d'identité (XEH_postInitClient), posés une seule fois par classe (aucune action par objet) :
      personne (CAManBase, ACE_MainActions) › Contrôle d'identité :
        Demander les papiers (2 s) · Contrôler l'identité (documents présentés) · Fouiller les papiers (6 s vivant,
        4 s corps) · Documents trouvés · Rendre un document · Poser une question (PNJ : questions de légende, 1 s)
      soi (ACE_SelfActions) › Mes papiers : Présenter mes papiers (présenter / remettre) · Refuser · Consulter
        · Répondre (question d'un contrôleur en attente : réponses au choix)
      véhicule terrestre (LandVehicle) › Contrôle d'identité : Contrôler les occupants · Faire descendre
    ACE obligatoire (comme tout le SSE) : pas de repli sans ACE.
*/
if (!hasInterface || {isNil "ace_interact_menu_fnc_addActionToClass"}) exitWith { false };
if (missionNamespace getVariable ["comspec_sse_idAceReady", false]) exitWith { false };
missionNamespace setVariable ["comspec_sse_idAceReady", true];

private _icon = "\z\comspec_sse\addons\identity\data\icon_card_ca.paa";
private _on = { missionNamespace getVariable ["comspec_sse_id_enabled", true] };
private _addClass = {
    params ["_cls", "_type", "_path", "_act"];
    [_cls, _type, _path, _act, true] call ace_interact_menu_fnc_addActionToClass;
};
private _new = { _this call comspec_sse_fnc_aceAction };

// --- Personne ---------------------------------------------------------------------------------------------------
private _root = ["COMSPEC_ID_Root", "Contrôle d'identité", _icon, {}, {
    params ["_t", "_p"];
    (missionNamespace getVariable ["comspec_sse_id_enabled", true]) && {_t isNotEqualTo _p} && {alive _p} && {isNull objectParent _p}
}] call _new;
["CAManBase", 0, ["ACE_MainActions"], _root] call _addClass;
private _path = ["ACE_MainActions", "COMSPEC_ID_Root"];

{
    ["CAManBase", 0, _path, _x call _new] call _addClass;
} forEach [
    ["COMSPEC_ID_Ask", "Demander les papiers", "", {
        params ["_t", "_p"];
        ["send", ["begin", [_p, "ask", _t]]] call comspec_sse_fnc_idClient;
        [2, "Papiers, s'il vous plaît…", { params ["_t"]; ["send", ["ask", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
    }, { params ["_t", "_p"]; alive _t && {isNull objectParent _t} && {(_p distance _t) <= 3} }],

    ["COMSPEC_ID_Check", "Contrôler l'identité", "", {}, {
        params ["_t"];
        private _tok = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
        (values _tok) findIf { (_x select 0) isEqualTo _t && {(_x select 2) > time} } >= 0
    }, { ["tokens", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }],

    ["COMSPEC_ID_Search", "Fouiller les papiers", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa", {
        params ["_t", "_p"];
        private _alive = alive _t;
        ["send", ["begin", [_p, ["docs", "docsAlive"] select _alive, _t]]] call comspec_sse_fnc_idClient;
        [[4, 6] select _alive, "Fouille des papiers…", { params ["_t"]; ["send", ["docs", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
    }, { params ["_t"]; [_t] call comspec_sse_fnc_idCanSearch }],

    ["COMSPEC_ID_Found", "Documents trouvés", "", {}, {
        params ["_t"];
        (missionNamespace getVariable ["comspec_sse_idSearch", [objNull, [], -999]]) params ["_s", "_list", "_at"];
        _s isEqualTo _t && {_list isNotEqualTo []} && {(time - _at) <= 180}
    }, { ["found", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }],

    ["COMSPEC_ID_Return", "Rendre un document", "", {}, {
        params ["_t"];
        private _n = netId _t;
        ((values (missionNamespace getVariable ["comspec_sse_idSeized", createHashMap])) findIf { _x isEqualTo _n }) >= 0
    }, { ["return", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }],

    ["COMSPEC_ID_Question", "Poser une question", "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa", {}, {
        params ["_t", "_p"];
        alive _t && {!isPlayer _t} && {(_p distance _t) <= 3} && {!(_t getVariable ["ACE_isUnconscious", false])}
    }, { ["questions", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }]
];

// --- Soi ---------------------------------------------------------------------------------------------------------
private _self = ["COMSPEC_ID_Self", "Mes papiers", _icon, {}, {
    (missionNamespace getVariable ["comspec_sse_id_enabled", true])
        && {((["pending"] call comspec_sse_fnc_idPresentPrompt) isNotEqualTo []) || {([player, 0] call comspec_sse_fnc_idDocsOf) isNotEqualTo []}
            || {(missionNamespace getVariable ["comspec_sse_idQuestion", []]) isNotEqualTo []}}
}] call _new;
["CAManBase", 1, ["ACE_SelfActions"], _self] call _addClass;
{
    ["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_ID_Self"], _x call _new] call _addClass;
} forEach [
    ["COMSPEC_ID_Present", "Présenter mes papiers", "", {}, { ([player, 0] call comspec_sse_fnc_idDocsOf) isNotEqualTo [] },
        { ["present", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }],
    ["COMSPEC_ID_Refuse", "Refuser", "", { ["refuse"] call comspec_sse_fnc_idPresentPrompt }, { (["pending"] call comspec_sse_fnc_idPresentPrompt) isNotEqualTo [] }],
    ["COMSPEC_ID_Answer", "Répondre", "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa", {}, {
        (missionNamespace getVariable ["comspec_sse_idQuestion", []]) params [["_w", ""], ["_l", []], ["_end", 0], ["_done", createHashMap]];
        _end > time && {(_l findIf { !((_x select 0) in _done) }) >= 0}
    }, { ["answers", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }],
    ["COMSPEC_ID_Consult", "Consulter mes papiers", "", {}, { ([player, 0] call comspec_sse_fnc_idDocsOf) isNotEqualTo [] },
        { ["consult", _this select 0, _this select 1] call comspec_sse_fnc_idActionChildren }]
];

// --- Véhicule ------------------------------------------------------------------------------------------------------
private _veh = ["COMSPEC_ID_VehRoot", "Contrôle d'identité", _icon, {}, {
    (missionNamespace getVariable ["comspec_sse_id_enabled", true]) && {["can", _this select 0] call comspec_sse_fnc_idVehicleOccupants}
}] call _new;
["LandVehicle", 0, ["ACE_MainActions"], _veh] call _addClass;
{
    ["LandVehicle", 0, ["ACE_MainActions", "COMSPEC_ID_VehRoot"], _x call _new] call _addClass;
} forEach [
    ["COMSPEC_ID_VehCheck", "Contrôler les occupants", "", { ["run", _this select 0, "vehicle"] call comspec_sse_fnc_idVehicleOccupants }, { true }],
    ["COMSPEC_ID_VehOut", "Faire descendre", "", { ["run", _this select 0, "dismount"] call comspec_sse_fnc_idVehicleOccupants }, { true }]
];
true
