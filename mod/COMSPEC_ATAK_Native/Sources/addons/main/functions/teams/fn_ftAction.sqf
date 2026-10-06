/*
    Actions des équipes de feu côté téléphone (app Groupe, onglet ÉQUIPES ; BFT ; Inter-team).
    Params : [action, argument]
      "create"         : nom, couleur, icône, description lus dans le formulaire (ftName, ftColor, ftIcon, ftDesc)
      "editOpen" [id]  : ouvre la fiche de modification ; "editClose" : la referme
      "edit"     [id]  : enregistre la fiche (ftEName, ftEColor, ftEIcon, ftEDesc)
      "delete"   [id]  "lock" [id]  "join" [id]  "leave"
      "myRole"         : rôle choisi dans ftMyRole (mémorisé : réappliqué aux prochaines missions, fn_ftRoleSync)
      "roleNewOpen" / "roleNewClose" : fiche de création d'un rôle ; "roleNew" : crée le rôle (ftRName, ftRIcon), retenu sur Athena
      "roleReload"     : relit les rôles d'Athena
      "manage"         : chef de groupe / chef d'équipe : membre ftMember placé dans ftTarget avec le rôle ftRole
    La modification part au serveur (fn_ftServer) qui la diffuse à tout le camp.
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _send = { params ["_op", ["_args", []]]; ["comspec_atak_native_ft", [player, _op, _args]] call CBA_fnc_serverEvent; };
private _val = { params ["_k", ["_d", ""]]; [_k, _d] call comspec_atak_native_fnc_formValue };
private _rerender = { [{ [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "GROUP"]] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "create": {
        private _name = trim (["ftName"] call _val);
        if (_name isEqualTo "") exitWith { ["WARNING", "Donnez un nom à l'équipe (ex. Alpha, Bravo).", 3, 20] call comspec_atak_native_fnc_notify; };
        ["create", [_name, ["ftColor", "RED"] call _val, ["ftIcon", "INF"] call _val, ["ftDesc"] call _val]] call _send;
        _s set ["ftCreate", false];
    };
    case "createOpen": { _s set ["ftCreate", true]; _s set ["ftEdit", ""]; call _rerender; };
    case "createClose": { _s set ["ftCreate", false]; call _rerender; };
    case "editOpen": { _s set ["ftEdit", _arg]; _s set ["ftCreate", false]; call _rerender; };
    case "editClose": { _s set ["ftEdit", ""]; call _rerender; };
    case "edit": {
        ["edit", [_arg, trim (["ftEName"] call _val), ["ftEColor"] call _val, ["ftEIcon"] call _val, ["ftEDesc"] call _val]] call _send;
        _s set ["ftEdit", ""];
    };
    case "delete": { ["delete", [_arg]] call _send; _s set ["ftEdit", ""]; };
    case "lock": { ["lock", [_arg]] call _send; };
    case "join": { ["join", [_arg]] call _send; };
    case "leave": { ["leave"] call _send; };
    case "myRoleKey": {
        private _r = _arg;
        ["role", [netId player, _r]] call _send;
        ["remember", _r] call comspec_atak_native_fnc_ftRoleSync;
    };
    case "myRole": {
        private _r = ["ftMyRole"] call _val;
        ["role", [netId player, _r]] call _send;
        ["remember", _r] call comspec_atak_native_fnc_ftRoleSync;
    };
    case "roleNewOpen": { _s set ["ftRoleNew", true]; call _rerender; };
    case "roleNewClose": { _s set ["ftRoleNew", false]; call _rerender; };
    case "roleNew": {
        private _name = trim (["ftRName"] call _val);
        if ((count _name) < 2) exitWith { ["WARNING", "Donnez un nom au rôle (ex. Mortier, Guide, Observateur).", 3, 20] call comspec_atak_native_fnc_notify; };
        ["roleNew", [_name, ["ftRIcon", "FUS"] call _val]] call _send;
        _s set ["ftRoleNew", false];
        // Envoi à Athena sans attendre la synchro du groupe : le rôle est retenu pour toute la communauté.
        [{ [true] call comspec_atak_native_fnc_squadSync; }, [], 4] call CBA_fnc_waitAndExecute;
    };
    case "roleReload": { ["load"] call comspec_atak_native_fnc_ftRoleSync; ["INFO", "Rôles : lecture d'Athena…", 3, 20] call comspec_atak_native_fnc_notify; };
    case "manage": {
        private _nid = ["ftMember"] call _val;
        if (_nid isEqualTo "") exitWith {};
        private _u = objectFromNetId _nid;
        if (isNull _u) exitWith {};
        private _to = ["ftTarget", "-"] call _val;
        if (_to isNotEqualTo "-" && {_to isNotEqualTo (_u getVariable ["COMSPEC_FT", ""])}) then { ["assign", [_nid, _to]] call _send; };
        private _r = ["ftRole", "-"] call _val;
        if (_r isNotEqualTo "-" && {_r isNotEqualTo (_u getVariable ["COMSPEC_FTRole", ""])}) then {
            // Rôle après le placement : le serveur traite les deux dans l'ordre d'arrivée.
            ["role", [_nid, _r]] call _send;
        };
        _s set ["ftMember", _nid];
    };
    case "pick": { _s set ["ftMember", _arg]; call _rerender; };
};
true
