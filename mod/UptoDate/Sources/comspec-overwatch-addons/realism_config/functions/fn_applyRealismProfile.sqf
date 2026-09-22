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

// Vérifier argument
if (_profileKey isEqualTo "") exitWith {
    diag_log "[ATHENA] applyRealismProfile: clé profil vide";
    hint "❌ Erreur : profil vide";
    false
};

// Vérifier exécution serveur
if (!isServer) exitWith {
    diag_log "[ATHENA] applyRealismProfile: fonction serveur uniquement";
    hint "❌ Cette commande doit être exécutée côté serveur";
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
    
    hint format ["✅ Profil appliqué : %1", _profileLabel];
    
    true
} else {
    diag_log format ["[ATHENA] applyRealismProfile: ERREUR (code %1): %2", _code, _result];
    hint format ["❌ Erreur application profil : %1", _result];
    false
};
