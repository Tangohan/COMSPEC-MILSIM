/*
    Bridge JS → SQF via A3API.SendAlert / alert().
    Protocole : "COMSPEC|<commande>"
      view:<nom> — bascule d’écran DANS la tablette (plus de dialog annexe)
      open:athena | open:system — portail / navigateur OS
      chat:send|<texte>
      order:status|<id>|<ACK|EXEC|FAILED>
      callsign:set|<indicatif>|<role>
      action:ping|medical|tactical|forcesync|quiet|recon|laser
      refresh | close | classic | toggle:quiet
      radio:monitor|… | radio:focus|…
      marker:place|… | map:show|… | map:hide|… | map:names|…
*/
params ["_ctrl", "_isConfirmDialog", "_message"];

private _msg = _message;
if (!(_msg isEqualType "")) then { _msg = str _msg; };

private _handled = true;

if ((_msg select [0, 8]) != "COMSPEC|") exitWith {
    true
};

private _cmd = _msg select [8, (count _msg) - 8];

private _fnc_refresh = {
    if (!isNull _ctrl) then {
        missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "local"];
        [_ctrl] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
    };
};

switch (true) do {
    case (_cmd isEqualTo "close"): {
        private _disp = findDisplay 9974;
        if (!isNull _disp) then {
            _disp closeDisplay 1;
        } else {
            closeDialog 0;
        };
    };
    case (_cmd isEqualTo "classic"): {
        if (!(missionNamespace getVariable ["comspec_overwatch_classic_tablet_enabled", false])) then {
            ["COMSPEC_Info", ["Vue classique temporairement désactivée — restez sur la tablette Athena."]] call comspec_overwatch_connect_fnc_showNotification;
        } else {
            [] call comspec_overwatch_connect_fnc_openClassicTablet;
        };
    };
    case (_cmd isEqualTo "refresh"): {
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "toggle:quiet"): {
        private _cur = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
        private _next = !_cur;
        missionNamespace setVariable ["comspec_overwatch_quiet_mode", _next];
        if (!isNil "cba_settings_fnc_set") then {
            ["comspec_overwatch_quiet_mode", _next, 2, "client"] call cba_settings_fnc_set;
        };
        private _label = if (_next) then {
            "Mode discret activé — alertes uniquement dans la tablette."
        } else {
            "Mode discret désactivé — alertes de nouveau visibles en jeu."
        };
        [_label, "system", "info"] call comspec_overwatch_connect_fnc_announce;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "open:athena"): {
        [_ctrl] call comspec_overwatch_connect_fnc_webBrowserOpenAthena;
    };
    case (_cmd isEqualTo "open:system"): {
        [] call comspec_overwatch_connect_fnc_webBrowserOpenSystem;
    };
    // Compat : open:X → view:X (plus de fermeture tablette)
    case ((_cmd select [0, 5]) isEqualTo "open:"): {
        private _view = _cmd select [5, (count _cmd) - 5];
        if (_view isEqualTo "hub") then { _view = "apps"; };
        if (_view isEqualTo "athena") then {
            [_ctrl] call comspec_overwatch_connect_fnc_webBrowserOpenAthena;
        } else {
            if (!isNull _ctrl) then {
                private _safe = [_view] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
                _ctrl ctrlWebBrowserAction [
                    "ExecJS",
                    format ["if(window.COMSPEC_setView){window.COMSPEC_setView('%1');}", _safe]
                ];
                if (_view isEqualTo "bft") then {
                    [] call comspec_overwatch_connect_fnc_webBrowserMapShow;
                } else {
                    [_view] call comspec_overwatch_connect_fnc_webBrowserMapHide;
                };
            } else {
                [_view] call comspec_overwatch_connect_fnc_openTabletView;
            };
        };
    };
    case ((_cmd select [0, 5]) isEqualTo "view:"): {
        private _view = _cmd select [5, (count _cmd) - 5];
        if (_view isEqualTo "hub") then { _view = "apps"; };
        if (!isNull _ctrl) then {
            private _safe = [_view] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
            _ctrl ctrlWebBrowserAction [
                "ExecJS",
                format ["if(window.COMSPEC_setView){window.COMSPEC_setView('%1');}", _safe]
            ];
            if (_view isEqualTo "bft") then {
                [] call comspec_overwatch_connect_fnc_webBrowserMapShow;
            } else {
                [_view] call comspec_overwatch_connect_fnc_webBrowserMapHide;
            };
        } else {
            [_view] call comspec_overwatch_connect_fnc_openTabletView;
        };
    };
    case ((_cmd select [0, 10]) isEqualTo "chat:send|"): {
        private _text = _cmd select [10, (count _cmd) - 10];
        [_text] call comspec_overwatch_connect_fnc_tabletChatSend;
        call _fnc_refresh;
    };
    case ((_cmd select [0, 13]) isEqualTo "order:status|"): {
        private _rest = _cmd select [13, (count _cmd) - 13];
        private _parts = _rest splitString "|";
        if ((count _parts) >= 2) then {
            private _oid = _parts select 0;
            private _st = _parts select 1;
            private _ok = [_oid, _st, ""] call comspec_overwatch_connect_fnc_updateOrderStatus;
            if (_ok) then {
                ["COMSPEC_Info", ["Statut d’ordre mis à jour."]] call comspec_overwatch_connect_fnc_showNotification;
            } else {
                ["COMSPEC_Warning", ["Impossible de mettre à jour cet ordre."]] call comspec_overwatch_connect_fnc_showNotification;
            };
        };
        call _fnc_refresh;
    };
    case ((_cmd select [0, 14]) isEqualTo "order:compose|"): {
        private _pref = _cmd select [14, (count _cmd) - 14];
        closeDialog 0;
        [_pref] spawn {
            params ["_k"];
            uiSleep 0.05;
            [_k] call comspec_overwatch_connect_fnc_orderComposeShow;
        };
    };
    case ((_cmd select [0, 13]) isEqualTo "callsign:set|"): {
        private _rest = _cmd select [13, (count _cmd) - 13];
        private _parts = _rest splitString "|";
        private _cs = if ((count _parts) >= 1) then { trim (_parts select 0) } else { "" };
        private _role = if ((count _parts) >= 2) then { trim (_parts select 1) } else { "" };
        if (_cs != "") then {
            [_cs, true, "tablet"] call comspec_overwatch_connect_fnc_setCallsign;
        };
        if (_role != "") then {
            [_role, true] call comspec_overwatch_connect_fnc_setUnitRole;
        };
        ["COMSPEC_Info", ["Profil mis à jour."]] call comspec_overwatch_connect_fnc_showNotification;
        call _fnc_refresh;
    };
    case ((_cmd select [0, 14]) isEqualTo "tactical:send|"): {
        private _rest = _cmd select [14, (count _cmd) - 14];
        private _parts = _rest splitString "|";
        private _kind = if ((count _parts) >= 1) then { _parts select 0 } else { "TIC" };
        private _body = if ((count _parts) >= 2) then {
            private _tail = +_parts;
            _tail deleteAt 0;
            _tail joinString "|"
        } else { "" };
        private _kindKey = toUpper (trim _kind);
        if (_kindKey isEqualTo "FRAGO"
            && { missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true] }
            && { !isNil "comspec_overwatch_connect_fnc_orderComposeShow" }
        ) then {
            closeDialog 0;
            0 spawn { ["FRAGO"] call comspec_overwatch_connect_fnc_orderComposeShow; };
        } else {
            [_kind, _body, getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;
            call _fnc_refresh;
        };
    };
    case ((_cmd select [0, 12]) isEqualTo "salute:send|"): {
        private _rest = _cmd select [12, (count _cmd) - 12];
        private _parts = _rest splitString "|";
        private _labels = ["S", "A", "L", "U", "T", "E"];
        private _bodyParts = [];
        {
            private _val = if ((count _parts) > _forEachIndex) then { trim (_parts select _forEachIndex) } else { "" };
            if (_val isNotEqualTo "") then {
                _bodyParts pushBack format ["%1=%2", _labels select _forEachIndex, _val];
            };
        } forEach _labels;
        if ((count _bodyParts) < 1) then {
            ["Renseignez au moins un champ SALUTE.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        } else {
            ["SALUTE", _bodyParts joinString "|", getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;
        };
        call _fnc_refresh;
    };
    case ((_cmd select [0, 13]) isEqualTo "account:link|"): {
        private _rest = _cmd select [13, (count _cmd) - 13];
        private _parts = _rest splitString "|";
        private _url = if ((count _parts) >= 1) then { trim (_parts select 0) } else { "" };
        private _code = if ((count _parts) >= 2) then { toUpper (trim (_parts select 1)) } else { "" };
        private _steam = if ((count _parts) >= 3) then { trim (_parts select 2) } else { "" };
        if (_url isEqualTo "") then {
            _url = trim (missionNamespace getVariable ["comspec_overwatch_api_url", ""]);
            if (_url isEqualTo "") then {
                _url = trim (profileNamespace getVariable ["comspec_overwatch_saved_api_url", "https://athena.ttrd.fr/public"]);
            };
            if (_url isEqualTo "") then { _url = "https://athena.ttrd.fr/public"; };
        };
        [_url, _code, _steam] spawn comspec_overwatch_connect_fnc_accountLinkSubmit;
    };
    case ((_cmd select [0, 9]) isEqualTo "cas:send|"): {
        private _rest = _cmd select [9, (count _cmd) - 9];
        private _parts = _rest splitString "|";
        private _casType = if ((count _parts) >= 1) then { trim (_parts select 0) } else { "CAS" };
        private _note = if ((count _parts) >= 2) then {
            private _tail = +_parts;
            _tail deleteAt 0;
            _tail joinString "|"
        } else { "" };
        private _g = group player;
        private _hasGroupLeader = !isNull leader _g;
        private _targetName = if (_hasGroupLeader) then { groupId _g } else { name player };
        private _targetType = if (_hasGroupLeader) then { "group" } else { "solo" };
        private _body = if (_note isEqualTo "") then {
            format ["Demande %1 — grille %2", _casType, mapGridPosition (getPos player)]
        } else {
            format ["%1 — %2 — grille %3", _casType, _note, mapGridPosition (getPos player)]
        };
        ["CAS", _targetName, _body, "URGENT", "", _targetType] call comspec_overwatch_connect_fnc_issueOrder;
        ["Demande d’appui aérien transmise.", "order", "info"] call comspec_overwatch_connect_fnc_announce;
        call _fnc_refresh;
    };
    case ((_cmd select [0, 15]) isEqualTo "manifest:send|"): {
        private _rest = _cmd select [15, (count _cmd) - 15];
        private _parts = _rest splitString "|";
        private _laser = if ((count _parts) >= 1) then { trim (_parts select 0) } else { "1688" };
        private _auth = if ((count _parts) >= 2) then { trim (_parts select 1) } else { "" };
        private _pax = if ((count _parts) >= 3) then { trim (_parts select 2) } else { "1" };
        missionNamespace setVariable ["COMSPEC_TabletManifestLaser", _laser, false];
        missionNamespace setVariable ["COMSPEC_TabletManifestAuth", _auth, false];
        missionNamespace setVariable ["COMSPEC_TabletManifestPax", _pax, false];
        [] call comspec_overwatch_connect_fnc_tabletFlightManifestSend;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "phone:refresh"): {
        [false] call comspec_overwatch_connect_fnc_phoneConnectShow;
    };
    case (_cmd isEqualTo "briefing:board"): {
        [] call comspec_overwatch_connect_fnc_openBriefingBoard;
    };
    case (_cmd isEqualTo "briefing:community"): {
        private _url = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
        if (_url isEqualTo "") then {
            [] call comspec_overwatch_connect_fnc_getBriefingSlides;
            _url = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
        };
        if (_url isEqualTo "") then {
            ["COMSPEC_Warning", ["Aucun brief Google publié pour la communauté."]] call comspec_overwatch_connect_fnc_showNotification;
        } else {
            [_url, 0, true] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
        };
    };
    case ((_cmd select [0, 15]) isEqualTo "briefing:google|"): {
        private _url = _cmd select [15, (count _cmd) - 15];
        [_url, 0, true] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
    };
    case ((_cmd select [0, 7]) isEqualTo "action:"): {
        private _act = _cmd select [7, (count _cmd) - 7];
        switch (_act) do {
            case "ping": {
                [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
                ["Point d'intérêt transmis.", "ping", "info"] call comspec_overwatch_connect_fnc_announce;
            };
            case "medical": {
                private _state = [player] call comspec_overwatch_connect_fnc_getMedicalState;
                private _parts = _state splitString "|";
                private _health = if (count _parts >= 1) then { _parts select 0 } else { "stable" };
                private _blood = if (count _parts >= 2) then { _parts select 1 } else { "?" };
                private _hr = if (count _parts >= 4) then { _parts select 3 } else { "?" };
                private _status = switch (_health) do {
                    case "cardiac_arrest": { "Arrêt cardiaque" };
                    case "unconscious": { "Inconscient" };
                    case "wounded": { "Blessé" };
                    default { "Stable" };
                };
                [player, "CHAT", format ["WIA|%1|sang≈%2%%|FC=%3", _status, _blood, _hr], "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;
                ["Bilan de santé transmis.", "medical", "info"] call comspec_overwatch_connect_fnc_announce;
            };
            case "tactical": {
                if (!isNull _ctrl) then {
                    _ctrl ctrlWebBrowserAction ["ExecJS", "if(window.COMSPEC_setView){window.COMSPEC_setView('tactical');}"];
                    ["tactical"] call comspec_overwatch_connect_fnc_webBrowserMapHide;
                } else {
                    ["tactical"] call comspec_overwatch_connect_fnc_openTabletView;
                };
            };
            case "phone": {
                [false] call comspec_overwatch_connect_fnc_phoneConnectShow;
            };
            case "forcesync": {
                [] call comspec_overwatch_connect_fnc_forceSyncData;
            };
            case "quiet": {
                private _cur = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
                missionNamespace setVariable ["comspec_overwatch_quiet_mode", !_cur];
                if (!isNil "cba_settings_fnc_set") then {
                    ["comspec_overwatch_quiet_mode", !_cur, 2, "client"] call cba_settings_fnc_set;
                };
            };
            case "recon": {
                [] call comspec_overwatch_connect_fnc_captureReconImage;
            };
            case "laser": {
                [] call comspec_overwatch_connect_fnc_syncLaserCode;
            };
            default {};
        };
        call _fnc_refresh;
    };
    case ((_cmd select [0, 13]) isEqualTo "radio:monitor"): {
        private _rest = _cmd select [13, (count _cmd) - 13];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        private _parts = _rest splitString "|";
        private _ch = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
        private _rid = if ((count _parts) >= 2) then { _parts select 1 } else { "" };
        [_ch, _rid] call comspec_overwatch_connect_fnc_monitorRadioNet;
        call _fnc_refresh;
    };
    case ((_cmd select [0, 11]) isEqualTo "radio:focus"): {
        private _rest = _cmd select [11, (count _cmd) - 11];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        missionNamespace setVariable ["COMSPEC_RadioWatchFocusCs", _rest, false];
        missionNamespace setVariable ["COMSPEC_RadioWatchFocus", objNull, false];
        private _fresh = [] call comspec_overwatch_connect_fnc_scanRadioProximity;
        missionNamespace setVariable ["COMSPEC_RadioProximityList", _fresh, false];
        call _fnc_refresh;
    };
    case ((_cmd select [0, 12]) isEqualTo "marker:place"): {
        private _rest = _cmd select [12, (count _cmd) - 12];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        private _parts = _rest splitString "|";
        if (count _parts >= 2) then {
            private _wx = parseNumber (_parts select 0);
            private _wy = parseNumber (_parts select 1);
            private _mType = if ((count _parts) >= 3) then { _parts select 2 } else { "mil_dot" };
            private _mColor = if ((count _parts) >= 4) then { _parts select 3 } else { "ColorRed" };
            [_wx, _wy, _mType, _mColor] call comspec_overwatch_connect_fnc_placeMarkerFromTablet;
        };
    };
    case ((_cmd select [0, 9]) isEqualTo "map:show"): {
        private _rest = _cmd select [9, (count _cmd) - 9];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        private _z = if (_rest isEqualTo "") then { -1 } else { parseNumber _rest };
        [_z] call comspec_overwatch_connect_fnc_webBrowserMapShow;
    };
    case ((_cmd select [0, 9]) isEqualTo "map:hide"): {
        private _rest = _cmd select [9, (count _cmd) - 9];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        if (_rest isEqualTo "") then { _rest = "bft"; };
        [_rest] call comspec_overwatch_connect_fnc_webBrowserMapHide;
    };
    case ((_cmd select [0, 10]) isEqualTo "map:names"): {
        private _rest = _cmd select [10, (count _cmd) - 10];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        missionNamespace setVariable ["COMSPEC_WebBrowser_MapShowNames", (_rest isEqualTo "1") || {_rest isEqualTo "true"}];
    };
    default {
        _handled = true;
    };
};

_handled
