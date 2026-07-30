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

private _fnc_resolve = {
    params ["_target", "_avg", "_coverage"];

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
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;

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
        [_target, _avg, _coverage],
        {
            (_this select 0) params ["_t", "_a", "_c"];
            [_t, _a, _c] call (uiNamespace getVariable ["COMSPEC_SseQueryResolve", {}]);
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
    [_target, _avg, _coverage],
    6
] call CBA_fnc_waitAndExecute;
