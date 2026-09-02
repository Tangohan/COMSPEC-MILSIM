/*
    Versions observées des composants COMSPEC et mods compagnons.
    Une entrée vide signifie « non chargé / non lisible », jamais une valeur inventée.
    Retour: HashMap
*/
private _fncPatch = {
    params ["_patch"];
    if (!isClass (configFile >> "CfgPatches" >> _patch)) exitWith { "" };
    private _v = getText (configFile >> "CfgPatches" >> _patch >> "versionStr");
    if (_v isEqualTo "") then {
        private _n = getNumber (configFile >> "CfgPatches" >> _patch >> "version");
        if (_n > 0) then { _v = str _n; };
    };
    _v
};

private _out = createHashMap;
_out set ["overwatch", [] call comspec_overwatch_connect_fnc_getModVersion];
_out set ["overwatch_main", ["comspec_overwatch_main"] call _fncPatch];
_out set ["atak", ["comspec_overwatch_atak_athena"] call _fncPatch];
_out set ["ace", ["ace_main"] call _fncPatch];
if ((_out get "ace") isEqualTo "") then {
    _out set ["ace", ["ace_common"] call _fncPatch];
};
_out set ["ace_medical", ["ace_medical"] call _fncPatch];
_out set ["cba", ["cba_main"] call _fncPatch];
_out set ["acre", ["acre_main"] call _fncPatch];
_out set ["tfar", ["tfar_core"] call _fncPatch];
_out set ["kat", ["kat_main"] call _fncPatch];
if ((_out get "kat") isEqualTo "") then {
    _out set ["kat", ["kat_advancedMedical"] call _fncPatch];
};
_out set ["ctab", ["ctab_core"] call _fncPatch];
if ((_out get "ctab") isEqualTo "") then {
    _out set ["ctab", ["cTab"] call _fncPatch];
};

private _dll = "";
private _extRaw = ["COMSPECExtension" callExtension ["GetExtensionVersion", []]] call comspec_overwatch_connect_fnc_extResult;
private _extParts = _extRaw splitString "|";
if ((count _extParts) >= 2 && {(_extParts select 0) isEqualTo "OK"}) then {
    private _bits = (_extParts select 1) splitString " ";
    if ((count _bits) >= 2) then { _dll = _bits select 1; } else { _dll = _extParts select 1; };
};
_out set ["dll", _dll];
_out set ["extension", _dll];

private _arma = "";
private _armaBuild = "";
private _armaBranch = "";
private _pv = productVersion;
if (_pv isEqualType [] && {count _pv >= 3}) then {
    _arma = format ["%1", _pv select 2];
    if ((count _pv) >= 4) then { _armaBuild = str (_pv select 3); };
    if ((count _pv) >= 5 && {(_pv select 4) isEqualType ""}) then { _armaBranch = _pv select 4; };
};
_out set ["arma", _arma];
_out set ["arma_build", _armaBuild];
_out set ["arma_branch", _armaBranch];

_out
