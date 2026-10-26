/*
    App Wave Relay : radio maillée (MANET) entre les téléphones du camp.
    Onglets MON NŒUD (débit, sauts jusqu'à la passerelle, voisins directs), RÉSEAU (tous les nœuds, LOCALISER un équipier
    par triangulation), COUVERTURE (carte de débit des relais, liste des relais) et VOIX avec ACRE2 (qui émet sur mes fréquences,
    canal, fréquence et alternat de mes radios, équipiers connectés : fn_mpu5), sinon TALKGROUPS (talkgroup data choisi sur le téléphone).
    MON NŒUD montre aussi la radio maillée MPU5 portée (fn_mpu5).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["wrTab", "NODE"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _qCol = { params ["_q"]; switch (true) do { case (_q >= 60): { "#5cc76b" }; case (_q >= 30): { "#f2ab33" }; default { "#e5483a" }; } };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['wrTab', '%1']; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
private _nodes = _mesh get "nodes";
private _links = _mesh get "links";
private _hops = _mesh get "hops";
private _gw = _mesh get "gw";
private _myHops = _hops select 0;
private _acre = !isNil "acre_api_fnc_getCurrentRadioList";
private _rows = [["segment", "", [["MON NŒUD", "NODE"] call _tabBtn, [format ["RÉSEAU (%1)", count _nodes], "MESH"] call _tabBtn, ["COUVERTURE", "COV"] call _tabBtn, [["TALKGROUPS", "VOIX"] select _acre, "TG"] call _tabBtn]]];
private _refresh = ["buttons", [
    ["ACTUALISER", { [] spawn { ["scan", "force"] call comspec_atak_native_fnc_waveRelayAction; if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }; }; }],
    ["VOIR SUR LA CARTE", { if !(profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then { profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true]; }; ["MAP"] call comspec_atak_native_fnc_navigate; }]
]];
switch (_tab) do {
    case "MESH": {
        _rows pushBack ["section", "Nœuds du maillage", format ["%1 nœud(s), %2 lien(s) · portée nominale %3", count _nodes, count _links, [_mesh get "range"] call _dist]];
        // Tri : passerelle, puis par nombre de sauts, les nœuds isolés à la fin.
        private _order = [];
        { _order pushBack [[1000, _x] select (_x >= 0), _forEachIndex]; } forEach _hops;
        _order sort true;
        {
            private _i = _x select 1;
            (_nodes select _i) params ["_o", "_p", "_name", "_kind"];
            private _h = _hops select _i;
            private _deg = { (_x select 0) isEqualTo _i || {(_x select 1) isEqualTo _i} } count _links;
            private _txt = format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>%3<br/><t size='0.8' color='#8a9a93'>%4 · %5 voisin(s) · %6</t>",
                [["#e5483a", "#5cc76b"] select (_h >= 0), "#7fb6e6"] select (_kind isEqualTo "gw"), [_name] call _esc,
                ["", " <t size='0.8' color='#7fb6e6'>(moi)</t>"] select (_kind isEqualTo "me"),
                switch (true) do { case (_kind isEqualTo "gw" || {_i isEqualTo _gw}): { "passerelle" }; case (_h < 0): { "<t color='#e5483a'>isolé</t>" }; default { format ["%1 saut(s)", _h] }; },
                _deg, [player distance2D _p] call _dist];
            // Téléphone d'un équipier : triangulation par les relais et les téléphones qui l'entendent (balise coupée, joueur perdu).
            _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", _txt,
                [[], [["LOCALISER", compile format ["['locate', '%1'] call comspec_atak_native_fnc_relayNet; profileNamespace setVariable ['COMSPEC_ATAK_MeshOnMap', true]; ['MAP'] call comspec_atak_native_fnc_navigate;", netId _o]]]] select (_kind isEqualTo "node"),
                [0.9, 0.94, 0.91, 1]];
        } forEach _order;
        _rows pushBack _refresh;
    };
    case "COV": {
        private _cv = missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []];
        private _busy = missionNamespace getVariable ["COMSPEC_ATAK_CoverageBusy", false];
        private _rad = _s getOrDefault ["rcRadius", 5000];
        private _opt = { params ["_t", "_v"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['rcRadius', %1]; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _v], _v isEqualTo _rad] };
        private _relays = ["list"] call comspec_atak_native_fnc_relayNet;
        _rows append [
            ["section", "Couverture réseau", "Débit reçu à hauteur d'homme autour de moi, d'après les relais et passerelles du camp, relief et brouilleurs compris"],
            ["segment", "Rayon", [["2 km", 2000] call _opt, ["5 km", 5000] call _opt, ["10 km", 10000] call _opt], format ["case de %1 m", round ((2 * _rad) / 40)]]
        ];
        if (_busy) then {
            _rows pushBack ["text", format ["<t color='#f2ab33'>● Calcul en cours : %1 %%</t>", round (100 * (missionNamespace getVariable ["COMSPEC_ATAK_CoverageProgress", 0]))]];
            _rows pushBack ["buttons", [["ACTUALISER", { [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
        } else {
            _rows pushBack ["buttons", [
                ["CALCULER", { ["coverage"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, true],
                ["EFFACER", { ["clear"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]
            ]];
        };
        if ((count _cv) >= 8) then {
            _cv params ["_center", "_cell", "_cells", "_r", "_pct", "_when", "_nr", "_avg"];
            _rows append [
                ["info", "Terrain couvert", format ["<t color='%1'>%2 %%</t>", ["#e5483a", "#f2ab33", "#5cc76b"] select ((floor (_pct / 34)) min 2), _pct]],
                ["info", "Zones blanches", format ["%1 %%", 100 - _pct]],
                ["info", "Débit moyen couvert", format ["%1 Mbit/s", _avg toFixed 1]],
                ["info", "Calculé", format ["%1 · %2 · %3 relais", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist, _nr]]
            ];
            private _here = ["at", getPosASL player] call comspec_atak_native_fnc_relayNet;
            if (_here isNotEqualTo []) then { _rows pushBack ["info", "À ma position", format ["%1 Mbit/s · perte %2 %%", (_here select 0) toFixed 1, _here select 1]]; };
            if ((player distance2D _center) > _cell * 3) then { _rows pushBack ["text", "<t size='0.8' color='#f2ab33'>Vous avez bougé depuis le calcul : relancez-le pour la zone où vous êtes.</t>"]; };
        };
        _rows pushBack ["text", "<t size='0.8'><t color='#33d94d'>●</t> 4 Mbit/s et plus  <t color='#bfd933'>●</t> 1,5  <t color='#ff9926'>●</t> 0,5  <t color='#e5332a'>●</t> faible  <t color='#777777'>●</t> zone blanche</t>"];
        _rows pushBack ["switch", "Couverture et liens sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false], { ["mapLinks"] call comspec_atak_native_fnc_waveRelayAction; }, "Calque Wave Relay : couverture calculée, liens du maillage"];
        _rows pushBack ["switch", "Relais sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_RelaysLayer", true], { ["mapRelays"] call comspec_atak_native_fnc_waveRelayAction; }, "Calque Relais : pastilles, portée du relais utilisé (aussi dans CALQUES)"];
        _rows pushBack ["section", format ["Relais et passerelles (%1)", count _relays], "État, portée, débit, places, distance"];
        {
            _x params ["_o", "_ap", "_name", "_kind", "_rng", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "_root"];
            private _l = ["link", [_ap, _rng, _mb, eyePos player]] call comspec_atak_native_fnc_relayNet;
            private _det = [format ["portée %1", [_rng] call _dist], format ["%1 Mbit/s", _mb toFixed 1]];
            if (_sl > 0) then { _det pushBack format ["%1/%2 places", _u, _sl]; };
            if (_bt >= 0) then { _det pushBack format ["batterie %1 %%", round _bt]; };
            if (_kind isEqualTo "relay" && {!_root}) then { _det pushBack "répéteur"; };
            _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>  <t size='0.8' color='#8a9a93'>%3</t><br/><t size='0.8' color='#8a9a93'>%4 · %5 · %6 %7 · %8</t>",
                ["#777777", "#7fb6e6"] select _ok, [_name] call _esc, createHashMapFromArray [["relay", "relais"], ["gw", "passerelle véhicule"], ["cp", "PC mobile"], ["mpu5", "MPU5 d'un équipier"]] getOrDefault [_kind, _kind],
                [format ["<t color='#e5483a'>%1</t>", _st], "en service"] select _ok, _det joinString " · ", [player distance2D _ap] call _dist, [player getDir _ap] call _card,
                [format ["<t color='#e5483a'>hors de portée</t>"], format ["<t color='#5cc76b'>reçu %1 Mbit/s</t>", (_l select 1) toFixed 1]] select ((_l select 1) > 0 && {_ok})]];
        } forEach _relays;
        if (_relays isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun relais dans cette mission ni passerelle de véhicule active dans votre camp.</t>"]; };
        private _tri = missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []];
        if (_tri isNotEqualTo []) then {
            _rows pushBack ["section", "Triangulations", "Localisations par les relais (10 min sur la carte)"];
            { _rows pushBack ["info", _x select 0, [format ["± %1 m · %2 source(s)", _x select 2, count (_x select 3)], "une source"] select ((_x select 1) isEqualTo [])]; } forEach _tri;
            _rows pushBack ["buttons", [["EFFACER", { ["trilatClear"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
        };
    };
    case "TG": {
        if (_acre) then {
            // Voix ACRE2 (fn_mpu5) : qui émet sur mes fréquences, mes radios (canal, fréquence, alternat), le réseau voix
            // publié par les téléphones du camp. Rendu à nouveau à chaque début ou fin d'émission.
            private _mhz = { params ["_f"]; [format ["%1 MHz", _f toFixed 3], "?"] select (!(_f isEqualType 0) || {_f <= 0}) };
            private _spk = ["speakers"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["section", format ["En émission (%1)", count _spk], "Alternats ouverts sur les fréquences que vos radios reçoivent"];
            {
                _x params ["_u", "_id", "_f", "_lbl", "_since"];
                _rows pushBack ["text", format ["<t color='#e5483a'>● ÉMET</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2%3 · depuis %4 s</t>",
                    [["", [[_u, true] call comspec_atak_native_fnc_unitCallsign] call _esc] select (_u isNotEqualTo player), "Vous"] select (_u isEqualTo player),
                    [format ["%1 · ", [_lbl] call _esc], ""] select (_lbl isEqualTo ""), [_f] call _mhz, round _since]];
            } forEach _spk;
            if (_spk isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Personne n'émet sur vos fréquences.</t>"]; };
            private _radios = ["radios"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["section", "Mes radios ACRE2", "Canal, fréquence et alternat (PTT) de chaque radio portée, canal modifiable d'ici"];
            if ((count _radios) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune radio ACRE2 sur vous.</t>"]; };
            {
                _x params ["_id", "_name", "_ch", "_lbl", "_tx", "_rx", "_ptt", "_cur", "_on"];
                private _tags = "";
                if (_cur) then { _tags = _tags + "  <t size='0.8' color='#5cc76b'>active</t>"; };
                if (_ptt > 0) then { _tags = _tags + format ["  <t size='0.8' color='#7fb6e6'>PTT %1</t>", _ptt]; };
                if (_on) then { _tags = _tags + "  <t size='0.8' color='#e5483a'>ÉMET</t>"; };
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.85' color='#8a9a93'>Canal %3%4 · %5%6</t>",
                    [_name] call _esc, _tags, _ch, [format [" (%1)", [_lbl] call _esc], ""] select (_lbl isEqualTo ""), [_tx] call _mhz,
                    ["", format [" · réception %1", [_rx] call _mhz]] select (_rx isEqualType 0 && {_tx isEqualType 0} && {abs (_rx - _tx) >= 0.001})],
                    [
                        ["-", compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _ch - 1], false, _ch > 1],
                        ["+", compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _ch + 1]]
                    ], [0.9, 0.94, 0.91, 1]];
                _rows pushBack ["segment", "", [1, 2, 3, 4, 5, 6] apply { [str _x, compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _x], _x isEqualTo _ch] }];
            } forEach _radios;
            // Réseau voix : téléphones du camp qui publient leurs fréquences (ATAK et ACRE2) ou une MPU5 ; même fréquence d'abord.
            private _peers = ["peers"] call comspec_atak_native_fnc_mpu5;
            private _ord = [];
            { _ord pushBack [[1, 0] select (_x select 3), player distance2D (_x select 0), _forEachIndex]; } forEach _peers;
            _ord sort true;
            _rows pushBack ["section", format ["Connectés (%1)", count _peers], format ["%1 sur vos fréquences · téléphones ATAK du camp", { _x select 3 } count _peers]];
            {
                (_peers select (_x select 2)) params ["_u", "_mpu", "_fs", "_same", "_talk"];
                private _fl = (_fs apply { format ["%1%2", [format ["%1 ", [_x select 1] call _esc], ""] select ((_x select 1) isEqualTo ""), [_x select 0] call _mhz] }) joinString " · ";
                _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>%3%4%5<br/><t size='0.8' color='#8a9a93'>%6 · %7</t>",
                    ["#8a9a93", "#5cc76b"] select _same, [[_u, true] call comspec_atak_native_fnc_unitCallsign] call _esc,
                    ["", "  <t size='0.8' color='#7fb6e6'>MPU5</t>"] select _mpu,
                    ["", "  <t size='0.8' color='#5cc76b'>ma fréquence</t>"] select _same,
                    ["", "  <t size='0.8' color='#e5483a'>ÉMET</t>"] select _talk,
                    [_fl, "pas de radio ACRE2 publiée"] select (_fl isEqualTo ""), [player distance2D _u] call _dist]];
            } forEach _ord;
            if (_peers isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun équipier ne publie son réseau voix (téléphone ATAK et radio ACRE2 requis).</t>"]; };
            _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Vous ne voyez émettre que les fréquences réglées sur vos radios, sans tenir compte de la portée. Les joueurs sans téléphone ATAK n'apparaissent pas.</t>"];
        } else {
            private _tgs = [["CMD", "Commandement"], ["ALPHA", "Section Alpha"], ["BRAVO", "Section Bravo"], ["CHARLIE", "Section Charlie"], ["FEUX", "Appui feux"], ["LOG", "Logistique"], ["SAN", "Santé / MEDEVAC"], ["AIR", "Air / sol"]];
            private _mine = player getVariable ["COMSPEC_ATAK_Talkgroup", ""];
            private _side = side group player;
            private _peers = allPlayers select { alive _x && {side group _x isEqualTo _side} };
            _rows pushBack ["section", "Talkgroups data", ["Aucun talkgroup choisi : vous recevez tout le maillage", format ["Vous êtes sur %1", _mine]] select (_mine isNotEqualTo "")];
            {
                _x params ["_k", "_label"];
                private _cnt = { (_x getVariable ["COMSPEC_ATAK_Talkgroup", ""]) isEqualTo _k } count _peers;
                private _on = _k isEqualTo _mine;
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t size='0.8' color='#8a9a93'>%3</t><br/><t size='0.85' color='#8a9a93'>%4 membre(s)</t>",
                    ["#e6ece8", "#5cc76b"] select _on, _k, _label, _cnt],
                    [[["REJOINDRE", "QUITTER"] select _on, compile format ["['talkgroup', '%1'] call comspec_atak_native_fnc_waveRelayAction;", ["" , _k] select !_on], !_on]], [[0.6, 0.65, 0.62, 1], [0.36, 0.78, 0.42, 1]] select _on];
            } forEach _tgs;
            _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>ACRE2 non chargé : le talkgroup ne sert qu'à regrouper les échanges data du maillage.</t>"];
        };
    };
    default {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        private _kbps = _q getOrDefault ["kbps", 0];
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t size='1.3' font='COMSPEC_BarlowBold'>%1</t><br/><t color='%2'>%3</t> · %4<br/><t size='0.85' color='#8a9a93'>Latence %5 ms · perte %6 %%</t>",
            [(_nodes select 0) select 2] call _esc,
            [["#e5483a", "#5cc76b"] select (_myHops >= 0), "#7fb6e6"] select (_gw isEqualTo 0),
            switch (true) do { case (_gw isEqualTo 0): { "Je suis la passerelle" }; case (_myHops < 0): { "Coupé de la passerelle" }; default { format ["%1 saut(s) jusqu'à la passerelle", _myHops] }; },
            [format ["%1 kbit/s", _kbps], format ["%1 Mbit/s", (_kbps / 1000) toFixed 1]] select (_kbps >= 1000),
            _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]]];
        _rows append [
            ["info", "Passerelle", [[_mesh get "gwLabel"] call _esc, "<t color='#e5483a'>aucune</t>"] select (_gw < 0)],
            ["info", "Signal", format ["%1 (%2/4)", _q getOrDefault ["label", "?"], _q getOrDefault ["bars", 0]]]
        ];
        // Radio maillée MPU5 (objet d'inventaire, fn_mpu5) : portée du nœud, gain sur la liaison, débit relayé à l'équipe.
        private _nr = ["range", [player]] call comspec_atak_native_fnc_mpu5;
        if (["has", [player]] call comspec_atak_native_fnc_mpu5) then {
            (player getVariable ["COMSPEC_ATAK_Mpu5", []]) params [["_up", 0], ["_uh", 0]];
            _rows append [
                ["info", "Radio", format ["<t color='#5cc76b'>MPU5</t> · portée %1 · +%2 dB", [_nr] call _dist, ["gain"] call comspec_atak_native_fnc_mpu5]],
                ["info", "Relais pour l'équipe", [format ["%1 Mbit/s relayés%2", _up toFixed 1, ["", format [" (%1 bond(s) MPU5)", _uh]] select (_uh > 0)], "<t color='#f2ab33'>aucun relais reçu, rien à relayer</t>"] select (_up <= 0)]
            ];
        } else {
            _rows pushBack ["info", "Radio", format ["téléphone seul · portée %1 (sans MPU5)", [_nr] call _dist]];
        };
        if (_gw > 0) then {
            private _gp = (_nodes select _gw) select 1;
            _rows pushBack ["info", "Distance passerelle", format ["%1 %2", [player distance2D _gp] call _dist, [player getDir _gp] call _card]];
        };
        private _talk = if (_acre) then { "" } else { player getVariable ["COMSPEC_ATAK_Talkgroup", ""] };
        if (_talk isNotEqualTo "") then { _rows pushBack ["info", "Talkgroup", _talk]; };
        _rows pushBack ["switch", "Liens du maillage sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false], { ["mapLinks"] call comspec_atak_native_fnc_waveRelayAction; }, "Traits colorés selon la qualité du lien"];
        // Voisins directs : liens partant de mon nœud (index 0), les meilleurs d'abord.
        private _nb = [];
        {
            _x params ["_a", "_b", "_lq"];
            if (_a isEqualTo 0) then { _nb pushBack [_lq, _b]; };
            if (_b isEqualTo 0) then { _nb pushBack [_lq, _a]; };
        } forEach _links;
        _nb sort false;
        _rows pushBack ["section", format ["Voisins directs (%1)", count _nb], "Nœuds joints sans relais intermédiaire"];
        {
            _x params ["_lq", "_i"];
            (_nodes select _i) params ["_o", "_p", "_name", "_kind"];
            private _h = _hops select _i;
            _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>  <t color='%1'>%3 %%</t><br/><t size='0.8' color='#8a9a93'>%4 %5 (%6°) · %7</t>",
                [_lq] call _qCol, [_name] call _esc, _lq, [player distance2D _p] call _dist, [player getDir _p] call _card, round (player getDir _p),
                switch (true) do { case (_kind isEqualTo "gw" || {_i isEqualTo _gw}): { "passerelle" }; case (_h < 0): { "isolé" }; default { format ["%1 saut(s) de la passerelle", _h] }; }]];
        } forEach _nb;
        if ((count _nb) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun voisin à portée : montez sur un point haut ou rapprochez-vous d'un nœud.</t>"]; };
        private _factors = _q getOrDefault ["factors", []];
        if ((count _factors) > 0) then {
            _rows pushBack ["section", "Facteurs de débit", ""];
            { _rows pushBack ["info", _x select 0, _x select 1]; } forEach _factors;
        };
        _rows pushBack _refresh;
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
