/*
 * Auteur: COMSPEC
 * Initialisation principale système ATAK
 * Appelé automatiquement au chargement du mod via CBA XEH
 */

// Attendre que joueur et mission soient prêts
if (!hasInterface) exitWith {};
if (isNull player) exitWith {};

// Attendre CBA et ACE
waitUntil {!isNull player && !isNull (findDisplay 46)};
waitUntil {CBA_missionTime > 0};

// Log démarrage
diag_log "[COMSPEC ATAK] Initialisation système ATAK...";
systemChat "📡 COMSPEC ATAK - Initialisation...";

// 1. Vérifier extension disponible
private _extensionVersion = "COMSPECExtension" callExtension ["GetVersion", []];
if ((_extensionVersion select 0) isEqualTo "") then {
    systemChat "⚠️ Extension COMSPEC non chargée - fonctions ATAK limitées";
    diag_log "[COMSPEC ATAK] WARNING: Extension not loaded";
} else {
    systemChat format ["✅ Extension COMSPEC v%1 chargée", _extensionVersion select 0];
    diag_log format ["[COMSPEC ATAK] Extension loaded: v%1", _extensionVersion select 0];
};

// 2. Initialiser tracking véhicules automatique
[] call comspec_overwatch_connect_fnc_initVehicleTracking;

// 3. Initialiser menus ACE Interact
if (isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) then {
    [] call comspec_overwatch_connect_fnc_initATAKMenu;
    diag_log "[COMSPEC ATAK] ACE Interact menus initialized";
} else {
    systemChat "⚠️ ACE Interact non détecté - utilisez fonctions directement ou configurez raccourcis CBA";
    diag_log "[COMSPEC ATAK] WARNING: ACE Interact not found";
};

// 4. Event handlers globaux
player addEventHandler ["Respawn", {
    params ["_unit", "_corpse"];
    
    // Réinitialiser système après respawn
    [{
        [] call comspec_overwatch_connect_fnc_initVehicleTracking;
        [] call comspec_overwatch_connect_fnc_initATAKMenu;
    }, [], 2] call CBA_fnc_waitAndExecute;
}];

// 5. Boucle maintenance (toutes les 60s)
[{
    // Vérifier alertes critiques globales
    // Placeholder pour futures features (notifications polling, etc.)
}, 60] call CBA_fnc_addPerFrameHandler;

// Feedback final
systemChat "✅ Système ATAK opérationnel";
hint parseText "<t color='#00ff00' size='1.5' align='center'>COMSPEC ATAK</t><br/><t size='1'>Système tactique activé</t><br/><t size='0.8'>Menu: ACE Self-Interact<br/>Raccourcis: Configurables dans CBA Settings</t>";

diag_log "[COMSPEC ATAK] Initialization complete";

true
