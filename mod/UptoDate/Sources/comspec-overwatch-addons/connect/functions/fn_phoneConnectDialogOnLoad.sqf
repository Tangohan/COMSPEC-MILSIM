/*

    Peuple le dialog COMSPEC_PhoneConnect_Dialog (idd 9971) :

    adresse mobile + code d’appariement au centre (pas de QR).

*/

params [["_display", displayNull]];

if (isNull _display) then {

    _display = uiNamespace getVariable ["COMSPEC_PhoneConnect_Display", displayNull];

};

if (isNull _display) then { _display = findDisplay 9971; };

if (isNull _display) exitWith {};



uiNamespace setVariable ["COMSPEC_PhoneConnect_Display", _display];



private _fnc_setCenter = {

    params ["_disp", "_html"];

    private _c = _disp displayCtrl 9026;

    if (!isNull _c) then {

        _c ctrlShow true;

        _c ctrlSetStructuredText parseText _html;

    };

    // Masquer toute zone QR résiduelle

    private _qr = _disp displayCtrl 9021;

    if (!isNull _qr) then { _qr ctrlShow false; };

};



[

    _display,

    "<t align='center' size='0.6' color='#8aa0b4'>Récupération de l’adresse et du code…</t>"

] call _fnc_setCenter;



[_display, _fnc_setCenter] spawn {

    params ["_disp", "_fnc_setCenter"];



    private _info = [] call comspec_overwatch_connect_fnc_getPhoneConnectInfo;

    if (isNull _disp) exitWith {};



    private _fmtOk = {

        params ["_url", "_code"];

        private _urlSafe = (_url splitString """" joinString "'");

        private _codeSafe = (_code splitString """" joinString "'");

        private _urlLine = if (_urlSafe isEqualTo "") then {

            "<t align='center' size='0.55' color='#ff8a7a'>Adresse non reçue — réessayez « Nouveau code ».</t>"

        } else {

            format [

                "<t align='center' size='0.5' color='#8ab89a'>ADRESSE MOBILE ATHENA</t><br/>" +

                "<t align='center' size='0.58' color='#c8e8ff'>%1</t>",

                _urlSafe

            ]

        };

        private _codeLine = if (_codeSafe isEqualTo "") then {

            "<br/><br/><t align='center' size='0.55' color='#ff8a7a'>Code indisponible</t>"

        } else {

            format [

                "<br/><br/><t align='center' size='0.5' color='#8ab89a'>CODE D’APPARIEMENT</t><br/>" +

                "<t align='center' size='1.15' font='RobotoCondensedBold' color='#ffffff'>%1</t>",

                _codeSafe

            ]

        };

        _urlLine + _codeLine +

        "<br/><br/><t align='center' size='0.45' color='#9ab0c0'>Saisissez ce code sur la page ouverte sur votre téléphone.</t>"

    };



    if ((count _info) < 4) exitWith {

        private _err = missionNamespace getVariable ["COMSPEC_PhoneConnectLastError", "Connexion téléphone indisponible."];

        private _cached = missionNamespace getVariable ["COMSPEC_PhoneConnectLastOk", []];

        if ((count _cached) >= 4) then {

            _cached params ["", "_cCode", "_cUrl"];

            private _html = [_cUrl, _cCode] call _fmtOk;

            _html = _html + format [

                "<br/><br/><t align='center' size='0.45' color='#ffd27a'>Nouveau code indisponible — valeurs précédentes.<br/>%1</t>",

                _err

            ];

            [_disp, _html] call _fnc_setCenter;

        } else {

            [_disp, format [

                "<t align='center' size='0.6' color='#ff8a7a'>%1</t>",

                _err

            ]] call _fnc_setCenter;

        };

    };



    _info params ["_token", "_code", "_connectUrl", "_qrImageUrl", "_expiresAt"];

    missionNamespace setVariable ["COMSPEC_PhoneConnectLastOk", [_token, _code, _connectUrl, _qrImageUrl, _expiresAt], false];



    // Miroir dans les contrôles cachés (compat autres scripts)

    private _codeCtrl = _disp displayCtrl 9022;

    private _urlCtrl = _disp displayCtrl 9023;

    if (!isNull _codeCtrl) then {

        _codeCtrl ctrlSetStructuredText parseText format [

            "<t align='center' size='0.95' font='RobotoCondensedBold' color='#ffffff'>%1</t>",

            _code

        ];

    };

    if (!isNull _urlCtrl) then {

        _urlCtrl ctrlSetStructuredText parseText format [

            "<t align='center' size='0.48' color='#c8e8ff'>%1</t>",

            _connectUrl

        ];

    };



    [_disp, [_connectUrl, _code] call _fmtOk] call _fnc_setCenter;

};


