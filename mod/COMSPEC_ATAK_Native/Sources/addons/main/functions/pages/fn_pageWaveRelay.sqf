/*
    App Wave Relay : radio maillée (MANET) entre les téléphones du camp.
    Onglets MON NŒUD (débit, sauts jusqu'à la passerelle, voisins directs), RÉSEAU (tous les nœuds)
    et TALKGROUPS (canaux des radios ACRE2 si chargé, sinon talkgroup data choisi sur le téléphone).
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
private _rows = [["segment", "", [["MON NŒUD", "NODE"] call _tabBtn, [format ["RÉSEAU (%1)", count _nodes], "MESH"] call _tabBtn, ["TALKGROUPS", "TG"] call _tabBtn]]];
private _refresh = ["buttons", [
    ["ACTUALISER", { ["scan", "force"] call comspec_atak_native_fnc_waveRelayAction; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
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
            _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='RobotoCondensedBold'>%2</t>%3  <t size='0.8' color='#8a9a93'>%4 · %5 voisin(s) · %6</t>",
                [["#e5483a", "#5cc76b"] select (_h >= 0), "#7fb6e6"] select (_kind isEqualTo "gw"), [_name] call _esc,
                ["", " <t size='0.8' color='#7fb6e6'>(moi)</t>"] select (_kind isEqualTo "me"),
                switch (true) do { case (_kind isEqualTo "gw" || {_i isEqualTo _gw}): { "passerelle" }; case (_h < 0): { "<t color='#e5483a'>isolé</t>" }; default { format ["%1 saut(s)", _h] }; },
                _deg, [player distance2D _p] call _dist]];
        } forEach _order;
        _rows pushBack _refresh;
    };
    case "TG": {
        if (_acre) then {
            private _radios = [] call acre_api_fnc_getCurrentRadioList;
            _rows pushBack ["section", "Radios ACRE2", "Canal de chaque radio portée, modifiable d'ici"];
            if ((count _radios) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune radio ACRE2 sur vous.</t>"]; };
            private _cur = if (isNil "acre_api_fnc_getCurrentRadio") then { "" } else { [] call acre_api_fnc_getCurrentRadio };
            {
                private _id = _x;
                private _base = [_id] call acre_api_fnc_getBaseRadio;
                private _name = getText (configFile >> "CfgWeapons" >> _base >> "displayName");
                if (_name isEqualTo "") then { _name = _base; };
                private _ch = [_id] call acre_api_fnc_getRadioChannel;
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t font='RobotoCondensedBold'>%1</t>%2<br/><t size='0.85' color='#8a9a93'>Canal %3</t>",
                    [_name] call _esc, ["", "  <t size='0.8' color='#5cc76b'>active</t>"] select (_id isEqualTo _cur), _ch],
                    [
                        ["-", compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _ch - 1], false, _ch > 1],
                        ["+", compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _ch + 1]]
                    ], [0.9, 0.94, 0.91, 1]];
                _rows pushBack ["segment", "", [1, 2, 3, 4, 5, 6] apply { [str _x, compile format ["['channel', '%1', %2] call comspec_atak_native_fnc_waveRelayAction;", _id, _x], _x isEqualTo _ch] }];
            } forEach _radios;
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
                _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.8' color='#8a9a93'>%3</t><br/><t size='0.85' color='#8a9a93'>%4 membre(s)</t>",
                    ["#e6ece8", "#5cc76b"] select _on, _k, _label, _cnt],
                    [[["REJOINDRE", "QUITTER"] select _on, compile format ["['talkgroup', '%1'] call comspec_atak_native_fnc_waveRelayAction;", ["" , _k] select !_on], !_on]], [[0.6, 0.65, 0.62, 1], [0.36, 0.78, 0.42, 1]] select _on];
            } forEach _tgs;
            _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>ACRE2 non chargé : le talkgroup ne sert qu'à regrouper les échanges data du maillage.</t>"];
        };
    };
    default {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        private _kbps = _q getOrDefault ["kbps", 0];
        _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", format ["<t size='1.3' font='RobotoCondensedBold'>%1</t><br/><t color='%2'>%3</t> · %4<br/><t size='0.85' color='#8a9a93'>Latence %5 ms · perte %6 %%</t>",
            [(_nodes select 0) select 2] call _esc,
            [["#e5483a", "#5cc76b"] select (_myHops >= 0), "#7fb6e6"] select (_gw isEqualTo 0),
            switch (true) do { case (_gw isEqualTo 0): { "Je suis la passerelle" }; case (_myHops < 0): { "Coupé de la passerelle" }; default { format ["%1 saut(s) jusqu'à la passerelle", _myHops] }; },
            [format ["%1 kbit/s", _kbps], format ["%1 Mbit/s", (_kbps / 1000) toFixed 1]] select (_kbps >= 1000),
            _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]]];
        _rows append [
            ["info", "Passerelle", [[_mesh get "gwLabel"] call _esc, "<t color='#e5483a'>aucune</t>"] select (_gw < 0)],
            ["info", "Signal", format ["%1 (%2/4)", _q getOrDefault ["label", "?"], _q getOrDefault ["bars", 0]]]
        ];
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
            _rows pushBack ["text", format ["<t color='%1'>●</t> <t font='RobotoCondensedBold'>%2</t>  <t color='%1'>%3 %%</t><br/><t size='0.8' color='#8a9a93'>%4 %5 (%6°) · %7</t>",
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
