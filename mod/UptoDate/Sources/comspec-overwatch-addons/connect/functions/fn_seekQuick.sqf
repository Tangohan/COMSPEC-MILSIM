/*
    CONTRÔLE RAPIDE du SEEK II (page 10, touche A1) : empreintes → iris → visage sur la cible,
    puis QUERY automatique (moteur biométrique, puis fichiers) et verdict
    CONCORDANT / DISCORDANT / INCONNU / SIGNALÉ face au document présenté.

    L'écran reste ouvert : la capture s'anime sur place (barre de progression et lignes du panneau
    mises à jour une à une, jamais de recréation de l'écran).

    [_op, _args] call comspec_overwatch_connect_fnc_seekQuick
      "start"  [_cible]     lance le contrôle complet (cible : visée, sinon la plus proche à 5 m)
      "query"               QUERY sur les relevés déjà pris (sans relevé : contrôle complet)
      "tick"                (interne) pas d'animation, 0,1 s
      "bioResult" [_res, _ctx]   (interne) réponse du moteur biométrique
      "fileResult" [_tag, _result]   (interne) évènement CBA comspec_sse_fileResult
      "finish"              (interne) verdict, historique, liens, évènement comspec_sse_seekVerdict
      "render"              repeint la page 10
      "events"              pose une fois les gestionnaires d'évènements CBA (fichiers, envoi)

    État : uiNamespace COMSPEC_SeekQuick (HashMap) ; verdict : uiNamespace COMSPEC_SeekVerdict.
    Évènement levé après chaque QUERY : comspec_sse_seekVerdict [_unit, _bioKey, _verdict, _rec, _lignes].
*/
params [["_op", "render", [""]], ["_args", []]];
if (!hasInterface) exitWith {};

private _tr = { _this call comspec_overwatch_connect_fnc_tr };
private _mods = ["empreintes", "iris", "visage"];
private _st = uiNamespace getVariable ["COMSPEC_SeekQuick", createHashMap];
if (!(_st isEqualType createHashMap)) then { _st = createHashMap; };
uiNamespace setVariable ["COMSPEC_SeekQuick", _st];

private _fnc_disp = {
    private _d = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    if (isNull _d) then { _d = findDisplay 9991; };
    _d
};
// Cible : celle du terminal, sinon la personne visée, sinon la plus proche (corps compris), 5 m au plus.
private _fnc_target = {
    params [["_t", objNull]];
    if (isNull _t) then { _t = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull]; };
    if (isNull _t) then {
        private _c = cursorObject;
        if (!isNull _c && {_c isKindOf "CAManBase"} && {_c != player} && {(player distance _c) < 5}) then {
            _t = _c;
        } else {
            private _best = 1e9;
            {
                private _dd = player distance _x;
                if (_dd < _best && {(vehicle _x) isEqualTo _x}) then { _best = _dd; _t = _x; };
            } forEach ((nearestObjects [player, ["CAManBase"], 5]) - [player]);
        };
    };
    if (!isNull _t) then { uiNamespace setVariable ["COMSPEC_SsePerson_Target", _t]; };
    _t
};
private _fnc_msg = {
    params ["_txt", ["_col", "#9a5b00"]];
    _st set ["msg", format ["<t size='0.44' font='TahomaB' color='%1'>%2</t>", _col, _txt]];
    ["render"] call comspec_overwatch_connect_fnc_seekQuick;
};

