/*
    Indique si Zeus a autorisé un champ sur un contact téléphone.
    Défaut : rien n’est publié (liste vide ou absente).
*/
params [
    ["_unit", objNull, [objNull]],
    ["_key", "", [""]]
];
if (isNull _unit) exitWith { false };
private _want = toLower (trim _key);
if (_want isEqualTo "") exitWith { false };

private _rev = _unit getVariable ["COMSPEC_PhoneReveal", []];
if (!(_rev isEqualType [])) exitWith { false };
(_rev findIf { (toLower (trim (str _x))) isEqualTo _want }) >= 0
