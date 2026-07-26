/*
    Ouvre la vue Téléphone de la tablette et injecte le code de pairage.
    Params: [_forceOpen] — false = rafraîchir le code sans rouvrir (déjà sur la tablette).
*/
params [["_forceOpen", true, [true]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (_forceOpen) then {
    ["phone"] call comspec_overwatch_connect_fnc_openTabletView;
};

0 spawn {
    private _t = diag_tickTime + 5;
    waitUntil {
        (!isNull (findDisplay 9974) && {missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]})
        || {diag_tickTime > _t}
    };
    if (isNull (findDisplay 9974)) exitWith {};

    private _info = [] call comspec_overwatch_connect_fnc_getPhoneConnectInfo;
    private _disp = findDisplay 9974;
    private _ctrl = _disp displayCtrl 9401;
    if (isNull _ctrl) exitWith {};

    if ((count _info) < 4) then {
        private _err = missionNamespace getVariable ["COMSPEC_PhoneConnectLastError", "Connexion téléphone indisponible."];
        private _safe = [_err] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        _ctrl ctrlWebBrowserAction [
            "ExecJS",
            format ["if(window.COMSPEC_setPhoneInfo){window.COMSPEC_setPhoneInfo({ok:false,error:'%1'});}", _safe]
        ];
    } else {
        _info params ["_token", "_code", "_connectUrl", "_qrImageUrl", "_expiresAt"];
        private _safeCode = [_code] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        private _safeExp = [str _expiresAt] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        private _safeQr = [_qrImageUrl] call comspec_overwatch_connect_fnc_webBrowserJsEscape;
        _ctrl ctrlWebBrowserAction [
            "ExecJS",
            format [
                "if(window.COMSPEC_setPhoneInfo){window.COMSPEC_setPhoneInfo({ok:true,code:'%1',expires:'%2',qrUrl:'%3'});}",
                _safeCode,
                _safeExp,
                _safeQr
            ]
        ];
    };
};
