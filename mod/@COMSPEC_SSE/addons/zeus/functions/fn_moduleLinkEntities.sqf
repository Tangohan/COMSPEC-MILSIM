/*
    Module « Lier des entités SSE » (nouveau, V0.8).
    Eden : relie en chaîne les entités synchronisées.
    Zeus : relie la cible du module (si attachée) et la sélection Zeus courante.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _targets = +(_ctx get "targets");
if (_ctx get "zeus") then {
    { if !(_x isKindOf "Logic") then { _targets pushBackUnique _x; }; } forEach ([] call comspec_sse_fnc_curatorSelectedObjects);
};

if (count _targets < 2) exitWith {
    ["Lier des entités : il faut au moins deux entités.\nZeus : sélectionnez-les puis posez le module sur l'une d'elles. Eden : synchronisez-les au module.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _apply = {
    params ["_targets", "_relation", "_confidence", "_mode"];
    private _n = 0;
    if (_mode isEqualTo "STAR") then {
        private _hub = _targets select 0;
        for "_i" from 1 to ((count _targets) - 1) do {
            if ([_hub, _targets select _i, _relation, _confidence, "ZEUS"] call comspec_sse_fnc_zeusLink) then { _n = _n + 1; };
        };
    } else {
        for "_i" from 1 to ((count _targets) - 1) do {
            if ([_targets select (_i - 1), _targets select _i, _relation, _confidence, "ZEUS"] call comspec_sse_fnc_zeusLink) then { _n = _n + 1; };
        };
    };
    [format ["%1 lien(s) « %2 » créé(s) entre %3 entité(s).", _n, _relation, count _targets]] call comspec_sse_fnc_zeusNotify;
};

private _relations = ["ASSOCIATE", "CONTACT", "OWNER", "REFERENCES"];
private _relLabs = ["Associé", "Contact", "Propriétaire de", "Fait référence à"];
private _relation = toUpper (_logic getVariable ["Relation", "ASSOCIATE"]);
private _confidence = _logic getVariable ["Confidence", 0.7];
private _mode = toUpper (_logic getVariable ["Mode", "CHAIN"]);

if (_ctx get "zeus") then {
    [
        "Lier des entités SSE",
        [
            ["COMBO", "Relation", "Nature du lien affiché dans le graphe.", [_relations, _relLabs, (_relations find _relation) max 0]],
            ["SLIDER", "Confiance", "0 = rumeur · 1 = établi.", [0, 1, _confidence, 2]],
            ["COMBO", "Disposition", "Chaîne : A-B, B-C… · Étoile : la première entité reliée à toutes les autres.", [["CHAIN", "STAR"], ["Chaîne", "Étoile"], [0, 1] select (_mode isEqualTo "STAR")]]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_targets, _apply],
        format ["%1 entité(s) sélectionnée(s).", count _targets]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_targets, _relation, _confidence, _mode] call _apply;
};

deleteVehicle _logic;
true
