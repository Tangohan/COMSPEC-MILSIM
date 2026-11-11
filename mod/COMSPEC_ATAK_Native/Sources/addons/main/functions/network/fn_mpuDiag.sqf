/*
    Assistant de dépannage de la voix ACRE2 / TFAR relayée par le maillage MPU5 (fn_mpuVoice) : volet « DÉPANNAGE MPU » de l'app Réseau
    (fn_pageNetwork, section « Voix radio par le maillage »). Retour de jeu : « J'ai l'impression que MPU et activation ne marche pas ? … ou faut un relais ? ».
    Lecture seule et locale : aucune écriture chez ACRE2 ou TFAR, aucune requête réseau (ni Athena ni serveur), aucun remoteExec.
    ACRE2 et TFAR ne sont lus que par fn_radioGuard ("api", "has", "compat", "voice"), et seulement si le lien radio n'est pas coupé ("read").
    Étapes, dans l'ordre de la chaîne de la voix : [id, état "OK" | "KO" | "ATTENTION" | "INCONNU", titre, explication avec exemple, action, valeur, correction]
      setting  réglage serveur « Voix relayée par le maillage » (comspec_atak_native_mpu_voice, décoché par défaut)
      real     liaison réaliste (comspec_atak_native_mpu_real, coupée par le profil Arcade : fn_mpuLink "active")
      radio    ACRE2 / TFAR chargé, version connue, coupe-circuit, radio portée
      ts       TeamSpeak et plugin radio : ACRE2 2.14 acre_api_fnc_isVOIPConnected, acre_api_fnc_getVOIPChannelName (API publique, lue dans
               le code d'ACRE2 2.14.0.1064) et le canal attendu par le réglage ACRE2 acre_sys_core_ts3ChannelName ; TFAR TFAR_fnc_isTeamSpeakPluginEnabled,
               TFAR_fnc_getTeamSpeakChannelName et TFAR_Teamspeak_Channel_Name (0.9 : tf_radio_channel_name), noms tirés du dépôt de TFAR,
               TFAR n'étant pas installé sur la machine d'étude : À CONFIRMER EN JEU. Fonction absente : INCONNU, sans erreur.
      mpu      radio de données portée (fn_mpu5 "has"), allumée, batterie, nœud publié, objet obligatoire (fn_netRequire)
      bridge   pont radio (COMSPEC_ATAK_VoiceBridge)
      mesh     maillage (COMSPEC_ATAK_MpuMesh, fn_meshGraph "view") : voisins, relais joints, chef de groupe, sauts, plus proche, gravité ;
               4.2.1 : cause lue dans le graphe partagé (lien manqué, maillon faible), reconvergence, relais hors service le plus proche
      peer     camarade choisi : MPU5 allumée, pontée, même camp, groupes communs, même maillage (même règle que fn_mpuVoice _linked)
      freq     même fréquence (ACRE2 : MHz de la radio pontée, fn_mpu5 "freq" et "voiceNet" ; TFAR : fréquence publiée)
      tfar     TFAR reconnu ou non
      perm     licence et droits : la voix relayée ne dépend pas d'Athena ; seul un admin change les réglages du serveur
    Faut-il un relais ? Non dans deux cas, oui dans le troisième (fn_meshGraph "view" : passerelles, cœur de réseau "vkey" ; fn_mpuVoice _linked) :
      sans relais dans la mission, le maillage d'équipe se fait autour de la MPU5 du chef de groupe (même canal, à portée en sauts) ;
      deux MPU5 qui se joignent en sauts (même canal) s'entendent sans relais, même de groupes différents ;
      au-delà, un relais du même camp (pylône, module, véhicule passerelle, poste de commandement) relie tous les maillages, sur tous les canaux.
      ["run", [netId du camarade]] -> [étapes, faits] (cache 1 s) ; ["steps", [netId]] -> étapes
      ["facts", [netId]] -> HashMap de l'état lu ; ["eval", [faits]] -> étapes (sans effet, testé par l'autotest COMSPEC_Autotest « mpudiag »)
      ["sample"] -> faits d'un cas où tout marche (autotest) ; uiNamespace COMSPEC_ATAK_MpuDiagSim : faits imposés par l'autotest, rien d'autre
      ["first", [étapes]] -> première étape KO, sinon ATTENTION, sinon [] ; ["chain", [étapes]] -> cases de la chaîne (fn_formRender "steps")
      ["chainText", [étapes, couleurs]] -> schéma texte ; ["report", [étapes, faits]] -> rapport à coller sur Discord
      ["rows"] -> lignes fn_formRender du volet ; ["open"] ; ["close"] ; ["peer", [netId]] ; ["fix", [correction]] ; ["copy"] ; ["arm"] ; ["tick", [jeton]]
*/
params [["_act", "run"], ["_arg", []]];
if !(_arg isEqualType []) then { _arg = [_arg]; };
private _rg = { params ["_n", ["_a", []], ["_d", -1]]; ["api", [_n, _a, _d]] call comspec_atak_native_fnc_radioGuard };
private _hasF = { ["has", [_this]] call comspec_atak_native_fnc_radioGuard };
private _distTxt = { params ["_m"]; if (_m >= 1000) then { format ["%1 km", (_m / 1000) toFixed 1] } else { format ["%1 m", round _m] } };
private _rerender = {
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "NETWORK") then {
        [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
};
private _say = { params ["_msg"]; if !([_msg, 4] call comspec_atak_native_fnc_toast) then { ["INFO", _msg, 5, 30] call comspec_atak_native_fnc_notify; }; };
switch (_act) do {
    case "facts": {
        private _pid = _arg param [0, ""];
        if !(_pid isEqualType "") then { _pid = ""; };
        private _f = createHashMap;
        private _str = { params ["_v"]; [str _v, _v] select (_v isEqualType "") };
        _f set ["setOn", (missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]) isEqualTo true];
        // Relais en service pour mon camp (réglage « au moins un relais », fn_mpuVoice « _relayOk »).
        _f set ["relayNeed", (missionNamespace getVariable ["comspec_atak_native_mpu_voice_needRelay", true]) isEqualTo true];
        _f set ["relays", count ((["list", [side group player]] call comspec_atak_native_fnc_relayNet) select { (_x select 3) in ["relay", "gw", "cp"] && {(_x select 6) isNotEqualTo false} })];
        _f set ["realSet", (missionNamespace getVariable ["comspec_atak_native_mpu_real", false]) isEqualTo true];
        _f set ["arcade", (["profile"] call comspec_atak_native_fnc_netCond) isEqualTo 1];
        _f set ["linkOn", (["active"] call comspec_atak_native_fnc_mpuLink) isEqualTo true];
        private _read = (["read"] call comspec_atak_native_fnc_radioGuard) isEqualTo true;
        _f set ["read", _read];
        _f set ["trip", [missionNamespace getVariable ["COMSPEC_ATAK_VoiceTrip", ""]] call _str];
        (["compat"] call comspec_atak_native_fnc_radioGuard) params [["_ca", "absent"], ["_ct", "absent"], ["_va", ""], ["_vt", ""]];
        _f set ["acre", _ca];
        _f set ["tfar", _ct];
        _f set ["acreV", [_va] call _str];
        _f set ["tfarV", [_vt] call _str];
        _f set ["voiceOk", (["voice"] call comspec_atak_native_fnc_radioGuard) isEqualTo true];
        private _radios = if (_read) then { ["radios"] call comspec_atak_native_fnc_mpuVoice } else { [] };
        if !(_radios isEqualType []) then { _radios = []; };
        _f set ["radios", _radios];
        _f set ["mp", isMultiplayer];
        // TeamSpeak et plugin radio : API publique seulement, par fn_radioGuard ; rien n'est lu une fois le lien radio coupé.
        private _tsMod = "";
        private _tsConn = "?";
        private _tsChan = "";
        private _tsWant = "";
        if (_read) then {
            switch (true) do {
                case (_ca isNotEqualTo "absent"): {
                    _tsMod = "acre";
                    if ("acre_api_fnc_isVOIPConnected" call _hasF) then {
                        private _c = ["acre_api_fnc_isVOIPConnected", [], -1] call _rg;
                        if (_c isEqualType true) then { _tsConn = ["no", "yes"] select _c; };
                    };
                    private _ch = ["acre_api_fnc_getVOIPChannelName", [], ""] call _rg;
                    if (_ch isEqualType "") then { _tsChan = _ch; };
                    private _w = missionNamespace getVariable ["acre_sys_core_ts3ChannelName", ""];
                    if (_w isEqualType "") then { _tsWant = _w; };
                };
                case (_ct isNotEqualTo "absent"): {
                    _tsMod = "tfar";
                    if ("TFAR_fnc_isTeamSpeakPluginEnabled" call _hasF) then {
                        private _c = ["TFAR_fnc_isTeamSpeakPluginEnabled", [], -1] call _rg;
                        if (_c isEqualType true) then { _tsConn = ["no", "yes"] select _c; };
                    };
                    if ("TFAR_fnc_getTeamSpeakChannelName" call _hasF) then {
                        private _ch = ["TFAR_fnc_getTeamSpeakChannelName", [], ""] call _rg;
                        if (_ch isEqualType "") then { _tsChan = _ch; };
                    };
                    private _w = missionNamespace getVariable ["TFAR_Teamspeak_Channel_Name", missionNamespace getVariable ["tf_radio_channel_name", ""]];
                    if (_w isEqualType "") then { _tsWant = _w; };
                };
            };
        };
        _f set ["tsMod", _tsMod];
        _f set ["tsConn", _tsConn];
        _f set ["tsChan", _tsChan];
        _f set ["tsWant", _tsWant];
        // Radio de données : MPU5 (la nôtre ou celle d'un mod) ou radio ACRE2 / TFAR reconnue (fn_mpu5 "gear").
        private _gear = ["gear"] call comspec_atak_native_fnc_mpu5;
        if !(_gear isEqualType []) then { _gear = []; };
        _f set ["carried", (["has", [player]] call comspec_atak_native_fnc_mpu5) isEqualTo true];
        _f set ["gearName", [_gear param [3, ""]] call _str];
        _f set ["radioMod", (_gear param [7, ""]) isNotEqualTo ""];
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap];
        if !(_st isEqualType createHashMap) then { _st = createHashMap; };
        _f set ["mpuOn", (_st getOrDefault ["on", true]) isEqualTo true];
        _f set ["batt", round (_st getOrDefault ["batt", 100])];
        _f set ["chan", _st getOrDefault ["chan", 1]];
        _f set ["spare", "COMSPEC_ATAK_RadioBattery" in (items player)];
        private _node = player getVariable ["COMSPEC_ATAK_MpuNode", []];
        if !(_node isEqualType []) then { _node = []; };
        _f set ["node", _node isNotEqualTo []];
        _f set ["range", _node param [1, 0]];
        private _den = ["denied"] call comspec_atak_native_fnc_netRequire;
        _f set ["denied", if (_den isEqualType [] && {_den isNotEqualTo []}) then { [["labels", [_den]] call comspec_atak_native_fnc_netRequire] call _str } else { "" }];
        // Maillage lu par fn_mpuLink (graphe unique fn_meshGraph) : aucun calcul ici.
        private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        if !(_m isEqualType createHashMap) then { _m = createHashMap; };
        _f set ["meshFresh", (_m getOrDefault ["key", ""]) isEqualTo (_node param [0, "?"]) && {diag_tickTime - (_m getOrDefault ["at", -1e6]) <= 20}];
        _f set ["meshOk", (_m getOrDefault ["ok", false]) isEqualTo true];
        _f set ["meshWhy", [_m getOrDefault ["why", ""]] call _str];
        _f set ["meshMode", [_m getOrDefault ["mode", ""]] call _str];
        _f set ["meshHops", _m getOrDefault ["hops", -1]];
        _f set ["meshGw", [_m getOrDefault ["gw", ""]] call _str];
        _f set ["meshLevel", _m getOrDefault ["level", 3]];
        // Cause lue dans le graphe partagé (4.2.1, fn_meshGraph "view") : relief, distance, bâtiments, canal, brouillage, reconvergence.
        _f set ["meshCause", [_m getOrDefault ["cause", ""]] call _str];
        _f set ["meshFix", [_m getOrDefault ["fix", ""]] call _str];
        _f set ["meshReconv", (_m getOrDefault ["reconv", 0]) isEqualType 0 && {(_m getOrDefault ["reconv", 0]) > 0}];
        // Relais du camp hors service le plus proche (liste locale de fn_relayNet, en cache 2 s) : souvent la vraie raison d'une coupure.
        private _down = "";
        private _downD = 1e9;
        {
            _x params ["_ro", "_ra", "_rn", "_rk", "_rr", "", "_rOk", "_rSt"];
            if (_rOk isEqualTo true || {!(_rk in ["relay", "gw", "cp"])} || {!(_ra isEqualType [])}) then { continue };
            private _d = player distance2D _ra;
            if (_d < _downD && {_d < 3 * (_rr max 500)}) then { _downD = _d; _down = format ["%1 à %2 : %3", _rn, [_d] call _distTxt, _rSt]; };
        } forEach (["list"] call comspec_atak_native_fnc_relayNet);
        _f set ["relayDown", [_down] call _str];
        private _nl = _m getOrDefault ["nodes", []];
        if !(_nl isEqualType []) then { _nl = []; };
        private _near = -1;
        { if ((_x param [2, -1]) >= 0) then { private _d = _x param [3, -1]; if (_d >= 0 && {_near < 0 || {_d < _near}}) then { _near = _d; }; }; } forEach _nl;
        _f set ["nbNodes", { (_x param [2, -1]) >= 0 } count _nl];
        _f set ["nbRelays", { (_x param [1, ""]) isEqualTo "gw" && {(_x param [2, -1]) >= 0} } count _nl];
        _f set ["near", _near];
        private _ld = leader group player;
        _f set ["isLead", _ld isEqualTo player];
        _f set ["lead", [[_ld, true] call comspec_atak_native_fnc_unitCallsign] call _str];
        _f set ["maxHops", ["maxHops"] call comspec_atak_native_fnc_meshGraph];
        _f set ["gravity", (missionNamespace getVariable ["comspec_atak_native_mesh_gravity", true]) isEqualTo true];
        // Voix : pont, état publié, joueurs pontés joignables (fn_mpuVoice "tick").
        private _vb = missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []];
        if !(_vb isEqualType []) then { _vb = []; };
        _f set ["bridge", _vb];
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_pub", []], ["_reach", createHashMap], ["_vwhy", ""], ["_peers", []]];
        if !(_pub isEqualType []) then { _pub = []; };
        if !(_reach isEqualType createHashMap) then { _reach = createHashMap; };
        _f set ["pub", _pub];
        _f set ["vwhy", [_vwhy] call _str];
        _f set ["reachN", count _reach];
        _f set ["peersN", [0, count _peers] select (_peers isEqualType [])];
        _f set ["keysOn", (missionNamespace getVariable ["comspec_atak_native_mesh_keys", false]) isEqualTo true];
        _f set ["keysOk", (_pub isEqualTo []) || {(["voiceOk"] call comspec_atak_native_fnc_meshKeys) isEqualTo true}];
        // Fréquence de la radio pontée : MHz (ACRE2) ou texte publié (TFAR).
        private _myF = "";
        private _myN = -1;
        if (_vb isNotEqualTo [] && {_read}) then {
            if ((_vb param [1, ""]) isEqualTo "acre") then {
                private _fr = ["freq", [_vb param [0, ""]]] call comspec_atak_native_fnc_mpu5;
                if (_fr isEqualType [] && {_fr isNotEqualTo []} && {(_fr select 0) isEqualType 0}) then { _myN = _fr select 0; _myF = format ["%1 MHz", _myN toFixed 3]; };
            } else {
                _myF = [_pub param [2, ""]] call _str;
            };
        };
        _f set ["myFreq", _myF];
        _f set ["myFreqN", _myN];
        // Camarade choisi : ce qu'il publie (fn_meshRead, valeurs vérifiées) et ce que mon maillage voit de lui.
        private _p = createHashMap;
        if (_pid isNotEqualTo "") then {
            private _pu = objectFromNetId _pid;
            _p set ["id", _pid];
            _p set ["ok", !isNull _pu && {alive _pu} && {isPlayer _pu} && {_pu isNotEqualTo player}];
            _p set ["name", if (isNull _pu) then { "?" } else { [[_pu, true] call comspec_atak_native_fnc_unitCallsign] call _str }];
            if (!isNull _pu) then {
                _p set ["sameSide", (side group _pu) isEqualTo (side group player)];
                _p set ["dist", round (player distance _pu)];
                _p set ["node", (["node", _pu] call comspec_atak_native_fnc_meshRead) isNotEqualTo []];
                private _pv = ["voice", _pu] call comspec_atak_native_fnc_meshRead;
                if !(_pv isEqualType []) then { _pv = []; };
                _p set ["voice", _pv];
                _p set ["groups", (["shared", [_pu]] call comspec_atak_native_fnc_meshGroups) isEqualTo true];
                _p set ["direct", _reach getOrDefault [_pid, -1]];
                // Même règle que fn_mpuVoice (_linked) : cœur de réseau commun (somme des sauts sous le plafond), sinon nœud de mon maillage.
                private _h = -1;
                if (_pub isNotEqualTo [] && {(count _pv) >= 7}) then {
                    private _mk = _pub param [4, ""];
                    if (_mk isNotEqualTo "" && {_mk isEqualTo (_pv select 4)}) then {
                        private _sum = ((_pub param [5, 0]) max 0) + ((_pv select 5) max 0);
                        if (_sum <= (["maxHops"] call comspec_atak_native_fnc_meshGraph)) then { _h = _sum; };
                    } else {
                        _h = _reach getOrDefault [_pid, -1];
                    };
                };
                _p set ["hops", _h];
                _p set ["api", _pv param [0, ""]];
                private _pf = "";
                private _pn = -1;
                if ((count _pv) >= 7) then {
                    if ((_pv select 0) isEqualTo "acre") then {
                        private _net = ["voiceNet", _pu] call comspec_atak_native_fnc_meshRead;
                        if !(_net isEqualType []) then { _net = []; };
                        private _i = _net findIf { (_x param [0, ""]) isEqualTo (_pv select 1) };
                        if (_i >= 0 && {((_net select _i) param [1, -1]) isEqualType 0}) then { _pn = (_net select _i) select 1; _pf = format ["%1 MHz", _pn toFixed 3]; };
                    } else {
                        _pf = [_pv select 2] call _str;
                    };
                };
                _p set ["freq", _pf];
                _p set ["freqN", _pn];
            };
        };
        _f set ["peer", _p];
        _f set ["admin", ([player] call comspec_atak_native_fnc_adminAllowed) isEqualTo true];
        _f set ["athena", (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) isEqualTo true];
        _f set ["modVer", getText (configFile >> "CfgPatches" >> "comspec_atak_native_main" >> "versionStr")];
        _f set ["who", name player];
        _f set ["side", str (side group player)];
        _f
    };
    case "sample": {
        // Cas où tout marche (autotest) : ACRE2 2.14 relié à TeamSpeak, MPU5 allumée et pontée, maillage d'équipe, camarade ponté sur la même fréquence.
        createHashMapFromArray [
            ["setOn", true], ["realSet", true], ["arcade", false], ["linkOn", true], ["read", true], ["trip", ""],
            ["acre", "ok"], ["tfar", "absent"], ["acreV", "2.14.0.1064"], ["tfarV", ""], ["voiceOk", true],
            ["radios", [["ACRE_PRC152_ID_1", "acre", "AN/PRC-152"]]], ["mp", true],
            ["tsMod", "acre"], ["tsConn", "yes"], ["tsChan", "ACRE"], ["tsWant", ""],
            ["carried", true], ["gearName", "MPU5"], ["radioMod", false], ["mpuOn", true], ["batt", 80], ["chan", 1], ["spare", false],
            ["node", true], ["range", 6000], ["denied", ""],
            ["meshFresh", true], ["meshOk", true], ["meshWhy", ""], ["meshMode", "lead"], ["meshHops", 1], ["meshGw", "Alpha 1"], ["meshLevel", 0],
            ["nbNodes", 3], ["nbRelays", 0], ["near", 450], ["isLead", false], ["lead", "Alpha 1"], ["maxHops", 6], ["gravity", true],
            ["bridge", ["ACRE_PRC152_ID_1", "acre", "AN/PRC-152"]], ["pub", ["acre", "ACRE_PRC152_ID_1", "", 0, "L2:15", 1, "AN/PRC-152"]], ["vwhy", ""],
            ["reachN", 3], ["peersN", 1], ["keysOn", false], ["keysOk", true], ["myFreq", "152.000 MHz"], ["myFreqN", 152],
            ["peer", createHashMapFromArray [["id", "2:16"], ["ok", true], ["name", "Alpha 2"], ["sameSide", true], ["dist", 1200], ["node", true],
                ["voice", ["acre", "ACRE_PRC152_ID_2", "", 0, "L2:15", 1, "AN/PRC-152"]], ["groups", true], ["direct", 2], ["hops", 2], ["api", "acre"],
                ["freq", "152.000 MHz"], ["freqN", 152]]],
            ["relayNeed", true], ["relays", 1],
            ["admin", false], ["athena", false], ["modVer", "4.9.0"], ["who", "Test"], ["side", "WEST"]
        ]
    };
    case "eval": {
        private _f = _arg param [0, createHashMap];
        if !(_f isEqualType createHashMap) then { _f = createHashMap; };
        private _g = { params ["_k", "_d"]; private _v = _f getOrDefault [_k, _d]; [_d, _v] select (_v isEqualType _d) };
        private _out = [];
        private _add = { params ["_id", "_st", "_t", "_e", ["_a", ""], ["_v", ""], ["_fx", ""]]; _out pushBack [_id, _st, _t, _e, _a, _v, _fx]; };
        private _admin = ["admin", false] call _g;
        // Jamais un réglage du serveur changé sans droits : l'admin a le chemin, les autres le demandent.
        private _askAdmin = { params ["_what"]; [format ["À demander à l'admin : Échap › Réalisme › %1.", _what], format ["Ouvrez Échap › Réalisme, réglez %1, puis Appliquer au serveur.", _what]] select _admin };
        // 1. Réglage du serveur.
        private _setOn = ["setOn", false] call _g;
        if (_setOn) then {
            ["setting", "OK", "Réglage du serveur", "La voix relayée par le maillage est autorisée sur ce serveur.", "", "coché"] call _add;
        } else {
            ["setting", "KO", "Réglage du serveur", "La voix relayée est coupée par défaut : tant que ce réglage est décoché, votre radio garde sa portée normale, même avec une MPU5 (exemple : une AN/PRC-152 porte quelques kilomètres, pas plus).",
                ["« Voix relayée par le maillage » (coché)"] call _askAdmin, "décoché", "settings"] call _add;
        };
        // 1 bis. Au moins un relais dans la mission.
        if (["relayNeed", true] call _g) then {
            if ((["relays", 0] call _g) > 0) then {
                ["relay", "OK", "Relais de la mission", "La mission a au moins un relais en service pour votre camp : la voix peut passer par le maillage.", "", format ["%1 relais", ["relays", 0] call _g]] call _add;
            } else {
                // Relais présent mais hors service (4.2.1) : la vraie raison est dite, avec ce qui l'a mis hors service.
                private _rd = ["relayDown", ""] call _g;
                if (_rd isNotEqualTo "") then {
                    ["relay", "KO", "Relais de la mission", format ["Aucun relais en service pour votre camp. Le plus proche est hors service (%1) : sans relais, la voix ne passe pas par le maillage, même entre deux MPU5 voisines.", _rd],
                        "Remettez ce relais en service (batterie, interrupteur, réparation) ou posez-en un autre (module « Poser un relais », antenne transportable).", "hors service", "wave"] call _add;
                } else {
                    ["relay", "KO", "Relais de la mission", "Aucun relais en service pour votre camp : sans relais, la voix ne passe pas par le maillage, même entre deux MPU5 voisines (exemple : carte sans pylône ni module « Poser un relais »).",
                        "Posez un relais (module « Poser un relais », pylône, véhicule passerelle ou poste de commandement), ou demandez à l'admin de décocher « Voix par le maillage : au moins un relais ».", "aucun", "settings"] call _add;
                };
            };
        };
        // 2. Liaison réaliste.
        private _linkOn = ["linkOn", false] call _g;
        switch (true) do {
            case _linkOn: { ["real", "OK", "Liaison réaliste des radios", "Les radios de données forment un vrai maillage : c'est lui qui porte la voix relayée.", "", "active"] call _add; };
            case (["arcade", false] call _g): {
                ["real", "KO", "Liaison réaliste des radios", "Le profil Arcade coupe la liaison réaliste, donc la voix relayée (exemple : en Arcade, réseau parfait, mais aucune MPU5 ne relaie la voix).",
                    ["« Profil de réalisme » autre que Arcade"] call _askAdmin, "profil Arcade", "settings"] call _add;
            };
            default {
                ["real", "KO", "Liaison réaliste des radios", "La voix relayée passe par la liaison réaliste des radios de données : décochée, aucune MPU5 ne relaie rien (exemple : la MPU5 reste un objet inerte).",
                    ["« Liaison téléphone-radio réaliste » (coché)"] call _askAdmin, "décochée", "settings"] call _add;
            };
        };
        // 3. Radio ACRE2 / TFAR.
        private _read = ["read", true] call _g;
        private _trip = ["trip", ""] call _g;
        private _ca = ["acre", "absent"] call _g;
        private _ct = ["tfar", "absent"] call _g;
        private _va = ["acreV", ""] call _g;
        private _vt = ["tfarV", ""] call _g;
        private _radios = ["radios", []] call _g;
        private _modTxt = switch (true) do { case (_ca isNotEqualTo "absent"): { format ["ACRE2 %1", _va] }; case (_ct isNotEqualTo "absent"): { format ["TFAR %1", _vt] }; default { "aucune" }; };
        switch (true) do {
            case (!_read): {
                ["radio", "KO", "Radio ACRE2 / TFAR", "Le réglage admin « Couper tout lien avec la radio » est coché : le mod ne touche plus à la radio, la voix n'est jamais relayée.",
                    ["« Couper tout lien avec la radio » (décoché)"] call _askAdmin, "lien coupé", "settings"] call _add;
            };
            case (_trip isNotEqualTo ""): {
                ["radio", "KO", "Radio ACRE2 / TFAR", format ["Le coupe-circuit a coupé la voix relayée pour la session (%1) : la radio reste normale.", _trip],
                    "Reconnectez-vous à la mission, puis copiez le rapport pour l'équipe COMSPEC (salon support).", "coupe-circuit"] call _add;
            };
            case (_ca isEqualTo "absent" && {_ct isEqualTo "absent"}): {
                ["radio", "KO", "Radio ACRE2 / TFAR", "Ni ACRE2 ni TFAR n'est chargé : il n'y a pas de voix radio à relayer (exemple : sans eux, la MPU5 ne porte que les données du téléphone).",
                    "Chargez ACRE2 (ou TFAR) sur le serveur et chez chaque joueur.", "aucune"] call _add;
            };
            case ("inconnu" in [_ca, _ct] && {!(["voiceOk", false] call _g)}): {
                ["radio", "KO", "Radio ACRE2 / TFAR", format ["Version non reconnue (%1) : par sécurité, la voix relayée reste coupée, la radio marche normalement.", _modTxt],
                    "Utilisez ACRE2 2.14 ou TFAR 1.x, les versions lues dans leur code.", "version inconnue"] call _add;
            };
            case (_radios isEqualTo []): {
                ["radio", "KO", "Radio ACRE2 / TFAR", "Aucune radio ACRE2 ou TFAR sur vous : rien à relier à la MPU5 (exemple : une AN/PRC-152 dans la veste suffit).",
                    "Prenez une radio dans l'arsenal ou l'inventaire, puis ACTUALISER.", "aucune radio"] call _add;
            };
            default {
                ["radio", "OK", "Radio ACRE2 / TFAR", format ["%1 reconnu : la radio active est proposée pour le pont (exemple : %2).", _modTxt, (_radios select 0) param [2, "?"]], "", format ["%1 radio(s)", count _radios]] call _add;
            };
        };
        // 4. TeamSpeak et plugin radio.
        private _tsMod = ["tsMod", ""] call _g;
        private _tsC = ["tsConn", "?"] call _g;
        private _tsChan = ["tsChan", ""] call _g;
        private _tsWant = ["tsWant", ""] call _g;
        private _tsT = "TeamSpeak / plugin radio";
        switch (true) do {
            case (_tsMod isEqualTo ""): { ["ts", "INCONNU", _tsT, "Sans ACRE2 ni TFAR (ou lien radio coupé), rien à vérifier côté TeamSpeak.", "", "sans objet"] call _add; };
            case !(["mp", false] call _g): {
                ["ts", "INCONNU", _tsT, "Partie solo : ACRE2 et TFAR ne se relient à TeamSpeak qu'en multijoueur (exemple : en solo, la radio ne transmet aucune voix).",
                    "Testez sur le serveur de la communauté, en multijoueur.", "partie solo"] call _add;
            };
            case (_tsC isEqualTo "no"): {
                ["ts", "KO", _tsT, "Sans plugin, ni la radio ni le relais MPU ne portent la voix : la MPU5 ne relaie que ce que la radio capte. Exemple : TeamSpeak fermé, personne ne vous entend, même à 2 m.",
                    ["Ouvrez TeamSpeak 3, connectez-vous au serveur de la communauté, vérifiez que le plugin ACRE2 est activé (Outils › Options › Addons), puis rejoignez le canal de la partie (exemple : canal ACRE).",
                     "Ouvrez TeamSpeak 3, connectez-vous au serveur de la communauté, vérifiez que le plugin Task Force Radio est activé (Outils › Options › Addons), puis rejoignez le canal de la partie (exemple : canal TaskForceRadio)."] select (_tsMod isEqualTo "tfar"),
                    "plugin non connecté"] call _add;
            };
            case (_tsC isEqualTo "?"): {
                ["ts", "INCONNU", _tsT, "Cette version du mod radio ne dit pas si TeamSpeak est relié (fonction absente).", "Vérifiez vous-même : TeamSpeak 3 ouvert, plugin activé, canal de la partie.", "non lisible"] call _add;
            };
            case (_tsWant isNotEqualTo "" && {_tsChan isNotEqualTo ""} && {(toLower _tsWant) isNotEqualTo (toLower _tsChan)}): {
                ["ts", "ATTENTION", _tsT, format ["Vous êtes dans le canal TeamSpeak « %1 », la partie attend « %2 » : les joueurs d'un autre canal ne s'entendent pas à la radio.", _tsChan, _tsWant],
                    format ["Rejoignez le canal « %1 » dans TeamSpeak.", _tsWant], "autre canal"] call _add;
            };
            default {
                ["ts", "OK", _tsT, "Le plugin radio est relié à TeamSpeak : la radio peut porter la voix. Tous les joueurs doivent être dans le même canal TeamSpeak.", "",
                    ["connecté", format ["canal %1", _tsChan]] select (_tsChan isNotEqualTo "")] call _add;
            };
        };
        // 5. Radio de données.
        private _carried = ["carried", false] call _g;
        private _rm = ["radioMod", false] call _g;
        private _batt = ["batt", 100] call _g;
        private _node = ["node", false] call _g;
        private _den = ["denied", ""] call _g;
        private _spare = ["spare", false] call _g;
        private _mpuT = "MPU5 (radio de données)";
        switch (true) do {
            case (_den isNotEqualTo ""): {
                ["mpu", "KO", _mpuT, format ["Il manque l'objet exigé par le serveur pour faire partie du réseau : %1.", _den], "Prenez cet objet dans l'arsenal ou le sac.", "objet manquant"] call _add;
            };
            case (!_carried): {
                ["mpu", "KO", _mpuT, "Aucune radio de données sur vous : la voix passe par la MPU5 que vous portez. Exemple : une MPU5 posée au sol relaie les données, pas votre voix.",
                    "Prenez une MPU5 (objet MPU5 ou ACRE MPU5) dans l'inventaire.", "aucune MPU5"] call _add;
            };
            case !(["mpuOn", true] call _g): {
                ["mpu", "KO", _mpuT, "Votre MPU5 est éteinte : elle ne relaie plus rien, ni données ni voix.", "Allumez-la : bouton ci-dessous, ou ACE Téléphone ATAK › Radio de données › Allumer la MPU5.", "éteinte", "power"] call _add;
            };
            case (!_rm && {_batt <= 0}): {
                ["mpu", "KO", _mpuT, "Batterie de la MPU5 vide : radio éteinte, plus de maillage.", "Changez la batterie (objet Batterie de radio dans l'inventaire).", "batterie vide", ["", "battery"] select _spare] call _add;
            };
            case (!_node): {
                ["mpu", "ATTENTION", _mpuT, "La MPU5 n'est pas encore un nœud du réseau (premier calcul, environ 2 s).", "Patientez quelques secondes, puis ACTUALISER.", "démarrage"] call _add;
            };
            case (!_rm && {_batt <= 20}): {
                ["mpu", "ATTENTION", _mpuT, format ["Batterie faible : %1 %% (exemple : à 0 %%, la MPU5 s'éteint et quitte le maillage).", _batt], "Prévoyez une batterie de rechange (objet Batterie de radio).", format ["batterie %1 %%", _batt], ["", "battery"] select _spare] call _add;
            };
            default {
                ["mpu", "OK", _mpuT, format ["%1 allumée : elle relaie la voix et les données autour de vous, sur son canal.", ["gearName", "MPU5"] call _g], "",
                    [format ["canal %1 · %2", ["chan", 1] call _g, [["range", 0] call _g] call _distTxt], format ["canal %1 · %2 %% · %3", ["chan", 1] call _g, _batt, [["range", 0] call _g] call _distTxt]] select !_rm] call _add;
            };
        };
        // 6. Pont radio.
        private _vb = ["bridge", []] call _g;
        private _canBridge = _setOn && {_linkOn} && {["voiceOk", false] call _g} && {_carried} && {_radios isNotEqualTo []};
        if (_vb isEqualTo []) then {
            ["bridge", "KO", "Pont radio", "Le pont radio est le câble audio entre votre radio et la MPU5 : sans lui, la radio garde sa portée normale (exemple : AN/PRC-152 non reliée, aucun relais).",
                "Activer le pont radio : bouton ci-dessous, ou ACE Téléphone ATAK › Radio de données › Relier ma radio à la MPU5 (voix).", "non ponté", ["", "bridge"] select _canBridge] call _add;
        } else {
            ["bridge", "OK", "Pont radio", format ["Radio %1 reliée à la MPU5 : sa voix part dans le maillage.", _vb param [2, "?"]], "", _vb param [2, "?"]] call _add;
        };
        // 7. Maillage.
        private _mOk = ["meshOk", false] call _g;
        private _mMode = ["meshMode", ""] call _g;
        private _mHops = ["meshHops", -1] call _g;
        private _mGw = ["meshGw", ""] call _g;
        private _mLvl = (((["meshLevel", 3] call _g) max 0) min 3);
        private _nN = ["nbNodes", 0] call _g;
        private _nR = ["nbRelays", 0] call _g;
        private _near = ["near", -1] call _g;
        private _maxH = ["maxHops", 6] call _g;
        private _pub = ["pub", []] call _g;
        private _lvlTxt = ["signal fort", "signal réduit", "signal faible", "signal coupé"] select _mLvl;
        private _detail = format ["Chef de groupe : %1 · plus proche : %2 · %3", ["lead", "?"] call _g, ["aucun", [_near] call _distTxt] select (_near >= 0), _lvlTxt];
        private _mVal = format ["%1 voisin(s) · %2 relais · %3 saut(s)", _nN, _nR, _mHops max 0];
        private _mT = "Maillage";
        switch (true) do {
            case (!_linkOn || {!_carried} || {!_node}): {
                ["mesh", "INCONNU", _mT, "Pas de maillage à lire tant que la liaison réaliste et la MPU5 ne sont pas en service.", "Corrigez d'abord les étapes au-dessus.", "en attente"] call _add;
            };
            case !(["meshFresh", false] call _g): {
                ["mesh", "ATTENTION", _mT, "Lecture du maillage en cours : le graphe est relu toutes les 4 s tant que cet écran est ouvert.", "Patientez quelques secondes, puis ACTUALISER.", "lecture en cours"] call _add;
            };
            case (["meshReconv", false] call _g): {
                ["mesh", "ATTENTION", _mT, format ["Réseau en reconvergence (%1). Comme un vrai réseau maillé, les radios recalculent leurs routes pendant quelques secondes (réglage « Reconvergence après la perte d'un relais »).", ["meshWhy", ""] call _g],
                    "Patientez quelques secondes, puis ACTUALISER : s'il reste un autre chemin, le réseau repasse par lui.", "reconvergence", "wave"] call _add;
            };
            case (!_mOk): {
                // Cause lue dans le graphe (4.2.1) : le lien qui manque et ce qui le coupe ; sinon l'exemple général d'avant.
                private _cz = ["meshCause", ""] call _g;
                private _fx = ["meshFix", ""] call _g;
                private _rd = ["relayDown", ""] call _g;
                private _ex = [format ["Hors maillage (%1). Exemple : derrière une crête, à 7 km de l'équipe, plus aucun saut.", ["meshWhy", ""] call _g],
                    format ["Hors maillage : %1.", _cz]] select (_cz isNotEqualTo "");
                if (_rd isNotEqualTo "") then { _ex = _ex + "<br/>" + format ["Relais hors service à proximité : %1.", _rd]; };
                ["mesh", "KO", _mT, _ex,
                    ["Rapprochez-vous d'un équipier ou d'un relais, montez sur un point haut, ou mettez la MPU5 sur le canal de l'équipe.", _fx] select (_fx isNotEqualTo ""), "hors maillage", "wave"] call _add;
            };
            case (_mLvl >= 3): {
                ["mesh", "KO", _mT, format ["Le maillon le plus faible du chemin est trop faible : la voix ne reçoit aucune rallonge (gravité de coupure).<br/>%1", _detail],
                    "Rapprochez-vous du maillon faible ou montez sur un point haut (app Wave Relay, onglet RÉSEAU).", "signal coupé", "wave"] call _add;
            };
            case (_vb isNotEqualTo [] && {_pub isEqualTo []}): {
                private _w = ["vwhy", ""] call _g;
                ["mesh", "ATTENTION", _mT, format ["Radio pontée, voix en attente : %1.", ["hors du maillage", _w] select (_w isNotEqualTo "")], "Patientez quelques secondes, puis ACTUALISER.", "en attente"] call _add;
            };
            case ((["keysOn", false] call _g) && {!(["keysOk", true] call _g)}): {
                ["mesh", "KO", _mT, "Votre MPU5 n'a pas la clé du groupe de votre radio : les données passent, la voix ne reçoit aucune rallonge.", "Chargez la clé (chargeur de clés ou poste de commandement).", "clé absente"] call _add;
            };
            case (_mMode isEqualTo "self" && {(["reachN", 0] call _g) <= 0}): {
                ["mesh", "ATTENTION", _mT, format ["Seul dans votre maillage : aucune autre MPU5 du même canal à portée, et aucun relais.<br/>%1", _detail],
                    "Rapprochez-vous d'un équipier, ou faites poser un relais (pylône, module « Poser un relais », véhicule passerelle).", "aucun voisin", "wave"] call _add;
            };
            default {
                private _why = switch (_mMode) do {
                    case "relay": { format ["Votre MPU5 joint le relais %1 en %2 saut(s) : vous êtes dans le cœur de réseau du camp, sur tous les canaux.", _mGw, _mHops] };
                    case "lead": { format ["Votre MPU5 joint celle du chef de groupe %1 en %2 saut(s) : maillage d'équipe, sans relais.", _mGw, _mHops] };
                    default { ["Maillage local : seules les MPU5 que vous joignez en sauts (même canal) vous entendent.", "Vous êtes chef de groupe : votre MPU5 est le cœur du maillage d'équipe, sans relais."] select (["isLead", false] call _g) };
                };
                // Chemin fragile (4.2.1) : le maillon faible et sa cause, et le conseil qui va avec.
                private _cz = ["meshCause", ""] call _g;
                private _fx = ["meshFix", ""] call _g;
                if (_cz isNotEqualTo "" && {_mLvl >= 1}) then { _detail = _detail + "<br/>" + format ["Limite : %1.", _cz]; };
                ["mesh", ["OK", "ATTENTION"] select (_mLvl >= 2), _mT, format ["%1<br/>%2", _why, _detail],
                    ["", ["Lien faible : rapprochez-vous du maillon faible ou montez sur un point haut.", _fx] select (_fx isNotEqualTo "")] select (_mLvl >= 2), _mVal, ["", "wave"] select (_mLvl >= 2)] call _add;
            };
        };
        // 8. Camarade choisi.
        private _p = ["peer", createHashMap] call _g;
        private _pn = _p getOrDefault ["name", "?"];
        private _pT = "Camarade choisi";
        if ((count _p) isEqualTo 0) then {
            ["peer", "INCONNU", _pT, "Aucun camarade choisi : seul votre côté est vérifié.", "Choisissez un camarade dans « Tester avec un camarade » pour vérifier aussi son côté.", "aucun"] call _add;
        } else {
            switch (true) do {
                case !(_p getOrDefault ["ok", false]): { ["peer", "KO", _pT, format ["%1 est introuvable : déconnecté, mort ou pas un joueur.", _pn], "Choisissez un autre camarade.", "introuvable"] call _add; };
                case !(_p getOrDefault ["sameSide", true]): { ["peer", "KO", _pT, format ["%1 n'est pas de votre camp : les relais et le cœur de réseau sont propres à chaque camp.", _pn], "Choisissez un camarade de votre camp.", "autre camp"] call _add; };
                case !(_p getOrDefault ["node", false]): {
                    ["peer", "KO", _pT, format ["La MPU5 de %1 est éteinte, absente ou à court de batterie : de son côté, rien ne relaie.", _pn],
                        "Demandez-lui d'allumer sa MPU5 : ACE Téléphone ATAK › Radio de données › Allumer la MPU5.", "MPU5 éteinte"] call _add;
                };
                case ((_p getOrDefault ["voice", []]) isEqualTo []): {
                    ["peer", "KO", _pT, format ["%1 n'a pas ponté sa radio, ou sa MPU5 est hors maillage de son côté.", _pn],
                        "Demandez-lui : ACE Téléphone ATAK › Radio de données › Relier ma radio à la MPU5 (voix), puis d'ouvrir cet assistant chez lui.", "non ponté"] call _add;
                };
                case !(_p getOrDefault ["groups", true]): {
                    ["peer", "KO", _pT, format ["Vous et %1 n'écoutez aucun groupe de conversation commun du maillage.", _pn], "Choisissez un groupe commun dans l'app Wave Relay, onglet VOIX.", "groupes différents", "wave"] call _add;
                };
                case (_pub isEqualTo []): {
                    ["peer", "INCONNU", _pT, "Votre propre voix n'est pas encore dans le maillage : le lien avec lui ne peut pas être calculé.", "Corrigez d'abord vos étapes.", "en attente"] call _add;
                };
                case ((_p getOrDefault ["hops", -1]) < 0): {
                    ["peer", "KO", _pT, format ["%1 n'est pas dans votre maillage : ni relais commun, ni même chef de groupe, ni chemin de MPU5 en MPU5 (au plus %2 sauts).", _pn, _maxH],
                        "Rejoignez un relais du camp (pylône, module « Poser un relais », véhicule passerelle) : il relie tous les maillages.", "hors maillage", "wave"] call _add;
                };
                default {
                    ["peer", "OK", _pT, format ["MPU5 allumée et radio pontée chez %1, dans votre maillage. Son TeamSpeak ne se voit pas d'ici : en cas de doute, qu'il ouvre cet assistant.", _pn], "",
                        format ["%1 saut(s) · %2", _p getOrDefault ["hops", 0], [_p getOrDefault ["dist", 0]] call _distTxt]] call _add;
                };
            };
        };
        // 9. Même fréquence.
        private _myF = ["myFreq", ""] call _g;
        private _myN = ["myFreqN", -1] call _g;
        private _pv = _p getOrDefault ["voice", []];
        private _freqT = "Même fréquence";
        switch (true) do {
            case (_vb isEqualTo []): { ["freq", "INCONNU", _freqT, "La fréquence comptée est celle de la radio pontée : pas de pont, rien à comparer.", "Activez d'abord le pont radio.", "sans pont"] call _add; };
            case ((count _p) isEqualTo 0 || {_pv isEqualTo []}): {
                ["freq", "INCONNU", _freqT, format ["Votre radio pontée est sur %1 : votre camarade doit régler exactement la même. ACRE2 et TFAR choisissent qui s'entend, la MPU5 ne change pas la fréquence.", ["une fréquence illisible", _myF] select (_myF isNotEqualTo "")],
                    "Comparez avec votre camarade (exemple : tous les deux sur 45.000 MHz).", ["inconnue", _myF] select (_myF isNotEqualTo "")] call _add;
            };
            case ((_p getOrDefault ["api", ""]) isNotEqualTo (_vb param [1, ""])): {
                ["freq", "KO", _freqT, format ["Vous et %1 n'avez pas ponté le même type de radio (portative et longue portée ne s'entendent pas).", _pn], "Pontez le même type de radio des deux côtés.", "types différents"] call _add;
            };
            case ((_p getOrDefault ["freq", ""]) isEqualTo "" || {_myF isEqualTo ""}): {
                ["freq", "INCONNU", _freqT, format ["La fréquence de %1 ne se lit pas d'ici (radio pas encore synchronisée).", _pn], "Demandez-lui sa fréquence et comparez avec la vôtre.", ["inconnue", _myF] select (_myF isNotEqualTo "")] call _add;
            };
            default {
                private _pf = _p getOrDefault ["freq", ""];
                private _peerN = _p getOrDefault ["freqN", -1];
                private _same = if (_myN > 0 && {_peerN isEqualType 0} && {_peerN > 0}) then { abs (_myN - _peerN) < 0.0001 } else { _pf isEqualTo _myF };
                if (_same) then {
                    ["freq", "OK", _freqT, format ["Même fréquence que %1 : %2.", _pn, _myF], "", _myF] call _add;
                } else {
                    ["freq", "KO", _freqT, format ["Fréquences différentes : %1 chez vous, %2 chez %3 : aucun son, même relayé.", _myF, _pf, _pn],
                        "Réglez la même fréquence (ou le même canal de préréglage) sur les deux radios pontées.", "différentes"] call _add;
                };
            };
        };
        // 10. TFAR.
        switch (_ct) do {
            case "absent": { ["tfar", "OK", "TFAR", "TFAR n'est pas chargé : sans objet.", "", "non chargé"] call _add; };
            case "ok": { ["tfar", "OK", "TFAR", format ["TFAR %1 reconnu : sa portée est allongée par ses multiplicateurs officiels (lus dans le code de TFAR, à confirmer en jeu).", _vt], "", _vt] call _add; };
            default {
                ["tfar", "KO", "TFAR", format ["TFAR %1 non reconnu (version ou fonction absente) : voix relayée coupée par sécurité, radio normale.", _vt], "Utilisez TFAR 1.x, puis relancez la partie.", "non reconnu"] call _add;
            };
        };
        // 11. Licence et droits : rien n'est demandé à Athena.
        ["perm", "OK", "Licence et droits", ["La voix relayée ne demande ni licence ni connexion à Athena : tout se passe en jeu (exemple : hors ligne, elle marche pareil).<br/>Seul un admin, un Zeus ou un gestionnaire désigné change les réglages du serveur.",
            "La voix relayée ne demande ni licence ni connexion à Athena : tout se passe en jeu (exemple : hors ligne, elle marche pareil).<br/>Vous pouvez changer les réglages du serveur (admin)."] select _admin, "",
            format ["%1 · %2", ["joueur", "admin"] select _admin, ["Athena non liée", "Athena liée"] select (["athena", false] call _g)]] call _add;
        _out
    };
    case "run": {
        private _pid = _arg param [0, uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagPeer", ""]];
        if !(_pid isEqualType "") then { _pid = ""; };
        (uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagC", []]) params [["_at", -10], ["_cp", "?"], ["_steps", []], ["_facts", createHashMap]];
        if (diag_tickTime - _at < 1 && {_cp isEqualTo _pid} && {_steps isNotEqualTo []}) exitWith { [_steps, _facts] };
        private _t0 = diag_tickTime;
        private _f = ["facts", [_pid]] call comspec_atak_native_fnc_mpuDiag;
        // Faits imposés (autotest seulement) : états simulés sans radio réelle.
        private _sim = uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagSim", createHashMap];
        if (_sim isEqualType createHashMap && {(count _sim) > 0}) then { _f merge [_sim, true]; };
        _steps = ["eval", [_f]] call comspec_atak_native_fnc_mpuDiag;
        _f set ["ms", (diag_tickTime - _t0) * 1000];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", [diag_tickTime, _pid, _steps, _f]];
        [_steps, _f]
    };
    case "steps": { (["run", _arg] call comspec_atak_native_fnc_mpuDiag) select 0 };
    case "first": {
        private _steps = _arg param [0, []];
        private _i = _steps findIf { (_x select 1) isEqualTo "KO" };
        if (_i < 0) then { _i = _steps findIf { (_x select 1) isEqualTo "ATTENTION" }; };
        [[], _steps select _i] select (_i >= 0)
    };
    case "chain": {
        // Chaîne de la voix : ma radio, mon pont, ma MPU5, le maillage, puis la MPU5, le pont et la radio du camarade.
        private _steps = _arg param [0, []];
        private _st = createHashMapFromArray (_steps apply { [_x select 0, _x select 1] });
        private _cell = {
            params ["_ids"];
            private _ss = _ids apply { _st getOrDefault [_x, "INCONNU"] };
            switch (true) do {
                case ("KO" in _ss): { "abort" };
                case ("ATTENTION" in _ss): { "now" };
                case ((_ss findIf { _x isNotEqualTo "OK" }) < 0): { "done" };
                default { "todo" };
            }
        };
        [["RADIO", [["radio", "ts"]] call _cell], ["PONT", [["setting", "real", "bridge"]] call _cell], ["MPU5", [["mpu"]] call _cell], ["MAILLAGE", [["mesh"]] call _cell],
            ["MPU5", [["peer"]] call _cell], ["PONT", [["peer"]] call _cell], ["RADIO", [["freq"]] call _cell]]
    };
    case "chainText": {
        // Schéma texte : « Radio ✓ → Pont ✕ (cassé ici) → … » ; couleurs [ok, ko, attention, inconnu] en hexadécimal, [] : texte brut (rapport).
        _arg params [["_steps", []], ["_cols", []]];
        private _cells = ["chain", [_steps]] call comspec_atak_native_fnc_mpuDiag;
        private _labels = ["Votre radio", "pont", "MPU5", "maillage", "MPU5 du camarade", "pont", "sa radio"];
        private _first = _cells findIf { (_x select 1) isEqualTo "abort" };
        private _parts = [];
        {
            private _state = (_cells select _forEachIndex) select 1;
            private _k = ["done", "abort", "now", "todo"] find _state;
            private _mark = ["✓", "✕", "!", "?"] select (_k max 0);
            private _lbl = (_x call comspec_atak_native_fnc_tr) + " " + _mark;
            if (_forEachIndex isEqualTo _first) then { _lbl = _lbl + " " + (("(cassé ici)") call comspec_atak_native_fnc_tr); };
            if ((count _cols) isEqualTo 4) then { _lbl = format ["<t color='%1'>%2</t>", _cols select (_k max 0), _lbl]; };
            _parts pushBack _lbl;
        } forEach _labels;
        _parts joinString " → "
    };
    case "report": {
        // Texte à coller sur Discord : version du mod, ACRE2 / TFAR, étapes et valeurs. Traduit dans la langue affichée.
        _arg params [["_steps", []], ["_f", createHashMap]];
        private _T = { _this call comspec_atak_native_fnc_tr };
        private _plain = { params ["_s"]; ((_s regexReplace ["<br ?/?>", " "]) regexReplace ["<[^>]*>", ""]) };
        private _yn = { (["non", "oui"] select (_this isEqualTo true)) call _T };
        private _lines = [
            format [("Dépannage MPU · COMSPEC ATAK %1" call _T), _f getOrDefault ["modVer", "?"]],
            format [("Joueur : %1 · camp %2 · %3" call _T), _f getOrDefault ["who", "?"], _f getOrDefault ["side", "?"], (["partie solo", "multijoueur"] select ((_f getOrDefault ["mp", false]) isEqualTo true)) call _T],
            format [("Radio : ACRE2 %1 (%2) · TFAR %3 (%4)" call _T), _f getOrDefault ["acreV", ""], (_f getOrDefault ["acre", "?"]) call _T, _f getOrDefault ["tfarV", ""], (_f getOrDefault ["tfar", "?"]) call _T],
            format [("Réglages : voix relayée %1 · liaison réaliste %2 · gravité %3 · plafond %4 sauts" call _T), (_f getOrDefault ["setOn", false]) call _yn, (_f getOrDefault ["realSet", false]) call _yn,
                (_f getOrDefault ["gravity", true]) call _yn, _f getOrDefault ["maxHops", 6]],
            format [("Chaîne : %1" call _T), ["chainText", [_steps, []]] call comspec_atak_native_fnc_mpuDiag],
            ""
        ];
        {
            _x params ["", "_state", "_title", "_expl", "_todo", "_val"];
            _lines pushBack format [("Étape %1 [%2] %3 : %4" call _T), _forEachIndex + 1, _state call _T, _title call _T, [_val call _T] call _plain];
            if (_state isNotEqualTo "OK") then {
                _lines pushBack ("    " + ([_expl call _T] call _plain));
                if (_todo isNotEqualTo "") then { _lines pushBack ("    " + (format [("À faire : %1" call _T), [_todo call _T] call _plain])); };
            };
        } forEach _steps;
        _lines pushBack "";
        _lines pushBack format [("Lu en %1 ms sur le téléphone, rien n'a été envoyé." call _T), (_f getOrDefault ["ms", 0]) toFixed 1];
        _lines joinString endl
    };
    case "copy": {
        (["run"] call comspec_atak_native_fnc_mpuDiag) params ["_steps", "_f"];
        private _s = ["report", [_steps, _f]] call comspec_atak_native_fnc_mpuDiag;
        copyToClipboard _s;
        // En multijoueur copyToClipboard est sans effet : la DLL du mod écrit le presse-papiers Windows, par morceaux (comme fn_planAction).
        private _b = ["ClipBegin"] call comspec_atak_native_fnc_extensionCall;
        if (_b isEqualType "" && {(_b select [0, 2]) isEqualTo "OK"}) then {
            forceUnicode 0;
            private _len = count _s;
            for "_i" from 0 to (_len - 1) step 2000 do {
                forceUnicode 0;
                ["ClipAppend", [_s select [_i, 2000]]] call comspec_atak_native_fnc_extensionCall;
            };
            ["ClipCommit"] call comspec_atak_native_fnc_extensionCall;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagReport", _s];
        ["Rapport copié : collez-le sur Discord (Ctrl+V), salon support"] call _say;
        playSound "ClickSoft";
        true
    };
    case "open": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["netDiag", true];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
        playSound "ClickSoft";
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "NETWORK") then { call _rerender; } else { ["NETWORK"] call comspec_atak_native_fnc_navigate; };
        true
    };
    case "close": {
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["netDiag", false];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "peer": {
        private _id = _arg param [0, ""];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagPeer", [_id, ""] select !(_id isEqualType "")];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
        playSound "ClickSoft";
        call _rerender;
        true
    };
    case "fix": {
        // Corrections directes, toutes déjà offertes ailleurs (app Réseau, menu ACE). Réglages du serveur : fenêtre du menu Échap, où seul un admin applique.
        private _id = _arg param [0, ""];
        switch (_id) do {
            case "power": { ["power"] call comspec_atak_native_fnc_mpuLink; };
            case "battery": { ["battery"] call comspec_atak_native_fnc_mpuLink; };
            case "bridge": { ["bridge"] call comspec_atak_native_fnc_mpuVoice; };
            case "wave": { ["WAVERELAY"] call comspec_atak_native_fnc_navigate; };
            case "settings": {
                [] call comspec_atak_native_fnc_close;
                [{ ["open", displayNull] call comspec_atak_native_fnc_netConfig; }, [], 0.1] call comspec_atak_native_fnc_waitAndExec;
            };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
        call _rerender;
        true
    };
    case "arm": {
        // Rafraîchi toutes les 2 s tant que le volet est affiché : redessiné seulement si un état change, jamais pendant le choix d'un camarade.
        private _tok = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagTok", _tok];
        [{ ["tick", _this] call comspec_atak_native_fnc_mpuDiag; }, [_tok], 2] call comspec_atak_native_fnc_waitAndExec;
    };
    case "tick": {
        private _tok = _arg param [0, -1];
        if ((uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagTok", -2]) isNotEqualTo _tok) exitWith {};
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith {};
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_s getOrDefault ["netDiag", false]) isNotEqualTo true) exitWith {};
        if ((_s getOrDefault ["pageDrawn", ""]) isNotEqualTo "NETWORK") exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_MeshScanAt", diag_tickTime];
        private _sig = str ((["steps"] call comspec_atak_native_fnc_mpuDiag) apply { _x select 1 });
        private _fc = focusedCtrl _d;
        if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagSig", ""]) || {!isNull _fc && {(ctrlType _fc) in [2, 4]}}) exitWith {
            [{ ["tick", _this] call comspec_atak_native_fnc_mpuDiag; }, [_tok], 2] call comspec_atak_native_fnc_waitAndExec;
        };
        ["NETWORK"] call comspec_atak_native_fnc_pageRender;
    };
    case "rows": {
        // Volet de l'app Réseau. Le graphe du maillage est relu tant qu'il est ouvert, même téléphone débranché (fn_mpuLink "tick", COMSPEC_ATAK_MeshScanAt).
        uiNamespace setVariable ["COMSPEC_ATAK_MeshScanAt", diag_tickTime];
        (["run"] call comspec_atak_native_fnc_mpuDiag) params ["_steps", "_f"];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSig", str (_steps apply { _x select 1 })];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagDrawn", diag_tickTime];
        private _hOk = COMSPEC_ATAK_Th get "hexOk";
        private _hKo = COMSPEC_ATAK_Th get "hexAlert";
        private _hWa = COMSPEC_ATAK_Th get "hexWarn";
        private _hMu = COMSPEC_ATAK_Th get "hexTextMuted";
        private _hDi = COMSPEC_ATAK_Th get "hexTextDim";
        private _admin = (_f getOrDefault ["admin", false]) isEqualTo true;
        private _fixLbl = createHashMapFromArray [
            ["power", "ALLUMER LA MPU5"], ["battery", "CHANGER LA BATTERIE"], ["bridge", "ACTIVER LE PONT RADIO"], ["wave", "OUVRIR WAVE RELAY"],
            ["settings", ["VOIR LES RÉGLAGES DU SERVEUR", "OUVRIR LES RÉGLAGES DE L'ADMIN"] select _admin]
        ];
        private _rows = [
            ["header", "Dépannage MPU", "Voix radio par le maillage : ce qui marche, ce qui bloque", [["ACTUALISER", { uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []]; ["NETWORK"] call comspec_atak_native_fnc_pageRender; }]],
                { ["close"] call comspec_atak_native_fnc_mpuDiag; }],
            ["steps", ["chain", [_steps]] call comspec_atak_native_fnc_mpuDiag],
            ["text", format ["<t size='0.88'>%1</t>", ["chainText", [_steps, [_hOk, _hKo, _hWa, _hMu]]] call comspec_atak_native_fnc_mpuDiag]]
        ];
        // Première étape en échec, mise en avant avec sa correction.
        private _first = ["first", [_steps]] call comspec_atak_native_fnc_mpuDiag;
        private _pic = "\z\comspec_atak_native\addons\main\data\app_waverelay.paa";
        if (_first isEqualTo []) then {
            _rows pushBack ["status", _pic, COMSPEC_ATAK_Th get "ok", "Tout est prêt de votre côté", "Parlez sur la radio pontée : la case RELAIS ATAK s'affiche quand votre voix passe vraiment par le maillage. Exemple : un camarade ponté à 8 km vous entend sur la même fréquence.", ""];
        } else {
            _first params ["", "_fs", "_ft", "_fe", "_fa", "", "_fx"];
            _rows pushBack ["status", _pic, COMSPEC_ATAK_Th get (["warn", "alert"] select (_fs isEqualTo "KO")), format [("À corriger d'abord : %1" call comspec_atak_native_fnc_tr), (_ft call comspec_atak_native_fnc_tr)], _fe,
                [format ["À faire : %1", _fa], ""] select (_fa isEqualTo "")];
            if (_fx isNotEqualTo "") then {
                _rows pushBack ["buttons", [[_fixLbl getOrDefault [_fx, "CORRIGER"], compile format ["['fix', [%1]] call comspec_atak_native_fnc_mpuDiag;", str _fx], true]]];
            };
        };
        // Toutes les étapes, dans l'ordre de la chaîne.
        _rows pushBack ["section", "Étapes vérifiées", "Dans l'ordre de la chaîne : la première en rouge bloque la voix"];
        {
            _x params ["", "_state", "_title", "_expl", "_todo", "_val"];
            private _lvl = ["ok", "ko", "warn", "off"] select ((["OK", "KO", "ATTENTION", "INCONNU"] find _state) max 0);
            private _help = _expl;
            if (_state isNotEqualTo "OK" && {_todo isNotEqualTo ""}) then { _help = format ["%1<br/><t color='%2'>%3</t>", _expl, _hWa, format ["À faire : %1", _todo]]; };
            _rows pushBack ["check", _lvl, format ["%1. %2", _forEachIndex + 1, _title call comspec_atak_native_fnc_tr], _val, _help];
        } forEach _steps;
        // Camarade : son côté de la chaîne (MPU5, pont, maillage, fréquence).
        _rows pushBack ["section", "Tester avec un camarade", "Vérifie aussi son côté : MPU5, pont, maillage et fréquence"];
        private _pid = uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagPeer", ""];
        private _items = [["Aucun : vérifier seulement mon côté", ""]];
        private _cands = allPlayers select { _x isNotEqualTo player && {alive _x} && {(side group _x) isEqualTo (side group player)} };
        _cands = [_cands, [], { player distance _x }] call BIS_fnc_sortBy;
        { _items pushBack [format ["%1 · %2", [_x, true] call comspec_atak_native_fnc_unitCallsign, [player distance _x] call _distTxt], netId _x]; } forEach (_cands select [0, 24]);
        if ((_items findIf { (_x select 1) isEqualTo _pid }) < 0 && {_pid isNotEqualTo ""}) then { _items pushBack [format ["%1 (hors liste)", (_f getOrDefault ["peer", createHashMap]) getOrDefault ["name", "?"]], _pid]; };
        if ((count _items) < 2) then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", _hMu, "Aucun autre joueur de votre camp dans la partie pour l'instant."]]; };
        _rows pushBack ["combo", "mpuDiagPeer", "Camarade", _items, _pid, ["TESTER", { params ["_d"]; ["peer", [_d]] call comspec_atak_native_fnc_mpuDiag; }]];
        // Faut-il un relais ? Trois cas (fn_meshGraph "view", fn_mpuVoice _linked) et le vôtre.
        private _maxH = _f getOrDefault ["maxHops", 6];
        _rows append [
            ["section", "Faut-il un relais ?", "Oui : au moins un dans la mission (réglage par défaut)"],
            ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.88' color='%2'>%3</t>", "0. Aucun relais dans la mission : la voix ne passe pas.", _hDi,
                "Réglage « Voix par le maillage : au moins un relais » (coché par défaut) : sans pylône, module « Poser un relais », véhicule passerelle ni poste de commandement en service pour votre camp, la voix ne passe pas par les MPU5, même dans le groupe. Les trois cas suivants supposent qu'il y en a au moins un."]],
            ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.88' color='%2'>%3</t>", "1. Même groupe : non.", _hDi,
                "Sans relais dans la mission, les MPU5 du groupe forment le maillage d'équipe autour de celle du chef de groupe (même canal, à portée en sauts). Exemple : 4 équipiers d'Alpha à 2 km les uns des autres s'entendent par la MPU5 du chef."]],
            ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.88' color='%2'>%3</t>", "2. Voisins directs : non.", _hDi,
                format ["Deux MPU5 qui se joignent de saut en saut (même canal, au plus %1 sauts) s'entendent sans relais, même de groupes différents. Exemple : Bravo à 3 km, relayé par la MPU5 d'un équipier posté entre vous.", _maxH]]],
            ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.88' color='%2'>%3</t>", "3. Hors de portée de tout le maillage : oui.", _hDi,
                "Un relais du même camp (pylône, module « Poser un relais », véhicule passerelle, poste de commandement) relie tous les maillages, sur tous les canaux. Exemple : le PC à 12 km vous entend par le pylône."]]
        ];
        private _mine = switch (_f getOrDefault ["meshMode", ""]) do {
            case "relay": { format ["Votre cas : un relais est déjà joint (%1), vous êtes dans le cœur de réseau du camp.", _f getOrDefault ["meshGw", "?"]] };
            case "lead": { format ["Votre cas : maillage d'équipe du chef de groupe %1, pas besoin de relais pour joindre votre groupe.", _f getOrDefault ["meshGw", "?"]] };
            case "self": { ["Votre cas : maillage local seulement, seuls vos voisins directs vous entendent ; un relais est nécessaire pour les autres.", "Votre cas : vous êtes chef de groupe, votre MPU5 est le cœur du maillage d'équipe ; un relais est nécessaire au-delà."] select ((_f getOrDefault ["isLead", false]) isEqualTo true) };
            default { "Votre cas : pas encore de maillage lu (voir les étapes au-dessus)." };
        };
        _rows pushBack ["text", format ["<t size='0.92'>%1</t>", _mine], COMSPEC_ATAK_Th get "surface"];
        _rows append [
            ["gap"],
            ["buttons", [
                ["COPIER LE RAPPORT", { ["copy"] call comspec_atak_native_fnc_mpuDiag; }, true, true, "Texte à coller sur Discord : version du mod, ACRE2 / TFAR, étapes et valeurs"],
                [_fixLbl get "settings", { ["fix", ["settings"]] call comspec_atak_native_fnc_mpuDiag; }, false, true, ["Lecture seule : seul un admin applique au serveur", "Échap › Réalisme"] select _admin],
                ["FERMER", { ["close"] call comspec_atak_native_fnc_mpuDiag; }]
            ]],
            ["text", format ["<t size='0.8' color='%1'>%2</t>", _hMu, format ["Lecture seule, sur votre téléphone (%1 ms) : rien n'est envoyé. Le rapport sert à demander de l'aide sur Discord.", (_f getOrDefault ["ms", 0]) toFixed 1]]]
        ];
        _rows
    };
};
