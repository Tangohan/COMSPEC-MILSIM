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

switch (true) do {
    case (_cmd isEqualTo "close"): {
        closeDialog 0;
    };
    case (_cmd isEqualTo "classic"): {
        closeDialog 0;
        [] spawn {
            uiSleep 0.05;
            createDialog "COMSPEC_Device_Dialog";
        };
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
        closeDialog 0;
        [_view] spawn {
            params ["_view"];
            uiSleep 0.08;
            switch (_view) do {
                case "chat": { createDialog "COMSPEC_Chat_Dialog"; };
                case "cas": { [] call comspec_overwatch_connect_fnc_openCASDialog; };
                case "briefing": { [] call comspec_overwatch_connect_fnc_openBriefingBoard; };
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
        // radio:monitor|<channel>|<radioId>
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
        // radio:focus|<callsign>
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
        // marker:place|<wx>|<wy> — position monde déjà résolue côté JS (inverse de plotPositions).
        private _rest = _cmd select [12, (count _cmd) - 12];
        if ((_rest select [0, 1]) isEqualTo "|") then { _rest = _rest select [1, (count _rest) - 1]; };
        private _parts = _rest splitString "|";
        if (count _parts >= 2) then {
            private _wx = parseNumber (_parts select 0);
            private _wy = parseNumber (_parts select 1);
            [_wx, _wy] call comspec_overwatch_connect_fnc_placeMarkerFromTablet;
        };
    };
    default {
        _handled = true;
    };
};

_handled
