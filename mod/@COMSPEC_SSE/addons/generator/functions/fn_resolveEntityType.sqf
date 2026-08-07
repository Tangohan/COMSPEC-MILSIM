/*
    Déduit le type SSE d'une entité (PERSON, VEHICLE, RADIO, …).
    [_entity] call comspec_sse_fnc_resolveEntityType
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { "OBJECT" };

private _forced = toUpper (_entity getVariable ["comspec_sse_forcedType", ""]);
if (_forced != "") exitWith { _forced };

if (_entity isKindOf "CAManBase") exitWith { "PERSON" };
if (_entity isKindOf "LandVehicle" || {_entity isKindOf "Air"} || {_entity isKindOf "Ship"}) exitWith { "VEHICLE" };
if (_entity isKindOf "House" || {_entity isKindOf "Building"}) exitWith { "BUILDING" };
if (_entity isKindOf "ReammoBox_F" || {_entity isKindOf "WeaponHolder"} || {_entity isKindOf "WeaponHolderSimulated"}) exitWith {
    if (_entity isKindOf "WeaponHolder" || {_entity isKindOf "WeaponHolderSimulated"}) then { "WEAPON" } else { "CONTAINER" }
};

private _cls = toLower typeOf _entity;

if ((_cls find "phone") >= 0 || {(_cls find "smartphone") >= 0} || {_cls find "comspec_sse_phone" >= 0} || {_cls find "comspec_sse_smartphone" >= 0}) exitWith { "PHONE" };
if ((_cls find "laptop") >= 0 || {(_cls find "computer") >= 0} || {_cls find "comspec_sse_laptop" >= 0} || {(_cls find "pc_") >= 0}) exitWith { "COMPUTER" };
if (
    (_cls find "radio") >= 0
    || {(_cls find "anprc") >= 0}
    || {(_cls find "prc152") >= 0}
    || {(_cls find "prc148") >= 0}
    || {(_cls find "tfar_") >= 0}
    || {(_cls find "acre_") >= 0}
    || {_cls find "comspec_sse_satphone" >= 0}
) exitWith { "RADIO" };
if (
    (_cls find "document") >= 0
    || {(_cls find "leaflet") >= 0}
    || {(_cls find "file") >= 0}
    || {_cls find "comspec_sse_document" >= 0}
    || {_cls find "comspec_sse_notebook" >= 0}
) exitWith { "DOCUMENT" };
if (
    (_cls find "usb") >= 0
    || {(_cls find "sdcard") >= 0}
    || {(_cls find "harddrive") >= 0}
    || {_cls find "comspec_sse_usb" >= 0}
) exitWith { "MEDIA" };
if (
    (_cls find "rifle") >= 0
    || {(_cls find "pistol") >= 0}
    || {(_cls find "launcher") >= 0}
    || {(_cls find "weapon") >= 0}
) exitWith { "WEAPON" };

"OBJECT"
