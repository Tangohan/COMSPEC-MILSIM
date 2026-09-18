/*
    Applique image / titre / index sur l’UI Briefing ATAK (et dialog 9970 en repli).
    Params: [_path, _title, _index, _total]
*/
params [
    ["_path", "", [""]],
    ["_title", "", [""]],
    ["_index", 0, [0]],
    ["_total", 1, [0]]
];

private _label = if (_title isEqualTo "") then {
    format ["Diapositive %1", _index + 1]
} else {
    _title
};
private _idxTxt = format ["%1 / %2", _index + 1, _total max 1];

// App ATAK
private _group = uiNamespace getVariable ["COMSPEC_ATAK_Briefing_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    private _pic = _group controlsGroupCtrl 9852;
    if (!isNull _pic && {_path isNotEqualTo ""}) then { _pic ctrlSetText _path; };

    private _cap = _group controlsGroupCtrl 9853;
    if (!isNull _cap) then {
        _cap ctrlSetStructuredText parseText format [
            "<t align='center'>%1</t>",
            _label
        ];
    };

    private _idx = _group controlsGroupCtrl 9851;
    if (!isNull _idx) then {
        _idx ctrlSetStructuredText parseText format [
            "<t align='center'>%1</t>",
            _idxTxt
        ];
    };
};

// Dialog legacy 9970 (repli hors ATAK)
private _display = findDisplay 9970;
if (!isNull _display) then {
    private _ctrlPic = _display displayCtrl 9001;
    if (!isNull _ctrlPic && {_path isNotEqualTo ""}) then { _ctrlPic ctrlSetText _path; };

    private _ctrlTitle = _display displayCtrl 9002;
    if (!isNull _ctrlTitle) then { _ctrlTitle ctrlSetText _label; };

    private _ctrlIndex = _display displayCtrl 9003;
    if (!isNull _ctrlIndex) then { _ctrlIndex ctrlSetText _idxTxt; };
};

true
