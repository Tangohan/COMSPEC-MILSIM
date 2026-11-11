/*
    Module « Salle TOC complète » (Eden et Zeus) : pose d'un coup une salle d'opérations prête et déjà liée, devant le
    module et sur le sol qui est sous lui : mur d'images, grands écrans, écrans suspendus, bandeau horloge, bandeau de
    texte à LED, plaques de nom, bureaux, chaises et PC de TOC, contenus par défaut. Composition, recherche du sol, du mur
    et du plafond, pose objet par objet : fn_tocHallBuild (« build »), le même constructeur pour Eden et pour Zeus.
    Tailles (largeur x profondeur devant le module) : petite 8 x 7 m (mur 2x2, 2 postes), moyenne 11 x 8,5 m (mur 3x2,
    deux 98", écran double suspendu, 3 postes), grande 16 x 9,5 m (mur 4x3, deux 98", deux 136", écran triple suspendu,
    4 postes). Sans mur ou sans plafond : écrans sur pied à la place des écrans muraux et suspendus.
    Eden : posée par le serveur au lancement, après la reprise de la partie précédente ; la salle déjà reprise pour ce
    module (même nom, même endroit, même carte) est gardée, jamais posée en double ; reprise incomplète : reposée.
    Rien n'est dessiné dans l'éditeur : seul le module y apparaît, la salle se voit en jeu (encombrement ci-dessus).
    Attribut « Supprimer la salle avec le module » : le module supprimé en cours de partie emporte sa salle.
    Zeus : formulaire (taille, nom, camp, postes), le mur à l'opposé de la caméra ; le résultat revient dans la ligne de
    retour du Zeus. Serveur plus ancien : la salle est posée par le poste du Zeus, non gardée.
    Postes des opérateurs (attribut Postes) : PC portable de TOC, ou un poste PC fixe (fn_tocPc : un, deux ou trois
    écrans, écran incurvé), dont les moniteurs sont des écrans de la salle.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (is3DEN) exitWith { true };
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _name = toUpper (_logic getVariable ["Name", "SALLE TOC"]);
if (_name isEqualTo "") then { _name = "SALLE TOC"; };
private _side = _logic getVariable ["Side", "WEST"];
private _size = _logic getVariable ["Size", "M"];
private _post = _logic getVariable ["Post", ""];
// Attributs Eden jamais posés (module venu d'un Zeus ou d'un script) : jamais les valeurs par défaut, le formulaire
// s'ouvre (même règle que le module Écran TOC). Module de la mission : exécuté au démarrage, jamais pris pour un Zeus.
private _zeus = (_ctx get "zeus") || {hasInterface && {time > 1} && {isNil { _logic getVariable "Size" }} && {!isNull (getAssignedCuratorLogic player)}};
if (!_zeus) exitWith {
    if (!isServer) exitWith { true };
    private _args = [_logic, getPosATL _logic, getDir _logic, _size, _name, _side, _post, _logic getVariable ["Bind", true]];
    // Après la reprise de la partie précédente (fn_tocPersist « restore », 30 s d'attente au plus) : la salle déjà
    // reprise pour ce module est reconnue à ses écrans en place, pas à une ligne de la sauvegarde.
    [
        { missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false] },
        { [{ ["eden", _this] call comspec_atak_native_fnc_tocHallBuild; }, _this, 1] call comspec_atak_native_fnc_waitAndExec; },
        _args, 30,
        { ["eden", _this] call comspec_atak_native_fnc_tocHallBuild; }
    ] call CBA_fnc_waitUntilAndExecute;
    true
};
private _pos = getPosATL _logic;
deleteVehicle _logic;
["Salle TOC complète", [
    ["COMBO", "Taille", [["Petite, 8 x 7 m : mur 2x2, 2 postes", "S"], ["Moyenne, 11 x 8,5 m : mur 3x2, deux écrans 98 pouces, écran double suspendu, 3 postes", "M"],
        ["Grande, 16 x 9,5 m : mur 4x3, écrans 98 et 136 pouces, écran triple suspendu, 4 postes", "L"]], "M"],
    ["EDIT", "Nom de la salle", "SALLE TOC"],
    ["COMBO", "Camp", [["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"]], str side group player],
    ["COMBO", "Postes des opérateurs", [["PC portable de TOC", ""]] + (["types"] call comspec_atak_native_fnc_tocPc), ""]
], {
    params ["_v", "_pos"];
    _v params [["_size", "M"], ["_name", ""], ["_side", "WEST"], ["_post", ""]];
    if !(_side in ["WEST", "EAST", "GUER"]) then { _side = "WEST"; };
    // Le mur est posé à l'opposé de la caméra du Zeus : la salle s'ouvre vers lui.
    private _dir = (getPosATL curatorCamera) getDir _pos;
    ["zeus", [_pos, _dir, _size, [toUpper _name, "SALLE TOC"] select (_name isEqualTo ""), _side, _post]] call comspec_atak_native_fnc_tocHallBuild;
}, _pos, "Pose une salle d'opérations complète devant vous, sur le sol sous le module, déjà liée à ses PC. Contre un mur et sous un plafond : écrans muraux et suspendus ; en extérieur : écrans sur pied. Gardée pour la partie suivante."] call comspec_atak_native_fnc_zeusForm;
true
