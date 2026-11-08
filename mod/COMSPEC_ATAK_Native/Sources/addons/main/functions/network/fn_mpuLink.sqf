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
    Calculs : "tick" toutes les 2 s (aucun calcul par image). Graphe unique de fn_meshGraph (sur le serveur par défaut), relu
    dans un script planifié au plus toutes les 4 s, ou dès qu'il change ; fn_linkQuality ne lit que le dernier état ("status").
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
      "power" ; "chan" [+1 | -1] ; "txpower" ; "width" ; "mast" ; "battery" ; "info"
      "tick", "mesh" (planifié), "aceInit"
*/
params [["_act", "status"], ["_arg", []]];
private _active = { missionNamespace getVariable ["comspec_atak_native_mpu_real", false] };
private _state = {
    private _s = uiNamespace getVariable "COMSPEC_ATAK_MpuSt";
    if (isNil "_s") then {
        _s = createHashMapFromArray [["on", true], ["chan", 1], ["pwr", 2], ["link", ""], ["batt", 100], ["holder", objNull], ["at", diag_tickTime], ["warned", 100], ["width", 1]];
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
// Largeur de canal (lot 9) : étroit, portée x 1,41 (3 dB) et débit / 2 ; large, portée x 0,71 et débit x 1,5. Choix de jeu.
private _widK = [1.41, 1, 0.71];
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
        if (isNil "_s") then { _s = createHashMapFromArray [["active", true], ["kind", ""], ["link", ""], ["ok", false], ["why", "sans MPU5 : pas de réseau de données"], ["kbps", 0], ["lat", 0], ["loss", 0], ["hops", -1], ["gw", ""], ["via", ""], ["mode", ""], ["nodes", []], ["wifiD", -1], ["wifiQ", 0], ["level", 3]]; };
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
            ["pwrLabel", ["Faible (1 W)", "Moyenne (3 W)", "Max (6 W)"] select _pwr], ["width", ["Étroit", "Normal", "Large"] select (_st getOrDefault ["width", 1])],
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
    case "width": {
        private _st = call _state;
        private _w = ((_st getOrDefault ["width", 1]) + 1) mod 3;
        _st set ["width", _w];
        ["INFO", format ["MPU5 : canal %1 (%2)", ["étroit", "normal", "large"] select _w, ["plus de portée, moins de débit", "réglage de base", "plus de débit, moins de portée"] select _w]] call _say;
        call _kick;
        _w
    };
    case "mast": {
        // Mât d'antenne sur la MPU5 posée (lot 9, réglage mesh_mast) : classe de décor d'Arma, pas de modèle propre. Se voit de loin.
        private _st = call _state;
        private _h = _st getOrDefault ["holder", objNull];
        private _old = _st getOrDefault ["mast", objNull];
        if (!isNull _old) exitWith {
            deleteVehicle _old;
            _st set ["mast", objNull];
            if (!isNull _h) then { _h setVariable ["COMSPEC_ATAK_MeshMast", 0, true]; };
            ["INFO", "Mât replié"] call _say;
            call _kick;
            false
        };
        if (isNull _h || {!((missionNamespace getVariable ["comspec_atak_native_mesh_mast", true]) isEqualTo true)}) exitWith { ["WARNING", "Mât : il faut une MPU5 posée, reliée en Wi-Fi"] call _say; false };
        ["INFO", "Déploiement du mât : 30 s"] call _say;
        [{
            params ["_holder", "_stt"];
            if (isNull _holder) exitWith {};
            private _m = createVehicle ["Land_TTowerSmall_1_F", getPosATL _holder, [], 2, "NONE"];
            _stt set ["mast", _m];
            _holder setVariable ["COMSPEC_ATAK_MeshMast", 8, true];
            uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
            ["Mât déployé", 3] call comspec_atak_native_fnc_toast;
        }, [_h, _st], 30] call comspec_atak_native_fnc_waitAndExec;
        true
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
            if ((player getVariable ["COMSPEC_ATAK_MpuNode", []]) isNotEqualTo []) then { player setVariable ["COMSPEC_ATAK_MpuNode", []]; };
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
            // Trafic relayé (lot 9, réglage mesh_traffic_power) : un nœud central se vide plus vite, au plus deux fois.
            private _relay = if ((missionNamespace getVariable ["comspec_atak_native_mesh_traffic_power", true]) isEqualTo true) then { ((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["relayed", 0]) min 10 } else { 0 };
            private _b = ((_st get "batt") - _dt * 100 / (_hours * 3600) * ([0.55, 0.75, 1] select (_st get "pwr")) * (1 + 0.1 * _relay)) max 0;
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
        // Objet obligatoire manquant (fn_netRequire) : plus de nœud publié, plus de réseau, sauf le Bluetooth et les données déjà reçues.
        private _den = ["denied"] call comspec_atak_native_fnc_netRequire;
        private _on = _present && {_st get "on"} && {(_st get "batt") > 0} && {_den isEqualTo []};
        private _mR = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        private _wid = _st getOrDefault ["width", 1];
        // Discrétion (lot 11, fn_meshEW) : 0 normal, 1 puissance réduite, 2 écoute seule. Activité arrondie : puissance et trafic relayé.
        private _mode = _st getOrDefault ["mode", 0];
        if (_mode isEqualTo 1) then { _st set ["pwr", 0]; };
        private _activity = if (_mode isEqualTo 2) then { 0 } else { ((1 + (_st get "pwr") * 2 + floor (((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["relayed", 0]) / 2)) min 9) };
        private _node = [[], [format ["C%1", _st get "chan"], (round (_mR * (_pwrK select (_st get "pwr")) * (_widK select _wid))) min 20000, [player, _h] select (!isNull _h), _wid, ["nodeKey"] call comspec_atak_native_fnc_meshKeys, _activity]] select _on;
        ["tick"] call comspec_atak_native_fnc_meshEW;
        ["tick"] call comspec_atak_native_fnc_meshKeys;
        if (_node isNotEqualTo (player getVariable ["COMSPEC_ATAK_MpuNode", []])) then { player setVariable ["COMSPEC_ATAK_MpuNode", _node]; };
        // État vu par le téléphone.
        private _kind = switch (true) do {
            case (_link in ["cable", "wifi"]): { "mpu" };
            case (([player] call comspec_atak_native_fnc_vehicleTerminal) isNotEqualTo ""): { "veh" };
            default { "" };
        };
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_MpuStatus", createHashMap];
        private _s = createHashMapFromArray [["active", true], ["kind", _kind], ["link", _link], ["ok", false], ["why", ""], ["kbps", 0], ["lat", 0], ["loss", 0],
            ["hops", -1], ["gw", ""], ["via", ""], ["mode", ""], ["nodes", []], ["wifiD", _wd], ["wifiQ", _wq], ["level", 3]];
        private _need = false;
        if (_den isNotEqualTo []) then { _kind = "denied"; _s set ["kind", ""]; };
        switch (_kind) do {
            case "denied": { _s set ["why", format ["objet requis manquant : %1", ["labels", [_den]] call comspec_atak_native_fnc_netRequire]]; };
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
                { _s set [_x, _m getOrDefault [_x, _s get _x]]; } forEach ["hops", "gw", "via", "mode", "nodes", "why", "level"];
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
        // App Wave Relay ou carte ouverte (fn_waveRelayAction "scan") : graphe relu même téléphone débranché.
        if (!_need && {_on} && {_now - (uiNamespace getVariable ["COMSPEC_ATAK_MeshScanAt", -100]) < 10}) then { _need = true; };
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
        // Maillage : relu au plus toutes les 4 s (8 s au-delà de 40 nœuds), ou dès qu'il change, dans un script planifié (un seul à la fois, abandonné après 20 s).
        if (_need && {_now - (uiNamespace getVariable ["COMSPEC_ATAK_MpuMeshAt", -100]) >= (["every"] call comspec_atak_native_fnc_meshGraph)} && {_now - (uiNamespace getVariable ["COMSPEC_ATAK_MpuMeshBusy", -100]) > 20}) then {
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
        // Graphe unique (fn_meshGraph) : calculé ici seulement sans serveur de référence, puis lu comme partout ailleurs.
        _arg params ["_key", "", "_ant", "_maxK"];
        if !(["serverBuilt"] call comspec_atak_native_fnc_meshGraph) then { ["pass", [[str side group player]]] call comspec_atak_native_fnc_meshGraph; };
        private _res = ["view", [_key, _ant, _maxK]] call comspec_atak_native_fnc_meshGraph;
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
