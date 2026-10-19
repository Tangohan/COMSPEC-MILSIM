/*
    Avis de l'administration ATHENA sur l'ATAK du joueur (site : /admin/parc-atak, poste de commandement, back-office) :
    compromission déclarée ou levée, données ATAK réinitialisées, certificat révoqué, rétabli ou régénéré, nouveau numéro,
    message imposé. Récupérés par COMSPEC Link (« AdminControl.Poll », toutes les 30 s une fois relié à Athena).
    Chaque nouvel avis : alerte de la couleur du genre dans l'écran du téléphone (jamais sur l'écran de jeu), notification, et carte en tête de l'app Alertes
    jusqu'à ce que le joueur appuie sur COMPRIS (validé sur Athena par « AdminControl.Ack »).
    Params : [action, argument]
      "check"        client : demande les avis à Athena (toutes les 30 s au plus)
      "show" ligne   alerte, notification et vibration pour un avis
      "ack" id       COMPRIS : retire la carte et prévient Athena
      "list"         avis non validés, plus récents d'abord
      "style" ligne  [titre, texte, couleur RGBA, type de notification] d'un avis
    Ligne : [id, genre, niveau, titre, texte, à valider, heure]
*/
params [["_act", ""], ["_arg", []]];
private _list = missionNamespace getVariable ["COMSPEC_ATAK_AdminNotices", []];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "check": {
        if (!hasInterface) exitWith {};
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesAt", -1e9]) < 30) exitWith {};
        if !([] call comspec_atak_native_fnc_bridge) exitWith {};
        // L'avis attend que le joueur ait un téléphone sur lui.
        if (isNull player || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {};
        if (isNil "comspec_overwatch_connect_fnc_hashMapToJson") exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesNo", false]) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesBusy", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesAt", diag_tickTime];
        private _tuid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
        if !(_tuid isEqualType "") then { _tuid = ""; };
        private _json = [createHashMapFromArray [["player_uid", getPlayerUID player], ["terminal_uid", _tuid]]] call comspec_overwatch_connect_fnc_hashMapToJson;
        [_json] spawn {
            params ["_json"];
            private _r = ["COMSPECExtension", "AdminControl.Poll", [_json]] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesBusy", false];
            if !(_r isEqualType "") exitWith {};
            // COMSPEC Link sans « AdminControl » : plus redemandé de la partie.
            if (_r isEqualTo "" || {(_r find "unknown") >= 0}) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_AdminNoticesNo", true]; };
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {};
            private _list = missionNamespace getVariable ["COMSPEC_ATAK_AdminNotices", []];
            private _seen = missionNamespace getVariable ["COMSPEC_ATAK_AdminNoticesSeen", []];
            private _new = [];
            {
                (_x splitString toString [9]) params [["_id", ""], ["_kind", "message"], ["_level", "info"], ["_title", ""], ["_body", ""], ["_ack", "1"]];
                if (_id isNotEqualTo "" && {!(_id in _seen)}) then {
                    private _row = [_id, _kind, _level, _title, _body, _ack isEqualTo "1", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
                    _seen pushBack _id;
                    _new pushBack _id;
                    if (_ack isEqualTo "1") then { _list pushBack _row; };
                    ["show", _row] call comspec_atak_native_fnc_adminNotices;
                };
            } forEach ((_r select [3]) splitString toString [10]);
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
            case "compromise": { [_title, "L'administration ATHENA a déclaré votre ATAK compromis : ses certificats peuvent être révoqués.", [0.62, 0.07, 0.06, 0.94], "ERROR"] };
            case "compromise_cleared": { [_title, "Votre ATAK est de nouveau sous contrôle.", [0.10, 0.45, 0.24, 0.94], "SUCCESS"] };
            case "reset": { [_title, "Vos données ATAK ont été réinitialisées puis régénérées : l'ATAK se réenregistre auprès d'Athena.", [0.70, 0.40, 0.04, 0.94], "WARNING"] };
            case "certificate": { [_title, "Le certificat de votre ATAK a changé.", [0.70, 0.40, 0.04, 0.94], "WARNING"] };
            case "phone": { [_title, "Votre téléphone a reçu un nouveau numéro.", [0.12, 0.32, 0.62, 0.94], "INFO"] };
            default {
                private _lv = (["info", "warning", "critical"] find _level) max 0;
                [_title, _body, [[0.12, 0.32, 0.62, 0.94], [0.70, 0.40, 0.04, 0.94], [0.62, 0.07, 0.06, 0.94]] select _lv, ["INFO", "WARNING", "ERROR"] select _lv]
            };
        }
    };
    case "show": {
        if (!hasInterface) exitWith {};
        _arg params ["", ["_kind", "message"], "", ["_title", ""], ["_body", ""]];
        (["style", _arg] call comspec_atak_native_fnc_adminNotices) params ["_t", "_msg", "_rgba", "_type"];
        // Détail donné par Athena (motif, référence, numéro) sous le texte de l'avis.
        private _detail = [_msg, format ["%1 · %2", _msg, _body]] select (_kind isNotEqualTo "message" && {_body isNotEqualTo ""});
        [toUpper _t, _detail, "Administration ATHENA", _rgba, 14] call comspec_atak_native_fnc_fullAlert;
        [_type, format ["%1 : %2", _t, _detail], 12, 95, "ALERTS"] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "ack": {
        private _i = _list findIf { (_x select 0) isEqualTo _arg };
        if (_i < 0) exitWith {};
        _list deleteAt _i;
        missionNamespace setVariable ["COMSPEC_ATAK_AdminNotices", _list];
        call _render;
        [_arg] spawn { params ["_id"]; ["COMSPECExtension", "AdminControl.Ack", [_id, "acked", getPlayerUID player]] call comspec_atak_native_fnc_extAwait; };
    };
    case "list": { private _c = +_list; reverse _c; _c };
};
