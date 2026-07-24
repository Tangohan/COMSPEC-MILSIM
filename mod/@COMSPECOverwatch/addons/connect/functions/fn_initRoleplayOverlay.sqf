/*
    Initialise l'overlay roleplay ingame.
    Crée le display et démarre le PFH de mise à jour.
*/

// Vérifier si le roleplay est activé
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith {};
if (!hasInterface) exitWith {};

// Détruire l'ancien overlay si existant
private _oldDisplay = uiNamespace getVariable ["COMSPEC_RoleplayOverlay", displayNull];
if (!isNull _oldDisplay) then {
    _oldDisplay closeDisplay 0;
};

// Créer le display
("COMSPEC_RoleplayOverlay" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RoleplayOverlay", "PLAIN", 0, false];

// Attendre que le display soit créé
private _timeout = diag_tickTime + 2;
waitUntil {
    !isNull (uiNamespace getVariable ["COMSPEC_RoleplayOverlay", displayNull]) ||
    {diag_tickTime > _timeout}
};

private _display = uiNamespace getVariable ["COMSPEC_RoleplayOverlay", displayNull];
if (isNull _display) exitWith {
    diag_log "[COMSPEC Roleplay] Échec création overlay";
};

// Cacher initialement tous les éléments
{
    private _ctrl = _display displayCtrl _x;
    _ctrl ctrlShow false;
} forEach [16801, 16802, 16803, 16810, 16811, 16812, 16813];

diag_log "[COMSPEC Roleplay] Overlay initialisé";

// Démarrer le PFH de mise à jour
if (isNil "COMSPEC_RoleplayOverlayPFH") then {
    COMSPEC_RoleplayOverlayPFH = [{
        [] call comspec_overwatch_connect_fnc_updateRoleplayOverlay;
    }, 0.5, []] call CBA_fnc_addPerFrameHandler; // MAJ toutes les 0.5s
};
