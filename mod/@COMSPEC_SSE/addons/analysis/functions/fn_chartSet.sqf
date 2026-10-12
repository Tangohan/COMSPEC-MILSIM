/*
    Boutons du tableau des liens.
    [_key, _value] call comspec_sse_fnc_chartSet
      "mode" graph | orbat · "filter" all | people | orgs | things · "confirmed" (bascule)
      "fit" (recadrer) · "reveal" (Zeus : révéler le nœud choisi aux joueurs)
*/
params [["_key", "", [""]], ["_value", ""]];
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
switch (_key) do {
    case "mode": { _st set ["mode", _value]; [true] call comspec_sse_fnc_chartRefresh; };
    case "filter": { _st set ["filter", _value]; [true] call comspec_sse_fnc_chartRefresh; };
    case "confirmed": { _st set ["confirmed", !(_st getOrDefault ["confirmed", false])]; [true] call comspec_sse_fnc_chartRefresh; };
    case "fit": { [true] call comspec_sse_fnc_chartRefresh; };
    case "reveal": {
        private _id = _st getOrDefault ["sel", ""];
        if (_id isEqualTo "") exitWith { hint "Choisissez d'abord une personne ou une organisation."; };
        switch (_id select [0, 2]) do {
            case "P:": { ["reveal", [_id select [2], 2, "renseignement transmis par le PC"]] call comspec_sse_fnc_orgOp; };
            case "O:": { ["revealOrg", [_id select [2], 2, "renseignement transmis par le PC"]] call comspec_sse_fnc_orgOp; };
            default { hint "Seules les personnes et organisations se révèlent."; };
        };
        // Le serveur répond par la diffusion de l'état : rafraîchissement automatique.
    };
};
true
