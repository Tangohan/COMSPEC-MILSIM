/*
    Ouverture de l’app BII-10 / SEEK II dans le tiroir ATAK.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_BII_group", _group];
["bii"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _body = _group controlsGroupCtrl 9801;
if (isNull _body) exitWith {};

private _hasBii = isClass (configFile >> "CfgPatches" >> "BII_Identifi")
    || {!isNil "BII_fnc_identifi_open"};

private _hasDevice = false;
if (_hasBii && {!isNil "BII_fnc_identifi_hasDevice"}) then {
    _hasDevice = [player] call BII_fnc_identifi_hasDevice;
};

private _lines = [
    "<t size='0.95' color='#5EC8F0'>Identification</t>",
    "<t size='0.72' color='#A0A0A0'>Reconnaissance et dossiers terrain</t>",
    "",
    if (_hasBii) then {
        "<t color='#7CFF9A'>Module d’identification présent</t>"
    } else {
        "<t color='#FF8A7A'>Module d’identification absent</t>"
    },
    if (_hasDevice) then {
        "<t color='#7CFF9A'>Appareil d’identification en inventaire</t>"
    } else {
        if (_hasBii) then {
            "<t color='#FFD080'>Équipez un appareil d’identification</t>"
        } else {
            ""
        }
    },
    "",
    "<t size='0.72' color='#A0A0A0'>Choisissez un outil ci-dessous. L’écran s’ouvre dans le même téléphone.</t>"
];

_body ctrlSetStructuredText parseText (_lines joinString "<br/>");

private _journal = _group controlsGroupCtrl 9820;
if (isNull _journal) exitWith {};

private _hist = [];
private _grp = group player;
if (!isNull _grp) then {
    private _shared = _grp getVariable ["COMSPEC_SeekQueryHistory", []];
    if (_shared isEqualType []) then { _hist = _shared; };
};
if ((count _hist) < 1) then {
    private _local = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []];
    if (_local isEqualType []) then { _hist = _local; };
};

private _jLines = [];
if ((count _hist) < 1) then {
    _jLines pushBack "<t color='#A0A0A0'>Aucune interrogation pour l’instant. Les relevés identifiés apparaissent ici, avec le statut, la confiance et l’heure.</t>";
} else {
    private _shown = 0;
    {
        if (_shown >= 12) then { continue };
        if (!(_x isEqualType []) || {(count _x) < 8}) then { continue };
        _x params ["", "_clock", "_who", "_alias", "_status", "_conf", "_ref", "_grid", ["_code", ""]];
        private _col = switch (toLower _code) do {
            case "confirmed": { "#7CFF9A" };
            case "possible": { "#FFE08A" };
            default { "#A8B8C8" };
        };
        private _aliasBit = if (_alias isEqualType "" && {_alias isNotEqualTo ""}) then {
            format [" «%1»", _alias]
        } else { "" };
        private _confBit = if ((_conf isEqualType 0) && {_conf > 0}) then {
            format [" · %1 %%", _conf]
        } else { "" };
        private _refBit = if (_ref isEqualType "" && {_ref isNotEqualTo ""}) then {
            format [" · %1", _ref]
        } else { "" };
        _jLines pushBack format [
            "<t color='#8FB4C8'>%1</t>  <t color='#E8F2FA'>%2%3</t><br/><t color='%4'>%5</t><t color='#A0A0A0'>%6%7 · grille %8</t>",
            _clock,
            _who,
            _aliasBit,
            _col,
            _status,
            _confBit,
            _refBit,
            _grid
        ];
        _shown = _shown + 1;
    } forEach _hist;
};

_journal ctrlSetStructuredText parseText (_jLines joinString "<br/><br/>");
_journal ctrlShow true;
_journal ctrlCommit 0;
