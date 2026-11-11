/*
    Radio maillée MPU5 (Wave Relay) et voix ACRE2 dans le téléphone. Pas de modèle 3D : la radio est un objet d'inventaire.
    Détection ("isMpu", seule règle, aussi pour fn_mpuLink) : objet COMSPEC_ATAK_MPU5, toute classe dont le nom contient « MPU5 »
    (ACRE_MPU5 du mod « MPU-5 Persistent Systems » et ses instances ACRE_MPU5_ID_n), classe de base ACRE2 « MPU5 »
    (acre_baseClass), marque Iceman_WR_isMPU5, ou classes du réglage CBA comspec_atak_native_mpu5_items. La voix reste sur les
    radios ACRE2 du joueur, l'app Wave Relay en montre les canaux, fréquences, alternats (PTT) et qui émet.
      "isMpu"    : [classe] -> vrai si c'est une MPU5.
      "detected" : radio de données affichée (app Wave Relay, fn_linkQuality) : [nom, classe, portée m, liaison, mod, type] ou [].
      "has"      : [unité] -> vrai si elle porte une MPU5 (joueur : inventaire ; autre joueur : état publié par son téléphone).
      "range"    : [unité] -> portée maillage m (réglage comspec_atak_native_mpu5_range avec MPU5, sinon COMSPEC_ATAK_MeshRange).
      "gain"     : gain dB de la MPU5 du joueur sur ses liaisons (réglage comspec_atak_native_mpu5_gain), 0 sans MPU5.
      "radios"   : radios ACRE2 du joueur : [[id, nom, canal, nom du canal, MHz émission, MHz réception, alternat (0 aucun,
                   1-3), active, en émission]...] ; [] sans ACRE2.
      "freq"     : [id radio ACRE2] -> [MHz émission, MHz réception, nom du canal] ou [] (données ACRE2, sinon préréglage).
      "speakers" : émissions radio en cours sur les fréquences des radios du joueur (et la sienne) : [[unité, id radio, MHz,
                   nom du canal, depuis s]...]. Une émission sur une fréquence qu'aucune de ses radios n'écoute n'apparaît pas ;
                   la portée radio n'est pas vérifiée (ACRE2 ne dit pas à un client ce qu'il entend vraiment).
      "peers"    : joueurs du camp dont le téléphone publie son réseau voix (COMSPEC_ATAK_VoiceNet) ou une MPU5 :
                   [[unité, MPU5, [[MHz, nom du canal]...], même fréquence que moi, en émission entendue]...].
      "tick"     : (toutes les 2 s) détecte la MPU5, publie COMSPEC_ATAK_Mpu5 [débit montant Mbit/s, bonds] ou [] et
                   COMSPEC_ATAK_VoiceNet [[id radio, MHz émission, nom du canal]...] quand ils changent.
      "init"     : événements CBA d'ACRE2 (acre_startedSpeaking, acre_stoppedSpeaking et leurs versions remote) et tick.
      "plug"     : branche le téléphone sur la radio ACRE2 ou TFAR du joueur (longue portée d'abord), ou le débranche :
                   la radio relaie alors les données comme une MPU5, avec ses propres réglages (comspec_atak_native_radio_*).
      "plugged"  : radio branchée encore portée par le joueur : [id, "LR" | "SW", nom] ou [].
      "source"   : ce qui relaie mes données : ["MPU5" | "LR" | "SW", nom, portée m, gain dB, débit max Mbit/s] ou [].
                   Liaison réaliste active (fn_mpuLink, réglage comspec_atak_native_mpu_real) : toujours [] (la MPU5 passe
                   par le maillage de fn_mpuLink) ; les radios ACRE2 / TFAR ne relient plus le téléphone : "plug" refuse et
                   "plugged" renvoie [] (réglage comspec_atak_native_radio_data ignoré ; sans MPU5, pas de réseau de données).
    Lecture prudente d'ACRE2 : chaque fonction est testée avant l'appel ; sans ACRE2, seule la partie maillage reste.
*/
params [["_act", ""], ["_arg", []]];
private _read = ["read"] call comspec_atak_native_fnc_radioGuard;
private _acre = _read && {!isNil "acre_api_fnc_getCurrentRadioList"};
// Seule porte vers ACRE2 et TFAR (fn_radioGuard) : fonction testée avant chaque appel, valeur de repli sinon.
private _rg = { params ["_n", ["_a", []], ["_d", false]]; ["api", [_n, _a, _d]] call comspec_atak_native_fnc_radioGuard };
// Données de canal ACRE2 : HashMap (ACRE2 récent) ou espace de noms (ancien hash ACRE), clés sans casse.
private _hget = {
    params ["_h", "_k", "_d"];
    if (isNil "_h") exitWith { _d };
    private _r = _d;
    if (_h isEqualType createHashMap) exitWith {
        { if ((toLower _x) isEqualTo (toLower _k)) exitWith { _r = _y; }; } forEach _h;
        _r
    };
    if (_h isEqualType locationNull || {_h isEqualType objNull} || {_h isEqualType missionNamespace}) then { _r = _h getVariable [_k, _d]; };
    _r
};
switch (_act) do {
    case "has": {
        private _u = _arg param [0, player];
        if (_u isNotEqualTo player) exitWith { (["mpu5", _u] call comspec_atak_native_fnc_meshRead) isNotEqualTo [] };
        // Liaison réaliste : toute radio de données reconnue (ACRE2 et TFAR d'abord, voir "gear").
        if (["active"] call comspec_atak_native_fnc_mpuLink) exitWith { (["gear"] call comspec_atak_native_fnc_mpu5) isNotEqualTo [] };
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_Mpu5Has", []];
        if ((count _c) isEqualTo 2 && {diag_tickTime - (_c select 0) < 2}) exitWith { _c select 1 };
        private _has = (((items player) + (assignedItems player)) findIf { ["isMpu", [_x]] call comspec_atak_native_fnc_mpu5 }) >= 0;
        uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Has", [diag_tickTime, _has]];
        _has
    };
    case "isMpu": {
        // Radio de données MPU5, quelle que soit sa classe : nom (COMSPEC_ATAK_MPU5, ACRE_MPU5 du mod « MPU-5 Persistent Systems »
        // et ses instances ACRE_MPU5_ID_n), classe de base ACRE2 (acre_baseClass), marque Iceman_WR_isMPU5 de ce mod, ou réglage
        // comspec_atak_native_mpu5_items. Lecture de la config seulement.
        private _cls = _arg param [0, ""];
        if (!(_cls isEqualType "") || {_cls isEqualTo ""}) exitWith { false };
        private _l = toLower _cls;
        if ((_l find "mpu5") >= 0) exitWith { true };
        if (_l in (((missionNamespace getVariable ["comspec_atak_native_mpu5_items", ""]) splitString ", ;") apply { toLower _x })) exitWith { true };
        private _cfg = configFile >> "CfgWeapons" >> _cls;
        if !(isClass _cfg) exitWith { false };
        (getNumber (_cfg >> "Iceman_WR_isMPU5")) > 0 || {((toLower getText (_cfg >> "acre_baseClass")) find "mpu5") >= 0}
    };
    case "detected": {
        // Radio de données montrée par l'app Wave Relay (ligne « Radio », message ACRE2) et par le facteur de débit (fn_linkQuality) :
        // une seule source, pour qu'elles ne se contredisent plus. Liaison réaliste : la radio de fn_mpuLink ("gear", ou MPU5 posée).
        // Ancien modèle : la source de "source". -> [nom lisible, classe, portée m, liaison ("cable" | "wifi" | "" non reliée |
        // "porte" | "branche"), mod ("acre" | "tfar" | "mod" | ""), type ("RADIOMPU" | "LR" | "SW" | "PERSO" | "MPU")] ou []
        if (["active"] call comspec_atak_native_fnc_mpuLink) exitWith {
            private _v = ["view"] call comspec_atak_native_fnc_mpuLink;
            private _g = _v get "gear";
            private _lk = (_v get "status") getOrDefault ["link", ""];
            if (_g isNotEqualTo []) exitWith { [_g select 3, _g select 1, _v get "range", _lk, _g select 7, _g select 2] };
            if (_v get "posed") exitWith { ["MPU5 posée", "", _v get "range", _lk, "", "MPU"] };
            []
        };
        private _src = ["source"] call comspec_atak_native_fnc_mpu5;
        if (_src isEqualTo []) exitWith { [] };
        if ((_src select 0) isEqualTo "MPU5") exitWith {
            private _it = (((items player) + (assignedItems player)) select { ["isMpu", [_x]] call comspec_atak_native_fnc_mpu5 }) param [0, ""];
            private _cfg = configFile >> "CfgWeapons" >> _it;
            private _n = getText (_cfg >> "displayName");
            private _l = toLower _it;
            private _mod = switch (true) do {
                case ((_l find "comspec_") isEqualTo 0): { "" };
                case ((_l find "acre_") isEqualTo 0 || {(getText (_cfg >> "acre_baseClass")) isNotEqualTo ""}): { "acre" };
                case ((getText (_cfg >> "tf_parent")) isNotEqualTo "" || {(getNumber (_cfg >> "tf_range")) > 0}): { "tfar" };
                default { "mod" };
            };
            [[_n, "MPU5"] select (_n isEqualTo ""), _it, ["range", [player]] call comspec_atak_native_fnc_mpu5, "porte", _mod, ["RADIOMPU", "MPU"] select (_mod isEqualTo "")]
        };
        private _p = ["plugged"] call comspec_atak_native_fnc_mpu5;
        [_src select 1, _p param [0, ""], ["range", [player]] call comspec_atak_native_fnc_mpu5, "branche", ["tfar", "acre"] select _acre, _src select 0]
    };
    case "gear": {
        // Radio qui porte le réseau du joueur, ses vrais objets d'abord : MPU5 d'un mod radio, longue portée, portative, puis notre MPU5 (complément), puis la radio personnelle.
        // Lecture de la config seulement (acre_baseClass, tf_parent, tf_range), aucune écriture chez ACRE2 ou TFAR. Cache 2 s.
        // -> [rang, classe, "RADIOMPU" | "LR" | "SW" | "PERSO" | "MPU", nom, portée m, gain dB, débit Mbit/s, "acre" | "tfar" | ""] ou []
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_GearC", []];
        if ((count _c) isEqualTo 2 && {diag_tickTime - (_c select 0) < 2}) exitWith { _c select 1 };
        private _srcMod = missionNamespace getVariable ["comspec_atak_native_radio_source", 0];
        private _useA = _read && {_srcMod in [0, 1]};
        private _useT = _read && {_srcMod in [0, 2]};
        private _lrR = missionNamespace getVariable ["comspec_atak_native_radio_lr_range", 10000];
        private _swR = missionNamespace getVariable ["comspec_atak_native_radio_sw_range", 2000];
        // ACRE2 n'a pas de portée fixe (modèle de propagation) : portée de données au prorata de la racine de la puissance. Puissances d'usine lues dans ACRE2 2.14.
        private _acreT = [["acre_prc117f", "LR", 20000], ["acre_prc77", "LR", 3500], ["acre_sem70", "LR", 4000], ["acre_prc152", "SW", 5000], ["acre_prc148", "SW", 5000], ["acre_bf888s", "SW", 5000], ["acre_sem52sl", "SW", 1000], ["acre_prc343", "PERSO", 100]];
        private _gearOf = {
            params ["_kind"];
            [_kind, [missionNamespace getVariable ["comspec_atak_native_radio_sw_gain", 4], missionNamespace getVariable ["comspec_atak_native_radio_lr_gain", 10]] select (_kind isEqualTo "LR"),
                [missionNamespace getVariable ["comspec_atak_native_radio_sw_mbps", 0.5], missionNamespace getVariable ["comspec_atak_native_radio_lr_mbps", 2]] select (_kind isEqualTo "LR")]
        };
        private _best = [];
        private _offer = {
            params ["_rank", "_cls", "_kind", "_name", "_range", "_gain", "_mbps", "_mod"];
            if (_best isEqualTo [] || {_rank < (_best select 0)} || {_rank isEqualTo (_best select 0) && {_range > (_best select 4)}}) then { _best = [_rank, _cls, _kind, _name, _range, _gain, _mbps, _mod]; };
        };
        {
            private _cls = _x;
            private _low = toLower _cls;
            private _cfg = configFile >> "CfgWeapons" >> _cls;
            private _name = getText (_cfg >> "displayName");
            if (_name isEqualTo "") then { _name = _cls; };
            // MPU5 de toute classe (fn_mpu5 "isMpu") : ACRE_MPU5 et ses instances ACRE_MPU5_ID_n comprises.
            if (["isMpu", [_cls]] call comspec_atak_native_fnc_mpu5) then {
                private _ours = (_low find "comspec_") isEqualTo 0;
                private _isA = (_low find "acre_") isEqualTo 0 || {(getText (_cfg >> "acre_baseClass")) isNotEqualTo ""};
                private _isT = (getText (_cfg >> "tf_parent")) isNotEqualTo "" || {(getNumber (_cfg >> "tf_range")) > 0};
                if (!_ours && {(_isA && {!_useA}) || {_isT && {!_useT}}}) then { continue };
                [[0, 3] select _ours, _cls, ["RADIOMPU", "MPU"] select _ours, _name, missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000],
                    missionNamespace getVariable ["comspec_atak_native_mpu5_gain", 8], missionNamespace getVariable ["comspec_atak_native_mpu5_mbps", 20],
                    switch (true) do { case (_ours): { "" }; case (_isA): { "acre" }; case (_isT): { "tfar" }; default { "mod" }; }] call _offer;
                continue
            };
            if (_useA) then {
                private _b = toLower getText (_cfg >> "acre_baseClass");
                if (_b isEqualTo "") then { _b = _low; };
                private _i = _acreT findIf { (_b find (_x select 0)) isEqualTo 0 };
                if (_i >= 0) exitWith {
                    (_acreT select _i) params ["", "_kind", "_mw"];
                    // Puissance réglée sur la radio, si ACRE2 la donne : valeur d'usine sinon.
                    if ((_low find "_id_") >= 0) then {
                        private _cd = ["acre_sys_data_fnc_dataEvent", [_cls, "getCurrentChannelData"], createHashMap] call _rg;
                        private _pw = [_cd, "power", -1] call _hget;
                        if (_pw isEqualType 0 && {_pw > 0} && {finite _pw}) then { _mw = _pw; };
                    };
                    private _ref = [5000, 20000] select (_kind isEqualTo "LR");
                    private _top = [_swR, _lrR] select (_kind isEqualTo "LR");
                    ([_kind] call _gearOf) params ["", "_g", "_mb"];
                    if (_kind isEqualTo "PERSO") then { _mb = 0.1; _g = 0; };
                    [[2, 1, 4] select (["SW", "LR", "PERSO"] find _kind), _cls, _kind, _name, (round (_top * sqrt (_mw / _ref))) max 300 min 20000, _g, _mb, "acre"] call _offer;
                };
            };
            if (_useT) then {
                private _r = getNumber (_cfg >> "tf_range");
                if (_r > 0 || {(getText (_cfg >> "tf_parent")) isNotEqualTo ""}) then {
                    if (_r <= 0) then { _r = getNumber (configFile >> "CfgWeapons" >> getText (_cfg >> "tf_parent") >> "tf_range"); };
                    if (_r <= 0) then { _r = _swR; };
                    (["SW"] call _gearOf) params ["", "_g", "_mb"];
                    [2, _cls, "SW", _name, _r min 20000, _g, _mb, "tfar"] call _offer;
                };
            };
        } forEach ((items player) + (assignedItems player));
        // TFAR : la radio longue portée est un sac à dos, sa portée est dans sa config.
        if (_useT) then {
            private _bp = backpack player;
            private _cv = configFile >> "CfgVehicles" >> _bp;
            private _r = getNumber (_cv >> "tf_range");
            if (_bp isNotEqualTo "" && {_r > 0}) then {
                (["LR"] call _gearOf) params ["", "_g", "_mb"];
                [1, _bp, "LR", getText (_cv >> "displayName"), _r min 20000, _g, _mb, "tfar"] call _offer;
            };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_GearC", [diag_tickTime, _best]];
        _best
    };
    case "site": {
        // Maillage du téléphone vers le site (/m/radio/mpu) : variables locales lues par COMSPEC Link à chaque position (fn_updatePosition, champs wr_*). Jamais diffusées.
        // ATAK Enhanced chargé : ces variables sont les siennes. Mode avion : rien ne remonte.
        if (!isNil "Iceman_fnc_wr_onOpened" || {isNull player}) exitWith {};
        private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
        private _node = player getVariable ["COMSPEC_ATAK_MpuNode", []];
        private _legacy = player getVariable ["COMSPEC_ATAK_Mpu5", []];
        private _on = !(missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) && {[_legacy isNotEqualTo [], _node isNotEqualTo []] select _real};
        if (!_on) exitWith { if (player getVariable ["Iceman_WR_hasMPU5", false]) then { player setVariable ["Iceman_WR_hasMPU5", false]; }; };
        private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        private _tg = player getVariable ["COMSPEC_ATAK_Talkgroup", []];
        private _chan = parseNumber ((_node param [0, "C1"]) select [1]);
        private _vn = player getVariable ["COMSPEC_ATAK_VoiceNet", []];
        private _gear = ["gear"] call comspec_atak_native_fnc_mpu5;
        private _mode = _m getOrDefault ["mode", ""];
        player setVariable ["Iceman_WR_activeTG", if (_tg isEqualType [] && {_tg isNotEqualTo []}) then { _tg select 0 } else { -1 }];
        player setVariable ["Iceman_WR_frequency", if (_vn isNotEqualTo []) then { (_vn select 0) select 1 } else { 2200 + 20 * ((_chan max 1) - 1) }];
        player setVariable ["Iceman_WR_nodeId", (format ["C%1 %2", _chan max 1, _gear param [3, _legacy param [3, "MPU5"]]]) select [0, 32]];
        player setVariable ["Iceman_WR_gateway", _real && {_mode isEqualTo "self" || {_mode isEqualTo "lead" && {leader group player isEqualTo player}}}];
        player setVariable ["Iceman_WR_bridgeActive", ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) param [0, []]) isNotEqualTo []];
        player setVariable ["Iceman_WR_hasMPU5", true];
    };
    case "range": {
        private _base = missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000];
        private _u = _arg param [0, player];
        if (_u isNotEqualTo player) exitWith {
            private _pub = ["mpu5", _u] call comspec_atak_native_fnc_meshRead;
            if (_pub isEqualTo []) exitWith { _base };
            (_pub select 2) max _base
        };
        private _src = ["source"] call comspec_atak_native_fnc_mpu5;
        if (_src isEqualTo []) exitWith { _base };
        (_src select 2) max _base
    };
    case "gain": {
        (["source"] call comspec_atak_native_fnc_mpu5) param [3, 0]
    };
    case "plugged": {
        private _p = uiNamespace getVariable ["COMSPEC_ATAK_RadioPlug", []];
        if (_p isEqualTo [] || {!(missionNamespace getVariable ["comspec_atak_native_radio_data", true])} || {["active"] call comspec_atak_native_fnc_mpuLink}) exitWith { [] };
        _p params ["_id", "_kind"];
        // Radio rangée, jetée ou volée : la liaison tombe.
        private _ok = if (_acre) then {
            private _l = ["acre_api_fnc_getCurrentRadioList", [], []] call _rg;
            _l isEqualType [] && {_id in _l}
        } else {
            if (_kind isEqualTo "LR") then {
                private _lr = ["TFAR_fnc_activeLrRadio", [], []] call _rg;
                _lr isEqualType [] && {(count _lr) > 0}
            } else {
                private _sw = ["TFAR_fnc_activeSwRadio", [], ""] call _rg;
                _sw isEqualType "" && {_sw isNotEqualTo ""}
            };
        };
        if (!_ok) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_RadioPlug", []]; [] };
        _p
    };
    case "plug": {
        // Liaison réaliste (fn_mpuLink) : seule une MPU5 relie le téléphone au réseau, une radio ACRE2 / TFAR ne le relie pas.
        if (["active"] call comspec_atak_native_fnc_mpuLink) exitWith {
            ["INFO", "Liaison réaliste : le téléphone passe seul par votre radio ACRE2, TFAR ou MPU5, rien à brancher", 5, 20] call comspec_atak_native_fnc_notify; false
        };
        if ((["plugged"] call comspec_atak_native_fnc_mpu5) isNotEqualTo []) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_RadioPlug", []];
            ["INFO", "Téléphone débranché de la radio", 3, 20] call comspec_atak_native_fnc_notify;
            false
        };
        if !(missionNamespace getVariable ["comspec_atak_native_radio_data", true]) exitWith {
            ["WARNING", "Données par radio désactivées sur ce serveur", 3, 20] call comspec_atak_native_fnc_notify; false
        };
        // Radios longue portée (sac à dos, véhicule) d'abord : plus de portée, plus de débit.
        private _lrBase = ["acre_prc117f", "acre_prc77", "acre_sem70", "acre_vrc"];
        private _found = [];
        if (_acre) then {
            private _l = ["acre_api_fnc_getCurrentRadioList", [], []] call _rg;
            if !(_l isEqualType []) then { _l = []; };
            private _all = _l apply {
                private _b = toLower (["acre_api_fnc_getBaseRadio", [_x], ""] call _rg);
                [[1, 0] select ((_lrBase findIf { (_b find _x) isEqualTo 0 }) >= 0), _x, ["SW", "LR"] select ((_lrBase findIf { (_b find _x) isEqualTo 0 }) >= 0),
                    getText (configFile >> "CfgWeapons" >> _b >> "displayName")]
            } select { (toLower (_x select 1) find "acre_prc343") < 0 };
            _all sort true;
            if ((count _all) > 0) then { (_all select 0) params ["", "_id", "_k", "_n"]; _found = [_id, _k, [_n, _id] select (_n isEqualTo "")]; };
        } else {
            private _lr = ["TFAR_fnc_activeLrRadio", [], []] call _rg;
            private _sw = ["TFAR_fnc_activeSwRadio", [], ""] call _rg;
            if (_lr isEqualType [] && {(count _lr) > 0}) then {
                private _o = _lr select 0;
                _found = ["tfar_lr", "LR", [getText (configFile >> "CfgVehicles" >> typeOf _o >> "displayName"), "Radio longue portée"] select (isNull _o)];
            } else {
                if (_sw isEqualType "" && {_sw isNotEqualTo ""}) then {
                    _found = [_sw, "SW", getText (configFile >> "CfgWeapons" >> _sw >> "displayName")];
                };
            };
        };
        if (_found isEqualTo []) exitWith {
            ["WARNING", "Aucune radio ACRE2 ou TFAR compatible sur vous (la radio personnelle 343 ne transporte pas de données)", 4, 20] call comspec_atak_native_fnc_notify; false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_RadioPlug", _found];
        uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Has", nil];
        ["INFO", format ["Téléphone branché sur %1 : les données passent par la radio", _found select 2], 4, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "source": {
        // Liaison réaliste (fn_mpuLink) : la radio ne sert plus de relais « porté » de l'ancien modèle.
        if (["active"] call comspec_atak_native_fnc_mpuLink) exitWith { [] };
        // Objet obligatoire manquant (fn_netRequire) : ni MPU5 ni radio ne relaient plus rien.
        if ((["denied"] call comspec_atak_native_fnc_netRequire) isNotEqualTo []) exitWith { [] };
        if (["has", [player]] call comspec_atak_native_fnc_mpu5) exitWith {
            ["MPU5", "MPU5", missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000],
                missionNamespace getVariable ["comspec_atak_native_mpu5_gain", 8], missionNamespace getVariable ["comspec_atak_native_mpu5_mbps", 20]]
        };
        private _p = ["plugged"] call comspec_atak_native_fnc_mpu5;
        if (_p isEqualTo []) exitWith { [] };
        private _k = toLower (_p select 1);
        [_p select 1, _p select 2,
            missionNamespace getVariable [format ["comspec_atak_native_radio_%1_range", _k], [2000, 10000] select (_k isEqualTo "lr")],
            missionNamespace getVariable [format ["comspec_atak_native_radio_%1_gain", _k], [4, 10] select (_k isEqualTo "lr")],
            missionNamespace getVariable [format ["comspec_atak_native_radio_%1_mbps", _k], [0.5, 2] select (_k isEqualTo "lr")]]
    };
    case "freq": {
        private _id = _arg param [0, ""];
        if (!_acre || {_id isEqualTo ""}) exitWith { [] };
        private _tx = -1;
        private _rx = -1;
        private _lbl = "";
        // Données vivantes de la radio (fréquence réglée à la main comprise), lisibles pour les radios des autres joueurs
        // si ACRE2 les a synchronisées.
        if (["has", ["acre_sys_data_fnc_dataEvent"]] call comspec_atak_native_fnc_radioGuard) then {
            private _cd = ["acre_sys_data_fnc_dataEvent", [_id, "getCurrentChannelData"], -1] call _rg;
            if !(_cd isEqualTo -1) then {
                _tx = [_cd, "frequencyTX", -1] call _hget;
                _rx = [_cd, "frequencyRX", _tx] call _hget;
                { if (_lbl isEqualTo "") then { private _v = [_cd, _x, ""] call _hget; if (_v isEqualType "") then { _lbl = _v; }; }; } forEach ["description", "name", "label"];
            };
        };
        // Repli : préréglage de la radio au canal courant.
        if (!(_tx isEqualType 0) || {_tx <= 0}) then {
            private _base = ["acre_api_fnc_getBaseRadio", [_id], ""] call _rg;
            private _ch = ["acre_api_fnc_getRadioChannel", [_id], -1] call _rg;
            if (_base isEqualTo "" || {!(_ch isEqualType 0)} || {_ch < 0}) exitWith {};
            private _pre = ["acre_api_fnc_getPreset", [_base], ""] call _rg;
            if (_pre isEqualTo "") exitWith {};
            _tx = ["acre_api_fnc_getPresetChannelField", [_base, _pre, _ch, "frequencyTX"], -1] call _rg;
            _rx = ["acre_api_fnc_getPresetChannelField", [_base, _pre, _ch, "frequencyRX"], -1] call _rg;
            if !(_rx isEqualType 0) then { _rx = _tx; };
            if (_lbl isEqualTo "") then {
                {
                    if (_lbl isNotEqualTo "") then { continue };
                    private _v = ["acre_api_fnc_getPresetChannelField", [_base, _pre, _ch, _x], ""] call _rg;
                    if (_v isEqualType "") then { _lbl = _v; };
                } forEach ["description", "name", "label"];
            };
        };
        if (!(_tx isEqualType 0) || {_tx <= 0}) exitWith { [] };
        if (!(_rx isEqualType 0) || {_rx <= 0}) then { _rx = _tx; };
        [_tx, _rx, _lbl]
    };
    case "radios": {
        if (!_acre) exitWith { [] };
        private _list = ["acre_api_fnc_getCurrentRadioList", [], []] call _rg;
        if !(_list isEqualType []) exitWith { [] };
        private _cur = ["acre_api_fnc_getCurrentRadio", [], ""] call _rg;
        private _ptt = ["acre_api_fnc_getMultiPushToTalkAssignment", [], []] call _rg;
        if !(_ptt isEqualType []) then { _ptt = []; };
        private _txId = (uiNamespace getVariable ["COMSPEC_ATAK_PttMine", ["", 0]]) select 0;
        _list apply {
            private _base = ["acre_api_fnc_getBaseRadio", [_x], ""] call _rg;
            private _name = getText (configFile >> "CfgWeapons" >> _base >> "displayName");
            if (_name isEqualTo "") then { _name = _base; };
            private _ch = ["acre_api_fnc_getRadioChannel", [_x], 0] call _rg;
            (["freq", [_x]] call comspec_atak_native_fnc_mpu5) params [["_tx", -1], ["_rx", -1], ["_lbl", ""]];
            [_x, _name, _ch, _lbl, _tx, _rx, (_ptt find _x) + 1, _x isEqualTo _cur, _x isEqualTo _txId]
        }
    };
    case "speakers": {
        private _now = diag_tickTime;
        private _mine = (["radios"] call comspec_atak_native_fnc_mpu5) apply { _x select 5 };
        private _out = [];
        private _me = uiNamespace getVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
        if ((_me select 0) isNotEqualTo "") then {
            (["freq", [_me select 0]] call comspec_atak_native_fnc_mpu5) params [["_tx", -1], ["", -1], ["_lbl", ""]];
            _out pushBack [player, _me select 0, _tx, _lbl, _now - (_me select 1)];
        };
        {
            _x params ["_u", "_id", "_t0"];
            if (isNull _u || {!alive _u}) then { continue };
            // Fréquence de l'émetteur : publiée par son téléphone, sinon lue dans les données ACRE2 de sa radio.
            private _pub = ((["voiceNet", _u] call comspec_atak_native_fnc_meshRead) select { (_x select 0) isEqualTo _id }) param [0, []];
            private _f = if (_pub isNotEqualTo []) then { [_pub select 1, _pub select 1, _pub select 2] } else { ["freq", [_id]] call comspec_atak_native_fnc_mpu5 };
            if (_f isEqualTo []) then { continue };
            // Entendue seulement si une de mes radios reçoit cette fréquence (à 1 kHz près).
            private _tx = _f select 0;
            if ((_mine findIf { abs (_x - _tx) < 0.001 }) < 0) then { continue };
            _out pushBack [_u, _id, _tx, _f select 2, _now - _t0];
        } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]);
        _out
    };
    case "peers": {
        private _side = side group player;
        private _myF = (["radios"] call comspec_atak_native_fnc_mpu5) apply { _x select 5 };
        private _talk = (["speakers"] call comspec_atak_native_fnc_mpu5) apply { _x select 0 };
        private _out = [];
        {
            private _vn = ["voiceNet", _x] call comspec_atak_native_fnc_meshRead;
            private _mpu = (["mpu5", _x] call comspec_atak_native_fnc_meshRead) isNotEqualTo [];
            if (_vn isEqualTo [] && {!_mpu}) then { continue };
            private _fs = _vn apply { [_x select 1, _x select 2] };
            private _same = (_fs findIf { private _f = _x select 0; (_myF findIf { abs (_x - _f) < 0.001 }) >= 0 }) >= 0;
            _out pushBack [_x, _mpu, _fs, _same, _x in _talk];
        } forEach (allPlayers select { alive _x && {_x isNotEqualTo player} && {side group _x isEqualTo _side} });
        _out
    };
    case "tick": {
        ["tick"] call comspec_atak_native_fnc_netRequire;
        // Carte d'état de l'app Wave Relay ouverte sur ÉTAT : chiffres à jour sans redessiner la page.
        ["live"] call comspec_atak_native_fnc_waveRelayStatus;
        private _src = ["source"] call comspec_atak_native_fnc_mpu5;
        private _has = _src isNotEqualTo [];
        private _was = uiNamespace getVariable ["COMSPEC_ATAK_Mpu5Was", false];
        if (([false, _src param [0, ""]] select _has) isNotEqualTo _was) then {
            uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Was", [false, _src param [0, ""]] select _has];
            uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
            uiNamespace setVariable ["COMSPEC_ATAK_Mesh", nil];
            if ((["denied"] call comspec_atak_native_fnc_netRequire) isEqualTo []) then { ["INFO", [["Radio retirée : le téléphone repasse sur sa propre antenne", "MPU5 retirée : le téléphone repasse sur sa propre antenne"] select (_was isEqualTo "MPU5"),
                ["Données relayées par la radio", "MPU5 détectée : le téléphone passe par la radio maillée"] select ((_src param [0, ""]) isEqualTo "MPU5")] select _has, 4, 20] call comspec_atak_native_fnc_notify; };
        };
        // État publié : débit montant reçu d'un relais (ou d'une autre MPU5, au plus 3 bonds) que la MPU5 relaie à l'équipe.
        private _pub = [];
        if (_has) then {
            private _b = ["best"] call comspec_atak_native_fnc_relayNet;
            private _mb = 0;
            private _hops = 0;
            if (_b isNotEqualTo [] && {_b select 14}) then {
                _mb = (_b select 3) min (_src select 4);
                if ((_b select 5) isEqualTo "mpu5") then {
                    private _e = ["entry", _b select 0] call comspec_atak_native_fnc_relayNet;
                    _hops = (_e param [18, 0]) + 1;
                };
            };
            // Portée et nom publiés : les autres téléphones voient ce nœud avec la portée de sa radio (MPU5, LR ou SW).
            _pub = [parseNumber (_mb toFixed 2), _hops, round (_src select 2), _src select 1];
        };
        // Débit qui varie seul (évanouissement autour d'un palier de modulation) : diffusé s'il tient deux tours de suite
        // (comme fn_feedLink) ; changement de sauts, coupure ou retour du réseau : tout de suite. Débit qui change à chaque
        // tour (porteur en mouvement, mode simplifié où le débit suit la distance) : diffusé quand même au 3e tour en attente,
        // pour ne pas laisser l'équipe sur un débit périmé.
        private _mOld = player getVariable ["COMSPEC_ATAK_Mpu5", []];
        if (_pub isEqualTo _mOld) then { uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Pend", []]; } else {
            private _soft = (count _mOld) isEqualTo 4 && {(count _pub) isEqualTo 4} && {(_mOld select [1, 3]) isEqualTo (_pub select [1, 3])} && {(_mOld select 0) > 0} && {(_pub select 0) > 0};
            (uiNamespace getVariable ["COMSPEC_ATAK_Mpu5Pend", []]) params [["_pend", []], ["_pendN", 0]];
            if (_soft && {_pub isNotEqualTo _pend} && {_pendN < 2}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Pend", [_pub, _pendN + 1]];
            } else {
                uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Pend", []];
                player setVariable ["COMSPEC_ATAK_Mpu5", _pub];
            };
        };
        // Réseau voix publié pour les autres téléphones : fréquence d'émission et nom du canal de chaque radio ACRE2.
        private _vn = (["radios"] call comspec_atak_native_fnc_mpu5) select { (_x select 4) > 0 } apply { [_x select 0, parseNumber ((_x select 4) toFixed 4), _x select 3] };
        if (_vn isNotEqualTo (player getVariable ["COMSPEC_ATAK_VoiceNet", []])) then { player setVariable ["COMSPEC_ATAK_VoiceNet", _vn]; };
        // Une seule variable diffusée pour tout le maillage (fn_meshRead, lot 8).
        ["publish"] call comspec_atak_native_fnc_meshRead;
        ["site"] call comspec_atak_native_fnc_mpu5;
        ["flush"] call comspec_atak_native_fnc_p2pArchive;
        // Émissions restées ouvertes (événement de fin perdu, joueur parti) : retirées au bout de 60 s.
        private _heard = (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]) select { !isNull (_x select 0) && {diag_tickTime - (_x select 2) < 60} };
        uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", _heard];
    };
    case "init": {
        if (!hasInterface) exitWith {};
        private _rerender = {
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            if ((_s getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY" && {(_s getOrDefault ["wrTab", ""]) isEqualTo "TG"}) then {
                [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_Mpu5Rerender", _rerender];
        private _isRadio = { params ["_r"]; _r isEqualTo true || {_r isEqualTo 1} || {_r isEqualTo "1"} };
        missionNamespace setVariable ["COMSPEC_ATAK_Mpu5IsRadio", _isRadio];
        // Mon émission (alternat ACRE2 sur une radio).
        // Événements d'ACRE2 : lecture seule, nos seules variables. Tout lien coupé : rien.
        ["acre_startedSpeaking", {
            if !(["read"] call comspec_atak_native_fnc_radioGuard) exitWith {};
            params ["_u", ["_r", false], ["_id", ""]];
            if (_u isNotEqualTo player || {!([_r] call (missionNamespace getVariable "COMSPEC_ATAK_Mpu5IsRadio"))} || {!(_id isEqualType "")}) exitWith {};
            uiNamespace setVariable ["COMSPEC_ATAK_PttMine", [_id, diag_tickTime]];
            call (missionNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        ["acre_stoppedSpeaking", {
            params ["_u"];
            if (_u isNotEqualTo player) exitWith {};
            uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
            call (missionNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        // Émissions des autres joueurs : ACRE2 les signale à chaque client ; le tri « entendue ou non » se fait à l'affichage.
        ["acre_remoteStartedSpeaking", {
            if !(["read"] call comspec_atak_native_fnc_radioGuard) exitWith {};
            params ["_u", ["_r", false], ["_id", ""]];
            if (isNull _u || {!([_r] call (missionNamespace getVariable "COMSPEC_ATAK_Mpu5IsRadio"))} || {!(_id isEqualType "")} || {_id isEqualTo ""}) exitWith {};
            private _h = (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]) select { (_x select 0) isNotEqualTo _u };
            _h pushBack [_u, _id, diag_tickTime];
            uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", _h];
            call (missionNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        ["acre_remoteStoppedSpeaking", {
            params ["_u"];
            uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]) select { (_x select 0) isNotEqualTo _u }];
            call (missionNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        [{ ["tick"] call comspec_atak_native_fnc_mpu5; }, 2] call CBA_fnc_addPerFrameHandler;
    };
};
