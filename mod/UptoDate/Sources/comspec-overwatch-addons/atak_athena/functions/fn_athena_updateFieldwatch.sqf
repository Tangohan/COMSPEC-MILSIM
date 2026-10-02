/*
    Liste Fieldwatch : scan RF local + remontée au poste.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Fieldwatch_group", controlNull];
if (isNull _group || {!ctrlShown _group}) exitWith {};

private _page = (["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""];
if (_page isNotEqualTo "" && {!(_page in ["AtakFieldwatch", "COMSPEC_ATAK_Fieldwatch", "atakfieldwatch", "fieldwatch"])}) exitWith {
    if (!isNull _group) then {
        _group ctrlShow false;
        _group ctrlEnable false;
    };
    uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_token", -1];
    uiNamespace setVariable ["COMSPEC_ATAK_Fieldwatch_group", controlNull];
};

private _body = _group controlsGroupCtrl 9921;
if (isNull _body) then { _body = _group controlsGroupCtrl 9021; };
if (isNull _body) then { _body = _group controlsGroupCtrl 9001; };
if (isNull _body) exitWith {};

private _cs = player getVariable ["COMSPEC_CallSign", name player];
private _hits = [];
if (!isNil "comspec_overwatch_connect_fnc_scanRfNearby") then {
    _hits = [player, _cs, true] call comspec_overwatch_connect_fnc_scanRfNearby;
};
if (!(_hits isEqualType [])) then { _hits = []; };

private _emitters = missionNamespace getVariable ["COMSPEC_RfEmitters", []];
_emitters = _emitters select { !isNull _x };

private _html = [
    "<t color='#7CFF9A' size='1.08'>Fieldwatch</t><br/>",
    "<t color='#8FB4C8'>Écoute passive Wi‑Fi / BLE — simulée</t><br/><br/>"
];

if ((count _hits) < 1) then {
    _html pushBack "<t color='#FFE08A'>Aucun émetteur à portée</t><br/><br/>";
    if ((count _emitters) < 1) then {
        _html pushBack "<t color='#E8F2FA'>Aucun émetteur posé sur ce théâtre. Zeus → COMSPEC Roleplay → Émetteur RF Fieldwatch, ou module Eden.</t>";
    } else {
        _html pushBack format ["<t color='#E8F2FA'>%1 émetteur(s) sur le théâtre, hors de votre portée de scan.</t>", count _emitters];
    };
} else {
    _html pushBack format ["<t color='#7CFF9A'>%1 source(s) entendue(s)</t><br/><br/>", count _hits];
    {
        private _band = toUpper (_x getOrDefault ["band", "rf"]);
        private _label = _x getOrDefault ["label", "RF"];
        private _rssi = round (_x getOrDefault ["signal_dbm", -99]);
        private _dist = round (_x getOrDefault ["dist", 0]);
        private _mac = _x getOrDefault ["mac", "—"];
        private _sig = _x getOrDefault ["signature_id", ""];
        _html pushBack format ["<t color='#3D9CF0'>%1</t><br/>", _label];
        _html pushBack format ["<t color='#E8F2FA'>%1 · %2 dBm · %3 m</t><br/>", _band, _rssi, _dist];
        if (_mac isNotEqualTo "") then {
            _html pushBack format ["<t color='#8FB4C8' size='0.9'>%1</t><br/>", _mac];
        };
        if (_sig isNotEqualTo "") then {
            _html pushBack format ["<t color='#8FB4C8' size='0.85'>sig %1</t><br/>", _sig];
        };
        _html pushBack "<br/>";
    } forEach _hits;
    _html pushBack "<t color='#8FB4C8' size='0.9'>Hits remontés au poste (calque Fieldwatch).</t>";
};

_body ctrlSetStructuredText parseText (_html joinString "");
_body ctrlShow true;
_body ctrlCommit 0;
