/*
    Vérifie l'état de l'ATAK selon les blessures du joueur.
    Appelé périodiquement et lors de blessures.
    
    3 niveaux de dommages :
    1. ATAK éteint (temporaire, redémarrage possible)
    2. Écran détruit (connexion active mais pas d'affichage)
    3. ATAK détruit (connexion coupée + écran détruit)
*/

if (!hasInterface) exitWith {};
if (!alive player) exitWith {};

// Vérifier si le réalisme ATAK est activé
private _realism = missionNamespace getVariable ["comspec_overwatch_atak_realism", 0];
if (_realism == 0) exitWith {}; // Désactivé

// Récupérer l'état actuel de l'ATAK
private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_atakState isEqualTo createHashMap) then {
    _atakState set ["powered_on", true];
    _atakState set ["screen_destroyed", false];
    _atakState set ["device_destroyed", false];
    _atakState set ["last_check", time];
    missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
};

// Si déjà détruit, rien à faire
if (_atakState get "device_destroyed") exitWith {};

// Vérifier les blessures ACE Medical
if (!isClass (configFile >> "CfgPatches" >> "ace_medical")) exitWith {};

private _unit = player;
private _hitpoints = getAllHitPointsDamage _unit;
if (count _hitpoints < 2) exitWith {};

private _bodyParts = _hitpoints select 0;
private _damages = _hitpoints select 2;

// Chercher les dommages au torse
private _chestDamage = 0;
{
    if (_x in ["HitChest", "HitBody", "HitTorso"]) then {
        private _index = _bodyParts find _x;
        if (_index >= 0) then {
            _chestDamage = _chestDamage max (_damages select _index);
        };
    };
} forEach _bodyParts;

// Ancien état
private _wasPowered = _atakState get "powered_on";
private _wasScreenDestroyed = _atakState get "screen_destroyed";

// Logique selon le niveau de réalisme
switch (_realism) do {
    case 1: {
        // Niveau 1 : ATAK peut s'éteindre (réparable)
        if (_chestDamage > 0.5 && {random 100 < 30}) then {
            // 30% de chance si dommages modérés
            _atakState set ["powered_on", false];
            
            if (_wasPowered) then {
                hintSilent "ATAK éteint suite au choc !";
                playSound "addItemFailed";
                
                // Peut être rallumé après 30 secondes
                [{
                    private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
                    _state set ["powered_on", true];
                    hintSilent "ATAK redémarré";
                }, [], 30] call CBA_fnc_waitAndExecute;
            };
        };
    };
    
    case 2: {
        // Niveau 2 : Écran peut être détruit (connexion OK)
        if (_chestDamage > 0.7 && {!_wasScreenDestroyed} && {random 100 < 40}) then {
            // 40% de chance si dommages sévères
            _atakState set ["screen_destroyed", true];
            _atakState set ["powered_on", false];
            
            hintSilent "Écran ATAK détruit ! Connexion maintenue mais pas d'affichage.";
            playSound "FD_CP_Not_Clear_F";
            
            // Log pour debug
            diag_log "[COMSPEC] Écran ATAK détruit par blessure au torse";
        };
    };
    
    case 3: {
        // Niveau 3 : ATAK peut être complètement détruit
        if (_chestDamage > 0.8 && {random 100 < 50}) then {
            // 50% de chance si dommages critiques
            _atakState set ["device_destroyed", true];
            _atakState set ["screen_destroyed", true];
            _atakState set ["powered_on", false];
            
            hintSilent "ATAK complètement détruit ! Connexion perdue.";
            playSound "FD_CP_Not_Clear_F";
            
            // Forcer la déconnexion
            [] call comspec_overwatch_connect_fnc_disconnect;
            
            diag_log "[COMSPEC] ATAK complètement détruit par blessure au torse";
        };
    };
};

// Sauvegarder l'état
missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
