/*
    Liaison réaliste téléphone <-> radio de données (réglage serveur comspec_atak_native_mpu_real, désactivé par défaut :
    rien ne change, le téléphone garde son ancien réseau et fn_mpu5 son rôle d'avant).
    Comme sur le terrain, l'EUD (téléphone ATAK) n'a pas de réseau tactique propre : il passe par une radio de données
    reliée par le câble EUD (USB-C) ou par le Wi-Fi local de la radio, à quelques mètres.
      - MPU5 (Wave Relay, objet COMSPEC_ATAK_MPU5 ou toute classe « MPU5 », fn_mpu5 "has") : nœud d'un réseau maillé
        (MANET) avec les autres MPU5 allumées sur le même canal et les relais de la mission (pylônes, module
        « ATAK · Relais », passerelles : passerelles du maillage, sur tous les canaux). Multi-sauts : chaque MPU5 allumée
        relaie les autres, même si son porteur n'y relie pas son téléphone. Portée selon la puissance (1, 3 ou 6 W ;
        réglage comspec_atak_native_mpu5_range à 6 W), relief (fn_radioLink "coarse", en cache 30 s), brouilleurs.
        Batterie propre (autonomie comspec_atak_native_mpu_hours à 6 W, moins à basse puissance), objet
        COMSPEC_ATAK_RadioBattery pour la changer. Posée au sol (Wi-Fi seulement), elle reste un nœud à sa place.
    Sans MPU5 : pas de réseau de données (le Bluetooth entre téléphones et les données déjà reçues restent). Les radios
    ACRE2 / TFAR ne relient pas le téléphone : le branchement de l'ancien modèle (fn_mpu5 "plug", réglage
    comspec_atak_native_radio_data) est ignoré ici. Le repli ACRE2 / TFAR en données de la 2.9.31 est retiré : une
    telle liaison restée en mémoire est remise à « aucune radio reliée » au tick suivant, avec une notification.
    Passerelle du maillage : relais de la mission en service ; sans relais, la MPU5 du chef de groupe (sur le même canal) ;
    sinon la radio du joueur elle-même (maillage autonome de l'équipe, solo).
    Calculs : "tick" toutes les 2 s (aucun calcul par image), maillage recalculé au plus toutes les 4 s dans un script
    planifié (un seul à la fois), lignes de vue gardées 30 s ; fn_linkQuality ne lit que le dernier état ("status").
    Variable publique : COMSPEC_ATAK_MpuNode = [réseau ("C<canal>"), portée m, objet antenne (joueur ou radio posée)]
    ou [] ; publiée seulement quand elle change.
    Voix ACRE2 / TFAR relayée par le maillage (fn_mpuVoice, réglage comspec_atak_native_mpu_voice) : une radio pontée sur
    la MPU5 suffit pour que le maillage soit calculé (téléphone relié ou non) ; "mesh" donne aussi "reach" (netId du
    porteur -> sauts, nœuds joints) et "vkey" (cœur de réseau commun : "R<camp>" relais, "L<netId du chef>" groupe).
      "active"   -> vrai si le réalisme est actif
      "status"   -> HashMap : active, kind ("" | "mpu" | "veh"), link ("" | "cable" | "wifi"), ok, why,
                    kbps, lat, loss, hops, gw, via, mode ("relay" | "lead" | "self"), nodes, wifiD, wifiQ
      "noDirect" -> vrai si le téléphone ne parle plus directement aux relais (fn_relayNet "best")
      "view"     -> HashMap pour l'app Réseau (état de la radio et "status")
      "link"     [mode "cable" | "wifi"] ; "unlink"
      "power" ; "chan" [+1 | -1] ; "txpower" ; "battery" ; "info"
      "tick", "mesh" (planifié), "aceInit"
*/
params [["_act", "status"], ["_arg", []]];
private _active = { missionNamespace getVariable ["comspec_atak_native_mpu_real", false] };
private _state = {
    private _s = uiNamespace getVariable "COMSPEC_ATAK_MpuSt";
    if (isNil "_s") then {
        _s = createHashMapFromArray [["on", true], ["chan", 1], ["pwr", 2], ["link", ""], ["batt", 100], ["holder", objNull], ["at", diag_tickTime], ["warned", 100]];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuSt", _s];
    };
    _s
};
private _isMpu = {
    params ["_c"];
    private _l = toLower _c;
    ((_l find "mpu5") >= 0) || {_l in (((missionNamespace getVariable ["comspec_atak_native_mpu5_items", ""]) splitString ", ;") apply { toLower _x })}
};
private _holderHas = { params ["_h"]; !isNull _h && {((itemCargo _h) findIf { [_x] call _isMpu }) >= 0} };
private _findPosed = {
    params ["_rad"];
    private _h = objNull;
    { if ([_x] call _holderHas) exitWith { _h = _x; }; } forEach (nearestObjects [player, ["WeaponHolder", "GroundWeaponHolder", "WeaponHolderSimulated"], _rad]);
    _h
};
private _antOf = { params ["_o"]; if (_o isKindOf "CAManBase") then { (eyePos _o) vectorAdd [0, 0, 0.4] } else { (getPosASL _o) vectorAdd [0, 0, 0.8] } };
private _say = {
    params ["_type", "_msg"];
    if !([_msg, 4] call comspec_atak_native_fnc_toast) then { [_type, _msg, 4, 30] call comspec_atak_native_fnc_notify; };
};
private _rerender = {
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "NETWORK") then {
        [{ ["NETWORK"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
};
// Après un changement : maillage et qualité de liaison recalculés tout de suite, page Réseau redessinée.
private _kick = {
    uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
    ["tick"] call comspec_atak_native_fnc_mpuLink;
    call _rerender;
    playSound "ClickSoft";
};
private _pwrK = [0.41, 0.71, 1];
private _freqOf = { params ["_c"]; 2200 + 20 * (_c - 1) };
private _hasCable = { !(missionNamespace getVariable ["comspec_atak_native_mpu_cable", true]) || {"COMSPEC_ATAK_EudCable" in (items player)} };
switch (_act) do {
    case "active": { call _active };
    case "status": {
        if !(call _active) exitWith {
            private _off = missionNamespace getVariable "COMSPEC_ATAK_MpuOff";
            if (isNil "_off") then { _off = createHashMapFromArray [["active", false], ["kind", ""], ["ok", true]]; missionNamespace setVariable ["COMSPEC_ATAK_MpuOff", _off]; };
            _off
        };
        private _s = uiNamespace getVariable "COMSPEC_ATAK_MpuStatus";
        if (isNil "_s") then { _s = createHashMapFromArray [["active", true], ["kind", ""], ["link", ""], ["ok", false], ["why", "sans MPU5 : pas de réseau de données"], ["kbps", 0], ["lat", 0], ["loss", 0], ["hops", -1], ["gw", ""], ["via", ""], ["mode", ""], ["nodes", []], ["wifiD", -1], ["wifiQ", 0]]; };
        _s
    };
    case "noDirect": {
        if !(call _active) exitWith { false };
        private _s = ["status"] call comspec_atak_native_fnc_mpuLink;
        private _k = _s getOrDefault ["kind", ""];
        if (_k isEqualTo "veh") exitWith { false };
        // Radio en service : tout passe par elle. Radio obligatoire : jamais de liaison directe. Sinon (radio
        // facultative, sans réseau) : le téléphone garde sa liaison d'avant.
        (_k isEqualTo "mpu" && {_s getOrDefault ["ok", false]}) || {missionNamespace getVariable ["comspec_atak_native_mpu_require", true]}
    };
    case "view": {
        private _st = call _state;
        private _carried = ["has", [player]] call comspec_atak_native_fnc_mpu5;
        private _h = _st getOrDefault ["holder", objNull];
        private _pwr = _st get "pwr";
        private _hours = (missionNamespace getVariable ["comspec_atak_native_mpu_hours", 10]) max 0.1;
        private _left = round (((_st get "batt") / 100) * _hours * 60 / ([0.55, 0.75, 1] select _pwr));
        createHashMapFromArray [
            ["carried", _carried], ["posed", !_carried && {[_h] call _holderHas}], ["posedD", [-1, round (player distance _h)] select (!isNull _h)],
            ["on", _st get "on"], ["chan", _st get "chan"], ["freq", [_st get "chan"] call _freqOf], ["pwr", _pwr],
            ["pwrLabel", ["Faible (1 W)", "Moyenne (3 W)", "Max (6 W)"] select _pwr],
            ["range", round ((missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000]) * (_pwrK select _pwr))],
            ["batt", round (_st get "batt")], ["left", _left], ["link", _st get "link"],
            ["wifi", missionNamespace getVariable ["comspec_atak_native_mpu_wifi", true]], ["wifiR", missionNamespace getVariable ["comspec_atak_native_mpu_wifi_range", 8]],
            ["cable", "COMSPEC_ATAK_EudCable" in (items player)], ["cableReq", missionNamespace getVariable ["comspec_atak_native_mpu_cable", true]],
            ["spare", "COMSPEC_ATAK_RadioBattery" in (items player)],
            ["status", ["status"] call comspec_atak_native_fnc_mpuLink]
        ]
    };
    case "link": {
        private _mode = _arg param [0, "cable"];
        if (!(call _active) || {!(_mode in ["cable", "wifi"])}) exitWith { false };
        private _st = call _state;
        private _carried = ["has", [player]] call comspec_atak_native_fnc_mpu5;
        private _h = objNull;
        private _wr = missionNamespace getVariable ["comspec_atak_native_mpu_wifi_range", 8];
        private _err = "";
        if (_mode isEqualTo "cable") then {
            if (!_carried) exitWith { _err = "Aucune MPU5 sur vous : le câble EUD se branche sur une radio que vous portez"; };
            if !(call _hasCable) exitWith { _err = "Il faut un câble EUD dans l'inventaire pour relier le téléphone à la MPU5"; };
        } else {
            if !(missionNamespace getVariable ["comspec_atak_native_mpu_wifi", true]) exitWith { _err = "Wi-Fi de la MPU5 désactivé sur ce serveur : reliez-la par câble"; };
            if (!_carried) then {
                _h = [_wr] call _findPosed;
                if (isNull _h) then { _err = format ["Aucune MPU5 à moins de %1 m (portée Wi-Fi)", round _wr]; };
            };
        };
        if (_err isNotEqualTo "") exitWith { ["WARNING", _err] call _say; false };
        _st set ["link", _mode];
        _st set ["holder", _h];
        ["SUCCESS", format ["Téléphone relié à la MPU5 (%1) : connexion au maillage, canal %2%3", ["câble EUD", "Wi-Fi"] select (_mode isEqualTo "wifi"), _st get "chan",
            ["", " · la radio est éteinte : allumez-la"] select !(_st get "on")]] call _say;
        call _kick;
        true
    };
    case "unlink": {
        private _st = call _state;
        private _was = _st get "link";
        _st set ["link", ""];
        _st set ["holder", objNull];
        if (_was isNotEqualTo "") then {
            ["INFO", ["Téléphone débranché de la MPU5", "Téléphone débranché de la MPU5 : sans MPU5, pas de réseau de données"] select (missionNamespace getVariable ["comspec_atak_native_mpu_require", true])] call _say;
        };
        call _kick;
        true
    };
    case "power": {
        private _st = call _state;
        if (!(["has", [player]] call comspec_atak_native_fnc_mpu5) && {!([_st getOrDefault ["holder", objNull]] call _holderHas)}) exitWith { ["WARNING", "Aucune MPU5 sur vous"] call _say; false };
        _st set ["on", !(_st get "on")];
        ["INFO", ["MPU5 éteinte : elle ne relaie plus le maillage", format ["MPU5 allumée · canal %1 · %2 %%", _st get "chan", round (_st get "batt")]] select (_st get "on")] call _say;
        call _kick;
        _st get "on"
    };
    case "chan": {
        private _st = call _state;
        private _c = (((_st get "chan") - 1 + (_arg param [0, 1]) + 16) mod 16) + 1;
        _st set ["chan", _c];
        ["INFO", format ["MPU5 : canal %1 (%2 MHz) : seules les radios de ce canal forment votre maillage", _c, [_c] call _freqOf]] call _say;
        call _kick;
        _c
    };
    case "txpower": {
        private _st = call _state;
        private _p = ((_st get "pwr") + 1) mod 3;
        _st set ["pwr", _p];
        ["INFO", format ["MPU5 : puissance %1, portée %2 m", ["faible (1 W)", "moyenne (3 W)", "max (6 W)"] select _p,
            round ((missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000]) * (_pwrK select _p))]] call _say;
        call _kick;
        _p
    };
    case "battery": {
        private _st = call _state;
        if !("COMSPEC_ATAK_RadioBattery" in (items player)) exitWith { ["WARNING", "Aucune batterie de radio dans l'inventaire"] call _say; false };
        player removeItem "COMSPEC_ATAK_RadioBattery";
        _st set ["batt", 100];
        _st set ["warned", 100];
        ["SUCCESS", "Batterie de la MPU5 changée (100 %)"] call _say;
        call _kick;
        true
    };
    case "info": {
        private _v = ["view"] call comspec_atak_native_fnc_mpuLink;
        private _s = _v get "status";
        ["INFO", format ["MPU5 %1 · canal %2 (%3 MHz) · %4 · batterie %5 %% · %6", ["éteinte", "allumée"] select (_v get "on"), _v get "chan", _v get "freq", _v get "pwrLabel", _v get "batt",
            [_s getOrDefault ["why", ""], format ["%1 saut(s) · %2 kbit/s · %3 ms", _s get "hops", _s get "kbps", _s get "lat"]] select (_s getOrDefault ["ok", false])], 8, 30] call comspec_atak_native_fnc_notify;
    };
    case "tick": {
        if (!hasInterface) exitWith {};
        private _t0 = diag_tickTime;
        if !(call _active) exitWith {
            if ((player getVariable ["COMSPEC_ATAK_MpuNode", []]) isNotEqualTo []) then { player setVariable ["COMSPEC_ATAK_MpuNode", [], true]; };
            uiNamespace setVariable ["COMSPEC_ATAK_MpuStatus", nil];
        };
        private _st = call _state;
        // Repli ACRE2 / TFAR en données (2.9.31) retiré : liaison autre que câble ou Wi-Fi restée en mémoire, ou branchement
        // ACRE2 / TFAR de l'ancien modèle, remis à « aucune radio reliée » (seule une MPU5 relie le téléphone ici).
        if (!((_st get "link") in ["", "cable", "wifi"]) || {(uiNamespace getVariable ["COMSPEC_ATAK_RadioPlug", []]) isNotEqualTo []}) then {
            _st set ["link", ""];
            _st set ["holder", objNull];
            uiNamespace setVariable ["COMSPEC_ATAK_RadioPlug", []];
            uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
            ["WARNING", "Liaison radio tactique retirée : reliez une MPU5"] call _say;
        };
        private _now = diag_tickTime;
        private _dt = ((_now - (_st getOrDefault ["at", _now])) max 0) min 30;
        _st set ["at", _now];
        private _carried = ["has", [player]] call comspec_atak_native_fnc_mpu5;
        private _link = _st get "link";
        private _wr = missionNamespace getVariable ["comspec_atak_native_mpu_wifi_range", 8];
        private _drop = "";
        // Radio posée (Wi-Fi) : on garde celle qu'on a reliée tant qu'elle existe, sinon la plus proche à portée Wi-Fi.
        private _h = _st getOrDefault ["holder", objNull];
        if (_carried) then { _h = objNull; } else {
            if (_link isEqualTo "wifi" && {!([_h] call _holderHas)}) then { _h = [_wr + 2] call _findPosed; };
        };
        _st set ["holder", _h];
        private _present = _carried || {!isNull _h};
        // Batterie : se vide radio allumée (portée ou posée), moins vite à basse puissance.
        if (_present && {_st get "on"} && {(_st get "batt") > 0}) then {
            private _hours = (missionNamespace getVariable ["comspec_atak_native_mpu_hours", 10]) max 0.1;
            private _b = ((_st get "batt") - _dt * 100 / (_hours * 3600) * ([0.55, 0.75, 1] select (_st get "pwr"))) max 0;
            _st set ["batt", _b];
            private _w = _st getOrDefault ["warned", 100];
            {
                if (_b <= _x && {_w > _x}) exitWith {
                    _st set ["warned", _x];
                    ["WARNING", [format ["Batterie de la MPU5 : %1 %%", _x], "Batterie de la MPU5 vide : radio éteinte, plus de maillage"] select (_x isEqualTo 0)] call _say;
                };
            } forEach [0, 5, 20];
        };
        // Liaison téléphone <-> MPU5 : câble (radio portée) ou Wi-Fi (quelques mètres).
        private _wd = -1;
        private _wq = 0;
        switch (_link) do {
            case "cable": {
                if (!_carried) exitWith { _drop = "Câble débranché : la MPU5 n'est plus sur vous"; };
                if !(call _hasCable) exitWith { _drop = "Câble EUD retiré : liaison à la MPU5 coupée"; };
            };
            case "wifi": {
                if !(missionNamespace getVariable ["comspec_atak_native_mpu_wifi", true]) exitWith { _drop = "Wi-Fi de la MPU5 désactivé par le serveur"; };
                if (!_present) exitWith { _drop = "MPU5 introuvable : liaison Wi-Fi coupée"; };
                _wd = [player distance _h, 0.5] select _carried;
                _wq = [0, 1 - (_wd / (_wr max 1)) ^ 2] select (_wd <= _wr);
            };
        };
        if (_drop isNotEqualTo "") then { _link = ""; _st set ["link", ""]; ["WARNING", _drop] call _say; };
        // Nœud publié : MPU5 allumée (reliée au téléphone ou non).
        private _on = _present && {_st get "on"} && {(_st get "batt") > 0};
        private _mR = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        private _node = [[], [format ["C%1", _st get "chan"], round (_mR * (_pwrK select (_st get "pwr"))), [player, _h] select (!isNull _h)]] select _on;
        if (_node isNotEqualTo (player getVariable ["COMSPEC_ATAK_MpuNode", []])) then { player setVariable ["COMSPEC_ATAK_MpuNode", _node, true]; };
        // État vu par le téléphone.
        private _kind = switch (true) do {
            case (_link in ["cable", "wifi"]): { "mpu" };
            case (([player] call comspec_atak_native_fnc_vehicleTerminal) isNotEqualTo ""): { "veh" };
            default { "" };
        };
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_MpuStatus", createHashMap];
        private _s = createHashMapFromArray [["active", true], ["kind", _kind], ["link", _link], ["ok", false], ["why", ""], ["kbps", 0], ["lat", 0], ["loss", 0],
            ["hops", -1], ["gw", ""], ["via", ""], ["mode", ""], ["nodes", []], ["wifiD", _wd], ["wifiQ", _wq]];
        private _need = false;
        switch (_kind) do {
            case "": { _s set ["why", "sans MPU5 : pas de réseau de données"]; };
            case "veh": { _s set ["ok", true]; _s set ["why", "radio du véhicule"]; };
            default {
                private _down = switch (true) do {
                    case !(_st get "on"): { "MPU5 éteinte" };
                    case ((_st get "batt") <= 0): { "batterie de la MPU5 vide" };
                    case (_link isEqualTo "wifi" && {_wq <= 0}): { format ["hors de portée Wi-Fi de la MPU5 (%1 m)", round _wd] };
                    default { "" };
                };
                if (_down isNotEqualTo "") exitWith { _s set ["why", _down]; };
                _need = true;
                private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
                if ((_m getOrDefault ["key", ""]) isNotEqualTo (_node param [0, "?"]) || {_now - (_m getOrDefault ["at", -100]) > 20}) exitWith { _s set ["why", "connexion au maillage en cours"]; };
                { _s set [_x, _m getOrDefault [_x, _s get _x]]; } forEach ["hops", "gw", "via", "mode", "nodes", "why"];
                if !(_m getOrDefault ["ok", false]) exitWith {};
                private _kb = _m get "kbps";
                private _lat = _m get "lat";
                private _loss = _m get "loss";
                if (_link isEqualTo "wifi") then {
                    _kb = _kb min round (30000 * (0.3 + 0.7 * _wq));
                    _lat = _lat + 3;
                    _loss = _loss + round ((1 - _wq) * 10);
                };
                _s set ["ok", true];
                _s set ["why", ""];
                _s set ["kbps", round _kb];
                _s set ["lat", round _lat];
                _s set ["loss", _loss min 100];
            };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MpuStatus", _s];
        // Voix radio pontée sur la MPU5 portée (fn_mpuVoice) : maillage calculé même sans téléphone relié.
        if (!_need && {_on} && {_carried} && {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isNotEqualTo []} && {missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]}) then { _need = true; };
        // Outils de développement (fn_devMesh) : graphe calculé même téléphone débranché.
        if (!_need && {_on} && {["on"] call comspec_atak_native_fnc_devMesh}) then { _need = true; };
        // Réseau perdu ou retrouvé par la radio : notification (pas en double avec celle de la coupure du lien).
        private _wasOk = _old getOrDefault ["ok", false];
        if ((_s get "ok") isNotEqualTo _wasOk || {(_s get "kind") isNotEqualTo (_old getOrDefault ["kind", ""])}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
            if (_drop isEqualTo "" && {count _old > 0}) then {
                if (_s get "ok") then {
                    if ((_s get "kind") isEqualTo "mpu") then {
                        ["SUCCESS", [format ["Réseau par la MPU5 : %1 saut(s) jusqu'à %2", _s get "hops", _s get "gw"], "Réseau par la MPU5 : maillage autonome de l'équipe"] select ((_s get "hops") <= 0)] call _say;
                    };
                } else {
                    if (_wasOk && {(_s get "why") isNotEqualTo "connexion au maillage en cours"}) then { ["WARNING", format ["Réseau perdu : %1", _s get "why"]] call _say; };
                };
            };
            call _rerender;
        };
        // Maillage : recalculé au plus toutes les 4 s, dans un script planifié (un seul à la fois, abandonné après 20 s).
        if (_need && {_now - (uiNamespace getVariable ["COMSPEC_ATAK_MpuMeshAt", -100]) >= 4} && {_now - (uiNamespace getVariable ["COMSPEC_ATAK_MpuMeshBusy", -100]) > 20}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshBusy", _now];
            uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", _now];
            private _max = (missionNamespace getVariable ["comspec_atak_native_mpu5_mbps", 20]) * 1000;
            [_node select 0, _node select 1, _node select 2, _max] spawn {
                ["mesh", _this] call comspec_atak_native_fnc_mpuLink;
                uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshBusy", -100];
                ["tick"] call comspec_atak_native_fnc_mpuLink;
            };
        };
        ["radio de données", _t0] call comspec_atak_native_fnc_perfAcc;
    };
    case "mesh": {
        // Chemin au plus large débit (goulot maximal) de ma radio vers une passerelle, liens évalués à la demande.
        _arg params ["_key", "_range", "_ant", "_maxK"];
        private _now = diag_tickTime;
        private _side = side group player;
        private _tNow = [time, serverTime] select isMultiplayer;
        private _jam = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= _tNow) && {!((_x param [5, false]) && {(_x param [3, ""]) isEqualTo str _side})} };
        private _jamAt = {
            params ["_p"];
            private _m = 1;
            {
                _x params [["_jp", [0, 0, 0]], ["_jr", 500]];
                if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
                private _d = _p distance2D _jp;
                private _js = (([_x param [6, 1], 1] select !((_x param [6, 1]) isEqualType 0)) max 0) min 1;
                if (_d < _jr) then { _m = _m * (1 - _js * (1 - (_d / _jr) ^ 2)); };
            } forEach _jam;
            _m
        };
        // Nœuds : [objet antenne, position ASL, nom, type ("me" | "node" | "posed" | "gw"), portée m, porteur, débit max kbit/s]
        private _nodes = [[_ant, [_ant] call _antOf, "Ma radio", "me", _range, player, _maxK]];
        {
            (["node", _x] call comspec_atak_native_fnc_meshRead) params [["_k", ""], ["_r", 0], ["_o", objNull]];
            if (_k isNotEqualTo _key) then { continue };
            private _cs = [_x, true] call comspec_atak_native_fnc_unitCallsign;
            private _man = _o isKindOf "CAManBase";
            _nodes pushBack [_o, [_o] call _antOf, [format ["MPU5 posée (%1)", _cs], _cs] select _man, ["posed", "node"] select _man, _r, _x, _maxK];
        } forEach ((allPlayers select { alive _x && {_x isNotEqualTo player} && {side group _x isEqualTo _side} }) + (["units", [str _side]] call comspec_atak_native_fnc_devMeshSim));
        {
            _x params ["_o", "_ap", "_n", "_k", "_r", "_mb", "_ok"];
            if (!_ok || {_k isEqualTo "mpu5"}) then { continue };
            _nodes pushBack [_o, _ap, _n, "gw", _r, objNull, (_mb * 1000) min _maxK];
        } forEach (["list"] call comspec_atak_native_fnc_relayNet);
        private _n = count _nodes;
        private _gws = [];
        { if ((_x select 3) isEqualTo "gw") then { _gws pushBack _forEachIndex; }; } forEach _nodes;
        private _mode = "relay";
        if (_gws isEqualTo []) then {
            private _lead = leader group player;
            private _li = _nodes findIf { (_x select 5) isEqualTo _lead };
            if (_lead isNotEqualTo player && {_li > 0}) then { _gws = [_li]; _mode = "lead"; } else { _gws = [0]; _mode = "self"; };
        };
        // Lignes de vue (bilan radio, relief) en cache 30 s par paire de positions arrondies (15 m).
        private _devOn = ["on"] call comspec_atak_native_fnc_devMesh;
        private _losN = [0, 0];
        private _devLinks = [];
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_MpuLos", createHashMap];
        if ((count _cache) > 600) then { _cache = createHashMap; };
        uiNamespace setVariable ["COMSPEC_ATAK_MpuLos", _cache];
        private _los = {
            params ["_pa", "_pb", "_r"];
            private _ka = [round ((_pa select 0) / 15), round ((_pa select 1) / 15), round ((_pa select 2) / 5)];
            private _kb = [round ((_pb select 0) / 15), round ((_pb select 1) / 15), round ((_pb select 2) / 5)];
            private _k = str ([[_kb, _ka, round _r], [_ka, _kb, round _r]] select ((_ka select 0) < (_kb select 0) || {(_ka select 0) isEqualTo (_kb select 0) && {(_ka select 1) <= (_kb select 1)}}));
            private _hit = _cache getOrDefault [_k, []];
            if ((count _hit) isEqualTo 2 && {_now - (_hit select 0) < 30}) exitWith { _losN set [1, (_losN select 1) + 1]; _hit select 1 };
            _losN set [0, (_losN select 0) + 1];
            private _m = ([_pa, _pb, _r, "coarse"] call comspec_atak_native_fnc_radioLink) select 0;
            _cache set [_k, [_now, _m]];
            _m
        };
        private _jf = _nodes apply { [_x select 1] call _jamAt };
        private _bw = _nodes apply { -1 };
        _bw set [0, _maxK];
        private _hop = _nodes apply { -1 };
        _hop set [0, 0];
        private _prev = _nodes apply { -1 };
        private _mg = _nodes apply { 40 };
        private _done = _nodes apply { false };
        private _goal = -1;
        while { true } do {
            private _i = -1;
            private _bb = 0;
            { if (!(_done select _forEachIndex) && {_x > _bb}) then { _bb = _x; _i = _forEachIndex; }; } forEach _bw;
            if (_i < 0) exitWith {};
            _done set [_i, true];
            if (_goal < 0 && {_i in _gws}) then { _goal = _i; };
            // Grand maillage : on s'arrête à la passerelle (le reste n'est que de l'affichage).
            if (_goal >= 0 && {_n > 30}) exitWith {};
            private _a = _nodes select _i;
            {
                if (_done select _forEachIndex) then { continue };
                private _b = _x;
                // Deux relais : déjà reliés par le cœur de réseau (fn_relayNet).
                if ((_a select 3) isEqualTo "gw" && {(_b select 3) isEqualTo "gw"}) then { continue };
                private _r = (_a select 4) min (_b select 4);
                if (((_a select 1) distance (_b select 1)) > _r) then { continue };
                private _m = [_a select 1, _b select 1, _r] call _los;
                if (_m < 0) then { continue };
                private _j = (_jf select _i) min (_jf select _forEachIndex);
                if (_j < 0.1) then { continue };
                // Modulation adaptative (comme fn_relayNet "link") ; chaque bond relayé coûte 20 % (radio à une antenne).
                private _f = [0.06, 0.12, 0.25, 0.4, 0.55, 0.7, 0.85, 1] select (((({ _m >= _x } count [0, 2, 4, 6, 9, 12, 16, 20]) - 1) max 0) min 7);
                if (_devOn) then { _devLinks pushBack [_i, _forEachIndex, _m, ((_a select 6) min (_b select 6)) * _f * _j]; };
                private _c = ((_bw select _i) min (((_a select 6) min (_b select 6)) * _f * _j)) * ([1, 0.8] select (_i > 0));
                if (_c > (_bw select _forEachIndex)) then {
                    _bw set [_forEachIndex, _c];
                    _hop set [_forEachIndex, (_hop select _i) + 1];
                    _prev set [_forEachIndex, _i];
                    _mg set [_forEachIndex, (_mg select _i) min _m];
                };
            } forEach _nodes;
        };
        private _res = createHashMapFromArray [["key", _key], ["at", diag_tickTime], ["mode", _mode], ["ok", _goal >= 0]];
        // Voix par le maillage (fn_mpuVoice) : porteurs joints (netId -> sauts) et cœur de réseau commun.
        private _reach = createHashMap;
        { if (_forEachIndex > 0 && {!isNull (_x select 5)} && {(_hop select _forEachIndex) >= 0}) then { _reach set [netId (_x select 5), _hop select _forEachIndex]; }; } forEach _nodes;
        _res set ["reach", _reach];
        _res set ["vkey", [switch (_mode) do {
            case "relay": { "R" + str _side };
            case "lead": { "L" + netId (leader group player) };
            default { ["", "L" + netId player] select ((leader group player) isEqualTo player) };
        }, ""] select (_goal < 0)];
        if (_goal >= 0) then {
            private _h = _hop select _goal;
            private _via = _goal;
            while { (_prev select _via) > 0 } do { _via = _prev select _via; };
            private _mgMin = (_mg select _goal) max 0;
            _res set ["kbps", round (_bw select _goal)];
            _res set ["hops", _h];
            _res set ["gw", [(_nodes select _goal) select 2, "maillage autonome"] select (_goal isEqualTo 0)];
            _res set ["via", [(_nodes select _via) select 2, ""] select (_goal isEqualTo 0)];
            _res set ["lat", round ((missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15]) * (_h max 1)
                + 5 + 60 * (1 - ((_mgMin / 20) min 1)))];
            _res set ["loss", [round ((40 * exp (-_mgMin / 5)) min 60), 0] select (_h isEqualTo 0)];
            _res set ["why", ""];
        } else {
            _res set ["kbps", 0];
            _res set ["hops", -1];
            _res set ["gw", ""];
            _res set ["via", ""];
            _res set ["lat", 0];
            _res set ["loss", 100];
            _res set ["why", [format ["hors du maillage de l'équipe (%1 injoignable)", (_nodes select (_gws select 0)) select 2],
                "aucune passerelle du maillage à portée (relais, pylônes, MPU5 du canal)"] select (_mode isEqualTo "relay")];
        };
        private _list = [];
        for "_i" from 1 to (_n - 1) do {
            (_nodes select _i) params ["", "_p", "_nm", "_k"];
            _list pushBack [[_hop select _i, 99] select ((_hop select _i) < 0), round (player distance _p), _nm, _k, _hop select _i, round (_bw select _i)];
        };
        _list sort true;
        // Affichage (app Réseau) : [nom, type, sauts (-1 hors maillage), distance m, débit kbit/s]
        _res set ["nodes", (_list select [0, 12]) apply { [_x select 2, _x select 3, _x select 4, _x select 1, _x select 5] }];
        _res set ["count", _n];
        // Compteurs du tableau de performance (fn_devMesh) ; graphe complet pour les outils, en mode développeur seulement.
        missionNamespace setVariable ["COMSPEC_ATAK_MeshStats", createHashMapFromArray [["at", diag_tickTime], ["ms", (diag_tickTime - _now) * 1000], ["nodes", _n], ["links", count _devLinks], ["tests", _losN select 0], ["hits", _losN select 1]]];
        if (_devOn) then {
            private _devNodes = [];
            {
                _x params ["_o", "_p", "_nm", "_k", "_r"];
                _devNodes pushBack [_o, _p, _nm, _k, _key, _r, _hop select _forEachIndex, _bw select _forEachIndex];
            } forEach _nodes;
            _res set ["dev", [_devNodes, _devLinks]];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMesh", _res];
        _res
    };
    case "aceInit": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
        private _root = ["ACE_SelfActions", "COMSPEC_ATAK_Phone"];
        private _menu = ["COMSPEC_ATAK_Mpu", "Radio de données", "\z\comspec_atak_native\addons\main\data\app_waverelay.paa", {},
            { ["active"] call comspec_atak_native_fnc_mpuLink }] call comspec_atak_native_fnc_aceAction;
        ["CAManBase", 1, _root, _menu, true] call ace_interact_menu_fnc_addActionToClass;
        private _path = _root + ["COMSPEC_ATAK_Mpu"];
        {
            _x params ["_id", "_name", "_code", "_cond"];
            ["CAManBase", 1, _path, [_id, _name, "", _code, _cond] call comspec_atak_native_fnc_aceAction, true] call ace_interact_menu_fnc_addActionToClass;
        } forEach [
            ["COMSPEC_ATAK_MpuCable", "Relier le téléphone à la MPU5 (câble)", { ["link", ["cable"]] call comspec_atak_native_fnc_mpuLink; },
                { ((uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["link", ""]) isNotEqualTo "cable" && {["has", [player]] call comspec_atak_native_fnc_mpu5} }],
            ["COMSPEC_ATAK_MpuWifi", "Relier le téléphone à la MPU5 (Wi-Fi)", { ["link", ["wifi"]] call comspec_atak_native_fnc_mpuLink; },
                { (missionNamespace getVariable ["comspec_atak_native_mpu_wifi", true]) && {((uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["link", ""]) isNotEqualTo "wifi"} }],
            ["COMSPEC_ATAK_MpuUnlink", "Débrancher le téléphone de la MPU5", { ["unlink"] call comspec_atak_native_fnc_mpuLink; },
                { ((uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["link", ""]) isNotEqualTo "" }],
            ["COMSPEC_ATAK_MpuOn", "Allumer la MPU5", { ["power"] call comspec_atak_native_fnc_mpuLink; },
                { !((uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["on", true]) && {["has", [player]] call comspec_atak_native_fnc_mpu5} }],
            ["COMSPEC_ATAK_MpuOff", "Éteindre la MPU5", { ["power"] call comspec_atak_native_fnc_mpuLink; },
                { ((uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["on", true]) && {["has", [player]] call comspec_atak_native_fnc_mpu5} }],
            ["COMSPEC_ATAK_MpuBatt", "Changer la batterie de la MPU5", {
                if (isNil "ace_common_fnc_progressBar") exitWith { ["battery"] call comspec_atak_native_fnc_mpuLink; };
                [6, [], { ["battery"] call comspec_atak_native_fnc_mpuLink; }, {}, ("Changement de la batterie de la MPU5…" call comspec_atak_native_fnc_tr)] call ace_common_fnc_progressBar;
            }, { "COMSPEC_ATAK_RadioBattery" in (items player) && {((uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["batt", 100]) < 95} }],
            ["COMSPEC_ATAK_MpuInfo", "État de la radio", { ["info"] call comspec_atak_native_fnc_mpuLink; }, { true }]
        ];
        // Pont radio (fn_mpuVoice) : la radio active ACRE2 / TFAR reliée à la MPU5 par câble audio, nom de la radio dans l'action.
        ["CAManBase", 1, _path, ["COMSPEC_ATAK_MpuVoiceOn", "Relier ma radio à la MPU5 (voix)", "", { ["bridge"] call comspec_atak_native_fnc_mpuVoice; },
            { (missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]) && {(missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isEqualTo []}
                && {!isNil "acre_api_fnc_getCurrentRadioList" || {!isNil "TFAR_fnc_activeSwRadio"}} && {["has", [player]] call comspec_atak_native_fnc_mpu5} },
            {}, [], [0, 0, 0], 2, [false, false, false, false, false], {
                params ["", "", "", "_ad"];
                private _p = ["preferred"] call comspec_atak_native_fnc_mpuVoice;
                if (_p isNotEqualTo []) then { _ad set [1, format [("Relier ma radio %1 à la MPU5 (voix)" call comspec_atak_native_fnc_tr), _p select 2]]; };
            }] call comspec_atak_native_fnc_aceAction, true] call ace_interact_menu_fnc_addActionToClass;
        ["CAManBase", 1, _path, ["COMSPEC_ATAK_MpuVoiceOff", "Débrancher ma radio de la MPU5 (voix)", "", { ["unbridge"] call comspec_atak_native_fnc_mpuVoice; },
            { (missionNamespace getVariable ["COMSPEC_ATAK_VoiceBridge", []]) isNotEqualTo [] }] call comspec_atak_native_fnc_aceAction, true] call ace_interact_menu_fnc_addActionToClass;
        true
    };
};
