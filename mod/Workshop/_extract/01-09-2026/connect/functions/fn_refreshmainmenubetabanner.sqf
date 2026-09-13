/*
    Met à jour le bandeau bêta du menu principal selon la langue et l’accusé.
*/
params [["_display", displayNull]];
if (isNull _display) then { _display = findDisplay 0; };
if (isNull _display) exitWith {};

private _text = _display displayCtrl 9611;
if (isNull _text) exitWith {};

private _version = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
if (_version isEqualTo "") then { _version = "?"; };

private _fnc_isTrue = {
    params ["_v"];
    (_v isEqualTo true) || {_v isEqualTo 1} || {
        (_v isEqualType "") && { (toLower _v) in ["true", "1", "yes"] }
    }
};
private _acked = ([profileNamespace getVariable ["comspec_overwatch_cgu_ack", false]] call _fnc_isTrue)
    || ([profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false]] call _fnc_isTrue);

private _fr = (toLower language) isEqualTo "french";
private _html = if (_fr) then {
    if (_acked) then {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>BÊTA PUBLIQUE</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — le mod peut encore changer. Cliquez pour relire la note.</t></t>",
            _version
        ]
    } else {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>BÊTA PUBLIQUE — À LIRE</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — version publique en essai. Cliquez pour afficher la note.</t></t>",
            _version
        ]
    }
} else {
    if (_acked) then {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>PUBLIC BETA</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — the mod may still change. Click to read the notice again.</t></t>",
            _version
        ]
    } else {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>PUBLIC BETA — PLEASE READ</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — public trial build. Click to open the notice.</t></t>",
            _version
        ]
    }
};

_text ctrlSetStructuredText parseText _html;
private _hit = _display displayCtrl 9614;
if (!isNull _hit) then {
    _hit ctrlSetTooltip (if (_fr) then {
        "Note de bêta publique COMSPEC Overwatch"
    } else {
        "COMSPEC Overwatch public beta notice"
    });
};
