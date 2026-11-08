/*
    Actions de l'app Groupe. Params : [action, argument]
      "rename" : nom lu dans le formulaire (chef ou seul dans le groupe)  "type" : clé de fn_groupTypes
      "lock"   : groupe fermé / ouvert aux arrivées                        "leave" : quitter (nouveau groupe à mon nom)
      "join"   : rejoindre le groupe (netId)                              "lead"  : passer le commandement (netId de l'unité)
      "fold"   : [clé, ouverte par défaut] ouvre ou replie une section de l'app (onglets MON GROUPE et ÉQUIPES)
      "card"   : déplie ou replie la fiche unique d'un membre (netId)
      "rally"  : RALLIEMENT SUR MOI : un seul envoi réseau vers tous les téléphones du groupe, plus un ping sur moi (chef)
      "smsGroup" : canal du groupe s'il existe, sinon SMS préparé dans l'app ; "smsGroupSend" : l'envoie à chaque joueur
      "frameGroup" : carte à l'échelle qui contient tous les membres vivants, sélection sur le chef
*/
params [["_act", ""], ["_arg", ""]];
private _grp = group player;
private _isLead = (leader _grp) isEqualTo player || {(count units _grp) isEqualTo 1};
private _rerender = { [{ ["GROUP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
switch (_act) do {
    case "fold": {
        _arg params [["_k", ""], ["_def", false]];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _key = format ["grpFold_%1", _k];
        _s set [_key, !(_s getOrDefault [_key, _def])];
        call _rerender;
    };
    case "card": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["grpCard", ["", _arg] select ((_s getOrDefault ["grpCard", ""]) isNotEqualTo _arg)];
        call _rerender;
    };
    case "rally": {
        if !(_isLead) exitWith { ["WARNING", "Seul le chef de groupe peut rallier le groupe", 3, 20] call comspec_atak_native_fnc_notify; };
        private _at = missionNamespace getVariable ["COMSPEC_ATAK_RallyAt", -1e9];
        if ((diag_tickTime - _at) < 5) exitWith { ["INFO", "Patientez quelques secondes avant de refaire vibrer.", 3] call comspec_atak_native_fnc_notify; };
        missionNamespace setVariable ["COMSPEC_ATAK_RallyAt", diag_tickTime];
        private _to = (units _grp) select { isPlayer _x && {_x isNotEqualTo player} && {alive _x} && {[_x] call comspec_atak_native_fnc_hasDevice} };
        ["send", getPosATL player] call comspec_atak_native_fnc_mapPing;
        if ((count _to) isEqualTo 0) exitWith { ["INFO", "Aucun téléphone ATAK dans le groupe : ping seul", 3] call comspec_atak_native_fnc_notify; };
        // Anti-rebond du vibreur par joueur (fn_bftAction) : le ralliement compte pour chacun.
        private _cd = missionNamespace getVariable ["COMSPEC_ATAK_BuzzAt", createHashMap];
        { _cd set [netId _x, diag_tickTime]; } forEach _to;
        missionNamespace setVariable ["COMSPEC_ATAK_BuzzAt", _cd];
        // Un seul envoi vers tous, pas un par joueur : huit envois en file sur un canal radio arriveraient en cascade.
        private _res = [{ params ["_t", "_from", "_grid", "_txt", "_pos", "_me"]; ["comspec_atak_native_buzz", [_from, _grid, _txt, _pos, _me], _t] call CBA_fnc_targetEvent; },
            [_to, [player] call comspec_atak_native_fnc_unitCallsign, [getPosASL player, 6] call comspec_atak_native_fnc_gridRef, "RALLIEMENT", getPosASL player, player], "Vibreur", 1] call comspec_atak_native_fnc_netSend;
        [["INFO", "WARNING"] select (_res isEqualTo "QUEUED"), [format ["Ralliement envoyé à %1 téléphone(s)", count _to], "Pas de réseau : le ralliement partira au retour du signal"] select (_res isEqualTo "QUEUED"), 3] call comspec_atak_native_fnc_notify;
    };
    case "smsGroup": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _ch = ((["channels"] call comspec_atak_native_fnc_chatUnread) select { (_x select 0) in ["CH:groupe", "CH:group"] }) param [0, []];
        if ((count _ch) > 0) exitWith {
            _s set ["chatPeer", _ch select 0];
            _s set ["chatView", "CONV"];
            _s set ["chatPick", false];
            ["CHAT"] call comspec_atak_native_fnc_navigate;
        };
        _s set ["grpSms", !(_s getOrDefault ["grpSms", false])];
        call _rerender;
    };
    case "smsGroupSend": {
        private _txt = trim (["grpSmsText"] call comspec_atak_native_fnc_formValue);
        if (_txt isEqualTo "") exitWith { ["WARNING", "Message vide", 3, 20] call comspec_atak_native_fnc_notify; };
        private _to = (units _grp) select { isPlayer _x && {_x isNotEqualTo player} };
        { [name _x, _txt] call comspec_atak_native_fnc_p2pSend; } forEach _to;
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["grpSms", false];
        ["SUCCESS", format ["SMS envoyé à %1 joueur(s)", count _to], 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "frameGroup": {
        ["select", leader _grp] call comspec_atak_native_fnc_unitCard;
        ["MAP"] call comspec_atak_native_fnc_navigate;
        // Échelle mesurée sur la carte en place : à l'image où elle a pris sa taille.
        [{
            private _m = [] call comspec_atak_native_fnc_mapCtrl;
            private _alive = (units group player) select { alive _x };
            if (isNull _m || {(count _alive) isEqualTo 0}) exitWith {};
            private _xs = _alive apply { (getPosASL _x) select 0 };
            private _ys = _alive apply { (getPosASL _x) select 1 };
            private _x0 = selectMin _xs; private _x1 = selectMax _xs;
            private _y0 = selectMin _ys; private _y1 = selectMax _ys;
            (ctrlPosition _m) params ["_mx", "_my", "_mw", "_mh"];
            private _wW = (_m ctrlMapScreenToWorld [_mx, _my]) distance2D (_m ctrlMapScreenToWorld [_mx + _mw, _my]);
            private _wH = (_m ctrlMapScreenToWorld [_mx, _my]) distance2D (_m ctrlMapScreenToWorld [_mx, _my + _mh]);
            // Marge de 30 % (fiche, bandeau et barre d'outils couvrent les bords) ; jamais plus près que le zoom de la fiche.
            private _k = (((_x1 - _x0) * 1.3) / (_wW max 1)) max (((_y1 - _y0) * 1.3) / (_wH max 1));
            private _z = (((ctrlMapScale _m) * _k) max 0.02) min 1;
            [[(_x0 + _x1) / 2, (_y0 + _y1) / 2], _z] call comspec_atak_native_fnc_mapCenter;
            [] call comspec_atak_native_fnc_inspectorUpdate;
        }, [], 0.25] call comspec_atak_native_fnc_waitAndExec;
    };
    case "rename": {
        if !(_isLead) exitWith { ["WARNING", "Seul le chef de groupe peut le renommer", 3, 20] call comspec_atak_native_fnc_notify; };
        // Coupé à 32 caractères avant le contrôle de doublon : c'est ce nom-là que reçoit setGroupIdGlobal.
        private _name = (trim (["grpName"] call comspec_atak_native_fnc_formValue)) select [0, 32];
        if (_name isEqualTo "") exitWith { ["WARNING", "Nom de groupe vide", 3, 20] call comspec_atak_native_fnc_notify; };
        if ((allGroups findIf { side _x isEqualTo side _grp && {_x isNotEqualTo _grp} && {(groupId _x) isEqualTo _name} }) >= 0) exitWith { ["WARNING", format ["Le nom %1 est déjà pris dans votre camp", _name], 4, 20] call comspec_atak_native_fnc_notify; };
        _grp setGroupIdGlobal [_name];
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
        // Redessin seulement si l'app Groupe est encore ouverte une seconde plus tard.
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "GROUP") then { ["GROUP"] call comspec_atak_native_fnc_pageRender; }; }, [], 1] call comspec_atak_native_fnc_waitAndExec;
    };
};
true
