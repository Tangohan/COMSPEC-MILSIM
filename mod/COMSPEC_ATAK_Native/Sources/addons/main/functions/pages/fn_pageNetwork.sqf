/*
    App Réseau : chemin de la liaison (téléphone → porteur → COMSPEC Link → Athena) en schéma, indicateurs en tuiles,
    passerelles, relais et postes de commandement proches (celle utilisée marquée CONNECTÉ, la raison pour les autres),
    puis zone radio, débit simulé (fn_linkQuality) et état du téléphone (fn_deviceHealth, fn_deviceReport).
    Données : fn_linkQuality, fn_relayNet (relais de mission, passerelles de véhicule, état, places, batterie),
    fn_vehicleTerminal, fn_waveRelayAction (maillage), fn_antenna, état du câble DL-7 et de la
    Liaison allié. Rafraîchie toutes les 5 s tant qu'elle est affichée (défilement gardé par fn_pageRender).
    Liaison réaliste téléphone <-> radio (fn_mpuLink, réglage comspec_atak_native_mpu_real) : le porteur est la radio de
    données ; section « Radio de données » (radio, liaison au téléphone, canal, puissance, batterie, maillage, nœuds,
    débit et latence estimés) avec ses boutons (allumer, canal, puissance, câble EUD, Wi-Fi, débrancher). Sans MPU5 :
    pas de réseau de données.
    Voix ACRE2 / TFAR par le maillage (fn_mpuVoice, réglage comspec_atak_native_mpu_voice) : section « Voix radio par le
    maillage » (radio pontée, état, correspondants pontés joignables) avec les boutons pour relier ou débrancher une radio.
    Lisibilité (2.9.34, retour de jeu : « le texte est trop petit, c'est illisible ») : le texte structuré a pour base le
    corps du téléphone (layoutGet "font", posé sur chaque contrôle, comme l'app Garmin) ; les tailles relatives restent
    entre 0,88 et 1,2 de ce corps (avant : multipliées en plus par fontSmall / (0.024 * safeZoneH), soit des légendes
    à 0,4 du corps, sous le plancher COMSPEC_ATAK_FontMin). Une information par ligne (libellé à gauche, valeur à droite),
    cartes et lignes à la hauteur de leur texte, boutons plus hauts (2 par rangée en portrait). Sections repliables
    (chevron, état gardé dans le profil : COMSPEC_ATAK_NetOpen), seules les quatre premières ouvertes par défaut.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _font = _l get "font";
private _pad = (_l get "pad") * 2;
// Tailles relatives au corps du téléphone (_font) : titres, valeurs, libellés, détails. La plus petite (0,88 du corps)
// reste au-dessus du plancher des légendes (COMSPEC_ATAK_FontMin = 0,68 du corps, 13 px au moins).
private _zT = 1.15;
private _zV = 1.0;
private _zL = 0.95;
private _zS = 0.88;
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
// Gris des libellés et des détails : plus clair qu'avant (#8a9a93), lisible sur le fond sombre.
private _greyL = "#a9b6b0";
private _greyS = "#97a59f";

// --- Contrôles ---
private _mk = { params ["_cls", "_pos", ["_txt", ""]]; [_cls, _pos, _txt] call comspec_atak_native_fnc_pageCtrl };
private _rect = { params ["_pos", "_rgba"]; private _c = ["COMSPEC_RscText", _pos] call _mk; _c ctrlSetBackgroundColor _rgba; _c ctrlEnable false; _c };
// Texte structuré : taille de base = corps du téléphone, posée avant le texte (ctrlTextHeight mesure alors juste).
private _txt = {
    params ["_pos", "_s2", ["_fit", false]];
    private _c = ["COMSPEC_RscStructuredText", _pos] call _mk;
    _c ctrlSetFontHeight _font;
    _c ctrlSetStructuredText parseText ((_s2) call comspec_atak_native_fnc_tr);
    _c ctrlEnable false;
    if (_fit) then { _pos set [3, (ctrlTextHeight _c) max (_pos select 3)]; _c ctrlSetPosition _pos; _c ctrlCommit 0; };
    _c
};
// Clic différé d'une image (comme fn_formRender) : l'action redessine souvent la page, ce qui supprimait le bouton
// pendant son propre gestionnaire.
private _click = {
    params ["_b", "_code"];
    if (_code isEqualType "") then { _code = compile _code; };
    _b setVariable ["comspec_click", _code];
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        [{ params ["_c", "_code"]; [_c] call _code; }, [_c, _c getVariable ["comspec_click", {}]]] call comspec_atak_native_fnc_nextFrame;
    }];
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
// Sections repliables : ouvertes = identifiants rangés dans le profil (par défaut les quatre premières).
private _openDef = ["path", "mpu", "voice", "ind"];
private _openIds = profileNamespace getVariable ["COMSPEC_ATAK_NetOpen", _openDef];
if !(_openIds isEqualType []) then { _openIds = _openDef; };
// En-tête de section repliable (même allure que les sections repliables des formulaires) : titre à gauche, résumé à
// droite, chevron. Ouverte : sous-titre en gris dessous. Renvoie vrai si la section est ouverte.
private _fold = {
    params ["_id", "_title", ["_sum", ""], ["_sub", ""]];
    private _open = _id in _openIds;
    _y = _y + _pad * 0.5;
    private _x0 = _pad;
    private _w0 = _cw - 2 * _pad;
    private _h = _font * 2.1;
    private _cs = _h * 0.5;
    private _chW = _cs / _ratio;
    private _card = [[_x0, _y, _w0, _h], [[0.12, 0.145, 0.132, 1], [0.15, 0.185, 0.165, 1]] select _open] call _rect;
    private _tW = _w0 - _chW - _pad * 2.6;
    private _t = [[_x0 + _pad * 0.9, _y, _tW, _h], format ["<t size='%1' font='COMSPEC_BarlowBold' color='#f2f6f3'>%2</t>%3", _zT, toUpper ((_title) call comspec_atak_native_fnc_tr),
        ["", format ["<t align='right' size='%1' color='#c9d4ce'>%2</t>", _zS, _sum]] select (_sum isNotEqualTo "")]] call _txt;
    private _th = ctrlTextHeight _t;
    _h = _h max (_th + _pad * 0.6);
    _t ctrlSetPosition [_x0 + _pad * 0.9, _y + (((_h - _th) / 2) max 0), _tW, _th];
    _t ctrlCommit 0;
    _card ctrlSetPositionH _h;
    _card ctrlCommit 0;
    [[_x0, _y, pixelW * 6, _h], [[0.55, 0.62, 0.58, 1], _acc] select _open] call _rect;
    private _ch = ["COMSPEC_RscPicture", [_x0 + _w0 - _chW - _pad * 0.75, _y + (_h - _cs) / 2, _chW, _cs], format ["\z\comspec_atak_native\addons\main\data\%1.paa", ["ui_chevron_down", "ui_chevron_up"] select _open]] call _mk;
    _ch ctrlSetTextColor ([[0.92, 0.95, 0.93, 1], _acc] select _open);
    _ch ctrlEnable false;
    private _b = ["COMSPEC_RscButtonOverlay", [_x0, _y, _w0, _h]] call _mk;
    _b ctrlSetTooltip ((["Déplier", "Replier"] select _open) call comspec_atak_native_fnc_tr);
    _b setVariable ["comspec_fold", _id];
    _b setVariable ["comspec_foldDef", _openDef];
    [_b, {
        params ["_c"];
        private _id = _c getVariable ["comspec_fold", ""];
        if (_id isEqualTo "") exitWith {};
        private _o = profileNamespace getVariable ["COMSPEC_ATAK_NetOpen", _c getVariable ["comspec_foldDef", []]];
        if !(_o isEqualType []) then { _o = []; };
        _o = +_o;
        if (_id in _o) then { _o = _o - [_id]; } else { _o pushBack _id; };
        profileNamespace setVariable ["COMSPEC_ATAK_NetOpen", _o];
        playSound "ClickSoft";
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isEqualTo "NETWORK") then { ["NETWORK"] call comspec_atak_native_fnc_pageRender; };
    }] call _click;
    _y = _y + _h + _pad * 0.4;
    if (_open && {_sub isNotEqualTo ""}) then {
        private _c = [[_pad, _y, _cw - 2 * _pad, _font], format ["<t size='%1' color='%2'>%3</t>", _zS, _greyS, _sub], true] call _txt;
        _y = _y + ((ctrlPosition _c) select 3) + _pad * 0.3;
    };
    _open
};
// Ligne libellé / valeur : deux colonnes alignées (libellé 44 %, valeur 56 % alignée à droite), filet dessous.
private _info = {
    params ["_label", "_value"];
    private _ih = _font * 1.35;
    private _a = [[_pad, _y, (_cw - 2 * _pad) * 0.44, _ih], format ["<t size='%1' color='%2'>%3</t>", _zL, _greyL, _label], true] call _txt;
    private _b = [[_pad + (_cw - 2 * _pad) * 0.44, _y, (_cw - 2 * _pad) * 0.56, _ih], format ["<t size='%1' align='right'>%2</t>", _zV, _value], true] call _txt;
    _y = _y + (((ctrlPosition _a) select 3) max ((ctrlPosition _b) select 3)) + _pad * 0.25;
    [[_pad, _y - _pad * 0.12, _cw - 2 * _pad, _lh * 0.5], [1, 1, 1, 0.06]] call _rect;
};
// Paragraphe (explication en gris par défaut).
private _para = {
    params ["_s2", ["_grey", true]];
    private _c = [[_pad, _y, _cw - 2 * _pad, _font * 1.2], format ["<t size='%1' color='%2'>%3</t>", [_zV, _zS] select _grey, ["#e8eadf", _greyS] select _grey, _s2], true] call _txt;
    _y = _y + ((ctrlPosition _c) select 3) + _pad * 0.35;
};
// Boutons : plus hauts qu'avant (doigt, souris), 2 par rangée sur un écran étroit, 4 sur un écran large ; libellé trop
// long : la police rétrécit (jamais sous le plancher) au lieu de couper le texte.
private _btnH = _font * 1.95;
private _perRow = [2, 4] select (_cw > _font * 30);
private _buttons = {
    params ["_btns"];
    while { _btns isNotEqualTo [] } do {
        private _row = _btns select [0, _perRow];
        _btns = _btns select [_perRow];
        private _n = count _row;
        private _w1 = ((_cw - 2 * _pad) - (_n - 1) * _pad * 0.5) / (_n max 1);
        {
            _x params ["_label", "_code", ["_primary", false]];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, [_pad + _forEachIndex * (_w1 + _pad * 0.5), _y, _w1, _btnH], _label] call _mk;
            private _bf = (_font * 0.92) max COMSPEC_ATAK_FontMin;
            _b ctrlSetFontHeight _bf;
            private _tw = ctrlTextWidth _b;
            if (_tw > _w1 * 0.9) then { _b ctrlSetFontHeight ((_bf * _w1 * 0.9 / _tw) max COMSPEC_ATAK_FontMin); };
            [_b, _code] call _click;
        } forEach _row;
        _y = _y + _btnH + _pad * 0.5;
    };
};
// Ajuste à la hauteur finale les fonds d'une carte (créés avant son texte : l'ordre de création fait l'empilement).
// Éléments : [contrôle, "h" (hauteur) | "bot" (filet du bas) | "gauge" (jauge en bas)].
private _fixH = {
    params ["_fix", "_y0", "_h"];
    {
        _x params ["_c", "_k2"];
        switch (_k2) do {
            case "h": { _c ctrlSetPositionH _h; };
            case "bot": { _c ctrlSetPositionY (_y0 + _h - _lh); };
            case "gauge": { _c ctrlSetPositionY (_y0 + _h - _font * 0.45); };
        };
        _c ctrlCommit 0;
    } forEach _fix;
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
private _only = ["only"] call comspec_atak_native_fnc_relayNet;
private _req = ["required"] call comspec_atak_native_fnc_relayNet;
private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
private _hops = (_mesh getOrDefault ["hops", [-1]]) param [_mesh getOrDefault ["me", 0], -1];
private _term = [player] call comspec_atak_native_fnc_vehicleTerminal;
private _activeObj = _best param [0, objNull];
// Liaison réaliste téléphone <-> radio de données (fn_mpuLink) : le porteur est la radio reliée au téléphone.
private _mpuS = ["status"] call comspec_atak_native_fnc_mpuLink;
private _mpuOn = (_mpuS get "active") && {(_mpuS get "kind") isNotEqualTo "veh"};
private _mpuReq = missionNamespace getVariable ["comspec_atak_native_mpu_require", true];
private _kbTxt = { params ["_kb"]; if (_kb >= 1000) then { format ["%1 Mbit/s", (_kb / 1000) toFixed 1] } else { format ["%1 kbit/s", round _kb] } };
private _bearer = switch (true) do {
    case (_mpuOn && {(_mpuS get "ok") || {_mpuReq} || {(_mpuS get "kind") isNotEqualTo ""}}): {
        private _kd = _mpuS get "kind";
        private _lk = _mpuS get "link";
        private _ms = uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap];
        [["Radio de données", "MPU5 · Wave Relay"] select (_kd isEqualTo "mpu"),
            switch (_lk) do {
                case "cable": { format ["Câble EUD · canal %1", _ms getOrDefault ["chan", 1]] };
                case "wifi": { format ["Wi-Fi · canal %1", _ms getOrDefault ["chan", 1]] };
                default { "Non reliée" };
            },
            [[_mpuS get "why"] call _esc, format ["%1 saut(s) jusqu'à %2 · %3 · %4 ms", (_mpuS get "hops") max 0, [_mpuS get "gw"] call _esc, [_mpuS get "kbps"] call _kbTxt, _mpuS get "lat"]] select (_mpuS get "ok"),
            [["bad", "warn"] select ((_mpuS get "why") isEqualTo "connexion au maillage en cours"), ["ok", "warn"] select ((_mpuS get "kbps") < 900)] select (_mpuS get "ok"),
            [0, [1, 2, 3, 4] select (({ (_mpuS get "kbps") >= _x } count [250, 900, 2200]))] select (_mpuS get "ok"),
            "\z\comspec_atak_native\addons\main\data\app_waverelay.paa"]
    };
    case (_best isNotEqualTo []): {
        _best params ["_o", "_n", "_d", "_mb", "_ls", "_k", "_eff", "_u", "_sl", "_bt", ["_fq", 0], ["_hl", ""]];
        private _extra = [];
        if (_sl > 0) then { _extra pushBack format ["%1/%2 places", _u, _sl]; };
        if (_bt >= 0) then { _extra pushBack format ["batterie %1 %%", round _bt]; };
        if (_fq > 0) then { _extra pushBack format ["%1 MHz", _fq]; };
        _extra pushBack format ["perte %1 %%", _ls];
        if (_hl in ["dégradé", "critique"]) then { _extra pushBack _hl; };
        [createHashMapFromArray [["relay", "Relais radio"], ["gw", "Passerelle véhicule"], ["cp", "Poste de commandement"], ["mpu5", "MPU5 d'un équipier"]] getOrDefault [_k, "Relais radio"], [_n] call _esc,
            format ["%1 / %2 · %3 Mbit/s%4", [_d] call _dist, [_eff] call _dist, _mb toFixed 1, ["", " · " + (_extra joinString " · ")] select (_extra isNotEqualTo [])],
            ["ok", "warn"] select (_sl > 0 && {_u > _sl} || {_bt >= 0 && {_bt < 20}} || {_hl in ["dégradé", "critique"]}), [1 - (_d / (_eff max 1)) ^ 2] call _qBars,
            ["\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "\z\comspec_atak_native\addons\main\data\app_c2.paa"] select (_k isEqualTo "cp")]
    };
    case (_only): {
        ["Aucun relais", "Réseau uniquement par les relais", "aucun relais reçu : aucun réseau", "bad", 0, "\z\comspec_atak_native\addons\main\data\app_waverelay.paa"]
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
private _linkTxt = createHashMapFromArray [["linked", "Connecté"], ["degraded", "Dégradé"], ["connecting", "Connexion…"], ["offline", "Hors ligne"]] getOrDefault [_link, "Hors ligne"];
_nodes pushBack [["Extension native", "COMSPEC Link"] select _bridge, _linkTxt,
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
        case (_mpuOn && {_mpuReq} && {!(_mpuS get "ok")}): { toLower (_mpuS get "why") };
        case ((_bearer select 3) isEqualTo "bad"): { "aucun porteur à portée" };
        default { "" };
    },
    ["", "Athena hors ligne"] select (_linkSt isEqualTo "bad"),
    ["", "pas de réponse d'Athena"] select (_athSt isEqualTo "bad")
];
private _broken = -1;
{ if (_x isNotEqualTo "" && {_broken < 0}) then { _broken = _forEachIndex; }; } forEach _hopWhy;
if (_broken >= 0) then { for "_i" from (_broken + 2) to 3 do { (_nodes select _i) set [3, "off"]; }; };

private _pathSum = if (_broken >= 0) then { format ["<t color='#e5483a'>coupure : %1</t>", _hopWhy select _broken] } else { "<t color='#5cc76b'>liaison complète</t>" };
if (["path", "Chemin de la liaison", _pathSum, "Du téléphone à Athena : chaque saut, son état"] call _fold) then {
    // Cartes : 4 en ligne sur un écran très large, 2 par rangée sur un écran moyen, empilées sinon (sauts verticaux).
    private _nCol = switch (true) do { case (_cw > _font * 56): { 4 }; case (_cw > _font * 30): { 2 }; default { 1 }; };
    // Carte d'un saut : fond, liseré d'état, icône, titre / valeur / détail, barres. Renvoie [hauteur, éléments à
    // ajuster] : la carte prend la hauteur de son texte (plus de texte rogné en bas).
    private _card = {
        params ["_pos", "_node", ["_active", false]];
        _pos params ["_x0", "_y0", "_w0", "_h0"];
        _node params ["_title", "_main", "_sub", "_st", "_nb", "_icon"];
        private _fix = [];
        _fix pushBack [[_pos, [[0.07, 0.085, 0.078, 0.96], [0.09, 0.12, 0.10, 0.98]] select _active] call _rect, "h"];
        _fix pushBack [[[_x0, _y0, _lw * 2, _h0], _col get _st] call _rect, "h"];
        if (_active) then {
            [[_x0, _y0, _w0, _lh], _acc] call _rect;
            _fix pushBack [[[_x0, _y0 + _h0 - _lh, _w0, _lh], _acc] call _rect, "bot"];
            _fix pushBack [[[_x0 + _w0 - _lw, _y0, _lw, _h0], _acc] call _rect, "h"];
        };
        private _icH = _font * 1.5;
        private _ic = ["COMSPEC_RscPicture", [_x0 + _pad * 0.6, _y0 + _pad * 0.5, _icH / _ratio, _icH], _icon] call _mk;
        _ic ctrlSetTextColor ([[1, 1, 1, 0.9], [1, 1, 1, 0.3]] select (_st isEqualTo "off"));
        _ic ctrlEnable false;
        private _tx = _x0 + _pad * 1.1 + _icH / _ratio;
        private _barH = _font * 0.9;
        private _barW = _barH * 0.22 / _ratio * 1.45 * 4;
        private _t = [[_tx, _y0 + _pad * 0.3, _x0 + _w0 - _tx - _barW - _pad * 0.9, _h0 - _pad * 0.6], format ["<t size='%1' font='COMSPEC_BarlowBold' color='%2'>%3</t>%4<br/><t size='%5' font='COMSPEC_BarlowBold' color='%6'>%7</t><br/><t size='%8' color='%9'>%10</t>",
            _zS, _hex get _st, toUpper ((_title) call comspec_atak_native_fnc_tr), ["", format ["<t size='%1' color='%2'>  ● ACTIF</t>", _zS, _accHex]] select _active,
            1.1, ["#e8eadf", "#6d7a74"] select (_st isEqualTo "off"), _main, _zS, _greyS, _sub], true] call _txt;
        [_x0 + _w0 - _barW - _pad * 0.5, _y0 + _pad * 0.5, _barH, [_nb, 0] select (_st isEqualTo "off"), [_st, "ok"] select (_st isEqualTo "off")] call _bars;
        [_h0 max (((ctrlPosition _t) select 3) + _pad * 0.6), _fix]
    };
    // État du saut entre deux cartes (raison de la coupure, en attente, ou état de la carte suivante).
    private _hopSt = {
        params ["_i"];
        private _why = _hopWhy select _i;
        switch (true) do { case (_why isNotEqualTo ""): { "bad" }; case (_broken >= 0 && {_i > _broken}): { "off" }; default { ((_nodes select (_i + 1)) select 3) }; }
    };
    if (_nCol > 1) then {
        // Rangées de cartes, sauts horizontaux entre deux cartes d'une même rangée ; la coupure est écrite dessous.
        private _gap = _font * 1.4;
        private _w1 = ((_cw - 2 * _pad) - (_nCol - 1) * _gap) / _nCol;
        private _h1 = _font * 4.2;
        for "_r" from 0 to (ceil (4 / _nCol)) - 1 do {
            private _hRow = _h1;
            private _fixes = [];
            for "_c" from 0 to (_nCol - 1) do {
                private _i = _r * _nCol + _c;
                if (_i < 4) then {
                    private _node = _nodes select _i;
                    ([[_pad + _c * (_w1 + _gap), _y, _w1, _h1], _node, _i isEqualTo 1 && {(_node select 3) isNotEqualTo "bad"}] call _card) params ["_hc", "_fx"];
                    _hRow = _hRow max _hc;
                    _fixes append _fx;
                };
            };
            [_fixes, _y, _hRow] call _fixH;
            for "_c" from 0 to (_nCol - 2) do {
                private _i = _r * _nCol + _c;
                if (_i < 3) then {
                    private _st = [_i] call _hopSt;
                    private _gx = _pad + _c * (_w1 + _gap) + _w1;
                    [[_gx, _y + _hRow / 2 - _lh, _gap, _lh * 2], _col get _st] call _rect;
                    if ((_hopWhy select _i) isNotEqualTo "") then { [[_gx + _gap / 2 - _lw * 3, _y + _hRow / 2 - _lh * 3, _lw * 6, _lh * 6], _col get "bad"] call _rect; };
                };
            };
            _y = _y + _hRow + _pad * 0.5;
        };
        private _whyAll = (_hopWhy select { _x isNotEqualTo "" });
        if ((count _whyAll) > 0) then { [format ["<t color='#e5483a'>✗ Coupure : %1</t>", _whyAll select 0], false] call _para; };
    } else {
        // Portrait / mini : cartes empilées, saut vertical avec sa raison.
        private _h1 = _font * 3.6;
        private _gapH = _font * 1.45;
        {
            ([[_pad, _y, _cw - 2 * _pad, _h1], _x, _forEachIndex isEqualTo 1 && {(_x select 3) isNotEqualTo "bad"}] call _card) params ["_hc", "_fx"];
            [_fx, _y, _hc] call _fixH;
            _y = _y + _hc;
            if (_forEachIndex < 3) then {
                private _why = _hopWhy select _forEachIndex;
                private _st = [_forEachIndex] call _hopSt;
                private _lx = _pad + _font * 1.2;
                private _c = [[_lx + _font * 0.6, _y, _cw - _lx - _font * 0.6 - _pad, _gapH], format ["<t size='%1' color='%2'>%3</t>", _zS, _hex get _st,
                    [["▼", "▼ en attente"] select (_st isEqualTo "off"), "✗ " + _why] select (_why isNotEqualTo "")], true] call _txt;
                private _gh = _gapH max (((ctrlPosition _c) select 3) + _pad * 0.2);
                [[_lx, _y, _lw * 2, _gh], _col get _st] call _rect;
                _y = _y + _gh;
            };
        } forEach _nodes;
        _y = _y + _pad * 0.3;
    };
};

// --- Radio de données (liaison réaliste téléphone <-> radio, fn_mpuLink) : état, réglages et maillage ---
if (_mpuOn) then {
    private _v = ["view"] call comspec_atak_native_fnc_mpuLink;
    private _lk = _v get "link";
    private _mpuSum = if (_mpuS get "ok") then { format ["<t color='#5cc76b'>%1</t>", [_mpuS get "kbps"] call _kbTxt] } else { format ["<t color='#f2ab33'>%1</t>", [_mpuS get "why"] call _esc] };
    if (["mpu", "Radio de données", _mpuSum, ["MPU5 Wave Relay : elle améliore le réseau du téléphone", "MPU5 Wave Relay : le téléphone n'a de réseau qu'à travers elle. Sans MPU5 : pas de réseau de données"] select _mpuReq] call _fold) then {
        ["Radio", switch (true) do {
            case (_v get "carried"): { format ["MPU5 sur vous · <t color='%1'>%2</t>", ["#e5483a", "#5cc76b"] select (_v get "on"), ["éteinte", "allumée"] select (_v get "on")] };
            case (_v get "posed"): { format ["MPU5 posée à %1 m · <t color='%2'>%3</t>", _v get "posedD", ["#e5483a", "#5cc76b"] select (_v get "on"), ["éteinte", "allumée"] select (_v get "on")] };
            default { "<t color='#e5483a'>aucune MPU5 sur vous</t>" };
        }] call _info;
        ["Liaison au téléphone", switch (_lk) do {
            case "cable": { "<t color='#5cc76b'>câble EUD branché</t>" };
            case "wifi": { private _d = _mpuS get "wifiD"; format ["<t color='%1'>Wi-Fi</t> · %2 m / %3 m", ["#e5483a", "#5cc76b"] select ((_mpuS get "wifiQ") > 0), round (_d max 0), round (_v get "wifiR")] };
            default { "<t color='#e5483a'>non reliée</t>" };
        }] call _info;
        if (_lk isEqualTo "wifi") then { ["Qualité du Wi-Fi", format ["%1 %%", round (100 * (_mpuS get "wifiQ"))]] call _info; };
        if (_lk isEqualTo "" && {_v get "cableReq"} && {!(_v get "cable")}) then { ["Câble EUD", "<t color='#f2ab33'>absent</t>"] call _info; };
        ["Canal du maillage", format ["Canal %1 · %2 MHz", _v get "chan", _v get "freq"]] call _info;
        ["Puissance", format ["%1 · portée %2", _v get "pwrLabel", [_v get "range"] call _dist]] call _info;
        ["Batterie de la radio", format ["<t color='%1'>%2 %%</t> · environ %3 h %4", switch (true) do { case ((_v get "batt") <= 5): { "#e5483a" }; case ((_v get "batt") <= 20): { "#f2ab33" }; default { "#5cc76b" }; },
            _v get "batt", floor ((_v get "left") / 60), (_v get "left") mod 60]] call _info;
        ["Batterie de rechange", ["aucune", "sur vous"] select (_v get "spare")] call _info;
        ["Maillage", if (_mpuS get "ok") then {
            format ["<t color='#5cc76b'>%1</t>", switch (_mpuS get "mode") do {
                case "self": { "maillage autonome" };
                case "lead": { format ["%1 saut(s) jusqu'au chef de groupe %2", _mpuS get "hops", [_mpuS get "gw"] call _esc] };
                default { format ["%1 saut(s) jusqu'à %2", _mpuS get "hops", [_mpuS get "gw"] call _esc] };
            }]
        } else { format ["<t color='%1'>%2</t>", ["#e5483a", "#f2ab33"] select ((_mpuS get "why") isEqualTo "connexion au maillage en cours"), [_mpuS get "why"] call _esc] }] call _info;
        if (_mpuS get "ok") then {
            if ((_mpuS get "via") isNotEqualTo "" && {(_mpuS get "hops") > 1}) then { ["Par", [_mpuS get "via"] call _esc] call _info; };
            ["Débit estimé", [_mpuS get "kbps"] call _kbTxt] call _info;
            ["Latence · perte", format ["%1 ms · %2 %%", _mpuS get "lat", _mpuS get "loss"]] call _info;
        };
        [format ["Même canal = même maillage.%1", ["", " Aucun relais dans la mission : les MPU5 forment leur propre maillage."] select ((_mpuS get "mode") isEqualTo "self")]] call _para;
        // Nœuds du maillage (MPU5 du même canal, radios posées, relais), les plus proches en sauts d'abord.
        private _nl = _mpuS getOrDefault ["nodes", []];
        if (_nl isNotEqualTo []) then {
            [format ["<t font='COMSPEC_BarlowBold' color='#e8eadf'>Nœuds du maillage (%1)</t>", count _nl], false] call _para;
            {
                _x params ["_nm", "_nk", "_nh", "_nd", "_nb"];
                [format ["<t color='%1'>●</t> %2", ["#e5483a", "#5cc76b"] select (_nh >= 0), [_nm] call _esc],
                    format ["%1 · %2<br/><t size='%3' color='%4'>%5</t>", createHashMapFromArray [["node", "MPU5"], ["posed", "MPU5 posée"], ["gw", "relais"]] getOrDefault [_nk, "MPU5"], [_nd] call _dist, _zS, _greyS,
                        [format ["%1 saut(s) · %2", _nh, [_nb] call _kbTxt], "<t color='#e5483a'>hors maillage</t>"] select (_nh < 0)]] call _info;
            } forEach _nl;
        } else {
            if (_lk in ["cable", "wifi"] && {_mpuS get "ok"}) then { ["Aucun autre nœud sur ce canal : montez sur un point haut ou rapprochez-vous d'une MPU5 ou d'un relais."] call _para; };
        };
        _y = _y + _pad * 0.3;
        if (_v get "carried" || {_v get "posed"}) then {
            [[
                [["ALLUMER", "ÉTEINDRE"] select (_v get "on"), { ["power"] call comspec_atak_native_fnc_mpuLink; }, !(_v get "on")],
                ["PUISSANCE", { ["txpower"] call comspec_atak_native_fnc_mpuLink; }],
                [format ["CANAL %1", toUpper (_v getOrDefault ["width", "Normal"])], { ["width"] call comspec_atak_native_fnc_mpuLink; }],
                ["CANAL −", { ["chan", [-1]] call comspec_atak_native_fnc_mpuLink; }],
                ["CANAL +", { ["chan", [1]] call comspec_atak_native_fnc_mpuLink; }]
            ]] call _buttons;
        };
        private _btn = [];
        if (_lk isNotEqualTo "cable" && {_v get "carried"}) then { _btn pushBack ["CÂBLE EUD", { ["link", ["cable"]] call comspec_atak_native_fnc_mpuLink; }, _lk isEqualTo ""]; };
        if (_lk isNotEqualTo "wifi" && {_v get "wifi"}) then { _btn pushBack ["WI-FI", { ["link", ["wifi"]] call comspec_atak_native_fnc_mpuLink; }]; };
        if (_lk isNotEqualTo "") then { _btn pushBack ["DÉBRANCHER", { ["unlink"] call comspec_atak_native_fnc_mpuLink; }]; };
        if ((_v get "posed") && {missionNamespace getVariable ["comspec_atak_native_mesh_mast", true]}) then { _btn pushBack ["MÂT", { ["mast"] call comspec_atak_native_fnc_mpuLink; }]; };
        if ((_v get "spare") && {(_v get "batt") < 95}) then { _btn pushBack ["BATTERIE", { ["battery"] call comspec_atak_native_fnc_mpuLink; }]; };
        if (_btn isNotEqualTo []) then { [_btn] call _buttons; };
    };
};

// --- Voix ACRE2 / TFAR relayée par le maillage MPU5 (fn_mpuVoice) : pont radio, état, correspondants joignables ---
if ((_mpuS get "active") && {missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]}) then {
    private _vv = ["view"] call comspec_atak_native_fnc_mpuVoice;
    private _vb = _vv get "bridge";
    private _vSum = switch (true) do {
        case (_vb isEqualTo []): { "aucune radio reliée" };
        case (_vv get "ok"): { "<t color='#5cc76b'>voix relayée</t>" };
        default { "<t color='#f2ab33'>portée radio normale</t>" };
    };
    if (["voice", "Voix radio par le maillage", _vSum, "Radio ACRE2 / TFAR reliée à la MPU5 par câble audio : la voix passe par le maillage jusqu'aux radios reliées des équipiers, sur la même fréquence"] call _fold) then {
        if (_vb isEqualTo []) then {
            ["Pont radio", format ["<t color='%1'>aucune radio reliée à la MPU5</t>", _greyS]] call _info;
        } else {
            ["Radio pontée", [_vb select 2] call _esc] call _info;
            if (_vv get "ok") then {
                ["Voix", format ["<t color='#5cc76b'>relayée par le maillage</t> · %1 relais", _vv get "hops"]] call _info;
            } else {
                ["Voix", format ["<t color='#f2ab33'>%1 : portée radio normale</t>", [_vv get "why"] call _esc]] call _info;
            };
            if (_vv get "ok") then {
                private _pl = _vv get "peers";
                if (_pl isEqualTo []) then {
                    ["Aucun correspondant ponté joignable pour l'instant : il faut une radio reliée à une MPU5 de l'autre côté aussi."] call _para;
                } else {
                    [format ["<t font='COMSPEC_BarlowBold' color='#e8eadf'>Correspondants pontés joignables (%1)</t>", count _pl], false] call _para;
                    {
                        _x params ["_pn", "_ph", "_pd", "_ps"];
                        [format ["<t color='#5cc76b'>●</t> %1", [_pn] call _esc], format ["%1 saut(s) · %2%3", _ph, [_pd] call _dist, ["", "<br/><t color='#f2ab33'>autre fréquence</t>"] select !_ps]] call _info;
                    } forEach (_pl select [0, 8]);
                    if ((_vv get "tfMult") > 0) then { ["Portée TFAR", format ["réception allongée %1 fois", round (1 / ((_vv get "tfMult") max 0.01))]] call _info; };
                };
            };
        };
        _y = _y + _pad * 0.3;
        private _vbt = [];
        if (_vv get "carried") then {
            {
                _x params ["_rk", "", "_rn"];
                if (_vb isEqualTo [] || {(_vb select 0) isNotEqualTo _rk}) then {
                    _vbt pushBack [format ["RELIER %1", toUpper (_rn select [0, 14])], format ['["bridge", [%1]] call comspec_atak_native_fnc_mpuVoice;', str _rk], _vb isEqualTo []];
                };
            } forEach ((_vv get "radios") select [0, 3]);
        };
        if (_vb isNotEqualTo []) then { _vbt pushBack ["DÉBRANCHER LA RADIO", { ["unbridge"] call comspec_atak_native_fnc_mpuVoice; }]; };
        if (!(_vv get "carried") && {_vb isEqualTo []}) then { ["Portez une MPU5 pour y relier votre radio."] call _para; };
        if (_vbt isNotEqualTo []) then { [_vbt] call _buttons; };
    };
};

// --- Indicateurs ---
// Stabilité (0-100) : perte de paquets (mesurée et simulée), latence, état de COMSPEC Link et réponses d'Athena.
// Sans aucune mesure (rien envoyé, latence inconnue, liaison pas encore établie) : « — », pas un chiffre inventé.
private _lossAll = _loss max ([0, _lq get "loss"] select _sim);
private _stab = -1;
private _stabLabel = "pas encore de mesure";
switch (true) do {
    case (_air || {_linkSt isEqualTo "bad"} || {(_lq get "kbps") <= 0}): { _stab = 0; _stabLabel = "liaison coupée"; };
    case (_sent <= 0 && {_lat < 0} && {_link isNotEqualTo "linked"}): {};
    default {
        _stab = 100 - ((_lossAll * 2.5) min 60) - ([0, ((_lat - 150) / 15) min 30] select (_lat > 150))
            - ([0, 20] select (_linkSt isEqualTo "warn")) - ([0, 15, 40] select ((["warn", "bad"] find _athSt) + 1));
        _stab = round ((_stab max 0) min 100);
        _stabLabel = switch (true) do { case (_stab >= 85): { "stable" }; case (_stab >= 70): { "correcte" }; case (_stab >= 40): { "instable" }; default { "très instable" }; };
    };
};
private _stSt = switch (true) do { case (_stab < 0): { "off" }; case (_stab >= 70): { "ok" }; case (_stab >= 40): { "warn" }; default { "bad" }; };
if (["ind", "Indicateurs", format ["<t color='%1'>%2</t>", [_hex get _stSt, "#c9d4ce"] select (_stSt isEqualTo "off"), [format ["stabilité %1 %%", _stab], _stabLabel] select (_stab < 0)]] call _fold) then {
    private _cols = [2, 3] select (_cw > _font * 30);
    private _tw = ((_cw - 2 * _pad) - (_cols - 1) * _pad * 0.5) / _cols;
    private _th = _font * 3.4;
    private _tiles = [
        ["Stabilité", [format ["%1 %%", _stab], "—"] select (_stab < 0), _stabLabel, _stSt, [_stab / 100, -1] select (_stab < 0)],
        ["Latence", [format ["%1 ms", round _lat], "—"] select (_lat < 0), ["non mesurée", ""] select (_lat >= 0), switch (true) do { case (_lat < 0): { "off" }; case (_lat < 150): { "ok" }; case (_lat < 400): { "warn" }; default { "bad" }; }, -1],
        ["Perte de paquets", format ["%1 %%", round _loss], "", switch (true) do { case (_loss < 5): { "ok" }; case (_loss < 20): { "warn" }; default { "bad" }; }, -1],
        ["Paquets envoyés", [str _sent, _sent toFixed 0] select (_sent isEqualType 0), "", "off", -1],
        ["Dernière position", [["COMSPEC_LastPositionSync"] call _ageS] call _ageTxt, "envoyée à Athena", switch (true) do { case ((["COMSPEC_LastPositionSync"] call _ageS) < 0): { "bad" }; case ((["COMSPEC_LastPositionSync"] call _ageS) < 60): { "ok" }; default { "warn" }; }, -1],
        ["Réponse Athena", _hTxt, "", _athSt, -1]
    ];
    // Tuiles d'une rangée à la hauteur de la plus haute (valeur en grand, détail sur sa propre ligne).
    private _hRow = _th;
    private _fixes = [];
    {
        _x params ["_lbl", "_val", "_sub", "_st", "_gauge"];
        private _ci = _forEachIndex mod _cols;
        if (_ci isEqualTo 0 && {_forEachIndex > 0}) then {
            [_fixes, _y, _hRow] call _fixH;
            _y = _y + _hRow + _pad * 0.5;
            _hRow = _th; _fixes = [];
        };
        private _tx = _pad + _ci * (_tw + _pad * 0.5);
        _fixes pushBack [[[_tx, _y, _tw, _th], [0.07, 0.085, 0.078, 0.96]] call _rect, "h"];
        private _t = [[_tx + _pad * 0.5, _y + _pad * 0.3, _tw - _pad, _th - _pad], format ["<t size='%1' color='%2'>%3</t><br/><t size='1.4' font='COMSPEC_BarlowBold' color='%4'>%5</t>%6",
            _zS, _greyL, toUpper ((_lbl) call comspec_atak_native_fnc_tr), [_hex get _st, "#e8eadf"] select (_st isEqualTo "off"), _val,
            ["", format ["<br/><t size='%1' color='%2'>%3</t>", _zS, _greyS, _sub]] select (_sub isNotEqualTo "")], true] call _txt;
        _hRow = _hRow max (((ctrlPosition _t) select 3) + _pad * 0.6 + ([0, _font * 0.5] select (_gauge >= 0)));
        if (_gauge >= 0) then {
            private _gw2 = _tw - _pad;
            _fixes pushBack [[[_tx + _pad * 0.5, _y + _th - _font * 0.45, _gw2, _font * 0.22], [0.2, 0.24, 0.22, 1]] call _rect, "gauge"];
            _fixes pushBack [[[_tx + _pad * 0.5, _y + _th - _font * 0.45, (_gw2 * (_gauge min 1)) max _lw, _font * 0.22], _col get _st] call _rect, "gauge"];
        };
    } forEach _tiles;
    [_fixes, _y, _hRow] call _fixH;
    _y = _y + _hRow + _pad * 0.5;
};

// --- Passerelles, relais et postes de commandement proches ---
private _sub = switch (true) do {
    case (_mpuOn): { "Liaison réaliste : le téléphone passe par sa radio de données, les relais sont des passerelles du maillage" };
    case (_only): { "Réseau exclusivement par relais : sans relais, aucun réseau" };
    case (_req): { "Réaliste : relais obligatoire" };
    default { "Arcade : le téléphone passe sans relais" };
};
private _items = [];
{
    _x params ["_o", "_ant", "_n", "_k", "_r", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "", "", "_fq", "_auto", "_loss", "_hp", "_hl", "_hops", "_co", "_lat"];
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
    // Lignes 3 et 4 : fréquence, perte, latence ; santé et structure du relais.
    private _hc = createHashMapFromArray [["nominal", "#5cd96b"], ["dégradé", "#f2ab33"], ["critique", "#e5483a"], ["hors service", "#8a9a93"]];
    private _health = format ["%1 MHz%2 · perte %3 %% · %4 ms<br/><t color='%5'>%6</t> · structure %7 %%%8", _fq, ["", " auto"] select _auto,
        _loss, _lat, _hc getOrDefault [_hl, "#8a9a93"], _hl, _hp, ["", format [" · <t color='#f2ab33'>%1 sur le canal</t>", _co]] select (_co > 0)];
    _items pushBack [_d, createHashMapFromArray [["relay", "Relais radio"], ["gw", "Passerelle véhicule"], ["cp", "Poste de commandement"], ["mpu5", "MPU5 d'un équipier"]] getOrDefault [_k, "Relais radio"], _n, _r, _eff, _rate, _o isEqualTo _activeObj, _why,
        ["\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "\z\comspec_atak_native\addons\main\data\app_c2.paa"] select (_k isEqualTo "cp"), _health];
} forEach (["list"] call comspec_atak_native_fnc_relayNet);
_items sort true;
private _onItem = _items findIf { _x select 6 };
private _relSum = switch (true) do {
    case ((count _items) isEqualTo 0): { "aucun" };
    case (_onItem >= 0): { format ["<t color='#5cc76b'>%1</t>", [(_items select _onItem) select 2] call _esc] };
    default { format ["%1 déclaré(s)", count _items] };
};
if (["relay", "Passerelles et relais", _relSum, _sub] call _fold) then {
    if ((count _items) isEqualTo 0) then {
        ["Aucune passerelle de véhicule, aucun poste de commandement ni relais déclaré pour votre camp."] call _para;
    };
    private _rowH = _font * 3.4;
    {
        _x params ["_d", "_type", "_name", "_r", "_eff", "_rate", "_on", "_why", "_icon", ["_health", ""]];
        private _q = [0, 1 - (_d / (_eff max 1)) ^ 2] select (_d < _eff);
        private _st = switch (true) do { case (_on): { "ok" }; case (_why in ["détruit", "éteint", "batterie vide", "brouillé", "sans liaison"]): { "bad" }; case (_q > 0): { "warn" }; default { "off" }; };
        private _x0 = _pad;
        private _w0 = _cw - 2 * _pad;
        private _fix = [];
        _fix pushBack [[[_x0, _y, _w0, _rowH], [[0.06, 0.072, 0.066, 0.96], [0.09, 0.12, 0.10, 0.98]] select _on] call _rect, "h"];
        if (_on) then { _fix pushBack [[[_x0, _y, _lw * 2, _rowH], _acc] call _rect, "h"]; };
        private _icH = _font * 1.5;
        private _ic = ["COMSPEC_RscPicture", [_x0 + _pad * 0.6, _y + _pad * 0.5, _icH / _ratio, _icH], _icon] call _mk;
        _ic ctrlSetTextColor ([[1, 1, 1, 0.45], [1, 1, 1, 1]] select (_on || {_q > 0}));
        _ic ctrlEnable false;
        // À droite : barres de signal, puis CONNECTÉ ou la raison sous les barres.
        private _rightW = (_font * 6) min (_w0 * 0.34);
        private _rx = _x0 + _w0 - _rightW - _pad * 0.5;
        [_rx, _y + _pad * 0.5, _font * 0.9, [_q] call _qBars, [_st, "ok"] select (_st isEqualTo "off")] call _bars;
        private _tx = _x0 + _pad * 1.2 + _icH / _ratio;
        private _t = [[_tx, _y + _pad * 0.3, _rx - _tx - _pad * 0.5, _rowH - _pad * 0.6], format ["<t size='%1' font='COMSPEC_BarlowBold'>%2</t><br/><t size='%3' color='%4'>%5</t><br/><t size='%3' color='%6'>%7 · portée %8 · %9</t><br/><t size='%3' color='%4'>%10</t>",
            1.05, [_name] call _esc, _zS, _greyS, _type, "#c9d4ce", [_d] call _dist, [_eff] call _dist, _rate, _health], true] call _txt;
        private _wy = _y + _pad * 0.5 + _font * 1.15;
        private _wt = if (_on) then {
            private _bd = ["COMSPEC_RscTextCenter", [_rx, _wy, _rightW, _font * 1.35], "CONNECTÉ"] call _mk;
            _bd ctrlEnable false;
            _bd ctrlSetBackgroundColor _acc;
            _bd ctrlSetTextColor [0.02, 0.03, 0.02, 1];
            _bd ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _bd ctrlSetFontHeight ((_font * 0.85) max COMSPEC_ATAK_FontMin);
            _font * 1.35
        } else {
            private _wc = [[_rx, _wy, _rightW, _font * 1.2], format ["<t align='right' size='%1' color='%2'>%3</t>", _zS, _hex get _st, _why], true] call _txt;
            (ctrlPosition _wc) select 3
        };
        private _h = _rowH max (((ctrlPosition _t) select 3) + _pad * 0.6) max (_wy - _y + _wt + _pad * 0.4);
        [_fix, _y, _h] call _fixH;
        _y = _y + _h + _pad * 0.35;
    } forEach (_items select [0, 10]);
    if ((_mesh getOrDefault ["gwLabel", ""]) isNotEqualTo "") then {
        _y = _y + _pad * 0.2;
        ["Maillage Wave Relay", format ["%1 · %2", [_mesh get "gwLabel"] call _esc, switch (true) do { case (_hops < 0): { "<t color='#e5483a'>coupé</t>" }; case (_hops <= 1): { "en direct" }; default { format ["%1 sauts", _hops] }; }]] call _info;
    } else {
        if (_hops >= 0) then { _y = _y + _pad * 0.2; ["Maillage Wave Relay", format ["<t color='%1'>aucune passerelle</t>", _greyS]] call _info; };
    };
};

// --- Zone radio et canal (COMSPEC Overwatch) ---
if (_bridge) then {
    if (["zone", "Zone radio", [[_zone getOrDefault ["name", "Zone"]] call _esc, "couverture normale"] select ((count _zone) isEqualTo 0)] call _fold) then {
        if ((count _zone) isEqualTo 0) then { ["Couverture normale."] call _para; } else {
            ["Zone", format ["%1%2", [_zone getOrDefault ["name", "Zone"]] call _esc, ["", format [" (%1)", _zone getOrDefault ["type", ""]]] select ((_zone getOrDefault ["type", ""]) isNotEqualTo "")]] call _info;
            ["Perte minimale", format ["%1 %%", _zone getOrDefault ["packet_loss_floor", 0]]] call _info;
            ["Latence ajoutée", format ["+%1 ms", _zone getOrDefault ["latency_add", 0]]] call _info;
        };
        // Fonctions d'Overwatch absentes d'une version plus ancienne : la page continue sans elles.
        if (!isNil "comspec_overwatch_connect_fnc_isLinkDegradeSimActive") then {
            ["Liaison instable simulée", [format ["<t color='%1'>désactivée</t>", _greyS], "<t color='#f2ab33'>active : pertes et délais simulés</t>"] select ([] call comspec_overwatch_connect_fnc_isLinkDegradeSimActive)] call _info;
        };
        if (_best isNotEqualTo []) then {
            ["Relais utilisé", [_best select 1] call _esc] call _info;
            ["Distance · débit", format ["%1 · %2 Mbit/s", [_best select 2] call _dist, (_best select 3) toFixed 1]] call _info;
        };
        _y = _y + _pad * 0.3;
        [[
            ["MESURER LA LATENCE", { if (!isNil "comspec_overwatch_connect_fnc_measureLatency") then { [] call comspec_overwatch_connect_fnc_measureLatency; }; [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }],
            ["ROUVRIR LE CANAL", { ["enter"] call comspec_atak_native_fnc_athenaAction; }, true]
        ]] call _buttons;
    };
} else {
    ["COMSPEC Overwatch n'est pas chargé : relais, zones et simulations ne sont pas disponibles."] call _para;
};

// --- Débit simulé du téléphone (fn_linkQuality) ---
private _rate = { params ["_kb"]; if (_kb >= 1000) then { format ["%1 Mbit/s", (_kb / 1000) toFixed 1] } else { format ["%1 kbit/s", _kb] } };
private _queue = count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]);
if (["rate", "Débit du téléphone", [format ["%1 · %2", _lq get "label", [_lq get "kbps"] call _rate], "simulation coupée"] select !_sim] call _fold) then {
    if !(_sim) then {
        ["Simulation de débit coupée par le serveur (réglages CBA)."] call _para;
    } else {
        private _bH = _font * 1.1;
        private _bwd = [_pad, _y + _pad * 0.2, _bH, _lq get "bars", ["bad", "bad", "warn", "ok", "ok"] select (_lq get "bars")] call _bars;
        private _c = [[_pad + _bwd + _pad, _y, _cw - _bwd - 3 * _pad, _bH], format ["<t size='1.1' font='COMSPEC_BarlowBold'>%1</t>", _lq get "label"], true] call _txt;
        _y = _y + (((ctrlPosition _c) select 3) max _bH) + _pad * 0.4;
        ["Débit", [_lq get "kbps"] call _rate] call _info;
        ["Latence", format ["%1 ms", _lq get "latency"]] call _info;
        ["Perte", format ["%1 %%", _lq get "loss"]] call _info;
        ["Photo (600 Ko)", format ["environ %1 s", [round (((_lq get "latency") / 1000) + 4800 / ((_lq get "kbps") max 1)), "∞"] select ((_lq get "kbps") <= 0)]] call _info;
        ["Message", format ["%1 s", [((((_lq get "latency") / 1000) + 8 / ((_lq get "kbps") max 1)) toFixed 1), "∞"] select ((_lq get "kbps") <= 0)]] call _info;
        if (_queue > 0) then { ["En attente de réseau", format ["<t color='#f2ab33'>%1 envoi(s)</t>", _queue]] call _info; };
        { _x call _info; } forEach (_lq get "factors");
    };
};

// --- Hors connexion (fn_offline) : données figées, réceptions retenues, envois en file ---
(["state"] call comspec_atak_native_fnc_offline) params ["_offNow", "_offSync", "_offFor", "_offHeld"];
private _offQueue = _queue;
private _offSum = if (_offNow) then { "<t color='#f2ab33'>hors connexion</t>" } else { format ["<t color='#5cc76b'>en ligne</t>%1", ["", format [" · %1 en file", _offQueue]] select (_offQueue > 0)] };
if (["offline", "Hors connexion", _offSum, ["Sans signal, le téléphone garde ses données et envoie tout au retour du réseau", "Le téléphone ne reçoit plus : vous consultez les données enregistrées"] select _offNow] call _fold) then {
    ["État", ["<t color='#5cc76b'>en ligne</t>", format ["<t color='#f2ab33'>hors connexion depuis %1</t>", [format ["%1 s", round _offFor], format ["%1 min", floor (_offFor / 60)]] select (_offFor >= 60)]] select _offNow] call _info;
    ["Dernière synchro", [_offSync, "—"] select (_offSync isEqualTo "")] call _info;
    ["Envois en file", [str _offQueue, format ["<t color='#f2ab33'>%1</t>", _offQueue]] select (_offQueue > 0)] call _info;
    ["Reçus en attente au relais", [str _offHeld, format ["<t color='#f2ab33'>%1</t>", _offHeld]] select (_offHeld > 0)] call _info;
    if !(["offlineHold"] call comspec_atak_native_fnc_netCond) then { ["Rétention coupée par le serveur : comptes rendus et alertes arrivent même sans signal."] call _para; };
};

// --- Synchronisation avec Athena (fn_syncStats : volume par canal compté par COMSPEC Link 2.0.88) ---
// Ce qui part et revient, canal par canal : la fiche opérateur ne repart que si elle change, et Athena ne renvoie plus
// une liste (messages, marqueurs, formes, ordres…) qui n'a pas bougé depuis le dernier passage.
if (_bridge) then {
    private _linkVal = switch (true) do {
        case (_link in ["linked", "online", "connected"]): { "<t color='#5cc76b'>connectée</t>" };
        case (_link isEqualTo "degraded"): { "<t color='#f2ab33'>dégradée</t>" };
        case (_link isEqualTo "connecting"): { "<t color='#f2ab33'>connexion…</t>" };
        default { "<t color='#e5483a'>hors ligne</t>" };
    };
    if (["sync", "Synchronisation avec Athena", _linkVal, "Seul ce qui change est envoyé ; une liste inchangée n'est pas retéléchargée"] call _fold) then {
        private _ko = { params ["_b"]; switch (true) do { case (_b >= 1e6): { format ["%1 Mo", (_b / 1e6) toFixed 1] }; case (_b >= 1e4): { format ["%1 ko", round (_b / 1000)] }; default { format ["%1 ko", (_b / 1000) toFixed 1] }; } };
        private _ss = [] call comspec_atak_native_fnc_syncStats;
        ["Liaison", _linkVal] call _info;
        if (_ss get "ok") then {
            private _sp = _ss get "span";
            ["Envoyé (5 dernières min)", format ["%1/min", [(_ss get "wOut") * 60 / _sp] call _ko]] call _info;
            ["Reçu (5 dernières min)", format ["%1/min", [(_ss get "wIn") * 60 / _sp] call _ko]] call _info;
            ["Depuis le lancement", format ["envoyé %1 · reçu %2", [_ss get "out"] call _ko, [_ss get "in"] call _ko]] call _info;
            ["Requêtes", str (_ss get "calls")] call _info;
            ["Réponses inchangées", format ["%1 · %2 non retéléchargés", _ss get "same", [_ss get "saved"] call _ko]] call _info;
        } else {
            ["Le détail par canal demande COMSPEC Link 2.0.88 ou plus récent."] call _para;
        };
        // Fiche opérateur (Overwatch connect : fn_syncOperatorProfile).
        private _fAt = missionNamespace getVariable ["COMSPEC_OperatorLastSentAt", -1];
        private _fWhat = missionNamespace getVariable ["COMSPEC_OperatorLastWhat", ""];
        private _fBack = (missionNamespace getVariable ["COMSPEC_OperatorProfileBackoffUntil", 0]) - diag_tickTime;
        ["Fiche opérateur", if (_fAt isEqualType 0 && {_fAt > 0}) then {
            format ["%1 · %2<br/><t size='%6' color='%7'>%3 · %4 envoi(s)</t>%5", [diag_tickTime - _fAt] call _ageTxt, [_fWhat] call _esc,
                [missionNamespace getVariable ["COMSPEC_OperatorLastBytes", 0]] call _ko, missionNamespace getVariable ["COMSPEC_OperatorSentCount", 0],
                ["", format ["<br/><t color='#f2ab33'>nouvel essai dans %1 s</t>", round _fBack]] select (_fBack > 0), _zS, _greyS]
        } else { ["pas encore envoyée", format ["<t color='#f2ab33'>pas encore envoyée · nouvel essai dans %1 s</t>", round _fBack]] select (_fBack > 0) }] call _info;
        ["La fiche part à la connexion, puis seulement si l'équipement, l'indicatif, le grade ou les versions changent, avec un rappel toutes les 15 min. Catalogue d'objets et liste des mods : une fois, puis seulement s'ils changent."] call _para;
        // En attente : file du téléphone (hors connexion), tampon d'Overwatch (comptes rendus rédigés), reçus retenus au relais.
        private _obx = 0;
        if (!isNil "comspec_overwatch_connect_fnc_outboxState") then { _obx = (["get"] call comspec_overwatch_connect_fnc_outboxState) getOrDefault ["count", 0]; };
        private _wait = _offQueue + _obx;
        ["En attente d'envoi", ["<t color='#5cc76b'>rien</t>", format ["<t color='#f2ab33'>%1 (téléphone %2 · comptes rendus %3)</t>", _wait, _offQueue, _obx]] select (_wait > 0)] call _info;
        if (_ss get "ok") then {
            private _rows = _ss get "rows";
            if (_rows isEqualTo []) then { ["Aucun échange avec Athena pour l'instant."] call _para; };
            {
                _x params ["_lbl", "_path", "_calls", "_bo", "_bi", "_same", "_age", "_code"];
                private _err = _code < 0 || {_code >= 400};
                [[_lbl] call _esc, format ["envoyé %1 · reçu %2<br/><t size='%7' color='%8'>%3 appel(s)%4 · %5%6</t>", [_bo] call _ko, [_bi] call _ko, _calls,
                    ["", format [" · %1 %% inchangé", round (100 * _same / (_calls max 1))]] select (_same > 0),
                    [_age] call _ageTxt,
                    ["", format [" · <t color='#e5483a'>%1</t>", ["délai dépassé", format ["erreur %1", _code]] select (_code >= 0)]] select _err, _zS, _greyS]] call _info;
            } forEach (_rows select [0, 12]);
        };
    };
};

// --- File d'envoi Athena (journal de COMSPEC Link 2.0.65, lu en mémoire sans réseau) ---
private _sqRaw = if (_bridge && {!(missionNamespace getVariable ["COMSPEC_ATAK_SendQNo", false])}) then { ((["COMSPECExtension", "SendQueue.Status", []] call comspec_overwatch_connect_fnc_extDirect)) select 0 } else { "" };
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
    if (["sendq", "File d'envoi Athena", format ["<t color='%1'>%2 en file</t>", _hex get (_ovTxt select 0), _q]] call _fold) then {
        ["État d'Athena", format ["<t color='%1'>%2</t>", _hex get (_ovTxt select 0), _ovTxt select 1]] call _info;
        ["Latence moyenne · p95", format ["%1 ms · %2 ms", _avg, _p95]] call _info;
        ["5 dernières min", format ["%1 surcharge(s) · %2 erreur(s) serveur · %3 délai(s)", _n429, _n5xx, _nto]] call _info;
        (missionNamespace getVariable ["COMSPEC_ATAK_ServerPresence", []]) params [["_svSt", ""], ["_svLbl", ""], ["_svCom", ""], "", ["_svAt", ""]];
        if (_svSt in ["ok", "away"]) then {
            ["Serveur de jeu", [format ["<t color='%1'>%2</t> · vérifié à %3", _hex get "ok", [_svLbl] call _esc, _svAt],
                format ["<t color='%1'>hors des serveurs de %2</t> · vérifié à %3", _hex get "warn", [["votre communauté", _svCom] select (_svCom isNotEqualTo "")] call _esc, _svAt]] select (_svSt isEqualTo "away")] call _info;
        };
        ["En file · en cours", format ["%1 · %2", _q, _sd]] call _info;
        ["Nouvel essai · échec", format ["<t color='#f2ab33'>%1</t> · <t color='#e5483a'>%2</t>", _rt, _fl]] call _info;
        ["Envoyés", format ["<t color='#5cc76b'>%1</t>", _dn]] call _info;
        private _stTxt = createHashMapFromArray [["queued", ["off", "en file"]], ["sending", ["warn", "envoi…"]], ["retry", ["warn", "nouvel essai"]], ["failed", ["bad", "échec"]], ["done", ["ok", "envoyé"]]];
        {
            (_x splitString toString [9]) params [["_id", ""], ["_lbl", ""], ["_st", ""], ["_try", "0"], ["_age", "0"], ["_err", ""]];
            private _m = _stTxt getOrDefault [_st, ["off", _st]];
            private _a = parseNumber _age;
            [[_lbl] call _esc, format ["<t color='%1'>%2</t><br/><t size='%6' color='%7'>%3 essai(s) · %4%5</t>", _hex get (_m select 0), _m select 1, _try,
                [format ["%1 s", _a], format ["%1 min", round (_a / 60)]] select (_a >= 90),
                ["", format ["<br/>%1", [_err] call _esc]] select (_err isNotEqualTo "" && {_st in ["retry", "failed"]}), _zS, _greyS]] call _info;
        } forEach ((_lines select [1, 8]));
        if ((count _lines) <= 1) then { ["Aucun envoi suivi pour l'instant (comptes rendus, demandes, messages…)."] call _para; };
    };
};

// --- État du téléphone (fn_deviceHealth, fn_deviceReport) ---
private _lvl = round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
private _batHex = switch (true) do { case (_lvl <= 5): { "#e5483a" }; case (_lvl <= 20): { "#f2ab33" }; default { "#5cc76b" }; };
private _devSum = format ["%1 · <t color='%2'>%3 %%</t>", [["endommagé", "éteint"] select ((_hp get "state") isEqualTo "OFF"), "intact"] select ((_hp get "state") isEqualTo "OK"), _batHex, _lvl];
if (["device", "État du téléphone", _devSum] call _fold) then {
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
    (missionNamespace getVariable ["COMSPEC_ATAK_BatteryInfo", [0, []]]) params ["_r", ["_f", []]];
    ["Batterie", format ["<t color='%1'>%2 %%</t>", _batHex, _lvl]] call _info;
    private _left = switch (true) do {
        case (_r < 0): { "en charge" };
        case (_r <= 0.01): { "" };
        default { private _m = round (_lvl / _r); format ["environ %1 h %2", floor (_m / 60), _m mod 60] };
    };
    if (_left isNotEqualTo "") then { ["Autonomie", _left] call _info; };
    // Consommation par poste, une ligne chacun (avant : tout sur une ligne en petit).
    {
        _x params ["_n", ["_v", 0]];
        [_n, if (_v isEqualType 0) then { format ["%1%2 %%/min", ["", "+"] select (_v < 0), abs _v] } else { format ["%1", _v] }] call _info;
    } forEach _f;
    ["Réparation : action ACE « Réparer le téléphone ATAK » avec une trousse à outils, ou « Changer de téléphone ATAK » avec un appareil de rechange."] call _para;
    [[
        ["TEST DE DÉBIT", { uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []]; private _q = [] call comspec_atak_native_fnc_linkQuality; ["INFO", format ["Test de débit : %1 kbit/s, %2 ms, perte %3 %%", _q get "kbps", _q get "latency", _q get "loss"], 5, 20] call comspec_atak_native_fnc_notify; [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }],
        ["REDÉMARRER", { ["reboot"] call comspec_atak_native_fnc_deviceRepair; }]
    ]] call _buttons;
};
_bgc ctrlSetPosition [0, 0, _bw, _bh max (_y + _pad)];
_bgc ctrlCommit 0;
// Contrôle toutes les 5 s tant que la page est affichée : redessinée seulement si ses données ont changé (empreinte
// ci-dessous ; les âges « il y a … » avancent au moins toutes les 10 s) et jamais pendant une saisie.
private _netSig = {
    hashValue [missionNamespace getVariable ["COMSPEC_LinkState", ""], missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1],
        str ([] call comspec_atak_native_fnc_linkQuality), str ([] call comspec_atak_native_fnc_deviceHealth), str ([] call comspec_atak_native_fnc_athenaPulse),
        profileNamespace getVariable ["COMSPEC_ATAK_NetOpen", []], count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]), floor (diag_tickTime / 10)]
};
private _netTick = {
    params ["_tok"];
    if ((uiNamespace getVariable ["COMSPEC_ATAK_NetTok", -1]) isNotEqualTo _tok) exitWith {};
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d) exitWith {};
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "NETWORK") exitWith {};
    private _fc = focusedCtrl _d;
    if ((!isNull _fc && {(ctrlType _fc) isEqualTo 2}) || {(call (uiNamespace getVariable ["COMSPEC_ATAK_NetSigFn", {0}])) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_NetSig", 0])}) exitWith {
        [uiNamespace getVariable ["COMSPEC_ATAK_NetTick", {}], [_tok], 5] call comspec_atak_native_fnc_waitAndExec;
    };
    ["NETWORK"] call comspec_atak_native_fnc_pageRender;
};
private _tok = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_NetTok", _tok];
uiNamespace setVariable ["COMSPEC_ATAK_NetSigFn", _netSig];
uiNamespace setVariable ["COMSPEC_ATAK_NetTick", _netTick];
uiNamespace setVariable ["COMSPEC_ATAK_NetSig", call _netSig];
[_netTick, [_tok], 5] call comspec_atak_native_fnc_waitAndExec;
true
