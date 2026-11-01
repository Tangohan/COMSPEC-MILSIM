/*
    Met à jour le bandeau bêta du menu principal selon la langue et l’acceptation de la licence (note de service).
*/
params [["_display", displayNull]];
if (isNull _display) then { _display = findDisplay 0; };
if (isNull _display) exitWith {};

private _text = _display displayCtrl 9611;
if (isNull _text) exitWith {};

private _version = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
if (_version isEqualTo "") then { _version = "?"; };

// Bandeau « à lire » tant que la licence courante n'est pas acceptée (note de service, fn_licenceStatus).
private _acked = ([] call comspec_overwatch_connect_fnc_licenceStatus) select 1;

// Langue du mod (réglage « Langue / Language » de l'ATAK natif) ; sans l'ATAK natif, langue du jeu.
private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", ["en", "fr"] select ((toLower language) isEqualTo "french")]) isEqualTo "fr";
private _html = if (_fr) then {
    if (_acked) then {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>BÊTA PUBLIQUE</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — le mod peut encore changer. Cliquez pour relire la note de service et la licence.</t></t>",
            _version
        ]
    } else {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>BÊTA PUBLIQUE — À LIRE</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — note de service et licence à accepter. Cliquez pour l’afficher.</t></t>",
            _version
        ]
    }
} else {
    if (_acked) then {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>PUBLIC BETA</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — the mod may still change. Click to read the service note and licence again.</t></t>",
            _version
        ]
    } else {
        format [
            "<t align='left'><t font='RobotoCondensedBold' size='0.85' color='#e8b84a'>PUBLIC BETA — PLEASE READ</t>  <t size='0.72' color='#c8d8e4'>COMSPEC Overwatch v%1 — service note and licence to accept. Click to open it.</t></t>",
            _version
        ]
    }
};

_text ctrlSetStructuredText parseText ((_html) call comspec_overwatch_connect_fnc_tr);
private _hit = _display displayCtrl 9614;
if (!isNull _hit) then {
    _hit ctrlSetTooltip (((if (_fr) then {
        "Note de service et licence COMSPEC ATAK"
    } else {
        "COMSPEC ATAK service note and licence"
    })) call comspec_overwatch_connect_fnc_tr);
};
