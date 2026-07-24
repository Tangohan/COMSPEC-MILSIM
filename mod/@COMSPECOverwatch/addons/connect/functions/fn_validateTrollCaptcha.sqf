/*
    Valide la réponse du captcha troll.
    
    Params:
        0: INTEGER - Index du bouton cliqué
*/

params [
    ["_buttonIndex", -1, [0]]
];

if (_buttonIndex < 0) exitWith {};

private _trollData = missionNamespace getVariable ["COMSPEC_TrollCaptchaData", createHashMap];
private _correctAnswer = _trollData getOrDefault ["correct", 0];
private _type = _trollData getOrDefault ["type", ""];

// Compter les tentatives
private _attempts = missionNamespace getVariable ["COMSPEC_TrollCaptchaAttempts", 0];
_attempts = _attempts + 1;
missionNamespace setVariable ["COMSPEC_TrollCaptchaAttempts", _attempts];

// Vérifier si c'est la bonne réponse
private _isCorrect = (_buttonIndex == _correctAnswer);

// TROLL : Parfois même la bonne réponse est refusée
if (_isCorrect && {random 100 < 30}) then {
    _isCorrect = false;
    
    // Messages d'erreur absurdes
    private _trollMessages = [
        "Réponse incorrecte. Veuillez réessayer.",
        "Désolé, le système a détecté une activité suspecte.",
        "Erreur 418 : Je suis une théière.",
        "Réponse trop rapide. Êtes-vous un robot ?",
        "Cette réponse est correcte dans 47% des cas. Pas celui-ci.",
        "Le serveur de validation est temporairement indisponible. Réessayez dans 3... 2... 1... Maintenant !",
        "Votre réponse contient des caractères non autorisés.",
        "Tentative de connexion depuis un appareil non reconnu. Captcha réinitialisé."
    ];
    
    hint selectRandom _trollMessages;
    playSound "FD_Start_F";
    
    closeDialog 0;
    
    // Nouveau captcha dans 2 secondes
    [{
        [] call comspec_overwatch_connect_fnc_showTrollCaptcha;
    }, [], 2] call CBA_fnc_waitAndExecute;
    
    exitWith {};
};

if (_isCorrect) then {
    // SUCCESS - mais avec encore plus de troll
    private _successMessages = [
        "Validation réussie !",
        "Bravo ! Vous n'êtes probablement pas un robot.",
        "Félicitations ! Vous avez passé le test de Turing.",
        "Accès accordé. Votre session expirera dans 30 secondes.",
        "Bienvenue, humain confirmé.",
        format ["Tentatives : %1 · Record : 1 · Vous êtes %2% plus lent que la moyenne", _attempts, (_attempts * 100 - 50)]
    ];
    
    hint selectRandom _successMessages;
    playSound "FD_Finish_F";
    
    // Réinitialiser
    missionNamespace setVariable ["COMSPEC_TrollCaptchaAttempts", 0];
    missionNamespace setVariable ["COMSPEC_TrollCaptchaData", nil];
    
    closeDialog 0;
    
    // Réouvrir le Hub après 1 seconde
    [{
        createDialog "COMSPEC_Hub_Dialog";
    }, [], 1] call CBA_fnc_waitAndExecute;
    
} else {
    // ÉCHEC
    private _failMessages = [
        "Réponse incorrecte. Veuillez réessayer.",
        "Mauvaise réponse. Êtes-vous sûr de ne pas être un robot ?",
        "Erreur de validation. Nouvelle tentative requise.",
        format ["Tentative %1/∞ échouée.", _attempts],
        "Cette réponse est invalide dans ce contexte.",
        "Le système a détecté une incohérence dans votre réponse."
    ];
    
    hint selectRandom _failMessages;
    playSound "FD_CP_Not_Clear_F";
    
    closeDialog 0;
    
    // Nouveau captcha dans 1.5 secondes
    [{
        [] call comspec_overwatch_connect_fnc_showTrollCaptcha;
    }, [], 1.5] call CBA_fnc_waitAndExecute;
};
