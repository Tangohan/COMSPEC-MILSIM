/*
    Superposition 3D du graphe (mode développeur, fn_devMesh) : traits entre les nœuds, couleur selon la marge (vert
    >= 9 dB, jaune >= 4 dB, orange >= 0 dB, rouge : lien gardé par l'hystérésis alors que le dernier constat a échoué),
    épaisseur selon le débit (1 à 3 traits parallèles), nom et sauts au-dessus de chaque nœud.
    Le gestionnaire Draw3D n'existe que superposition affichée, sur ce seul client, et ne fait que dessiner ce que
    "refresh" a préparé (une fois par seconde).
      "toggle" ; "stop" ; "refresh"
*/
params [["_act", "toggle"]];
switch (_act) do {
    case "toggle": {
        if ((missionNamespace getVariable ["COMSPEC_ATAK_MeshOverlayEh", -1]) >= 0) exitWith { ["stop"] call comspec_atak_native_fnc_devMeshOverlay; false };
        if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { false };
        ["refresh"] call comspec_atak_native_fnc_devMeshOverlay;
        missionNamespace setVariable ["COMSPEC_ATAK_MeshOverlayEh", addMissionEventHandler ["Draw3D", {
            (missionNamespace getVariable ["COMSPEC_ATAK_MeshOverlayData", [[], []]]) params ["_lines", "_labels"];
            { drawLine3D _x; } forEach _lines;
            { drawIcon3D ["", _x select 0, _x select 1, 0, 0, 0, _x select 2, 2, 0.032, "PuristaMedium", "center"]; } forEach _labels;
        }]];
        true
    };
    case "stop": {
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_MeshOverlayEh", -1];
        if (_eh >= 0) then { removeMissionEventHandler ["Draw3D", _eh]; };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshOverlayEh", -1];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshOverlayData", nil];
        true
    };
    case "refresh": {
        if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { ["stop"] call comspec_atak_native_fnc_devMeshOverlay; };
        private _s = ["snapshot"] call comspec_atak_native_fnc_devMesh;
        private _lines = [];
        private _labels = [];
        if ((count _s) > 0) then {
            private _nodes = _s get "nodes";
            // Positions relues à chaque préparation : les traits suivent les nœuds entre deux passes du constructeur.
            private _pos = _nodes apply {
                private _o = _x select 0;
                if (isNull _o) then { ASLToAGL (_x select 1) } else {
                    ASLToAGL (([getPosASL _o, eyePos _o] select (_o isKindOf "CAManBase")) vectorAdd [0, 0, 0.4])
                }
            };
            {
                _x params ["_a", "_b", "_m", "_kb"];
                private _col = switch (true) do {
                    case (_m >= 9): { [0.2, 0.9, 0.3, 0.9] };
                    case (_m >= 4): { [0.95, 0.9, 0.2, 0.9] };
                    case (_m >= 0): { [1, 0.55, 0.1, 0.9] };
                    default { [0.95, 0.15, 0.1, 0.9] };
                };
                private _n = switch (true) do { case (_kb >= 10000): { 3 }; case (_kb >= 2000): { 2 }; default { 1 }; };
                for "_i" from 0 to (_n - 1) do {
                    private _off = [0, 0, 0.06 * _i];
                    _lines pushBack [(_pos select _a) vectorAdd _off, (_pos select _b) vectorAdd _off, _col];
                };
                if ((count _lines) >= 300) then { break };
            } forEach (_s get "links");
            {
                _x params ["", "", "_name", "", "", "", "_h"];
                _labels pushBack [[[0.95, 0.15, 0.1, 1], [1, 1, 1, 1]] select (_h >= 0), (_pos select _forEachIndex) vectorAdd [0, 0, 0.6], format ["%1 · %2", _name, ["hors maillage", format ["%1 saut(s)", _h]] select (_h >= 0)]];
            } forEach _nodes;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshOverlayData", [_lines, _labels]];
        [{
            if ((missionNamespace getVariable ["COMSPEC_ATAK_MeshOverlayEh", -1]) >= 0) then { ["refresh"] call comspec_atak_native_fnc_devMeshOverlay; };
        }, [], 1] call comspec_atak_native_fnc_waitAndExec;
    };
};
