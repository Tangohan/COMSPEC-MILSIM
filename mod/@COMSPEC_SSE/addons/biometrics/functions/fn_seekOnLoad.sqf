private _display = findDisplay 93100;
if (isNull _display) exitWith {};

private _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
if (isNull _target) exitWith {};

if (!isNil "comspec_sse_fnc_uiSetRecord") then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

private _sum = [_target] call comspec_sse_fnc_getBiometricSummary;
private _data = [_target] call comspec_sse_fnc_getData;
private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
private _id = [_target, "identity"] call comspec_sse_fnc_getSection;
private _name = if (!isNil "_id" && {_id isEqualType createHashMap}) then { _id getOrDefault ["name", "—"] } else {"—"};
private _alias = if (!isNil "_id" && {_id isEqualType createHashMap}) then { _id getOrDefault ["alias", "—"] } else {"—"};
private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };

private _match = _bio getOrDefault ["matchHint", "Aucune recherche effectuée"];
private _conf = _bio getOrDefault ["matchConfidence", -1];
private _wl = _bio getOrDefault ["watchlistRef", "—"];
private _confTxt = if (_conf < 0) then {"—"} else {format ["%1%%", _conf]};

private _qFp = _bio getOrDefault ["fingerprintQuality", 0];
private _qIr = _bio getOrDefault ["irisQuality", 0];
private _qFace = _bio getOrDefault ["faceQuality", 0];
private _qDna = _bio getOrDefault ["dnaQuality", 0];
private _avgQ = 0;
private _qCount = 0;
{ if (_x > 0) then { _avgQ = _avgQ + _x; _qCount = _qCount + 1; }; } forEach [_qFp, _qIr, _qFace, _qDna];
if (_qCount > 0) then { _avgQ = round (_avgQ / _qCount); };

private _html = format [
    "<t color='#88ff88' font='PuristaMedium'>ACQUISITION BIOMÉTRIQUE</t><br/><br/>" +
    "<t color='#aaaaaa'>Record SSE</t><br/><t color='#ccffcc'>%1</t><br/><br/>" +
    "<t color='#aaaaaa'>Identité supposée</t><br/>" +
    "<t color='#ccffcc'>%2</t><br/><t color='#99cc99'>Alias: %3</t><br/><br/>" +
    "<t color='#aaaaaa'>Prélèvements</t><br/>" +
    "<t color='#bbeebb'>Empreintes : %4</t><br/>" +
    "<t color='#bbeebb'>Iris : %5</t><br/>" +
    "<t color='#bbeebb'>Visage : %6</t><br/>" +
    "<t color='#bbeebb'>ADN : %7</t><br/><br/>" +
    "<t color='#aaaaaa'>Qualité moyenne du prélèvement</t><br/><t color='#ccffcc'>%8%%</t><br/><br/>" +
    "<t color='#aaaaaa'>Résultat de recherche</t><br/>" +
    "<t color='#88ff88'>%9</t><br/>" +
    "<t color='#99cc99'>Score de correspondance: %10</t><br/>" +
    "<t color='#99cc99'>Référence: %11</t><br/><br/>" +
    "<t color='#aaaaaa'>Statut</t><br/><t color='#88ff88'>%12</t>",
    _uid,
    _name,
    _alias,
    _sum getOrDefault ["fingerprint", "NONE"],
    _sum getOrDefault ["iris", "NONE"],
    _sum getOrDefault ["face", "NONE"],
    _sum getOrDefault ["dna", "NONE"],
    _avgQ,
    _match,
    _confTxt,
    _wl,
    _sum getOrDefault ["status", "READY"]
];

(_display displayCtrl 93110) ctrlSetStructuredText parseText _html;
