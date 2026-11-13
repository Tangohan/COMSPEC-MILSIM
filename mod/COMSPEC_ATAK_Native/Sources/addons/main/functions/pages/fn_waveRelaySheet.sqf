/*
    Fiches de l'app Wave Relay : écrans courts ouverts par un bouton, à la place de l'onglet, fermés par « retour ».
    Elles remplacent les longs paragraphes posés au milieu des pages.
      ["rows", clé] -> lignes de formulaire : "voice" (voix par le réseau, étape par étape, avec l'état réel de chaque étape),
                       "gateway" (choisir la passerelle, règles de bascule), "diag" (signaler un problème : diagnostic réuni),
                       "relay" (état d'un relais : action ACE « Voir l'état du relais », liste des relais, gestion Zeus / admin)
      ["open", [clé, objet]] : ouvre la fiche (téléphone ouvert au besoin) ; ["close"]
      ["test"]  : teste la liaison jusqu'à la passerelle (latence, perte, débit, maillon faible), résultat écrit dans l'onglet ÉTAT
      ["diagLines"] -> lignes du diagnostic ; ["diagSend"] : écrit le diagnostic dans le journal du jeu (RPT). Aucune requête réseau.
      ["relayAct", [action, valeur]] : action Zeus / admin sur le relais de la fiche (allumer, couper, réparer, batterie, canal, remise à zéro)
*/
params [["_act", "rows"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _render = { if ((_s getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }; };
private _hex = { COMSPEC_ATAK_Th get _this };
private _muted = { format ["<t size='0.88' color='%1'>%2</t>", "hexTextMuted" call _hex, _this] };
private _dist = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _back = { ["close"] call comspec_atak_native_fnc_waveRelaySheet; };
private _isAdmin = { !isNull (getAssignedCuratorLogic player) || {([player] call comspec_atak_native_fnc_adminAllowed) isEqualTo true} };
switch (_act) do {
    case "open": {
        _arg params [["_key", ""], ["_o", objNull]];
        if (isNull ([] call comspec_atak_native_fnc_display)) then { [true] call comspec_atak_native_fnc_open; };
        _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["wrSheet", _key];
        _s set ["wrTuto", -1];
        if (!isNull _o) then { uiNamespace setVariable ["COMSPEC_ATAK_WrRelay", _o]; };
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY") then { call _render; } else { ["WAVERELAY"] call comspec_atak_native_fnc_navigate; };
    };
    case "close": {
        _s set ["wrSheet", ""];
        call _render;
    };
    case "test": {
        // Mesure du modèle radio du jeu, lue dans la qualité de liaison et le graphe déjà calculés : rien n'est envoyé.
        uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        private _mm = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        private _kb = _q getOrDefault ["kbps", 0];
        private _line = if ((_q getOrDefault ["bars", 0]) <= 0) then {
            private _gate = ["gate"] call comspec_atak_native_fnc_relayNet;
            format ["<t color='%1'>%2</t> %3", "hexAlert" call _hex, "Aucune liaison." call comspec_atak_native_fnc_tr, ([_gate select 2, _q getOrDefault ["label", ""]] select ((_gate select 2) isEqualTo "")) call comspec_atak_native_fnc_tr]
        } else {
            format ["<t color='%1'>%2</t> · %3 · %4 · %5", "hexOk" call _hex, "Liaison joignable" call comspec_atak_native_fnc_tr,
                format ["latence %1 ms" call comspec_atak_native_fnc_tr, _q getOrDefault ["latency", 0]],
                format ["perte %1 %%" call comspec_atak_native_fnc_tr, _q getOrDefault ["loss", 0]],
                [format ["%1 kbit/s", _kb], format ["%1 Mbit/s", (_kb / 1000) toFixed 1]] select (_kb >= 1000)]
        };
        private _cz = _mm getOrDefault ["cause", ""];
        if (_cz isNotEqualTo "") then { _line = _line + format ["<br/><t size='0.88' color='%1'>%2 : %3</t>", "hexWarn" call _hex, "Maillon faible" call comspec_atak_native_fnc_tr, _cz call comspec_atak_native_fnc_tr]; };
        _s set ["wrTest", [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _line]];
        playSound "ClickSoft";
        call _render;
    };
    case "diagLines": {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        private _mesh = ["scan"] call comspec_atak_native_fnc_waveRelayAction;
        private _gate = ["gate"] call comspec_atak_native_fnc_relayNet;
        (["summary"] call comspec_atak_native_fnc_mpuVoice) params ["_vCode", "_vState"];
        private _det = ["detected"] call comspec_atak_native_fnc_mpu5;
        (["get"] call comspec_atak_native_fnc_voicePath) params ["_vp", "_vr"];
        private _yn = { ["non", "oui"] select (_this isEqualTo true) };
        [
            format ["Wave Relay · %1 · position %2 · %3", [player, true] call comspec_atak_native_fnc_unitCallsign, mapGridPosition player, [dayTime, "HH:MM"] call BIS_fnc_timeToString],
            format ["Réseau : %1 · %2 kbit/s · latence %3 ms · perte %4 %%", _q getOrDefault ["label", "?"], _q getOrDefault ["kbps", 0], _q getOrDefault ["latency", 0], _q getOrDefault ["loss", 0]],
            format ["Passerelle : %1 · %2 saut(s) · %3 nœud(s) · %4 lien(s)", [_mesh get "gwLabel", "aucune"] select ((_mesh get "gw") < 0), (_mesh get "hops") param [0, -1], count (_mesh get "nodes"), count (_mesh get "links")],
            format ["Relais exigé : %1 · %2 %3", (["gateOn"] call comspec_atak_native_fnc_relayNet) call _yn, _gate select 1, _gate select 2],
            format ["Radio de données : %1 · %2", [_det param [0, "aucune"], "aucune"] select (_det isEqualTo []), _det param [3, ""]],
            format ["Voix par le réseau : %1 (%2) · chemin : %3 (%4)", _vState, _vCode, _vp, _vr],
            format ["Réglages : liaison réaliste %1 · voix relayée %2 · dégradation %3 · ACRE2 %4 · TFAR %5",
                (["active"] call comspec_atak_native_fnc_mpuLink) call _yn, ((missionNamespace getVariable ["comspec_atak_native_mpu_voice", true]) isEqualTo true) call _yn,
                missionNamespace getVariable ["comspec_atak_native_mesh_voice_degrade", 2], (!isNil "acre_api_fnc_getCurrentRadioList") call _yn, (!isNil "TFAR_fnc_activeSwRadio") call _yn],
            format ["Ce qui gêne : %1", [((_q getOrDefault ["factors", []]) apply { format ["%1 (%2)", _x select 0, _x select 1] }) joinString " ; ", "rien"] select ((_q getOrDefault ["factors", []]) isEqualTo [])]
        ]
    };
    case "diagSend": {
        { diag_log text format ["[COMSPEC ATAK NATIVE][WAVERELAY][DIAG] %1", _x]; } forEach (["diagLines"] call comspec_atak_native_fnc_waveRelaySheet);
        _s set ["wrDiagSent", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        playSound "ClickSoft";
        call _render;
    };
    case "relayAct": {
        _arg params [["_what", ""], ["_v", 0]];
        private _o = uiNamespace getVariable ["COMSPEC_ATAK_WrRelay", objNull];
        if (isNull _o || {!(call _isAdmin)}) exitWith {};
        switch (_what) do {
            case "on": { ["set", [_o, "On", true]] call comspec_atak_native_fnc_relayZeus; };
            case "off": { ["set", [_o, "On", false]] call comspec_atak_native_fnc_relayZeus; };
            case "batt": { ["set", [_o, "Batt", 100]] call comspec_atak_native_fnc_relayZeus; };
            case "freq": { ["set", [_o, "Freq", _v]] call comspec_atak_native_fnc_relayZeus; };
            case "repair": { if (alive _o) then { [_o, 0] remoteExec ["setDamage", _o]; }; };
            case "reset": {
                if (alive _o) then { [_o, 0] remoteExec ["setDamage", _o]; };
                ["set", [_o, "On", true]] call comspec_atak_native_fnc_relayZeus;
                ["set", [_o, "Freq", 0]] call comspec_atak_native_fnc_relayZeus;
                private _b = _o getVariable ["COMSPEC_AtakRelayBatt", -1];
                if (_b isEqualType 0 && {_b >= 0}) then { ["set", [_o, "Batt", 100]] call comspec_atak_native_fnc_relayZeus; };
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_RelayCache", nil, true];
        uiNamespace setVariable ["COMSPEC_ATAK_GateCache", []];
        playSound "ClickSoft";
        [{ call _this; }, _render, 0.4] call comspec_atak_native_fnc_waitAndExec;
    };
    case "rows": {
        private _rows = [];
        switch (_arg) do {
            case "voice": {
                _rows pushBack ["header", "Voix par le réseau", "", [], _back];
                _rows pushBack ["text", "Votre radio garde sa portée normale. Reliée à votre MPU5, sa voix passe aussi par les relais : un équipier relié vous entend derrière une colline ou à 15 km." call _muted];
                private _setOn = (missionNamespace getVariable ["comspec_atak_native_mpu_voice", true]) isEqualTo true;
                private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
                private _gate = ["gate"] call comspec_atak_native_fnc_relayNet;
                private _has = ["has", [player]] call comspec_atak_native_fnc_mpu5;
                private _b = missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []];
                (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []], "", ["_why", ""], ["_peers", []]];
                private _same = { _x param [3, false] } count _peers;
                // Étapes dans l'ordre de la chaîne : [faite, libellé, valeur si faite, valeur sinon, aide]
                private _steps = [
                    [_setOn && {_real}, "1. Réglage du serveur", "activé (par défaut)", ["coupé par un admin", "liaison réaliste coupée"] select _setOn,
                        "Actif par défaut. Pour le couper ou le remettre, un admin ouvre Échap > Réalisme > Maillage > « Voix relayée par le maillage »."],
                    [_gate select 0, "2. Un relais en service à portée", "oui", _gate select 2, ["Sans relais en service à portée, aucun réseau MPU5 ni voix relayée.", _gate select 3] select !(_gate select 0)],
                    [_has, "3. Une MPU5 sur vous, allumée", "oui", "aucune MPU5", "Prenez une radio de données (MPU5) dans l'arsenal et allumez-la."],
                    [_b isNotEqualTo [], "4. Radio reliée à la MPU5", _b param [2, ""], "à faire", "Bouton ci-dessous, ou menu ACE : Téléphone ATAK > Radio de données > Relier ma radio à la MPU5 (voix)."],
                    [_me isNotEqualTo [], "5. MPU5 dans le maillage", "oui", [_why, "en attente"] select (_why isEqualTo ""), "La MPU5 doit joindre un relais, directement ou par les radios des équipiers."],
                    [_same > 0, "6. Un équipier relié, même fréquence", format ["%1 équipier(s)", _same], "personne pour l'instant", "Exemple : vous êtes tous les deux sur 40.000 MHz, chacun avec sa radio reliée à sa MPU5."]
                ];
                private _first = _steps findIf { !(_x select 0) };
                _rows pushBack ["section", ["Tout est prêt", "Où vous en êtes"] select (_first >= 0), ["Parlez à la radio : la voix passe par le réseau quand il porte mieux.", "La première étape en orange est celle à faire."] select (_first >= 0)];
                {
                    _x params ["_done", "_lbl", "_okV", "_koV", "_help"];
                    private _lvl = switch (true) do { case (_done): { "ok" }; case (_forEachIndex isEqualTo _first): { "now" }; default { "todo" }; };
                    _rows pushBack ["check", _lvl, _lbl, [[_koV, "ensuite"] select (_lvl isEqualTo "todo"), _okV] select _done, ["", _help] select (_lvl isNotEqualTo "ok")];
                } forEach _steps;
                if (_setOn && {_real} && {_has}) then {
                    _rows pushBack ["buttons", [[["RELIER MA RADIO À LA MPU5", "DÉBRANCHER MA RADIO"] select (_b isNotEqualTo []), {
                        if ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isEqualTo []) then { ["bridge"] call comspec_atak_native_fnc_mpuVoice; } else { ["unbridge"] call comspec_atak_native_fnc_mpuVoice; };
                        [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
                    }, _b isEqualTo []]]];
                };
                _rows pushBack ["buttons", [["DÉPANNAGE COMPLET", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["wrSheet", ""]; ["open"] call comspec_atak_native_fnc_mpuDiag; }], ["RETOUR", _back]]];
                _rows pushBack ["text", "La qualité de la voix relayée suit le lien : Excellent, Bon, Moyen, Mauvais, Perdu (onglet VOIX). La voix directe et la radio utilisée seule ne changent pas." call _muted];
            };
            case "gateway": {
                _rows pushBack ["header", "Choisir la passerelle", "", [], _back];
                private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
                _rows pushBack ["text", ([
                    "Le téléphone prend seul le meilleur relais reçu et le garde tant qu'il porte encore (20 % de marge au-delà de sa portée, pour ne pas sauter d'un relais à l'autre). Relais perdu : bascule sur le suivant. Vous pouvez en préférer un : il est gardé tant qu'il est reçu.",
                    "Avec une radio de données, le maillage choisit seul le chemin le plus court vers un relais en service, et rebascule quelques secondes après une perte. Le relais préféré ne compte que pour un téléphone sans radio."
                ] select _real) call _muted];
                private _pin = uiNamespace getVariable ["COMSPEC_ATAK_RelayPin", objNull];
                private _list = (["list"] call comspec_atak_native_fnc_relayNet) select { (_x select 3) isNotEqualTo "mpu5" };
                _rows pushBack ["section", format ["Relais du camp (%1)", count _list], ["choix automatique", format ["préféré : %1", _pin getVariable ["COMSPEC_AtakRelayName", "Relais"]]] select (!isNull _pin)];
                {
                    _x params ["_o", "_ap", "_name", "", "_rng", "_mb", "_ok", "_st"];
                    private _l = ["link", [_ap, _rng, _mb, eyePos player]] call comspec_atak_native_fnc_relayNet;
                    private _pct = [0, round (100 * (_l select 0))] select (_ok && {(_l select 1) > 0});
                    _rows pushBack ["meter", _name, format ["%1 · %2%3", [player distance2D _ap] call _dist, [format ["hors service : %1", _st], format ["reçu %1 Mbit/s", (_l select 1) toFixed 1]] select _ok, ["", " · préféré"] select (_o isEqualTo _pin)], _pct,
                        [[], ["hors de portée"]] select (_ok && {_pct <= 0}), compile format ["['pin', objectFromNetId '%1'] call comspec_atak_native_fnc_relayNet; [{ ['WAVERELAY'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", netId _o], _o isEqualTo _pin];
                } forEach _list;
                if (_list isEqualTo []) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "Aucun relais sur le serveur", "Posez un relais : pylône, antenne, kit ou véhicule relais."]; };
                _rows pushBack ["buttons", [["CHOIX AUTOMATIQUE", { ["pin", objNull] call comspec_atak_native_fnc_relayNet; [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, isNull _pin], ["RETOUR", _back]]];
                _rows pushBack ["text", "Touchez un relais pour le préférer, touchez-le encore pour revenir au choix automatique." call _muted];
            };
            case "diag": {
                _rows pushBack ["header", "Signaler un problème", "", [], _back];
                _rows pushBack ["text", "Voici ce que le téléphone sait de votre réseau en ce moment. Rien n'est envoyé sur internet : le bouton écrit ces lignes dans le fichier journal du jeu (RPT), à joindre à votre message sur le Discord." call _muted];
                { _rows pushBack ["text", format ["<t size='0.85'>%1</t>", _x], COMSPEC_ATAK_Th get "surface"]; } forEach (["diagLines"] call comspec_atak_native_fnc_waveRelaySheet);
                private _sent = _s getOrDefault ["wrDiagSent", ""];
                if (_sent isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", "hexOk" call _hex, format ["Écrit dans le journal à %1." call comspec_atak_native_fnc_tr, _sent]]]; };
                _rows pushBack ["buttons", [["ÉCRIRE DANS LE JOURNAL", { ["diagSend"] call comspec_atak_native_fnc_waveRelaySheet; }, true], ["DÉPANNAGE DE LA VOIX", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["wrSheet", ""]; ["open"] call comspec_atak_native_fnc_mpuDiag; }], ["RETOUR", _back]]];
            };
            case "relay": {
                private _o = uiNamespace getVariable ["COMSPEC_ATAK_WrRelay", objNull];
                private _e = if (isNull _o) then { [] } else { ((["list", "force"] call comspec_atak_native_fnc_relayNet) select { (_x select 0) isEqualTo _o }) param [0, []] };
                // Relais d'un autre camp (Zeus) : lu dans la liste de son camp.
                if (_e isEqualTo [] && {!isNull _o} && {call _isAdmin}) then {
                    { private _l = ["list", [_x]] call comspec_atak_native_fnc_relayNet; private _i = _l findIf { (_x select 0) isEqualTo _o }; if (_i >= 0 && {_e isEqualTo []}) then { _e = _l select _i; }; } forEach ["WEST", "EAST", "GUER", "CIV"];
                };
                if (_e isEqualTo []) exitWith {
                    _rows pushBack ["header", "État du relais", "", [], _back];
                    _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", "Relais d'un autre réseau", "Ce relais n'appartient pas au réseau de votre camp : aucune information."];
                };
                _e params ["", "_ap", "_n", "_kind", "_r", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "_root", "", "_fq", "_auto", "_loss", "_hp", "_hl", ["_hopsR", 0], ["_co", 0], ["_lat", 0]];
                _rows pushBack ["header", _n, "", [], _back];
                _rows pushBack ["status", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", COMSPEC_ATAK_Th get (["alert", "ok"] select _ok), [format ["Hors service : %1", _st], format ["En service · %1", _hl]] select _ok,
                    format ["%1 Mbit/s · portée %2 · à %3", _mb toFixed 1, [_r] call _dist, [player distance2D _ap] call _dist],
                    ["Réparez-le ou rallumez-le : tout ce qui passait par lui est coupé.", ""] select _ok];
                private _lvl = { ["ko", "warn", "ok"] select _this };
                _rows append [
                    ["check", [0, 2] select _ok, "Alimentation", [[format ["batterie %1 %%", round _bt], "sur secteur"] select (_bt < 0), "éteint"] select (!(_o getVariable ["COMSPEC_AtakRelayOn", true]))],
                    ["check", [0, 1, 2] select (([0, 1] select (_hp > 30)) + ([0, 1] select (_hp > 75))), "Structure", format ["%1 %%", _hp]],
                    ["check", [1, 2] select (_sl <= 0 || {_u < _sl}), "Charge", [format ["%1 téléphone(s), sans limite", _u], format ["%1 / %2 places", _u, _sl]] select (_sl > 0)],
                    ["check", [0, 1, 2] select (([0, 1] select (_loss < 40)) + ([0, 1] select (_loss < 10))), "Qualité", format ["perte %1 %% · %2 ms", _loss, _lat]],
                    ["check", [1, 2] select (_co <= 0), "Canal", format ["%1 MHz%2%3", _fq, ["", " (auto)"] select _auto, ["", format [" · %1 relais sur le même canal", _co]] select (_co > 0)]],
                    ["check", [0, 2] select (_root || {_hopsR >= 0}), "Liaison", [["répéteur sans relais raccordé en vue", format ["répéteur, %1 bond(s) jusqu'au relais raccordé", _hopsR]] select (_hopsR >= 0), "raccordé (satellite ou fibre)"] select _root]
                ] apply { [_x select 0, (_x select 1) call _lvl, _x select 2, _x select 3] };
                _rows pushBack ["buttons", [["VOIR SUR LA CARTE", { profileNamespace setVariable ["COMSPEC_ATAK_RelaysLayer", true]; ["MAP"] call comspec_atak_native_fnc_navigate; }], ["PRÉFÉRER CE RELAIS", { ["pin", uiNamespace getVariable ["COMSPEC_ATAK_WrRelay", objNull]] call comspec_atak_native_fnc_relayNet; }]]];
                if (call _isAdmin) then {
                    _rows pushBack ["section", "Gestion (Zeus et admin)", "Pour le déplacer : faites-le glisser dans Zeus."];
                    private _on = _o getVariable ["COMSPEC_AtakRelayOn", true];
                    _rows pushBack ["buttons", [
                        [["ALLUMER", "COUPER"] select _on, compile format ["['relayAct', ['%1']] call comspec_atak_native_fnc_waveRelaySheet;", ["on", "off"] select _on], !_on],
                        ["RÉPARER", { ["relayAct", ["repair"]] call comspec_atak_native_fnc_waveRelaySheet; }, false, alive _o && {damage _o > 0}],
                        ["BATTERIE NEUVE", { ["relayAct", ["batt"]] call comspec_atak_native_fnc_waveRelaySheet; }, false, _bt >= 0],
                        ["REMISE À ZÉRO", { ["relayAct", ["reset"]] call comspec_atak_native_fnc_waveRelaySheet; }]
                    ]];
                    _rows pushBack ["segment", "Canal", ([[0, "AUTO"], [4940, "4940"], [4950, "4950"], [4960, "4960"], [4970, "4970"]] apply {
                        [_x select 1, compile format ["['relayAct', ['freq', %1]] call comspec_atak_native_fnc_waveRelaySheet;", _x select 0], [(_x select 0) isEqualTo _fq, _auto] select ((_x select 0) isEqualTo 0)]
                    }), "Deux relais voisins sur le même canal se gênent."];
                };
            };
        };
        _rows
    };
};
