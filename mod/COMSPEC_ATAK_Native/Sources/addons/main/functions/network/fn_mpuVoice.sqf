/*
    Voix ACRE2 / TFAR relayée par le maillage MPU5 (passerelle radio sur IP, comme une vraie MPU5 reliée par câble audio
    à la radio tactique) : réglage serveur comspec_atak_native_mpu_voice (désactivé par défaut), avec la liaison réaliste
    (comspec_atak_native_mpu_real, fn_mpuLink). Aucune donnée ATAK ne passe par la radio : seule la voix passe par la MPU5.
    Condition : l'émetteur et le récepteur portent chacun une MPU5 allumée (batterie) à laquelle ils ont relié leur radio
    (« pont radio » : action ACE Téléphone ATAK > Radio de données, ou app Réseau), et leurs MPU5 sont dans le même
    maillage (fn_mpuLink "mesh") :
      - même cœur de réseau : les deux joignent un relais de la mission (pylône, module « ATAK · Relais », passerelle de
        véhicule, poste de commandement) du même camp, ou la MPU5 du même chef de groupe (maillage d'équipe sans relais) ;
      - ou l'un voit l'autre dans son propre maillage (MPU5 du même canal, multi-sauts).
    La fréquence (ou le canal) reste celle de la radio, comme d'habitude : ACRE2 / TFAR choisissent qui s'entend.
    Effet :
      - Gravité de coupure (lot 3, réglage comspec_atak_native_mesh_gravity, coché par défaut) : le signal par le maillage suit le
        pire lien du chemin (fn_meshGraph "gravity") : niveau 0 fort, 1 et 2 réduit par paliers, 3 aucune rallonge. Passage net
        (décision 6), pas d'à-coups. Valeurs de jeu, à recalibrer. Décoché : signal fixe de l'ancien modèle.
      - TFAR, lot 4 (réglage comspec_atak_native_mesh_tfar_ptt, coché par défaut ; décoché : multiplicateur de réception permanent de
        l'ancien modèle). D'après le code source de TFAR (michail-nikolaev/task-force-arma-3-radio, fce977f), TFAR n'étant pas
        installé sur la machine d'étude ; À CONFIRMER EN JEU, avec le plugin et la version du Workshop :
        OnBeforeTangent (fnc_doSRTransmit, fnc_doLRTransmit) part avant le calcul de la portée, TFAR lit alors
        tf_sendingDistanceMultiplicator : posé à l'appui si la radio utilisée est celle du pont et qu'un correspondant ponté joignable est sur
        sa fréquence, rendu à la relâche (OnTangent faux : l'envoi TANGENT RELEASED le relit aussi). Le récepteur n'allonge sa portée
        que pendant l'émission d'un correspondant ponté (événement ciblé comspec_atak_native_meshTfTx), avec le retard d'envoi de
        TFAR_fnc_sendFrequencyInfo (environ 2 s). Limites : la portée d'émission vaut pour tous les auditeurs ; une radio non pontée
        du récepteur, sur la même fréquence, en profite pendant ce temps.
      - ACRE2 : fonction de signal personnalisée (acre_api_fnc_setCustomSignalFunc) enchaînée à celle déjà installée, ou au
        calcul normal d'ACRE2 (acre_sys_signal_fnc_getSignalCore, toujours appelé : modèle de signal intact). Entre deux
        radios pontées dans le même maillage, le signal renvoyé est le meilleur des deux : direct ou par le maillage
        (fort, un peu moins à chaque saut). L'objet qui porte la radio émettrice : acre_sys_radio_fnc_getRadioObject.
      - TFAR (1.0 et 0.9) : pas de calcul par paire de radios. Variable officielle tf_receivingDistanceMultiplicator du
        joueur qui écoute (distance effective multipliée, < 1 = entendu de plus loin) : tant que sa radio est pontée et
        qu'un correspondant ponté du maillage émet sur la même fréquence (même type de radio : portative ou longue
        portée), la portée de réception est allongée juste assez pour l'atteindre (relief compris, au plus 100 fois) ;
        retour à la valeur d'avant dès la sortie du maillage. Une valeur posée par un autre script (brouilleur) est
        gardée et multipliée. Limite TFAR : pendant ce temps, une radio non pontée sur la même fréquence et dans ce rayon
        s'entend aussi.
    Calculs : "tick" toutes les 2 s (état publié, joueurs pontés joignables, TFAR) ; ACRE2 : cache par paire de radios
    rafraîchi au plus toutes les 2 s (aucun calcul de maillage par signal), aucun remoteExec.
    Variable publique : COMSPEC_ATAK_MpuVoice = [type ("acre" | "sw" | "lr"), clé de la radio (ID ACRE2, classe TFAR,
    "tfar_lr"), fréquence TFAR (texte), portée TFAR m, réseau ("R<camp>" : relais ; "L<netId du chef>" : maillage du
    groupe ; "" : maillage local seulement), sauts jusqu'à la passerelle, nom de la radio] ou [] ; publiée seulement quand
    elle change.
      "enabled"  -> vrai si le réglage et la liaison réaliste sont actifs
      "radios"   -> radios ACRE2 / TFAR portées : [[clé, type, nom]...]
      "preferred"-> radio à ponter par défaut (active) : [clé, type, nom] ou []
      "bridge" [clé] ; "unbridge"
      "view"     -> HashMap pour l'app Réseau
      "tick" ; "signal" (fonction de signal ACRE2) ; "pair" [ID récepteur, ID émetteur] -> [sauts, niveau] (sauts -1 : aucun lien)
      "tfBefore", "tfAfter" [unité, radio, type, additionnel, appui] : événements TFAR ; "tfRx" [émetteur, appui] : émission d'un correspondant
      "sigMap" [Px d'ACRE2, dBm d'ACRE2, sauts, niveau, gravité active] -> [Px, dBm] (sans effet, testé par fn_devMeshTest)
*/
params [["_act", ""], ["_arg", []]];
// Chemin rapide : appelé par ACRE2 pour chaque signal reçu.
if (_act isEqualTo "sigMap") exitWith {
    _arg params ["_px", "_dbm", "_hops", "_lvl", "_grav"];
    if (_hops < 0 || {_lvl >= 3}) exitWith { [_px, _dbm] };
    if (!_grav || {_lvl <= 0}) exitWith { [_px max ((1 - 0.04 * _hops) max 0.6), _dbm max (-60 - 3 * _hops)] };
    [_px max ([0.7, 0.45] select (_lvl >= 2)), _dbm max ([-80, -95] select (_lvl >= 2))]
};
private _enabled = { (missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]) && {["voice"] call comspec_atak_native_fnc_radioGuard} && {["active"] call comspec_atak_native_fnc_mpuLink} };
private _read = ["read"] call comspec_atak_native_fnc_radioGuard;
private _acre = _read && {!isNil "acre_api_fnc_getCurrentRadioList"};
private _tfar = _read && {!isNil "TFAR_fnc_activeSwRadio"};
private _rg = { params ["_n", ["_a", []], ["_d", false]]; ["api", [_n, _a, _d]] call comspec_atak_native_fnc_radioGuard };
// Multiplicateur TFAR rendu tel qu'il était : valeur d'avant, ou aucune s'il n'y en avait pas. Une valeur posée entre-temps par un autre script lui reste.
private _tfPut = {
    params ["_u", "_var", "_base", "_had", "_mine"];
    if (isNull _u) exitWith {};
    private _now = _u getVariable _var;
    if (isNil "_now" || {!(_now isEqualType 0)} || {abs (_now - _mine) >= 0.0001}) exitWith {};
    if (_had) then { _u setVariable [_var, _base]; } else { _u setVariable [_var, nil]; };
};
private _say = {
    params ["_type", "_msg"];
    if !([_msg, 4] call comspec_atak_native_fnc_toast) then { [_type, _msg, 4, 30] call comspec_atak_native_fnc_notify; };
};
private _rerender = {
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "NETWORK") then {
        [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
};
private _radios = {
    private _out = [];
    if (_acre) then {
        private _l = ["acre_api_fnc_getCurrentRadioList", [], []] call _rg;
        if !(_l isEqualType []) then { _l = []; };
        {
            private _id = _x;
            private _n = ["acre_api_fnc_getDisplayName", [_id], ""] call _rg;
            if !(_n isEqualType "") then { _n = ""; };
            if (_n isEqualTo "") then {
                private _bs = ["acre_api_fnc_getBaseRadio", [_id], ""] call _rg;
                if (_bs isEqualType "") then { _n = getText (configFile >> "CfgWeapons" >> _bs >> "displayName"); };
            };
            if (_n isEqualTo "") then { _n = _id; };
            _out pushBack [_id, "acre", _n];
        } forEach _l;
    };
    if (_tfar) then {
        private _sw = ["TFAR_fnc_radiosList", player, []] call _rg;
        if !(_sw isEqualType []) then { _sw = []; };
        { if (_x isEqualType "" && {_x isNotEqualTo ""}) then { _out pushBack [_x, "sw", getText (configFile >> "CfgWeapons" >> _x >> "displayName")]; }; } forEach _sw;
        private _lr = ["TFAR_fnc_activeLrRadio", [], []] call _rg;
        if (_lr isEqualType [] && {(count _lr) > 1} && {(_lr select 0) isEqualType objNull} && {!isNull (_lr select 0)}) then {
            private _n = getText (configOf (_lr select 0) >> "displayName");
            _out pushBack ["tfar_lr", "lr", [_n, "Radio longue portée"] select (_n isEqualTo "")];
        };
    };
    _out
};
// Fréquence et portée d'une radio TFAR pontée (fréquence en texte : comparée telle quelle d'un joueur à l'autre).
private _tfInfo = {
    params ["_key", "_api"];
    private _f = "";
    private _r = 0;
    if (_api isEqualTo "lr") then {
        private _lr = ["TFAR_fnc_activeLrRadio", [], []] call _rg;
        if (_lr isEqualType [] && {(count _lr) > 1}) then {
            _f = ["TFAR_fnc_getLrFrequency", _lr, ""] call _rg;
            _r = ["TFAR_fnc_getLrRadioProperty", [_lr select 0, "tf_range"], 0] call _rg;
        };
    } else {
        _f = ["TFAR_fnc_getSwFrequency", _key, ""] call _rg;
        _r = getNumber (configFile >> "CfgWeapons" >> _key >> "tf_range");
    };
    if (!(_r isEqualType 0) || {_r <= 0}) then { _r = [5000, 20000] select (_api isEqualTo "lr"); };
    private _u = missionNamespace getVariable ["TFAR_currentUnit", player];
    if (isNil "_u" || {!(_u isEqualType objNull)} || {isNull _u}) then { _u = player; };
    private _m = _u getVariable ["tf_sendingDistanceMultiplicator", 1];
    // Pendant une émission par le pont : valeur d'avant, pas celle posée pour cette émission.
    (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []]) params [["_txU", objNull], ["_txBase", 1]];
    if (!isNull _txU) then { _m = _txBase; };
    if (!(_m isEqualType 0) || {_m <= 0}) then { _m = 1; };
    [str _f, round (_r * _m)]
};
// Lien voix avec un joueur ponté : sauts (cœur de réseau commun ou nœud de mon maillage), -1 sinon.
private _linked = {
    params ["_u", "_pp"];
    (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []], ["_reach", createHashMap]];
    if (_me isEqualTo [] || {(count _pp) < 7}) exitWith { -1 };
    // Hors de tout groupe commun : pas de rallonge (fn_meshGroups).
    if !(["shared", [_u]] call comspec_atak_native_fnc_meshGroups) exitWith { -1 };
    // Sans la clé du groupe de ma radio : relais possible, voix sans rallonge (décision 2).
    if !(["voiceOk"] call comspec_atak_native_fnc_meshKeys) exitWith { -1 };
    private _mk = _me select 4;
    if (_mk isNotEqualTo "" && {_mk isEqualTo (_pp select 4)}) exitWith {
        // Par le cœur de réseau : mes sauts plus les siens, sous le même plafond que le reste du graphe (fn_meshGraph).
        private _sum = ((_me select 5) max 0) + ((_pp select 5) max 0);
        [-1, _sum] select (_sum <= (["maxHops"] call comspec_atak_native_fnc_meshGraph))
    };
    _reach getOrDefault [netId _u, -1]
};
switch (_act) do {
    case "tfGuard": {
        // Seul point d'entrée des événements TFAR : compteurs d'entrée et de sortie lus par fn_radioGuard "watch". Couche coupée : rien, tout de suite.
        _arg params [["_what", ""], ["_a", []]];
        if !(missionNamespace getVariable ["COMSPEC_ATAK_VoiceLive", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_GuardIn", (missionNamespace getVariable ["COMSPEC_ATAK_GuardIn", 0]) + 1];
        [_what, _a] call comspec_atak_native_fnc_mpuVoice;
        missionNamespace setVariable ["COMSPEC_ATAK_GuardOut", (missionNamespace getVariable ["COMSPEC_ATAK_GuardOut", 0]) + 1];
    };
    case "release": {
        // Tout rendre : fonction de signal d'ACRE2, multiplicateurs TFAR, code TFAR, pont. Sans danger si rien n'est posé. Mort, respawn, fin de mission, déconnexion, coupe-circuit, coupure.
        private _why = _arg param [0, ""];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceLive", false];
        missionNamespace setVariable ["COMSPEC_ATAK_VoicePairMap", []];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceRx", ""];
        private _had = (missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isNotEqualTo [];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceBridge", []];
        ["sigOff"] call comspec_atak_native_fnc_mpuVoice;
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []]) params [["_txU", objNull], ["_txB", 1], ["_txV", 1], ["_txT", []], ["_txH", true]];
        if (!isNull _txU) then {
            [_txU, "tf_sendingDistanceMultiplicator", _txB, _txH, _txV] call _tfPut;
            { ["comspec_atak_native_meshTfTx", [_txU, false], _x] call CBA_fnc_targetEvent; } forEach _txT;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceTx", []];
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTf", []]) params [["_rxU", objNull], ["_rxB", 1], ["_rxV", -1], ["_rxH", true]];
        if (!isNull _rxU && {_rxV >= 0}) then { [_rxU, "tf_receivingDistanceMultiplicator", _rxB, _rxH, _rxV] call _tfPut; };
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceTf", []];
        missionNamespace setVariable ["COMSPEC_ATAK_TfRxActive", createHashMap];
        ["tfRestore"] call comspec_atak_native_fnc_meshKeys;
        if (!isNull player && {(player getVariable ["COMSPEC_ATAK_MpuVoice", []]) isNotEqualTo []}) then { player setVariable ["COMSPEC_ATAK_MpuVoice", []]; };
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", []];
        if (_had) then { diag_log format ["[COMSPEC ATAK NATIVE][INFO][RADIO] Pont radio retiré, valeurs d'origine rendues : %1", _why]; };
    };
    case "sigOff": {
        // Rend ce qui était en place avant nous, seulement si notre fonction est encore la dernière. Un autre mod posé par-dessus reste : la nôtre, s'il l'appelle, rend le signal d'origine.
        (missionNamespace getVariable ["COMSPEC_ATAK_AcreSigOrig", []]) params [["_set", false], ["_orig", {}]];
        if (!_set) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_AcreSigOrig", []];
        missionNamespace setVariable ["COMSPEC_ATAK_AcreSigN", 0];
        private _cur = missionNamespace getVariable "acre_sys_signal_customSignalFunc";
        if (!isNil "_cur" && {_cur isEqualTo comspec_atak_native_fnc_mpuVoiceSig}) then { ["acre_api_fnc_setCustomSignalFunc", [_orig], false] call _rg; };
    };
    case "tfBefore": {
        _arg params ["_u", "_radio", ["_type", 0], "", ["_down", false]];
        if (!_down || {_u isNotEqualTo (missionNamespace getVariable ["TFAR_currentUnit", player])}) exitWith {};
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []], "", "", "", "", ["_targets", []], ["_far", 0]];
        if (_me isEqualTo [] || {_far <= 0} || {_targets isEqualTo []}) exitWith {};
        // Radio utilisée = radio du pont : portative par sa classe, longue portée par son type.
        private _isBridge = [(_me select 0) isEqualTo "sw" && {_radio isEqualTo (_me select 1)}, (_me select 0) isEqualTo "lr"] select (_type isEqualTo 1);
        if (!_isBridge) exitWith {};
        private _raw = _u getVariable "tf_sendingDistanceMultiplicator";
        private _hadTx = !isNil "_raw";
        private _base = _u getVariable ["tf_sendingDistanceMultiplicator", 1];
        if (!(_base isEqualType 0) || {_base <= 0}) then { _base = 1; };
        private _range = (_me select 3) max 1;
        // Juste assez pour le plus lointain, marge pour le relief, 100 fois au plus, jamais moins que la portée d'origine.
        private _v = _base * ((((_far * 1.5) / _range) max 1) min 100);
        if !(finite _v) exitWith { ["bad"] call comspec_atak_native_fnc_radioGuard; };
        _u setVariable ["tf_sendingDistanceMultiplicator", _v];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceTx", [_u, _base, _v, _targets, _hadTx]];
        { ["comspec_atak_native_meshTfTx", [_u, true], _x] call CBA_fnc_targetEvent; } forEach _targets;
    };
    case "tfAfter": {
        _arg params ["_u", "", "", "", ["_down", false]];
        if (_down) exitWith {};
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []]) params [["_txU", objNull], ["_base", 1], ["_v", 1], ["_targets", []], ["_hadTx", true]];
        if (isNull _txU || {_txU isNotEqualTo _u}) exitWith {};
        // Valeur changée pendant l'émission par un autre script : on la lui laisse.
        [_u, "tf_sendingDistanceMultiplicator", _base, _hadTx, _v] call _tfPut;
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceTx", []];
        { ["comspec_atak_native_meshTfTx", [_u, false], _x] call CBA_fnc_targetEvent; } forEach _targets;
    };
    case "tfRx": {
        _arg params [["_from", objNull], ["_down", false]];
        if (isNull _from) exitWith {};
        private _act2 = missionNamespace getVariable ["COMSPEC_ATAK_TfRxActive", createHashMap];
        // Fin d'émission perdue (déconnexion) : oubliée après 60 s par "tick".
        if (_down) then { _act2 set [netId _from, diag_tickTime]; } else { _act2 deleteAt (netId _from); };
        missionNamespace setVariable ["COMSPEC_ATAK_TfRxActive", _act2];
        ["tick"] call comspec_atak_native_fnc_mpuVoice;
    };
    case "enabled": { call _enabled };
    case "radios": { call _radios };
    case "preferred": {
        private _rl = call _radios;
        if (_rl isEqualTo []) exitWith { [] };
        private _cur = "";
        if (_acre) then { _cur = ["acre_api_fnc_getCurrentRadio", [], ""] call _rg; };
        if (_tfar && {_cur isEqualTo ""}) then { _cur = ["TFAR_fnc_activeSwRadio", [], ""] call _rg; };
        if !(_cur isEqualType "") then { _cur = ""; };
        private _i = _rl findIf { (_x select 0) isEqualTo _cur };
        _rl select (_i max 0)
    };
    case "bridge": {
        if !(call _enabled) exitWith { ["WARNING", "Voix radio par le maillage MPU5 désactivée sur ce serveur"] call _say; false };
        if (!_acre && {!_tfar}) exitWith { ["WARNING", "Ni ACRE2 ni TFAR sur ce serveur : aucune radio à relier"] call _say; false };
        if !(["has", [player]] call comspec_atak_native_fnc_mpu5) exitWith { ["WARNING", "Aucune MPU5 sur vous : le câble audio relie une radio que vous portez à votre MPU5"] call _say; false };
        private _rl = call _radios;
        private _key = _arg param [0, ""];
        private _pick = if (_key isEqualTo "") then { ["preferred"] call comspec_atak_native_fnc_mpuVoice } else {
            private _i = _rl findIf { (_x select 0) isEqualTo _key };
            [[], _rl select _i] select (_i >= 0)
        };
        if (_pick isEqualTo []) exitWith { ["WARNING", "Aucune radio ACRE2 ou TFAR sur vous"] call _say; false };
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceBridge", _pick];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceOk", nil];
        ["SUCCESS", format ["Radio %1 reliée à la MPU5 : sa voix passe par le maillage vers les radios pontées sur la même fréquence", _pick select 2]] call _say;
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
        ["tick"] call comspec_atak_native_fnc_mpuLink;
        ["tick"] call comspec_atak_native_fnc_mpuVoice;
        call _rerender;
        playSound "ClickSoft";
        true
    };
    case "unbridge": {
        private _b = missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceBridge", []];
        if (_b isNotEqualTo []) then { ["INFO", format ["Radio %1 débranchée de la MPU5 : portée radio normale", _b select 2]] call _say; };
        ["tick"] call comspec_atak_native_fnc_mpuVoice;
        call _rerender;
        playSound "ClickSoft";
        true
    };
    case "view": {
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []], "", ["_why", ""], ["_peers", []], ["_mode", ""]];
        createHashMapFromArray [
            ["enabled", call _enabled], ["bridge", missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]], ["radios", call _radios],
            ["ok", _me isNotEqualTo []], ["why", _why], ["hops", _me param [5, 0]], ["mode", _mode], ["peers", _peers],
            ["tfMult", (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTf", []]) param [2, -1]],
            ["carried", ["has", [player]] call comspec_atak_native_fnc_mpu5]
        ]
    };
    case "tick": {
        if (!hasInterface) exitWith {};
        private _t0 = diag_tickTime;
        ["watch"] call comspec_atak_native_fnc_radioGuard;
        private _on = call _enabled;
        // Réglage décoché, coupe-circuit, coupure : tout est rendu, une fois, et plus rien ne tourne ici.
        if (!_on) exitWith {
            if ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceLive", false]) || {(missionNamespace getVariable ["COMSPEC_ATAK_AcreSigOrig", []]) isNotEqualTo []} || {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceTf", []]) isNotEqualTo []} || {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isNotEqualTo []}) then {
                ["release", ["voix relayée désactivée"]] call comspec_atak_native_fnc_mpuVoice;
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_GuardIn", (missionNamespace getVariable ["COMSPEC_ATAK_GuardIn", 0]) + 1];
        private _b = missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []];
        private _pub = [];
        private _why = "";
        private _reach = createHashMap;
        private _mode = "";
        private _drop = "";
        if (_on && {_b isNotEqualTo []}) then {
            _b params ["_key", "_api", "_name"];
            if (((call _radios) findIf { (_x select 0) isEqualTo _key }) < 0) exitWith { _drop = format ["Radio %1 plus sur vous : pont radio retiré", _name]; };
            if !(["has", [player]] call comspec_atak_native_fnc_mpu5) exitWith { _drop = "MPU5 plus sur vous : pont radio retiré"; };
            private _node = player getVariable ["COMSPEC_ATAK_MpuNode", []];
            if (_node isEqualTo [] || {(_node param [2, objNull]) isNotEqualTo player}) exitWith { _why = "MPU5 éteinte ou batterie vide"; };
            private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
            if ((_m getOrDefault ["key", ""]) isNotEqualTo (_node select 0) || {diag_tickTime - (_m getOrDefault ["at", -100]) > 20}) exitWith { _why = "connexion au maillage en cours"; };
            if !(_m getOrDefault ["ok", false]) exitWith { _why = _m getOrDefault ["why", "hors du maillage"]; };
            _reach = _m getOrDefault ["reach", createHashMap];
            _mode = _m getOrDefault ["mode", ""];
            private _ti = [["", 0], [_key, _api] call _tfInfo] select (_api in ["sw", "lr"]);
            _pub = [_api, _key, _ti select 0, _ti select 1, _m getOrDefault ["vkey", ""], (_m getOrDefault ["hops", 0]) max 0, _name];
        };
        if (_drop isNotEqualTo "") then { missionNamespace setVariable ["COMSPEC_ATAK_VoiceBridge", []]; ["WARNING", _drop] call _say; };
        if (_pub isNotEqualTo (player getVariable ["COMSPEC_ATAK_MpuVoice", []])) then { player setVariable ["COMSPEC_ATAK_MpuVoice", _pub]; };
        // Joueurs pontés joignables par le maillage : [nom, sauts, distance m, même fréquence TFAR (ou ACRE2 : vrai)].
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", [_pub, _reach, _why, [], _mode]];
        private _peers = [];
        private _dmax = 0;
        private _rr = _pub param [3, 0];
        private _ptt = _tfar && {(missionNamespace getVariable ["comspec_atak_native_mesh_tfar_ptt", true]) isEqualTo true} && {["has", ["TFAR_fnc_addEventHandler"]] call comspec_atak_native_fnc_radioGuard};
        // Table de la fonction de signal d'ACRE2 (fn_mpuVoiceSig) : ID de radio émettrice -> [sauts, niveau]. Calculée ici, jamais dans le chemin voix.
        private _pairs = createHashMap;
        private _voiceG = (uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["voice", createHashMap];
        private _rxOn = missionNamespace getVariable ["COMSPEC_ATAK_TfRxActive", createHashMap];
        { if (diag_tickTime - _y > 60) then { _rxOn deleteAt _x; }; } forEach (+_rxOn);
        private _targets = [];
        private _far = 0;
        if (_pub isNotEqualTo []) then {
            private _api = _pub select 0;
            {
                private _pp = ["voice", _x] call comspec_atak_native_fnc_meshRead;
                if (_pp isEqualTo []) then { continue };
                private _h = [_x, _pp] call _linked;
                if (_h < 0) then { continue };
                private _same = switch (true) do {
                    case (_api isEqualTo "acre"): { (_pp select 0) isEqualTo "acre" };
                    default { (_pp select 0) isEqualTo _api && {(_pp select 2) isEqualTo (_pub select 2)} && {(_pub select 2) isNotEqualTo ""} };
                };
                if (!_same && {_api isEqualTo "acre"}) then { continue };
                // Niveau tiré du graphe unique ; porteur absent du graphe : aucun lien, signal d'origine.
                if (_api isEqualTo "acre") then {
                    private _vg = _voiceG getOrDefault [netId _x, []];
                    if (_vg isNotEqualTo [] && {(_pp select 1) isEqualType ""}) then { _pairs set [_pp select 1, [_vg select 0, _vg select 2]]; };
                };
                private _d = player distance _x;
                _peers pushBack [[_x, true] call comspec_atak_native_fnc_unitCallsign, _h, round _d, _same];
                if (_same && {_api in ["sw", "lr"]}) then {
                    _targets pushBack _x;
                    _far = _far max _d;
                    // Mode alternat : la réception ne s'allonge que pendant que ce correspondant émet.
                    if (!_ptt || {(netId _x) in _rxOn}) then { _dmax = _dmax max _d; _rr = _rr min (_pp select 3); };
                };
            } forEach (allPlayers select { _x isNotEqualTo player && {alive _x} });
            _peers = [_peers, [], { _x select 1 }] call BIS_fnc_sortBy;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceMe", [_pub, _reach, _why, _peers, _mode, _targets, _far]];
        missionNamespace setVariable ["COMSPEC_ATAK_VoicePairMap", [_pairs]];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceRx", [_pub param [1, ""], ""] select ((_pub param [0, ""]) isNotEqualTo "acre")];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceLive", _b isNotEqualTo []];
        ["tick"] call comspec_atak_native_fnc_meshGroups;
        // TFAR : événements d'émission, enregistrés une fois, au premier pont actif seulement. Coupés par tfGuard dès que la couche est coupée. Émission en cours mais pont retiré : valeur d'origine rendue.
        if (_ptt && {_pub isNotEqualTo []} && {!(missionNamespace getVariable ["COMSPEC_ATAK_TfEhSet", false])}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TfEhSet", true];
            ["TFAR_fnc_addEventHandler", ["COMSPEC_ATAK_MeshBefore", "OnBeforeTangent", { ["tfGuard", ["tfBefore", _this]] call comspec_atak_native_fnc_mpuVoice; }], false] call _rg;
            ["TFAR_fnc_addEventHandler", ["COMSPEC_ATAK_MeshAfter", "OnTangent", { ["tfGuard", ["tfAfter", _this]] call comspec_atak_native_fnc_mpuVoice; }], false] call _rg;
            ["comspec_atak_native_meshTfTx", { ["tfGuard", ["tfRx", _this]] call comspec_atak_native_fnc_mpuVoice; }] call CBA_fnc_addEventHandler;
        };
        if (_pub isEqualTo []) then {
            (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []]) params [["_txU", objNull]];
            if (!isNull _txU) then { ["tfAfter", [_txU, "", 0, false, false]] call comspec_atak_native_fnc_mpuVoice; };
        };
        // État pris ou perdu : notification, page Réseau redessinée.
        private _ok = _pub isNotEqualTo [];
        private _was = missionNamespace getVariable "COMSPEC_ATAK_VoiceOk";
        if (isNil "_was" || {_was isNotEqualTo _ok}) then {
            if (!isNil "_was" && {_drop isEqualTo ""} && {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isNotEqualTo []}) then {
                if (_ok) then {
                    ["SUCCESS", format ["Voix de la radio %1 relayée par le maillage MPU5", _pub select 6]] call _say;
                } else {
                    if (_why isNotEqualTo "connexion au maillage en cours") then { ["WARNING", format ["Voix radio hors maillage (%1) : portée radio normale", _why]] call _say; };
                };
            };
            missionNamespace setVariable ["COMSPEC_ATAK_VoiceOk", _ok];
            call _rerender;
        };
        // ACRE2 : fonction de signal installée seulement pendant un pont radio, enchaînée à celle déjà en place (rappelée, jamais écrasée), sinon au calcul normal d'ACRE2.
        // Pont retiré : rendue. Écrasée par un autre mod : réinstallée par-dessus, 5 fois au plus (deux mods qui se réinstallent sans fin : on laisse l'autre, signal d'origine pour nous).
        if (_acre && {(_pub param [0, ""]) isEqualTo "acre"} && {["has", ["acre_api_fnc_setCustomSignalFunc"]] call comspec_atak_native_fnc_radioGuard} && {["has", ["acre_sys_signal_fnc_getSignalCore"]] call comspec_atak_native_fnc_radioGuard}) then {
            private _cur = missionNamespace getVariable "acre_sys_signal_customSignalFunc";
            private _mine = comspec_atak_native_fnc_mpuVoiceSig;
            if (isNil "_cur" || {_cur isNotEqualTo _mine}) then {
                private _n = missionNamespace getVariable ["COMSPEC_ATAK_AcreSigN", 0];
                if (_n <= 5) then {
                    private _keep = !isNil "_cur" && {_cur isEqualType {}} && {_cur isNotEqualTo {}};
                    missionNamespace setVariable ["COMSPEC_ATAK_AcreSigPrev", if (_keep) then { _cur } else { missionNamespace getVariable "acre_sys_signal_fnc_getSignalCore" }];
                    missionNamespace setVariable ["COMSPEC_ATAK_AcreSigOrig", [true, [{}, _cur] select _keep]];
                    ["acre_api_fnc_setCustomSignalFunc", [_mine], false] call _rg;
                    missionNamespace setVariable ["COMSPEC_ATAK_AcreSigN", _n + 1];
                    if (_n > 0) then { diag_log format ["[COMSPEC ATAK NATIVE][WARN][RADIO] Fonction de signal ACRE2 écrasée par un autre mod : réinstallée (%1 sur 5)", _n]; };
                };
            };
        } else {
            ["sigOff"] call comspec_atak_native_fnc_mpuVoice;
        };
        // TFAR : portée de réception allongée jusqu'au correspondant ponté le plus lointain (marge x3 pour le relief).
        if (_tfar || {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceTf", []]) isNotEqualTo []}) then {
            private _f = 1;
            if (_dmax > 0 && {_rr > 0}) then { _f = ((_rr / (_dmax * 3)) max 0.01) min 1; };
            private _u = missionNamespace getVariable ["TFAR_currentUnit", player];
            if (isNil "_u" || {!(_u isEqualType objNull)} || {isNull _u}) then { _u = player; };
            (missionNamespace getVariable ["COMSPEC_ATAK_VoiceTf", []]) params [["_tu", objNull], ["_base", 1], ["_ap", -1], ["_hadRx", true]];
            // Joueur changé (contrôle à distance) : valeur d'avant rendue à l'ancienne unité.
            if (_tu isNotEqualTo _u) then {
                if (!isNull _tu && {_ap >= 0}) then { [_tu, "tf_receivingDistanceMultiplicator", _base, _hadRx, _ap] call _tfPut; };
                _ap = -1;
            };
            private _raw = _u getVariable "tf_receivingDistanceMultiplicator";
            private _cur = _u getVariable ["tf_receivingDistanceMultiplicator", 1];
            if (!(_cur isEqualType 0) || {_cur <= 0}) then { _cur = 1; };
            // Valeur changée par un autre script (brouilleur…) : elle devient la base.
            if (_ap < 0 || {abs (_cur - _ap) > 0.0001}) then { _base = _cur; _ap = -1; _hadRx = !isNil "_raw"; };
            if (_f < 1) then {
                private _v = _base * _f;
                if (finite _v && {_v > 0}) then {
                    if (_ap < 0 || {abs (_v - _ap) > 0.0001}) then { _u setVariable ["tf_receivingDistanceMultiplicator", _v]; };
                    missionNamespace setVariable ["COMSPEC_ATAK_VoiceTf", [_u, _base, _v, _hadRx]];
                } else { ["bad"] call comspec_atak_native_fnc_radioGuard; };
            } else {
                if (_ap >= 0) then { [_u, "tf_receivingDistanceMultiplicator", _base, _hadRx, _ap] call _tfPut; };
                missionNamespace setVariable ["COMSPEC_ATAK_VoiceTf", []];
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_GuardOut", (missionNamespace getVariable ["COMSPEC_ATAK_GuardOut", 0]) + 1];
        ["voix par le maillage", _t0] call comspec_atak_native_fnc_perfAcc;
    };
};
