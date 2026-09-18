/*
    Ouverture de l’app Message : choix P2P local ou Via Athena.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_MessageHub_group", _group];
["msghub"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

_group ctrlShow true;
_group ctrlEnable true;
{
    _x ctrlShow true;
    _x ctrlEnable true;
} forEach (allControls _group);
