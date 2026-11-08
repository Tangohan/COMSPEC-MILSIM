// Banc réseau : scénarios du banc COMSPEC_BancMaillage à 30 et 60 nœuds simulés, chiffres au RPT (AUTOTEST|PERF, [PERF] du mod).
// Le mode développeur n'est forcé que dans cette mission ; la voix par le maillage reste coupée : ACRE n'est jamais touché.
if !("reseau" call AT_fnc_groupe) exitWith {};
["reseau_prepare", {
    if (["comspec_atak_native_fnc_devMeshSim", "comspec_atak_native_fnc_devMesh", "comspec_atak_native_fnc_mpuLink", "comspec_atak_native_fnc_devMeshTest"] call AT_fnc_nil) exitWith {};
    { missionNamespace setVariable [_x, true, true]; } forEach ["comspec_atak_native_mesh_dev", "comspec_atak_native_mpu_real"];
    { if !(_x in (items player)) then { player addItem _x; }; } forEach ["COMSPEC_ATAK_Phone", "COMSPEC_ATAK_MPU5", "COMSPEC_ATAK_EudCable"];
    uiSleep 2;
    ["link", ["cable"]] call comspec_atak_native_fnc_mpuLink;
    [["allowed"] call comspec_atak_native_fnc_devMesh, "outils du maillage refusés"] call AT_fnc_check;
    private _r = [] call comspec_atak_native_fnc_devMeshTest;
    (format ["tests purs du maillage : %1 réussis sur %2", _r select 0, _r select 1]) call AT_fnc_info;
    [(_r select 0) isEqualTo (_r select 1), format ["fn_devMeshTest : %1 sur %2", _r select 0, _r select 1]] call AT_fnc_check;
    call AT_fnc_done;
}, 45] call AT_fnc_run;

{
    _x params ["_nom", "_scen", "_n"];
    [_nom, {
        params ["_scen", "_n", "_nom"];
        if (["comspec_atak_native_fnc_devMeshSim"] call AT_fnc_nil) exitWith {};
        ["request", ["scenario", [_scen]]] call comspec_atak_native_fnc_devMeshSim;
        private _noeuds = { count ((missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]) select { !isNull _x }) };
        [[{ (call _noeuds) >= _n }, 30] call AT_fnc_wait, format ["%1 nœuds simulés sur %2", call _noeuds, _n]] call AT_fnc_check;
        // Plus de 30 s : au moins un bilan [PERF] du mod (boucle de 30 s) tombe dans la mesure.
        private _fin = diag_tickTime + 65;
        private _fps = [];
        while { diag_tickTime < _fin } do { _fps pushBack diag_fps; uiSleep 1; };
        private _moy = 0;
        { _moy = _moy + _x; } forEach _fps;
        _moy = _moy / ((count _fps) max 1);
        private _st = missionNamespace getVariable ["COMSPEC_ATAK_MeshStats", createHashMap];
        diag_log format ["AUTOTEST|PERF|%1|noeuds=%2|fps_moy=%3|fps_min=%4|constructeur=%5", _nom, call _noeuds, round _moy, round (selectMin _fps), _st toArray false];
        [(count _st) > 0, "aucune passe du constructeur mesurée"] call AT_fnc_check;
        [_nom] call AT_fnc_shot;
        ["request", ["clear", []]] call comspec_atak_native_fnc_devMeshSim;
        uiSleep 3;
        call AT_fnc_done;
    }, 150, [_scen, _n, _nom]] call AT_fnc_run;
} forEach [["reseau_30_noeuds", "grid30", 30], ["reseau_60_noeuds", "grid60", 60]];

{ missionNamespace setVariable [_x, false, true]; } forEach ["comspec_atak_native_mesh_dev", "comspec_atak_native_mpu_real"];
