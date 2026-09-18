/*
    Relance toutes les données Athena depuis le terminal ATAK.
    Peut être appelé depuis l’app Resynch, le bureau, ACE, ou un bouton.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Resynch_group", controlNull];
private _fnc_paint = {
    private _g = uiNamespace getVariable ["COMSPEC_ATAK_Resynch_group", controlNull];
    if (isNull _g) exitWith {};
    private _body = _g controlsGroupCtrl 9871;
    if (isNull _body) exitWith {};
    private _lines = missionNamespace getVariable ["COMSPEC_LastResynchSummary", []];
    if (!(_lines isEqualType []) || {_lines isEqualTo []}) then {
        _lines = ["<t size='0.78'>Renvoi des données en cours…</t>"];
    };
    _body ctrlSetStructuredText parseText (_lines joinString "<br/>");
};

missionNamespace setVariable [
    "COMSPEC_LastResynchSummary",
    ["<t size='0.78'>Renvoi de toutes les données vers le poste de commandement…</t>"],
    false
];
[] call _fnc_paint;

if (isNil "comspec_overwatch_connect_fnc_forceSyncData") exitWith {
    missionNamespace setVariable [
        "COMSPEC_LastResynchSummary",
        ["<t size='0.78' color='#ffb0a0'>Resynch indisponible sur ce terminal.</t>"],
        false
    ];
    [] call _fnc_paint;
};

[] spawn {
    [] call comspec_overwatch_connect_fnc_forceSyncData;
    private _g = uiNamespace getVariable ["COMSPEC_ATAK_Resynch_group", controlNull];
    if (isNull _g) exitWith {};
    private _body = _g controlsGroupCtrl 9871;
    if (isNull _body) exitWith {};
    private _lines = missionNamespace getVariable ["COMSPEC_LastResynchSummary", []];
    if (!(_lines isEqualType []) || {_lines isEqualTo []}) then {
        _lines = ["<t size='0.78'>Resynch terminé.</t>"];
    };
    _body ctrlSetStructuredText parseText (_lines joinString "<br/>");
};

true
