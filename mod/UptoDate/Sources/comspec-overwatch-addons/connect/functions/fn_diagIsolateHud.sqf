/*
    Affiche / met à jour le bandeau de dépannage (reste à l’écran même en pause).
*/
params [
    ["_label", "", [""]],
    ["_index", 0, [0]],
    ["_total", 1, [0]],
    ["_remain", 55, [0]]
];

if (!hasInterface) exitWith {};

private _layer = ["COMSPEC_DiagIsolateHud"] call BIS_fnc_rscLayer;
if (isNull (uiNamespace getVariable ["COMSPEC_DiagIsolateHudDisp", displayNull])) then {
    _layer cutRsc ["COMSPEC_DiagIsolateHud", "PLAIN", 0, true];
};

private _disp = uiNamespace getVariable ["COMSPEC_DiagIsolateHudDisp", displayNull];
if (isNull _disp) exitWith {};

private _title = _disp displayCtrl 10;
private _body = _disp displayCtrl 11;
private _step = (_index + 1) min _total;
private _remainTxt = if (_remain < 0) then { "—" } else { format ["%1 s", round _remain] };
private _titleTxt = "<t align='center' font='PuristaBold' size='1.05' color='#e8f4f0'>Dépannage liaison</t>";
private _bodyTxt = format [
    "<t align='center' size='0.88' color='#2dd4a8'>Étape %1 / %2</t><br/><t align='center' font='PuristaBold' size='1.15' color='#ffffff'>%3</t><br/><t align='center' size='0.82' color='#ffd27a'>Encore %4</t><br/><t align='center' size='0.68' color='#8aa0b4'>Si le jeu s’arrête, c’est cette fonction. Relancez Arma : le dernier essai est mémorisé.</t>",
    _step,
    _total,
    _label,
    _remainTxt
];
private _probe = missionNamespace getVariable ["COMSPEC_DiagIsolateProbeNote", ""];
if ((_probe isEqualType "") && {_probe isNotEqualTo ""}) then {
    _bodyTxt = _bodyTxt + format ["<br/><t align='center' size='0.78' color='#7dffb3'>%1</t>", _probe];
};
if (!isNil "comspec_overwatch_connect_fnc_diagStatusSnapshot") then {
    private _snap = [] call comspec_overwatch_connect_fnc_diagStatusSnapshot;
    if ((_snap isEqualType createHashMap) && {(_snap getOrDefault ["hudHtml", ""]) isNotEqualTo ""}) then {
        _bodyTxt = _bodyTxt + "<br/>" + (_snap get "hudHtml");
    };
};
if (!isNull _title) then { _title ctrlSetStructuredText parseText _titleTxt; };
if (!isNull _body) then { _body ctrlSetStructuredText parseText _bodyTxt; };
