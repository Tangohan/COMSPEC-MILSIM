/*
    Lit l’état réel de l’extension et actualise la fenêtre (connexion / synchro / prêt).
*/
private _auth = [] call comspec_overwatch_connect_fnc_authStateCells;
private _state = _auth getOrDefault ["state", ""];
private _progress = parseNumber (_auth getOrDefault ["progress", "0"]);
private _err = _auth getOrDefault ["error", ""];
private _name = _auth getOrDefault ["name", ""];
private _cs = _auth getOrDefault ["callsign", ""];
private _tenant = _auth getOrDefault ["tenant", ""];
private _unit = _auth getOrDefault ["unit", ""];
private _grade = _auth getOrDefault ["grade", ""];
private _brand = _auth getOrDefault ["brand", ""];
private _modDet = _auth getOrDefault ["mod", ""];
private _extDet = _auth getOrDefault ["ext", ""];
private _modMin = _auth getOrDefault ["min", ""];
private _avatar = _auth getOrDefault ["avatar", ""];
private _role = _auth getOrDefault ["role", ""];
private _function = _auth getOrDefault ["function", ""];

if (_modDet isEqualTo "") then { _modDet = [] call comspec_overwatch_connect_fnc_packVersion; };
if (_extDet isEqualTo "") then { _extDet = "1.18.0"; };

missionNamespace setVariable ["comspec_overwatch_auth_state", _state, false];
if (!(_name isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_name", _name, false]; };
if (!(_tenant isEqualTo "")) then { missionNamespace setVariable ["comspec_tenant_name", _tenant, false]; };
if (!(_unit isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_unit", _unit, false]; };
if (!(_grade isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_grade", _grade, false]; };
if (!(_role isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_role", _role, false]; };
if (!(_function isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_function", _function, false]; };
if (!(_avatar isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_avatar", _avatar, false]; };
if (!([_cs] call comspec_overwatch_connect_fnc_isUsableCallsign)) then { _cs = ""; };
if (!(_cs isEqualTo "")) then { missionNamespace setVariable ["comspec_profile_callsign", _cs, false]; };

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

private _pic = _d displayCtrl 9431;
if (!isNull _pic) then {
    if (_ready && {!(_avatar isEqualTo "")}) then {
        private _cached = missionNamespace getVariable ["comspec_profile_avatar_local", ""];
        if (!(_cached isEqualTo "")) then {
            _pic ctrlSetText _cached;
            _pic ctrlShow true;
        } else {
            _pic ctrlShow false;
            _pic ctrlSetText "";
            if (!(missionNamespace getVariable ["comspec_profile_avatar_loading", false])) then {
                missionNamespace setVariable ["comspec_profile_avatar_loading", true, false];
                [_d, _avatar] spawn {
                    params ["_disp", "_url"];
                    private _raw = ["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_url, "auth_avatar"]]] call comspec_overwatch_connect_fnc_extResult;
                    private _bits = _raw splitString "|";
                    private _local = "";
                    if ((count _bits) >= 2 && {(_bits select 0) isEqualTo "OK"}) then {
                        _local = _bits select 1;
                    };
                    missionNamespace setVariable ["comspec_profile_avatar_loading", false, false];
                    if (_local isEqualTo "") exitWith {};
                    missionNamespace setVariable ["comspec_profile_avatar_local", _local, false];
                    if (isNull _disp) then {
                        _disp = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
                    };
                    if (isNull _disp) exitWith {};
                    private _p = _disp displayCtrl 9431;
                    if (!isNull _p) then {
                        _p ctrlSetText _local;
                        _p ctrlShow true;
                    };
                    private _loader = _disp displayCtrl 9413;
                    if (!isNull _loader) then {
                        _loader ctrlSetPosition [
                            0.31 * safezoneW + safezoneX,
                            0.418 * safezoneH + safezoneY,
                            0.38 * safezoneW,
                            0.210 * safezoneH
                        ];
                        _loader ctrlCommit 0;
                    };
                };
            };
        };
    } else {
        _pic ctrlShow false;
        _pic ctrlSetText "";
    };
};

private _loader = _d displayCtrl 9413;
if (!isNull _loader) then {
    private _picShown = !isNull _pic && {ctrlShown _pic};
    if (_ready && {_picShown}) then {
        _loader ctrlSetPosition [
            0.31 * safezoneW + safezoneX,
            0.418 * safezoneH + safezoneY,
            0.38 * safezoneW,
            0.210 * safezoneH
        ];
    } else {
        _loader ctrlSetPosition [
            0.31 * safezoneW + safezoneX,
            0.325 * safezoneH + safezoneY,
            0.38 * safezoneW,
            0.280 * safezoneH
        ];
    };
    _loader ctrlCommit 0;
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
    private _lines = [];
    if (!(_name isEqualTo "")) then {
        _lines pushBack format ["<t align='center' size='0.95' color='#e8f4f0'>%1</t>", _name];
    };
    if (!(_cs isEqualTo "")) then {
        _lines pushBack format ["<t align='center' size='0.8' color='#c8e8dc'>Indicatif : %1</t>", _cs];
    };
    private _bits = [];
    if (!(_role isEqualTo "")) then { _bits pushBack format ["Rôle : %1", _role]; };
    if (!(_grade isEqualTo "")) then { _bits pushBack format ["Grade : %1", _grade]; };
    if (!(_function isEqualTo "")) then { _bits pushBack format ["Fonction : %1", _function]; };
    if ((count _bits) > 0) then {
        _lines pushBack format ["<t align='center' size='0.7' color='#8aa0b4'>%1</t>", _bits joinString "  ·  "];
    };
    if (!(_unit isEqualTo "") && {!(_unit isEqualTo _tenant)} && {!(_unit isEqualTo _function)}) then {
        _lines pushBack format ["<t align='center' size='0.65' color='#8aa0b4'>Unité : %1</t>", _unit];
    };
    if (!(_tenant isEqualTo "")) then {
        _lines pushBack format ["<t align='center' size='0.65' color='#7aa89a'>Communauté : %1</t>", _tenant];
    };
    _lines pushBack "<t align='center' size='0.65' color='#7aa89a'>STEAM        LIÉ<br/>ATHENA      CONNECTÉ<br/>OVERWATCH   ACTIF<br/>C2          DISPONIBLE</t>";
    (_d displayCtrl 9413) ctrlSetStructuredText parseText (_lines joinString "<br/>");
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
            ([_tenOk, if (_tenant isEqualTo "") then { "Communauté" } else { format ["Communauté %1", _tenant] }] call _line),
            ([_habOk, "Habilitations"] call _line),
            ([_profOk, "Profil opérateur"] call _line),
            ([_cfgOk, "Configuration Overwatch"] call _line),
            ([_c2Ok, "Services C2"] call _line)
        ];
        private _who = if (_grade isEqualTo "") then {
            _name
        } else {
            if (_name isEqualTo "") then { _grade } else { format ["%1 %2", _grade, _name] }
        };
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
