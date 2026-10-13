/*
    Affiche / met à jour le bandeau du pas à pas (reste à l’écran même en pause) :
    étape, fonction en cours, temps restant, barre de progression, dernier essai, état de la liaison.
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

private _T = { _this call comspec_overwatch_connect_fnc_tr };
private _step = (_index + 1) min _total;
private _delay = missionNamespace getVariable ["COMSPEC_DiagIsolateDelay", 55];
private _remainTxt = if (_remain < 0) then { "—" } else { (format ["encore %1 s", round _remain]) call _T };

(_disp displayCtrl 10) ctrlSetStructuredText parseText ((format [
    "<t font='PuristaSemibold' size='0.95' color='#E6F0E8'>%1</t><t align='right' font='PuristaMedium' size='0.85' color='#F2AB33'>%2</t>",
    "Dépannage liaison · pas à pas" call _T,
    (format ["Étape %1 / %2", _step, _total]) call _T
]) call comspec_overwatch_connect_fnc_tr);

private _body = format [
    "<t font='PuristaSemibold' size='1.15' color='#FFFFFF'>%1</t><t align='right' font='PuristaMedium' size='0.9' color='#F2AB33'>%2</t>",
    _label call _T,
    _remainTxt
];
private _probe = missionNamespace getVariable ["COMSPEC_DiagIsolateProbeNote", ""];
if ((_probe isEqualType "") && {_probe isNotEqualTo ""}) then {
    _body = _body + format ["<br/><t font='PuristaMedium' size='0.78' color='#5CC76B'>%1</t>", _probe call _T];
};
(_disp displayCtrl 11) ctrlSetStructuredText parseText ((_body) call comspec_overwatch_connect_fnc_tr);

// Progression sur l'ensemble du pas à pas.
private _bg = _disp displayCtrl 13;
private _fill = _disp displayCtrl 12;
if (!isNull _bg && {!isNull _fill}) then {
    private _done = (_index + ((1 - ((_remain max 0) / (_delay max 1))) max 0 min 1)) / (_total max 1);
    private _p = ctrlPosition _bg;
    _fill ctrlSetPosition [_p select 0, _p select 1, (_p select 2) * (_done max 0 min 1), _p select 3];
    _fill ctrlCommit 0;
};

private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _linkTxt = switch (_link) do {
    case "linked": { "<t color='#5CC76B'>" + ("liaison OK" call _T) + "</t>" };
    case "connecting": { "<t color='#F2AB33'>" + ("connexion…" call _T) + "</t>" };
    default { "<t color='#94A399'>" + ("liaison coupée" call _T) + "</t>" };
};
(_disp displayCtrl 14) ctrlSetStructuredText parseText ((format [
    "<t font='PuristaLight' size='0.72' color='#94A399'>%1 · %2</t>",
    _linkTxt,
    "si le jeu s'arrête, cette fonction est en cause · Échap > Arrêter le pas à pas" call _T
]) call comspec_overwatch_connect_fnc_tr);
