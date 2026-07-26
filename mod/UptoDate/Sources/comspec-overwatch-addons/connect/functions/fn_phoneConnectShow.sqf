/*

    Ouvre la liaison mobile (adresse + code).

    Par défaut : écran natif Athena / ATAK Enhanced.

    Params: [_forceOpen] — false = rafraîchir uniquement si la tablette legacy est déjà ouverte.

*/

params [["_forceOpen", true, [true]]];



if (!hasInterface) exitWith {};

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};



// Chemin principal : dialog natif sous ATAK Enhanced

if (

    _forceOpen

    || {isNull (findDisplay 9974)}

    || {missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]}

) exitWith {

    if (_forceOpen || {missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]}) then {

        ["liaison"] call comspec_overwatch_connect_fnc_openAthenaFeature;

    } else {

        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect") then {

            [] call comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect;

        };

    };

};



// Legacy : injecte le code dans la tablette Chromium déjà ouverte

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

        private _safeUrl = [_connectUrl] call comspec_overwatch_connect_fnc_webBrowserJsEscape;

        _ctrl ctrlWebBrowserAction [

            "ExecJS",

            format [

                "if(window.COMSPEC_setPhoneInfo){window.COMSPEC_setPhoneInfo({ok:true,code:'%1',expires:'%2',qrUrl:'%3',url:'%4'});}",

                _safeCode,

                _safeExp,

                _safeQr,

                _safeUrl

            ]

        ];

    };

};


