/*
    Compatibilité avec le mod Workshop « Fenrir - Live Presentation » (FENRIR, 3824155646), facultatif :
    rien de ce mod n'est copié ni requis. Tout est détecté en jeu ; sans lui, chaque mode rend une valeur vide.
    On lit l'état public de ses présentations et on passe par sa fonction de commande, dont le serveur FENRIR
    vérifie lui-même les droits (« Qui peut piloter » du module FENRIR).

    État d'une présentation (missionNamespace "lpres_s_<id>", ids "p1", "p2"… donnés par son serveur) :
      [lien, titre, diapositives [[hash, titre, notes]...], index, statut "idle"|"loading"|"ready"|"error",
       message, écrans, contrôleurs, réglages, révision, heure de mise à jour, nom]
    Images : JPEG en base64 dans LPRES_cache (hash -> données), demandées au serveur par LPRES_fnc_requestImage.

    Modes :
      ["on"]                      FENRIR chargé
      ["list"]                    présentations à portée (écran ou contrôleur à moins de 100 m), triées par distance : [[id, distance]...]
      ["state", id]               état de la présentation ([] si inconnue)
      ["can", id]                 ce joueur peut la piloter (réponse indicative, le serveur FENRIR tranche)
      ["cmd", [id, action, arg]]  commande FENRIR : "next", "prev", "goto" index, "refresh", "load" lien
      ["image", hash]             base64 de la diapositive, "" en attendant (elle est demandée au serveur)
      ["view", id]                l'onglet DIAPOSITIVES montre cette présentation ("" : revient à mes diapositives)
      ["viewed"]                  id regardée dans DIAPOSITIVES, "" si aucune (ou si elle a disparu)
      ["push", id]                charge mon lien Google Slides dans la présentation FENRIR
      ["sig"]                     empreinte pour le redessin de l'app Briefing (PFH de XEH_postInitClient)
*/
params [["_mode", ""], ["_a", ""]];
private _fn = comspec_atak_native_fnc_fenrir;
private _on = !isNil "LPRES_fnc_request" && {isClass (configFile >> "CfgPatches" >> "lpres_main")};

switch (_mode) do {
    case "on": { _on };
    case "state": {
        if (!_on || {!(_a isEqualType "")} || {_a isEqualTo ""}) exitWith { [] };
        private _st = missionNamespace getVariable ["lpres_s_" + _a, []];
        if !(_st isEqualType [] && {(count _st) >= 12}) exitWith { [] };
        _st
    };
    case "list": {
        if (!_on || {isNull player}) exitWith { [] };
        // Ids suivis tant qu'il en existe ; trois trous de suite marquent la fin.
        private _out = [];
        private _miss = 0;
        private _i = 1;
        while { _miss < 3 && {_i < 500} } do {
            private _id = format ["p%1", _i];
            private _st = ["state", _id] call _fn;
            if ((count _st) isEqualTo 0) then { _miss = _miss + 1; } else {
                _miss = 0;
                private _objs = ((_st select 6) + (_st select 7)) select { !isNull _x };
                if ((count _objs) > 0) then {
                    private _d = selectMin (_objs apply { player distance _x });
                    if (_d <= 100) then { _out pushBack [_id, _d]; };
                };
            };
            _i = _i + 1;
        };
        [_out, [], { _x select 1 }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "can": {
        if ((count (["state", _a] call _fn)) isEqualTo 0) exitWith { false };
        [_a, player] call LPRES_fnc_canControl
    };
    case "cmd": {
        _a params [["_id", ""], ["_action", ""], ["_arg", ""]];
        if !(["can", _id] call _fn) exitWith {
            ["WARNING", "Présentation FENRIR en lecture seule : ses commandes sont réservées par le module FENRIR", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        [_id, _action, _arg] remoteExecCall ["LPRES_fnc_request", 2];
        true
    };
    case "image": {
        if (!_on || {_a isEqualTo ""} || {isNil "LPRES_cache"}) exitWith { "" };
        private _b64 = LPRES_cache getOrDefault [_a, ""];
        if (_b64 isEqualTo "") then { [_a] call LPRES_fnc_requestImage; };
        _b64
    };
    case "view": {
        uiNamespace setVariable ["COMSPEC_ATAK_BriefFenrir", _a];
        if (_a isNotEqualTo "") then { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["briefTab", "SLIDES"]; };
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BRIEFING") then { ["BRIEFING"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        true
    };
    case "viewed": {
        private _id = uiNamespace getVariable ["COMSPEC_ATAK_BriefFenrir", ""];
        if (_id isEqualTo "" || {(count (["state", _id] call _fn)) isEqualTo 0}) exitWith { "" };
        _id
    };
    case "push": {
        // Deck Google synchronisé en cours, sinon le lien enregistré dans l'engrenage.
        private _url = if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) then { missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""] } else { "" };
        if (_url isEqualTo "") then { _url = profileNamespace getVariable ["COMSPEC_ATAK_BriefGoogleUrl", ""]; };
        if (_url isEqualTo "") exitWith {
            ["WARNING", "Aucun lien Google Slides : enregistrez-le d'abord dans l'engrenage de l'app Briefing", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        if !(["cmd", [_a, "load", _url]] call _fn) exitWith { false };
        ["INFO", "Google Slides envoyé à la présentation FENRIR, chargement par son serveur…", 4, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "sig": {
        if (!_on) exitWith { [] };
        (["list"] call _fn) apply { [_x select 0, (["state", _x select 0] call _fn) select 9] }
    };
    default { false };
};
