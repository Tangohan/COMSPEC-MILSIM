/*
    Ouvre (ou rafraîchit) le dialog "Connexion téléphone" : génère un pairing, télécharge le QR
    en réutilisant downloadBriefingSlide (même mécanisme de cache que les diapositives), et
    affiche TOUJOURS le code court en grand — même si l’image QR échoue.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _info = [] call comspec_overwatch_connect_fnc_getPhoneConnectInfo;
if (count _info < 4) exitWith {
    private _err = missionNamespace getVariable ["COMSPEC_PhoneConnectLastError", ""];
    if (_err == "") then {
        _err = "Connexion téléphone indisponible pour le moment (réseau ou plateforme).";
    };
    ["COMSPEC_Warning", [_err]] call comspec_overwatch_connect_fnc_showNotification;
    if (!(missionNamespace getVariable ["comspec_overwatch_quiet_mode", false])) then {
        systemChat ("[COMSPEC] " + _err);
    };
};

_info params ["_token", "_code", "_connectUrl", "_qrImageUrl", "_expiresAt"];

if (isNull (findDisplay 9971)) then { createDialog "COMSPEC_PhoneConnect_Dialog"; };
private _display = findDisplay 9971;
if (isNull _display) exitWith {};

// Code pairing : toujours visible, grand, indépendamment du QR (pas d’URL dans ce champ).
private _ctrlCode = _display displayCtrl 9022;
if (!isNull _ctrlCode) then {
    _ctrlCode ctrlSetStructuredText parseText format [
        "<t align='center' size='2.0' font='RobotoCondensedBold' color='#7dffb3'>%1</t>",
        _code
    ];
    _ctrlCode ctrlSetTextColor [0.49, 1, 0.7, 1];
};

private _ctrlUrl = _display displayCtrl 9023;
if (!isNull _ctrlUrl) then {
    // Ne pas injecter l’URL brute dans parseText (caractères spéciaux) — libellé métier seulement.
    private _urlLabel = if (_connectUrl != "") then {
        "<t align='center' size='0.5' color='#8aa0b4'>Ou ouvrez la page de connexion Athena sur votre téléphone.</t>"
    } else {
        "<t align='center' size='0.5' color='#8aa0b4'>Saisissez le code sur la page de connexion Athena.</t>"
    };
    _ctrlUrl ctrlSetStructuredText parseText _urlLabel;
};

private _ctrlPic = _display displayCtrl 9021;
private _ctrlFallback = _display displayCtrl 9026;
if (!isNull _ctrlPic) then { _ctrlPic ctrlSetText ""; };
if (!isNull _ctrlFallback) then {
    _ctrlFallback ctrlSetStructuredText parseText "<t align='center' size='0.7' color='#8aa0b4'>Préparation du QR…</t>";
};

// Télécharge le QR (cache key "phoneqr"). Chemin Windows normalisé pour RscPicture.
private _qrPath = "";
if (_qrImageUrl != "") then {
    _qrPath = [["phoneqr", "QR", 0, _qrImageUrl]] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
};

if (isNull (findDisplay 9971)) exitWith {}; // fermé pendant le téléchargement

if (_qrPath != "") then {
    // Arma affiche mieux les chemins absolus avec des / (pas des \ Windows).
    _qrPath = (_qrPath splitString "\") joinString "/";
    if (!isNull _ctrlPic) then {
        _ctrlPic ctrlSetText _qrPath;
        _ctrlPic ctrlShow true;
    };
    if (!isNull _ctrlFallback) then {
        _ctrlFallback ctrlSetStructuredText parseText "";
        _ctrlFallback ctrlShow false;
    };
    diag_log format ["[COMSPEC] QR téléphone affiché : %1", _qrPath];
} else {
    if (!isNull _ctrlPic) then {
        _ctrlPic ctrlSetText "";
        _ctrlPic ctrlShow false;
    };
    if (!isNull _ctrlFallback) then {
        _ctrlFallback ctrlShow true;
        _ctrlFallback ctrlSetStructuredText parseText format [
            "<t align='center' size='0.75' color='#d0dce8'>QR indisponible pour le moment.<br/><br/>Saisissez ce code sur votre téléphone :</t><br/><t align='center' size='1.8' font='RobotoCondensedBold' color='#7dffb3'>%1</t>",
            _code
        ];
    };
    diag_log format ["[COMSPEC] Échec téléchargement QR téléphone (url=%1) — code affiché : %2", _qrImageUrl, _code];
    ["COMSPEC_Warning", [format ["QR indisponible — saisissez le code %1 sur votre téléphone.", _code]]] call comspec_overwatch_connect_fnc_showNotification;
};
