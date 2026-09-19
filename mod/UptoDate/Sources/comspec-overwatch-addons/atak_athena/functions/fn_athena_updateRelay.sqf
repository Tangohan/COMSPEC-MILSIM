/*
    Fiche Relais AT : mât le plus proche, mesures, destruction.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Relay_group", controlNull];
if (isNull _group || {!ctrlShown _group}) exitWith {};

private _page = (["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""];
if (_page isNotEqualTo "" && {!(_page in ["WaveRelay", "AtakRelay", "COMSPEC_ATAK_Relay", "waverelay", "atakrelay"])}) exitWith {};

private _body = _group controlsGroupCtrl 9911;
if (isNull _body) then { _body = _group controlsGroupCtrl 9021; };
if (isNull _body) then { _body = _group controlsGroupCtrl 9001; };
if (isNull _body) exitWith {};

private _info = createHashMap;
if (!isNil "comspec_overwatch_connect_fnc_getNearestAtakRelay") then {
    _info = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
};
if (!(_info isEqualType createHashMap)) then { _info = createHashMap; };

private _html = "";
if ((count (keys _info)) < 1) then {
    _html = [
        "<t color='#FFE08A' size='1.05'>Aucun mât à proximité</t><br/><br/>",
        "<t color='#E8F2FA'>Aucun relais n’a été posé sur ce théâtre, ou ils sont trop loin pour être listés.</t><br/><br/>",
        "<t color='#7CFF9A'>Pose</t><br/>",
        "<t color='#E8F2FA'>Dans l’éditeur : Modules COMSPEC → Relais ATAK (mât). Renseignez nom, portée, identité, débit, fiabilité, places, puissance, adresse, passerelle et certificat. Le mât est détruisible.</t>"
    ] joinString "";
} else {
    private _alive = _info getOrDefault ["alive", false];
    private _name = _info getOrDefault ["name", "Relais"];
    private _state = if (_alive) then {
        if (_info getOrDefault ["in_range", false]) then { "À portée — intact" } else { "Hors portée — intact" };
    } else {
        "Détruit"
    };
    private _stateCol = if (!_alive) then { "#FF8A80" } else {
        if (_info getOrDefault ["in_range", false]) then { "#7CFF9A" } else { "#FFE08A" };
    };
    private _row = {
        params ["_lab", "_val"];
        format ["<t color='#8FB4C8'>%1</t><br/><t color='#E8F2FA'>%2</t><br/><br/>", _lab, _val]
    };
    _html = [
        format ["<t color='#7CFF9A' size='1.08'>%1</t><br/>", _name],
        format ["<t color='%1'>%2</t><br/><br/>", _stateCol, _state],
        ["Identité", _info getOrDefault ["identity", "—"]] call _row,
        ["Position", format ["Grille %1 · %2 m", _info getOrDefault ["grid", "—"], round (_info getOrDefault ["dist", 0])]] call _row,
        ["Portée", format ["%1 m", round (_info getOrDefault ["range", 0])]] call _row,
        ["Débit", format ["%1 Mbit/s", ((round (((_info getOrDefault ["throughput_mbps", 0]) * 10))) / 10)]] call _row,
        ["Fiabilité", format ["%1 %%", round (_info getOrDefault ["reliability_pct", 0])]] call _row,
        ["Places", format ["%1 / %2", _info getOrDefault ["used", 0], _info getOrDefault ["slots", 0]]] call _row,
        ["Puissance", format ["%1 W", round (_info getOrDefault ["power_w", 0])]] call _row,
        ["Adresse réseau", _info getOrDefault ["ip", "—"]] call _row,
        ["Passerelle", _info getOrDefault ["gateway", "—"]] call _row,
        ["Certificat", _info getOrDefault ["certificate", "—"]] call _row
    ] joinString "";
};

_body ctrlSetStructuredText parseText _html;
_body ctrlShow true;
_body ctrlCommit 0;
