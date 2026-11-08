/*
    Avis de l'administration ATHENA sur l'ATAK du joueur (site : /admin/parc-atak, poste de commandement, back-office) :
    compromission déclarée ou levée, données ATAK réinitialisées, certificat révoqué, rétabli ou régénéré, nouveau numéro,
    message imposé. Récupérés par COMSPEC Link (« AdminControl.Poll », toutes les 30 s une fois relié à Athena).
    Chaque nouvel avis : alerte de la couleur du genre dans l'écran du téléphone (jamais sur l'écran de jeu), notification, et carte en tête de l'app Alertes
    jusqu'à ce que le joueur appuie sur COMPRIS (validé sur Athena par « AdminControl.Ack »).
    Params : [action, argument]
      "check"        client : demande les avis à Athena (toutes les 30 s au plus). Sans téléphone sur le joueur, seule la
                     demande de réparation est traitée : un joueur dont le jeu plante en prenant un téléphone n'en a pas
      "show" ligne   alerte, notification et vibration pour un avis
      "ack" id       COMPRIS : retire la carte et prévient Athena
      "list"         avis non validés, plus récents d'abord
      "style" ligne  [titre, texte, couleur RGBA, type de notification] d'un avis
    Ligne : [id, genre, niveau, titre, texte, à valider, heure, méta]
    Méta (COMSPEC Link 2.0.90+, 7e colonne) : « source=admin;state=captured;revoked=2;ref=… » pour une compromission
    (fn_compromiseView) ; vide avec un Link plus ancien (origine manuelle, état lu dans le titre).
    Compromission : le téléphone passe en mode « terminal compromis » (fn_compromiseView) jusqu'à la levée.
    CR de fin d'opération (genre « debrief », réglages « debrief_cfg ») : confiés à l'app CR fin d'op (fn_debrief).
    Avis déjà vus et déjà validés : gardés dans le profil du joueur (COMSPEC_ATAK_NoticeSeen, COMSPEC_ATAK_NoticeAck,
    200 derniers) et non dans la seule partie en cours. Un avis validé n'est plus affiché ni listé, même si Athena le
    renvoie (retour de TTRD, 26/10 : « j'ai beau valider la levée de compromission, le message s'affiche à chaque
    fois ») : le COMPRIS repart vers Athena à chaque réception, jusqu'à ce qu'il cesse de l'envoyer. Un avis déjà vu
    la partie précédente, mais pas encore validé, reste dans l'app Alertes sans rejouer son bandeau.
*/
params [["_act", ""], ["_arg", []]];
private _list = missionNamespace getVariable ["COMSPEC_ATAK_AdminNotices", []];
// Listes gardées dans le profil : avis déjà affichés, avis validés par le joueur (lues aussi depuis la requête Athena).
private _keep = {
    params ["_k", ["_add", ""]];
    private _l = profileNamespace getVariable [_k, []];
    if !(_l isEqualType []) then { _l = []; };
    if (_add isNotEqualTo "" && {!(_add in _l)}) then {
        _l pushBack _add;
        while { (count _l) > 200 } do { _l deleteAt 0; };
        profileNamespace setVariable [_k, _l];
        [] call comspec_atak_native_fnc_saveProfile;
    };
    _l
};
missionNamespace setVariable ["COMSPEC_ATAK_NoticeKeep", _keep];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
switch (_act) do {
    case "check": {
        if (!hasInterface) exitWith {};
        // Vrai téléphone ouvert depuis le jeu (« Sur mon téléphone », fn_mobileQr) : ses envois arrivent toutes les 10 s.
        private _every = [30, 10] select ((missionNamespace getVariable ["COMSPEC_ATAK_MobileUntil", -1]) > diag_tickTime);
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesAt", -1e9]) < _every) exitWith {};
        if !([] call comspec_atak_native_fnc_bridge) exitWith {};
        if (isNull player) exitWith {};
        // Les autres avis attendent que le joueur ait un téléphone sur lui.
        private _noPhone = !([player] call comspec_atak_native_fnc_hasDevice);
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {};
        if (isNil "comspec_overwatch_connect_fnc_hashMapToJson") exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesNo", false]) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesBusy", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesAt", diag_tickTime];
        private _tuid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
        if !(_tuid isEqualType "") then { _tuid = ""; };
        // « caps » : ce téléphone sait ouvrir le CR de fin d'opération imposé par le site (fn_debrief).
        private _json = [createHashMapFromArray [["player_uid", getPlayerUID player], ["terminal_uid", _tuid], ["caps", "debrief"]]] call comspec_overwatch_connect_fnc_hashMapToJson;
        [_json, _noPhone] spawn {
            params ["_json", "_noPhone"];
            private _r = ["COMSPECExtension", "AdminControl.Poll", [_json]] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesBusy", false];
            if !(_r isEqualType "") exitWith {};
            // COMSPEC Link sans « AdminControl » : plus redemandé de la partie.
            if (_r isEqualTo "" || {(_r find "unknown") >= 0}) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesNo", true]; };
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {};
            private _list = missionNamespace getVariable ["COMSPEC_ATAK_AdminNotices", []];
            private _seen = missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesSeen", []];
            private _keep = missionNamespace getVariable ["COMSPEC_ATAK_NoticeKeep", {[]}];
            private _shown = ["COMSPEC_ATAK_NoticeSeen"] call _keep;
            private _acked = ["COMSPEC_ATAK_NoticeAck"] call _keep;
            private _new = [];
            private _again = [];
            {
                (_x splitString toString [9]) params [["_id", ""], ["_kind", "message"], ["_level", "info"], ["_title", ""], ["_body", ""], ["_ack", "1"], ["_meta", ""]];
                // Ligne d'Athena vérifiée avant usage : identifiant numérique attendu, sinon ignorée avec une ligne [WARN].
                if (_id isEqualTo "" || {(parseNumber _id) < 1}) then {
                    ["WARN", "ATHENA", format ["Avis illisible ignoré : %1", _x select [0, 60]]] call comspec_atak_native_fnc_log;
                    continue;
                };
                if (_noPhone && {_kind isNotEqualTo "repair_data"}) then { continue; };
                _title = _title select [0, 160];
                _body = _body select [0, 2000];
                // Réglages du CR de fin d'opération (ligne « debrief_cfg », redonnée à chaque relève) : sans alerte.
                if (_kind isEqualTo "debrief_cfg") then {
                    if (_id isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_DbfCfgId", ""])) then {
                        missionNamespace setVariable ["COMSPEC_ATAK_DbfCfgId", _id];
                        ["cfg", [_id, _kind, _level, _title, _body, false, "", _meta]] call comspec_atak_native_fnc_debrief;
                    };
                    continue;
                };
                // Avis déjà validé par le joueur : jamais réaffiché, le COMPRIS repart vers Athena.
                if (_id isNotEqualTo "" && {_id in _acked}) then { _again pushBackUnique _id; continue };
                // « Sur mon téléphone » : compte rendu ou CR fin d'op rempli sur le vrai téléphone. Envoyé par cet ATAK
                // comme s'il avait été rempli ici (image suivante, hors de cette boucle), sans alerte. Athena note « reçu »
                // ici (delivered) et « envoyé » (acked) seulement après l'envoi : avant, la page web affichait « Envoyé sur
                // votre ATAK » alors que la fiche pouvait être refusée ou perdue. Fiche refusée (gardée en brouillon) : reste
                // « reçu », reproposée à la partie suivante tant que l'avis vit (2 h).
                // Demande de réparation (site ou app Admin) : appliquée une fois, sans bandeau, validée aussitôt.
                if (_kind isEqualTo "repair_data") then {
                    if !(_id in _seen) then {
                        _seen pushBack _id;
                        ["COMSPEC_ATAK_NoticeAck", _id] call _keep;
                        _again pushBackUnique _id;
                        [{ ["repair", "demande de la gestion"] call comspec_atak_native_fnc_deviceGuard; }] call comspec_atak_native_fnc_nextFrame;
                    };
                    continue;
                };
                if (_kind in ["mobile_report", "mobile_debrief"]) then {
                    if (_id isNotEqualTo "" && {!(_id in _seen)}) then {
                        _seen pushBack _id;
                        _new pushBack _id;
                        [{
                            params ["_kind", "_body", "_id"];
                            private _fnKeep = missionNamespace getVariable ["COMSPEC_ATAK_NoticeKeep", {[]}];
                            missionNamespace setVariable ["COMSPEC_ATAK_MobileSent", false];
                            if (_kind isEqualTo "mobile_report") then { ["mobile", _body] call comspec_atak_native_fnc_reportAction; } else { ["mobile", _body] call comspec_atak_native_fnc_debrief; };
                            if !(missionNamespace getVariable ["COMSPEC_ATAK_MobileSent", false]) exitWith {};
                            ["COMSPEC_ATAK_NoticeAck", _id] call _fnKeep;
                            [_id] spawn { params ["_id"]; ["COMSPECExtension", "AdminControl.Ack", [_id, "acked", getPlayerUID player]] call comspec_atak_native_fnc_extAwait; };
                        }, [_kind, _body, _id]] call comspec_atak_native_fnc_nextFrame;
                    };
                    continue;
                };
                if (_id isNotEqualTo "" && {!(_id in _seen)}) then {
                    private _row = [_id, _kind, _level, _title, _body, _ack isEqualTo "1", (["now"] call comspec_atak_native_fnc_clockFmt), _meta];
                    // CR de fin d'opération imposé par le site : la fiche s'ouvre dans l'app CR fin d'op, validée à la remise.
                    if (_kind isEqualTo "debrief") then {
                        _seen pushBack _id;
                        _new pushBack _id;
                        ["site", _row] call comspec_atak_native_fnc_debrief;
                        continue;
                    };
                    _seen pushBack _id;
                    _new pushBack _id;
                    if (_ack isEqualTo "1") then { _list pushBack _row; };
                    // Déjà affiché dans une partie précédente : repris dans l'app Alertes, sans rejouer le bandeau.
                    if (_id in _shown) then {
                        ["state", _row] call comspec_atak_native_fnc_adminNotices;
                    } else {
                        ["COMSPEC_ATAK_NoticeSeen", _id] call _keep;
                        ["show", _row] call comspec_atak_native_fnc_adminNotices;
                    };
                };
            } forEach ((_r select [3]) splitString toString [10]);
            if (_again isNotEqualTo []) then {
                ["COMSPECExtension", "AdminControl.Ack", [_again joinString ",", "acked", getPlayerUID player]] call comspec_atak_native_fnc_extAwait;
            };
            while { (count _list) > 10 } do { _list deleteAt 0; };
            missionNamespace setVariable ["COMSPEC_ATAK_AdminNotices", _list];
            missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesSeen", _seen];
            if (_new isNotEqualTo []) then {
                ["COMSPECExtension", "AdminControl.Ack", [_new joinString ",", "delivered", getPlayerUID player]] call comspec_atak_native_fnc_extAwait;
            };
        };
    };
    case "style": {
        _arg params ["", ["_kind", "message"], ["_level", "info"], ["_title", ""], ["_body", ""]];
        switch (_kind) do {
            case "compromise": {
                private _cap = (_title find "aptur") >= 0;
                [_title, ["Liaison de données coupée : certificats révoqués, terminal exclu du réseau Athena.", "Terminal mis en quarantaine par Athena : liaison de données coupée."] select _cap, [0.62, 0.07, 0.06, 0.94], "ERROR"]
            };
            case "compromise_cleared": { [_title, "Votre ATAK est de nouveau sous contrôle.", [0.10, 0.45, 0.24, 0.94], "SUCCESS"] };
            case "reset": { [_title, "Vos données ATAK ont été réinitialisées puis régénérées : l'ATAK se réenregistre auprès d'Athena.", [0.70, 0.40, 0.04, 0.94], "WARNING"] };
            case "certificate": { [_title, "Le certificat de votre ATAK a changé.", [0.70, 0.40, 0.04, 0.94], "WARNING"] };
            case "phone": { [_title, "Votre téléphone a reçu un nouveau numéro.", [0.12, 0.32, 0.62, 0.94], "INFO"] };
            case "device_replaced": { [_title, "Votre ancien ATAK reste exclu. Ce téléphone reçoit un nouvel identifiant et retrouve l'accès tout seul.", [0.70, 0.40, 0.04, 0.94], "WARNING"] };
            default {
                private _lv = (["info", "warning", "critical"] find _level) max 0;
                [_title, _body, [[0.12, 0.32, 0.62, 0.94], [0.70, 0.40, 0.04, 0.94], [0.62, 0.07, 0.06, 0.94]] select _lv, ["INFO", "WARNING", "ERROR"] select _lv]
            };
        }
    };
    case "state";
    case "show": {
        if (!hasInterface) exitWith {};
        _arg params [["_id", ""], ["_kind", "message"], "", ["_title", ""], ["_body", ""], "", "", ["_meta", ""]];
        (["style", _arg] call comspec_atak_native_fnc_adminNotices) params ["_t", "_msg", "_rgba", "_type"];
        // Compromission déclarée (manuelle) : mode « terminal compromis » ; levée ou certificat neuf : retour à la normale.
        private _m = ["meta", _meta] call comspec_atak_native_fnc_compromiseView;
        private _from = switch (_m getOrDefault ["source", "admin"]) do { case "gestion": { "Gestion de la communauté" }; case "web": { "Poste de commandement" }; default { "Administration ATHENA" }; };
        switch (_kind) do {
            case "device_replaced": {
                // L'identifiant change seulement si l'ordre vise bien le terminal actif de ce poste (fn_compromise « newDevice »).
                if (_act isEqualTo "show") then { ["newDevice", ["N" + _id, "remplacement décidé par la gestion", _m getOrDefault ["terminal", "?"]]] call comspec_atak_native_fnc_compromise; };
            };
            case "compromise": {
                // Sans terminal désigné, l'avis ne peut pas verrouiller ce téléphone : c'est peut-être celui d'un autre.
                if ((_m getOrDefault ["terminal", ""]) isEqualTo "") exitWith {
                    ["WARN", "CAPTURE", format ["Avis de compromission %1 sans terminal : téléphone non verrouillé", _id]] call comspec_atak_native_fnc_log;
                };
                ["set", createHashMapFromArray [["origin", "manual"], ["source", _m getOrDefault ["source", "admin"]],
                    ["state", _m getOrDefault ["state", ["compromised", "captured"] select ((_title find "aptur") >= 0)]],
                    ["reason", _body], ["by", _m getOrDefault ["by", _from]], ["revoked", _m getOrDefault ["revoked", "0"]],
                    ["ref", _m getOrDefault ["ref", "N" + _id]], ["terminal", _m getOrDefault ["terminal", ""]], ["match", "terminal"]]] call comspec_atak_native_fnc_compromiseView;
            };
            case "compromise_cleared": { ["clear"] call comspec_atak_native_fnc_compromiseView; };
            case "certificate": { if ((_title find "évoqu") < 0) then { ["clear"] call comspec_atak_native_fnc_compromiseView; }; };
        };
        // « state » : avis déjà vu la partie précédente, son effet est repris sans rejouer le bandeau.
        if (_act isEqualTo "state") exitWith { call _render; };
        // Détail donné par Athena (motif, référence, numéro) sous le texte de l'avis.
        private _detail = [_msg, format ["%1 · %2", _msg, _body]] select (_kind isNotEqualTo "message" && {_body isNotEqualTo ""});
        [toUpper _t, _detail, _from, _rgba, 14] call comspec_atak_native_fnc_fullAlert;
        [_type, format ["%1 : %2", _t, _detail], 12, 95, "ALERTS"] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "ack": {
        ["COMSPEC_ATAK_NoticeAck", _arg] call _keep;
        private _i = _list findIf { (_x select 0) isEqualTo _arg };
        if (_i < 0) exitWith {};
        _list deleteAt _i;
        missionNamespace setVariable ["COMSPEC_ATAK_AdminNotices", _list];
        call _render;
        [_arg] spawn { params ["_id"]; ["COMSPECExtension", "AdminControl.Ack", [_id, "acked", getPlayerUID player]] call comspec_atak_native_fnc_extAwait; };
    };
    case "list": { private _acked = ["COMSPEC_ATAK_NoticeAck"] call _keep; private _c = +(_list select { !((_x select 0) in _acked) }); reverse _c; _c };
};
