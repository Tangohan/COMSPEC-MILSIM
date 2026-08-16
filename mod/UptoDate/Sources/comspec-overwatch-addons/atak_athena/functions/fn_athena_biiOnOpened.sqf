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
    "<t size='0.85'>Terminal biométrique BII-10 Identifi</t>",
    "",
    if (_hasBii) then {
        "<t color='#8dffc0'>Mod BII détecté</t>"
    } else {
        "<t color='#ffb080'>Mod BII Identifi non chargé</t>"
    },
    if (_hasDevice) then {
        "<t color='#8dffc0'>Appareil BII-10 en inventaire</t>"
    } else {
        if (_hasBii) then {
            "<t color='#ffd080'>Équipez un BII-10 Identifi</t>"
        } else {
            ""
        }
    },
    "",
    "<t size='0.72' color='#b9efff'>Les boutons ouvrent l’application Identifi (même coque téléphone) sur l’onglet demandé.</t>"
];

_body ctrlSetStructuredText parseText (_lines joinString "<br/>");
