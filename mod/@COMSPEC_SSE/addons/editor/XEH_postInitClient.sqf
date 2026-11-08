// Module Zeus (ZEN) : l'éditeur s'ouvre sur la position et l'objet du module, repris à la pose.
if (!hasInterface) exitWith {};
// Réglage « Configurer une IA à sa pose » : fenêtre ouverte dès qu'un Zeus pose une IA (coché par le Zeus qui le souhaite).
[{ !isNull player && {!isNull (getAssignedCuratorLogic player)} }, {
    (getAssignedCuratorLogic player) addEventHandler ["CuratorObjectPlaced", {
        params ["", "_o"];
        if ((missionNamespace getVariable ["comspec_sse_id_zeusAutoConfig", false]) && {_o isKindOf "CAManBase"} && {!isPlayer _o} && {isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull])}) then {
            ["open", [getPosATL _o, _o, "ai"]] call comspec_sse_fnc_editor;
        };
    }];
}, [], 600] call CBA_fnc_waitUntilAndExecute;
[{ !isNil "zen_custom_modules_fnc_register" && {!isNil "comspec_sse_fnc_zenModule"} }, {
    ["COMSPEC_Cat_Sse", "Ouvrir l'éditeur", {
        params ["_pos", "_obj"];
        ["open", [_pos, _obj]] call comspec_sse_fnc_editor;
    }, "\z\comspec_sse\addons\editor\data\type_intel_ca.paa"] call comspec_sse_fnc_zenModule;
}] call CBA_fnc_waitUntilAndExecute;
