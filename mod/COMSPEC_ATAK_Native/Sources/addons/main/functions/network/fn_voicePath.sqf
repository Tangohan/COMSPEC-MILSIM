/*
    Chemin de la voix : par où passe ma voix quand j'émets, et avec quelle qualité. Lecture seule : ne règle aucune radio,
    ne touche ni au signal ni au pont radio (fn_mpuVoice, fn_mpuVoiceSig, fn_radioGuard restent les seuls à le faire).
    Piloté par les événements d'émission (ACRE2 : acre_startedSpeaking / acre_stoppedSpeaking ; TFAR : OnTangent, OnSpeak ;
    accusé du maillage : fn_relayTxBadge "draw"). Aucune boucle, rien ne tourne hors émission. Sans ACRE2 ni TFAR : rien.
    Chemins : "mesh" (réseau ATAK, maillage MPU5), "acre" (radio ACRE2 classique), "tfar" (radio TFAR classique),
              "local" (voix directe, proximité), "none" (aucun chemin).
    API (aussi pour le tableau radio du TOC) :
      ["get"]      -> [chemin, raison (texte), route [noms, de moi à la passerelle], réseau (radio · canal · fréquence), depuis (s, -1 au repos),
                       [palier 0-4, qualité %, latence ms, nom du palier], en émission (bool)]
      ["quality", [sauts, niveau de gravité 0-3, mode 0 aucune | 1 légère | 2 réaliste, latence par saut ms, seuil de coupure %]]
                   -> [palier (0 Excellent, 1 Bon, 2 Moyen, 3 Mauvais, 4 Perdu), qualité %, latence ms, Px, dBm]   (sans effet, table testée)
      ["resolve", [j'émets, sur la radio, mod ("acre" | "tfar" | ""), sauts du maillage (-1 : aucun), code de fn_mpuVoice "summary",
                   raison d'attente, relais exigé et absent]] -> [chemin, code de raison]   (sans effet, table testée)
      ["log"]      -> dernières émissions (20 au plus) : [heure du jeu, durée s (-1 : reçue), chemin, réseau ou émetteur, qualité % (-1 : sans objet), palier, sauts]
      ["policy"]   -> [priorité 0 les deux | 1 radio classique d'abord | 2 maillage d'abord, imposée par le serveur]
      ["setPolicy", [priorité]] ; ["tierName", [palier]] ; ["pathName", [chemin]] ; ["reasonText", [code]]
    Événement local CBA « comspec_atak_native_voicePath » : même tableau que "get", à chaque début, changement et fin d'émission.
    Limites dites à l'écran : la latence est une estimation affichée (la voix d'ACRE2 / TFAR ne peut pas être retardée par un mod) ;
    la dégradation audible passe par le signal rendu à ACRE2 (grésillement et coupures de son propre modèle), TFAR n'a pas de signal par paire.
*/
params [["_act", "get"], ["_arg", []]];
private _tiers = ["Excellent", "Bon", "Moyen", "Mauvais", "Perdu"];
private _paths = createHashMapFromArray [
    ["mesh", "Réseau ATAK (maillage MPU5)"], ["acre", "Radio ACRE2 (radio classique)"], ["tfar", "Radio TFAR (radio classique)"],
    ["local", "Voix directe (proximité)"], ["none", "Aucun chemin"]
];
// Table des raisons : code -> phrase. Les codes sont ceux de "resolve".
private _reasons = createHashMapFromArray [
    ["idle", "au repos"],
    ["direct", "vous parlez sans radio : seuls vos voisins vous entendent"],
    ["noradio", "ni ACRE2 ni TFAR sur ce serveur"],
    ["relayed", "voix relayée par le maillage : un équipier relié vous reçoit mieux par le réseau que par la radio"],
    ["radioEnough", "la radio suffit : aucun équipier relié n'a besoin du réseau pour vous entendre"],
    ["meshOff", "voix par le réseau coupée par un admin"],
    ["meshCut", "lien radio du mod coupé"],
    ["meshNoReal", "liaison téléphone-radio réaliste coupée (profil Arcade)"],
    ["notBridged", "radio non reliée à la MPU5"],
    ["noRelay", "aucun relais en service à portée : le réseau MPU5 ne porte rien"],
    ["jammed", "maillage coupé : brouillage, bascule sur la radio"],
    ["meshWait", "MPU5 hors du maillage, bascule sur la radio"]
];
switch (_act) do {
    case "quality": {
        _arg params [["_hops", -1], ["_lvl", 0], ["_mode", 2], ["_hopMs", 15], ["_cut", 0]];
        if (_hops < 0 || {_lvl >= 3}) exitWith { [4, 0, 0, 0, -992] };
        private _l = (_lvl max 0) min 2;
        private _px = switch (_mode) do {
            case 0: { 0.95 };
            case 1: { [(1 - 0.02 * _hops) max 0.8, 0.85, 0.65] select _l };
            default { [(1 - 0.04 * _hops) max 0.6, 0.7, 0.45] select _l };
        };
        private _dbm = switch (_mode) do {
            case 0: { -55 };
            case 1: { [-55 - 2 * _hops, -70, -85] select _l };
            default { [-60 - 3 * _hops, -80, -95] select _l };
        };
        private _pct = round (_px * 100);
        if (_pct < _cut) exitWith { [4, 0, 0, 0, -992] };
        private _tier = switch (true) do { case (_pct >= 90): { 0 }; case (_pct >= 70): { 1 }; case (_pct >= 50): { 2 }; default { 3 }; };
        [_tier, _pct, (round (40 + _hopMs * _hops)) min 400, _px, _dbm]
    };
    case "resolve": {
        _arg params [["_speaking", false], ["_onRadio", false], ["_mod", ""], ["_meshHops", -1], ["_code", "off"], ["_why", ""], ["_noRelay", false]];
        if (!_speaking) exitWith { ["none", "idle"] };
        if (!_onRadio) exitWith { ["local", "direct"] };
        if !(_mod in ["acre", "tfar"]) exitWith { ["none", "noradio"] };
        if (_meshHops >= 0 && {_code isEqualTo "on"}) exitWith { ["mesh", "relayed"] };
        [_mod, switch (_code) do {
            case "on": { "radioEnough" };
            case "off": { "meshOff" };
            case "cut": { "meshCut" };
            case "noreal": { "meshNoReal" };
            case "nobridge": { "notBridged" };
            default { switch (true) do { case (_noRelay): { "noRelay" }; case ((_why find "brouill") >= 0): { "jammed" }; default { "meshWait" }; } };
        }]
    };
    case "tierName": { _tiers param [_arg param [0, 4], "Perdu"] };
    case "pathName": { _paths getOrDefault [_arg param [0, "none"], "Aucun chemin"] };
    case "reasonText": { _reasons getOrDefault [_arg param [0, "idle"], ""] };
    case "policy": {
        private _srv = missionNamespace getVariable ["comspec_atak_native_mesh_voice_policy", 0];
        if (!(_srv isEqualType 0)) then { _srv = 0; };
        if (_srv > 0) exitWith { [_srv - 1, true] };
        [((profileNamespace getVariable ["COMSPEC_ATAK_VoicePolicy", 0]) max 0) min 2, false]
    };
    case "setPolicy": {
        if ((["policy"] call comspec_atak_native_fnc_voicePath) select 1) exitWith { false };
        profileNamespace setVariable ["COMSPEC_ATAK_VoicePolicy", ((_arg param [0, 0]) max 0) min 2];
        ["applyPolicy"] call comspec_atak_native_fnc_voicePath;
        true
    };
    case "applyPolicy": {
        // Lue par fn_mpuVoiceSig dans le chemin voix : une simple variable, jamais un appel.
        missionNamespace setVariable ["COMSPEC_ATAK_VoicePolicy", (["policy"] call comspec_atak_native_fnc_voicePath) select 0];
    };
    case "init": {
        if (!hasInterface) exitWith {};
        ["applyPolicy"] call comspec_atak_native_fnc_voicePath;
        ["acre_startedSpeaking", {
            params ["_u", ["_r", false], ["_id", ""]];
            if (_u isNotEqualTo player) exitWith {};
            ["start", ["acre", _r isEqualTo true, [_id, ""] select !(_id isEqualType "")]] call comspec_atak_native_fnc_voicePath;
        }] call CBA_fnc_addEventHandler;
        ["acre_stoppedSpeaking", {
            params ["_u"];
            if (_u isEqualTo player) then { ["end"] call comspec_atak_native_fnc_voicePath; };
        }] call CBA_fnc_addEventHandler;
        // TFAR : mêmes événements publics que fn_mpuVoice, par fn_radioGuard. À confirmer en jeu (TFAR absent de la machine d'étude).
        if (!isNil "TFAR_fnc_addEventHandler" && {["read"] call comspec_atak_native_fnc_radioGuard}) then {
            ["api", ["TFAR_fnc_addEventHandler", ["COMSPEC_ATAK_VoicePathTx", "OnTangent", {
                params [["_u", objNull], ["_radio", ""], "", "", ["_down", false]];
                if (_u isNotEqualTo (missionNamespace getVariable ["TFAR_currentUnit", player])) exitWith {};
                if (_down) then { ["start", ["tfar", true, [str _radio, _radio] select (_radio isEqualType "")]] call comspec_atak_native_fnc_voicePath; } else { ["end"] call comspec_atak_native_fnc_voicePath; };
            }], false]] call comspec_atak_native_fnc_radioGuard;
            ["api", ["TFAR_fnc_addEventHandler", ["COMSPEC_ATAK_VoicePathSpk", "OnSpeak", {
                params [["_u", objNull], ["_on", false]];
                if (_u isNotEqualTo (missionNamespace getVariable ["TFAR_currentUnit", player])) exitWith {};
                private _st = uiNamespace getVariable ["COMSPEC_ATAK_VoicePath", createHashMap];
                if (_on) then {
                    if !(_st getOrDefault ["active", false]) then { ["start", ["tfar", false, ""]] call comspec_atak_native_fnc_voicePath; };
                } else {
                    if ((_st getOrDefault ["path", ""]) isEqualTo "local") then { ["end"] call comspec_atak_native_fnc_voicePath; };
                };
            }], false]] call comspec_atak_native_fnc_radioGuard;
        };
    };
    case "facts": {
        // Ce que "resolve" ne peut pas deviner : état de la voix par le réseau (fn_mpuVoice "summary"), relais exigé et absent.
        (["summary"] call comspec_atak_native_fnc_mpuVoice) params [["_code", "off"], "", ""];
        private _why = (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) param [2, ""];
        private _gate = ["gate"] call comspec_atak_native_fnc_relayNet;
        [_code, _why, !(_gate select 0)]
    };
    case "net": {
        // Radio, canal et fréquence de l'émission : lus dans la liste déjà tenue par fn_mpu5 (ACRE2), jamais réglés.
        private _id = _arg param [0, ""];
        private _out = "";
        if (!isNil "acre_api_fnc_getCurrentRadioList") then {
            private _rl = ["radios"] call comspec_atak_native_fnc_mpu5;
            private _i = _rl findIf { (_x select 0) isEqualTo _id };
            if (_i < 0) then { _i = _rl findIf { _x param [7, false] }; };
            if (_i >= 0) then {
                (_rl select _i) params ["", "_name", "_ch", "", "_tx"];
                _out = format ["%1 · canal %2%3", _name, _ch, ["", format [" · %1 MHz", _tx toFixed 3]] select (_tx isEqualType 0 && {_tx > 0})];
            };
        };
        if (_out isEqualTo "") then {
            private _b = missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []];
            _out = _b param [2, ""];
        };
        _out
    };
    case "route": {
        // De moi à la passerelle, par le graphe déjà calculé (fn_meshGraph "view") : rien n'est recalculé.
        ((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["path", []]) params [["_nodes", []], "", ["_names", []], ["_me", -1], "", "", "", ["_viaG", []], ["_hopG", []]];
        if (_me < 0 || {(_hopG param [_me, -1]) < 0}) exitWith { [] };
        private _out = ["Moi"];
        private _cur = _me;
        private _guard = 0;
        while { (_hopG param [_cur, 0]) > 0 && {_guard < 16} } do {
            _cur = _viaG param [_cur, -1];
            if (_cur < 0 || {_cur >= (count _nodes)}) exitWith {};
            _out pushBack (_names param [_cur, "?"]);
            _guard = _guard + 1;
        };
        _out
    };
    case "start": {
        _arg params [["_mod", ""], ["_onRadio", false], ["_id", ""]];
        (["facts"] call comspec_atak_native_fnc_voicePath) params ["_code", "_why", "_noRelay"];
        (["resolve", [true, _onRadio, _mod, -1, _code, _why, _noRelay]] call comspec_atak_native_fnc_voicePath) params ["_path", "_reason"];
        uiNamespace setVariable ["COMSPEC_ATAK_VoicePath", createHashMapFromArray [
            ["active", true], ["path", _path], ["reason", _reason], ["at", diag_tickTime], ["day", dayTime], ["mod", _mod], ["radio", _onRadio],
            ["net", [["net", [_id]] call comspec_atak_native_fnc_voicePath, ""] select !_onRadio], ["hops", -1], ["q", [4, -1, 0]], ["code", _code]
        ]];
        ["emit"] call comspec_atak_native_fnc_voicePath;
        // TFAR : fn_mpuVoice "tfBefore" a déjà allongé la portée pour un équipier relié (COMSPEC_ATAK_VoiceTx) avant ce début d'émission.
        if (_mod isEqualTo "tfar" && {_onRadio} && {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []]) isNotEqualTo []}) then {
            private _hs = (((missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) param [3, []]) select { _x param [3, false] }) apply { _x param [1, 0] };
            _hs sort true;
            ["mesh", [_hs param [0, 0]]] call comspec_atak_native_fnc_voicePath;
        };
    };
    case "mesh": {
        // Accusé du maillage (fn_relayTxBadge "draw") : ma voix passe vraiment par le réseau, à ce nombre de sauts.
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_VoicePath", createHashMap];
        if !(_st getOrDefault ["active", false]) exitWith {};
        private _hops = (_arg param [0, 0]) max 0;
        // Niveau du pire lien : lu dans le graphe de la voix (fn_meshGraph "view"), pour le correspondant à ce nombre de sauts.
        private _lvl = 0;
        private _found = false;
        {
            if ((_y param [0, -1]) isEqualTo _hops && {!_found || {(_y param [2, 0]) < _lvl}}) then { _lvl = _y param [2, 0]; _found = true; };
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["voice", createHashMap]);
        private _q = ["quality", [_hops, _lvl, missionNamespace getVariable ["comspec_atak_native_mesh_voice_degrade", 2],
            missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15], missionNamespace getVariable ["comspec_atak_native_mesh_voice_cut", 0]]] call comspec_atak_native_fnc_voicePath;
        if ((_st get "path") isEqualTo "mesh" && {(_st get "hops") isEqualTo _hops} && {((_st get "q") select 0) isEqualTo (_q select 0)}) exitWith {};
        _st set ["path", "mesh"];
        _st set ["reason", "relayed"];
        _st set ["hops", _hops];
        _st set ["q", _q select [0, 3]];
        ["emit"] call comspec_atak_native_fnc_voicePath;
    };
    case "end": {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_VoicePath", createHashMap];
        if !(_st getOrDefault ["active", false]) exitWith {};
        _st set ["active", false];
        _st set ["ended", diag_tickTime];
        private _log = uiNamespace getVariable ["COMSPEC_ATAK_VoiceLog", []];
        _log pushBack [_st get "day", round (diag_tickTime - (_st get "at")), _st get "path", _st get "net", (_st get "q") select 1, (_st get "q") select 0, _st get "hops"];
        if ((count _log) > 20) then { _log deleteAt 0; };
        uiNamespace setVariable ["COMSPEC_ATAK_VoiceLog", _log];
        ["emit"] call comspec_atak_native_fnc_voicePath;
    };
    case "rx": {
        // Émission reçue par le maillage (fn_mpuVoice "tick", là où part l'accusé) : notée au journal, une fois par émetteur et par 10 s.
        _arg params [["_from", objNull], ["_hops", -1], ["_lvl", 0]];
        if (isNull _from || {_hops < 0}) exitWith {};
        private _seen = uiNamespace getVariable ["COMSPEC_ATAK_VoiceRxSeen", createHashMap];
        if (diag_tickTime - (_seen getOrDefault [netId _from, -100]) < 10) exitWith {};
        _seen set [netId _from, diag_tickTime];
        uiNamespace setVariable ["COMSPEC_ATAK_VoiceRxSeen", _seen];
        private _q = ["quality", [_hops, _lvl, missionNamespace getVariable ["comspec_atak_native_mesh_voice_degrade", 2],
            missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15], missionNamespace getVariable ["comspec_atak_native_mesh_voice_cut", 0]]] call comspec_atak_native_fnc_voicePath;
        private _log = uiNamespace getVariable ["COMSPEC_ATAK_VoiceLog", []];
        _log pushBack [dayTime, -1, "mesh", [_from, true] call comspec_atak_native_fnc_unitCallsign, _q select 1, _q select 0, _hops];
        if ((count _log) > 20) then { _log deleteAt 0; };
        uiNamespace setVariable ["COMSPEC_ATAK_VoiceLog", _log];
        ["live"] call comspec_atak_native_fnc_voicePath;
    };
    case "log": { +(uiNamespace getVariable ["COMSPEC_ATAK_VoiceLog", []]) };
    case "get": {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_VoicePath", createHashMap];
        private _on = _st getOrDefault ["active", false];
        if (!_on) exitWith {
            // Au repos : le chemin que prendrait le prochain appui sur l'alternat, d'après l'état connu.
            (["facts"] call comspec_atak_native_fnc_voicePath) params ["_code", "_why", "_noRelay"];
            private _mod = ["", "tfar"] select (!isNil "TFAR_fnc_activeSwRadio");
            if (!isNil "acre_api_fnc_getCurrentRadioList") then { _mod = "acre"; };
            private _peers = ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) param [3, []]) select { _x param [3, false] };
            private _h = if (_peers isEqualTo []) then { -1 } else { (_peers select 0) param [1, 0] };
            (["resolve", [true, true, _mod, _h, _code, _why, _noRelay]] call comspec_atak_native_fnc_voicePath) params ["_path", "_reason"];
            private _q = if (_path isEqualTo "mesh") then {
                (["quality", [_h, ((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["level", 0]) min 2, missionNamespace getVariable ["comspec_atak_native_mesh_voice_degrade", 2],
                    missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15], missionNamespace getVariable ["comspec_atak_native_mesh_voice_cut", 0]]] call comspec_atak_native_fnc_voicePath) select [0, 3]
            } else { [4, -1, 0] };
            [_path, _reasons getOrDefault [_reason, ""], [[], ["route"] call comspec_atak_native_fnc_voicePath] select (_path isEqualTo "mesh"),
                [["net", [""]] call comspec_atak_native_fnc_voicePath, ""] select (_mod isEqualTo ""), -1, _q + [_tiers param [_q select 0, "Perdu"]], false]
        };
        private _q = _st get "q";
        [_st get "path", _reasons getOrDefault [_st get "reason", ""], [[], ["route"] call comspec_atak_native_fnc_voicePath] select ((_st get "path") isEqualTo "mesh"),
            _st get "net", diag_tickTime - (_st get "at"), _q + [_tiers param [_q select 0, "Perdu"]], true]
    };
    case "emit": {
        ["comspec_atak_native_voicePath", ["get"] call comspec_atak_native_fnc_voicePath] call CBA_fnc_localEvent;
        ["bar"] call comspec_atak_native_fnc_voicePath;
        ["live"] call comspec_atak_native_fnc_voicePath;
    };
    case "short": {
        // Libellé de la barre d'état, au plus aussi large que « COMPROMISE », et sa couleur de thème.
        private _p = _arg param [0, "none"];
        [createHashMapFromArray [["mesh", "VOIX ATAK"], ["acre", "VOIX ACRE"], ["tfar", "VOIX TFAR"], ["local", "VOIX DIR."]] getOrDefault [_p, "SANS VOIX"],
            createHashMapFromArray [["mesh", "ok"], ["acre", "info"], ["tfar", "info"], ["local", "off"]] getOrDefault [_p, "alert"]]
    };
    case "bar": {
        // Barre d'état du téléphone, pendant l'émission et 3 s après : le libellé du cadenas dit le chemin de la voix, le cadenas en
        // prend la couleur (seul visible en petite vue). fn_statusUpdate rend ensuite le cadenas (COMSPEC_ATAK_VoiceBar périmé).
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_VoicePath", createHashMap];
        if ((count _st) isEqualTo 0) exitWith {};
        (["short", [_st get "path"]] call comspec_atak_native_fnc_voicePath) params ["_lab", "_tok"];
        private _col = COMSPEC_ATAK_Th getOrDefault [_tok, [1, 1, 1, 1]];
        private _q = _st get "q";
        private _tip = format ["%1 : %2", (_paths getOrDefault [_st get "path", ""]) call comspec_atak_native_fnc_tr, (_reasons getOrDefault [_st get "reason", ""]) call comspec_atak_native_fnc_tr];
        if ((_q select 1) >= 0) then { _tip = _tip + format [" · %1 %2 %%", (_tiers param [_q select 0, ""]) call comspec_atak_native_fnc_tr, _q select 1]; };
        uiNamespace setVariable ["COMSPEC_ATAK_VoiceBar", [_lab, _col, _tip, [diag_tickTime + 3, 1e9] select (_st get "active")]];
        {
            _x params ["_c", "_isText"];
            if (isNull _c) then { continue };
            if (_isText) then { _c ctrlSetText (_lab call comspec_atak_native_fnc_tr); _c setVariable ["cs_t", _lab]; };
            _c ctrlSetTextColor _col;
            _c setVariable ["cs_c", _col];
            _c ctrlSetTooltip _tip;
            _c setVariable ["cs_tt", _tip];
        } forEach [[uiNamespace getVariable ["COMSPEC_ATAK_LockTxt", controlNull], true], [uiNamespace getVariable ["COMSPEC_ATAK_LockCtrl", controlNull], false]];
    };
    case "cardText": {
        // Carte « Chemin de la voix » de l'app Wave Relay : trois lignes, toujours trois (mise à jour sur place, sans saut de mise en page).
        (["get"] call comspec_atak_native_fnc_voicePath) params ["_path", "_reason", "_route", "_net", "_since", "_q", "_on"];
        private _T = { _this call comspec_atak_native_fnc_tr };
        private _hex = { COMSPEC_ATAK_Th get _this };
        private _tok = (["short", [_path]] call comspec_atak_native_fnc_voicePath) select 1;
        private _colHex = ("hex" + (toUpper (_tok select [0, 1])) + (_tok select [1])) call _hex;
        private _qLine = if ((_q select 1) >= 0) then {
            format ["<t color='%1'>%2 · %3 %%</t> · %4 · %5", (["hexOk", "hexOk", "hexWarn", "hexAlert", "hexAlert"] select (_q select 0)) call _hex, (_q select 3) call _T, _q select 1,
                format ["%1 saut(s)" call _T, ((count _route) - 1) max 0], format ["latence ~%1 ms" call _T, _q select 2]]
        } else { _reason call _T };
        private _l3 = [[_net, (_route joinString " > ")] select { _x isNotEqualTo "" }, " · "] call { (_this select 0) joinString (_this select 1) };
        if (_l3 isEqualTo "") then { _l3 = "Appuyez sur l'alternat radio : le chemin s'affiche ici." call _T; };
        format ["<t font='COMSPEC_BarlowBold' color='%1'>%2 : %3</t><t align='right' size='0.85' color='%4'>%5</t><br/><t size='0.9'>%6</t><br/><t size='0.85' color='%7'>%8</t>",
            _colHex, "Voix" call _T, ((["pathName", [_path]] call comspec_atak_native_fnc_voicePath)) call _T, [("hexTextMuted" call _hex), ("hexAlert" call _hex)] select _on,
            [("au repos" call _T), format ["en émission · %1 s" call _T, round _since]] select _on, _qLine, "hexTextMuted" call _hex, _l3]
    };
    case "live": {
        // App Wave Relay ouverte : la carte change sur place, sans redessiner la page.
        private _ctl = uiNamespace getVariable ["COMSPEC_ATAK_WrVoiceCtl", controlNull];
        if (isNull _ctl || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "WAVERELAY"}) exitWith {};
        _ctl ctrlSetStructuredText parseText (["cardText"] call comspec_atak_native_fnc_voicePath);
    };
};
