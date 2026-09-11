/*
    Efface localement le fil du canal radio actif (affichage seul).
*/
if (!hasInterface) exitWith { false };

private _ch = toUpper (missionNamespace getVariable ["COMSPEC_Comms_Channel", "SQUAD"]);
private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (_inbox isEqualType []) then {
    // On vide l’inbox messages radio / groupe (affichage local).
    private _kept = _inbox select {
        private _kind = _x param [0, ""];
        !(_kind in ["GROUP", "HQ", "NOTIFY", "MSG"])
    };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _kept, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};

private _gMessages = +(missionNamespace getVariable ["Iceman_ATAK_Group_messages", []]);
if (_gMessages isEqualType []) then {
    missionNamespace setVariable ["Iceman_ATAK_Group_messages", [], false];
    Iceman_ATAK_Group_messages = [];
    Iceman_ATAK_Group_selected = -1;
    if (!isNil "Iceman_fnc_group_updatePanel") then {
        call Iceman_fnc_group_updatePanel;
    };
};

["Affichage du canal effacé (historique serveur inchangé).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
true
