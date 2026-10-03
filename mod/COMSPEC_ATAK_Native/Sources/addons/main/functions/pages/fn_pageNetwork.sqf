/* App Réseau : qualité de la liaison Athena, relais, zone radio et simulations roleplay. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _link = toLower (missionNamespace getVariable ["COMSPEC_LinkState", ["offline", "linked"] select ((_s getOrDefault ["networkState", ""]) isEqualTo "CONNECTED")]);
private _lat = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
private _loss = 0;
private _sent = 0;
if (_bridge && {!isNil "comspec_overwatch_connect_fnc_getPacketLossStats"}) then {
    private _st = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    if (_st isEqualType createHashMap) then { _loss = _st getOrDefault ["packet_loss_percent", 0]; _sent = _st getOrDefault ["packets_sent_total", 0]; };
};
// Stabilité : même barème que l'app État ATAK d'Overwatch.
private _stab = 100 - 0.85 * _loss;
if (_lat > 120) then { _stab = _stab - (((_lat - 120) / 20) min 25); };
if (_lat > 400) then { _stab = _stab - 10; };
if (_link isNotEqualTo "linked") then { _stab = _stab - 35; };
_stab = (round _stab) max 0;
private _stabLabel = switch (true) do { case (_stab >= 85): { "Excellente" }; case (_stab >= 70): { "Bonne" }; case (_stab >= 50): { "Correcte" }; case (_stab >= 30): { "Faible" }; default { "Critique" }; };
private _age = { params ["_k"]; private _t = missionNamespace getVariable [_k, -1]; if (!(_t isEqualType 0) || {_t <= 0}) then { "jamais" } else { format ["il y a %1 s", round (diag_tickTime - _t)] } };
private _rows = [
    ["title", "Liaison"],
    ["text", format ["État : <t color='%1'>%2</t><br/>Stabilité : %3 %% (%4)<br/>Latence : %5<br/>Perte de paquets : %6 %%<br/>Paquets envoyés : %7<br/>Dernière position envoyée : %8<br/>Dernière réponse Athena : %9",
        switch (_link) do { case "linked": { "#5cc76b" }; case "degraded"; case "connecting": { "#f2ab33" }; default { "#e5483a" }; }, toUpper _link,
        _stab, _stabLabel, [format ["%1 ms", round _lat], "non mesurée"] select (_lat < 0), round _loss, _sent,
        ["COMSPEC_LastPositionSync"] call _age, ["COMSPEC_LastHealthOk"] call _age]]
];
if (_bridge) then {
    private _relay = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
    _rows pushBack ["title", "Relais"];
    _rows pushBack ["text", if ((count _relay) isEqualTo 0) then { "<t color='#8a9a93'>Aucun relais déclaré sur la carte.</t>" } else {
        format ["%1 · %2 m · %3<br/>Portée %4 m · fiabilité %5 %% · %6 Mbit/s<br/>Mode de liaison : %7",
            _relay getOrDefault ["name", "Relais"], round (_relay getOrDefault ["dist", 0]),
            [["<t color='#e5483a'>hors portée</t>", "<t color='#5cc76b'>à portée</t>"] select (_relay getOrDefault ["in_range", false]), "<t color='#e5483a'>détruit</t>"] select !(_relay getOrDefault ["alive", true]),
            round (_relay getOrDefault ["range", 0]), round (_relay getOrDefault ["reliability_pct", 0]), _relay getOrDefault ["throughput_mbps", 0],
            ["Arcade", "Réaliste (relais obligatoire)"] select (missionNamespace getVariable ["COMSPEC_LinkViaRelays", false])]
    }];
    private _zone = missionNamespace getVariable ["COMSPEC_ZoneEffects", createHashMap];
    _rows pushBack ["title", "Zone radio"];
    _rows pushBack ["text", if (!(_zone isEqualType createHashMap) || {(count _zone) isEqualTo 0}) then { "Couverture normale." } else {
        format ["%1 (%2)<br/>Perte minimale %3 %% · latence +%4 ms", _zone getOrDefault ["name", "Zone"], _zone getOrDefault ["type", "?"], _zone getOrDefault ["packet_loss_floor", 0], _zone getOrDefault ["latency_add", 0]]
    }];
    _rows pushBack ["text", format ["Simulation de liaison dégradée : %1", ["inactive", "<t color='#f2ab33'>active</t>"] select ([] call comspec_overwatch_connect_fnc_isLinkDegradeSimActive)]];
    _rows pushBack ["buttons", [
        ["MESURER LA LATENCE", { [] call comspec_overwatch_connect_fnc_measureLatency; [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
        ["ROUVRIR LE CANAL", { ["enter"] call comspec_atak_native_fnc_athenaAction; }, true]
    ]];
} else {
    _rows pushBack ["text", "<t color='#8a9a93'>COMSPEC Overwatch n'est pas chargé : relais, zones et simulations ne sont pas disponibles.</t>"];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
