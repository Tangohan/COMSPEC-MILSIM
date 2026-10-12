/*
    Avis de recherche Athena côté joueur (la DLL COMSPEC Link est chez lui), mis en
    cache 5 minutes. Réutilise la liste déjà chargée par l'app Avis de recherche
    du téléphone si elle existe.

    [] call comspec_sse_fnc_bioWantedCache -> [[type, id, réf, nom, alias, niveau, détails], ...]
*/
if !(missionNamespace getVariable ["comspec_sse_bio_athena", true]) exitWith { [] };

private _cache = uiNamespace getVariable ["comspec_sse_bioWantedCache", [-1e9, []]];
if ((diag_tickTime - (_cache select 0)) < 300) exitWith { _cache select 1 };

private _list = [];
private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Wanted", []];
if (_phone isEqualType [] && {(count _phone) > 0} && {(_phone select 0) isEqualType []} && {(_phone select 0) isNotEqualTo []}) then {
    _list = (_phone select 0) apply { _x select [0, 7] };
} else {
    private _raw = "COMSPECExtension" callExtension ["GetWantedNotices", []];
    if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
    if (!isNil "comspec_overwatch_connect_fnc_extResult") then { _raw = [_raw] call comspec_overwatch_connect_fnc_extResult; };
    if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
        {
            private _c = _x splitString toString [9];
            if ((count _c) >= 6) then { _list pushBack (_c select [0, 7]); };
        } forEach ((_raw select [3]) splitString toString [10]);
    };
};
uiNamespace setVariable ["comspec_sse_bioWantedCache", [diag_tickTime, _list]];
_list
