/*
    Attribut Eden « Profil IA RP » (contrôle COMSPEC_SSE_IarpProfileAttr, unités et module « Atelier IA ») :
    un résumé d'une ligne et le bouton « Atelier IA… » qui ouvre l'atelier (fn_iarpWorkshop) par-dessus la fenêtre
    des attributs. Valeur : le profil en texte (fn_iarpProfile « serialize »), enregistré dans la mission.
      ["load", [groupe, valeur]] · ["save", groupe] -> valeur · ["open", [groupe]] · ["set", [groupe, valeur]]
    En jeu, l'expression de l'attribut applique le profil sur le serveur seulement (secrets et renseignement jamais
    diffusés aux joueurs) : ["applyRaw", [unité, valeur]] call comspec_sse_fnc_iarpProfile.
*/
params [["_op", ""], ["_a", []]];
disableSerialization;
private _show = {
    params ["_g"];
    private _raw = _g getVariable ["value", ""];
    private _p = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
    (_g controlsGroupCtrl 101) ctrlSetText (if ((count _p) isEqualTo 0) then { localize "STR_COMSPEC_IARP_W_NoProfile" } else { ["summary", _p] call comspec_sse_fnc_iarpProfile });
};
switch (_op) do {
    case "load": {
        _a params [["_g", controlNull], ["_v", ""]];
        _g setVariable ["value", [_v, ""] select !(_v isEqualType "")];
        [_g] call _show;
    };
    case "save": { _a getVariable ["value", ""] };
    case "open": {
        _a params [["_g", controlNull]];
        if (isNull _g) exitWith {};
        ["open", ["eden", _g]] call comspec_sse_fnc_iarpWorkshop;
    };
    case "set": {
        _a params [["_g", controlNull], ["_v", ""]];
        if (isNull _g) exitWith {};
        _g setVariable ["value", _v];
        [_g] call _show;
    };
};
