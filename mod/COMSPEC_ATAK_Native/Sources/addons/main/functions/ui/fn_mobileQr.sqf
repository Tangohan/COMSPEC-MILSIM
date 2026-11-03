/*
    « Sur mon téléphone » (2.9.39) : QR code à scanner avec son vrai téléphone pour retrouver une app de l'ATAK en version
    mobile sur Athena (comptes rendus, CR fin d'op, carte, briefs air), liée au compte, à la communauté et à la partie.
    Page MOBILEQR, ouverte par le bouton « Sur mon téléphone » des apps Comptes rendus, CR fin d'op et Carte.
    Le lien vient d'Athena par COMSPEC Link (« MobileLink.Create », 2.0.117) : adresse, adresse courte, validité (5 min,
    usage unique) et matrice du QR code, dessinée en contrôles par fn_formRender (ligne « qr ») : aucune image.
    Ce qui est fait sur le vrai téléphone revient par les flux du jeu : marqueurs du site (sondés), comptes rendus et
    CR fin d'op par un avis Athena traité dans fn_adminNotices (« mobile_report », « mobile_debrief »).
    Params : [action, argument]
      "open" app       ouvre la page MOBILEQR pour cette app (cr, compte-rendu, carte, briefs, controle) et demande un lien
                       (controle, 3.0.2 : historique des contrôles d'identité du joueur, /m/controle ; ouvert par l'app
                       Contrôle ID du SSE avec ["open", "controle"] call comspec_atak_native_fnc_mobileQr)
      "request"        demande (ou redemande : RÉGÉNÉRER) un lien à Athena
      "page"           dessine la page (fn_pageRender)
      "tick"           compte à rebours, chaque seconde tant que la page est ouverte
    État : uiNamespace COMSPEC_ATAK_MobQr (app, état wait / ok / err, adresse, adresse courte, fin de validité, QR, message)
*/
params [["_act", ""], ["_arg", ""]];
private _q = uiNamespace getVariable ["COMSPEC_ATAK_MobQr", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MobQr", _q];
private _labels = createHashMapFromArray [["cr", "Comptes rendus"], ["compte-rendu", "CR fin d'op"], ["carte", "Carte"], ["briefs", "Briefs air"], ["controle", "Contrôle ID"]];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MOBILEQR") then { ["MOBILEQR"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _fail = {
    params ["_msg", ["_link", false]];
    _q set ["status", "err"];
    _q set ["msg", _msg];
    _q set ["linkFix", _link];
    _q set ["busy", false];
};
switch (_act) do {
    case "open": {
        if !(_arg in _labels) then { _arg = "cr"; };
        _q set ["app", _arg];
        _q set ["status", "wait"];
        _q set ["qr", []];
        ["MOBILEQR"] call comspec_atak_native_fnc_navigate;
        ["request"] call comspec_atak_native_fnc_mobileQr;
    };
    case "request": {
        if (_q getOrDefault ["busy", false]) exitWith {};
        _q set ["status", "wait"];
        _q set ["qr", []];
        call _rerender;
        if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith {
            ["Le service Athena est désactivé : le téléphone fonctionne en local, il n'y a pas de version mobile à ouvrir."] call _fail;
            call _rerender;
        };
        if (!([] call comspec_atak_native_fnc_bridge) || {isNil "comspec_overwatch_connect_fnc_hashMapToJson"}) exitWith {
            ["COMSPEC Link n'est pas chargé : relancez Arma avec le pack COMSPEC ATAK à jour."] call _fail;
            call _rerender;
        };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {
            ["Athena non connecté : reliez ce jeu à votre compte dans l'app Athena, puis réessayez.", true] call _fail;
            call _rerender;
        };
        private _app = _q getOrDefault ["app", "cr"];
        private _p = getPosWorld player;
        private _body = createHashMapFromArray [
            ["app", _app], ["player_uid", getPlayerUID player], ["player_name", name player], ["pos_x", _p select 0], ["pos_y", _p select 1]
        ];
        private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", ""];
        if (_mk isEqualType "" && {_mk isNotEqualTo ""}) then { _body set ["mission_key", _mk]; };
        // CR fin d'op : la fiche ouverte (ou la dernière à remettre) part avec la demande, le vrai téléphone l'affiche.
        if (_app isEqualTo "compte-rendu") then {
            private _all = missionNamespace getVariable ["COMSPEC_ATAK_Dbf", createHashMap];
            private _e = _all getOrDefault [(uiNamespace getVariable ["COMSPEC_ATAK_DbfUi", createHashMap]) getOrDefault ["open", ""], createHashMap];
            if ((count _e) isEqualTo 0 || {(_e get "state") isEqualTo "done"}) then {
                private _pend = (values _all) select { (_x get "state") isNotEqualTo "done" };
                _e = if (_pend isEqualTo []) then { createHashMap } else { _pend select -1 };
            };
            if ((count _e) > 0) then {
                _body set ["context", createHashMapFromArray [
                    ["id", _e get "id"], ["mode", _e getOrDefault ["mode", "fill"]], ["title", _e getOrDefault ["title", ""]],
                    ["intro", _e getOrDefault ["intro", ""]], ["read", _e getOrDefault ["read", ""]], ["qs", _e getOrDefault ["qs", []]],
                    ["vals", _e getOrDefault ["vals", createHashMap]], ["state", _e getOrDefault ["state", ""]]
                ]];
            };
        };
        _q set ["busy", true];
        // L'ATAK traite les envois du vrai téléphone plus souvent pendant la partie (fn_adminNotices « check »).
        missionNamespace setVariable ["COMSPEC_ATAK_MobileUntil", diag_tickTime + 8 * 3600];
        if (isNil { missionNamespace getVariable "COMSPEC_ATAK_MobilePfh" }) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MobilePfh", [{
                params ["", "_id"];
                if ((missionNamespace getVariable ["COMSPEC_ATAK_MobileUntil", -1]) < diag_tickTime) exitWith {
                    [_id] call CBA_fnc_removePerFrameHandler;
                    missionNamespace setVariable ["COMSPEC_ATAK_MobilePfh", nil];
                };
                ["check"] call comspec_atak_native_fnc_adminNotices;
            }, 10] call CBA_fnc_addPerFrameHandler];
        };
        [[_body] call comspec_atak_native_fnc_json] spawn {
            params ["_json"];
            private _r = ["COMSPECExtension", "MobileLink.Create", [_json]] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = str _r; };
            [{
                params ["_r"];
                private _q = uiNamespace getVariable ["COMSPEC_ATAK_MobQr", createHashMap];
                _q set ["busy", false];
                if ((_r select [0, 3]) isEqualTo "OK|") then {
                    private _lines = (_r select [3]) splitString toString [10];
                    ((_lines param [0, ""]) splitString toString [9]) params [["_url", ""], ["_short", ""], ["_secs", "300"]];
                    private _rows = _lines select [1];
                    if (_url isEqualTo "" || {(count _rows) < 21}) exitWith {
                        _q set ["status", "err"]; _q set ["msg", "Athena n'a pas renvoyé de QR code. Réessayez dans un instant."]; _q set ["linkFix", false];
                    };
                    _q set ["status", "ok"];
                    _q set ["url", _url];
                    _q set ["short", [_url] call comspec_atak_native_fnc_athenaShortUrl];
                    _q set ["until", diag_tickTime + ((parseNumber _secs) max 30)];
                    _q set ["qr", _rows];
                } else {
                    private _e = toLower _r;
                    private _msg = switch (true) do {
                        case ((_e find "not_linked") >= 0 || {(_e find "unauthorized") >= 0}): { "Ce jeu n'est relié à aucun compte Athena : reliez-le dans l'app Athena (OBTENIR MON CODE), puis réessayez." };
                        case ((_e find "rate_limited") >= 0 || {(_e find "http_429") >= 0}): { "Trop de QR codes demandés : patientez quelques minutes avant de régénérer." };
                        case ((_e find "unknown") >= 0 || {(_e find "use_async") >= 0}): { "COMSPEC Link est trop ancien pour cette fonction : mettez à jour le pack, puis relancez Arma." };
                        case ((_e find "uplink_hold") >= 0): { "Pas de liaison de données sur ce téléphone : rapprochez-vous d'un relais, puis réessayez." };
                        case ((_e find "unavailable") >= 0 || {(_e find "http_503") >= 0}): { "Athena n'est pas encore prêt pour cette fonction. Réessayez plus tard." };
                        default { "Athena n'a pas répondu. Vérifiez la liaison, puis réessayez." };
                    };
                    _q set ["status", "err"];
                    _q set ["msg", _msg];
                    _q set ["linkFix", (_e find "not_linked") >= 0];
                };
                if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MOBILEQR") then { ["MOBILEQR"] call comspec_atak_native_fnc_pageRender; };
            }, [_r]] call CBA_fnc_execNextFrame;
        };
    };
    case "tick": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_MobQrLeft", controlNull];
        if (isNull _c || {(_q getOrDefault ["status", ""]) isNotEqualTo "ok"}) exitWith {};
        private _left = round ((_q getOrDefault ["until", 0]) - diag_tickTime);
        if (_left <= 0) exitWith { call _rerender; };
        _c ctrlSetStructuredText parseText ((format ["<t align='center' size='0.95' color='%1'>%2</t>", [(COMSPEC_ATAK_Th get "hexTextMuted"), "#e8c547"] select (_left < 60),
            format [("Valable encore %1 min %2 s · une seule utilisation") call comspec_atak_native_fnc_tr, floor (_left / 60), [str (_left mod 60), "0" + str (_left mod 60)] select ((_left mod 60) < 10)]]) call comspec_atak_native_fnc_tr);
    };
    case "page": {
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["", "", "_bw", "_bh"];
        private _app = _q getOrDefault ["app", "cr"];
        private _name = _labels getOrDefault [_app, "Comptes rendus"];
        private _icon = "\z\comspec_atak_native\addons\main\data\app_athena.paa";
        private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_mobileQr;", str _a, str _v] };
        private _back = { [{ [] call comspec_atak_native_fnc_back; }] call CBA_fnc_execNextFrame; };
        private _rows = [["strip", format ["<t font='COMSPEC_BarlowBold' color='#35d6a1'>%1</t>  <t font='COMSPEC_BarlowBold' color='" + (COMSPEC_ATAK_Th get "hexText") + "'>%2</t>",
            "SUR MON TÉLÉPHONE" call comspec_atak_native_fnc_tr, toUpper (_name call comspec_atak_native_fnc_tr)]]];
        uiNamespace setVariable ["COMSPEC_ATAK_MobQrLeft", controlNull];
        switch (_q getOrDefault ["status", "wait"]) do {
            case "err": {
                _rows pushBack ["status", _icon, [0.90, 0.28, 0.23, 1], "QR code indisponible", _q getOrDefault ["msg", ""], ""];
                private _btns = [["RÉESSAYER", ["request"] call _act, true]];
                if (_q getOrDefault ["linkFix", false]) then { _btns pushBack ["APP ATHENA", { [{ ["ATHENA"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }, false]; };
                _btns pushBack ["RETOUR", _back, false];
                _rows pushBack ["buttons", _btns];
            };
            case "ok": {
                private _left = round ((_q getOrDefault ["until", 0]) - diag_tickTime);
                if (_left <= 0) then {
                    _rows pushBack ["banner", "<t font='COMSPEC_BarlowBold' size='1.1' color='#140c02'>QR CODE EXPIRÉ</t>", [0.92, 0.6, 0.18, 1]];
                    _rows pushBack ["text", "<t size='0.9' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Il n'était valable que 5 minutes. Régénérez-le, puis scannez le nouveau.</t>"];
                    _rows pushBack ["buttons", [["RÉGÉNÉRER", ["request"] call _act, true], ["RETOUR", _back, false]]];
                } else {
                    _rows pushBack ["text", format ["<t size='0.9' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", format [("Scannez ce QR code avec l'appareil photo de votre vrai téléphone : l'app %1 s'ouvre sur Athena, liée à votre compte et à cette partie.") call comspec_atak_native_fnc_tr, _name call comspec_atak_native_fnc_tr]]];
                    _rows pushBack ["qr", _q getOrDefault ["qr", []], [0.86, 0.62] select (_bw > _bh)];
                    _rows pushBack ["text", format ["<t align='center' font='EtelkaMonospacePro' size='0.9' color='" + (COMSPEC_ATAK_Th get "hexText") + "'>%1</t>", _q getOrDefault ["short", ""]]];
                    _rows pushBack ["text", "", [], "COMSPEC_ATAK_MobQrLeft"];
                    _rows pushBack ["buttons", [["RÉGÉNÉRER", ["request"] call _act, true, true, "Nouveau QR code : l'ancien ne sert plus"], ["RETOUR", _back, false]]];
                    _rows pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Ce que vous faites sur le vrai téléphone arrive dans cet ATAK et part comme si vous l'aviez fait ici. Déconnexion du téléphone : profil Athena, Appareils ATAK.</t>"];
                };
            };
            default {
                _rows pushBack ["empty", _icon, "Demande du QR code…", "Athena prépare un lien pour votre vrai téléphone."];
            };
        };
        [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
        ["tick"] call comspec_atak_native_fnc_mobileQr;
        // Compte à rebours : une seconde, tant que la page reste ouverte.
        if (isNil { uiNamespace getVariable "COMSPEC_ATAK_MobQrPfh" }) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MobQrPfh", [{
                params ["", "_id"];
                if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "MOBILEQR" || {isNull ([] call comspec_atak_native_fnc_display)}) exitWith {
                    [_id] call CBA_fnc_removePerFrameHandler;
                    uiNamespace setVariable ["COMSPEC_ATAK_MobQrPfh", nil];
                };
                ["tick"] call comspec_atak_native_fnc_mobileQr;
            }, 1] call CBA_fnc_addPerFrameHandler];
        };
        true
    };
};
