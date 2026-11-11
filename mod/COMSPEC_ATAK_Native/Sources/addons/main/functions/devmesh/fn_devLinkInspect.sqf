/*
    Inspecteur de lien (mode développeur, fn_devMesh) : viser deux nœuds, lire le détail du bilan radio pour recalibrer
    les seuils en jeu. Sortie : RPT (rapport complet) et centre de notifications du téléphone (jamais le hint d'Arma).
      "actions" : ajoute ou retire les deux actions de visée sur le joueur local
      "pick"    : retient le nœud le plus proche du réticule ; au deuxième, écrit le rapport
      "report"  [nœud A, nœud B] (entrées de "snapshot" de fn_devMesh) -> texte du rapport
*/
params [["_act", "pick"], ["_arg", []]];
if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { false };
switch (_act) do {
    case "actions": {
        private _ids = uiNamespace getVariable ["COMSPEC_ATAK_MeshInspectActs", []];
        if (_ids isNotEqualTo []) exitWith {
            { (_x select 0) removeAction (_x select 1); } forEach _ids;
            uiNamespace setVariable ["COMSPEC_ATAK_MeshInspectActs", []];
            ["Actions de visée retirées", 3] call comspec_atak_native_fnc_toast;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshInspectActs", [
            [player, player addAction ["Maillage : inspecter le nœud visé", { ["pick"] call comspec_atak_native_fnc_devLinkInspect; }, [], 0, false, true]]
        ]];
        ["Action de visée ajoutée (molette)", 3] call comspec_atak_native_fnc_toast;
    };
    case "pick": {
        private _s = ["snapshot"] call comspec_atak_native_fnc_devMesh;
        private _say = { params ["_t"]; diag_log format ["[COMSPEC ATAK NATIVE][MESH] Inspecteur : %1", _t]; ["INFO", _t, 5, 20, "WAVERELAY"] call comspec_atak_native_fnc_notify; };
        if ((count _s) isEqualTo 0) exitWith { ["Aucun graphe sur ce client."] call _say; false };
        private _best = [];
        private _bd = 0.25;
        {
            private _sc = worldToScreen (ASLToAGL (_x select 1));
            if (_sc isEqualTo []) then { continue };
            private _d = (_sc distance2D [0.5, 0.5]);
            if (_d < _bd) then { _bd = _d; _best = _x; };
        } forEach (_s get "nodes");
        if (_best isEqualTo []) exitWith { ["Aucun nœud près du réticule."] call _say; false };
        private _first = uiNamespace getVariable ["COMSPEC_ATAK_MeshInspectA", []];
        if (_first isEqualTo [] || {(_first select 0) isEqualTo (_best select 0)}) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshInspectA", _best];
            [format ["Nœud A : %1 · Visez le nœud B.", _best select 2]] call _say;
            true
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshInspectA", []];
        // Le rapport est écrit ligne par ligne au RPT par "report" ; le téléphone n'en reçoit qu'un résumé.
        private _txt = ["report", [_first, _best]] call comspec_atak_native_fnc_devLinkInspect;
        ["INFO", (_txt splitString (toString [10])) joinString " · ", 10, 20, "WAVERELAY"] call comspec_atak_native_fnc_notify;
        true
    };
    case "report": {
        _arg params ["_na", "_nb"];
        private _pa = _na select 1;
        private _pb = _nb select 1;
        private _r = (_na select 5) min (_nb select 5);
        private _coarse = [_pa, _pb, _r, "coarse"] call comspec_atak_native_fnc_radioLink;
        private _full = [_pa, _pb, _r, "full", [_na select 0, _nb select 0]] call comspec_atak_native_fnc_radioLink;
        private _mcs = ((({ (_coarse select 0) >= _x } count [0, 2, 4, 6, 9, 12, 16, 20]) - 1) max 0) min 7;
        private _heightOf = {
            params ["_p"];
            (_p select 2) - (getTerrainHeightASL _p)
        };
        private _causes = {
            params ["_list"];
            if (_list isEqualTo []) exitWith { "aucune perte" };
            (_list apply { format ["%1 : %2 dB", _x select 0, (_x select 1) toFixed 1] }) joinString " ; "
        };
        private _d = _coarse select 2;
        private _lines = [
            format ["%1 <-> %2", _na select 2, _nb select 2],
            format ["Distance %1 m, portée de la paire %2 m", round _d, round _r],
            format ["Espace libre %1 dB", (20 * log ((_r max 1) / (_d max 1))) toFixed 1],
            format ["Hauteur d'antenne : A %1 m, B %2 m", ([_pa] call _heightOf) toFixed 1, ([_pb] call _heightOf) toFixed 1],
            format ["Profil grossier (celui du graphe) : marge %1 dB, MCS %2", (_coarse select 0) toFixed 1, _mcs],
            [_coarse select 1] call _causes,
            format ["Profil complet : marge %1 dB", (_full select 0) toFixed 1],
            [_full select 1] call _causes
        ];
        // Historique de l'hystérésis : journal du constructeur (serveur ou client), en mode développeur.
        private _log = (missionNamespace getVariable [["COMSPEC_ATAK_MeshLinkLog", "COMSPEC_ATAK_MeshLinkLogSrv"] select ((["serverBuilt"] call comspec_atak_native_fnc_meshGraph) && {!isServer}), []]);
        private _mine = _log select { [(_x select 1), (_x select 2)] in [[_na select 0, _nb select 0], [_nb select 0, _na select 0]] };
        _lines pushBack (["Hystérésis : aucun changement récent", (_mine apply { format ["t=%1 : %2 (%3 dB)", _x select 0, _x select 3, (_x select 4) toFixed 1] }) joinString " ; "] select (_mine isNotEqualTo []));
        private _txt = _lines joinString "\n";
        { diag_log format ["[COMSPEC ATAK NATIVE][MESH] Inspecteur : %1", _x]; } forEach _lines;
        _txt
    };
};
