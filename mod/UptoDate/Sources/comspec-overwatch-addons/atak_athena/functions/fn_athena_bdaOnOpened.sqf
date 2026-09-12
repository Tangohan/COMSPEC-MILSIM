/*
    Ouverture BDA Report (stub BCE sans Opened).
    - Si ATAK_BDA (Iceman) est chargé → délègue à Iceman_fnc_bda_onOpened puis libellés FR
    - Sinon → onglet Athena BDA / envoi rapide
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

["bda"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _applyBdaFr = {
    private _g = uiNamespace getVariable ["Iceman_ATAK_BDA_group", controlNull];
    if (isNull _g) exitWith {};
    private _title = _g controlsGroupCtrl 9700;
    if (!isNull _title) then {
        _title ctrlSetStructuredText parseText "<t align='center' size='1.05'>Bilan des dégâts</t>";
    };
    {
        _x params ["_idc", "_txt"];
        private _c = _g controlsGroupCtrl _idc;
        if (!isNull _c) then {
            _c ctrlSetStructuredText parseText format ["<t size='0.82'>%1</t>", _txt];
        };
    } forEach [
        [9710, "Cible"],
        [9712, "Grille"],
        [9714, "Dégâts observés"],
        [9716, "Effets ennemis"],
        [9718, "Effets amis / civils"],
        [9720, "Munitions"],
        [9722, "Remarques"]
    ];
    private _send = _g controlsGroupCtrl 9724;
    if (!isNull _send) then { _send ctrlSetText "Envoyer"; };
    private _clear = _g controlsGroupCtrl 9725;
    if (!isNull _clear) then { _clear ctrlSetText "Effacer"; };
};

if (!isNil "Iceman_fnc_bda_onOpened") exitWith {
    _this call Iceman_fnc_bda_onOpened;
    [] call _applyBdaFr;
    {
        [{
            private _g = uiNamespace getVariable ["Iceman_ATAK_BDA_group", controlNull];
            if (isNull _g) exitWith {};
            private _title = _g controlsGroupCtrl 9700;
            if (!isNull _title) then {
                _title ctrlSetStructuredText parseText "<t align='center' size='1.05'>Bilan des dégâts</t>";
            };
            {
                _x params ["_idc", "_txt"];
                private _c = _g controlsGroupCtrl _idc;
                if (!isNull _c) then {
                    _c ctrlSetStructuredText parseText format ["<t size='0.82'>%1</t>", _txt];
                };
            } forEach [
                [9710, "Cible"],
                [9712, "Grille"],
                [9714, "Dégâts observés"],
                [9716, "Effets ennemis"],
                [9718, "Effets amis / civils"],
                [9720, "Munitions"],
                [9722, "Remarques"]
            ];
            private _send = _g controlsGroupCtrl 9724;
            if (!isNull _send) then { _send ctrlSetText "Envoyer"; };
            private _clear = _g controlsGroupCtrl 9725;
            if (!isNull _clear) then { _clear ctrlSetText "Effacer"; };
        }, [], _x] call CBA_fnc_waitAndExecute;
    } forEach [0.05, 0.25, 0.8];
};

// Repli COMSPEC : pas de module Iceman BDA
private _ph = _group controlsGroupCtrl 9860;
if (!isNull _ph) then {
    _ph ctrlSetStructuredText parseText (
        "<t align='center'>Module BDA ATAK indisponible.</t><br/>" +
        "<t align='center' color='#8aa0b4'>Ouverture d’Athena (onglet BDA)…</t>"
    );
};

[{
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openFeature") then {
        ["bda"] call comspec_overwatch_atak_athena_fnc_athena_openFeature;
    };
}, [], 0.35] call CBA_fnc_waitAndExecute;