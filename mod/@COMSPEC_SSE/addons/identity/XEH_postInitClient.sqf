// Menus ACE (une fois par classe) et modules Zeus du contrôle d'identité.
if (!hasInterface) exitWith {};
[] call comspec_sse_fnc_initAceIdentity;
[] call comspec_sse_fnc_idZenRegister;
// Kit Athena (papiers du compte, bloc « kits » du paquet) : demandé au serveur à l'arrivée (connexion, JIP) et après
// chaque respawn ; le serveur lit lui-même le Steam ID et ne remet que les papiers manquants (fn_idKit).
[{ !isNull player && {alive player} && {(getPlayerUID player) isNotEqualTo ""} }, {
    ["send", ["kit", [player, objNull]]] call comspec_sse_fnc_idClient;
}, [], 300] call CBA_fnc_waitUntilAndExecute;
["CAManBase", "Respawn", {
    params ["_new", "_old"];
    if (!local _new) exitWith {};
    [{
        params ["_new", "_old"];
        if (_new isEqualTo player && {alive _new}) then { ["send", ["kit", [_new, _old]]] call comspec_sse_fnc_idClient; };
    }, [_new, _old], 2] call CBA_fnc_waitAndExecute;
}] call CBA_fnc_addClassEventHandler;
