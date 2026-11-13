/**
 * ATHENA_fnc_applyRealismProfile
 * 
 * Applique un profil de configuration réalisme (Débutant, Événement, Expert).
 * Les profils sont pré-configurés côté serveur et surchargent les valeurs par défaut.
 * 
 * Arguments:
 *   0: STRING - Clé du profil ("beginner", "event", "expert")
 * 
 * Retour:
 *   BOOL - true si profil appliqué avec succès, false si erreur
 * 
 * Exemples:
 *   ["beginner"] call ATHENA_fnc_applyRealismProfile;  // Mode arcade
 *   ["event"] call ATHENA_fnc_applyRealismProfile;     // Mode équilibré
 *   ["expert"] call ATHENA_fnc_applyRealismProfile;    // Mode hardcore
 * 
 * Note:
 *   Cette fonction doit être appelée côté serveur uniquement (par Zeus ou admin).
 *   Nécessite droits admin pour appeler l'API ATHENA.
 */

params [
    ["_profileKey", "", [""]]
];

// Retour à l'opérateur : notification du téléphone ATAK natif si présent, sinon RPT (jamais le hint d'Arma).
private _say = { params ["_t"]; if (!isNil "comspec_atak_native_fnc_notify") then { ["INFO", _t, 6, 20, "ATHENA"] call comspec_atak_native_fnc_notify; } else { diag_log text format ["[ATHENA] %1", _t]; }; };

// Vérifier argument
if (_profileKey isEqualTo "") exitWith {
    diag_log "[ATHENA] applyRealismProfile: clé profil vide";
    ["❌ Erreur : profil vide"] call _say;
    false
};

// Vérifier exécution serveur
if (!isServer) exitWith {
    diag_log "[ATHENA] applyRealismProfile: fonction serveur uniquement";
    ["❌ Cette commande doit être exécutée côté serveur"] call _say;
    false
};

// Vérifier droits admin (à implémenter selon RBAC ATHENA)
// if (!(player call ATHENA_fnc_isAdmin)) exitWith { ... };

diag_log format ["[ATHENA] applyRealismProfile: application profil '%1'...", _profileKey];

// Appel API ATHENA pour appliquer profil
// POST /api/atak/realism/apply-profile
private _response = "COMSPECExtension" callExtension ["ApplyRealismProfile", [_profileKey]];

private _result = _response select 0;
private _code = _response select 1;

if (_code isEqualTo 0) then {
    diag_log format ["[ATHENA] applyRealismProfile: profil '%1' appliqué avec succès", _profileKey];
    
    // Invalider cache local pour forcer rechargement
    ATHENA_realismConfigCacheTime = -999999;
    
    // Notifier tous les clients
    ["ATHENA_realismProfileApplied", [_profileKey]] call CBA_fnc_globalEvent;
    
    // Hint admin
    private _profileLabel = switch (_profileKey) do {
        case "beginner": {"🟢 Débutant (arcade)"};
        case "event": {"🟡 Événement (équilibré)"};
        case "expert": {"🔴 Expert (simulation)"};
        default {"Profil custom"};
    };
    
    [format ["✅ Profil appliqué : %1", _profileLabel]] call _say;
    
    true
} else {
    diag_log format ["[ATHENA] applyRealismProfile: ERREUR (code %1): %2", _code, _result];
    [format ["❌ Erreur application profil : %1", _result]] call _say;
    false
};
