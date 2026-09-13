/*
    Enregistre le panneau Athena sur ouverture ACE Arsenal.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ArsenalOverlayEH") exitWith {};

if (isNil "CBA_fnc_addEventHandler") exitWith {};

COMSPEC_ArsenalOverlayEH = [
    "ace_arsenal_displayOpened",
    {
        params ["_display"];
        if (isNull _display) exitWith {};
        uiNamespace setVariable ["ace_arsenal_display", _display];
        // Léger délai : laisse ACE construire son UI
        [{
            params ["_disp"];
            if (!isNull _disp) then {
                [_disp] call comspec_overwatch_connect_fnc_arsenalOverlayShow;
            };
        }, [_display], 0.35] call CBA_fnc_waitAndExecute;
    }
] call CBA_fnc_addEventHandler;

["ace_arsenal_displayClosed", {
    uiNamespace setVariable ["ace_arsenal_display", displayNull];
}] call CBA_fnc_addEventHandler;

["INFO", "ARSENAL", "Panneau Athena ACE Arsenal enregistré"] call comspec_overwatch_connect_fnc_log;
