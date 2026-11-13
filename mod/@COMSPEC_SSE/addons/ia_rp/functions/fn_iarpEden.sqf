/*
    Attribut Eden « Profil IA RP » (contrôle COMSPEC_SSE_IarpProfileAttr, unités et module « Atelier IA ») :
    un résumé d'une ligne, « Question » et « Réponse de l'IA », et le bouton « Atelier IA… » qui ouvre l'atelier
    (fn_iarpWorkshop) par-dessus la fenêtre des attributs. Valeur : le profil en texte (fn_iarpProfile « serialize »),
    enregistré dans la mission ; question et réponse y sont la 1re question perso de ses réponses (fn_iarpAnswers
    « qaGet » / « qaSet »), la même que dans l'atelier et la fenêtre « Toutes les questions et réponses… ».
      ["load", [groupe, valeur]] · ["save", groupe] -> valeur · ["open", [groupe]] · ["set", [groupe, valeur]]
      ["hint", [groupe]] : ligne sous la question (exemple ; plusieurs questions ; une seule des deux remplie)
    Valeur relue telle quelle si question et réponse n'ont pas changé : les missions déjà faites (savoir, déblocage…)
    gardent exactement leur texte.
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
private _hint = {
    params ["_g"];
    private _q = trim (ctrlText (_g controlsGroupCtrl 103));
    private _r = trim (ctrlText (_g controlsGroupCtrl 105));
    private _n = (_g getVariable ["qa", ["", "", "", 0]]) param [3, 0];
    private _c = _g controlsGroupCtrl 104;
    private _half = (_q isEqualTo "") isNotEqualTo (_r isEqualTo "");
    _c ctrlSetText (call {
        if (_half) exitWith { localize "STR_COMSPEC_IARP_W_QaHalf" };
        if (_n > 1) exitWith { format [localize "STR_COMSPEC_IARP_W_QuestionMore", _n] };
        localize "STR_COMSPEC_IARP_W_QuestionHint"
    });
    _c ctrlSetTextColor ([[1, 1, 1, 0.6], [0.95, 0.72, 0.28, 1]] select _half);
};
// Valeur -> question et réponse (1re question perso), clé gardée pour l'écrire au même endroit.
private _qaShow = {
    params ["_g"];
    private _p = ["parse", _g getVariable ["value", ""]] call comspec_sse_fnc_iarpProfile;
    private _qa = ["qaGet", _p getOrDefault ["answers", []]] call comspec_sse_fnc_iarpAnswers;
    _g setVariable ["qa", _qa];
    (_g controlsGroupCtrl 103) ctrlSetText (_qa select 1);
    (_g controlsGroupCtrl 105) ctrlSetText (_qa select 2);
    [_g] call _hint;
};
// Question et réponse -> valeur ; inchangées : la valeur reste telle quelle.
private _sync = {
    params ["_g"];
    private _raw = _g getVariable ["value", ""];
    (_g getVariable ["qa", ["", "", "", 0]]) params ["_k", "_q0", "_r0"];
    private _q = ctrlText (_g controlsGroupCtrl 103);
    private _r = ctrlText (_g controlsGroupCtrl 105);
    if ([trim _q, trim _r] isEqualTo [trim _q0, trim _r0]) exitWith { _raw };
    private _p = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
    if ((count _p) isEqualTo 0) then { _p = ["defaults"] call comspec_sse_fnc_iarpProfile; };
    private _ans = ["qaSet", [_p get "answers", _k, _q, _r]] call comspec_sse_fnc_iarpAnswers;
    // Rien à garder (question seule, ou profil vide sans question) : la valeur d'avant.
    if (_ans isEqualTo (_p get "answers")) exitWith { _raw };
    _p set ["answers", _ans];
    ["serialize", _p] call comspec_sse_fnc_iarpProfile
};
switch (_op) do {
    case "load": {
        _a params [["_g", controlNull], ["_v", ""]];
        _g setVariable ["value", [_v, ""] select !(_v isEqualType "")];
        [_g] call _show;
        [_g] call _qaShow;
    };
    case "save": { [_a] call _sync };
    case "hint": {
        _a params [["_g", controlNull]];
        if (!isNull _g) then { [_g] call _hint; };
    };
    case "open": {
        _a params [["_g", controlNull]];
        if (isNull _g) exitWith {};
        // L'atelier part de la question et de la réponse en cours.
        _g setVariable ["value", [_g] call _sync];
        [_g] call _qaShow;
        ["open", ["eden", _g]] call comspec_sse_fnc_iarpWorkshop;
    };
    case "set": {
        _a params [["_g", controlNull], ["_v", ""]];
        if (isNull _g) exitWith {};
        _g setVariable ["value", _v];
        [_g] call _show;
        [_g] call _qaShow;
    };
};
