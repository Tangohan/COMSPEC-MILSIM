/*
    Sandbox : génère un site aléatoire complet autour du joueur.
    [_radius] call comspec_sse_fnc_sandboxGenerateSite
*/
params [
    ["_radius", 50, [0]]
];

private _packs = ["INSURGENT_CELL", "SAFEHOUSE", "IED_WORKSHOP", "WEAPONS_DEPOT", "FINANCIAL_NODE"];
private _pick = _packs select (floor random count _packs);
[_pick, player, _radius] call comspec_sse_fnc_loadScenarioPack
