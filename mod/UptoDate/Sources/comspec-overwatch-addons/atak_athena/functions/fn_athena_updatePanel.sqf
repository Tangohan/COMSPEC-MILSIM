/*
    Tuile Athena : formulaire connexion natif ou fiche + état des remontées.
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group || {!ctrlShown _group}) exitWith {};
private _pageNow = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_pageNow isNotEqualTo "" && {_pageNow isNotEqualTo "athena"}) exitWith {};

[] call comspec_overwatch_atak_athena_fnc_athena_applyHomeLayout;

private _statusCtrl = [_group, 9701] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _authHint = [_group, 9791] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _steamRaw = missionNamespace getVariable ["COMSPEC_SteamLinked", nil];
private _steamOk = if (isNil "_steamRaw") then { _linked } else { _steamRaw isEqualTo true };
private _authState = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
private _authErr = missionNamespace getVariable ["comspec_overwatch_auth_error", ""];

private _fncClean = {
    params ["_v"];
    private _s = trim (str _v);
    if (_s isEqualTo "" || {(toLower _s) in ["<null>", "any", "nil"]}) then { "" } else { _s };
};

private _fncAgo = {
    params ["_tick", ["_jamais", "pas encore"]];
    if (!(_tick isEqualType 0) || {_tick < 0}) exitWith { _jamais };
    private _sec = round (diag_tickTime - _tick);
    if (_sec < 0) then { _sec = 0; };
    if (_sec < 60) exitWith { format ["il y a %1 s", _sec] };
    if (_sec < 3600) exitWith { format ["il y a %1 min", round (_sec / 60)] };
    format ["il y a %1 h", round (_sec / 3600)]
};

private _fullName = [missionNamespace getVariable ["comspec_profile_name", ""]] call _fncClean;
private _first = "";
private _last = "";
if (_fullName isNotEqualTo "") then {
    private _bits = _fullName splitString " ";
    if ((count _bits) >= 2) then {
        _first = _bits select 0;
        _last = (_bits select [1, (count _bits) - 1]) joinString " ";
    } else {
        _last = _fullName;
    };
};
if (_first isEqualTo "" || {_last isEqualTo ""}) then {
    if (!isNil "comspec_overwatch_connect_fnc_collectOperatorIdentity") then {
        private _ident = [player] call comspec_overwatch_connect_fnc_collectOperatorIdentity;
        if (_ident isEqualType createHashMap) then {
            if (_first isEqualTo "") then { _first = [_ident getOrDefault ["first_name_detected", ""]] call _fncClean; };
            if (_last isEqualTo "") then { _last = [_ident getOrDefault ["last_name_detected", ""]] call _fncClean; };
        };
    };
};
private _role = [missionNamespace getVariable ["comspec_profile_role", ""]] call _fncClean;
private _fn = [missionNamespace getVariable ["comspec_profile_function", ""]] call _fncClean;
private _unit = [missionNamespace getVariable ["comspec_profile_unit", ""]] call _fncClean;
private _cs = [missionNamespace getVariable ["comspec_profile_callsign", ""]] call _fncClean;
private _tenant = [missionNamespace getVariable ["comspec_tenant_name", ""]] call _fncClean;
if (_role isEqualTo "") then { _role = "—"; };
if (_fn isEqualTo "") then { _fn = "—"; };
if (_unit isEqualTo "") then { _unit = "—"; };
if (_first isEqualTo "") then { _first = "—"; };
if (_last isEqualTo "") then { _last = "—"; };
if (_tenant isEqualTo "") then { _tenant = "—"; };

private _allOk = _linked && {_steamOk};

if (_allOk) then {
    private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
    if (!(_linkState isEqualType "")) then { _linkState = "offline"; };
    private _canalLabel = switch (_linkState) do {
        case "linked": { "<t color='#7dffb0'>ouvert</t>" };
        case "connecting": { "<t color='#ffd27a'>en cours…</t>" };
        default { "<t color='#FF8A80'>interrompu</t>" };
    };
    private _steamLabel = if (_steamOk) then {
        "<t color='#7dffb0'>associé</t>"
    } else {
        "<t color='#FF8A80'>non associé</t>"
    };
    private _posAgo = [missionNamespace getVariable ["COMSPEC_LastPositionSync", -1], "pas encore"] call _fncAgo;
    private _healthAgo = [missionNamespace getVariable ["COMSPEC_LastHealthOk", -1], "—"] call _fncAgo;
    private _ptTick = missionNamespace getVariable ["COMSPEC_LastPlaytimeSent", -1];
    private _ptLabel = if (_ptTick isEqualType 0 && {_ptTick >= 0}) then {
        format ["envoyé %1", [_ptTick, ""] call _fncAgo]
    } else {
        "suivi actif (prochain envoi sous ~5 min)"
    };
    private _pack = "";
    if (!isNil "comspec_overwatch_connect_fnc_packVersion") then {
        _pack = [] call comspec_overwatch_connect_fnc_packVersion;
    };
    if (!(_pack isEqualType "")) then { _pack = ""; };

    private _statusTxt = format [
        "<t color='#8aa0b4' size='0.88'>NOM</t>  <t color='#e8f4f0'>%1</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>PRÉNOM</t>  <t color='#e8f4f0'>%2</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>INDICATIF</t>  <t color='#e8f4f0'>%6</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>RÔLE</t>  <t color='#e8f4f0'>%3</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>FONCTION</t>  <t color='#e8f4f0'>%4</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>AFFECTATION</t>  <t color='#e8f4f0'>%5</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>COMMUNAUTÉ</t>  <t color='#e8f4f0'>%7</t><br/><br/>" +
        "<t color='#7aa89a'>État de liaison</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>Steam</t>  %8<br/>" +
        "<t color='#8aa0b4' size='0.88'>Canal poste</t>  %9<br/>" +
        "<t color='#8aa0b4' size='0.88'>Dernière confirmation</t>  <t color='#c8e8dc'>%10</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>Position remontée</t>  <t color='#c8e8dc'>%11</t><br/>" +
        "<t color='#8aa0b4' size='0.88'>Temps de mission</t>  <t color='#c8e8dc'>%12</t>",
        _last, _first, _role, _fn, _unit,
        if (_cs isEqualTo "") then { "—" } else { _cs },
        _tenant, _steamLabel, _canalLabel, _healthAgo, _posAgo, _ptLabel
    ];
    if (_pack isNotEqualTo "") then {
        _statusTxt = _statusTxt + format ["<br/><t color='#6a7a88' size='0.82'>Pack %1</t>", _pack];
    };
    if (!isNull _statusCtrl) then {
        _statusCtrl ctrlSetBackgroundColor [0.08, 0.08, 0.08, 0.94];
        _statusCtrl ctrlSetStructuredText parseText _statusTxt;
    };
} else {
    if (!isNull _authHint) then {
        private _lines = [];
        if (_authState isEqualTo "READY") then {
            _lines pushBack "<t color='#7dffb0'>Environnement prêt</t>";
            if (_fullName isNotEqualTo "") then {
                _lines pushBack format ["<t color='#e8f4f0'>%1</t>", _fullName];
            };
            if (_cs isNotEqualTo "") then {
                _lines pushBack format ["<t color='#c8e8dc'>Indicatif %1</t>", _cs];
            };
            _lines pushBack "<t color='#8aa0b4' size='0.92'>Appuyez sur Entrer pour ouvrir le canal poste.</t>";
        } else {
            if (_authState in ["AUTHENTICATING","RESOLVING_ACCOUNT","RESOLVING_TENANT","SYNCING_PROFILE","LOADING_BRANDING","LOADING_CONFIGURATION","CONNECTING_C2","RESTORING_SESSION","CONTACTING_ATHENA"]) then {
                _lines pushBack "<t color='#7aa89a'>Synchronisation en cours…</t>";
            } else {
                if (!_steamOk) then { _lines pushBack "<t color='#FF8A80'>Steam non associé</t>"; };
                _lines pushBack "<t color='#FFD27A'>Compte non connecté</t>";
                _lines pushBack "<t color='#8aa0b4' size='0.92'>E-mail Athena, Steam, ou code Appairer du portail.</t>";
            };
            if (_authErr isEqualTo "STEAM_NOT_LINKED") then {
                _lines pushBack "<t color='#e8b84a' size='0.9'>Steam non lié au profil — utilisez l’e-mail ou un code.</t>";
            };
            if (_authErr isEqualTo "INVALID_CREDENTIALS") then {
                _lines pushBack "<t color='#e8b84a' size='0.9'>Adresse e-mail ou mot de passe incorrect.</t>";
            };
        };
        _authHint ctrlSetBackgroundColor (if (_authState isEqualTo "READY") then { [0.04, 0.12, 0.08, 0.94] } else { [0.12, 0.08, 0.04, 0.94] });
        _authHint ctrlSetStructuredText parseText (_lines joinString "<br/>");
    };
};

private _enter = [_group, 9801] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _enter) then {
    private _show = !_allOk && {(_authState isEqualTo "READY") || {_linked}};
    _enter ctrlShow _show;
    _enter ctrlEnable _show;
};
