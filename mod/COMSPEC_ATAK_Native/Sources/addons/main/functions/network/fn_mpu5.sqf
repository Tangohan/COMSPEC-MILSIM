/*
    Radio maillée MPU5 (Wave Relay) et voix ACRE2 dans le téléphone. Pas de modèle 3D : la radio est un objet d'inventaire.
    Détection : objet COMSPEC_ATAK_MPU5, toute classe dont le nom contient « MPU5 » (objets d'autres mods), ou classes du
    réglage CBA comspec_atak_native_mpu5_items. ACRE2 n'a pas de radio maillée : la voix reste sur les radios ACRE2 du
    joueur, l'app Wave Relay en montre les canaux, fréquences, alternats (PTT) et qui émet.
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
    Lecture prudente d'ACRE2 : chaque fonction est testée avant l'appel ; sans ACRE2, seule la partie maillage reste.
*/
params [["_act", ""], ["_arg", []]];
private _acre = !isNil "acre_api_fnc_getCurrentRadioList";
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
        if (_u isNotEqualTo player) exitWith { (_u getVariable ["COMSPEC_ATAK_Mpu5", []]) isNotEqualTo [] };
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_Mpu5Has", []];
        if ((count _c) isEqualTo 2 && {diag_tickTime - (_c select 0) < 2}) exitWith { _c select 1 };
        private _extra = ((missionNamespace getVariable ["comspec_atak_native_mpu5_items", ""]) splitString ", ;") apply { toLower _x };
        private _has = (((items player) + (assignedItems player)) findIf {
            private _l = toLower _x;
            ((_l find "mpu5") >= 0) || {_l in _extra}
        }) >= 0;
        uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Has", [diag_tickTime, _has]];
        _has
    };
    case "range": {
        private _base = missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000];
        private _u = _arg param [0, player];
        if (_u isNotEqualTo player) exitWith {
            private _pub = _u getVariable ["COMSPEC_ATAK_Mpu5", []];
            if (_pub isEqualTo []) exitWith { _base };
            (_pub param [2, missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000]]) max _base
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
        if (_p isEqualTo [] || {!(missionNamespace getVariable ["comspec_atak_native_radio_data", true])}) exitWith { [] };
        _p params ["_id", "_kind"];
        // Radio rangée, jetée ou volée : la liaison tombe.
        private _ok = if (_acre) then {
            private _l = [] call acre_api_fnc_getCurrentRadioList;
            !isNil "_l" && {_id in _l}
        } else {
            if (_kind isEqualTo "LR") then {
                private _lr = if (isNil "TFAR_fnc_activeLrRadio") then { nil } else { call TFAR_fnc_activeLrRadio };
                !isNil "_lr" && {_lr isEqualType []} && {(count _lr) > 0}
            } else {
                private _sw = if (isNil "TFAR_fnc_activeSwRadio") then { nil } else { call TFAR_fnc_activeSwRadio };
                !isNil "_sw" && {_sw isEqualType ""} && {_sw isNotEqualTo ""}
            };
        };
        if (!_ok) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_RadioPlug", []]; [] };
        _p
    };
    case "plug": {
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
            private _l = [] call acre_api_fnc_getCurrentRadioList;
            if (isNil "_l") then { _l = []; };
            private _all = _l apply {
                private _b = toLower ([_x] call acre_api_fnc_getBaseRadio);
                [[1, 0] select ((_lrBase findIf { (_b find _x) isEqualTo 0 }) >= 0), _x, ["SW", "LR"] select ((_lrBase findIf { (_b find _x) isEqualTo 0 }) >= 0),
                    getText (configFile >> "CfgWeapons" >> _b >> "displayName")]
            } select { (toLower (_x select 1) find "acre_prc343") < 0 };
            _all sort true;
            if ((count _all) > 0) then { (_all select 0) params ["", "_id", "_k", "_n"]; _found = [_id, _k, [_n, _id] select (_n isEqualTo "")]; };
        } else {
            private _lr = if (isNil "TFAR_fnc_activeLrRadio") then { nil } else { call TFAR_fnc_activeLrRadio };
            private _sw = if (isNil "TFAR_fnc_activeSwRadio") then { nil } else { call TFAR_fnc_activeSwRadio };
            if (!isNil "_lr" && {_lr isEqualType []} && {(count _lr) > 0}) then {
                private _o = _lr select 0;
                _found = ["tfar_lr", "LR", [getText (configFile >> "CfgVehicles" >> typeOf _o >> "displayName"), "Radio longue portée"] select (isNull _o)];
            } else {
                if (!isNil "_sw" && {_sw isEqualType ""} && {_sw isNotEqualTo ""}) then {
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
        if (!isNil "acre_sys_data_fnc_dataEvent") then {
            private _cd = [_id, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
            if (!isNil "_cd") then {
                _tx = [_cd, "frequencyTX", -1] call _hget;
                _rx = [_cd, "frequencyRX", _tx] call _hget;
                { if (_lbl isEqualTo "") then { private _v = [_cd, _x, ""] call _hget; if (_v isEqualType "") then { _lbl = _v; }; }; } forEach ["description", "name", "label"];
            };
        };
        // Repli : préréglage de la radio au canal courant.
        if (!(_tx isEqualType 0) || {_tx <= 0}) then {
            if (isNil "acre_api_fnc_getPresetChannelField" || {isNil "acre_api_fnc_getPreset"}) exitWith {};
            private _base = [_id] call acre_api_fnc_getBaseRadio;
            private _ch = [_id] call acre_api_fnc_getRadioChannel;
            if (isNil "_base" || {isNil "_ch"} || {!(_ch isEqualType 0)}) exitWith {};
            private _pre = [_base] call acre_api_fnc_getPreset;
            if (isNil "_pre") exitWith {};
            _tx = [_base, _pre, _ch, "frequencyTX"] call acre_api_fnc_getPresetChannelField;
            _rx = [_base, _pre, _ch, "frequencyRX"] call acre_api_fnc_getPresetChannelField;
            if (isNil "_rx" || {!(_rx isEqualType 0)}) then { _rx = _tx; };
            if (_lbl isEqualTo "") then {
                {
                    if (_lbl isNotEqualTo "") then { continue };
                    private _v = [_base, _pre, _ch, _x] call acre_api_fnc_getPresetChannelField;
                    if (!isNil "_v" && {_v isEqualType ""}) then { _lbl = _v; };
                } forEach ["description", "name", "label"];
            };
        };
        if (isNil "_tx" || {!(_tx isEqualType 0)} || {_tx <= 0}) exitWith { [] };
        if (!(_rx isEqualType 0) || {_rx <= 0}) then { _rx = _tx; };
        [_tx, _rx, _lbl]
    };
    case "radios": {
        if (!_acre) exitWith { [] };
        private _list = [] call acre_api_fnc_getCurrentRadioList;
        if (isNil "_list" || {!(_list isEqualType [])}) exitWith { [] };
        private _cur = if (isNil "acre_api_fnc_getCurrentRadio") then { "" } else { [] call acre_api_fnc_getCurrentRadio };
        if (isNil "_cur") then { _cur = ""; };
        private _ptt = if (isNil "acre_api_fnc_getMultiPushToTalkAssignment") then { [] } else { [] call acre_api_fnc_getMultiPushToTalkAssignment };
        if (isNil "_ptt" || {!(_ptt isEqualType [])}) then { _ptt = []; };
        private _txId = (uiNamespace getVariable ["COMSPEC_ATAK_PttMine", ["", 0]]) select 0;
        _list apply {
            private _base = [_x] call acre_api_fnc_getBaseRadio;
            private _name = getText (configFile >> "CfgWeapons" >> _base >> "displayName");
            if (_name isEqualTo "") then { _name = _base; };
            private _ch = [_x] call acre_api_fnc_getRadioChannel;
            if (isNil "_ch") then { _ch = 0; };
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
            private _pub = ((_u getVariable ["COMSPEC_ATAK_VoiceNet", []]) select { (_x select 0) isEqualTo _id }) param [0, []];
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
            private _vn = _x getVariable ["COMSPEC_ATAK_VoiceNet", []];
            private _mpu = (_x getVariable ["COMSPEC_ATAK_Mpu5", []]) isNotEqualTo [];
            if (_vn isEqualTo [] && {!_mpu}) then { continue };
            private _fs = _vn apply { [_x select 1, _x select 2] };
            private _same = (_fs findIf { private _f = _x select 0; (_myF findIf { abs (_x - _f) < 0.001 }) >= 0 }) >= 0;
            _out pushBack [_x, _mpu, _fs, _same, _x in _talk];
        } forEach (allPlayers select { alive _x && {_x isNotEqualTo player} && {side group _x isEqualTo _side} });
        _out
    };
    case "tick": {
        private _src = ["source"] call comspec_atak_native_fnc_mpu5;
        private _has = _src isNotEqualTo [];
        private _was = uiNamespace getVariable ["COMSPEC_ATAK_Mpu5Was", false];
        if (([false, _src param [0, ""]] select _has) isNotEqualTo _was) then {
            uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Was", [false, _src param [0, ""]] select _has];
            uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
            uiNamespace setVariable ["COMSPEC_ATAK_Mesh", nil];
            ["INFO", [["Radio retirée : le téléphone repasse sur sa propre antenne", "MPU5 retirée : le téléphone repasse sur sa propre antenne"] select (_was isEqualTo "MPU5"),
                ["Données relayées par la radio", "MPU5 détectée : le téléphone passe par la radio maillée"] select ((_src param [0, ""]) isEqualTo "MPU5")] select _has, 4, 20] call comspec_atak_native_fnc_notify;
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
            private _soft = (count _mOld) isEqualTo 2 && {(count _pub) isEqualTo 2} && {(_mOld select 1) isEqualTo (_pub select 1)} && {(_mOld select 0) > 0} && {(_pub select 0) > 0};
            (uiNamespace getVariable ["COMSPEC_ATAK_Mpu5Pend", []]) params [["_pend", []], ["_pendN", 0]];
            if (_soft && {_pub isNotEqualTo _pend} && {_pendN < 2}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Pend", [_pub, _pendN + 1]];
            } else {
                uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Pend", []];
                player setVariable ["COMSPEC_ATAK_Mpu5", _pub, true];
            };
        };
        // Réseau voix publié pour les autres téléphones : fréquence d'émission et nom du canal de chaque radio ACRE2.
        private _vn = (["radios"] call comspec_atak_native_fnc_mpu5) select { (_x select 4) > 0 } apply { [_x select 0, parseNumber ((_x select 4) toFixed 4), _x select 3] };
        if (_vn isNotEqualTo (player getVariable ["COMSPEC_ATAK_VoiceNet", []])) then { player setVariable ["COMSPEC_ATAK_VoiceNet", _vn, true]; };
        // Émissions restées ouvertes (événement de fin perdu, joueur parti) : retirées au bout de 60 s.
        private _heard = (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]) select { !isNull (_x select 0) && {diag_tickTime - (_x select 2) < 60} };
        uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", _heard];
    };
    case "init": {
        if (!hasInterface) exitWith {};
        private _rerender = {
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            if ((_s getOrDefault ["activePage", ""]) isEqualTo "WAVERELAY" && {(_s getOrDefault ["wrTab", ""]) isEqualTo "TG"}) then {
                [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_Mpu5Rerender", _rerender];
        private _isRadio = { params ["_r"]; _r isEqualTo true || {_r isEqualTo 1} || {_r isEqualTo "1"} };
        uiNamespace setVariable ["COMSPEC_ATAK_Mpu5IsRadio", _isRadio];
        // Mon émission (alternat ACRE2 sur une radio).
        ["acre_startedSpeaking", {
            params ["_u", ["_r", false], ["_id", ""]];
            if (_u isNotEqualTo player || {!([_r] call (uiNamespace getVariable "COMSPEC_ATAK_Mpu5IsRadio"))} || {!(_id isEqualType "")}) exitWith {};
            uiNamespace setVariable ["COMSPEC_ATAK_PttMine", [_id, diag_tickTime]];
            call (uiNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        ["acre_stoppedSpeaking", {
            params ["_u"];
            if (_u isNotEqualTo player) exitWith {};
            uiNamespace setVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
            call (uiNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        // Émissions des autres joueurs : ACRE2 les signale à chaque client ; le tri « entendue ou non » se fait à l'affichage.
        ["acre_remoteStartedSpeaking", {
            params ["_u", ["_r", false], ["_id", ""]];
            if (isNull _u || {!([_r] call (uiNamespace getVariable "COMSPEC_ATAK_Mpu5IsRadio"))} || {!(_id isEqualType "")} || {_id isEqualTo ""}) exitWith {};
            private _h = (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]) select { (_x select 0) isNotEqualTo _u };
            _h pushBack [_u, _id, diag_tickTime];
            uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", _h];
            call (uiNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        ["acre_remoteStoppedSpeaking", {
            params ["_u"];
            uiNamespace setVariable ["COMSPEC_ATAK_PttHeard", (uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []]) select { (_x select 0) isNotEqualTo _u }];
            call (uiNamespace getVariable "COMSPEC_ATAK_Mpu5Rerender");
        }] call CBA_fnc_addEventHandler;
        [{ ["tick"] call comspec_atak_native_fnc_mpu5; }, 2] call CBA_fnc_addPerFrameHandler;
    };
};
