/*
    Remplit la fenêtre de dépannage : dernier essai mémorisé, consignes.
*/
params ["_display"];
if (isNull _display) exitWith {};
uiNamespace setVariable ["COMSPEC_DiagIsolate_Display", _display];

private _last = profileNamespace getVariable ["COMSPEC_DiagIsolateLast", []];
private _lastTxt = "Aucun essai mémorisé pour l’instant.";
if ((_last isEqualType []) && {(count _last) >= 2}) then {
    private _label = _last select 1;
    if (_label isEqualType "" && {_label isNotEqualTo ""}) then {
        _lastTxt = format ["Dernier essai avant arrêt : %1. Si le jeu s’est fermé, commencez par cette fonction.", _label];
    };
};

private _hint = _display displayCtrl 20;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText format [
        "<t align='center' size='0.72' color='#c8d8e0'>Gardez le téléphone. Overwatch est coupé, puis chaque fonction revient une par une, 55 secondes d’écart. Trois essais partent vers le poste : un message, un repère, une photo. Un bandeau reste à l’écran. Si le jeu s’arrête, la fonction affichée est en cause.</t><br/><br/><t align='center' size='0.78' color='#ffd27a'>%1</t>",
        _lastTxt
    ];
};

private _run = missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false];
private _btnStart = _display displayCtrl 21;
private _btnStop = _display displayCtrl 22;
if (!isNull _btnStart) then { _btnStart ctrlEnable !_run; };
if (!isNull _btnStop) then { _btnStop ctrlEnable _run; };
