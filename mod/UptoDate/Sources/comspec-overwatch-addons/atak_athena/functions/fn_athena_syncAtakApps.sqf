/*
    Liste du tiroir : IceMan + apps Athena, depuis le config.
    IceMan lit localNamespace (plus le profil). Un reset force la liste complète.
*/
if (isNil "BCE_fnc_ATAK_getAPPs") exitWith { [] };

private _apps = [true, false] call BCE_fnc_ATAK_getAPPs;
if (!(_apps isEqualType [])) then { _apps = []; };

if (!isNil "BCE_fnc_ATAK_setAPPs_props" && {(count _apps) > 0}) then {
    [_apps] call BCE_fnc_ATAK_setAPPs_props;
};

_apps
