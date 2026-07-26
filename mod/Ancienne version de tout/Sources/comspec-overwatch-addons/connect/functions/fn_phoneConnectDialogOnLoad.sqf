/*
    Peuple le dialog natif COMSPEC_PhoneConnect_Dialog (idd 9971) : code + QR téléchargé en
    local (un RscPicture ne charge pas d'URL distante — DownloadBriefingSlideImage télécharge
    l'image et retourne un chemin de fichier local, même mécanisme que les diapositives de
    briefing). Dialog natif, pas de navigateur intégré : reste "dans" ATAK Enhanced (ouvert en
    enfant de cTab_Android_dlg par fn_athena_showPhoneConnect.sqf), pas un système séparé.

    Appelée à l'onLoad du dialog ET par le bouton "New code" (ré-exécution directe, params [_display]).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_PhoneConnect_Display", _display];

private _codeCtrl = _display displayCtrl 9022;
private _urlCtrl = _display displayCtrl 9023;
private _qrCtrl = _display displayCtrl 9021;
private _fallbackCtrl = _display displayCtrl 9026;

if (!isNull _codeCtrl) then {
    _codeCtrl ctrlSetStructuredText parseText "<t align='center' size='1.25' font='RobotoCondensedBold' color='#ffffff'>——————</t>";
};
if (!isNull _fallbackCtrl) then {
    _fallbackCtrl ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#8aa0b4'>Chargement…</t>";
};
if (!isNull _qrCtrl) then { _qrCtrl ctrlShow false; };

[_display] spawn {
    params ["_disp"];

    private _info = [] call comspec_overwatch_connect_fnc_getPhoneConnectInfo;
    if (isNull _disp) exitWith {};

    private _codeCtrl = _disp displayCtrl 9022;
    private _urlCtrl = _disp displayCtrl 9023;
    private _qrCtrl = _disp displayCtrl 9021;
    private _fallbackCtrl = _disp displayCtrl 9026;

    if ((count _info) < 4) exitWith {
        private _err = missionNamespace getVariable ["COMSPEC_PhoneConnectLastError", "Connexion téléphone indisponible."];
        if (!isNull _codeCtrl) then {
            _codeCtrl ctrlSetStructuredText parseText "<t align='center' size='1.1' color='#ff8a7a'>Indisponible</t>";
        };
        if (!isNull _fallbackCtrl) then {
            _fallbackCtrl ctrlSetStructuredText parseText format ["<t align='center' size='0.55' color='#ff8a7a'>%1</t>", _err];
        };
        if (!isNull _qrCtrl) then { _qrCtrl ctrlShow false; };
    };

    _info params ["_token", "_code", "_connectUrl", "_qrImageUrl", "_expiresAt"];

    if (!isNull _codeCtrl) then {
        _codeCtrl ctrlSetStructuredText parseText format [
            "<t align='center' size='1.25' font='RobotoCondensedBold' color='#ffffff'>%1</t>", _code
        ];
    };
    if (!isNull _urlCtrl) then {
        _urlCtrl ctrlSetStructuredText parseText format [
            "<t align='center' size='0.45' color='#8ab89a'>%1</t>", _connectUrl
        ];
    };

    if (_qrImageUrl isEqualTo "" || {isNull _qrCtrl}) exitWith {
        if (!isNull _fallbackCtrl) then {
            _fallbackCtrl ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#b0c4d4'>Saisissez le code ci-dessus sur la page de connexion.</t>";
        };
    };

    private _res = ["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_qrImageUrl, "phoneqr"]]] call comspec_overwatch_connect_fnc_extResult;
    if (isNull _disp) exitWith {};

    if (_res isEqualType "" && {(_res select [0, 3]) == "OK|"}) then {
        private _path = _res select [3, (count _res) - 3];
        _qrCtrl ctrlSetText _path;
        _qrCtrl ctrlShow true;
        if (!isNull _fallbackCtrl) then { _fallbackCtrl ctrlSetStructuredText parseText ""; };
    } else {
        _qrCtrl ctrlShow false;
        if (!isNull _fallbackCtrl) then {
            _fallbackCtrl ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#b0c4d4'>QR indisponible pour le moment — utilisez le code ci-dessus.</t>";
        };
    };
};
