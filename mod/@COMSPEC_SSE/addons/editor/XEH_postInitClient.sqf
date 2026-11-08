// Module Zeus (ZEN) : l'éditeur s'ouvre sur la position et l'objet du module, repris à la pose.
if (!hasInterface) exitWith {};
[{ !isNil "zen_custom_modules_fnc_register" && {!isNil "comspec_sse_fnc_zenModule"} }, {
    ["COMSPEC_Cat_Sse", "Ouvrir l'éditeur", {
        params ["_pos", "_obj"];
        ["open", [_pos, _obj]] call comspec_sse_fnc_editor;
    }, "\z\comspec_sse\addons\editor\data\type_intel_ca.paa"] call comspec_sse_fnc_zenModule;
}] call CBA_fnc_waitUntilAndExecute;
