private _controls = call Iceman_fnc_photo_findControls;
if ((count _controls) == 0) exitWith {false};

{
    private _ctrl = _controls getOrDefault [str _x, controlNull];
    if (!isNull _ctrl && {isNil {_ctrl getVariable "IcemanPhotoOriginalPosition"}}) then {
        _ctrl setVariable ["IcemanPhotoOriginalPosition", ctrlPosition _ctrl];
    };
} forEach [9400, 9401, 9410, 9420, 9421, 9430, 9431, 9440, 9441, 9442, 9443, 9444];

private _expanded = missionNamespace getVariable ["Iceman_PhotoLibrary_expanded", false];
private _preview = _controls getOrDefault ["9420", controlNull];
private _view = _controls getOrDefault ["9440", controlNull];
private _modeButton = _controls getOrDefault ["9444", controlNull];

{
    private _ctrl = _controls getOrDefault [str _x, controlNull];
    if (!isNull _ctrl) then {
        _ctrl ctrlShow !_expanded;
        _ctrl ctrlEnable !_expanded;
    };
} forEach [9401, 9410, 9421, 9430, 9431, 9441, 9442, 9443];

if (!isNull _view) then {
    _view ctrlShow true;
    _view ctrlEnable true;
};

if (_expanded) then {
    private _title = _controls getOrDefault ["9400", controlNull];
    private _titlePos = _title getVariable ["IcemanPhotoOriginalPosition", ctrlPosition _title];
    private _previewPos = _preview getVariable ["IcemanPhotoOriginalPosition", ctrlPosition _preview];
    private _viewPos = _view getVariable ["IcemanPhotoOriginalPosition", ctrlPosition _view];
    private _modePos = _modeButton getVariable ["IcemanPhotoOriginalPosition", ctrlPosition _modeButton];
    private _unitH = (_previewPos # 3) / 2.18;
    private _top = (_titlePos # 1) + (_titlePos # 3) + (0.10 * _unitH);
    private _height = ((_viewPos # 1) - _top - (0.10 * _unitH)) max (_previewPos # 3);

    _preview ctrlSetPosition [_previewPos # 0, _top, _previewPos # 2, _height];
    _preview ctrlCommit 0;

    _view ctrlSetPosition _viewPos;
    _view ctrlCommit 0;
    _view ctrlSetText "Library";

    _modeButton ctrlSetPosition _modePos;
    _modeButton ctrlCommit 0;
    _modeButton ctrlShow true;

    private _record = call Iceman_fnc_photo_getSelectedRecord;
    private _isLocal = !(_record isEqualTo []) && {(_record param [1, "received"]) == "local"};
    private _previewMode = missionNamespace getVariable ["Iceman_PhotoLibrary_previewMode", "live"];
    _modeButton ctrlEnable _isLocal;
    private _modeText = if (_isLocal) then {
        ["Original", "Live View"] select (_previewMode == "original")
    } else {
        "Live View"
    };
    _modeButton ctrlSetText _modeText;
} else {
    {
        private _ctrl = _controls getOrDefault [str _x, controlNull];
        if (!isNull _ctrl) then {
            private _original = _ctrl getVariable ["IcemanPhotoOriginalPosition", ctrlPosition _ctrl];
            _ctrl ctrlSetPosition _original;
            _ctrl ctrlCommit 0;
        };
    } forEach [9420, 9440, 9444];

    _view ctrlSetText "View";
    _modeButton ctrlShow false;
};

true
