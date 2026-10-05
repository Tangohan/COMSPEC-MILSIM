/*
    Actions de l'app Groupe. Params : [action, argument]
      "rename" : nom lu dans le formulaire (chef ou seul dans le groupe)  "type" : clé de fn_groupTypes
      "lock"   : groupe fermé / ouvert aux arrivées                        "leave" : quitter (nouveau groupe à mon nom)
      "join"   : rejoindre le groupe (netId)                              "lead"  : passer le commandement (netId de l'unité)
*/
params [["_act", ""], ["_arg", ""]];
private _grp = group player;
private _isLead = (leader _grp) isEqualTo player || {(count units _grp) isEqualTo 1};
private _rerender = { [{ ["GROUP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "rename": {
        if !(_isLead) exitWith { ["WARNING", "Seul le chef de groupe peut le renommer", 3, 20] call comspec_atak_native_fnc_notify; };
        private _name = trim (["grpName"] call comspec_atak_native_fnc_formValue);
        if (_name isEqualTo "") exitWith { ["WARNING", "Nom de groupe vide", 3, 20] call comspec_atak_native_fnc_notify; };
        if ((allGroups findIf { side _x isEqualTo side _grp && {_x isNotEqualTo _grp} && {(groupId _x) isEqualTo _name} }) >= 0) exitWith { ["WARNING", format ["Le nom %1 est déjà pris dans votre camp", _name], 4, 20] call comspec_atak_native_fnc_notify; };
        _grp setGroupIdGlobal [_name select [0, 32]];
        ["SUCCESS", format ["Groupe renommé : %1", _name], 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "type": {
        if !(_isLead) exitWith {};
        _grp setVariable ["COMSPEC_GroupType", _arg, true];
        call _rerender;
    };
    case "lock": {
        if !(_isLead) exitWith {};
        _grp setVariable ["COMSPEC_GroupLocked", !(_grp getVariable ["COMSPEC_GroupLocked", false]), true];
        call _rerender;
    };
    case "leave": {
        if ((count units _grp) isEqualTo 1) exitWith { ["INFO", "Vous êtes déjà seul dans votre groupe", 3, 20] call comspec_atak_native_fnc_notify; };
        private _old = groupId _grp;
        // L'équipe de feu appartient à l'ancien groupe.
        ["comspec_atak_native_ft", [player, "leave", []]] call CBA_fnc_serverEvent;
        private _new = createGroup [side _grp, true];
        [player] joinSilent _new;
        private _base = [player] call comspec_atak_native_fnc_unitCallsign;
        _new setGroupIdGlobal [format ["%1", [_base, name player] select (_base isEqualTo "")]];
        _new setVariable ["COMSPEC_GroupType", _grp getVariable ["COMSPEC_GroupType", "INF"], true];
        ["INFO", format ["Vous avez quitté %1", _old], 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "join": {
        private _to = groupFromNetId _arg;
        if (isNull _to || {_to isEqualTo _grp}) exitWith {};
        if (_to getVariable ["COMSPEC_GroupLocked", false]) exitWith { ["WARNING", format ["%1 est fermé : demandez au chef de l'ouvrir", groupId _to], 4, 20] call comspec_atak_native_fnc_notify; };
        if (side _to isNotEqualTo side _grp) exitWith {};
        ["comspec_atak_native_ft", [player, "leave", []]] call CBA_fnc_serverEvent;
        [player] joinSilent _to;
        ["SUCCESS", format ["Vous avez rejoint %1", groupId _to], 3, 20] call comspec_atak_native_fnc_notify;
        // Prévenir le chef du groupe rejoint.
        ["comspec_atak_native_groupNotice", [format ["%1 a rejoint le groupe", name player]], units _to - [player]] call CBA_fnc_targetEvent;
        call _rerender;
    };
    case "lead": {
        if !(_isLead) exitWith {};
        private _u = objectFromNetId _arg;
        if (isNull _u || {group _u isNotEqualTo _grp}) exitWith {};
        // selectLeader doit tourner là où le groupe est local (machine du chef actuel).
        [_grp, _u] remoteExec ["selectLeader", leader _grp];
        ["comspec_atak_native_groupNotice", [format ["%1 prend le commandement de %2", name _u, groupId _grp]], units _grp] call CBA_fnc_targetEvent;
        [{ ["GROUP"] call comspec_atak_native_fnc_pageRender; }, [], 1] call CBA_fnc_waitAndExecute;
    };
};
true
