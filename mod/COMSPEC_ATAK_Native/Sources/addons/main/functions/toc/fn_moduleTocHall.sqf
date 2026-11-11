/*
    Module « Salle TOC complète » (Eden et Zeus) : pose d'un coup une salle d'opérations prête et déjà liée
    (fn_tocHall « spawnRoom ») : mur d'images, grands écrans, écrans suspendus (grande taille), bandeau horloge,
    plaques de nom, bureaux, chaises et PC de TOC du jeu de base, contenus par défaut, orientée selon le module (le mur
    est devant le module). Tailles : petite (mur 2x2, 2 postes), moyenne (mur 3x2, deux 98", 3 postes), grande (plus
    deux 65" suspendus et deux 98" sur pied, 4 postes).
    Eden : posée par le serveur au lancement ; si la salle a été gardée d'une partie précédente (même nom), la reprise
    la remet telle qu'elle était et rien n'est posé en double.
    Zeus : formulaire (taille, nom, camp), le mur à l'opposé de la caméra.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _name = toUpper (_logic getVariable ["Name", "SALLE TOC"]);
if (_name isEqualTo "") then { _name = "SALLE TOC"; };
private _side = _logic getVariable ["Side", "WEST"];
private _size = _logic getVariable ["Size", "M"];
if !(_ctx get "zeus") exitWith {
    if (!isServer) exitWith { true };
    private _id = format ["E-%1", _name];
    private _pos = getPosATL _logic;
    private _dir = getDir _logic;
    // Après la reprise (3 s) : une salle gardée de la partie précédente n'est pas posée une seconde fois.
    [{
        params ["_id", "_pos", "_dir", "_size", "_name", "_side"];
        if (["saved", _id] call comspec_atak_native_fnc_tocHall) exitWith {
            ["INFO", "TOC", format ["Salle TOC « %1 » reprise de la partie précédente : pas de nouvelle pose", _name]] call comspec_atak_native_fnc_log;
        };
        ["spawnRoom", [_pos, _dir, _size, _name, _side, _id]] call comspec_atak_native_fnc_tocHall;
    }, [_id, _pos, _dir, _size, _name, _side], 5] call comspec_atak_native_fnc_waitAndExec;
    true
};
private _pos = getPosATL _logic;
deleteVehicle _logic;
["Salle TOC complète", [
    ["COMBO", "Taille", [["Petite : mur 2x2, 2 postes", "S"], ["Moyenne : mur 3x2, deux écrans 98 pouces, 3 postes", "M"], ["Grande : moyenne, écrans suspendus et sur pied, 4 postes", "L"]], "M"],
    ["EDIT", "Nom de la salle", "SALLE TOC"],
    ["COMBO", "Camp", [["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"]], str side group player]
], {
    params ["_v", "_pos"];
    _v params ["_size", "_name", "_side"];
    // Le mur est posé à l'opposé de la caméra du Zeus : la salle s'ouvre vers lui.
    private _dir = (getPosATL curatorCamera) getDir _pos;
    ["do", ["spawn", [_pos, _dir, _size, [_name, "SALLE TOC"] select (_name isEqualTo ""), _side]]] call comspec_atak_native_fnc_tocHall;
    ["feedback", [format ["ATAK : salle TOC « %1 » posée", [_name, "SALLE TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
}, _pos, "Pose une salle d'opérations complète, déjà liée à ses PC : mur d'images, grands écrans, bandeau horloge, plaques de nom, bureaux et PC. Gardée pour la partie suivante."] call comspec_atak_native_fnc_zeusForm;
true
