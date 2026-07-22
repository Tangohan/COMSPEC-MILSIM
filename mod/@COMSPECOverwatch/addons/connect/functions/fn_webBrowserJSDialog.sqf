/*
    Bridge JS → SQF via A3API.SendAlert / alert().
    Protocole : "COMSPEC|<commande>"
      open:chat|briefing|phone|account|orders|hub|cas|callsign|athena
      refresh | close | classic | toggle:quiet
      radio:monitor|<canal>|<radioId> | radio:focus|<indicatif>
      marker:place|<wx>|<wy> (double-clic sur la vue radar)
*/
params ["_ctrl", "_isConfirmDialog", "_message"];

private _msg = _message;
if (!(_msg isEqualType "")) then { _msg = str _msg; };

private _handled = true;

if ((_msg select [0, 8]) != "COMSPEC|") exitWith {
    true
};

private _cmd = _msg select [8, (count _msg) - 8];

// Ferme la tablette (idd 9974) puis attend sa destruction avant d’ouvrir un autre dialog.
private _fnc_afterTabletClosed = {
    params ["_code"];
    [_code] spawn {
        params ["_code"];
        private _disp = findDisplay 9974;
        if (!isNull _disp) then {
            _disp closeDisplay 1;
        } else {
            closeDialog 0;
        };
        private _t = diag_tickTime + 2;
        waitUntil {
            isNull (findDisplay 9974) || {diag_tickTime > _t}
        };
        uiSleep 0.05;
        call _code;
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
        [{
            if (isNull (findDisplay 9973)) then {
                createDialog "COMSPEC_Device_Dialog";
            };
        }] call _fnc_afterTabletClosed;
    };
    case (_cmd isEqualTo "refresh"): {
        if (!isNull _ctrl) then {
            missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "local"];
            [_ctrl] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
        };
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
        if (!isNull _ctrl) then {
            [_ctrl] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
        };
    };
    case (_cmd isEqualTo "open:athena"): {
        [_ctrl] call comspec_overwatch_connect_fnc_webBrowserOpenAthena;
    };
    case (_cmd isEqualTo "open:system"): {
        [] call comspec_overwatch_connect_fnc_webBrowserOpenSystem;
    };
    case ((_cmd select [0, 5]) isEqualTo "open:"): {
        private _view = _cmd select [5, (count _cmd) - 5];
        // Capture _view dans le spawn (évite race closeDialog trop rapide)
        [_view] spawn {
            params ["_view"];
            private _disp = findDisplay 9974;
            if (!isNull _disp) then {
                _disp closeDisplay 1;
            } else {
                closeDialog 0;
            };
            private _t = diag_tickTime + 2;
            waitUntil {
                isNull (findDisplay 9974) || {diag_tickTime > _t}
            };
            uiSleep 0.05;
            switch (_view) do {
                case "chat": { createDialog "COMSPEC_Chat_Dialog"; };
                case "cas": { [] call comspec_overwatch_connect_fnc_openCASDialog; };
                case "briefing": {
                    [] call comspec_overwatch_connect_fnc_openBriefingBoard;
                    if (isNull (findDisplay 9970)) then {
                        ["Impossible d’ouvrir le tableau de briefing.", "system", "warn", true] call comspec_overwatch_connect_fnc_announce;
                    };
                };
                case "phone": { [] call comspec_overwatch_connect_fnc_phoneConnectShow; };
                case "account": { [] call comspec_overwatch_connect_fnc_accountLinkShow; };
                case "callsign": { [] call comspec_overwatch_connect_fnc_callsignDialogShow; };
                case "orders": { [] call comspec_overwatch_connect_fnc_orderInboxShow; };
                case "hub": { [] call comspec_overwatch_connect_fnc_openHub; };
                case "manifest": { createDialog "COMSPEC_FlightManifest_Dialog"; };
                default {};
            };
        };
    };
    case ((_cmd select [0, 13]) isEqualTo "radio:monitor"): {
        private _rest = _cmd select [13, (count _cmd) - 13];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        private _parts = _rest splitString "|";
        private _ch = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
        private _rid = if ((count _parts) >= 2) then { _parts select 1 } else { "" };
        [_ch, _rid] call comspec_overwatch_connect_fnc_monitorRadioNet;
        if (!isNull _ctrl) then {
            [_ctrl] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
        };
    };
    case ((_cmd select [0, 11]) isEqualTo "radio:focus"): {
        private _rest = _cmd select [11, (count _cmd) - 11];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        missionNamespace setVariable ["COMSPEC_RadioWatchFocusCs", _rest, false];
        missionNamespace setVariable ["COMSPEC_RadioWatchFocus", objNull, false];
        private _fresh = [] call comspec_overwatch_connect_fnc_scanRadioProximity;
        missionNamespace setVariable ["COMSPEC_RadioProximityList", _fresh, false];
        if (!isNull _ctrl) then {
            [_ctrl] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
        };
    };
    case ((_cmd select [0, 12]) isEqualTo "marker:place"): {
        // marker:place|<wx>|<wy>[|<type>|<color>]
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
    default {
        _handled = true;
    };
};

_handled
