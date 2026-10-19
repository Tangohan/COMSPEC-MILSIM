/*
    Message différé du moteur (résultat de laboratoire) affiché à l'opérateur : notification du téléphone ATAK,
    bulle d'aide si le téléphone est fermé (comspec_sse_fnc_notify, qui traduit avant la mise en forme).
    [_title, _lines] call comspec_sse_fnc_bioNotify
*/
params [["_title", ""], ["_lines", []]];
if (!hasInterface) exitWith {};
[format ["%1\n%2", _title, _lines joinString "\n"], "TACTICAL", "SSE · Biométrie"] call comspec_sse_fnc_notify;
playSound "FD_Finish_F";
