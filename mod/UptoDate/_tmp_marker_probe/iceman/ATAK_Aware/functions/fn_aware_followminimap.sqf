params ["_ctrlScreen"];

if (isNull _ctrlScreen) exitWith {false};
if (isNil "cTab_player" || {isNull cTab_player}) exitWith {false};

private _miniDisplay = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
if (isNull _miniDisplay || {!((ctrlParent _ctrlScreen) isEqualTo _miniDisplay)}) exitWith {false};
if !(ctrlShown _ctrlScreen) exitWith {false};

private _scale = missionNamespace getVariable ["cTabMapScale", ctrlMapScale _ctrlScreen];
if !(_scale isEqualType 0) then {
    _scale = ctrlMapScale _ctrlScreen;
};
_scale = _scale max 0.001;

private _playerPos = getPosASL (vehicle cTab_player);
_ctrlScreen ctrlMapAnimAdd [0, _scale, _playerPos];
ctrlMapAnimCommit _ctrlScreen;

true