switch (_op) do {
    case "events": {
        if (missionNamespace getVariable ["comspec_seek_eventsReady", false]) exitWith {};
        if (isNil "CBA_fnc_addEventHandler") exitWith {};
        missionNamespace setVariable ["comspec_seek_eventsReady", true];
        ["comspec_sse_fileResult", { ["fileResult", _this] call comspec_overwatch_connect_fnc_seekQuick; }] call CBA_fnc_addEventHandler;
        ["comspec_sse_fileSubmitted", { ["submitted", _this] call comspec_overwatch_connect_fnc_seekSend; }] call CBA_fnc_addEventHandler;
    };

    case "start": {
        ["events"] call comspec_overwatch_connect_fnc_seekQuick;
        if !(["can"] call comspec_overwatch_connect_fnc_seekBattery) exitWith { ["render"] call comspec_overwatch_connect_fnc_seekBattery; };
        // Une étape restée en suspens (réponse perdue) ne bloque pas l'appareil plus de 30 s.
        if ((_st getOrDefault ["phase", "idle"]) in ["capture", "query"] && {(diag_tickTime - (_st getOrDefault ["busySince", 0])) < 30}) exitWith {};
        _args params [["_want", objNull, [objNull]]];
        private _t = [_want] call _fnc_target;
        [10] call comspec_overwatch_connect_fnc_sseTerminalPage;
        if (isNull _t) exitWith {
            ["Aucune personne à 5 m : visez la personne à contrôler." call _tr] call _fnc_msg;
        };
        private _run = (_st getOrDefault ["run", 0]) + 1;
        _st set ["run", _run];
        _st set ["target", _t];
        _st set ["phase", "capture"];
        _st set ["busySince", diag_tickTime];
        _st set ["step", 0];
        _st set ["t0", diag_tickTime];
        _st set ["steps", createHashMapFromArray (_mods apply { [_x, ["todo", -1, ""]] })];
        _st set ["msg", ""];
        _st set ["bio", createHashMap];
        _st set ["file", createHashMap];
        uiNamespace setVariable ["COMSPEC_SeekVerdict", createHashMap];
        // Nouveau contrôle : l'accusé d'envoi précédent ne vaut plus pour ce sujet.
        uiNamespace setVariable ["COMSPEC_SeekSend", createHashMap];
        if (!isNil "comspec_sse_fnc_bioAnimate" && {isNull objectParent player}) then {
            [player, _t, "empreintes", "start"] call comspec_sse_fnc_bioAnimate;
        };
        private _pfh = _st getOrDefault ["pfh", -1];
        if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
        _st set ["pfh", [{ ["tick", [_this select 1]] call comspec_overwatch_connect_fnc_seekQuick; }, 0.1] call CBA_fnc_addPerFrameHandler];
        ["render"] call comspec_overwatch_connect_fnc_seekQuick;
    };

    case "tick": {
        _args params [["_self", -1]];
        private _stop = {
            // Le gestionnaire se retire lui-même : l'état peut avoir été remis à zéro (écran refermé).
            if (_self >= 0) then { [_self] call CBA_fnc_removePerFrameHandler; };
            private _p = _st getOrDefault ["pfh", -1];
            if (_p >= 0 && {_p isNotEqualTo _self}) then { [_p] call CBA_fnc_removePerFrameHandler; };
            _st set ["pfh", -1];
            if (!isNil "comspec_sse_fnc_bioAnimate") then { [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate; };
        };
        if ((_st getOrDefault ["phase", ""]) isNotEqualTo "capture") exitWith { call _stop; };
        private _d = call _fnc_disp;
        private _t = _st getOrDefault ["target", objNull];
        if (isNull _d) exitWith { call _stop; _st set ["phase", "idle"]; };
        if !(["can"] call comspec_overwatch_connect_fnc_seekBattery) exitWith {
            call _stop; _st set ["phase", "idle"];
            ["render"] call comspec_overwatch_connect_fnc_seekBattery;
        };
        if (isNull _t || {(player distance _t) > 5}) exitWith {
            call _stop; _st set ["phase", "idle"];
            ["Contrôle interrompu : restez à moins de 5 m du sujet." call _tr, "#b3261e"] call _fnc_msg;
        };
        private _i = _st getOrDefault ["step", 0];
        private _kind = _mods select _i;
        private _dur = [3.5, 2.5, 2] select _i;
        private _p = ((diag_tickTime - (_st getOrDefault ["t0", diag_tickTime])) / _dur) min 1;
        _st set ["progress", _p];
        private _steps = _st get "steps";
        if (_p < 1) exitWith {
            (_steps get _kind) set [0, "run"];
            ["render"] call comspec_overwatch_connect_fnc_seekQuick;
        };

        // Fin de l'étape : relevé réel par le moteur (qualité selon le terrain), sinon tirage simple.
        private _engine = !isNil "comspec_sse_fnc_bioCapture" && {missionNamespace getVariable ["comspec_sse_bio_enabled", true]};
        private _cap = createHashMap;
        if (_engine) then { _cap = [_t, player, _kind, "seek"] call comspec_sse_fnc_bioCapture; };
        ["use", 0.8] call comspec_overwatch_connect_fnc_seekBattery;
        if (_engine && {!(_cap getOrDefault ["ok", false])}) then {
            _steps set [_kind, ["blocked", -1, _cap getOrDefault ["blocked", "Relevé impossible." call _tr]]];
        } else {
            private _q = if (_engine) then { _cap getOrDefault ["quality", 0] } else { 60 + floor (random 35) };
            private _st0 = systemTime;
            private _two = { params ["_n"]; if (_n < 10) then { format ["0%1", _n] } else { str _n } };
            private _clock = format ["%1:%2", [_st0 select 3] call _two, [_st0 select 4] call _two];
            if (_kind isEqualTo "visage") then {
                // Cliché de contrôle : même trace que la page VISAGE (synthèse, requête, fiche).
                private _meta = _t getVariable ["comspec_sse_facePhotoMeta", []];
                if (!(_meta isEqualType []) || {(count _meta) < 3} || {(_meta param [1, 0]) < _q}) then {
                    _t setVariable ["comspec_sse_facePhotoMeta", ["face", _q, _clock, "SEEK"], true];
                };
            } else {
                private _detail = if (_engine) then { [_cap getOrDefault ["nfiqs", []], _cap getOrDefault ["eyes", []]] select (_kind isEqualTo "iris") } else { [] };
                private _ref = if (_engine) then { _cap getOrDefault ["labRef", ""] } else { format ["LAB-%1-%2%3", date select 0, ["EMP", "IRI"] select (_kind isEqualTo "iris"), 1000 + floor (random 9000)] };
                private _row = [_kind, _q, _ref, _detail, _cap getOrDefault ["factors", []]];
                private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
                if (!(_samples isEqualType [])) then { _samples = []; };
                _samples = _samples select { !((_x isEqualType []) && {(_x param [0, ""]) isEqualTo _kind}) };
                _samples pushBack _row;
                uiNamespace setVariable ["COMSPEC_SsePerson_Samples", _samples];
                uiNamespace setVariable ["COMSPEC_SsePerson_BioPending", true];
                private _on = _t getVariable ["comspec_sse_bioSamples", []];
                if (!(_on isEqualType [])) then { _on = []; };
                _on = _on select { (_x isEqualType []) && {(_x param [0, ""]) isNotEqualTo _kind} };
                _on pushBack _row;
                _t setVariable ["comspec_sse_bioSamples", _on, true];
            };
            _steps set [_kind, [["low", "ok"] select (_q >= 65), _q, (_cap getOrDefault ["factors", []]) param [0, ""]]];
            if (!isNil "comspec_overwatch_connect_fnc_sseTeamLog") then {
                [_t, "Contrôle rapide" call _tr, format ["%1 %2", _kind call _tr, _q]] call comspec_overwatch_connect_fnc_sseTeamLog;
            };
        };
        if (isClass (configFile >> "CfgSounds" >> "ClickSoft")) then { playSound "ClickSoft"; };
        if (_i < 2) exitWith {
            _st set ["step", _i + 1];
            _st set ["t0", diag_tickTime];
            _st set ["progress", 0];
            if (!isNil "comspec_sse_fnc_bioAnimate" && {isNull objectParent player}) then {
                [player, _t, _mods select (_i + 1), "start"] call comspec_sse_fnc_bioAnimate;
            };
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
        };
        call _stop;
        _st set ["phase", "idle"];
        ["query", [true]] call comspec_overwatch_connect_fnc_seekQuick;
    };

    case "query": {
        ["events"] call comspec_overwatch_connect_fnc_seekQuick;
        if !(["can"] call comspec_overwatch_connect_fnc_seekBattery) exitWith { ["render"] call comspec_overwatch_connect_fnc_seekBattery; };
        _args params [["_auto", false, [false]]];
        if ((_st getOrDefault ["phase", ""]) in ["capture", "query"] && {(diag_tickTime - (_st getOrDefault ["busySince", 0])) < 30}) exitWith {};
        private _t = [] call _fnc_target;
        private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
        if (!(_samples isEqualType [])) then { _samples = []; };
        private _meta = if (isNull _t) then { [] } else { _t getVariable ["comspec_sse_facePhotoMeta", []] };
        if (!(_meta isEqualType [])) then { _meta = []; };
        // Rien à comparer : la touche QUERY lance le contrôle complet.
        private _nothing = isNull _t || {_samples isEqualTo [] && {(count _meta) < 3}};
        // Contrôle complet dont tous les relevés ont été refusés : verdict « relevés insuffisants ».
        if (_nothing && {_auto} && {!isNull _t}) exitWith {
            _st set ["phase", "query"];
            _st set ["bio", createHashMapFromArray [["verdict", "insufficient"]]];
            _st set ["file", createHashMap];
            ["finish"] call comspec_overwatch_connect_fnc_seekQuick;
        };
        if (_nothing) exitWith { ["start", [_t]] call comspec_overwatch_connect_fnc_seekQuick; };
        if ((_st getOrDefault ["target", objNull]) isNotEqualTo _t) then {
            _st set ["steps", createHashMapFromArray (_mods apply { [_x, ["todo", -1, ""]] })];
            _st set ["target", _t];
        };
        [10] call comspec_overwatch_connect_fnc_sseTerminalPage;
        private _run = (_st getOrDefault ["run", 0]) + 1;
        _st set ["run", _run];
        _st set ["phase", "query"];
        _st set ["busySince", diag_tickTime];
        _st set ["msg", ""];
        _st set ["bio", createHashMap];
        _st set ["file", createHashMap];
        ["use", 0.5] call comspec_overwatch_connect_fnc_seekBattery;
        uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", true];
        ["render"] call comspec_overwatch_connect_fnc_seekQuick;

        if (isNil "comspec_sse_fnc_bioQuery" || {!(missionNamespace getVariable ["comspec_sse_bio_enabled", true])}) exitWith {
            ["bioResult", [createHashMapFromArray [["verdict", "none"]], [_run]]] call comspec_overwatch_connect_fnc_seekQuick;
        };
        private _caps = createHashMapFromArray [["nfiqs", []], ["eyes", []], ["face", -1], ["attempt", 0]];
        {
            if ((_x isEqualType []) && {(count _x) >= 4}) then {
                switch (_x select 0) do {
                    case "empreintes": { _caps set ["nfiqs", _x select 3]; };
                    case "iris": { _caps set ["eyes", _x select 3]; };
                };
            };
        } forEach _samples;
        if ((count _meta) >= 3) then { _caps set ["face", _meta select 1]; };
        private _att = 0;
        { _att = _att + (_x select 1); } forEach ((_t getVariable ["comspec_sse_bioAttempts", []]) select { (_x isEqualType []) && {(count _x) >= 2} });
        _caps set ["attempt", _att];
        // Nom déclaré : document présenté, sinon la saisie de la page SUJET.
        private _doc = ["doc", [_t]] call comspec_overwatch_connect_fnc_seekState;
        private _declared = trim format ["%1 %2", _doc getOrDefault ["first", ""], _doc getOrDefault ["last", ""]];
        if (_declared isEqualTo "") then {
            (["subject"] call comspec_overwatch_connect_fnc_seekState) params ["_l", "_f"];
            _declared = trim format ["%1 %2", _f, _l];
        };
        [_t, _caps, _declared, {
            params ["_res", "_ctx"];
            ["bioResult", [_res, _ctx]] call comspec_overwatch_connect_fnc_seekQuick;
        }, [_run]] call comspec_sse_fnc_bioQuery;
    };

    case "bioResult": {
        _args params [["_res", createHashMap], ["_ctx", []]];
        if ((_ctx param [0, -1]) isNotEqualTo (_st getOrDefault ["run", 0])) exitWith {};
        if (!(_res isEqualType createHashMap)) then { _res = createHashMap; };
        _st set ["bio", _res];
        private _t = _st getOrDefault ["target", objNull];
        private _map = createHashMapFromArray [["match", "confirmed"], ["possible", "possible"], ["latent", "none"], ["insufficient", "insufficient"]];
        private _result = _map getOrDefault [_res getOrDefault ["verdict", "none"], "none"];
        private _packed = if (isNil "comspec_sse_fnc_bioPack") then { [] } else { [_res] call comspec_sse_fnc_bioPack };
        // Même format que la requête de la page REVUE : la fiche transmise et le bandeau watchlist suivent.
        uiNamespace setVariable ["COMSPEC_SsePerson_Query", [_result, _res getOrDefault ["score", 0], _res getOrDefault ["ref", ""], _packed]];
        uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", false];
        if (!isNull _t) then { _t setVariable ["comspec_sse_seekLastQuery", ["", +(uiNamespace getVariable ["COMSPEC_SsePerson_Query", []])]]; };

        // Fichiers (ATAK / Athena) : la clé biométrique de la cible, et le REC si elle en a déjà un.
        if (isNil "comspec_sse_fnc_fileQuery" || {isNull _t}) exitWith { ["finish"] call comspec_overwatch_connect_fnc_seekQuick; };
        private _p = createHashMapFromArray [["bioKey", [_t] call comspec_sse_fnc_bioPersonKey]];
        private _rec = _t getVariable ["comspec_seek_rec", ""];
        if (_rec isEqualType "" && {_rec isNotEqualTo ""}) then { _p set ["rec", _rec]; };
        private _tag = format ["SEEKQ-%1-%2", clientOwner, _st get "run"];
        _st set ["fileTag", _tag];
        ["QB", _p, "seek", _tag] call comspec_sse_fnc_fileQuery;
        // Pas de réponse en 10 s : verdict sur la biométrie seule.
        [{
            params ["_tag"];
            private _s = uiNamespace getVariable ["COMSPEC_SeekQuick", createHashMap];
            if ((_s getOrDefault ["fileTag", ""]) isEqualTo _tag && {(_s getOrDefault ["phase", ""]) isEqualTo "query"}) then {
                diag_log text format ["[COMSPEC SEEK] Fichiers sans réponse (%1) : verdict sur la biométrie seule.", _tag];
                _s set ["fileTag", ""];
                ["finish"] call comspec_overwatch_connect_fnc_seekQuick;
            };
        }, [_tag], 10] call CBA_fnc_waitAndExecute;
    };

    case "fileResult": {
        _args params [["_tag", ""], ["_result", createHashMap]];
        if (_tag isEqualTo "" || {(_st getOrDefault ["fileTag", ""]) isNotEqualTo _tag}) exitWith {};
        _st set ["fileTag", ""];
        _st set ["file", [createHashMap, _result] select (_result isEqualType createHashMap)];
        if ((_st getOrDefault ["phase", ""]) isEqualTo "query") then { ["finish"] call comspec_overwatch_connect_fnc_seekQuick; };
    };

    case "finish": {
        private _t = _st getOrDefault ["target", objNull];
        private _doc = ["doc", [_t]] call comspec_overwatch_connect_fnc_seekState;
        private _v = [_st getOrDefault ["bio", createHashMap], _st getOrDefault ["file", createHashMap], _doc, _t] call comspec_overwatch_connect_fnc_seekVerdict;
        _st set ["phase", "done"];
        uiNamespace setVariable ["COMSPEC_SeekVerdict", _v];
        private _verdict = _v get "verdict";
        // Inconnu : l'enrôlement part de la cible et du document présenté.
        if (_verdict isEqualTo "inconnu" && {(_doc getOrDefault ["dob", ""]) isNotEqualTo ""}) then { uiNamespace setVariable ["COMSPEC_SeekDob", _doc get "dob"]; };
        if (!isNull _t) then { _t setVariable ["comspec_seek_verdict", _verdict]; };

        private _key = if (isNull _t || {isNil "comspec_sse_fnc_bioPersonKey"}) then { "" } else { [_t] call comspec_sse_fnc_bioPersonKey };
        private _rec = if (isNull _t) then { "" } else { _t getVariable ["comspec_seek_rec", ""] };
        if (!(_rec isEqualType "")) then { _rec = ""; };
        private _bio = _st getOrDefault ["bio", createHashMap];
        // Historique local du SEEK (journal BII-10 du groupe) : une ligne par sujet.
        if (!isNil "comspec_overwatch_connect_fnc_seekHistoryPush") then {
            private _hr = switch (_verdict) do { case "concordant"; case "signale": { "confirmed" }; case "discordant": { "possible" }; default { "none" }; };
            [_t, _hr, _bio getOrDefault ["score", 0], [_rec, _v getOrDefault ["ref", ""]] select (_rec isEqualTo "")] call comspec_overwatch_connect_fnc_seekHistoryPush;
        };
        // Tableau des liens (addon analysis) : la personne contrôlée y entre.
        if (!isNull _t && {!isNil "comspec_sse_fnc_orgOp"}) then { ["seen", [_t]] call comspec_sse_fnc_orgOp; };
        diag_log text format ["[COMSPEC SEEK] Contrôle %1 : %2 (bio %3, fichiers %4).", _key, _verdict, _bio getOrDefault ["verdict", "none"], (count (_st getOrDefault ["file", createHashMap])) > 0];
        if (!isNil "CBA_fnc_localEvent") then {
            ["comspec_sse_seekVerdict", [_t, _key, _verdict, _rec, _v getOrDefault ["plain", []]]] call CBA_fnc_localEvent;
        };
        if (isClass (configFile >> "CfgSounds" >> "Beep_Target")) then { playSound "Beep_Target"; };
        ["render"] call comspec_overwatch_connect_fnc_seekQuick;
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };

    case "render": {
        private _d = call _fnc_disp;
        if (isNull _d) exitWith {};
        private _cMute = "#5a6670";
        private _cInk = "#14181d";
        private _cOk = "#1a7f37";
        private _cWarn = "#9a5b00";
        private _cBad = "#b3261e";
        private _cBlue = "#0a246a";
        private _phase = _st getOrDefault ["phase", "idle"];
        private _t = _st getOrDefault ["target", objNull];
        if (isNull _t) then { _t = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull]; };
        private _v = uiNamespace getVariable ["COMSPEC_SeekVerdict", createHashMap];
        if (!(_v isEqualType createHashMap)) then { _v = createHashMap; };
        private _done = _phase isEqualTo "done" && {(count _v) > 0};

        // Bandeau.
        private _ban = [0.42, 0.43, 0.45, 1];
        private _banTxt = "";
        switch (true) do {
            case (_phase isEqualTo "capture"): {
                private _i = _st getOrDefault ["step", 0];
                _ban = [0.06, 0.20, 0.52, 1];
                _banTxt = format ["<t size='0.62' font='TahomaB' color='#ffffff'>%1 %2 %3</t><br/><t size='0.44' color='#dde6ff'>%4</t>",
                    "CAPTURE" call _tr, (["EMPREINTES", "IRIS", "VISAGE"] select _i) call _tr, format ["%1/3", _i + 1],
                    (["Doigts posés à plat sur la platine", "Objectif à 10-15 cm des yeux", "Visage de face, regard vers le SEEK"] select _i) call _tr];
            };
            case (_phase isEqualTo "query"): {
                _ban = [0.06, 0.20, 0.52, 1];
                _banTxt = format ["<t size='0.62' font='TahomaB' color='#ffffff'>QUERY…</t><br/><t size='0.44' color='#dde6ff'>%1</t>",
                    (["Base biométrique locale puis fichiers", "ABIS puis fichiers (en ligne)"] select (([] call comspec_overwatch_connect_fnc_sseLinkState) isEqualTo "linked")) call _tr];
            };
            case (_done): {
                _ban = _v getOrDefault ["color", _ban];
                _banTxt = format ["<t size='0.66' font='TahomaB' color='#ffffff'>%1</t><br/><t size='0.44' font='TahomaB' color='#ffffff'>%2</t>",
                    _v getOrDefault ["title", ""], _v getOrDefault ["sub", ""]];
            };
            default {
                _banTxt = format ["<t size='0.62' font='TahomaB' color='#ffffff'>%1</t><br/><t size='0.44' color='#ffffff'>%2</t>",
                    "CONTRÔLE RAPIDE" call _tr, "A1 : empreintes, iris, visage puis QUERY automatique" call _tr];
            };
        };
        private _c = _d displayCtrl 9680;
        if (!isNull _c) then { _c ctrlSetBackgroundColor _ban; };
        _c = _d displayCtrl 9681;
        if (!isNull _c) then { _c ctrlSetStructuredText parseText _banTxt; };

        // Barre de progression : 3 captures puis la requête.
        private _bg = _d displayCtrl 9683;
        private _bar = _d displayCtrl 9684;
        if (!isNull _bg && {!isNull _bar}) then {
            private _frac = switch (_phase) do {
                case "capture": { ((_st getOrDefault ["step", 0]) + (_st getOrDefault ["progress", 0])) / 4 };
                case "query": { 0.85 };
                case "done": { 1 };
                default { 0 };
            };
            private _pp = ctrlPosition _bg;
            _pp set [2, ((_pp select 2) * _frac) max 0.001];
            _bar ctrlSetPosition _pp;
            _bar ctrlCommit 0;
            _bar ctrlSetBackgroundColor ([[0.08, 0.30, 0.78, 1], [0.12, 0.55, 0.22, 1]] select (_phase isEqualTo "done"));
        };

        // Panneau : sujet, trois étapes avec leur qualité, puis verdict détaillé.
        private _rows = [];
        if (isNull _t) then {
            _rows pushBack format ["<t size='0.44' color='%1'>%2</t>", _cWarn, "Aucun sujet : visez la personne puis A1." call _tr];
        } else {
            private _n = trim format ["%1 %2", _t getVariable ["COMSPEC_SSE_FirstName", ""], _t getVariable ["COMSPEC_SSE_LastName", ""]];
            if (_n isEqualTo "") then { _n = "personne visée" call _tr; };
            _rows pushBack format ["<t size='0.44' color='%1'>%2 </t><t size='0.44' font='TahomaB' color='%3'>%4</t><t size='0.42' color='%1'>  ·  %5 m%6</t>",
                _cMute, "Sujet :" call _tr, _cInk, _n, (player distance _t) toFixed 1, ["", format ["  ·  %1", "décédé" call _tr]] select (!alive _t)];
        };
        private _steps = _st getOrDefault ["steps", createHashMap];
        {
            private _kind = _x;
            private _label = (["EMPREINTES", "IRIS", "VISAGE"] select _forEachIndex) call _tr;
            (_steps getOrDefault [_kind, ["todo", -1, ""]]) params ["_s", "_q", "_note"];
            private _line = switch (_s) do {
                case "run": {
                    format ["<t size='0.44' font='TahomaB' color='%1'>[..]</t><t size='0.44' font='TahomaB' color='%2'>  %3</t><t size='0.42' color='%1'>  %4 %5%6</t>",
                        _cBlue, _cInk, _label, "capture" call _tr, round (100 * (_st getOrDefault ["progress", 0])), "%"]
                };
                case "ok";
                case "low": {
                    format ["<t size='0.44' font='TahomaB' color='%1'>[OK]</t><t size='0.44' font='TahomaB' color='%2'>  %3</t><t size='0.42' color='%1'>  %4 %5</t><t size='0.40' color='%6'>%7</t>",
                        [_cWarn, _cOk] select (_s isEqualTo "ok"), _cInk, _label, "qualité" call _tr, _q, _cMute,
                        if (_s isEqualTo "low") then { format ["  ·  %1", "médiocre" call _tr] } else { "" }]
                };
                case "blocked": {
                    format ["<t size='0.44' font='TahomaB' color='%1'>[X]</t><t size='0.44' font='TahomaB' color='%2'>  %3</t><t size='0.40' color='%1'>  %4</t>", _cBad, _cInk, _label, _note]
                };
                default { format ["<t size='0.44' font='TahomaB' color='%1'>[  ]</t><t size='0.44' color='%1'>  %2</t>", _cMute, _label] };
            };
            _rows pushBack _line;
        } forEach _mods;
        if (_done) then { _rows append (_v getOrDefault ["lines", []]); };
        // Accusé d'envoi (SIGNER ET ENVOYER).
        private _snd = uiNamespace getVariable ["COMSPEC_SeekSend", createHashMap];
        if (_snd isEqualType createHashMap && {(count _snd) > 0}) then { _rows pushBack (_snd getOrDefault ["line", ""]); };
        private _msg = _st getOrDefault ["msg", ""];
        if (_msg isNotEqualTo "") then { _rows pushBack _msg; };
        _c = _d displayCtrl 9682;
        if (!isNull _c) then { _c ctrlSetStructuredText parseText (_rows joinString "<br/>"); };

        // Boutons.
        private _busy = _phase in ["capture", "query"];
        _c = _d displayCtrl 9686;
        if (!isNull _c) then {
            _c ctrlSetText ((["CONTRÔLE RAPIDE", "RELANCER"] select _done) call _tr);
            _c ctrlEnable !_busy;
        };
        _c = _d displayCtrl 9687;
        if (!isNull _c) then { _c ctrlEnable (_done && {!isNil "comspec_sse_fnc_openApp"} && {!isNull _t}); };
        _c = _d displayCtrl 9688;
        if (!isNull _c) then { _c ctrlEnable !_busy; };
        _c = _d displayCtrl 9689;
        if (!isNull _c) then { _c ctrlEnable (!_busy && {(["steps"] call comspec_overwatch_connect_fnc_seekState) getOrDefault ["minimum", false] || {_done}}); };
    };
};
