/*
    Mission de banc d'essai du réseau maillé (lot 0, docs/technical/reseau-mesh-mpu5.md, section 6 bis).
    Installation : copier ce dossier dans « Documents\Arma 3\missions\ », puis l'ouvrir dans Eden avec le mod COMSPEC ATAK
    Native et lancer avec -showScriptErrors. Même relief et mêmes scénarios à chaque test (Stratis : relief, ville d'Agia
    Marina, forêt), pour comparer d'une version à l'autre.
    Cette mission force le mode développeur, la liaison réaliste et la voix par le maillage, jamais activés par défaut
    ailleurs. Scénarios et outils : molette (ci-dessous) ou app Debug, onglet MAILLAGE.
*/
if (isServer) then {
    { missionNamespace setVariable [_x, true, true]; } forEach ["comspec_atak_native_mesh_dev", "comspec_atak_native_mpu_real", "comspec_atak_native_mpu_voice"];
};
if (!hasInterface) exitWith {};
[] spawn {
    waitUntil { !isNull player && {!isNil "comspec_atak_native_fnc_mpuLink"} };
    // Au sol : la hauteur de mission.sqm est écrite à la main.
    player setPosATL [(getPosATL player) select 0, (getPosATL player) select 1, 0];
    { if !(_x in (items player)) then { player addItem _x; }; } forEach ["COMSPEC_ATAK_Phone", "COMSPEC_ATAK_MPU5", "COMSPEC_ATAK_EudCable"];
    uiSleep 3;
    ["link", ["cable"]] call comspec_atak_native_fnc_mpuLink;
    {
        _x params ["_label", "_code"];
        player addAction [format ["Banc : %1", _label], _code, [], 0, false, true];
    } forEach [
        ["chaîne A-B-C-D", { ["request", ["scenario", ["chain"]]] call comspec_atak_native_fnc_devMeshSim; }],
        ["grille de 30 nœuds", { ["request", ["scenario", ["grid30"]]] call comspec_atak_native_fnc_devMeshSim; }],
        ["grille de 60 nœuds", { ["request", ["scenario", ["grid60"]]] call comspec_atak_native_fnc_devMeshSim; }],
        ["partition (le nœud du milieu tombe)", { ["request", ["scenario", ["partition"]]] call comspec_atak_native_fnc_devMeshSim; }],
        ["brouilleur 120 s", { ["request", ["scenario", ["jammer"]]] call comspec_atak_native_fnc_devMeshSim; }],
        ["mouvement oui / non", { ["request", ["scenario", ["move"]]] call comspec_atak_native_fnc_devMeshSim; }],
        ["tout effacer", { ["request", ["clear", []]] call comspec_atak_native_fnc_devMeshSim; }],
        ["superposition 3D", { ["toggle"] call comspec_atak_native_fnc_devMeshOverlay; }],
        ["inspecter le nœud visé", { ["pick"] call comspec_atak_native_fnc_devLinkInspect; }],
        ["export du graphe dans le RPT", { ["export"] call comspec_atak_native_fnc_devMesh; }],
        ["tests réussi / échoué", { [] call comspec_atak_native_fnc_devMeshTest; }]
    ];
};
