/*
    Actions du poste TOC web sur CE téléphone (fil WC1 « phone » du sondage des charges, fn_webCmdDispatch du connect).
    L'ordre vient du site pour le seul joueur visé (compte du joueur, déjà vérifié par fn_webCmdDispatch). Rien n'est affiché
    à l'écran du jeu : ni hint, ni texte à l'écran, ni bandeau, ni toast, ni popup vert. Le message arrive dans la boîte du
    téléphone (app Messages, canal des alertes) et dans le journal de l'app Alertes (carte « ALERTE DU TOC »), le
    téléphone vibre ou sonne avec les sons déjà présents du mod (fn_vibrate, son de notification d'ordre).

    Modes :
      ["cmd", [id, commande, réf, args]]  client : exécute une commande du poste, rend compte par fn_webCmdAck ; true si traitée
        notify  lv = i | a | u (info, alerte, urgent), m = texte codé (« @code_ » pour tout caractère hors lettres, chiffres,
                espace et « . , : - »), ak = 1 (accusé de réception demandé : « reçu » à l'arrivée, « lu » à l'ouverture de
                l'app Alertes), by = opérateur du poste. info : arrive sans bruit ; alerte : vibre ; urgent : trois vibrations.
        ring    d = 3 à 30 s, md = ring | vib, by : fait sonner (son de notification d'ordre) ou vibrer le téléphone ; une carte
                « ALERTE DU TOC » le dit dans le journal
        stop    arrête la sonnerie ou la vibration en cours
        locate  vis = 1 : rend la position du téléphone telle que le téléphone la voit (fn_gpsPos) et sa précision en mètres
                (fn_gpsFix) ; téléphone éteint, détruit ou sans fix : « position inconnue » (jamais de position inventée) ;
                vis = 1 : une entrée « localisé par le TOC à HH:MM » est ajoutée au journal de l'app Alertes (transparence)
        trace   kd = tri : le poste a triangulé ce téléphone ; même entrée de journal (« triangulé par le TOC à HH:MM »)
      ["read"]                            l'app Alertes est ouverte : les messages à accusé de réception passent en « lu »
      ["decode", texte]                   texte codé du fil -> texte
      ["clear"]                           coupe la sonnerie en cours (changement de partie)
    Exemple : ["cmd", [_id, "locate", "", createHashMapFromArray [["vis", "1"]]]] call comspec_atak_native_fnc_tocPhone;
*/
params [["_mode", "cmd"], ["_a", []]];
private _ack = {
    params ["_cid", "_st", "_msg", ["_data", createHashMap]];
    if (isNil "comspec_overwatch_connect_fnc_webCmdAck") exitWith { false };
    [_cid, _st, _msg, _data] call comspec_overwatch_connect_fnc_webCmdAck
};
private _clock = { ["now"] call comspec_atak_native_fnc_clockFmt };
private _decode = {
    params [["_s", ""]];
    if !(_s isEqualType "") exitWith { "" };
    private _starts = (_s select [0, 1]) isEqualTo "@";
    private _out = "";
    {
        if (_forEachIndex > 0 || {_starts}) then {
            private _k = _x find "_";
            if (_k > 0) then {
                _out = _out + (toString [((parseNumber (_x select [0, _k])) max 32) min 65535]) + (_x select [_k + 1]);
            } else {
                _out = _out + _x;
            };
        } else {
            _out = _out + _x;
        };
    } forEach (_s splitString "@");
    _out
};
private _alert = {
    // Carte du journal de l'app Alertes : [type, de, texte]
    params ["_type", "_who", "_text"];
    [_type, _who, [], "", _text] call comspec_atak_native_fnc_alertsLog
};
private _ringOff = {
    private _h = missionNamespace getVariable ["COMSPEC_ATAK_TocRingHandle", -1];
    if (_h isNotEqualTo -1) then { [_h] call CBA_fnc_removePerFrameHandler; };
    missionNamespace setVariable ["COMSPEC_ATAK_TocRingHandle", -1];
    missionNamespace setVariable ["COMSPEC_ATAK_TocRingUntil", -1];
};

