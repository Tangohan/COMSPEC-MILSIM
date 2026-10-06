/*
    Module « Générer depuis un brief / un scénario ».
    Pack scénario renseigné : pack prédéfini. Sinon : brief libre analysé
    (effectif, financier, IED, courrier, chef…).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_brief", "_pack", "_radius"];
    if (_pack isNotEqualTo "") then {
        [_pack, _pos, _radius] call comspec_sse_fnc_loadScenarioPack;
    } else {
        if ((trim _brief) isEqualTo "") then { _brief = "cellule logistique de 5 personnes"; };
        [_brief, _pos, _radius] call comspec_sse_fnc_generateFromBrief;
    };
    // Menus ACE des objets tagués chez les autres joueurs (filtrés à l'envoi).
    [nearestObjects [_pos, ["ThingX", "ReammoBox_F", "WeaponHolder", "LandVehicle", "Static"], _radius], 8] call comspec_sse_fnc_zeusBroadcastEnabled;
    [format ["Réseau SSE généré (%1) — rayon %2 m", if (_pack isNotEqualTo "") then { _pack } else { "brief" }, _radius]] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _brief = _logic getVariable ["Brief", "cellule logistique de 5 personnes"];
private _pack = toUpper (trim (_logic getVariable ["ScenarioPack", ""]));
private _radius = _logic getVariable ["Radius", 40];

if (_ctx get "zeus") then {
    [
        "Générer depuis un brief",
        [
            ["EDIT", "Brief narratif", "Ex. « cellule de 3 financiers », « atelier IED », « chef et courrier ». Ignoré si un pack est choisi.", _brief],
            ["COMBO", "Pack scénario", "Scénario prédéfini cohérent (prioritaire sur le brief).", ["pack", _pack] call comspec_sse_fnc_zeusComboData],
            ["SLIDER", "Rayon (m)", "Les personnes et objets dans ce rayon forment le réseau.", [10, 200, _radius, 0]]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply],
        "Placez d'abord les personnes et objets, puis posez ce module au centre."
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _brief, _pack, _radius] call _apply;
};

deleteVehicle _logic;
true
