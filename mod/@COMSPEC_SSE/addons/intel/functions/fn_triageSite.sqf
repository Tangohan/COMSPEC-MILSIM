/*
    Triage automatique des éléments SSE d'un site.
    [_center, _radius] call comspec_sse_fnc_triageSite
*/
params [
    ["_center", objNull, [objNull, []]],
    ["_radius", 50, [0]]
];

private _pos = if (_center isEqualType []) then { _center } else { getPosATL _center };
// Classes des entités SSE seulement (comme les modules Zeus) : plus d'arbres, rochers ni objets de terrain sans classe.
private _objs = nearestObjects [_pos, ["ThingX", "ReammoBox_F", "WeaponHolder", "AllVehicles", "Static"], _radius];
private _results = [];

{
    private _o = _x;
    if (!isNull _o && {!isNil {[_o] call comspec_sse_fnc_getData}}) then {
        // Jamais de génération ici : le contenu naît au premier examen (génération paresseuse). Un élément pas encore
        // généré est trié « UNKNOWN » (non examiné) sans être généré.
        private _gen = [[_o] call comspec_sse_fnc_getData, "generated", false] call comspec_sse_fnc_getPair;
        private _sec = [_o, "sections"] call comspec_sse_fnc_getSection;
        if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) then { _sec = createHashMap; };
        if (_gen && {isNil {_sec getOrDefault ["intelLayers", nil]}}) then {
            [_o] call comspec_sse_fnc_attachIntelLayers;
            _sec = [_o, "sections"] call comspec_sse_fnc_getSection;
            if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) then { _sec = createHashMap; };
        };

        private _best = 0;
        private _triage = "UNKNOWN";
        private _tags = [];
        private _layers = _sec getOrDefault ["intelLayers", createHashMap];
        if (_layers isEqualType createHashMap) then {
            {
                private _items = _layers getOrDefault [_x, []];
                {
                    if (_x isEqualType createHashMap) then {
                        private _v = _x getOrDefault ["INTEL_VALUE", 0];
                        if (_v > _best) then {
                            _best = _v;
                            _triage = _x getOrDefault ["triage", "UNKNOWN"];
                        };
                        { _tags pushBackUnique _x } forEach (_x getOrDefault ["tags", []]);
                    };
                } forEach _items;
            } forEach ["TACTICAL", "FIELD", "DETAILED", "FUSION"];
        };

        // HVT phone boost
        private _type = if (isNil {[_o] call comspec_sse_fnc_getData}) then {""} else {
            [[_o] call comspec_sse_fnc_getData, "type", ""] call BIS_fnc_getFromPairs
        };
        if (_type in ["PHONE", "SMARTPHONE", "PERSON"] && {_best >= 60}) then {
            _triage = "EXPLOIT_NOW";
        };
        if (_gen && {_best < 25}) then { _triage = "LOW_VALUE"; };

        private _entry = createHashMapFromArray [
            ["entity", _o],
            ["netId", netId _o],
            ["type", _type],
            ["triage", _triage],
            ["INTEL_VALUE", _best],
            ["tags", _tags],
            ["level", [_o] call comspec_sse_fnc_getExploitationLevel],
            ["label", [_o] call comspec_sse_fnc_makeEvidenceLabel]
        ];
        _results pushBack _entry;
    };
} forEach _objs;

// Tri décroissant par valeur
_results = [_results, [], { -(_x getOrDefault ["INTEL_VALUE", 0]) }, "ASCEND"] call BIS_fnc_sortBy;

// Événement local seulement (même signature qu'emitEvent) : le triage est relancé à chaque rafraîchissement de
// l'onglet SITE, et diffuser tout le tableau de résultats à chaque machine chargeait le réseau pour rien.
["comspec_sse_triageDone", [_pos, _results, clientOwner]] call CBA_fnc_localEvent;
_results
