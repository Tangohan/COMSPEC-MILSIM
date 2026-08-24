/*
    Ouverture de l’app BII-10 / SEEK II dans le tiroir ATAK.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_BII_group", _group];

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
