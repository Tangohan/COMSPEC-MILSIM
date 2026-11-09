// Slot virtuel des lunettes (expérimental) : état public comspec_sse_eyewear_worn sur l'unité, objet 3D dessiné par chaque client.

// Mort : les lunettes du slot virtuel retournent dans l'inventaire du corps (ou par terre à côté), là où l'unité est locale.
["CAManBase", "Killed", {
    params ["_unit"];
    if (local _unit && {(_unit getVariable ["comspec_sse_eyewear_worn", ""]) isNotEqualTo ""}) then {
        [_unit, true] call comspec_sse_fnc_eyewearRemove;
    };
}] call CBA_fnc_addClassEventHandler;

// Déconnexion : l'état est effacé (le corps ou l'IA qui reprend l'unité ne garde pas un objet orphelin).
if (isServer) then {
    addMissionEventHandler ["HandleDisconnect", {
        params ["_unit"];
        if (!isNull _unit && {(_unit getVariable ["comspec_sse_eyewear_worn", ""]) isNotEqualTo ""}) then {
            [_unit, false] call comspec_sse_fnc_eyewearRemove;
        };
        false
    }];
};
