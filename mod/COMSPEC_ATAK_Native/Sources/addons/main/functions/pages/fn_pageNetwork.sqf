/*
    App Réseau : chemin de la liaison (téléphone → porteur → COMSPEC Link → Athena) en schéma, indicateurs en tuiles,
    passerelles, relais et postes de commandement proches (celle utilisée marquée CONNECTÉ, la raison pour les autres),
    puis zone radio, débit simulé (fn_linkQuality) et état du téléphone (fn_deviceHealth, fn_deviceReport).
    Données : fn_linkQuality, fn_relayNet (relais de mission, passerelles de véhicule, état, places, batterie),
    fn_vehicleTerminal, fn_waveRelayAction (maillage), fn_antenna, état du câble DL-7 et de la
    Liaison allié. Rafraîchie toutes les 5 s tant qu'elle est affichée.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _k = _fs / (0.024 * safeZoneH);
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _cw = _bw - 0.012;
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _link = toLower (missionNamespace getVariable ["COMSPEC_LinkState", ["offline", "linked"] select ((_s getOrDefault ["networkState", ""]) isEqualTo "CONNECTED")]);
private _lat = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
private _loss = 0;
private _sent = 0;
if (_bridge && {!isNil "comspec_overwatch_connect_fnc_getPacketLossStats"}) then {
    private _st = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    if (_st isEqualType createHashMap) then { _loss = _st getOrDefault ["packet_loss_percent", 0]; _sent = _st getOrDefault ["packets_sent_total", 0]; };
};
// Réponses d'Athena aux envois courants (positions comprises), lues en mémoire dans COMSPEC Link (fn_athenaPulse).
private _pulse = [] call comspec_atak_native_fnc_athenaPulse;
if ((_pulse get "sent") >= 0) then { _sent = _pulse get "sent"; };
if ((_pulse get "loss") >= 0) then { _loss = _loss max (_pulse get "loss"); };
if (_lat < 0) then { _lat = _pulse get "lat"; };
private _athRecent = _pulse get "recent";
private _ageS = { params ["_k2"]; private _t = missionNamespace getVariable [_k2, -1]; if (!(_t isEqualType 0) || {_t <= 0}) then { -1 } else { diag_tickTime - _t } };
private _ageTxt = { params ["_a"]; switch (true) do { case (_a < 0): { "jamais" }; case (_a < 90): { format ["il y a %1 s", round _a] }; default { format ["il y a %1 min", round (_a / 60)] }; } };
private _lq = [] call comspec_atak_native_fnc_linkQuality;
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _dist = { params ["_m"]; if (_m >= 1000) then { format ["%1 km", (_m / 1000) toFixed 1] } else { format ["%1 m", round _m] } };

// Couleurs d'état.
private _col = createHashMapFromArray [["ok", [0.36, 0.78, 0.42, 1]], ["warn", [0.95, 0.67, 0.2, 1]], ["bad", [0.9, 0.28, 0.23, 1]], ["off", [0.42, 0.47, 0.44, 1]]];
private _hex = createHashMapFromArray [["ok", "#5cc76b"], ["warn", "#f2ab33"], ["bad", "#e5483a"], ["off", "#6d7a74"]];

// --- Contrôles ---
private _mk = { params ["_cls", "_pos", ["_txt", ""]]; [_cls, _pos, _txt] call comspec_atak_native_fnc_pageCtrl };
private _rect = { params ["_pos", "_rgba"]; private _c = ["COMSPEC_RscText", _pos] call _mk; _c ctrlSetBackgroundColor _rgba; _c ctrlEnable false; _c };
private _txt = {
    params ["_pos", "_s2", ["_fit", false]];
    private _c = ["COMSPEC_RscStructuredText", _pos] call _mk;
    _c ctrlSetStructuredText parseText ((_s2) call comspec_atak_native_fnc_tr);
    _c ctrlEnable false;
    if (_fit) then { _pos set [3, (ctrlTextHeight _c) max (_pos select 3)]; _c ctrlSetPosition _pos; _c ctrlCommit 0; };
    _c
};
private _lw = pixelW * 2;
private _lh = pixelH * 2;
// Barres de signal (0-4) dessinées.
private _bars = {
    params ["_x0", "_y0", "_h", "_n", "_st"];
    private _bw1 = _h * 0.22 / _ratio;
    for "_i" from 1 to 4 do {
        private _hh = _h * (0.25 + 0.25 * (_i - 1));
        [[_x0 + (_i - 1) * _bw1 * 1.45, _y0 + _h - _hh, _bw1, _hh], [[0.25, 0.29, 0.27, 1], _col get _st] select (_i <= _n)] call _rect;
    };
    _bw1 * 1.45 * 4
};
private _qBars = { params ["_q"]; switch (true) do { case (_q <= 0): { 0 }; case (_q < 0.25): { 1 }; case (_q < 0.5): { 2 }; case (_q < 0.75): { 3 }; default { 4 }; } };
// Fond de page, allongé à la fin à la hauteur du contenu.
private _bgc = [[0, 0, _bw, _bh], [0.035, 0.045, 0.04, 0.96]] call _rect;
private _y = _pad * 0.6;
private _section = {
    params ["_title", ["_sub", ""]];
    _y = _y + _pad * 0.4;
    [[_pad, _y + _fs * 0.15, _lw * 2, _fs * 1.0], _acc] call _rect;
    private _c = [[_pad * 1.6, _y, _cw - _pad * 2.2, _fs * 1.3], format ["<t size='%1' font='PuristaBold' color='#e8eadf'>%2</t>%3", _k * 0.95, toUpper _title,
        ["", format ["<br/><t size='%1' color='#8a9a93'>%2</t>", _k * 0.72, _sub]] select (_sub isNotEqualTo "")], true] call _txt;
    _y = _y + ((ctrlPosition _c) select 3) + _pad * 0.3;
};
private _info = {
    params ["_label", "_value"];
    private _a = [[_pad, _y, _cw * 0.42, _fs * 1.25], format ["<t size='%1' color='#8a9a93'>%2</t>", _k * 0.8, _label], true] call _txt;
    private _b = [[_pad + _cw * 0.42, _y, _cw * 0.58 - 2 * _pad, _fs * 1.25], format ["<t size='%1' align='right'>%2</t>", _k * 0.8, _value], true] call _txt;
    _y = _y + (((ctrlPosition _a) select 3) max ((ctrlPosition _b) select 3)) + _pad * 0.15;
    [[_pad, _y - _pad * 0.08, _cw - 2 * _pad, _lh * 0.5], [1, 1, 1, 0.05]] call _rect;
};
private _para = {
    params ["_s2"];
    private _c = [[_pad, _y, _cw - 2 * _pad, _fs * 1.2], format ["<t size='%1'>%2</t>", _k * 0.85, _s2], true] call _txt;
    _y = _y + ((ctrlPosition _c) select 3) + _pad * 0.3;
};
private _buttons = {
    params ["_btns"];
    private _n = count _btns;
    private _w1 = ((_cw - 2 * _pad) - (_n - 1) * _pad * 0.5) / (_n max 1);
    {
        _x params ["_label", "_code", ["_primary", false]];
        private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, [_pad + _forEachIndex * (_w1 + _pad * 0.5), _y, _w1, _fs * 1.7], _label] call _mk;
        _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        _b ctrlAddEventHandler ["ButtonClick", _code];
    } forEach _btns;
    _y = _y + _fs * 1.7 + _pad * 0.5;
};

// --- Chemin de la liaison ---
private _air = _lq getOrDefault ["airplane", false];
private _sim = _lq get "sim";
private _dead = ((_lq get "factors") select { (_x select 1) isEqualTo "aucun signal" }) apply { _x select 0 };
private _zone = missionNamespace getVariable ["COMSPEC_ZoneEffects", createHashMap];
if !(_zone isEqualType createHashMap) then { _zone = createHashMap; };
// 1. Téléphone : antenne, câble DL-7, liaison allié.
private _ext = ["get"] call comspec_atak_native_fnc_antenna;
private _antDmg = ((_hp getOrDefault ["parts", createHashMap]) getOrDefault ["antenna", 0]) > 0;
private _phoneSub = [[["Antenne interne", "<t color='#f2ab33'>Antenne interne endommagée</t>"] select _antDmg, _ext param [2, ""]] select (_ext isNotEqualTo [])];
if (((uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap]) getOrDefault ["phase", ""]) isNotEqualTo "") then { _phoneSub pushBack "câble DL-7 branché"; };
private _ally = uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap];
if ((_ally getOrDefault ["phase", ""]) isEqualTo "LINKED" && {!isNull (_ally getOrDefault ["target", objNull])}) then { _phoneSub pushBack format ["lié à %1", [name (_ally get "target")] call _esc]; };
private _phoneSt = switch (true) do {
    case ((_hp get "state") in ["OFF", "BROKEN"]): { "bad" };
    case (_air || {(_lq get "kbps") <= 0}): { "bad" };
    case ((_lq get "bars") <= 1): { "warn" };
    default { "ok" };
};
private _nodes = [];
_nodes pushBack ["Téléphone", _lq get "label", _phoneSub joinString " · ", _phoneSt, _lq get "bars", "\z\comspec_atak_native\addons\main\data\item_phone.paa"];
// 2. Porteur : passerelle de véhicule / PC mobile, relais Overwatch, maillage via équipiers, ou direct.
private _best = ["best"] call comspec_atak_native_fnc_relayNet;
private _req = (missionNamespace getVariable ["COMSPEC_LinkViaRelays", false]) || {missionNamespace getVariable ["COMSPEC_ATAK_RelaysRequired", false]};
private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
private _hops = (_mesh getOrDefault ["hops", [-1]]) param [_mesh getOrDefault ["me", 0], -1];
private _term = [player] call comspec_atak_native_fnc_vehicleTerminal;
private _activeObj = _best param [0, objNull];
private _bearer = switch (true) do {
    case (_best isNotEqualTo []): {
        _best params ["_o", "_n", "_d", "_mb", "_ls", "_k", "_eff", "_u", "_sl", "_bt"];
        private _extra = [];
        if (_sl > 0) then { _extra pushBack format ["%1/%2 places", _u, _sl]; };
        if (_bt >= 0) then { _extra pushBack format ["batterie %1 %%", round _bt]; };
        [createHashMapFromArray [["relay", "Relais radio"], ["gw", "Passerelle véhicule"], ["cp", "Poste de commandement"]] getOrDefault [_k, "Relais radio"], [_n] call _esc,
            format ["%1 / %2 · %3 Mbit/s%4", [_d] call _dist, [_eff] call _dist, _mb toFixed 1, ["", " · " + (_extra joinString " · ")] select (_extra isNotEqualTo [])],
            ["ok", "warn"] select (_sl > 0 && {_u > _sl} || {_bt >= 0 && {_bt < 20}}), [1 - (_d / (_eff max 1)) ^ 2] call _qBars,
            ["\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "\z\comspec_atak_native\addons\main\data\app_c2.paa"] select (_k isEqualTo "cp")]
    };
    case (_hops > 1): {
        ["Maillage Wave Relay", format ["%1 sauts via équipiers", _hops - 1], format ["jusqu'à %1", [_mesh getOrDefault ["gwLabel", ""]] call _esc], "warn", 2, "\z\comspec_atak_native\addons\main\data\app_waverelay.paa"]
    };
    case (_req): {
        ["Aucun porteur", "Relais obligatoires", "aucun relais, passerelle ou équipier à portée", "bad", 0, "\z\comspec_atak_native\addons\main\data\app_waverelay.paa"]
    };
    default {
        ["Réseau direct", "Liaison radio-data du téléphone", ["sans relais (mode arcade)", "terminal de bord du véhicule"] select (_term isNotEqualTo ""), "ok", _lq get "bars", "\z\comspec_atak_native\addons\main\data\app_network.paa"]
    };
};
_nodes pushBack _bearer;
// 3. COMSPEC Link (Overwatch) ou extension native.
private _linkSt = switch (_link) do { case "linked": { "ok" }; case "degraded"; case "connecting": { "warn" }; default { "bad" }; };
_nodes pushBack [["Extension native", "COMSPEC Link"] select _bridge, toUpper _link,
    format ["latence %1 · perte %2 %%", [format ["%1 ms", round _lat], "non mesurée"] select (_lat < 0), round _loss], _linkSt, [0, 4, 2, 2, 1] select ((["", "linked", "degraded", "connecting", "offline"] find _link) max 0), "\z\comspec_atak_native\addons\main\data\ui_comspec_link.paa"];
// 4. Athena : âge de la dernière réponse.
private _hAge = ["COMSPEC_LastHealthOk"] call _ageS;
private _athSt = if (_bridge) then { switch (true) do { case (_hAge < 0 && {_athRecent}): { "ok" }; case (_hAge >= 0 && {_pulse get "idle"}): { "ok" }; case (_hAge < 0): { "bad" }; case (_hAge < 90): { "ok" }; case (_hAge < 300): { "warn" }; default { "bad" }; } } else { ["bad", "ok"] select (_link isEqualTo "linked") };
private _hTxt = ([[_hAge] call _ageTxt, "il y a moins de 15 min"] select (_hAge < 0 && {_athRecent}));
_nodes pushBack ["Athena", ["hors ligne", "en ligne"] select (_athSt isNotEqualTo "bad"), format ["dernière réponse %1", _hTxt], _athSt, [0, 2, 4] select (["bad", "warn", "ok"] find _athSt), "\z\comspec_atak_native\addons\main\data\app_athena.paa"];
// Sauts : raison de la coupure sur le premier saut rompu ; au-delà, tout est éteint.
private _hopWhy = [
    switch (true) do {
        case (_air): { "mode avion" };
        case ((_hp get "state") in ["OFF", "BROKEN"]): { toLower (_hp get "reason") };
        case ((count _dead) > 0): { toLower (_dead select 0) };
        case (_zone getOrDefault ["force_disconnect", false]): { format ["zone %1 : coupure", _zone getOrDefault ["name", "radio"]] };
        case ((_bearer select 3) isEqualTo "bad"): { "aucun porteur à portée" };
        default { "" };
    },
    ["", "Athena hors ligne"] select (_linkSt isEqualTo "bad"),
    ["", "pas de réponse d'Athena"] select (_athSt isEqualTo "bad")
];
private _broken = -1;
{ if (_x isNotEqualTo "" && {_broken < 0}) then { _broken = _forEachIndex; }; } forEach _hopWhy;
if (_broken >= 0) then { for "_i" from (_broken + 2) to 3 do { (_nodes select _i) set [3, "off"]; }; };

["Chemin de la liaison", "Du téléphone à Athena : chaque saut, son état"] call _section;
private _wide = _cw > _fs * 34;
private _card = {
    params ["_pos", "_node", ["_active", false]];
    _pos params ["_x0", "_y0", "_w0", "_h0"];
    _node params ["_title", "_main", "_sub", "_st", "_nb", "_icon"];
    [_pos, [[0.07, 0.085, 0.078, 0.96], [0.09, 0.12, 0.10, 0.98]] select _active] call _rect;
    [[_x0, _y0, _lw * 2, _h0], _col get _st] call _rect;
    if (_active) then {
        [[_x0, _y0, _w0, _lh], _acc] call _rect;
        [[_x0, _y0 + _h0 - _lh, _w0, _lh], _acc] call _rect;
        [[_x0 + _w0 - _lw, _y0, _lw, _h0], _acc] call _rect;
    };
    private _icH = (_h0 * 0.5) min (_fs * 1.6);
    private _ic = ["COMSPEC_RscPicture", [_x0 + _pad * 0.6, _y0 + (_h0 - _icH) / 2, _icH / _ratio, _icH], _icon] call _mk;
    _ic ctrlSetTextColor ([[1, 1, 1, 0.9], [1, 1, 1, 0.3]] select (_st isEqualTo "off"));
    _ic ctrlEnable false;
    private _tx = _x0 + _pad * 1.1 + _icH / _ratio;
    private _barW = _fs * 0.9 / _ratio * 0.22 * 1.45 * 4;
    [[_tx, _y0 + _pad * 0.15, _x0 + _w0 - _tx - _barW - _pad * 0.6, _h0 - _pad * 0.2], format ["<t size='%1' font='EtelkaMonospaceProBold' color='%2'>%3</t>%7<br/><t size='%4' font='RobotoCondensedBold' color='%5'>%6</t><br/><t size='%8' color='#8a9a93'>%9</t>",
        _k * 0.6, _hex get _st, toUpper _title, _k * 0.85, ["#e8eadf", "#6d7a74"] select (_st isEqualTo "off"), _main,
        ["", format ["<t size='%1' color='%2'>  ● ACTIF</t>", _k * 0.6, _accHex]] select _active, _k * 0.68, _sub]] call _txt;
    [_x0 + _w0 - _barW - _pad * 0.5, _y0 + _pad * 0.4, _fs * 0.9, [_nb, 0] select (_st isEqualTo "off"), [_st, "ok"] select (_st isEqualTo "off")] call _bars;
};
if (_wide) then {
    // Paysage large : quatre cartes en ligne, sauts entre elles.
    private _gap = _fs * 1.6;
    private _w1 = ((_cw - 2 * _pad) - 3 * _gap) / 4;
    private _h1 = _fs * 4.4;
    {
        private _x0 = _pad + _forEachIndex * (_w1 + _gap);
        [[_x0, _y, _w1, _h1], _x, _forEachIndex isEqualTo 1 && {(_x select 3) isNotEqualTo "bad"}] call _card;
        if (_forEachIndex < 3) then {
            private _why = _hopWhy select _forEachIndex;
            private _st = switch (true) do { case (_why isNotEqualTo ""): { "bad" }; case (_broken >= 0 && {_forEachIndex > _broken}): { "off" }; default { ((_nodes select (_forEachIndex + 1)) select 3) }; };
            [[_x0 + _w1, _y + _h1 / 2 - _lh, _gap, _lh * 2], _col get _st] call _rect;
            if (_why isNotEqualTo "") then {
                [[_x0 + _w1 + _gap / 2 - _lw * 3, _y + _h1 / 2 - _lh * 6 * 0.5 / 1, _lw * 6, _lh * 6], _col get "bad"] call _rect;
            };
        };
    } forEach _nodes;
    _y = _y + _h1 + _pad * 0.3;
    private _whyAll = (_hopWhy select { _x isNotEqualTo "" });
    if ((count _whyAll) > 0) then { [format ["<t color='#e5483a'>✗ Coupure : %1</t>", _whyAll select 0]] call _para; };
} else {
    // Portrait / mini : cartes empilées, saut vertical avec sa raison.
    private _h1 = _fs * 3.3;
    private _gapH = _fs * 1.25;
    {
        [[_pad, _y, _cw - 2 * _pad, _h1], _x, _forEachIndex isEqualTo 1 && {(_x select 3) isNotEqualTo "bad"}] call _card;
        _y = _y + _h1;
        if (_forEachIndex < 3) then {
            private _why = _hopWhy select _forEachIndex;
            private _st = switch (true) do { case (_why isNotEqualTo ""): { "bad" }; case (_broken >= 0 && {_forEachIndex > _broken}): { "off" }; default { ((_nodes select (_forEachIndex + 1)) select 3) }; };
            private _lx = _pad + _fs * 1.2;
            [[_lx, _y, _lw * 2, _gapH], _col get _st] call _rect;
            [[_lx + _fs * 0.6, _y, _cw - _lx - _pad, _gapH], format ["<t size='%1' color='%2'>%3</t>", _k * 0.7, _hex get _st,
                [["▼", "▼ en attente"] select (_st isEqualTo "off"), "✗ " + _why] select (_why isNotEqualTo "")]] call _txt;
            _y = _y + _gapH;
        };
    } forEach _nodes;
    _y = _y + _pad * 0.3;
};

// --- Indicateurs ---
["Indicateurs"] call _section;
private _cols = [2, 3] select _wide;
private _tw = ((_cw - 2 * _pad) - (_cols - 1) * _pad * 0.5) / _cols;
private _th = _fs * 2.9;
private _tiles = [
    ["Stabilité", format ["%1 %%", _stab], _stabLabel, switch (true) do { case (_stab >= 70): { "ok" }; case (_stab >= 40): { "warn" }; default { "bad" }; }, _stab / 100],
    ["Latence", [format ["%1 ms", round _lat], "—"] select (_lat < 0), ["non mesurée", ""] select (_lat >= 0), switch (true) do { case (_lat < 0): { "off" }; case (_lat < 150): { "ok" }; case (_lat < 400): { "warn" }; default { "bad" }; }, -1],
    ["Perte de paquets", format ["%1 %%", round _loss], "", switch (true) do { case (_loss < 5): { "ok" }; case (_loss < 20): { "warn" }; default { "bad" }; }, -1],
    ["Paquets envoyés", str _sent, "", "off", -1],
    ["Dernière position", [["COMSPEC_LastPositionSync"] call _ageS] call _ageTxt, "envoyée à Athena", switch (true) do { case ((["COMSPEC_LastPositionSync"] call _ageS) < 0): { "bad" }; case ((["COMSPEC_LastPositionSync"] call _ageS) < 60): { "ok" }; default { "warn" }; }, -1],
    ["Réponse Athena", _hTxt, "", _athSt, -1]
];
{
    _x params ["_lbl", "_val", "_sub", "_st", "_gauge"];
    private _tx = _pad + (_forEachIndex mod _cols) * (_tw + _pad * 0.5);
    private _ty = _y + (floor (_forEachIndex / _cols)) * (_th + _pad * 0.5);
    [[_tx, _ty, _tw, _th], [0.07, 0.085, 0.078, 0.96]] call _rect;
    [[_tx + _pad * 0.5, _ty + _pad * 0.2, _tw - _pad, _th], format ["<t size='%1' color='#8a9a93'>%2</t><br/><t size='%3' font='RobotoCondensedBold' color='%4'>%5</t> <t size='%6' color='#8a9a93'>%7</t>",
        _k * 0.62, toUpper _lbl, _k * 1.15, [_hex get _st, "#e8eadf"] select (_st isEqualTo "off"), _val, _k * 0.65, _sub]] call _txt;
    if (_gauge >= 0) then {
        private _gw2 = _tw - _pad;
        [[_tx + _pad * 0.5, _ty + _th - _fs * 0.45, _gw2, _fs * 0.22], [0.2, 0.24, 0.22, 1]] call _rect;
        [[_tx + _pad * 0.5, _ty + _th - _fs * 0.45, (_gw2 * (_gauge min 1)) max _lw, _fs * 0.22], _col get _st] call _rect;
    };
} forEach _tiles;
_y = _y + (ceil ((count _tiles) / _cols)) * (_th + _pad * 0.5);

// --- Passerelles, relais et postes de commandement proches ---
private _sub = format ["Mode %1", ["arcade : le téléphone passe sans relais", "réaliste : relais obligatoire"] select _req];
["Passerelles et relais", _sub] call _section;
private _items = [];
{
    _x params ["_o", "_ant", "_n", "_k", "_r", "_mb", "_ok", "_st", "_u", "_sl", "_bt"];
    private _d = player distance _ant;
    private _masked = terrainIntersectASL [eyePos player, _ant];
    private _eff = [[_r, _r * 0.35] select _masked, 0] select !_ok;
    private _why = switch (true) do {
        case (_o isEqualTo _activeObj): { "" };
        case (!_ok): { _st };
        case (_d >= _eff): { format ["hors portée%1", ["", " (relief masquant)"] select _masked] };
        default { "à portée, moins bon débit" };
    };
    private _rate = format ["%1 Mbit/s", _mb toFixed 1];
    if (_sl > 0) then { _rate = _rate + format [" · %1/%2", _u, _sl]; };
    if (_bt >= 0) then { _rate = _rate + format [" · %1 %%", round _bt]; };
    _items pushBack [_d, createHashMapFromArray [["relay", "Relais radio"], ["gw", "Passerelle véhicule"], ["cp", "Poste de commandement"]] getOrDefault [_k, "Relais radio"], _n, _r, _eff, _rate, _o isEqualTo _activeObj, _why,
        ["\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "\z\comspec_atak_native\addons\main\data\app_c2.paa"] select (_k isEqualTo "cp")];
} forEach (["list"] call comspec_atak_native_fnc_relayNet);
_items sort true;
if ((count _items) isEqualTo 0) then {
    [format ["<t color='#8a9a93'>Aucune passerelle de véhicule, aucun poste de commandement ni relais déclaré pour votre camp.%1</t>",
        ["", format ["<br/>Maillage : %1", [[_mesh getOrDefault ["gwLabel", ""]] call _esc, "aucune passerelle"] select ((_mesh getOrDefault ["gwLabel", ""]) isEqualTo "")]] select (_hops >= 0)]] call _para;
};
private _rowH = _fs * 2.5;
{
    _x params ["_d", "_type", "_name", "_r", "_eff", "_rate", "_on", "_why", "_icon"];
    private _q = [0, 1 - (_d / (_eff max 1)) ^ 2] select (_d < _eff);
    private _st = switch (true) do { case (_on): { "ok" }; case (_why in ["détruit", "éteint", "batterie vide", "brouillé", "sans liaison"]): { "bad" }; case (_q > 0): { "warn" }; default { "off" }; };
    private _x0 = _pad;
    private _w0 = _cw - 2 * _pad;
    [[_x0, _y, _w0, _rowH], [[0.06, 0.072, 0.066, 0.96], [0.09, 0.12, 0.10, 0.98]] select _on] call _rect;
    if (_on) then { [[_x0, _y, _lw * 2, _rowH], _acc] call _rect; };
    private _icH = _rowH * 0.6;
    private _ic = ["COMSPEC_RscPicture", [_x0 + _pad * 0.6, _y + _rowH * 0.2, _icH / _ratio, _icH], _icon] call _mk;
    _ic ctrlSetTextColor ([[1, 1, 1, 0.45], [1, 1, 1, 1]] select (_on || {_q > 0}));
    _ic ctrlEnable false;
    private _badgeW = _fs * 5.4;
    private _tx = _x0 + _pad * 1.2 + _icH / _ratio;
    private _bwBars = [_x0 + _w0 - _badgeW - _pad * 0.6 - _fs * 1.6, _y + _rowH * 0.25, _rowH * 0.5, [_q] call _qBars, [_st, "ok"] select (_st isEqualTo "off")] call _bars;
    [[_tx, _y + _pad * 0.1, (_x0 + _w0 - _badgeW - _pad * 0.8 - _fs * 1.8) - _tx, _rowH], format ["<t size='%1' font='RobotoCondensedBold'>%2</t> <t size='%3' color='#8a9a93'>%4</t><br/><t size='%3' color='#8a9a93'>%5 · portée %6 · %7</t>",
        _k * 0.85, [_name] call _esc, _k * 0.68, _type, [_d] call _dist, [_eff] call _dist, _rate]] call _txt;
    private _bd = ["COMSPEC_RscTextCenter", [_x0 + _w0 - _badgeW - _pad * 0.4, _y + _rowH * 0.25, _badgeW, _rowH * 0.5], ["", "CONNECTÉ"] select _on] call _mk;
    _bd ctrlEnable false;
    if (_on) then {
        _bd ctrlSetBackgroundColor _acc;
        _bd ctrlSetTextColor [0.02, 0.03, 0.02, 1];
        _bd ctrlSetFont "RobotoCondensedBold";
        _bd ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
    } else {
        ctrlDelete _bd;
        [[_x0 + _w0 - _badgeW - _pad * 0.4, _y + _rowH * 0.12, _badgeW, _rowH * 0.8], format ["<t align='right' size='%1' color='%2'>%3</t>", _k * 0.66, _hex get _st, _why]] call _txt;
    };
    _y = _y + _rowH + _pad * 0.3;
} forEach (_items select [0, 10]);
if ((_mesh getOrDefault ["gwLabel", ""]) isNotEqualTo "") then {
    ["Maillage Wave Relay", format ["%1 · %2", [_mesh get "gwLabel"] call _esc, switch (true) do { case (_hops < 0): { "<t color='#e5483a'>coupé</t>" }; case (_hops <= 1): { "en direct" }; default { format ["%1 sauts", _hops] }; }]] call _info;
};

// --- Zone radio et canal (COMSPEC Overwatch) ---
if (_bridge) then {
    ["Zone radio"] call _section;
    [if ((count _zone) isEqualTo 0) then { "Couverture normale." } else {
        format ["%1 (%2)<br/>Perte minimale %3 %% · latence +%4 ms", _zone getOrDefault ["name", "Zone"], _zone getOrDefault ["type", "?"], _zone getOrDefault ["packet_loss_floor", 0], _zone getOrDefault ["latency_add", 0]]
    }] call _para;
    // Fonctions d'Overwatch absentes d'une version plus ancienne : la page continue sans elles.
    if (!isNil "comspec_overwatch_connect_fnc_isLinkDegradeSimActive") then {
        ["Simulation de liaison dégradée", ["inactive", "<t color='#f2ab33'>active</t>"] select ([] call comspec_overwatch_connect_fnc_isLinkDegradeSimActive)] call _info;
    };
    if (_best isNotEqualTo []) then {
        ["Relais utilisé", format ["%1 · %2 · %3 Mbit/s", [_best select 1] call _esc, [_best select 2] call _dist, (_best select 3) toFixed 1]] call _info;
    };
    _y = _y + _pad * 0.3;
    [[
        ["MESURER LA LATENCE", { if (!isNil "comspec_overwatch_connect_fnc_measureLatency") then { [] call comspec_overwatch_connect_fnc_measureLatency; }; [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
        ["ROUVRIR LE CANAL", { ["enter"] call comspec_atak_native_fnc_athenaAction; }, true]
    ]] call _buttons;
} else {
    ["<t color='#8a9a93'>COMSPEC Overwatch n'est pas chargé : relais, zones et simulations ne sont pas disponibles.</t>"] call _para;
};

// --- Débit simulé du téléphone (fn_linkQuality) ---
private _rate = { params ["_kb"]; if (_kb >= 1000) then { format ["%1 Mbit/s", (_kb / 1000) toFixed 1] } else { format ["%1 kbit/s", _kb] } };
["Débit du téléphone"] call _section;
if !(_sim) then {
    ["<t color='#8a9a93'>Simulation de débit coupée par le serveur (réglages CBA).</t>"] call _para;
} else {
    private _bx = _pad;
    [_bx, _y + _fs * 0.1, _fs * 1.1, _lq get "bars", ["bad", "bad", "warn", "ok", "ok"] select (_lq get "bars")] call _bars;
    private _queue = count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]);
    private _c = [[_bx + _fs * 2.2, _y, _cw - _fs * 2.2 - 2 * _pad, _fs * 1.3], format ["<t size='%1' font='RobotoCondensedBold'>%2</t><br/><t size='%3'>Débit <t color='#c9d4cf'>%4</t> · latence %5 ms · perte %6 %%<br/>Photo (600 Ko) : environ %7 s · message : %8 s%9</t>",
        _k, _lq get "label", _k * 0.8, [_lq get "kbps"] call _rate, _lq get "latency", _lq get "loss",
        [round (((_lq get "latency") / 1000) + 4800 / ((_lq get "kbps") max 1)), "∞"] select ((_lq get "kbps") <= 0),
        [((((_lq get "latency") / 1000) + 8 / ((_lq get "kbps") max 1)) toFixed 1), "∞"] select ((_lq get "kbps") <= 0),
        ["", format ["<br/><t color='#f2ab33'>%1 envoi(s) en attente de réseau</t>", _queue]] select (_queue > 0)], true] call _txt;
    _y = _y + ((ctrlPosition _c) select 3) + _pad * 0.4;
    { _x call _info; } forEach (_lq get "factors");
};

// --- File d'envoi Athena (journal de COMSPEC Link 2.0.65, lu en mémoire sans réseau) ---
private _sqRaw = if (_bridge && {!(missionNamespace getVariable ["COMSPEC_ATAK_SendQNo", false])}) then { ("COMSPECExtension" callExtension ["SendQueue.Status", []]) select 0 } else { "" };
if ((_sqRaw select [0, 3]) isEqualTo "OK|") then {
    private _raw = _sqRaw;
    private _lines = (_raw select [3]) splitString toString [10];
    private _h = (_lines param [0, ""]) splitString toString [9];
    _h params [["_q", "0"], ["_sd", "0"], ["_rt", "0"], ["_fl", "0"], ["_dn", "0"], ["_ov", "ok"], ["_avg", "0"], ["_p95", "0"], ["_n429", "0"], ["_n5xx", "0"], ["_nto", "0"]];
    private _ovTxt = switch (_ov) do {
        case "offline": { ["bad", "Athena injoignable : envois en pause"] };
        case "rate_limited": { ["bad", "Athena surchargé : il demande de ralentir"] };
        case "slow": { ["warn", "Athena ralenti"] };
        default { ["ok", "Athena répond normalement"] };
    };
    ["File d'envoi Athena", format ["Latence moyenne %1 ms · p95 %2 ms · 5 dernières min : %3 surcharge(s), %4 erreur(s) serveur, %5 délai(s)", _avg, _p95, _n429, _n5xx, _nto]] call _section;
    ["État d'Athena", format ["<t color='%1'>%2</t>", _hex get (_ovTxt select 0), _ovTxt select 1]] call _info;
    (missionNamespace getVariable ["COMSPEC_ATAK_ServerPresence", []]) params [["_svSt", ""], ["_svLbl", ""], ["_svCom", ""], ["", ""], ["_svAt", ""]];
    if (_svSt in ["ok", "away"]) then {
        ["Serveur de jeu", [format ["<t color='%1'>%2</t> · vérifié à %3", _hex get "ok", [_svLbl] call _esc, _svAt],
            format ["<t color='%1'>hors des serveurs de %2</t> · vérifié à %3", _hex get "warn", [["votre communauté", _svCom] select (_svCom isNotEqualTo "")] call _esc, _svAt]] select (_svSt isEqualTo "away")] call _info;
    };
    ["Envois", format ["%1 en file · %2 en cours · <t color='#f2ab33'>%3 nouvel essai</t> · <t color='#e5483a'>%4 échec</t> · <t color='#5cc76b'>%5 envoyés</t>", _q, _sd, _rt, _fl, _dn]] call _info;
    private _stTxt = createHashMapFromArray [["queued", ["off", "en file"]], ["sending", ["warn", "envoi…"]], ["retry", ["warn", "nouvel essai"]], ["failed", ["bad", "échec"]], ["done", ["ok", "envoyé"]]];
    {
        (_x splitString toString [9]) params [["_id", ""], ["_lbl", ""], ["_st", ""], ["_try", "0"], ["_age", "0"], ["_err", ""]];
        private _m = _stTxt getOrDefault [_st, ["off", _st]];
        private _a = parseNumber _age;
        [[_lbl] call _esc, format ["<t color='%1'>%2</t> <t color='#8a9a93'>· %3 essai(s) · %4%5</t>", _hex get (_m select 0), _m select 1, _try,
            [format ["%1 s", _a], format ["%1 min", round (_a / 60)]] select (_a >= 90),
            ["", format ["<br/>%1", [_err] call _esc]] select (_err isNotEqualTo "" && {_st in ["retry", "failed"]})]] call _info;
    } forEach ((_lines select [1, 8]));
    if ((count _lines) <= 1) then { ["<t color='#8a9a93'>Aucun envoi suivi pour l'instant (comptes rendus, demandes, messages…).</t>"] call _para; };
};

// --- État du téléphone (fn_deviceHealth, fn_deviceReport) ---
["État du téléphone"] call _section;
["Écran et boîtier", switch (_hp get "state") do {
    case "OK": { "<t color='#5cc76b'>intact</t>" };
    case "OFF": { format ["<t color='#f2ab33'>%1</t>", _hp get "reason"] };
    default { format ["<t color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select ((_hp get "crack") >= 3), _hp get "reason"] };
}] call _info;
["Usure", format ["%1 %%", round ((_hp get "damage") * 100)]] call _info;
{
    switch (_x select 0) do {
        case "info": { [_x select 1, _x select 2] call _info; };
        case "text": { [_x select 1] call _para; };
    };
} forEach ((["rows"] call comspec_atak_native_fnc_deviceReport) select { (_x select 1) isNotEqualTo "Appareil" });
["Batterie", call {
    private _lvl = round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
    (missionNamespace getVariable ["COMSPEC_ATAK_BatteryInfo", [0, []]]) params ["_r"];
    private _left = switch (true) do {
        case (_r < 0): { "en charge" };
        case (_r <= 0.01): { "" };
        default { private _m = round (_lvl / _r); format ["environ %1 h %2 restantes", floor (_m / 60), _m mod 60] };
    };
    format ["<t color='%1'>%2 %%</t>  <t color='#8a9a93'>%3</t>", switch (true) do { case (_lvl <= 5): { "#e5483a" }; case (_lvl <= 20): { "#f2ab33" }; default { "#5cc76b" }; }, _lvl, _left]
}] call _info;
[call {
    (missionNamespace getVariable ["COMSPEC_ATAK_BatteryInfo", [0, []]]) params ["", "_f"];
    "<t size='0.8' color='#8a9a93'>" + ((_f apply { _x params ["_n", ["_v", 0]]; if (_v isEqualType 0) then { format ["%1 %2%3 %%/min", _n, ["", "+"] select (_v < 0), abs _v] } else { format ["%1 %2", _n, _v] } }) joinString " · ") + "</t>"
}] call _para;
["<t size='0.8' color='#8a9a93'>Réparation : action ACE « Réparer le téléphone ATAK » avec une trousse à outils, ou « Changer de téléphone ATAK » avec un appareil de rechange.</t>"] call _para;
[[
    ["TEST DE DÉBIT", { uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []]; private _q = [] call comspec_atak_native_fnc_linkQuality; ["INFO", format ["Test de débit : %1 kbit/s, %2 ms, perte %3 %%", _q get "kbps", _q get "latency", _q get "loss"], 5, 20] call comspec_atak_native_fnc_notify; [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
    ["REDÉMARRER", { ["reboot"] call comspec_atak_native_fnc_deviceRepair; }]
]] call _buttons;
_bgc ctrlSetPosition [0, 0, _bw, _bh max (_y + _pad)];
_bgc ctrlCommit 0;
// Rafraîchissement toutes les 5 s tant que la page est affichée (le défilement est gardé par fn_pageRender).
private _tok = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_NetTok", _tok];
[{
    params ["_tok"];
    if ((uiNamespace getVariable ["COMSPEC_ATAK_NetTok", -1]) isNotEqualTo _tok) exitWith {};
    if (isNull ([] call comspec_atak_native_fnc_display)) exitWith {};
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "NETWORK") exitWith {};
    ["NETWORK"] call comspec_atak_native_fnc_pageRender;
}, [_tok], 5] call CBA_fnc_waitAndExecute;
true
