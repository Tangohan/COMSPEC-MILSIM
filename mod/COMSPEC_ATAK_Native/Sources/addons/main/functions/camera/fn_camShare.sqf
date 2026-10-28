/*
    Partage des caméras (app Vidéo live, bouton PARTAGER) : caméra IP fixe (fn_camAction) ou caméra de mon téléphone
    (Live cam, fn_livecamStart), vers un ATAK précis ou vers TOUS, par l'une des trois méthodes :
      NFC      téléphones dos à dos (comspec_atak_native_camshare_nfc_range, 2 m). Aucun réseau : marche en coupure
               totale, en mode avion ou si l'un des deux ATAK n'a pas de réseau. TOUS = chaque ATAK à cette distance.
      BT       Bluetooth allumé sur les deux téléphones, portée comspec_atak_native_bt_range (15 m par défaut, réglage
               CBA, la même que l'app Bluetooth). Pas de réseau nécessaire. TOUS = chaque ATAK à portée.
      RELAY    par le réseau (maillage direct ou relais radio et vidéo) : les deux téléphones doivent avoir du réseau,
               pas de limite de distance. TOUS = tout le camp, y compris ceux qui arrivent ensuite.
    Partager donne l'accès (clé et adresse du flux). L'image suit ensuite la liaison de la caméra : une caméra fixe
    se regarde par son propre Wi-Fi à portée (comspec_atak_native_camera_range, sans réseau), sinon par le réseau
    et les relais (fn_feedLink, fn_videoLink) ; la caméra d'un téléphone passe par le réseau.

    Accès : variable publique COMSPEC_ATAK_ShareAcc sur la source (objet caméra, ou unité pour sa caméra de
    téléphone) = [[uid ou "ALL", méthode, nom de qui a partagé, heure]...]. Le poseur d'une caméra fixe (et le porteur
    de son téléphone) y a toujours accès. Réglage comspec_atak_native_camera_share (CBA) : 0 partage obligatoire
    (défaut), 1 tout le camp voit toutes les caméras sans partage (comportement d'avant).

    Modes :
      ["required"]                      bool : le partage est-il obligatoire
      ["can", source, viewer]           bool : viewer (défaut player) peut-il voir la source
      ["owner", source]                 bool : je suis le poseur de la caméra (ou c'est mon téléphone)
      ["canShare", source]              bool : je peux la partager (accès à une caméra fixe, ou mon téléphone)
      ["access", source]                liste d'accès
      ["methods"]                       [[code, titre, conditions]...]
      ["mine", méthode]                 "" si mon téléphone peut partager par cette méthode, sinon la raison
      ["candidates", [source, méthode]] [[unité, distance, joignable, raison]...] du camp, triés par distance
      ["send", [source, méthode, uid | "ALL"]]  partage ; renvoie le nombre d'ATAK servis
      ["revoke", source]                retire tous les accès (poseur ou porteur seulement)
      ["srvAcc", [source, entrées]]     serveur : fusionne les entrées (vide = tout retirer) et publie la liste
      ["recv", [netId source, nom, méthode, qui]]  événement comspec_atak_native_camShare reçu : notification
*/
params [["_mode", ""], ["_a", objNull], ["_b", objNull]];
private _fn = comspec_atak_native_fnc_camShare;
private _uidOf = { params ["_u"]; private _id = getPlayerUID _u; if (_id isEqualTo "") then { netId _u } else { _id } };
private _isCam = { params ["_o"]; !isNull _o && {_o isKindOf "COMSPEC_ATAK_CameraObj"} };
private _nameOf = {
    params ["_o"];
    if ([_o] call _isCam) then { _o getVariable ["COMSPEC_ATAK_CamName", "CAM"] } else { format ["caméra de %1", [_o, true] call comspec_atak_native_fnc_unitCallsign] }
};
private _num = { params ["_v", "_d"]; if (_v isEqualType 0) then { _v } else { _d } };

