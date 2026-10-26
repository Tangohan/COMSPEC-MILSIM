/*
    Pivot : trouve d'autres entités SSE partageant numéro / alias / grid / id.
    [_entity, _emit] call comspec_sse_fnc_pivotSearch
      _emit : émet SSE_NetworkLinked (défaut false), une seule fois par paire et par machine.
              Seule l'exploitation (advanceExploitation) émet ; l'interface (graphe, liens Zeus) ne fait que lire,
              sinon chaque poste qui ouvre le graphe diffusait son propre événement global par lien.
*/
params [
    ["_entity", objNull, [objNull]],
    ["_emit", false, [true]]
];

if (isNull _entity) exitWith { [] };

private _keys = [];
private _id = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (!isNil "_id" && {_id isEqualType createHashMap}) then {
    { if (_x isEqualType "" && {_x != ""}) then { _keys pushBackUnique (toLower _x); }; } forEach [
        _id getOrDefault ["alias", ""],
        _id getOrDefault ["phone", ""],
        _id getOrDefault ["plate", ""],
        _id getOrDefault ["name", ""]
    ];
};
private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
if (!isNil "_devices" && {_devices isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _n = _x getOrDefault ["phoneNumber", ""];
            if (_n != "") then { _keys pushBackUnique (toLower _n); };
        };
    } forEach _devices;
};
private _locs = [_entity, "locations"] call comspec_sse_fnc_getSection;
if (!isNil "_locs" && {_locs isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _g = _x getOrDefault ["grid", ""];
            if (_g != "") then { _keys pushBackUnique (toLower _g); };
        };
    } forEach _locs;
};

if (_keys isEqualTo []) exitWith { [] };

private _matches = [];
// Candidats : entités SSE uniquement, sans doublons (allMissionObjects recoupe allUnits/vehicles).
// Balayage de la mission mis en cache 10 s : l'onglet graphe rappelle le pivot à chaque rafraîchissement.
// Cache vidé quand une entité devient SSE (intel/XEH_postInit) ; l'exploitation (_emit) rebalaye toujours.
private _cache = missionNamespace getVariable ["comspec_sse_pivotCandidates", [-1, []]];
private _candidates = _cache select 1;
if (_emit || {(_cache select 0) < 0} || {(diag_tickTime - (_cache select 0)) > 10}) then {
    _candidates = (allUnits + vehicles + (allMissionObjects "All")) select {
        _x getVariable ["comspec_sse_enabled", false]
    };
    _candidates = _candidates arrayIntersect _candidates;
    missionNamespace setVariable ["comspec_sse_pivotCandidates", [diag_tickTime, _candidates]];
};
// Liens déjà annoncés (clé « netId source|netId cible ») : un événement réseau par lien nouveau seulement.
private _emitted = missionNamespace getVariable ["comspec_sse_pivotEmitted", createHashMap];

{
    private _o = _x;
    private _data = [_o] call comspec_sse_fnc_getData;
    if (!isNull _o && {_o != _entity} && {!isNil "_data"}) then {
        // Fiche sérialisée mise en cache sur l'objet (local) : même fiche (même référence) et moins de 10 s, comme la
        // liste des candidats ; vidée par comspec_sse_recordChanged (intel/XEH_postInit). Plus de str de chaque fiche
        // à chaque rafraîchissement du graphe.
        private _bc = _o getVariable ["comspec_sse_pivotBlob", []];
        private _blob = "";
        if ((count _bc) == 3 && {(_bc select 0) isEqualRef _data} && {(diag_tickTime - (_bc select 2)) <= 10}) then {
            _blob = _bc select 1;
        } else {
            _blob = toLower str _data;
            _o setVariable ["comspec_sse_pivotBlob", [_data, _blob, diag_tickTime]];
        };
        private _hit = false;
        { if ((_blob find _x) >= 0) exitWith { _hit = true; }; } forEach _keys;
        if (_hit) then {
            _matches pushBack _o;
            private _key = format ["%1|%2", netId _entity, netId _o];
            if (_emit && {!(_key in _emitted)}) then {
                _emitted set [_key, true];
                ["SSE_NetworkLinked", [netId _entity, netId _o, _keys]] call comspec_sse_fnc_emitEvent;
            };
        };
    };
} forEach _candidates;
missionNamespace setVariable ["comspec_sse_pivotEmitted", _emitted];

_matches
