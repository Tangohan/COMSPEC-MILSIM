/*
    Ouvre (ou rafraîchit) le dialog "Connexion téléphone" : génère un pairing, télécharge le QR
    en réutilisant downloadBriefingSlide (même mécanisme de cache que les diapositives, un QR
    n'étant qu'une image comme une autre pour l'extension), et affiche QR + code court.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _info = [] call comspec_overwatch_connect_fnc_getPhoneConnectInfo;
if (count _info < 4) exitWith {
    ["Connexion téléphone indisponible pour le moment (réseau ou plateforme)."] call BIS_fnc_showNotification;
};

_info params ["_token", "_code", "_connectUrl", "_qrImageUrl", "_expiresAt"];

if (isNull (findDisplay 9971)) then { createDialog "COMSPEC_PhoneConnect_Dialog"; };
private _display = findDisplay 9971;
if (isNull _display) exitWith {};

private _ctrlCode = _display displayCtrl 9022;
if (!isNull _ctrlCode) then {
    _ctrlCode ctrlSetStructuredText parseText format [
        "<t align='center' size='1.35' font='RobotoCondensedBold'>%1</t>",
        _code
    ];
};

private _ctrlUrl = _display displayCtrl 9023;
if (!isNull _ctrlUrl) then {
    _ctrlUrl ctrlSetStructuredText parseText format [
        "<t align='center' size='0.55'>%1</t>",
        _connectUrl
    ];
};

// Réutilise downloadBriefingSlide : cache key = id "phoneqr" (évite collision avec une vraie diapo id 0).
private _qrPath = [["phoneqr", "QR", 0, _qrImageUrl]] call comspec_overwatch_connect_fnc_downloadBriefingSlide;

if (isNull (findDisplay 9971)) exitWith {}; // le joueur a pu fermer le dialog pendant le téléchargement

private _ctrlPic = _display displayCtrl 9021;
if (_qrPath != "") then {
    if (!isNull _ctrlPic) then { _ctrlPic ctrlSetText _qrPath; };
} else {
    ["QR code indisponible — utilisez le code affiché pour vous connecter manuellement."] call BIS_fnc_showNotification;
};