switch (_mode) do {
    case "required": { ([missionNamespace getVariable ["comspec_atak_native_camera_share", 0], 0] call _num) isEqualTo 0 };
    case "access": {
        if (isNull _a) exitWith { [] };
        private _acc = _a getVariable ["COMSPEC_ATAK_ShareAcc", []];
        if (_acc isEqualType []) then { _acc } else { [] }
    };
    case "owner": {
        if (isNull _a) exitWith { false };
        if !([_a] call _isCam) exitWith { _a isEqualTo player };
        private _ou = _a getVariable ["COMSPEC_ATAK_CamOwnerUid", ""];
        // Caméra posée avant le partage : seul le nom du poseur est connu.
        if (_ou isEqualTo "") then { (_a getVariable ["COMSPEC_ATAK_CamOwner", ""]) isEqualTo name player } else { _ou isEqualTo ([player] call _uidOf) }
    };
    case "can": {
        private _v = [_b, player] select (isNull _b);
        if (isNull _a) exitWith { false };
        if !(["required"] call _fn) exitWith { true };
        if (_a isEqualTo _v) exitWith { true };
        if ([_a] call _isCam && {
            private _ou = _a getVariable ["COMSPEC_ATAK_CamOwnerUid", ""];
            [(_a getVariable ["COMSPEC_ATAK_CamOwner", ""]) isEqualTo name _v, _ou isEqualTo ([_v] call _uidOf)] select (_ou isNotEqualTo "")
        }) exitWith { true };
        private _id = [_v] call _uidOf;
        ((["access", _a] call _fn) findIf { (_x param [0, ""]) in ["ALL", _id] }) >= 0
    };
    case "canShare": {
        if (isNull _a) exitWith { false };
        // Mon téléphone : moi seul. Caméra fixe : quiconque y a accès peut transmettre l'accès (la clé se recopie).
        if !([_a] call _isCam) exitWith { _a isEqualTo player };
        !(["dead", _a] call comspec_atak_native_fnc_camAction) && {["can", _a] call _fn}
    };
    case "methods": {
        private _nfc = [missionNamespace getVariable ["comspec_atak_native_camshare_nfc_range", 2], 2] call _num;
        private _bt = [missionNamespace getVariable ["comspec_atak_native_bt_range", 15], 15] call _num;
        [
            ["NFC", "NFC", format ["Téléphones dos à dos, à moins de %1 m. Aucun réseau nécessaire : marche en coupure totale, en mode avion ou si l'un des deux ATAK n'a pas de réseau. TOUS = chaque ATAK à moins de %1 m.", _nfc]],
            ["BT", "BLUETOOTH", format ["Portée réaliste de %1 m entre les deux téléphones, Bluetooth allumé des deux côtés (app Bluetooth). Pas de réseau nécessaire. TOUS = chaque ATAK à moins de %1 m.", round _bt]],
            ["RELAY", "RELAIS", "Par le réseau : en direct ou à travers les relais radio et vidéo. Les deux téléphones doivent avoir du réseau, sans limite de distance. TOUS = tout le camp, y compris ceux qui arrivent ensuite."]
        ]
    };
    case "mine": {
        private _why = [] call comspec_atak_native_fnc_canUse;
        if (_why isNotEqualTo "") exitWith { "Votre téléphone est inutilisable." };
        switch (_a) do {
            case "BT": { ["Bluetooth éteint : allumez-le dans l'app Bluetooth.", ""] select (["on"] call comspec_atak_native_fnc_btAction) };
            case "RELAY": { ["Pas de réseau sur votre téléphone : utilisez le NFC ou le Bluetooth.", ""] select ((([] call comspec_atak_native_fnc_linkQuality) get "bars") > 0) };
            default { "" };
        }
    };
    case "candidates": {
        _a params [["_src", objNull], ["_m", "RELAY"]];
        private _nfc = [missionNamespace getVariable ["comspec_atak_native_camshare_nfc_range", 2], 2] call _num;
        private _bt = [missionNamespace getVariable ["comspec_atak_native_bt_range", 15], 15] call _num;
        // Rayon d'affichage : un peu plus que la portée, pour voir qui approcher.
        private _show = switch (_m) do { case "NFC": { 25 }; case "BT": { (_bt * 3) max 50 }; default { 1e9 } };
        private _out = [];
        {
            private _u = _x;
            private _d = player distance _u;
            if (_u isNotEqualTo player && {alive _u} && {side group _u isEqualTo side group player} && {_d <= _show} && {[_u] call comspec_atak_native_fnc_hasDevice}) then {
                (_u getVariable ["COMSPEC_ATAK_Pub", []]) params [["_bat", 100], ["_bars", 4], ["_st", "OK"]];
                private _why = switch (true) do {
                    case (_st in ["OFF", "BROKEN"] || {_bat isEqualType 0 && {_bat <= 0}}): { "téléphone éteint" };
                    case (_m isEqualTo "NFC" && {_d > _nfc}): { format ["approchez à %1 m", _nfc] };
                    case (_m isEqualTo "BT" && {_d > _bt}): { format ["hors de portée (%1 m)", round _bt] };
                    case (_m isEqualTo "BT" && {!(_u getVariable ["COMSPEC_ATAK_BtOn", false])}): { "Bluetooth éteint" };
                    case (_m isEqualTo "RELAY" && {_bars isEqualType 0 && {_bars <= 0}}): { "pas de réseau" };
                    default { "" };
                };
                _out pushBack [_u, _d, _why isEqualTo "", _why];
            };
        } forEach allPlayers;
        [_out, [], { _x select 1 }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "send": {
        _a params [["_src", objNull], ["_m", "RELAY"], ["_to", ""]];
        if !(["canShare", _src] call _fn) exitWith { ["WARNING", "Vous n'avez pas accès à cette caméra : impossible de la partager.", 4, 30] call comspec_atak_native_fnc_notify; 0 };
        private _mine = ["mine", _m] call _fn;
        if (_mine isNotEqualTo "") exitWith { ["WARNING", _mine, 4, 30] call comspec_atak_native_fnc_notify; 0 };
        private _label = (((["methods"] call _fn) select { (_x select 0) isEqualTo _m }) param [0, ["", _m]]) select 1;
        private _cands = (["candidates", [_src, _m]] call _fn) select { _x select 2 };
        private _targets = if (_to isEqualTo "ALL") then { _cands apply { _x select 0 } } else { (_cands select { ([_x select 0] call _uidOf) isEqualTo _to }) apply { _x select 0 } };
        // TOUS par les relais : accès « ALL », valable pour tout le camp, même sans personne joignable maintenant.
        private _all = _to isEqualTo "ALL" && {_m isEqualTo "RELAY"};
        if (!_all && {(count _targets) isEqualTo 0}) exitWith {
            private _r = if (_to isEqualTo "ALL") then { format ["Aucun ATAK joignable par %1 pour le moment.", _label] } else {
                private _c = (["candidates", [_src, _m]] call _fn) select { ([_x select 0] call _uidOf) isEqualTo _to };
                format ["Partage %1 impossible : %2.", _label, ((_c param [0, []]) param [3, "ATAK introuvable"])]
            };
            ["WARNING", _r, 4, 30] call comspec_atak_native_fnc_notify;
            0
        };
        private _me = [player, true] call comspec_atak_native_fnc_unitCallsign;
        if (_me isEqualTo "") then { _me = name player; };
        private _at = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        private _acc = +(["access", _src] call _fn);
        private _ids = if (_all) then { ["ALL"] } else { _targets apply { [_x] call _uidOf } };
        _acc = _acc select { !((_x param [0, ""]) in _ids) };
        { _acc pushBack [_x, _m, _me, _at]; } forEach _ids;
        // Copie locale immédiate ; le serveur fusionne et publie (deux partages simultanés ne s'écrasent plus).
        _src setVariable ["COMSPEC_ATAK_ShareAcc", _acc];
        ["srvAcc", [_src, _ids apply { [_x, _m, _me, _at] }]] remoteExecCall ["comspec_atak_native_fnc_camShare", 2];
        if (_all) then { _targets = _cands apply { _x select 0 }; };
        if ((count _targets) > 0) then { ["comspec_atak_native_camShare", [netId _src, [_src] call _nameOf, _m, _me], _targets] call CBA_fnc_targetEvent; };
        playSound "ClickSoft";
        ["SUCCESS", format ["%1 partagée par %2 avec %3", [_src] call _nameOf, _label,
            [[name (_targets param [0, objNull]), format ["%1 ATAK", count _targets]] select ((count _targets) > 1), "TOUS (tout le camp)"] select _all], 4, 30] call comspec_atak_native_fnc_notify;
        count _targets
    };
    case "revoke": {
        if !(["owner", _a] call _fn) exitWith { false };
        _a setVariable ["COMSPEC_ATAK_ShareAcc", []];
        ["srvAcc", [_a, []]] remoteExecCall ["comspec_atak_native_fnc_camShare", 2];
        ["INFO", format ["%1 : tous les accès partagés retirés", [_a] call _nameOf], 3, 30] call comspec_atak_native_fnc_notify;
        true
    };
    // Serveur : seul à modifier et publier COMSPEC_ATAK_ShareAcc. Entrées vides : tous les accès retirés.
    case "srvAcc": {
        if (!isServer) exitWith { false };
        _a params [["_src", objNull, [objNull]], ["_add", [], [[]]]];
        if (isNull _src) exitWith { false };
        private _acc = if (_add isEqualTo []) then { [] } else {
            private _new = _add select { _x isEqualType [] && {(count _x) isEqualTo 4} && {(_x select 0) isEqualType ""} };
            private _ids = _new apply { _x select 0 };
            ((["access", _src] call _fn) select { !((_x param [0, ""]) in _ids) }) + _new
        };
        _src setVariable ["COMSPEC_ATAK_ShareAcc", _acc, true];
        true
    };
    case "recv": {
        _a params [["_id", ""], ["_name", "CAM"], ["_m", ""], ["_by", "?"]];
        private _label = (((["methods"] call _fn) select { (_x select 0) isEqualTo _m }) param [0, ["", _m]]) select 1;
        ["MESSAGE", format ["%1 vous partage %2 (%3) : app Vidéo live", _by, _name, _label], 6, 40, "LIVECAM"] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "LIVECAM") then {
            [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        true
    };
    default { false };
};
