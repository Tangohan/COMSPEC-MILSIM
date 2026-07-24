/*
    Met à jour le contenu du dialog captcha troll.
*/

if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_TrollCaptcha_Display", displayNull];
if (isNull _display) exitWith {};

private _trollData = missionNamespace getVariable ["COMSPEC_TrollCaptchaData", createHashMap];
if (_trollData isEqualTo createHashMap) exitWith {};

// Récupérer les contrôles
private _ctrlTitle = _display displayCtrl 9951;
private _ctrlMessage = _display displayCtrl 9952;
private _ctrlButtons = [
    _display displayCtrl 9961,
    _display displayCtrl 9962,
    _display displayCtrl 9963,
    _display displayCtrl 9964
];

// Titre
private _title = _trollData getOrDefault ["title", "Vérification"];
_ctrlTitle ctrlSetStructuredText parseText format ["<t font='PuristaLight' size='1' align='center' color='#ffffff'>%1</t>", _title];

// Message
private _message = _trollData getOrDefault ["message", ""];
_ctrlMessage ctrlSetStructuredText parseText format ["<t font='PuristaLight' size='0.8' align='center' color='#000000'>%1</t>", _message];

// Boutons
private _buttons = _trollData getOrDefault ["buttons", []];
{
    if (_forEachIndex < count _buttons) then {
        _x ctrlSetText (_buttons select _forEachIndex);
        _x ctrlShow true;
    } else {
        _x ctrlShow false;
    };
} forEach _ctrlButtons;

// Animation d'apparition progressive
private _alpha = 0;
[{
    params ["_display", "_alpha"];
    _alpha = _alpha + 0.05;
    if (_alpha >= 1 || isNull _display) exitWith {
        [_this select 1] call CBA_fnc_removePerFrameHandler;
    };
    // Effet fade-in progressif (simulé avec des updates)
    _display displayCtrl 9952 ctrlCommit 0.05;
}, 0.05, [_display, _alpha]] call CBA_fnc_addPerFrameHandler;
