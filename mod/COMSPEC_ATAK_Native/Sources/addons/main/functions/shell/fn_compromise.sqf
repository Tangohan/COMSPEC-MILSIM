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
      "ownerReset" [id, terminal] : mon téléphone a été compromis : nouvel IMEI et nouvel identifiant sur le prochain
      "newDevice" [ref, raison, terminal] : l'identifiant du terminal change ; l'ancien reste exclu sur Athena, seul le nouveau s'enregistre
      "deviceTick"     : à chaque tour de la barre d'état : enregistre le nouvel appareil dès qu'un téléphone est sur moi
      "deviceStatus"   : [titre, texte] pour l'app Alertes ([] : rien en cours)
      "boot"           : IMEI gardé après un respawn, compromission validée pendant mon absence
      "can" ligne      : puis-je valider ?        "validate" id    "dismiss" id    "map" id
      "mine"           : compromissions de mon camp (plus récentes d'abord)
*/
params [["_act", ""], ["_arg", []]];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
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
        if ((player getVariable ["COMSPEC_ATAK_PhoneTag", []]) isNotEqualTo _tag) then { player setVariable ["COMSPEC_ATAK_PhoneTag", _tag, [2, clientOwner]]; };
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
                ["reason", format ["Capturé par l'ennemi vers %1", _r select 5]], ["by", _r select 8], ["ref", _r select 0], ["revoked", "1"],
                // Identifiant déjà changé par « ownerReset » : le mode compromis reste sur l'ancien, jamais sur le nouvel appareil.
                ["terminal", ["previous", _r select 0] call comspec_atak_native_fnc_compromise]]] call comspec_atak_native_fnc_compromiseView;
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
        // Requête HTTP vers Athena : hors du gestionnaire CBA (non suspendable), par fn_extAwait pour ne pas figer le jeu.
        [_id, _tuid, _cs] spawn {
            params ["_id", "_tuid", "_cs"];
            private _ok = false;
            if (_tuid isNotEqualTo "" && {[] call comspec_atak_native_fnc_bridge}) then {
                private _r = ["COMSPECExtension", "CompromiseTerminal", [_tuid, "compromised", format ["Compromission validée sur le terrain (%1, ATAK de %2) : révocation du certificat", _id, _cs]]] call comspec_atak_native_fnc_extAwait;
                if (_r isEqualType []) then { _r = _r param [0, ""]; };
                if !(_r isEqualType "") then { _r = str _r; };
                _ok = (_r select [0, 3]) isEqualTo "OK|";
                ["INFO", "CAPTURE", format ["%1 : CompromiseTerminal %2 -> %3", _id, _tuid, _r]] call comspec_atak_native_fnc_log;
            };
            ["comspec_atak_native_captureSrv", ["athena", [_id, _ok, player]]] call CBA_fnc_serverEvent;
            if (_ok) then { ["SUCCESS", format ["Athena : certificat de l'ATAK de %1 révoqué", _cs], 5, 40] call comspec_atak_native_fnc_notify; };
        };
    };
    case "ownerReset": {
        _arg params [["_id", ""], ["_term", ""]];
        private _gen = (player getVariable ["COMSPEC_ATAK_PhoneGen", 0]) + 1;
        player setVariable ["COMSPEC_ATAK_PhoneGen", _gen, true];
        // Dans le profil : une variable d'unité disparaît au respawn, et l'ancien IMEI revenait avec le téléphone suivant.
        profileNamespace setVariable ["COMSPEC_ATAK_PhoneGenSaved", _gen];
        ["newDevice", [_id, "capture validée", _term]] call comspec_atak_native_fnc_compromise;
    };
    case "newDevice": {
        _arg params [["_ref", ""], ["_why", ""], ["_term", ""]];
        private _cur = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
        if !(_cur isEqualType "") then { _cur = ""; };
        // Ordre pour un autre identifiant que le mien (déjà remplacé, autre poste) : le terminal actif ne change pas.
        if (_term isNotEqualTo "" && {_term isNotEqualTo _cur}) exitWith {
            ["INFO", "CAPTURE", format ["Remplacement de l'appareil %1 ignoré : terminal actif %2", _term, _cur]] call comspec_atak_native_fnc_log;
            false
        };
        private _gen = profileNamespace getVariable ["comspec_overwatch_terminal_gen", 0];
        if !(_gen isEqualType 0) then { _gen = 0; };
        profileNamespace setVariable ["comspec_overwatch_terminal_gen", _gen + 1];
        profileNamespace setVariable ["comspec_overwatch_terminal_uid", ""];
        private _new = if (isNil "comspec_overwatch_connect_fnc_getTerminalUid") then { "" } else { [] call comspec_overwatch_connect_fnc_getTerminalUid };
        profileNamespace setVariable ["COMSPEC_ATAK_NewDevice", [_cur, _ref, _why, "pending"]];
        [true] call comspec_atak_native_fnc_saveProfile;
        uiNamespace setVariable ["COMSPEC_ATAK_NewDeviceAt", -1e9];
        ["WARN", "CAPTURE", format ["Appareil remplacé (%1, %2) : %3 reste exclu, nouveau terminal %4", _why, _ref, _cur, _new]] call comspec_atak_native_fnc_log;
        ["WARNING", "Votre ancien ATAK est exclu du réseau. Prenez un téléphone : il recevra son propre identifiant et retrouvera l'accès tout seul.", 10, 80, "ALERTS"] call comspec_atak_native_fnc_notify;
        call _render;
        true
    };
    case "previous": {
        // Terminal qui portait la compromission _arg ("" : l'actif, le mode compromis prend alors le mien).
        (profileNamespace getVariable ["COMSPEC_ATAK_NewDevice", []]) params [["_old", ""], ["_ref", ""]];
        [_old, ""] select !(_ref isEqualTo _arg)
    };
    case "deviceTick": {
        private _st = profileNamespace getVariable ["COMSPEC_ATAK_NewDevice", []];
        if !(_st isEqualType [] && {(_st param [3, ""]) isEqualTo "pending"}) exitWith {};
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_NewDeviceAt", -1e9]) < 20) exitWith {};
        if (isNil "comspec_overwatch_connect_fnc_syncAtakRealism" || {!([player, true] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
        if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_NewDeviceAt", diag_tickTime];
        [] spawn {
            private _ok = ["", true] call comspec_overwatch_connect_fnc_syncAtakRealism;
            if !(_ok isEqualTo true) exitWith {
                ["WARN", "CAPTURE", format ["Nouvel appareil non enregistré : %1", missionNamespace getVariable ["COMSPEC_AtakRealismLastError", ""]]] call comspec_atak_native_fnc_log;
            };
            private _s = profileNamespace getVariable ["COMSPEC_ATAK_NewDevice", []];
            if ((count _s) >= 4) then { _s set [3, "done"]; profileNamespace setVariable ["COMSPEC_ATAK_NewDevice", _s]; };
            [] call comspec_atak_native_fnc_saveProfile;
            ["SUCCESS", "Nouvel appareil enregistré : vous êtes de nouveau sur le réseau.", 8, 70, "ALERTS"] call comspec_atak_native_fnc_notify;
            [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
        };
    };
    case "deviceStatus": {
        private _st = profileNamespace getVariable ["COMSPEC_ATAK_NewDevice", []];
        if !(_st isEqualType [] && {(count _st) >= 4}) exitWith { [] };
        if ((_st select 3) isEqualTo "done") exitWith { ["Nouvel appareil actif", "L'ancien ATAK reste exclu du réseau."] };
        if !([player, true] call comspec_atak_native_fnc_hasDevice) exitWith { ["Ancien ATAK exclu", "Prenez un téléphone : il recevra son propre identifiant."] };
        ["Nouvel appareil", "Enregistrement en cours auprès d'Athena…"]
    };
    case "boot": {
        if (!hasInterface) exitWith {};
        private _restore = {
            params ["_u"];
            private _g = profileNamespace getVariable ["COMSPEC_ATAK_PhoneGenSaved", 0];
            if (_g isEqualType 0 && {_g > (_u getVariable ["COMSPEC_ATAK_PhoneGen", 0])}) then { _u setVariable ["COMSPEC_ATAK_PhoneGen", _g, true]; };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneGenRestore", _restore];
        [player] call _restore;
        player addEventHandler ["Respawn", { params ["_u"]; [_u] call (missionNamespace getVariable ["COMSPEC_ATAK_PhoneGenRestore", {}]); }];
        // Compromission validée pendant que j'étais déconnecté (plantage, reconnexion) : l'ordre du serveur n'a pas pu m'arriver.
        [{
            private _done = (profileNamespace getVariable ["COMSPEC_ATAK_NewDevice", []]) param [1, ""];
            {
                _x params ["_id", "", "", "", "", "", "", "_state", "", "", "_ouid"];
                if (_state isEqualTo "validated" && {_ouid isEqualTo getPlayerUID player} && {_id isNotEqualTo _done}) exitWith {
                    ["ownerReset", [_id, ""]] call comspec_atak_native_fnc_compromise;
                };
            } forEach (missionNamespace getVariable ["COMSPEC_ATAK_CaptureList", []]);
        }, [], 30] call comspec_atak_native_fnc_waitAndExec;
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
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call comspec_atak_native_fnc_nextFrame;
    };
    case "mine": {
        private _side = str side group player;
        private _l = (missionNamespace getVariable ["COMSPEC_ATAK_CaptureList", []]) select { (_x select 1) isEqualTo _side };
        reverse _l;
        _l
    };
    default { false };
};
