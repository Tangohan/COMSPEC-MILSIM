/*
    Graphe du réseau d'un camp, lisible : même mise en page et même dessin partout (mini-graphe de l'app Wave Relay, fenêtre
    « Réseau du camp » de Zeus, écran ou console du TOC). Remplace l'ancienne superposition 3D de « Voir le graphe de ce camp ».
    Rien ne tourne en continu : le modèle et la mise en page sont calculés à l'ouverture, au clic et sur « ACTUALISER », jamais par image.
      ["layout", [sauts par nœud, relais suivant par nœud (-1), noms, plafond de nœuds (80), nœuds par rangée (8)]]
          -> [positions [[x, y]...] entre 0 et 1 ([-1, -1] : hors réseau ou regroupé), rangées [[sauts, y]...], regroupés [[sauts, y, nombre]...]]
          Sans effet, déterministe, sans chevauchement : une case par nœud (rangée = nombre de sauts, les relais en haut ; une rangée
          trop pleine passe à la ligne ; au-delà du plafond, les nœuds les plus lointains sont regroupés en « + N »).
      ["geo", [positions monde [[x, y]...], écart minimal 0-1]] -> positions [[x, y]...] entre 0 et 1, nord en haut, écartées (8 passes).
      ["model", [camp ("" : mon réseau, tel que l'app le voit)]] -> HashMap :
          nodes [[nom, genre ("gw" | "me" | "node" | "mpu"), sauts (-1 : hors réseau), position ASL, état ("" | "dead" | "power" | "jam" | "range" | "off"),
                  raison (texte), objet, relais suivant (-1)]...], links [[a, b, qualité %, cause (code de fn_meshGraph "causeTxt")]...],
          gate [ouvert, code, raison, remède] (fn_relayNet "gate"), side
      ["draw", [display, groupe parent (ou controlNull), [x, y, l, h], modèle, options (HashMap)]] -> contrôles créés
          options : "mode" ("logic" | "geo"), "sel" (index choisi, -1), "filter" ("all" | "relay" | "mpu" | "phone" | "weak" | "out"),
                    "click" (code appelé avec [index] au clic sur un nœud), "fs" (hauteur de police), "compact" (mini-graphe du téléphone)
      ["detail", [modèle, index]] -> [[libellé, valeur, jeton de couleur]...] : sauts, route, maillon faible, cause, voisins
      ["open", [camp]] (Zeus) : fenêtre plein écran ; ["render"] : la redessine ; ["close"]
    API pour le TOC : ["model", [camp]] puis ["draw", [display de l'écran, groupe, rectangle, modèle, options]] à n'importe quel format.
    Indicatifs : comspec_atak_native_fnc_tocDisplayName s'il existe, sinon fn_unitCallsign ; jamais le nom Steam.
*/
params [["_act", ""], ["_arg", []]];
disableSerialization;
private _dataDir = "\z\comspec_atak_native\addons\main\data\";
private _qTok = { params ["_q"]; ["alert", "warn", "ok"] select (([0, 1] select (_q >= 30)) + ([0, 1] select (_q >= 60))) };
switch (_act) do {
    case "layout": {
        _arg params [["_hops", []], ["_via", []], ["_names", []], ["_cap", 80], ["_perRow", 8]];
        private _n = count _hops;
        private _pos = _hops apply { [-1, -1] };
        // Ordre stable : sauts, puis rang du relais suivant, puis nom, puis index.
        private _order = [];
        { if (_x >= 0) then { _order pushBack [_x, _forEachIndex]; }; } forEach _hops;
        _order sort true;
        private _kept = _order select [0, _cap];
        private _dropped = _order select [_cap];
        private _maxHop = 0;
        { _maxHop = _maxHop max (_x select 0); } forEach _kept;
        private _rows = [];
        private _clusters = [];
        private _rowIdx = 0;
        private _slot = createHashMap;
        private _placed = [];
        for "_h" from 0 to _maxHop do {
            private _in = [];
            {
                _x params ["_hh", "_i"];
                if (_hh isEqualTo _h) then { _in pushBack [_slot getOrDefault [_via param [_i, -1], 999], _names param [_i, ""], _i]; };
            } forEach _kept;
            if (_in isEqualTo []) then { continue };
            _in sort true;
            private _extra = { (_x select 0) isEqualTo _h } count _dropped;
            private _total = (count _in) + ([0, 1] select (_extra > 0));
            private _lines = ceil (_total / _perRow);
            for "_ln" from 0 to (_lines - 1) do {
                private _chunk = _in select [_ln * _perRow, _perRow];
                private _cnt = (count _chunk) + ([0, 1] select (_extra > 0 && {_ln isEqualTo (_lines - 1)}));
                {
                    private _i = _x select 2;
                    _slot set [_i, _forEachIndex + _ln * _perRow];
                    _placed pushBack [_i, _rowIdx, (_forEachIndex + 0.5) / (_cnt max 1)];
                } forEach _chunk;
                if (_ln isEqualTo 0) then { _rows pushBack [_h, _rowIdx]; };
                if (_extra > 0 && {_ln isEqualTo (_lines - 1)}) then { _clusters pushBack [_h, _rowIdx, _extra, (_cnt - 0.5) / _cnt]; };
                _rowIdx = _rowIdx + 1;
            };
        };
        private _nRows = _rowIdx max 1;
        { _x params ["_i", "_r", "_fx"]; _pos set [_i, [_fx, (_r + 0.5) / _nRows]]; } forEach _placed;
        [_pos, _rows apply { [_x select 0, ((_x select 1) + 0.5) / _nRows] }, _clusters apply { [_x select 0, ((_x select 1) + 0.5) / _nRows, _x select 2, _x select 3] }, _n]
    };
    case "geo": {
        _arg params [["_world", []], ["_gap", 0.08]];
        if (_world isEqualTo []) exitWith { [] };
        private _xs = _world apply { _x select 0 };
        private _ys = _world apply { _x select 1 };
        private _x0 = selectMin _xs;
        private _y0 = selectMin _ys;
        private _span = ((selectMax _xs) - _x0) max ((selectMax _ys) - _y0) max 1;
        private _cx = (_x0 + (selectMax _xs)) / 2;
        private _cy = (_y0 + (selectMax _ys)) / 2;
        private _out = _world apply { [0.5 + ((_x select 0) - _cx) / _span * 0.84, 0.5 - ((_x select 1) - _cy) / _span * 0.84] };
        private _n = count _out;
        for "_pass" from 1 to 8 do {
            for "_i" from 0 to (_n - 2) do {
                for "_j" from (_i + 1) to (_n - 1) do {
                    (_out select _i) params ["_ax", "_ay"];
                    (_out select _j) params ["_bx", "_by"];
                    private _dx = _bx - _ax;
                    private _dy = _by - _ay;
                    private _dd = sqrt (_dx * _dx + _dy * _dy);
                    if (_dd < _gap) then {
                        // Même point : écart fixe, d'après les index (déterministe).
                        if (_dd < 0.0001) then { _dx = 0.001 * ((_j - _i) max 1); _dy = 0.001; _dd = sqrt (_dx * _dx + _dy * _dy); };
                        private _push = (_gap - _dd) / 2;
                        _out set [_i, [((_ax - _dx / _dd * _push) max 0.04) min 0.96, ((_ay - _dy / _dd * _push) max 0.04) min 0.96]];
                        _out set [_j, [((_bx + _dx / _dd * _push) max 0.04) min 0.96, ((_by + _dy / _dd * _push) max 0.04) min 0.96]];
                    };
                };
            };
        };
        _out
    };
    case "model": {
        _arg params [["_side", ""]];
        private _nameOf = {
            params ["_u"];
            private _fn = missionNamespace getVariable ["comspec_atak_native_fnc_tocDisplayName", {}];
            if (_fn isNotEqualTo {}) exitWith { [_u] call _fn };
            [_u, true] call comspec_atak_native_fnc_unitCallsign
        };
        private _nodes = [];
        private _links = [];
        private _mine = _side isEqualTo "";
        private _sideStr = [_side, str side group player] select _mine;
        if (_mine) then {
            private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
            private _hops = _mesh get "hops";
            {
                _x params ["_o", "_p", "_name", "_kind"];
                private _h = _hops param [_forEachIndex, -1];
                _nodes pushBack [_name, _kind, _h, _p, ["", "range"] select (_h < 0), ["", "aucun chemin vers un relais en service"] select (_h < 0), _o, -1];
            } forEach (_mesh get "nodes");
            { _x params ["_a", "_b", "_q", ["_c", 0]]; _links pushBack [_a, _b, _q, _c]; } forEach (_mesh get "links");
        } else {
            private _dec = ["graph", [_side]] call comspec_atak_native_fnc_meshGraph;
            if (_dec isNotEqualTo []) then {
                _dec params ["_gn", "", "_gl"];
                private _relays = ["list", [_side]] call comspec_atak_native_fnc_relayNet;
                {
                    _x params ["_o", "_owner", "_type", "_chan"];
                    private _name = switch (_type) do {
                        case 2: { private _e = _relays findIf { (_x select 0) isEqualTo _o }; if (_e >= 0) then { (_relays select _e) select 2 } else { "Relais ATAK" } };
                        case 1: { format ["MPU5 posée (%1)", [_owner] call _nameOf] };
                        default { [_owner] call _nameOf };
                    };
                    _nodes pushBack [_name, ["node", "mpu", "gw"] select _type, [-1, 0] select (_type isEqualTo 2), getPosASL _o, "", format ["canal %1", _chan], [_owner, _o] select (_type isEqualTo 2), -1];
                } forEach _gn;
                { _x params ["_a", "_b", "_m", "", ["_c", 0]]; _links pushBack [_a, _b, ((round (100 * ((_m / 20) min 1))) max 1) min 100, _c]; } forEach _gl;
            };
        };
        // Sauts et relais suivant : parcours en largeur depuis les relais, par le meilleur lien (un seul passage, à la demande).
        private _adj = _nodes apply { [] };
        { _x params ["_a", "_b", "_q"]; if (_a < (count _nodes) && {_b < (count _nodes)}) then { (_adj select _a) pushBack [_q, _b]; (_adj select _b) pushBack [_q, _a]; }; } forEach _links;
        private _queue = [];
        { if ((_x select 1) isEqualTo "gw" || {(_x select 2) isEqualTo 0}) then { _x set [2, 0]; _queue pushBack _forEachIndex; } else { if (!_mine) then { _x set [2, -1]; }; }; } forEach _nodes;
        if (!_mine) then {
            while { _queue isNotEqualTo [] } do {
                private _c = _queue deleteAt 0;
                { private _j = _x select 1; if (((_nodes select _j) select 2) < 0) then { (_nodes select _j) set [2, ((_nodes select _c) select 2) + 1]; _queue pushBack _j; }; } forEach (_adj select _c);
            };
        };
        {
            private _h = _x select 2;
            if (_h > 0) then {
                private _i = _forEachIndex;
                private _best = [-1, -1];
                { _x params ["_q", "_j"]; if (((_nodes select _j) select 2) isEqualTo (_h - 1) && {_q > (_best select 0)}) then { _best = [_q, _j]; }; } forEach (_adj select _i);
                _x set [7, _best select 1];
            };
        } forEach _nodes;
        // Relais hors service du camp : hors réseau, avec la raison exacte (détruit, éteint, batterie vide, brouillé, sans liaison).
        {
            _x params ["_o", "_ap", "_name", "_kind", "", "", "_ok", "_st"];
            if (!_ok && {_kind isNotEqualTo "mpu5"} && {(_nodes findIf { (_x select 6) isEqualTo _o }) < 0}) then {
                _nodes pushBack [_name, "gw", -1, _ap, switch (true) do { case ((_st find "détruit") >= 0): { "dead" }; case ((_st find "brouill") >= 0): { "jam" }; case ((_st find "batterie") >= 0 || {(_st find "éteint") >= 0}): { "power" }; default { "off" }; }, _st, _o, -1];
            };
        } forEach (if (_mine) then { ["list"] call comspec_atak_native_fnc_relayNet } else { ["list", [_side]] call comspec_atak_native_fnc_relayNet });
        createHashMapFromArray [["nodes", _nodes], ["links", _links], ["side", _sideStr], ["gate", ["gate", [_sideStr]] call comspec_atak_native_fnc_relayNet], ["at", diag_tickTime]]
    };
    case "detail": {
        _arg params [["_model", createHashMap], ["_i", -1]];
        private _nodes = _model getOrDefault ["nodes", []];
        if (_i < 0 || {_i >= (count _nodes)}) exitWith { [] };
        (_nodes select _i) params ["_name", "_kind", "_h", "", "_st", "_why", "", "_via"];
        private _out = [["Type", createHashMapFromArray [["gw", "relais ou passerelle"], ["me", "mon téléphone"], ["mpu", "radio MPU5 posée"]] getOrDefault [_kind, "téléphone d'un équipier"], "text"]];
        if (_h < 0) exitWith { _out + [["État", "hors réseau", "alert"], ["Raison", [_why, "aucun chemin vers un relais en service"] select (_why isEqualTo ""), "warn"]] };
        _out pushBack ["Sauts jusqu'au relais", str _h, "text"];
        // Route et maillon faible : en suivant le relais suivant, 16 pas au plus.
        private _links = _model getOrDefault ["links", []];
        private _qOf = { params ["_a", "_b"]; private _k = _links findIf { ((_x select 0) isEqualTo _a && {(_x select 1) isEqualTo _b}) || {(_x select 0) isEqualTo _b && {(_x select 1) isEqualTo _a}} }; if (_k < 0) then { [100, 0] } else { [(_links select _k) select 2, (_links select _k) select 3] } };
        private _route = [_name];
        private _weak = [101, "", 0];
        private _cur = _i;
        private _nx = _via;
        private _guard = 0;
        while { _nx >= 0 && {_guard < 16} } do {
            ([_cur, _nx] call _qOf) params ["_q", "_c"];
            if (_q < (_weak select 0)) then { _weak = [_q, format ["%1 > %2", (_nodes select _cur) select 0, (_nodes select _nx) select 0], _c]; };
            _route pushBack ((_nodes select _nx) select 0);
            _cur = _nx;
            _nx = (_nodes select _cur) select 7;
            _guard = _guard + 1;
        };
        if ((count _route) > 1) then { _out pushBack ["Route", _route joinString " > ", "text"]; };
        if ((_weak select 1) isNotEqualTo "") then {
            _out pushBack ["Maillon faible", format ["%1 · %2 %%", _weak select 1, _weak select 0], [_weak select 0] call _qTok];
            if ((_weak select 2) > 0 && {(_weak select 0) < 60}) then {
                _out pushBack ["Ce qui le limite", ["causeTxt", [_weak select 2]] call comspec_atak_native_fnc_meshGraph, "warn"];
                _out pushBack ["Que faire", ["causeFix", [_weak select 2, 0]] call comspec_atak_native_fnc_meshGraph, "textMuted"];
            };
        };
        private _nb = [];
        { _x params ["_a", "_b", "_q"]; if (_a isEqualTo _i) then { _nb pushBack [_q, _b]; }; if (_b isEqualTo _i) then { _nb pushBack [_q, _a]; }; } forEach _links;
        _nb sort false;
        { _out pushBack [["Voisin", format ["Voisins (%1)", count _nb]] select (_forEachIndex isEqualTo 0), format ["%1 · %2 %%", (_nodes select (_x select 1)) select 0, _x select 0], [_x select 0] call _qTok]; } forEach (_nb select [0, 6]);
        _out
    };
    case "draw": {
        _arg params [["_d", displayNull], ["_grp", controlNull], ["_rect", [0, 0, 1, 1]], ["_model", createHashMap], ["_opt", createHashMap]];
        if (isNull _d) exitWith { [] };
        _rect params ["_rx", "_ry", "_rw", "_rh"];
        private _th = [] call comspec_atak_native_fnc_theme;
        private _mode = _opt getOrDefault ["mode", "logic"];
        private _sel = _opt getOrDefault ["sel", -1];
        private _filter = _opt getOrDefault ["filter", "all"];
        private _click = _opt getOrDefault ["click", {}];
        private _compact = _opt getOrDefault ["compact", false];
        private _fs = _opt getOrDefault ["fs", 0.019 * safeZoneH];
        private _made = [];
        private _mk = {
            params ["_class", "_p", ["_text", ""]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_class, -1] } else { _d ctrlCreate [_class, -1, _grp] };
            [_c, _class] call comspec_atak_native_fnc_fontApply;
            _c ctrlSetPosition _p;
            if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        private _stx = {
            params ["_p", "_txt"];
            private _c = ["COMSPEC_RscStructuredText", _p] call _mk;
            _c ctrlSetStructuredText parseText (_txt call comspec_atak_native_fnc_tr);
            _c
        };
        private _hex = { _th get _this };
        private _bg = ["COMSPEC_RscText", _rect] call _mk;
        _bg ctrlSetBackgroundColor (_th get "surfaceAlt");
        private _nodes = _model getOrDefault ["nodes", []];
        private _links = _model getOrDefault ["links", []];
        (_model getOrDefault ["gate", [true, "", "", ""]]) params ["_gOpen", "", "_gWhy", "_gFix"];
        private _isoIdx = [];
        { if ((_x select 2) < 0) then { _isoIdx pushBack _forEachIndex; }; } forEach _nodes;
        private _inNet = (count _nodes) - (count _isoIdx);
        // Bandeau « hors réseau » en bas : nom et raison, 3 en mini-graphe, 6 sinon.
        private _isoMax = [6, 3] select _compact;
        private _isoH = if (_isoIdx isEqualTo []) then { 0 } else { _fs * ([3.4, 2.5] select _compact) };
        private _gh = _rh - _isoH;
        // Règle « pas de relais, pas de réseau » : écrite en clair, à la place d'un graphe vide.
        if (!_gOpen || {_inNet <= ([0, 1] select _compact)}) then {
            [[_rx + _rw * 0.06, _ry + _gh * 0.28, _rw * 0.88, _gh * 0.6], format ["<t align='center' font='COMSPEC_BarlowBold' color='%1'>%2</t><br/><t align='center' size='0.85' color='%3'>%4</t>",
                ([("hexWarn" call _hex), ("hexAlert" call _hex)] select !_gOpen), ([("Aucun voisin joint pour l'instant" call comspec_atak_native_fnc_tr), ("Aucun relais en service : réseau indisponible" call comspec_atak_native_fnc_tr)] select !_gOpen),
                "hexTextMuted" call _hex, ([_gFix, "Montez sur un point haut ou rapprochez-vous d'un relais."] select (_gOpen || {_gFix isEqualTo ""})) call comspec_atak_native_fnc_tr]] call _stx;
        };
        // Mise en page : une fois par dessin (ouverture, clic, ACTUALISER), jamais par image.
        private _pos = [];
        private _rows = [];
        private _clusters = [];
        if (_mode isEqualTo "geo") then {
            private _keep = [];
            { if ((_x select 2) >= 0) then { _keep pushBack _forEachIndex; }; } forEach _nodes;
            private _g = ["geo", [_keep apply { ((_nodes select _x) select 3) select [0, 2] }, [0.07, 0.16] select _compact]] call comspec_atak_native_fnc_meshGraphView;
            _pos = _nodes apply { [-1, -1] };
            { _pos set [_x, _g select _forEachIndex]; } forEach _keep;
        } else {
            (["layout", [_nodes apply { _x select 2 }, _nodes apply { _x select 7 }, _nodes apply { _x select 0 }, [80, 14] select _compact, [8, 5] select _compact]] call comspec_atak_native_fnc_meshGraphView) params ["_p0", "_r0", "_c0"];
            _pos = _p0; _rows = _r0; _clusters = _c0;
        };
        private _padX = _rw * 0.07;
        private _padT = _fs * ([1.5, 0.9] select _compact);
        private _padB = _fs * 1.6;
        private _toScr = { params ["_p"]; [_rx + _padX + (_p select 0) * (_rw - 2 * _padX), _ry + _padT + (_p select 1) * (_gh - _padT - _padB)] };
        // Rangées : filet et libellé (schéma seulement).
        if (!_compact) then {
            {
                _x params ["_h", "_yy"];
                private _sy = ([[0, _yy]] call _toScr) select 1;
                private _ln = ["COMSPEC_RscText", [_rx + _fs * 0.4, _sy, _rw - _fs * 0.8, pixelH]] call _mk;
                _ln ctrlSetBackgroundColor [1, 1, 1, 0.06];
                [[_rx + _fs * 0.4, _sy - _fs * 1.25, _rw * 0.3, _fs * 1.2], format ["<t size='0.8' color='%1'>%2</t>", "hexTextMuted" call _hex, ([format ["%1 saut(s)", _h], "Relais"] select (_h isEqualTo 0))]] call _stx;
            } forEach _rows;
        };
        // Liens : pointillés de contrôles (aucune primitive de trait dans un groupe), couleur par qualité, lien faible espacé.
        private _budget = ((floor (900 / ((count _links) max 1))) max 4) min ([26, 14] select _compact);
        private _dot = [3, 2] select _compact;
        private _dim = { params ["_i"]; private _k = (_nodes select _i) select 1; switch (_filter) do { case "relay": { _k isNotEqualTo "gw" }; case "mpu": { _k isNotEqualTo "mpu" }; case "phone": { !(_k in ["node", "me"]) }; case "out": { true }; default { false }; } };
        {
            _x params ["_a", "_b", "_q"];
            private _pa = _pos param [_a, [-1, -1]];
            private _pb = _pos param [_b, [-1, -1]];
            if ((_pa select 0) < 0 || {(_pb select 0) < 0}) then { continue };
            ([_pa] call _toScr) params ["_ax", "_ay"];
            ([_pb] call _toScr) params ["_bx", "_by"];
            private _len = sqrt ((((_bx - _ax) / pixelW) ^ 2) + (((_by - _ay) / pixelH) ^ 2));
            private _n = ((round (_len / ([9, 7] select _compact))) max 3) min _budget;
            private _col = +(_th get ([_q] call _qTok));
            private _big = _sel in [_a, _b];
            if ((_filter isEqualTo "weak" && {_q >= 60}) || {(_sel >= 0 && {!_big})} || {[_a] call _dim && {[_b] call _dim}}) then { _col set [3, 0.28]; };
            private _s = _dot + ([0, 1] select _big);
            for "_k" from 1 to (_n - 1) do {
                if (_q < 30 && {(_k mod 2) isEqualTo 1}) then { continue };
                private _c = ["COMSPEC_RscText", [_ax + (_bx - _ax) * _k / _n - _s * pixelW / 2, _ay + (_by - _ay) * _k / _n - _s * pixelH / 2, _s * pixelW, _s * pixelH]] call _mk;
                _c ctrlSetBackgroundColor _col;
            };
            // Pourcentage : seulement sur les liens du nœud choisi.
            if (_big) then {
                [[(_ax + _bx) / 2 + _fs * 0.3, (_ay + _by) / 2 - _fs * 0.6, _fs * 4, _fs * 1.2], format ["<t size='0.8' font='COMSPEC_BarlowBold' color='%1'>%2 %%</t>", ("hex" + (["Alert", "Warn", "Ok"] select (([0, 1] select (_q >= 30)) + ([0, 1] select (_q >= 60))))) call _hex, _q]] call _stx;
            };
        } forEach _links;
        // Nœuds : relais en grand (triangle), radio posée (carré), téléphone (disque) ; indicatif dessous ; clic = fiche.
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _slotW = (_rw - 2 * _padX) / ([8, 5] select _compact);
        {
            private _p = _pos param [_forEachIndex, [-1, -1]];
            if ((_p select 0) < 0) then { continue };
            _x params ["_name", "_kind", "_h"];
            ([_p] call _toScr) params ["_nx", "_ny"];
            private _sz = _fs * ([[1.15, 1.7] select (_kind isEqualTo "gw"), [0.95, 1.3] select (_kind isEqualTo "gw")] select _compact);
            private _w = _sz / _ratio;
            private _i = _forEachIndex;
            private _col = +(_th get (switch (_kind) do { case "gw": { "ok" }; case "me": { "accent" }; default { "info" }; }));
            if ([_i] call _dim) then { _col set [3, 0.3]; };
            if (_i isEqualTo _sel) then {
                private _ring = ["COMSPEC_RscPicture", [_nx - _w * 0.85, _ny - _sz * 0.85, _w * 1.7, _sz * 1.7], _dataDir + "wr_node_ring.paa"] call _mk;
                _ring ctrlSetTextColor (_th get "text");
            };
            private _pic = ["COMSPEC_RscPicture", [_nx - _w / 2, _ny - _sz / 2, _w, _sz], _dataDir + (switch (_kind) do { case "gw": { "wr_node_relay.paa" }; case "mpu": { "wr_node_mpu.paa" }; default { "wr_node_phone.paa" }; })] call _mk;
            _pic ctrlSetTextColor _col;
            private _lab = [_name, (_name select [0, 13]) + "…"] select ((count _name) > 14);
            [[_nx - _slotW / 2, _ny + _sz * 0.55, _slotW, _fs * 1.1], format ["<t align='center' size='%1' color='%2'%3>%4</t>", [0.78, 0.7] select _compact, [("hexTextDim" call _hex), ("hexText" call _hex)] select (_kind in ["gw", "me"] || {_i isEqualTo _sel}),
                ["", " font='COMSPEC_BarlowBold'"] select (_kind in ["gw", "me"]), [_lab, "Moi"] select (_kind isEqualTo "me")]] call _stx;
            private _b = ["COMSPEC_RscButtonOverlay", [_nx - _slotW * 0.4, _ny - _sz * 0.7, _slotW * 0.8, _sz * 1.4 + _fs]] call _mk;
            _b ctrlSetTooltip (format ["%1 · %2", _name, [format [("%1 saut(s)" call comspec_atak_native_fnc_tr), _h], ("relais" call comspec_atak_native_fnc_tr)] select (_h isEqualTo 0)]);
            _b setVariable ["wr_i", _i];
            _b setVariable ["wr_code", _click];
            // Clic différé d'une image : le rappel redessine souvent le graphe, donc supprime ce bouton (même règle que fn_formRender).
            _b ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                [{ params ["_i", "_code", "_done"]; if (_done select 0) exitWith {}; _done set [0, true]; [_i] call _code; }, [_c getVariable ["wr_i", -1], _c getVariable ["wr_code", {}], [false]]] call comspec_atak_native_fnc_nextFrame;
            }];
        } forEach _nodes;
        {
            _x params ["", "_yy", "_cnt", "_fx"];
            ([[_fx, _yy]] call _toScr) params ["_cx", "_cy"];
            [[_cx - _slotW / 2, _cy - _fs * 0.6, _slotW, _fs * 1.2], format ["<t align='center' font='COMSPEC_BarlowBold' color='%1'>+ %2</t>", "hexTextDim" call _hex, _cnt]] call _stx;
        } forEach _clusters;
        // Hors réseau : à part, avec la raison.
        if (_isoIdx isNotEqualTo []) then {
            private _iy = _ry + _gh;
            private _strip = ["COMSPEC_RscText", [_rx, _iy, _rw, _isoH]] call _mk;
            _strip ctrlSetBackgroundColor (_th get "surface");
            private _shown = _isoIdx select [0, _isoMax];
            private _txt = (_shown apply {
                (_nodes select _x) params ["_n", "", "", "", "_st", "_why"];
                format ["<t color='%1'>%2</t> <t size='0.85' color='%3'>%4</t>", "hexText" call _hex, _n, [("hexWarn" call _hex), ("hexAlert" call _hex)] select (_st in ["dead", "jam"]), [_why, "hors de portée"] select (_why isEqualTo "")]
            }) joinString "   ·   ";
            [[_rx + _fs * 0.5, _iy + _fs * 0.2, _rw - _fs, _isoH - _fs * 0.3], format ["<t size='0.85' font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t size='0.85'>%3%4</t>", "hexAlert" call _hex, format [("Hors réseau (%1)" call comspec_atak_native_fnc_tr), count _isoIdx], _txt,
                ["", format [" … + %1", (count _isoIdx) - _isoMax]] select ((count _isoIdx) > _isoMax)]] call _stx;
        };
        _made
    };
    case "open": {
        // Fenêtre « Réseau du camp » (Zeus) : plein écran, posée sur l'écran Zeus ; fermée par FERMER ou Échap.
        private _parent = findDisplay 312;
        if (isNull _parent) then { _parent = findDisplay 46; };
        if (isNull _parent) exitWith {};
        private _d = _parent createDisplay "RscDisplayEmpty";
        uiNamespace setVariable ["COMSPEC_ATAK_GraphUi", createHashMapFromArray [["display", _d], ["side", _arg param [0, str side group player]], ["mode", "logic"], ["zoom", 1], ["filter", "all"], ["sel", -1], ["ctrls", []], ["query", ""]]];
        ["render"] call comspec_atak_native_fnc_meshGraphView;
    };
    case "close": {
        private _ui = uiNamespace getVariable ["COMSPEC_ATAK_GraphUi", createHashMap];
        private _d = _ui getOrDefault ["display", displayNull];
        if (!isNull _d) then { _d closeDisplay 2; };
        uiNamespace setVariable ["COMSPEC_ATAK_GraphUi", createHashMap];
    };
    case "set": {
        // ["set", [clé, valeur]] : change un choix de la fenêtre (camp, vue, zoom, filtre, nœud choisi) et la redessine.
        private _ui = uiNamespace getVariable ["COMSPEC_ATAK_GraphUi", createHashMap];
        _arg params [["_k", ""], "_v"];
        if (_k isEqualTo "zoom") then { _v = (((_ui getOrDefault ["zoom", 1]) + _v) max 1) min 3; };
        if (_k isEqualTo "side") then { _ui set ["sel", -1]; };
        if (_k isEqualTo "query") then {
            // Recherche par indicatif : choisit le premier nœud dont le nom contient le texte.
            private _q = toLower (ctrlText (_ui getOrDefault ["edit", controlNull]));
            _v = _q;
            private _i = if (_q isEqualTo "") then { -1 } else { ((_ui getOrDefault ["model", createHashMap]) getOrDefault ["nodes", []]) findIf { ((toLower (_x select 0)) find _q) >= 0 } };
            _ui set ["sel", _i];
        };
        _ui set [_k, _v];
        ["render", [_k in ["sel", "filter", "zoom", "mode", "query"]]] call comspec_atak_native_fnc_meshGraphView;
    };
    case "render": {
        private _ui = uiNamespace getVariable ["COMSPEC_ATAK_GraphUi", createHashMap];
        private _d = _ui getOrDefault ["display", displayNull];
        if (isNull _d) exitWith {};
        { ctrlDelete _x; } forEach (_ui getOrDefault ["ctrls", []]);
        private _th = [] call comspec_atak_native_fnc_theme;
        private _T = { _this call comspec_atak_native_fnc_tr };
        // Modèle gardé quand seul l'affichage change (clic sur un nœud, filtre, zoom) : relu à l'ouverture, au changement de camp et sur ACTUALISER.
        private _model = if ((_arg param [0, false]) && {(count (_ui getOrDefault ["model", createHashMap])) > 0}) then { _ui get "model" } else { ["model", [_ui get "side"]] call comspec_atak_native_fnc_meshGraphView };
        _ui set ["model", _model];
        private _made = [];
        private _fs = 0.019 * safeZoneH;
        private _x0 = safeZoneX;
        private _y0 = safeZoneY;
        private _w = safeZoneW;
        private _h = safeZoneH;
        private _mk = {
            params ["_class", "_p", ["_text", ""], ["_grp", controlNull]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_class, -1] } else { _d ctrlCreate [_class, -1, _grp] };
            [_c, _class] call comspec_atak_native_fnc_fontApply;
            _c ctrlSetPosition _p;
            if (_text isNotEqualTo "") then { _c ctrlSetText (_text call _T); };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        private _btn = {
            params ["_p", "_label", "_code", ["_on", false]];
            private _face = ["COMSPEC_RscText", _p] call _mk;
            _face ctrlSetBackgroundColor ([_th get "surface2", _th get "accent"] select _on);
            private _b = ["COMSPEC_RscButtonFlat", _p, _label] call _mk;
            _b ctrlSetFontHeight (_fs * 0.95);
            _b ctrlSetTextColor ([_th get "text", _th get "onAccent"] select _on);
            _b setVariable ["wr_code", _code];
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ params ["_code", "_done"]; if (_done select 0) exitWith {}; _done set [0, true]; call _code; }, [_c getVariable ["wr_code", {}], [false]]] call comspec_atak_native_fnc_nextFrame; }];
            _b
        };
        private _bgAll = ["COMSPEC_RscText", [_x0, _y0, _w, _h]] call _mk;
        _bgAll ctrlSetBackgroundColor (_th get "bg1");
        private _barH = _fs * 2.6;
        private _bar = ["COMSPEC_RscText", [_x0, _y0, _w, _barH]] call _mk;
        _bar ctrlSetBackgroundColor (_th get "bg0");
        private _title = ["COMSPEC_RscStructuredText", [_x0 + _fs, _y0 + _fs * 0.55, _fs * 9, _fs * 1.6]] call _mk;
        _title ctrlSetStructuredText parseText (format ["<t size='1.25' font='COMSPEC_BarlowBold'>%1</t>", "Réseau du camp" call _T]);
        private _bw = _fs * 4.4;
        private _bh = _fs * 1.5;
        private _by = _y0 + (_barH - _bh) / 2;
        private _bx = _x0 + _fs * 11;
        {
            _x params ["_k", "_lbl", "_key", "_val"];
            [[_bx, _by, _bw, _bh], _lbl, compile format ["['set', ['%1', '%2']] call comspec_atak_native_fnc_meshGraphView;", _key, _val], (_ui get _key) isEqualTo _val] call _btn;
            _bx = _bx + _bw + _fs * ([0.2, 1.2] select _k);
        } forEach [[0, "BLUFOR", "side", "WEST"], [0, "OPFOR", "side", "EAST"], [1, "INDÉP.", "side", "GUER"], [0, "SCHÉMA", "mode", "logic"], [1, "CARTE", "mode", "geo"]];
        [[_bx, _by, _bh, _bh], "-", { ["set", ["zoom", -0.5]] call comspec_atak_native_fnc_meshGraphView; }] call _btn;
        [[_bx + _bh * 1.1, _by, _bh, _bh], "+", { ["set", ["zoom", 0.5]] call comspec_atak_native_fnc_meshGraphView; }] call _btn;
        _bx = _bx + _bh * 2.2 + _fs;
        private _ed = ["COMSPEC_RscEdit", [_bx, _by, _fs * 9, _bh]] call _mk;
        _ed ctrlSetText (_ui getOrDefault ["query", ""]);
        _ed ctrlSetTooltip ("Indicatif à chercher, puis CHERCHER" call _T);
        _ui set ["edit", _ed];
        [[_bx + _fs * 9.2, _by, _bw, _bh], "CHERCHER", { ["set", ["query", ""]] call comspec_atak_native_fnc_meshGraphView; }] call _btn;
        [[_x0 + _w - _bw * 2 - _fs * 1.2, _by, _bw, _bh], "ACTUALISER", { ["render"] call comspec_atak_native_fnc_meshGraphView; }] call _btn;
        [[_x0 + _w - _bw - _fs, _by, _bw, _bh], "FERMER", { ["close"] call comspec_atak_native_fnc_meshGraphView; }] call _btn;
        // Colonne de gauche : filtres et légende.
        private _lw = _fs * 10;
        private _ly = _y0 + _barH + _fs * 0.6;
        {
            _x params ["_k", "_lbl"];
            [[_x0 + _fs * 0.6, _ly, _lw - _fs * 1.2, _bh], _lbl, compile format ["['set', ['filter', '%1']] call comspec_atak_native_fnc_meshGraphView;", _k], (_ui get "filter") isEqualTo _k] call _btn;
            _ly = _ly + _bh * 1.12;
        } forEach [["all", "TOUT"], ["relay", "RELAIS ET PYLÔNES"], ["mpu", "RADIOS POSÉES"], ["phone", "TÉLÉPHONES"], ["weak", "LIENS FAIBLES"], ["out", "HORS RÉSEAU"]];
        private _nodes = _model get "nodes";
        private _hx = { _th get _this };
        private _leg = ["COMSPEC_RscStructuredText", [_x0 + _fs * 0.6, _ly + _fs * 0.5, _lw - _fs * 1.2, _fs * 12]] call _mk;
        _leg ctrlSetStructuredText parseText (format ["<t size='0.85'><t color='%1'>● ● ●</t> %4<br/><t color='%2'>● ● ●</t> %5<br/><t color='%3'>●   ●</t> %6<br/><img image='%7' color='%1'/> %8<br/><img image='%9' color='%10'/> %11<br/><img image='%12' color='%10'/> %13<br/><br/><t color='%14'>%15<br/>%16</t></t>",
            "hexOk" call _hx, "hexWarn" call _hx, "hexAlert" call _hx, "lien bon (60 % et plus)" call _T, "lien moyen" call _T, "lien faible (espacé)" call _T,
            _dataDir + "wr_node_relay.paa", "relais, pylône, passerelle" call _T, _dataDir + "wr_node_mpu.paa", "hexInfo" call _hx, "radio MPU5 posée" call _T, _dataDir + "wr_node_phone.paa", "téléphone" call _T,
            "hexTextMuted" call _hx, format ["%1 nœud(s) · %2 lien(s)" call _T, count _nodes, count (_model get "links")],
            format ["%1 relais en service sur %2" call _T, { (_x select 1) isEqualTo "gw" && {(_x select 2) isEqualTo 0} } count _nodes, { (_x select 1) isEqualTo "gw" } count _nodes]]);
        // Graphe : dans un groupe qui défile (le zoom agrandit le dessin, les barres de défilement déplacent la vue).
        private _dw = _fs * 17;
        private _gx = _x0 + _lw;
        private _gy = _y0 + _barH;
        private _gw = _w - _lw - _dw;
        private _gh = _h - _barH;
        private _grp = ["COMSPEC_RscControlsGroup", [_gx, _gy, _gw, _gh]] call _mk;
        private _z = _ui getOrDefault ["zoom", 1];
        private _opt = createHashMapFromArray [["mode", _ui get "mode"], ["sel", _ui get "sel"], ["filter", _ui get "filter"], ["fs", _fs],
            ["click", { ["set", ["sel", _this select 0]] call comspec_atak_native_fnc_meshGraphView; }]];
        _made append (["draw", [_d, _grp, [0, 0, (_gw - 0.02) * _z, (_gh - 0.02) * _z], _model, _opt]] call comspec_atak_native_fnc_meshGraphView);
        // Panneau de détail du nœud choisi.
        private _px = _x0 + _w - _dw;
        private _pan = ["COMSPEC_RscText", [_px, _gy, _dw, _gh]] call _mk;
        _pan ctrlSetBackgroundColor (_th get "surface");
        private _sel = _ui get "sel";
        private _det = ["detail", [_model, _sel]] call comspec_atak_native_fnc_meshGraphView;
        private _txt = if (_det isEqualTo []) then {
            format ["<t color='%1'>%2</t>", "hexTextMuted" call _hx, "Cliquez sur un nœud : sauts, route jusqu'au relais, maillon faible, voisins." call _T]
        } else {
            format ["<t size='1.3' font='COMSPEC_BarlowBold'>%1</t><br/>", (_nodes select _sel) select 0] + ((_det apply {
                _x params ["_k", "_v", "_tok"];
                format ["<t size='0.85' color='%1'>%2</t><br/><t color='%3'>%4</t>", "hexTextMuted" call _hx, _k call _T, ("hex" + (toUpper (_tok select [0, 1])) + (_tok select [1])) call _hx, _v call _T]
            }) joinString "<br/>")
        };
        private _dt = ["COMSPEC_RscStructuredText", [_px + _fs * 0.7, _gy + _fs * 0.7, _dw - _fs * 1.4, _gh - _fs * 6]] call _mk;
        _dt ctrlSetStructuredText parseText _txt;
        // Actions Zeus sur le nœud choisi : relais (allumer, couper, réparer), joueur (couper sa radio). Vérifiées côté serveur comme avant.
        if (_sel >= 0 && {_sel < (count _nodes)}) then {
            private _o = (_nodes select _sel) select 6;
            uiNamespace setVariable ["COMSPEC_ATAK_GraphSelObj", _o];
            private _ay = _gy + _gh - _bh * 3.6;
            if (_o getVariable ["COMSPEC_AtakRelay", false]) then {
                private _on = _o getVariable ["COMSPEC_AtakRelayOn", true];
                [[_px + _fs * 0.7, _ay, _dw - _fs * 1.4, _bh], ["ALLUMER LE RELAIS", "COUPER LE RELAIS"] select _on, compile format ["['set', [uiNamespace getVariable ['COMSPEC_ATAK_GraphSelObj', objNull], 'On', %1]] call comspec_atak_native_fnc_relayZeus; ['render'] call comspec_atak_native_fnc_meshGraphView;", !_on]] call _btn;
                [[_px + _fs * 0.7, _ay + _bh * 1.15, _dw - _fs * 1.4, _bh], "RÉPARER LE RELAIS", { private _r = uiNamespace getVariable ["COMSPEC_ATAK_GraphSelObj", objNull]; if (alive _r) then { [_r, 0] remoteExec ["setDamage", _r]; }; missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", nil, true]; ["render"] call comspec_atak_native_fnc_meshGraphView; }] call _btn;
            } else {
                if (_o isKindOf "CAManBase" && {!isNil { missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk" }}) then {
                    [[_px + _fs * 0.7, _ay, _dw - _fs * 1.4, _bh], "COUPER LA RADIO DE CE JOUEUR", { ["off", [uiNamespace getVariable ["COMSPEC_ATAK_GraphSelObj", objNull]]] call (missionNamespace getVariable "COMSPEC_ATAK_MeshZeusAsk"); }] call _btn;
                };
            };
        };
        _ui set ["ctrls", _made];
    };
};
