/*
    Interrogation d'identité depuis un terminal (SEEK II, actions ACE) : construit la
    sonde à partir des relevés et l'envoie au serveur.

    Hors liaison : base locale du terminal. En liaison (COMSPEC Link) : ABIS complet
    et avis de recherche Athena.

    Params: [_target, _relevés, _declaredName, _callback, _context]
      _relevés HashMap : nfiqs [10], eyes [2], face (qualité ou -1)
    Le rappel reçoit [résultat de comspec_sse_fnc_bioSearch, _context].
*/
params [
    ["_target", objNull, [objNull]],
    ["_captures", createHashMap, [createHashMap]],
    ["_declared", "", [""]],
    ["_callback", {}, [{}]],
    ["_context", []]
];

private _link = toLower (call {
    // État de liaison Athena : COMSPEC_LinkState est une chaîne ("linked", "degraded"…). Sans état
    // COMSPEC Link, on suit la liaison propre de l'ATAK natif (barre d'état verte = en ligne).
    // Même verdict que la barre d'état du téléphone et le terminal SEEK (connect fn_sseLinkState).
    if (!isNil "comspec_overwatch_connect_fnc_sseLinkState") exitWith { [] call comspec_overwatch_connect_fnc_sseLinkState };
    private _s = missionNamespace getVariable ["COMSPEC_LinkState", ""];
    if (!(_s isEqualType "")) then { _s = ""; };
    _s = toLower _s;
    if (_s isEqualTo "connecting") then { _s = "degraded"; };
    if (_s isEqualTo "") then {
        private _n = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s = if (_n isEqualType createHashMap) then { toLower (_n getOrDefault ["networkState", "offline"]) } else { "offline" };
        if (_s isEqualTo "connected") then { _s = "linked"; };
    };
    _s
});
private _online = _link isEqualTo "linked" || {missionNamespace getVariable ["comspec_sse_bioForceOnline", false]};

private _probe = createHashMapFromArray [
    ["unit", _target],
    ["key", [_target] call comspec_sse_fnc_bioPersonKey],
    ["declaredName", _declared],
    ["scope", ["local", "abis"] select _online],
    ["nfiqs", _captures getOrDefault ["nfiqs", []]],
    ["eyes", _captures getOrDefault ["eyes", []]],
    ["face", _captures getOrDefault ["face", -1]],
    ["attempt", _captures getOrDefault ["attempt", 1]]
];
if (_online) then { _probe set ["wanted", [] call comspec_sse_fnc_bioWantedCache]; };

["search", _probe, _callback, _context] call comspec_sse_fnc_bioRequest