switch (_mode) do {
    case "decode": { [_a] call _decode };
    case "clear": { call _ringOff; true };
    case "read": {
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_TocPhoneToRead", []];
        if (_list isEqualTo []) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocPhoneToRead", []];
        { [_x, "done", "Lu sur le téléphone"] call _ack; } forEach _list;
        true
    };
    case "cmd": {
        _a params [["_cid", ""], ["_cmd", ""], "", ["_args", createHashMap]];
        if !(_args isEqualType createHashMap) then { _args = createHashMap; };
        private _by = ([_args getOrDefault ["by", ""]] call _decode) select [0, 24];
        private _who = ["TOC", format ["TOC · %1", _by]] select (_by isNotEqualTo "");
        switch (_cmd) do {
            case "notify": {
                private _lv = _args getOrDefault ["lv", "i"];
                private _txt = ([_args getOrDefault ["m", ""]] call _decode) select [0, 160];
                if (_txt isEqualTo "") exitWith { [_cid, "failed", "Message vide"] call _ack; true };
                private _wantAck = (_args getOrDefault ["ak", "0"]) isEqualTo "1";
                private _mid = "TOC-" + _cid;
                private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
                private _inbox = +(_data getOrDefault ["inbox", []]);
                if ((_inbox findIf { _x isEqualType createHashMap && {(_x getOrDefault ["id", ""]) isEqualTo _mid} }) >= 0) exitWith {
                    // Déjà reçue (accusé perdu, nouvelle session) : on rend compte sans rejouer le son.
                    [_cid, "done", "Déjà reçu"] call _ack;
                    true
                };
                _inbox pushBack createHashMapFromArray [
                    ["id", _mid], ["author", _who], ["body", _txt], ["time", call _clock], ["stamp", call _clock],
                    ["kind", "TOC_ALERT"], ["status", "RECEIVED"], ["level", _lv]
                ];
                while { (count _inbox) > 60 } do { _inbox deleteAt 0; };
                ["inbox", _inbox] call comspec_atak_native_fnc_storeSet;
                private _type = switch (_lv) do { case "u": { "TOC_URGENT" }; case "a": { "TOC_ALERT" }; default { "TOC_INFO" } };
                [_type, _who, _txt] call _alert;
                // Sons et vibration du téléphone seulement (fn_vibrate respecte le mode discrétion et le réglage du joueur).
                if (_lv isEqualTo "a") then { [] call comspec_atak_native_fnc_vibrate; };
                if (_lv isEqualTo "u") then {
                    [] call comspec_atak_native_fnc_vibrate;
                    { [{ [] call comspec_atak_native_fnc_vibrate; }, [], _x] call comspec_atak_native_fnc_waitAndExec; } forEach [1.6, 3.2];
                };
                if (_wantAck) then {
                    private _todo = missionNamespace getVariable ["COMSPEC_ATAK_TocPhoneToRead", []];
                    _todo pushBack _cid;
                    missionNamespace setVariable ["COMSPEC_ATAK_TocPhoneToRead", _todo];
                    [_cid, "accepted", "Reçu sur le téléphone"] call _ack;
                } else {
                    [_cid, "done", "Reçu sur le téléphone"] call _ack;
                };
                true
            };
            case "ring": {
                private _dur = ((parseNumber (_args getOrDefault ["d", "10"])) max 3) min 30;
                private _md = _args getOrDefault ["md", "ring"];
                call _ringOff;
                private _until = diag_tickTime + _dur;
                missionNamespace setVariable ["COMSPEC_ATAK_TocRingUntil", _until];
                private _vib = _md isEqualTo "vib";
                private _label = ["Sonnerie", "Vibration"] select _vib;
                ["TOC_INFO", _who, format [["Votre téléphone sonne (%1 s)", "Votre téléphone vibre (%1 s)"] select _vib, round _dur]] call _alert;
                private _h = [{
                    params ["_pa", "_h"];
                    _pa params ["_until", "_md"];
                    if (diag_tickTime >= _until || {(missionNamespace getVariable ["COMSPEC_ATAK_TocRingUntil", -1]) isNotEqualTo _until}) exitWith {
                        [_h] call CBA_fnc_removePerFrameHandler;
                        if ((missionNamespace getVariable ["COMSPEC_ATAK_TocRingUntil", -1]) isEqualTo _until) then { missionNamespace setVariable ["COMSPEC_ATAK_TocRingHandle", -1]; };
                    };
                    [] call comspec_atak_native_fnc_vibrate;
                    if (_md isEqualTo "ring" && {!(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false])} && {!isNil "comspec_overwatch_connect_fnc_playAtakNotification"}) then {
                        ["order_priority"] call comspec_overwatch_connect_fnc_playAtakNotification;
                    };
                }, 1.5, [_until, _md]] call CBA_fnc_addPerFrameHandler;
                missionNamespace setVariable ["COMSPEC_ATAK_TocRingHandle", _h];
                [_cid, "done", format ["%1 pendant %2 s", _label, round _dur]] call _ack;
                true
            };
            case "stop": {
                call _ringOff;
                [_cid, "done", "Sonnerie arrêtée"] call _ack;
                true
            };
            case "locate": {
                private _health = ([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"];
                private _has = [player] call comspec_atak_native_fnc_hasDevice;
                if (!_has) exitWith { [_cid, "failed", "Pas de téléphone sur ce joueur"] call _ack; true };
                if (_health in ["OFF", "BROKEN"]) exitWith { [_cid, "failed", "Téléphone éteint ou hors service"] call _ack; true };
                private _fix = [] call comspec_atak_native_fnc_gpsFix;
                _fix params [["_err", 500], ["_state", "NOFIX"]];
                if !(_state in ["FIX", "ACQ", "NET"]) exitWith { [_cid, "failed", "Position inconnue : pas de fix GPS"] call _ack; true };
                private _p = [] call comspec_atak_native_fnc_gpsPos;
                if ((_args getOrDefault ["vis", "1"]) isEqualTo "1") then {
                    ["TOC_TRACE", _who, format ["Localisé par le TOC à %1", call _clock]] call _alert;
                };
                private _res = createHashMapFromArray [["x", round (_p select 0)], ["y", round (_p select 1)], ["acc", (round _err) max 3]];
                [_cid, "done", "Position transmise", _res] call _ack;
                true
            };
            case "trace": {
                if ((_args getOrDefault ["kd", ""]) isEqualTo "tri") then {
                    ["TOC_TRACE", _who, format ["Triangulé par le TOC à %1", call _clock]] call _alert;
                };
                [_cid, "done", "Journal du téléphone mis à jour"] call _ack;
                true
            };
            default { [_cid, "failed", "Commande de téléphone inconnue"] call _ack; true };
        };
    };
    default { false };
};
