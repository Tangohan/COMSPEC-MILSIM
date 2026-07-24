/*
    Détermine si on doit afficher un captcha troll.
    Basé sur le paramètre comspec_overwatch_troll_mode.
    
    Returns:
        BOOLEAN - true si on doit afficher le captcha
*/

if (!hasInterface) exitWith {false};

private _trollMode = missionNamespace getVariable ["comspec_overwatch_troll_mode", 0];

if (_trollMode == 0) exitWith {false};

// Vérifier si on a déjà passé un captcha récemment
private _lastCaptchaTime = missionNamespace getVariable ["COMSPEC_LastTrollCaptchaTime", 0];
private _timeSinceLastCaptcha = CBA_missionTime - _lastCaptchaTime;

// Cooldown de 60 secondes minimum entre deux captchas
if (_timeSinceLastCaptcha < 60) exitWith {false};

// Probabilité selon le niveau
private _chance = switch (_trollMode) do {
    case 1: { 10 }; // Occasionnel
    case 2: { 40 }; // Fréquent
    case 3: { 100 }; // Systématique
    default { 0 };
};

// Tirer au sort
private _shouldShow = (random 100 < _chance);

if (_shouldShow) then {
    missionNamespace setVariable ["COMSPEC_LastTrollCaptchaTime", CBA_missionTime];
};

_shouldShow
