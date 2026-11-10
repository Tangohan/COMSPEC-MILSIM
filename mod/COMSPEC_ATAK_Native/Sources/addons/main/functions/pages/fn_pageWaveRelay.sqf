/*
    App Wave Relay : radio maillée (MANET) entre les téléphones du camp.
    Onglets MON NŒUD (débit, sauts jusqu'à la passerelle, voisins directs), RÉSEAU (tous les nœuds, LOCALISER un équipier
    par triangulation), COUVERTURE (carte de débit des relais, liste des relais) et VOIX avec ACRE2 (qui émet sur mes fréquences,
    canal, fréquence et alternat de mes radios, équipiers connectés : fn_mpu5), sinon TALKGROUPS (talkgroup data choisi sur le téléphone).
    MON NŒUD montre aussi la radio de données détectée (fn_mpu5 "detected", même source que le facteur de débit) et, avec ACRE2,
    la radio ACRE2 reconnue et l'état de la voix par le réseau (fn_mpuVoice "summary"), redit en tête de l'onglet VOIX. État bon, dégradé ou coupé, cause et action : fn_waveRelayStatus. Tutoriel, bouton « ? » : fn_waveRelayTuto.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["wrTab", "NODE"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _qCol = { params ["_q"]; switch (true) do { case (_q >= 60): { (COMSPEC_ATAK_Th get "hexOk") }; case (_q >= 30): { (COMSPEC_ATAK_Th get "hexWarn") }; default { (COMSPEC_ATAK_Th get "hexAlert") }; } };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['wrTab', '%1']; (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['wrTuto', -1]; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k], _tab isEqualTo _k] };
private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
private _nodes = _mesh get "nodes";
private _links = _mesh get "links";
private _hops = _mesh get "hops";
private _gw = _mesh get "gw";
private _myHops = _hops select 0;
private _acre = !isNil "acre_api_fnc_getCurrentRadioList";
private _rows = [["segment", "", [["MON NŒUD", "NODE"] call _tabBtn, [format ["RÉSEAU (%1)", count _nodes], "MESH"] call _tabBtn, ["COUVERTURE", "COV"] call _tabBtn, [["TALKGROUPS", "VOIX"] select _acre, "TG"] call _tabBtn, ["?", { ["set", [0, -1] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["wrTuto", -1]) >= 0)] call comspec_atak_native_fnc_waveRelayTuto; }, false, "Tutoriel du réseau"]]]];
// Tutoriel (fn_waveRelayTuto) : à la place de l'onglet, sur le bouton « ? ».
private _tuto = _s getOrDefault ["wrTuto", -1];
if (_tuto >= 0) exitWith {
    _rows append (["rows", _tuto] call comspec_atak_native_fnc_waveRelayTuto);
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
_rows append (["offer"] call comspec_atak_native_fnc_waveRelayTuto);
// Objet obligatoire manquant (fn_netRequire) : dit sur tous les onglets.
private _denied = ["denied"] call comspec_atak_native_fnc_netRequire;
if (_denied isNotEqualTo [] && {_tab isNotEqualTo "NODE"}) then {
    _rows pushBack ["text", format ["<t color='%1'>Vous n'êtes plus un nœud du réseau : il manque %2.</t>", COMSPEC_ATAK_Th get "hexAlert", ["labels", [_denied]] call comspec_atak_native_fnc_netRequire], COMSPEC_ATAK_Th get "surface"];
};
private _refresh = ["buttons", [
    ["ACTUALISER", { [] spawn { ["scan", "force"] call comspec_atak_native_fnc_waveRelayAction; if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }; }; }],
    ["VOIR SUR LA CARTE", { if !(profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false]) then { profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true]; }; ["MAP"] call comspec_atak_native_fnc_navigate; }]
]];
switch (_tab) do {
    case "MESH": {
        _rows append (["playerRows"] call comspec_atak_native_fnc_meshTools);
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
            private _txt = format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>%3<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%4 · %5 voisin(s) · %6</t>",
                [[(COMSPEC_ATAK_Th get "hexAlert"), (COMSPEC_ATAK_Th get "hexOk")] select (_h >= 0), (COMSPEC_ATAK_Th get "hexInfo")] select (_kind isEqualTo "gw"), [_name] call _esc,
                ["", " <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>(moi)</t>"] select (_kind isEqualTo "me"),
                switch (true) do { case (_kind isEqualTo "gw" || {_i isEqualTo _gw}): { "passerelle" }; case (_h < 0): { "<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>isolé</t>" }; default { format ["%1 saut(s)", _h] }; },
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
        private _opt = { params ["_t", "_v"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['rcRadius', %1]; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _v], _v isEqualTo _rad] };
        private _relays = ["list"] call comspec_atak_native_fnc_relayNet;
        _rows append [
            ["section", "Couverture réseau", "Débit reçu à hauteur d'homme autour de moi, d'après les relais et passerelles du camp, relief et brouilleurs compris"],
            ["segment", "Rayon", [["2 km", 2000] call _opt, ["5 km", 5000] call _opt, ["10 km", 10000] call _opt], format ["case de %1 m", round ((2 * _rad) / 40)]]
        ];
        if (_busy) then {
            _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>● Calcul en cours : %1 %%</t>", round (100 * (missionNamespace getVariable ["COMSPEC_ATAK_CoverageProgress", 0]))]];
            _rows pushBack ["buttons", [["ACTUALISER", { [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]]];
        } else {
            _rows pushBack ["buttons", [
                ["CALCULER", { ["coverage"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, true],
                ["EFFACER", { ["clear"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]
            ]];
        };
        if ((count _cv) >= 8) then {
            _cv params ["_center", "_cell", "_cells", "_r", "_pct", "_when", "_nr", "_avg"];
            _rows append [
                ["info", "Terrain couvert", format ["<t color='%1'>%2 %%</t>", [(COMSPEC_ATAK_Th get "hexAlert"), (COMSPEC_ATAK_Th get "hexWarn"), (COMSPEC_ATAK_Th get "hexOk")] select ((floor (_pct / 34)) min 2), _pct]],
                ["info", "Zones blanches", format ["%1 %%", 100 - _pct]],
                ["info", "Débit moyen couvert", format ["%1 Mbit/s", _avg toFixed 1]],
                ["info", "Calculé", format ["%1 · %2 · %3 relais", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist, _nr]]
            ];
            private _here = ["at", getPosASL player] call comspec_atak_native_fnc_relayNet;
            if (_here isNotEqualTo []) then { _rows pushBack ["info", "À ma position", format ["%1 Mbit/s · perte %2 %%", (_here select 0) toFixed 1, _here select 1]]; };
            if ((player distance2D _center) > _cell * 3) then { _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Vous avez bougé depuis le calcul : relancez-le pour la zone où vous êtes.</t>"]; };
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
            _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%3</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%4 · %5 · %6 %7 · %8</t>",
                ["#777777", (COMSPEC_ATAK_Th get "hexInfo")] select _ok, [_name] call _esc, createHashMapFromArray [["relay", "relais"], ["gw", "passerelle véhicule"], ["cp", "PC mobile"], ["mpu5", "MPU5 d'un équipier"]] getOrDefault [_kind, _kind],
                [format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>%1</t>", _st], "en service"] select _ok, _det joinString " · ", [player distance2D _ap] call _dist, [player getDir _ap] call _card,
                [format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>hors de portée</t>"], format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>reçu %1 Mbit/s</t>", (_l select 1) toFixed 1]] select ((_l select 1) > 0 && {_ok})]];
        } forEach _relays;
        if (_relays isEqualTo []) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucun relais dans cette mission ni passerelle de véhicule active dans votre camp.</t>"]; };
        private _tri = missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []];
        if (_tri isNotEqualTo []) then {
            _rows pushBack ["section", "Triangulations", "Localisations par les relais (10 min sur la carte)"];
            { _rows pushBack ["info", _x select 0, [format ["± %1 m · %2 source(s)", _x select 2, count (_x select 3)], "une source"] select ((_x select 1) isEqualTo [])]; } forEach _tri;
            _rows pushBack ["buttons", [["EFFACER", { ["trilatClear"] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]]];
        };
    };
    case "TG": {
        if (_acre) then {
            // Voix ACRE2 (fn_mpu5) : qui émet sur mes fréquences, mes radios (canal, fréquence, alternat), le réseau voix
            // publié par les téléphones du camp. Rendu à nouveau à chaque début ou fin d'émission.
            private _mhz = { params ["_f"]; [format ["%1 MHz", _f toFixed 3], "?"] select (!(_f isEqualType 0) || {_f <= 0}) };
            // Voix par le réseau (fn_mpuVoice "summary") : état dit en clair, même désactivée, avec la façon de l'activer.
            (["summary"] call comspec_atak_native_fnc_mpuVoice) params ["_vCode", "_vState", "_vHelp"];
            _rows pushBack ["section", "À quoi sert cet onglet", "En deux mots"];
            _rows pushBack ["text", format ["<t size='0.9'>%1</t>", "Cet onglet montre qui parle sur les mêmes fréquences radio que vous, et dit si votre voix ACRE peut passer par le réseau MPU5 de vos équipiers. Ex. vous réglez votre radio ACRE sur 40,0 MHz ; si 2 équipiers sont aussi sur 40,0 ils apparaissent ici et vous pouvez les entendre même hors de portée directe, via la MPU5." call comspec_atak_native_fnc_tr], COMSPEC_ATAK_Th get "surface"];
            _rows pushBack ["section", "Voix par le réseau", "Votre voix ACRE2 relayée par le maillage MPU5 vers les radios reliées"];
            _rows pushBack ["info", "État", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexWarn", "hexOk"] select (_vCode isEqualTo "on")), [_vState] call _esc]];
            _rows pushBack ["info", "Radio ACRE2", format ["%1 · %2", format ["%1 détectée(s)" call comspec_atak_native_fnc_tr, count (["radios"] call comspec_atak_native_fnc_mpu5)], (["non reliée à la MPU5", "reliée à la MPU5"] apply { _x call comspec_atak_native_fnc_tr }) select (!((missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isEqualTo []))]];
            _rows pushBack ["text", format ["<t color='%1'>%3</t> %2", COMSPEC_ATAK_Th get "hexInfo", [(createHashMapFromArray [
                ["off", "un admin ou un Zeus active « Voix relayée par le maillage » dans Échap > Réalisme (section Maillage), puis Appliquer au serveur. Autre chemin : Réglages d'addons > COMSPEC > ATAK."],
                ["cut", "rétablir le lien radio : Échap > Réalisme, bouton Rétablir le lien radio."],
                ["noreal", "activer « Liaison téléphone-radio réaliste » dans Échap > Réalisme (section Maillage) et ne pas utiliser le profil Arcade."],
                ["nobridge", "appuyez sur le bouton RELIER MA RADIO À LA MPU5 (VOIX) ci-dessous."],
                ["wait", "rapprochez-vous d'un relais ou d'un équipier avec une MPU5 pour rejoindre le maillage."],
                ["on", "rien à faire. Réglez votre radio sur la fréquence d'un équipier relié."]
            ] getOrDefault [_vCode, ""]) call comspec_atak_native_fnc_tr] call _esc, "Prochain pas" call comspec_atak_native_fnc_tr]];
            if (_vHelp isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.9' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", [_vHelp] call _esc]]; };
            // Geste du joueur, comme l'action ACE : relie ou débranche le pont radio (fn_mpuVoice "bridge"), seulement si le serveur l'autorise.
            if (_vCode in ["nobridge", "wait", "on"] && {["has", [player]] call comspec_atak_native_fnc_mpu5}) then {
                _rows pushBack ["buttons", [[["RELIER MA RADIO À LA MPU5 (VOIX)", "DÉBRANCHER MA RADIO (VOIX)"] select (_vCode isNotEqualTo "nobridge"), {
                    if (((missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isEqualTo [])) then { ["bridge"] call comspec_atak_native_fnc_mpuVoice; } else { ["unbridge"] call comspec_atak_native_fnc_mpuVoice; };
                    [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
                }, _vCode isEqualTo "nobridge"]]];
            };
            private _spk = ["speakers"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["section", format ["En émission (%1)", count _spk], "Alternats ouverts sur les fréquences que vos radios reçoivent"];
            {
                _x params ["_u", "_id", "_f", "_lbl", "_since"];
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>● ÉMET</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2%3 · depuis %4 s</t>",
                    [["", [[_u, true] call comspec_atak_native_fnc_unitCallsign] call _esc] select (_u isNotEqualTo player), "Vous"] select (_u isEqualTo player),
                    [format ["%1 · ", [_lbl] call _esc], ""] select (_lbl isEqualTo ""), [_f] call _mhz, round _since]];
            } forEach _spk;
            if (_spk isEqualTo []) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Personne n'émet sur vos fréquences pour l'instant : normal si personne ne parle.</t>"]; };
            private _radios = ["radios"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["section", "Fréquences", "Vos radios ACRE2 : canal, fréquence et alternat (PTT), canal modifiable d'ici"];
            _rows pushBack ["text", format ["<t size='0.85'>%1</t>", "La fréquence est ce que votre radio écoute. Ex. le canal 2 de votre radio est réglé sur 40,0 MHz ; les équipiers sur 40,0 MHz sont ceux que vous entendez. Les boutons - et + changent de canal." call comspec_atak_native_fnc_tr]];
            if ((count _radios) isEqualTo 0) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucune radio ACRE2 sur vous.</t>"]; };
            {
                _x params ["_id", "_name", "_ch", "_lbl", "_tx", "_rx", "_ptt", "_cur", "_on"];
                private _tags = "";
                if (_cur) then { _tags = _tags + "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>active</t>"; };
                if (_ptt > 0) then { _tags = _tags + format ["  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>PTT %1</t>", _ptt]; };
                if (_on) then { _tags = _tags + "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>ÉMET</t>"; };
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Canal %3%4 · %5%6</t>",
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
                _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>%3%4%5<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%6 · %7</t>",
                    [(COMSPEC_ATAK_Th get "hexTextMuted"), (COMSPEC_ATAK_Th get "hexOk")] select _same, [[_u, true] call comspec_atak_native_fnc_unitCallsign] call _esc,
                    ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>MPU5</t>"] select _mpu,
                    ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>ma fréquence</t>"] select _same,
                    ["", "  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>ÉMET</t>"] select _talk,
                    [_fl, "pas de radio ACRE2 publiée"] select (_fl isEqualTo ""), [player distance2D _u] call _dist]];
            } forEach _ord;
            if (_peers isEqualTo []) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Personne d'autre ne publie son réseau voix pour l'instant : normal si vous êtes seul ou si personne n'a de radio ACRE2 et de téléphone ATAK.</t>"]; };
            _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Vous ne voyez émettre que les fréquences réglées sur vos radios, sans tenir compte de la portée. Les joueurs sans téléphone ATAK n'apparaissent pas.</t>"];
            _rows append (["rows"] call comspec_atak_native_fnc_meshGroups);
        } else {
            _rows append (["rows"] call comspec_atak_native_fnc_meshGroups);
        };
    };
    default {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        // Carte d'état : bon, dégradé ou coupé, cause principale, action. Ses chiffres changent ensuite sur place (fn_waveRelayStatus "live").
        private _st = ["read", _q] call comspec_atak_native_fnc_waveRelayStatus;
        uiNamespace setVariable ["COMSPEC_ATAK_WrStatusSig", _st get "sig"];
        uiNamespace setVariable ["COMSPEC_ATAK_WrStatusText", _st get "text"];
        _rows pushBack ["text", _st get "text", COMSPEC_ATAK_Th get "surface", "COMSPEC_ATAK_WrStatusCtl"];
        // Objet obligatoire manquant : ce qu'il faut, et rien d'autre à régler ici.
        if (_denied isNotEqualTo []) exitWith {
            _rows pushBack ["section", "Objets requis par ce serveur", "Le téléphone reste utilisable. Le réseau repart dès que l'objet est équipé."];
            _rows append (["rows"] call comspec_atak_native_fnc_netRequire);
        };
        // Ce qui change le débit, du plus gênant au moins gênant, avant les boutons : la liste n'est plus coupée en bas de page.
        private _factorRows = _st get "rows";
        if (_factorRows isNotEqualTo []) then {
            _rows pushBack ["section", "Ce qui change votre débit", "Du plus gênant au moins gênant"];
            _rows append _factorRows;
        };
        _rows pushBack _refresh;
        _rows pushBack ["section", "Mon nœud", (_nodes select 0) select 2];
        // Passerelle : seul et sans relais, le joueur est sa propre passerelle (chef de son groupe). Rien ne passe alors par lui : le débit est celui de son téléphone.
        private _selfGw = _gw isEqualTo 0;
        _rows append [
            ["info", "Passerelle", switch (true) do {
                case (_gw < 0): { "<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>aucune</t>" };
                case (_selfGw): { format ["vous-même · %1", [_mesh get "gwLabel"] call _esc] };
                case (_myHops < 0): { format ["%1 · <t color='%2'>coupé d'elle</t>", [_mesh get "gwLabel"] call _esc, COMSPEC_ATAK_Th get "hexAlert"] };
                default { format ["%1 · %2 saut(s)", [_mesh get "gwLabel"] call _esc, _myHops] };
            }],
            ["info", "Signal", format ["%1 (%2/4)", _q getOrDefault ["label", "?"], _q getOrDefault ["bars", 0]]]
        ];
        if (_selfGw) then { _rows pushBack ["text", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Pas de relais reçu : vous êtes votre propre passerelle, sans lien vers un relais. Le débit affiché est celui de votre téléphone, après les pertes listées au-dessus.</t>"]; };
        // Radio de données : même source que le facteur « … canal N (câble EUD) » au-dessus (fn_mpu5 "detected"). Liaison réaliste :
        // la radio de fn_mpuLink (« source » y est toujours vide : la ligne disait « sans MPU5 » sous un facteur MPU5, 4.1.0).
        private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
        private _nr = ["range", [player]] call comspec_atak_native_fnc_mpu5;
        private _det = ["detected"] call comspec_atak_native_fnc_mpu5;
        private _muted = { format ["<t size='0.9' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", _this] };
        if (_det isNotEqualTo []) then {
            _det params ["_dn", "_dc", "_dr", "_dl", "_dm"];
            private _lkTxt = switch (_dl) do {
                case "cable": { "câble EUD" };
                case "wifi": { "Wi-Fi" };
                case "porte": { "portée sur vous" };
                case "branche": { "téléphone branché" };
                default { format ["<t color='%1'>non reliée au téléphone</t>", COMSPEC_ATAK_Th get "hexWarn"] };
            };
            _rows pushBack ["info", "Radio", format ["<t color='%1'>%2</t> · portée %3 · %4", COMSPEC_ATAK_Th get "hexOk", [_dn] call _esc, [_dr] call _dist, _lkTxt]];
            if (_dc isNotEqualTo "") then {
                _rows pushBack ["info", "Objet détecté", format ["%1 · %2", [_dc] call _esc, createHashMapFromArray [["acre", "radio ACRE2"], ["tfar", "radio TFAR"], ["mod", "objet d'un autre mod"]] getOrDefault [_dm, "objet COMSPEC"]]];
            };
            if (_real && {_dl isEqualTo ""}) then {
                _rows pushBack ["text", "Radio vue, mais pas reliée au téléphone : menu ACE Téléphone ATAK &gt; Radio de données &gt; Relier le téléphone à la radio (câble)." call _muted];
            };
            if (!_real) then {
                (player getVariable ["COMSPEC_ATAK_Mpu5", []]) params [["_up", 0], ["_uh", 0]];
                _rows pushBack ["info", "Partage avec l'équipe", [format ["%1 Mbit/s partagés%2", _up toFixed 1, ["", format [" (%1 bond(s) MPU5)", _uh]] select (_uh > 0)], "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>rien à partager : aucun relais reçu</t>"] select (_up <= 0)];
            };
        } else {
            _rows pushBack ["info", "Radio", format ["téléphone seul · portée %1 (aucune radio de données sur vous)", [_nr] call _dist]];
        };
        // Message ACRE2 : la radio ACRE2 reconnue, sa portée, et la voix par le réseau (fn_mpuVoice "summary"), dite même désactivée.
        if (_acre) then {
            (["summary"] call comspec_atak_native_fnc_mpuVoice) params ["_vCode", "_vState", "_vHelp"];
            private _vCol = COMSPEC_ATAK_Th get (["hexWarn", "hexOk"] select (_vCode isEqualTo "on"));
            private _aRad = if ((_det param [4, ""]) isEqualTo "acre") then {
                format ["Radio ACRE détectée : %1 · portée %2", [_det select 0] call _esc, [_det select 2] call _dist]
            } else {
                private _nAcre = count (["radios"] call comspec_atak_native_fnc_mpu5);
                ["Radio ACRE détectée : aucune radio de données (MPU5, 117F, 152, 148…) sur vous", format ["Radio ACRE détectée : aucune radio de données parmi vos %1 radio(s) ACRE2", _nAcre]] select (_nAcre > 0)
            };
            _rows pushBack ["section", "ACRE2", "Lu dans vos radios ACRE2, sans jamais les régler"];
            _rows pushBack ["text", format ["%1<br/>Voix par le réseau : <t color='%2'>%3</t>", _aRad, _vCol, [_vState] call _esc]];
            if (_vHelp isNotEqualTo "") then { _rows pushBack ["text", ([_vHelp] call _esc) call _muted]; };
        };
        // Chiffrement de ma liaison : cadenas et libellé de la barre d'état, avec le détail (fn_meshKeys "link").
        (["link"] call comspec_atak_native_fnc_meshKeys) params ["_lockCode", "", "_lockLong", "_lockWhy", "_lockImg", "", "_lockHex"];
        _rows pushBack ["info", "Chiffrement", format ["<img image='%1' color='%2'/> <t color='%2'>%3</t> · <t color='%4'>%5</t>", _lockImg, COMSPEC_ATAK_Th get _lockHex, (_lockLong) call comspec_atak_native_fnc_tr, COMSPEC_ATAK_Th get "hexTextMuted", [(_lockWhy) call comspec_atak_native_fnc_tr] call _esc]];
        if (_lockCode isEqualTo "clear") then {
            _rows pushBack ["text", "« EN CLAIR » (cadenas ouvert, en haut de l'écran) : vos données passent sans chiffrement de jeu. La raison et le remède sont sur la ligne Chiffrement." call _muted];
        };
        // Liaison réaliste (fn_mpuLink) : état de la MPU5, réglages dans l'app Réseau. Sans MPU5 : pas de réseau de données.
        if (_real) then {
            private _ms = ["status"] call comspec_atak_native_fnc_mpuLink;
            private _mv = ["view"] call comspec_atak_native_fnc_mpuLink;
            _rows append (["rows"] call comspec_atak_native_fnc_meshKeys);
            _rows pushBack ["info", "Autonomie estimée · trafic relayé", format ["%1 min · %2 nœud(s)", _mv get "left", (uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["relayed", 0]]];
            _rows pushBack ["info", "Radio de données", format ["%1 · <t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>réglages : app Réseau</t>",
                [format ["<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>%1</t>", [_ms get "why"] call _esc], format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>%1 saut(s) jusqu'à %2</t>", (_ms get "hops") max 0, [_ms get "gw"] call _esc]] select (_ms get "ok")]];
            // Qualité du chemin et cause (4.2.1, fn_meshGraph "view") : ce qui coupe ou limite, et quoi faire.
            private _mm = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
            private _lvl = ((_mm getOrDefault ["level", 3]) max 0) min 3;
            _rows pushBack ["info", "Qualité du chemin", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexOk", "hexWarn", "hexWarn", "hexAlert"] select _lvl),
                (["bonne", "dégradée", "à la limite", "coupée"] select _lvl) call comspec_atak_native_fnc_tr]];
            private _cz = _mm getOrDefault ["cause", ""];
            if (_cz isNotEqualTo "") then {
                _rows pushBack ["info", "Cause", format ["<t color='%1'>%2</t>", COMSPEC_ATAK_Th get (["hexWarn", "hexAlert"] select (_lvl >= 3)), [_cz call comspec_atak_native_fnc_tr] call _esc]];
                private _fx = _mm getOrDefault ["fix", ""];
                if (_fx isNotEqualTo "") then { _rows pushBack ["text", ([_fx call comspec_atak_native_fnc_tr] call _esc) call _muted]; };
            };
        };
        // Téléphone branché sur une radio ACRE2 / TFAR : elle relaie les données comme une MPU5 (réglages du serveur).
        // Pas en liaison réaliste : seule une MPU5 y relie le téléphone.
        if (!_real && {missionNamespace getVariable ["comspec_atak_native_radio_data", true]}) then {
            private _pl = ["plugged"] call comspec_atak_native_fnc_mpu5;
            _rows pushBack ["buttons", [[["BRANCHER SUR MA RADIO", "DÉBRANCHER LA RADIO"] select (_pl isNotEqualTo []), {
                ["plug"] call comspec_atak_native_fnc_mpu5;
                [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            }]]];
        };
        if (_gw > 0) then {
            private _gp = (_nodes select _gw) select 1;
            _rows pushBack ["info", "Distance passerelle", format ["%1 %2", [player distance2D _gp] call _dist, [player getDir _gp] call _card]];
        };
        private _talk = (["mine"] call comspec_atak_native_fnc_meshGroups) apply { ["name", [_x]] call comspec_atak_native_fnc_meshGroups };
        if (_talk isNotEqualTo []) then { _rows pushBack ["info", "Groupes", _talk joinString ", "]; };
        _rows pushBack ["switch", "Liens du maillage sur la carte", profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false], { ["mapLinks"] call comspec_atak_native_fnc_waveRelayAction; }, "Traits colorés selon la qualité du lien"];
        // Voisins directs : liens partant de mon nœud (index 0), les meilleurs d'abord.
        private _nb = [];
        {
            _x params ["_a", "_b", "_lq", ["_lc", 0]];
            if (_a isEqualTo 0) then { _nb pushBack [_lq, _b, _lc]; };
            if (_b isEqualTo 0) then { _nb pushBack [_lq, _a, _lc]; };
        } forEach _links;
        _nb sort false;
        _rows pushBack ["section", format ["Voisins directs (%1)", count _nb], "Téléphones et radios joints sans intermédiaire"];
        {
            _x params ["_lq", "_i", ["_lc", 0]];
            (_nodes select _i) params ["_o", "_p", "_name", "_kind"];
            private _h = _hops select _i;
            // Lien faible (4.2.1) : ce qui le limite (relief, bâtiments, distance…), écrit à côté de son pourcentage.
            private _lcTxt = if (_lq < 45 && {_lc > 0}) then { " · " + format ["limité par %1", ["causeTxt", [_lc]] call comspec_atak_native_fnc_meshGraph] } else { "" };
            _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>  <t color='%1'>%3 %%</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%4 %5 (%6°) · %7%8</t>",
                [_lq] call _qCol, [_name] call _esc, _lq, [player distance2D _p] call _dist, [player getDir _p] call _card, round (player getDir _p),
                switch (true) do { case (_kind isEqualTo "gw" || {_i isEqualTo _gw}): { "passerelle" }; case (_h < 0): { "isolé" }; default { format ["%1 saut(s) de la passerelle", _h] }; }, _lcTxt]];
        } forEach _nb;
        if ((count _nb) isEqualTo 0) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Personne à portée : montez sur un point haut ou rapprochez-vous d'un équipier.</t>"]; };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
