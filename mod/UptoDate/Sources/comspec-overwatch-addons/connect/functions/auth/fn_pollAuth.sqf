/*
    Lit l’état réel de l’extension et actualise la fenêtre (connexion / synchro / prêt).
*/
private _raw = ["COMSPECExtension" callExtension ["GetAuthState", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString (toString [9]);
if ((count _parts) < 2) exitWith {};
private _state = _parts select 1;
private _progress = if ((count _parts) > 2) then { parseNumber (_parts select 2) } else { 0 };
private _step = if ((count _parts) > 3) then { _parts select 3 } else { _state };
private _err = if ((count _parts) > 4) then { _parts select 4 } else { "" };
private _name = if ((count _parts) > 5) then { _parts select 5 } else { "" };
private _cs = if ((count _parts) > 6) then { _parts select 6 } else { "" };
private _tenant = if ((count _parts) > 7) then { _parts select 7 } else { "" };
private _unit = if ((count _parts) > 8) then { _parts select 8 } else { "" };
private _grade = if ((count _parts) > 9) then { _parts select 9 } else { "" };
private _brand = if ((count _parts) > 10) then { _parts select 10 } else { "" };
private _modDet = if ((count _parts) > 12) then { _parts select 12 } else { "" };
private _extDet = if ((count _parts) > 13) then { _parts select 13 } else { "" };
private _modMin = if ((count _parts) > 14) then { _parts select 14 } else { "" };

private _fnc_blank = {
    params ["_s"];
    _s = str _s;
    if (_s isEqualTo "-" || {_s isEqualTo ""}) then { "" } else { _s };
};
_modDet = [_modDet] call _fnc_blank;
_extDet = [_extDet] call _fnc_blank;
_modMin = [_modMin] call _fnc_blank;
if (_modDet isEqualTo "") then { _modDet = [] call comspec_overwatch_connect_fnc_packVersion; };
if (_extDet isEqualTo "") then { _extDet = "1.18.0"; };

missionNamespace setVariable ["comspec_overwatch_auth_state", _state, false];
if (!(_name isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_name", _name, false]; };
if (!(_cs isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_callsign", _cs, false]; };
if (!(_tenant isEqualTo "")) then { missionNamespace setVariable ["comspec_tenant_name", _tenant, false]; };
if (!(_unit isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_unit", _unit, false]; };
if (!(_grade isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_grade", _grade, false]; };

if (_state isEqualTo "READY") then {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
};

private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};

private _syncing = _state in ["AUTHENTICATING","RESOLVING_ACCOUNT","RESOLVING_TENANT","SYNCING_PROFILE","LOADING_BRANDING","LOADING_CONFIGURATION","CONNECTING_C2","RESTORING_SESSION","CONTACTING_ATHENA","EXTENSION_READY"];
private _ready = _state isEqualTo "READY";
private _login = !_syncing && {!_ready};

{
    (_d displayCtrl _x) ctrlShow _login;
} forEach [9401, 9402, 9420, 9421, 9422, 9425];
(_d displayCtrl 9413) ctrlShow (_syncing || {_ready});
(_d displayCtrl 9423) ctrlShow _ready;
if (!_login) then {
    (_d displayCtrl 9403) ctrlShow false;
    (_d displayCtrl 9424) ctrlShow false;
};

if (!(_brand isEqualTo "") && {_brand find "http" == 0}) then {
    (_d displayCtrl 9414) htmlLoad _brand;
};

private _bar = "";
private _filled = round ((_progress min 100) / 4);
for "_i" from 1 to 25 do {
    _bar = _bar + (if (_i <= _filled) then { "█" } else { "░" });
};

private _errTxt = switch (_err) do {
    case "INVALID_CREDENTIALS": { "Adresse e-mail ou mot de passe incorrect." };
    case "OTP_EXPIRED": { "Ce code n’est plus valable. Demandez-en un nouveau." };
    case "STEAM_NOT_LINKED": { "Steam n’est pas associé à ce poste. Connectez-vous d’abord avec votre e-mail." };
    case "ACCOUNT_DISABLED": { "Ce compte n’est plus autorisé." };
    case "TENANT_DISABLED": { "Cette communauté n’est plus accessible." };
    case "NO_TENANT": { "Aucune communauté n’est rattachée à ce compte." };
    case "MOD_OUTDATED": {
        if (_modMin isEqualTo "") then {
            "Cette version du pack n’est plus acceptée. Installez la mise à jour."
        } else {
            format [
                "Cette version du pack n’est plus acceptée. Pack actuel : %1 — version exigée : %2.",
                _modDet,
                _modMin
            ]
        }
    };
    case "SESSION_EXPIRED": { "La session a expiré. Connectez-vous à nouveau." };
    case "NETWORK_ERROR": { "Athena est injoignable pour le moment." };
    case "C2_UNAVAILABLE": { "Les services de carte sont indisponibles." };
    default { "" };
};

if (_ready) then {
    (_d displayCtrl 9416) ctrlSetStructuredText parseText "<t align='center' font='RobotoCondensedBold' size='1.05' color='#7dffb0'>ENVIRONNEMENT PRÊT</t>";
    private _body = format [
        "<t align='center' size='0.9' color='#e8f4f0'>%1</t><br/><t align='center' size='0.8' color='#c8e8dc'>%2 %3</t><br/><t align='center' size='0.7' color='#8aa0b4'>Indicatif : %4<br/>Unité : %5</t><br/><br/><t align='center' size='0.65' color='#7aa89a'>STEAM        LIÉ<br/>ATHENA      CONNECTÉ<br/>OVERWATCH   ACTIF<br/>C2          DISPONIBLE</t>",
        _tenant,
        _grade,
        _name,
        _cs,
        _unit
    ];
    (_d displayCtrl 9413) ctrlSetStructuredText parseText _body;
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7dffb0'>● Environnement opérationnel prêt</t>";
} else {
    if (_syncing) then {
        (_d displayCtrl 9416) ctrlSetStructuredText parseText "<t align='center' font='RobotoCondensedBold' size='1.0' color='#e8f4f0'>AUTHENTIFICATION RÉUSSIE</t>";
        private _line = {
            params ["_done", "_label"];
            if (_done) then { format ["<t color='#7dffb0'>✓ %1</t>", _label] } else { format ["<t color='#5a7080'>○ %1</t>", _label] };
        };
        private _accOk = !(_state in ["INITIALIZING","EXTENSION_READY","CONTACTING_ATHENA","RESTORING_SESSION","AUTHENTICATING"]);
        private _tenOk = _state in ["SYNCING_PROFILE","LOADING_BRANDING","LOADING_CONFIGURATION","CONNECTING_C2","READY"];
        private _habOk = _tenOk;
        private _profOk = _state in ["LOADING_BRANDING","LOADING_CONFIGURATION","CONNECTING_C2","READY"];
        private _cfgOk = _state in ["CONNECTING_C2","READY"];
        private _c2Ok = _state isEqualTo "READY";
        private _steps = [
            ([_accOk, "Identité Athena"] call _line),
            ([_tenOk, format ["Communauté %1", _tenant]] call _line),
            ([_habOk, "Habilitations"] call _line),
            ([_profOk, "Profil opérateur"] call _line),
            ([_cfgOk, "Configuration Overwatch"] call _line),
            ([_c2Ok, "Services C2"] call _line)
        ];
        private _who = format ["%1 %2", _grade, _name];
        private _txt = format [
            "<t align='center' size='0.75' color='#c8e8dc'>Synchronisation de votre environnement</t><br/><br/>%1<br/><br/><t align='center' size='0.7' color='#e8f4f0'>%2  %3 %%</t><br/><br/><t align='center' size='0.7' color='#c8e8dc'>%4</t><br/><t align='center' size='0.6' color='#8aa0b4'>%5</t><br/><t align='center' size='0.55' color='#7aa89a'>%6</t>",
            _steps joinString "<br/>",
            _bar,
            round _progress,
            _who,
            _tenant,
            if (_cs isEqualTo "") then { "Opérateur" } else { format ["Opérateur • %1", _cs] }
        ];
        (_d displayCtrl 9413) ctrlSetStructuredText parseText _txt;
        (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>● Synchronisation en cours</t>";
    };
};

if (!(_errTxt isEqualTo "")) then {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText format ["<t align='center' size='0.52' color='#e8b84a'>%1</t>", _errTxt];
};

private _foot = if (_modMin isEqualTo "") then {
    format ["<t align='center' size='0.48' color='#5a7080'>Liaison %1 • Pack actuel %2</t>", _extDet, _modDet]
} else {
    format [
        "<t align='center' size='0.48' color='#5a7080'>Liaison %1 • Pack actuel %2 • Pack exigé %3</t>",
        _extDet,
        _modDet,
        _modMin
    ]
};
(_d displayCtrl 9430) ctrlSetStructuredText parseText _foot;

if (_syncing && {!_ready}) then {
    [{ [] call comspec_overwatch_connect_fnc_pollAuth; }, [], 0.35] call CBA_fnc_waitAndExecute;
};
