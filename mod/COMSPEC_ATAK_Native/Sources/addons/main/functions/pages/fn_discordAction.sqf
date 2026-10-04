/*
    App Discord : message court vers le salon Discord de l'unité, relié par la communauté sur Athena.
    Le lien du salon reste sur Athena ; le téléphone envoie seulement le texte (COMSPEC Link, commande DiscordSend,
    route /api/atak/discord/send : 500 caractères, mentions retirées, un message toutes les 5 s).
    Params : [action, argument]
      "kind"  : type choisi pour le message libre (MSG, CONTACT, SITREP, SUPPORT, RTB)
      "quick" : envoi immédiat d'un raccourci (CONTACT, SITREP, SUPPORT, RTB)
      "send"  : envoi du message saisi
      "result": retour du poste [id, ok, détail] (interne)
*/
params [["_action", "send"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DISCORD") then { ["DISCORD"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _saveDraft = { private _m = ["msg"] call comspec_atak_native_fnc_formValue; if (_m isEqualType "") then { _s set ["discordDraft", _m]; }; };
private _hist = missionNamespace getVariable ["COMSPEC_ATAK_DiscordLog", []];

private _post = {
    params ["_kind", "_text"];
    if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Discord passe par Athena : COMSPEC Overwatch n'est pas chargé sur ce serveur.", 5, 30] call comspec_atak_native_fnc_notify; false };
    if (diag_tickTime < (_s getOrDefault ["discordNext", 0])) exitWith { ["WARNING", "Un message toutes les 5 secondes au plus.", 3, 20] call comspec_atak_native_fnc_notify; false };
    // Mentions neutralisées dès le téléphone (le poste les retire de toute façon).
    { _text = [_text, _x, ""] call CBA_fnc_replace; } forEach ["@everyone", "@here", "@"];
    _text = trim _text;
    if ((count _text) > 500) then { _text = (_text select [0, 499]) + "…"; };
    if (_text isEqualTo "" && {_kind isEqualTo "MSG"}) exitWith { ["WARNING", "Message vide.", 3, 20] call comspec_atak_native_fnc_notify; false };
    _s set ["discordNext", diag_tickTime + 5];
    private _cs = [player, true] call comspec_atak_native_fnc_unitCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    private _grid = if (_s getOrDefault ["discordGrid", true]) then { [getPosASL player, 8] call comspec_atak_native_fnc_gridRef } else { "" };
    private _id = format ["d%1", round (diag_tickTime * 10)];
    _hist pushBack [_id, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _kind, _text, "EN COURS", ""];
    while { (count _hist) > 10 } do { _hist deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_DiscordLog", _hist];
    private _json = [createHashMapFromArray [["text", _text], ["kind", _kind], ["grid", _grid], ["callsign", _cs], ["steam_uid", getPlayerUID player]]] call comspec_overwatch_connect_fnc_hashMapToJson;
    // Réseau simulé du téléphone, puis Athena (appel bloquant de la DLL : exécuté hors de l'image en cours).
    [{
        params ["_id", "_json"];
        [_id, _json] spawn {
            params ["_id", "_json"];
            private _raw = "COMSPECExtension" callExtension ["DiscordSend", [_json]];
            private _r = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
            _r params ["_ok", "_status", "_detail"];
            if (_status isEqualTo "") then { _detail = "COMSPEC Link à mettre à jour (envoi Discord absent de cette version)."; };
            // Erreur du poste : « HTTP 429: {…"message":"…"} » → on garde le message lisible.
            private _i = _detail find """message"":""";
            if (_i >= 0) then {
                private _rest = _detail select [_i + 11];
                _detail = _rest select [0, (_rest find """") max 0];
            };
            [{ ["result", _this] call comspec_atak_native_fnc_discordAction; }, [_id, _ok, _detail]] call CBA_fnc_execNextFrame;
        };
    }, [_id, _json], "Discord", 1] call comspec_atak_native_fnc_netSend;
    call _render;
    true
};

switch (_action) do {
    case "kind": { call _saveDraft; _s set ["discordKind", _arg]; call _render; };
    case "grid": { call _saveDraft; _s set ["discordGrid", !(_s getOrDefault ["discordGrid", true])]; call _render; };
    case "quick": {
        call _saveDraft;
        private _txt = switch (_arg) do {
            case "CONTACT": { "Contact ennemi." };
            case "SITREP": {
                private _u = units group player;
                private _ko = { !alive _x || {lifeState _x isEqualTo "INCAPACITATED"} } count _u;
                format ["%1 : %2 personnel(s), %3 valide(s), %4 hors de combat.", groupId group player, count _u, (count _u) - _ko, _ko]
            };
            case "SUPPORT": { "Besoin de soutien." };
            case "RTB": { "Retour à la base." };
            default { "" };
        };
        [_arg, _txt] call _post;
    };
    case "send": {
        call _saveDraft;
        if ([_s getOrDefault ["discordKind", "MSG"], _s getOrDefault ["discordDraft", ""]] call _post) then { _s set ["discordDraft", ""]; call _render; };
    };
    case "result": {
        _arg params ["_id", "_ok", "_detail"];
        private _i = _hist findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then { (_hist select _i) set [4, ["REFUSÉ", "PUBLIÉ"] select _ok]; (_hist select _i) set [5, ["", _detail] select !_ok]; };
        if (_ok) then { ["SUCCESS", "Message publié sur Discord", 3, 20] call comspec_atak_native_fnc_notify; } else { ["WARNING", format ["Discord : %1", [_detail, "envoi refusé"] select (_detail isEqualTo "")], 6, 30] call comspec_atak_native_fnc_notify; };
        call _render;
    };
};
true
