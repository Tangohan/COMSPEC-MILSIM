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
// Débit simulé du téléphone (fn_linkQuality) et état matériel (fn_deviceHealth).
private _lq = [] call comspec_atak_native_fnc_linkQuality;
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _rate = { params ["_k"]; if (_k >= 1000) then { format ["%1 Mbit/s", (_k / 1000) toFixed 1] } else { format ["%1 kbit/s", _k] } };
private _barTxt = { params ["_n"]; private _t = ""; for "_i" from 1 to 4 do { _t = _t + format ["<t color='%1'>●</t>", ["#3a4540", ["#e5483a", "#f2ab33", "#5cc76b"] select (((floor ((_n - 1) / 1.5)) min 2) max 0)] select (_i <= _n)]; }; _t };
_rows append [
    ["title", "Débit du téléphone"],
    ["text", if !(_lq get "sim") then { "<t color='#8a9a93'>Simulation de débit coupée par le serveur (réglages CBA).</t>" } else {
        format ["<t size='1.3'>%1</t>  %2<br/>Débit : <t color='#c9d4cf'>%3</t> · latence %4 ms · perte %5 %%<br/>Photo (600 Ko) : environ %6 s · message : %7 s%8",
            [_lq get "bars"] call _barTxt, _lq get "label", [_lq get "kbps"] call _rate, _lq get "latency", _lq get "loss",
            [round (((_lq get "latency") / 1000) + 4800 / ((_lq get "kbps") max 1)), "∞"] select ((_lq get "kbps") <= 0),
            [((((_lq get "latency") / 1000) + 8 / ((_lq get "kbps") max 1)) toFixed 1), "∞"] select ((_lq get "kbps") <= 0),
            ["", format ["<br/><t color='#f2ab33'>%1 envoi(s) en attente de réseau</t>", count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []])]] select ((count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []])) > 0)]
    }]
];
if ((_lq get "sim") && {(count (_lq get "factors")) > 0}) then {
    { _rows pushBack ["info", _x select 0, _x select 1]; } forEach (_lq get "factors");
};
_rows append [
    ["title", "État du téléphone"],
    ["info", "Écran et boîtier", switch (_hp get "state") do {
        case "OK": { "<t color='#5cc76b'>intact</t>" };
        case "OFF": { format ["<t color='#f2ab33'>%1</t>", _hp get "reason"] };
        default { format ["<t color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select ((_hp get "crack") >= 3), _hp get "reason"] };
    }],
    ["info", "Usure", format ["%1 %%", round ((_hp get "damage") * 100)]],
    ["info", "Batterie", call {
        private _lvl = round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
        (missionNamespace getVariable ["COMSPEC_ATAK_BatteryInfo", [0, []]]) params ["_r"];
        private _left = switch (true) do {
            case (_r < 0): { "en charge" };
            case (_r <= 0.01): { "" };
            default { private _m = round (_lvl / _r); format ["environ %1 h %2 restantes", floor (_m / 60), _m mod 60] };
        };
        format ["<t color='%1'>%2 %%</t>  <t color='#8a9a93'>%3</t>", switch (true) do { case (_lvl <= 5): { "#e5483a" }; case (_lvl <= 20): { "#f2ab33" }; default { "#5cc76b" }; }, _lvl, _left]
    }],
    ["text", call {
        (missionNamespace getVariable ["COMSPEC_ATAK_BatteryInfo", [0, []]]) params ["", "_f"];
        "<t size='0.8' color='#8a9a93'>" + ((_f apply { format ["%1 %2 %3 %%/min", _x select 0, ["", "+"] select ((_x select 1) < 0), abs (_x select 1)] }) joinString " · ") + "</t>"
    }],
    ["text", "<t size='0.8' color='#8a9a93'>Réparation : action ACE « Réparer le téléphone ATAK » avec une trousse à outils, ou « Changer de téléphone ATAK » avec un appareil de rechange.</t>"],
    ["buttons", [
        ["TEST DE DÉBIT", { uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []]; private _q = [] call comspec_atak_native_fnc_linkQuality; ["INFO", format ["Test de débit : %1 kbit/s, %2 ms, perte %3 %%", _q get "kbps", _q get "latency", _q get "loss"], 5, 20] call comspec_atak_native_fnc_notify; [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
        ["REDÉMARRER", { ["reboot"] call comspec_atak_native_fnc_deviceRepair; }]
    ]]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
