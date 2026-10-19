/*
    Compromission d'un ATAK du camp (client) : alertes, validation manuelle, révocation du certificat.
    Le serveur (fn_captureServer) détecte qu'une IA ennemie a ramassé le téléphone d'un joueur et l'exploite.
    Les ATAK du camp reçoivent l'alerte ; l'app Alertes liste les compromissions (COMSPEC_ATAK_CaptureList) avec
    VALIDER et REJETER pour les chefs de groupe, le propriétaire et les Zeus (réglage comspec_atak_native_capture_validate :
    tout le camp). Validée : le serveur coupe l'exploitation, puis demande à un client relié à Athena de révoquer le
    certificat du terminal (COMSPEC Link « CompromiseTerminal », état « compromised »).
    Params : [action, argument]
      "publish"        : toutes les 5 s, étiquette du téléphone sur mon unité (COMSPEC_ATAK_PhoneTag), lue par le serveur
                         sur mon corps ou quand je suis à terre : [UID, camp, indicatif, numéro, IMEI, terminal Athena,
                         code de verrouillage, effacé]
      "event" [genre, ligne] : "new" | "activity" | "validated" | "dismissed" (du serveur)
      "revoke" [id, terminal, indicatif] : appel COMSPEC Link puis compte rendu au serveur
      "ownerReset"     : mon téléphone a été compromis : nouvel IMEI sur le prochain
      "can" ligne      : puis-je valider ?        "validate" id    "dismiss" id    "map" id
      "mine"           : compromissions de mon camp (plus récentes d'abord)
*/
params [["_act", ""], ["_arg", []]];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _row = {
    params ["_id"];
    private _l = missionNamespace getVariable ["COMSPEC_ATAK_CaptureList", []];
    private _i = _l findIf { (_x select 0) isEqualTo _id };
    if (_i >= 0) then { _l select _i } else { [] }
};
switch (_act) do {
    case "publish": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith {};
        private _tag = [];
        if ([player, true] call comspec_atak_native_fnc_hasDevice) then {
            ([player] call comspec_atak_native_fnc_phoneIdent) params ["_num", "_imei"];
            private _tuid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
            if !(_tuid isEqualType "") then { _tuid = ""; };
            _tag = [getPlayerUID player, str side group player, [player] call comspec_atak_native_fnc_unitCallsign, _num, _imei, _tuid,
                (profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]) isNotEqualTo "", ["wiped"] call comspec_atak_native_fnc_phoneSecurity];
        };
        if ((player getVariable ["COMSPEC_ATAK_PhoneTag", []]) isNotEqualTo _tag) then { player setVariable ["COMSPEC_ATAK_PhoneTag", _tag, true]; };
    };
    case "event": {
        _arg params [["_kind", ""], ["_r", []]];
        if ((count _r) < 16) exitWith {};
        call _render;
        // Mon téléphone capturé, validé compromis : COMSPEC Link révoque le certificat de mon terminal Athena. Mode
        // « terminal compromis » automatique (fn_compromiseView), même sans téléphone sur moi (affiché sur le prochain),
        // jusqu'à sa levée par Athena. Pas pour un événement rejoué à l'arrivée en partie.
        if (_kind isEqualTo "validated" && {(_r select 10) isEqualTo getPlayerUID player} && {!(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false])}) then {
            ["set", createHashMapFromArray [["origin", "auto"], ["source", "field"], ["state", "compromised"], ["match", "terminal"], ["imei", _r select 4],
                ["reason", format ["Capturé par l'ennemi vers %1", _r select 5]], ["by", _r select 8], ["ref", _r select 0], ["revoked", "1"]]] call comspec_atak_native_fnc_compromiseView;
        };
        // L'alerte arrive sur l'ATAK : sans téléphone, rien.
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        _r params ["_id", "", "_cs", "_num", "_imei", "_grid", "", "", "_by", "", "_ouid", "_lastGrid", "", "_wiped"];
        private _mine = _ouid isEqualTo getPlayerUID player;
        private _who = [_cs, "votre téléphone"] select _mine;
        switch (_kind) do {
            case "new": {
                private _msg = format ["ATAK de %1 (IMEI %2) récupéré par l'ennemi vers %3.%4", _who, _imei, _grid,
                    [" Validez dans Alertes pour révoquer son certificat.", " Il avait été effacé : rien d'exploitable."] select _wiped];
                ["WARNING", _msg, 10, 90, "ALERTS"] call comspec_atak_native_fnc_notify;
                ["COMPROMISSION SUSPECTÉE", format ["ATAK de %1 aux mains de l'ennemi · IMEI %2", _who, _imei], "Réseau ATAK", [0.72, 0.38, 0.04, 0.92], 10] call comspec_atak_native_fnc_fullAlert;
                ["COMPROMISE", _cs, _r select 15, _grid, format ["IMEI %1 · n° %2", _imei, _num]] call comspec_atak_native_fnc_alertsLog;
            };
            case "activity": {
                ["WARNING", format ["Activité anormale : l'ATAK de %1 (IMEI %2) se connecte au réseau depuis %3", _who, _imei, _lastGrid], 8, 80, "ALERTS"] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
            };
            case "validated": {
                ["SUCCESS", format ["Compromission validée par %1 : l'ATAK de %2 est exclu du réseau, révocation du certificat demandée", _by, _who], 8, 70, "ALERTS"] call comspec_atak_native_fnc_notify;
            };
            case "dismissed": {
                ["INFO", format ["Alerte de compromission de l'ATAK de %1 rejetée par %2", _who, _by], 6, 40, "ALERTS"] call comspec_atak_native_fnc_notify;
            };
        };
    };
    case "revoke": {
        _arg params [["_id", ""], ["_tuid", ""], ["_cs", ""]];
        private _ok = false;
        if (_tuid isNotEqualTo "" && {[] call comspec_atak_native_fnc_bridge}) then {
            private _r = "COMSPECExtension" callExtension ["CompromiseTerminal", [_tuid, "compromised", format ["Compromission validée sur le terrain (%1, ATAK de %2) : révocation du certificat", _id, _cs]]];
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            _ok = (_r select [0, 3]) isEqualTo "OK|";
            ["INFO", "CAPTURE", format ["%1 : CompromiseTerminal %2 -> %3", _id, _tuid, _r]] call comspec_atak_native_fnc_log;
        };
        ["comspec_atak_native_captureSrv", ["athena", [_id, _ok, player]]] call CBA_fnc_serverEvent;
        if (_ok) then { ["SUCCESS", format ["Athena : certificat de l'ATAK de %1 révoqué", _cs], 5, 40] call comspec_atak_native_fnc_notify; };
    };
    case "ownerReset": {
        player setVariable ["COMSPEC_ATAK_PhoneGen", (player getVariable ["COMSPEC_ATAK_PhoneGen", 0]) + 1, true];
        ["WARNING", "Votre ATAK capturé est exclu du réseau. Votre prochain téléphone aura un nouvel IMEI.", 8, 70] call comspec_atak_native_fnc_notify;
    };
    case "can": {
        private _r = _arg;
        if ((count _r) < 16 || {(_r select 7) isNotEqualTo "suspected"}) exitWith { false };
        (missionNamespace getVariable ["comspec_atak_native_capture_validate", 0]) isEqualTo 1
            || {(leader group player) isEqualTo player}
            || {(_r select 10) isEqualTo getPlayerUID player}
            || {!isNull (getAssignedCuratorLogic player)}
    };
    case "validate";
    case "dismiss": {
        private _r = [_arg] call _row;
        if !(["can", _r] call comspec_atak_native_fnc_compromise) exitWith {
            ["WARNING", "Validation réservée aux chefs de groupe, au propriétaire du téléphone et aux Zeus", 4, 30] call comspec_atak_native_fnc_notify;
        };
        ["comspec_atak_native_captureSrv", [_act, [_arg, player]]] call CBA_fnc_serverEvent;
    };
    case "map": {
        private _r = [_arg] call _row;
        if ((count _r) < 16) exitWith {};
        private _p = [_r select 15, _r select 12] select ((count (_r select 12)) >= 2);
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "mine": {
        private _side = str side group player;
        private _l = (missionNamespace getVariable ["COMSPEC_ATAK_CaptureList", []]) select { (_x select 1) isEqualTo _side };
        reverse _l;
        _l
    };
    default { false };
};
