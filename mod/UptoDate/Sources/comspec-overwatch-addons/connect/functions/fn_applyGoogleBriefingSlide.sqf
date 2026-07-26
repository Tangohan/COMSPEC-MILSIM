/*
    Applique une texture (chemin fichier local) au dialog 9970 et aux écrans Eden enregistrés.
    Params: [_texturePath, _title, _index, _total]
*/
params [
    ["_path", "", [""]],
    ["_title", "", [""]],
    ["_index", 0, [0]],
    ["_total", 1, [0]]
];

if (_path isEqualTo "") exitWith { false };

private _display = findDisplay 9970;
if (!isNull _display) then {
    private _ctrlPic = _display displayCtrl 9001;
    if (!isNull _ctrlPic) then { _ctrlPic ctrlSetText _path; };

    private _ctrlTitle = _display displayCtrl 9002;
    if (!isNull _ctrlTitle) then {
        private _label = if (_title isEqualTo "") then {
            format ["Diapositive %1", _index + 1]
        } else {
            _title
        };
        _ctrlTitle ctrlSetText _label;
    };

    private _ctrlIndex = _display displayCtrl 9003;
    if (!isNull _ctrlIndex) then {
        _ctrlIndex ctrlSetText format ["%1 / %2", _index + 1, _total max 1];
    };
};

private _screens = missionNamespace getVariable ["COMSPEC_BriefingScreens", []];
{
    _x params ["_object", "_selection"];
    if (!isNull _object) then {
        _object setObjectTexture [_selection, _path];
    };
} forEach _screens;

true
