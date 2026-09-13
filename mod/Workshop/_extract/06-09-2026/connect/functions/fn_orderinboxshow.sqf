/*

    Ouvre la boîte des ordres reçus pour répondre (accepter / refuser / attente / proposition).

    Sur le téléphone ATAK : createDisplay pour ne pas fermer cTab.

*/



if (!hasInterface) exitWith {};



if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};



if (!isNull (uiNamespace getVariable ["COMSPEC_OrderInbox_Display", displayNull])) exitWith {};



private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];

if (isNull _parent) then {

    _parent = findDisplay 46;

};



private _ok = false;

private _disp = displayNull;

if (!isNull _parent) then {

    _disp = _parent createDisplay "COMSPEC_OrderInbox_Dialog";

    _ok = !isNull _disp;

} else {

    _ok = createDialog "COMSPEC_OrderInbox_Dialog";

    _disp = uiNamespace getVariable ["COMSPEC_OrderInbox_Display", displayNull];

};



if (!_ok || {isNull _disp}) exitWith {

    ["Impossible d’ouvrir les ordres reçus.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;

};



uiNamespace setVariable ["COMSPEC_OrderInbox_Display", _disp];

