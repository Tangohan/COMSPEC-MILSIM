if (!hasInterface) exitWith {};

// Le joueur n’est pas toujours prêt au postInit : même report que fn_initACE.
if (isNull player) exitWith {
    [{ [] call comspec_overwatch_sse_ace_fnc_initSseAce }, [], 2] call CBA_fnc_waitAndExecute;
};

[] call comspec_overwatch_sse_ace_fnc_initSseAce;
