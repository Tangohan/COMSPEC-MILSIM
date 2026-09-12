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

private _active = toLower (trim (missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"]));
if (_active in ["squad", "global", ""]) then { _active = "general"; };
if (_active in ["hq", "c2", "command"]) then { _active = "commandement"; };
if (_active in ["group"]) then { _active = "groupe"; };
private _store = +(missionNamespace getVariable ["COMSPEC_Comms_Messages", []]);
if (_store isEqualType []) then {
    _store = _store select {
        private _ck = toLower (trim (_x param [4, "general"]));
        if (_ck in ["squad", "global", ""]) then { _ck = "general"; };
        if (_ck in ["hq", "c2", "command"]) then { _ck = "commandement"; };
        if (_ck in ["group"]) then { _ck = "groupe"; };
        !(_ck isEqualTo _active)
    };
    missionNamespace setVariable ["COMSPEC_Comms_Messages", _store, false];
};

["Affichage du canal effacé (historique serveur inchangé).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
true
