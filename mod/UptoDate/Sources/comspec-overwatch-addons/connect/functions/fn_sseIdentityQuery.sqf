/*
    Requête d'identité depuis le terminal SEEK.

    Interroge la base fictive d'Athena à partir des relevés déjà pris. Le résultat est
    déterministe : il dépend de la graine de l'entité et de la qualité des acquisitions,
    jamais d'un tirage refait à chaque appel. Deux interrogations du même sujet avec les
    mêmes relevés donnent le même verdict.

    Le chef de mission peut imposer le résultat depuis Eden ou Zeus :
      COMSPEC_SSE_MatchResult  "none" | "possible" | "confirmed"
      COMSPEC_SSE_Confidence   nombre 0-100
      COMSPEC_SSE_RecordRef    référence de dossier affichée

    Aucune reconnaissance réelle : on compare des identifiants de scénario.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
if ((count _samples) == 0) exitWith {
    ["Aucun relevé exploitable — prenez au moins une acquisition avant d’interroger.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];

// Même sujet, mêmes relevés : la requête a déjà été faite. On reprend son verdict au lieu de
// réinterroger la base (et d'empiler une ligne de plus dans le journal partagé du groupe).
// Un nouveau relevé (doigt, iris, visage) change la signature et relance une vraie requête.
private _qSig = str [
    _samples apply { if (_x isEqualType []) then { [_x param [0, ""], _x param [1, 0], _x param [3, []]] } else { _x } },
    if (isNull _target) then { [] } else { _target getVariable ["comspec_sse_facePhotoMeta", []] }
];
uiNamespace setVariable ["COMSPEC_SseQuerySig", _qSig];
private _lastQuery = if (isNull _target) then { [] } else { _target getVariable ["comspec_sse_seekLastQuery", []] };
if (
    (_lastQuery isEqualType [])
    && {(count _lastQuery) >= 2}
    && {(_lastQuery select 0) isEqualTo _qSig}
    && {((_lastQuery select 1) isEqualType [])}
    && {(count (_lastQuery select 1)) >= 3}
) exitWith {
    uiNamespace setVariable ["COMSPEC_SsePerson_Query", +(_lastQuery select 1)];
    uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", false];
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    ["Requête déjà faite sur ce sujet avec ces relevés : résultat repris, rien n'a été renvoyé. Ajoutez un relevé pour relancer.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
};

// Qualité consolidée : plus il y a de modalités et meilleures elles sont, plus le
// verdict est net. C'est ce qui donne une raison de refaire une acquisition ratée.
private _sum = 0;
{
    if ((_x isEqualType []) && {(count _x) >= 2}) then { _sum = _sum + (_x select 1); };
} forEach _samples;
private _avg = _sum / (count _samples);
private _coverage = (count _samples) min 3;

uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", true];
[] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;

// --- Moteur biométrique (pack SSE) : vraie recherche dans la base -----------------
// Les relevés détaillés (NFIQ par doigt, OD/OG, cliché) sont comparés au dossier
// réel du sujet, s'il en a un ; hors liaison, seule la base locale répond.
if (!isNil "comspec_sse_fnc_bioQuery" && {missionNamespace getVariable ["comspec_sse_bio_enabled", true]} && {!isNull _target}) exitWith {
    private _caps = createHashMapFromArray [["nfiqs", []], ["eyes", []], ["face", -1], ["attempt", 0]];
    {
        if ((_x isEqualType []) && {(count _x) >= 4}) then {
            switch (_x select 0) do {
                case "empreintes": { _caps set ["nfiqs", _x select 3]; };
                case "iris": { _caps set ["eyes", _x select 3]; };
            };
        };
    } forEach _samples;
    private _meta = _target getVariable ["comspec_sse_facePhotoMeta", []];
    if ((_meta isEqualType []) && {(count _meta) >= 3}) then {
        private _fq = _meta select 1;
        private _dry = [_target, player, "visage", "seek", true] call comspec_sse_fnc_bioCapture;
        _caps set ["face", if (_dry getOrDefault ["ok", false]) then { _fq min (_dry get "quality") } else { -1 }];
    };
    private _att = 0;
    { _att = _att + (_x select 1); } forEach ((_target getVariable ["comspec_sse_bioAttempts", []]) select { _x isEqualType [] && {(count _x) >= 2} });
    _caps set ["attempt", _att];

    private _disp0 = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    private _declared = "";
    if (!isNull _disp0) then { _declared = trim format ["%1 %2", ctrlText (_disp0 displayCtrl 9502), ctrlText (_disp0 displayCtrl 9501)]; };
    if (_declared isEqualTo "") then {
        _declared = trim format ["%1 %2", _target getVariable ["COMSPEC_SSE_FirstName", ""], _target getVariable ["COMSPEC_SSE_LastName", ""]];
    };

    private _online = ([] call comspec_overwatch_connect_fnc_sseLinkState) isEqualTo "linked";
    private _delay = if (_online) then { missionNamespace getVariable ["comspec_sse_bio_delayAbis", 12] } else { missionNamespace getVariable ["comspec_sse_bio_delayLocal", 5] };
    uiNamespace setVariable ["COMSPEC_SseQueryArgs", [_target, _caps, _declared]];
    private _fnc_send = {
        (uiNamespace getVariable ["COMSPEC_SseQueryArgs", []]) params [["_t", objNull], ["_c", createHashMap], ["_d", ""]];
        [_t, _c, _d, {
            params ["_res", "_ctx"];
            _ctx params ["_t", ["_sig", ""]];
            private _map = createHashMapFromArray [["match", "confirmed"], ["possible", "possible"], ["latent", "none"], ["insufficient", "insufficient"]];
            private _result = _map getOrDefault [_res getOrDefault ["verdict", "none"], "none"];
            uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", false];
            uiNamespace setVariable ["COMSPEC_SsePerson_Query", [_result, _res getOrDefault ["score", 0], _res getOrDefault ["ref", ""], [_res] call comspec_sse_fnc_bioPack]];
            if (!isNull _t) then { _t setVariable ["comspec_sse_seekLastQuery", [_sig, +(uiNamespace getVariable ["COMSPEC_SsePerson_Query", []])]]; };
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
            if (!isNil "comspec_overwatch_connect_fnc_seekHistoryPush") then {
                [_t, _result, _res getOrDefault ["score", 0], _res getOrDefault ["ref", ""]] call comspec_overwatch_connect_fnc_seekHistoryPush;
            };
            private _lines = [_res] call comspec_sse_fnc_bioDescribe;
            [(_lines select [0, 3]) joinString " · ", "tactical", ["info", "warn"] select (_result isEqualTo "confirmed" || {_res getOrDefault ["mismatch", false]})] call comspec_overwatch_connect_fnc_announce;
        }, [_t, uiNamespace getVariable ["COMSPEC_SseQuerySig", ""]]] call comspec_sse_fnc_bioQuery;
    };
    uiNamespace setVariable ["COMSPEC_SseQuerySend", _fnc_send];
    if (!isNil "ace_common_fnc_progressBar") exitWith {
        [
            _delay, [],
            { call (uiNamespace getVariable ["COMSPEC_SseQuerySend", {}]); },
            {
                uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", false];
                [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
                ["Interrogation interrompue.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
            },
            ["Interrogation de la base locale…", "Interrogation de l'ABIS (liaison)…"] select _online,
            { true },
            ["isNotInside"]
        ] call ace_common_fnc_progressBar;
    };
    [{ call (uiNamespace getVariable ["COMSPEC_SseQuerySend", {}]); }, [], _delay] call CBA_fnc_waitAndExecute;
};

private _fnc_resolve = {
    params ["_target", "_avg", "_coverage", ["_sig", ""]];

    private _seed = [_target] call comspec_overwatch_connect_fnc_sseUnitSeed;

    // Verdict imposé par le scénario, s'il existe.
    private _forced = "";
    if (!isNull _target) then {
        _forced = _target getVariable ["COMSPEC_SSE_MatchResult", ""];
        if (!(_forced isEqualType "")) then { _forced = ""; };
    };

    private _result = toLower _forced;
    if (!(_result in ["none", "possible", "confirmed"])) then {
        // Déterministe : la graine décide si le sujet est connu de la base.
        private _known = (_seed mod 100);
        _result = switch (true) do {
            case (_known < 62): { "none" };       // majorité : bruit opérationnel
            case (_known < 88): { "possible" };
            default { "confirmed" };
        };
        // Une acquisition pauvre ne permet pas de confirmer.
        if (_result isEqualTo "confirmed" && { _avg < 70 || _coverage < 2 }) then {
            _result = "possible";
        };
    };

    private _confidence = -1;
    if (!isNull _target) then {
        private _c = _target getVariable ["COMSPEC_SSE_Confidence", -1];
        if (_c isEqualType 0 && { _c >= 0 }) then { _confidence = _c; };
    };
    if (_confidence < 0) then {
        _confidence = switch (_result) do {
            // Bornes stables, modulées par la qualité réelle des relevés.
            case "confirmed": { 94 + ((_seed mod 50) / 10) };
            case "possible":  { 66 + (_seed mod 18) };
            default { 0 };
        };
        if (_result isNotEqualTo "none") then {
            _confidence = ((_confidence * (0.85 + (_avg / 660))) min 99.4) max 55;
        };
    };

    private _ref = "";
    if (!isNull _target) then {
        _ref = _target getVariable ["COMSPEC_SSE_RecordRef", ""];
        if (!(_ref isEqualType "")) then { _ref = ""; };
    };
    if (_ref isEqualTo "" && { _result isNotEqualTo "none" }) then {
        _ref = format ["BIO-%1", 10000 + (_seed mod 89999)];
    };

    uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", false];
    uiNamespace setVariable ["COMSPEC_SsePerson_Query", [_result, _confidence, _ref]];
    if (!isNull _target) then { _target setVariable ["comspec_sse_seekLastQuery", [_sig, [_result, _confidence, _ref]]]; };
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    if (!isNil "comspec_overwatch_connect_fnc_seekHistoryPush") then {
        [_target, _result, _confidence, _ref] call comspec_overwatch_connect_fnc_seekHistoryPush;
    };

    private _msg = switch (_result) do {
        case "confirmed": { format ["Correspondance confirmée — dossier %1.", _ref] };
        case "possible":  { format ["Correspondance possible (%1 %2) — à confirmer par le poste de commandement.", _confidence toFixed 1, "%"] };
        default { "Aucune correspondance dans la base." };
    };
    [_msg, "tactical", if (_result isEqualTo "confirmed") then { "warn" } else { "info" }] call comspec_overwatch_connect_fnc_announce;
};
uiNamespace setVariable ["COMSPEC_SseQueryResolve", _fnc_resolve];

// Interrogation non instantanée : la barre ACE tient le joueur en place.
if (!isNil "ace_common_fnc_progressBar") exitWith {
    [
        6,
        [_target, _avg, _coverage, _qSig],
        {
            (_this select 0) params ["_t", "_a", "_c", "_s"];
            [_t, _a, _c, _s] call (uiNamespace getVariable ["COMSPEC_SseQueryResolve", {}]);
        },
        {
            uiNamespace setVariable ["COMSPEC_SsePerson_QueryPending", false];
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
            ["Interrogation interrompue.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        },
        "Interrogation de la base d’identités…",
        { true },
        ["isNotInside"]
    ] call ace_common_fnc_progressBar;
};

[
    { _this call (uiNamespace getVariable ["COMSPEC_SseQueryResolve", {}]) },
    [_target, _avg, _coverage, _qSig],
    6
] call CBA_fnc_waitAndExecute;
