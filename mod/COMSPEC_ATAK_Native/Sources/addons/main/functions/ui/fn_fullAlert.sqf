/*
    Alerte plein écran du téléphone (TOC ou santé) : bandeau de couleur sur tout l'écran de jeu,
    titre, message, auteur et heure ; vibration ; disparaît après la durée ou au prochain affichage.
    Visible téléphone rangé ou en main. Params : [titre, message, auteur, couleur RGBA, durée s]
*/
params [["_title", "ALERTE"], ["_msg", ""], ["_from", ""], ["_rgba", [0.55, 0.08, 0.06, 0.92]], ["_dur", 10]];
if (!hasInterface) exitWith {};
// Événements rejoués par la synchro serveur (arrivée en cours de partie) : pas d'alerte plein écran.
if (missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]) exitWith {};
disableSerialization;
private _d = findDisplay 46;
if (isNull _d) exitWith {};
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_FullAlert", []]);
private _x0 = safeZoneX; private _w = safeZoneW;
private _h = safeZoneH * 0.36;
private _y0 = safeZoneY + (safeZoneH - _h) / 2;
private _bg = _d ctrlCreate ["RscText", -1];
_bg ctrlSetPosition [_x0, _y0, _w, _h];
_bg ctrlSetBackgroundColor _rgba;
_bg ctrlCommit 0;
private _bar = _d ctrlCreate ["RscText", -1];
_bar ctrlSetPosition [_x0, _y0, _w, safeZoneH * 0.006];
_bar ctrlSetBackgroundColor [1, 1, 1, 0.85];
_bar ctrlCommit 0;
private _t = _d ctrlCreate ["RscStructuredText", -1];
_t ctrlSetPosition [_x0 + _w * 0.08, _y0 + _h * 0.12, _w * 0.84, _h * 0.8];
_t ctrlSetStructuredText parseText format ["<t align='center' size='3' font='RobotoCondensedBold' color='#ffffff'>%1</t><br/><t align='center' size='1.6' color='#ffffff'>%2</t><br/><br/><t align='center' size='1.1' color='#ffe0d8'>%3 · %4</t>",
    _title, _msg, _from, [dayTime, "HH:MM"] call BIS_fnc_timeToString];
_t ctrlCommit 0;
uiNamespace setVariable ["COMSPEC_ATAK_FullAlert", [_bg, _bar, _t]];
[] call comspec_atak_native_fnc_vibrate;
// La barre blanche se vide pendant la durée d'affichage.
_bar ctrlSetPosition [_x0, _y0, 0, safeZoneH * 0.006];
_bar ctrlCommit _dur;
[{ { ctrlDelete _x; } forEach _this; }, [_bg, _bar, _t], _dur] call CBA_fnc_waitAndExecute;
