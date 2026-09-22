/*
    Envoie la note depuis le dialogue Reco.
*/
if (!hasInterface) exitWith {};
private _disp = uiNamespace getVariable ["COMSPEC_ReconNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9966; };
if (isNull _disp) exitWith {};

private _text = ctrlText (_disp displayCtrl 9762);
_text = trim _text;
if ((count _text) > 140) then { _text = _text select [0, 140]; };
private _tag = _disp getVariable ["COMSPEC_ReconTag", ""];
private _conf = _disp getVariable ["COMSPEC_ReconConfidence", "vu_direct"];
private _pos = uiNamespace getVariable ["COMSPEC_ReconNote_Pos", []];

if (_text isEqualTo "" && {_tag isEqualTo ""}) exitWith {
    ["Indiquez une observation ou un type.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

_disp closeDisplay 1;
[_text, _tag, _conf, _pos] call comspec_overwatch_connect_fnc_reconPushNote;
