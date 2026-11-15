/*
    Fiche opérateur : le Steam UID du joueur est-il connu ? (même règle que fn_collectOperatorIdentity : getPlayerUID,
    sinon le dernier UID enregistré dans le profil.) Appelée avant toute construction de fiche.
    Sans UID, la fiche ne peut pas partir : avant, elle était quand même assemblée en entier toutes les 8 s (équipement,
    catalogue d'objets, liste des mods) puis refusée, avec une ligne « Steam UID absent » à chaque fois (213 lignes dans
    une session). Ici le contrôle s'arrête net : un test, aucune construction, aucun script lancé, et une seule ligne
    au journal par changement d'état (UID absent, puis UID connu). La reprise est automatique dès que l'UID apparaît.
    Rien à l'écran.
    Retour : true si l'UID est connu.
*/
if (!hasInterface) exitWith { false };
// Joueur pas encore là : ni fiche ni changement d'état.
if (isNull player) exitWith { false };

private _steam = getPlayerUID player;
if (!(_steam isEqualType "")) then { _steam = ""; };
_steam = trim _steam;
if ((count _steam) < 15) then {
    _steam = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
    _steam = if (_steam isEqualType "") then { trim _steam } else { "" };
};
private _known = (count _steam) >= 15;
private _missing = missionNamespace getVariable ["COMSPEC_OperatorUidMissing", false];
if (!_known && {!_missing}) then {
    missionNamespace setVariable ["COMSPEC_OperatorUidMissing", true, false];
    ["WARN", "OperatorProfile", "Steam UID absent — fiche non transmise (contrôles suspendus, reprise dès que l'UID est connu)"] call comspec_overwatch_connect_fnc_log;
};
if (_known && {_missing}) then {
    missionNamespace setVariable ["COMSPEC_OperatorUidMissing", false, false];
    ["INFO", "OperatorProfile", "Steam UID connu — fiche opérateur reprise"] call comspec_overwatch_connect_fnc_log;
};
_known
