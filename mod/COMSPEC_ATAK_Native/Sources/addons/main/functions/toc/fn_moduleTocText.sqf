/*
    Module « Afficher un texte » (Eden et Zeus) : écrit le message des écrans en affichage TEXTE (bandeaux LED, écrans
    16:9, colonnes), sans passer par le menu de chaque écran (fn_tocTextTool).
    Eden : attributs Texte (« message ; couleur ; durée ; clignotant », messages séparés par |), Couleur, Vitesse,
    Défilement, Écrans visés (écrans synchronisés, écran texte le plus proche, toute la salle la plus proche, tous les
    écrans texte dans le rayon) et Rayon. Posé par le serveur au lancement, après la reprise de la partie précédente
    (fn_tocPersist) et les attributs des écrans : le texte du module l'emporte. Sans texte, le module ne fait rien.
    Zeus : posé sur un écran ou au sol, il ouvre la fenêtre de saisie pour cet écran, sinon pour l'écran texte le plus
    proche de l'endroit visé ; le résultat s'écrit dans la fenêtre. Réutilisable : chaque pose rouvre la fenêtre avec le
    texte en cours. L'écran est cherché par sa place dans le registre des écrans, jamais par « l'objet proche » du module.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (is3DEN) exitWith { true };
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
// Module de la mission : ses attributs Eden sont posés. Module venu d'un Zeus : aucun attribut, la fenêtre s'ouvre.
private _eden = !isNil { _logic getVariable "TxtScope" };
if (_eden || {!hasInterface}) exitWith {
    if (!isServer) exitWith { true };
    private _args = [getPosATL _logic, _ctx get "targets", _logic getVariable ["Text", ""], _logic getVariable ["Color", "G"], _logic getVariable ["Speed", 1],
        _logic getVariable ["Scroll", 0], _logic getVariable ["TxtScope", "sync"], _logic getVariable ["Radius", 50]];
    // Après la reprise (30 s d'attente au plus) et les attributs Eden des écrans (posés 3 s après le lancement).
    [
        { missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false] },
        { [{ ["moduleApply", _this] call comspec_atak_native_fnc_tocTextTool; }, _this, 5] call comspec_atak_native_fnc_waitAndExec; },
        _args, 30,
        { [{ ["moduleApply", _this] call comspec_atak_native_fnc_tocTextTool; }, _this, 5] call comspec_atak_native_fnc_waitAndExec; }
    ] call CBA_fnc_waitUntilAndExecute;
    true
};
private _pos = getPosATL _logic;
private _tg = _ctx get "target";
deleteVehicle _logic;
// Écran sous le module s'il sait afficher du texte, sinon l'écran texte le plus proche de l'endroit visé (sans limite).
private _list = ["targets", ["object", _tg]] call comspec_atak_native_fnc_tocTextTool;
if (_list isEqualTo []) then { _list = ["targets", ["nearest", _pos, 0]] call comspec_atak_native_fnc_tocTextTool; };
["dialog", [_list, "Afficher un texte", ["Aucun écran capable d'afficher du texte : posez un bandeau LED ou un écran TOC", ""] select (_list isNotEqualTo []), _pos]] call comspec_atak_native_fnc_tocTextTool;
true
