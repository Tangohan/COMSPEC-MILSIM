/*
    File d'image du mod (3.1.0) : remplace CBA_fnc_execNextFrame et CBA_fnc_waitAndExecute pour tout le code du téléphone.

    Pourquoi : CBA_fnc_onFrame (cba_common 3.19.0, init_perFrameHandler.sqf) rejoue tout son tampon « image suivante »
    tant qu'il n'a pas été échangé, et ne marque un rappel « attendre puis exécuter » comme fait qu'après l'avoir appelé.
    Une erreur de script dans un seul rappel interrompt le gestionnaire avant ces deux étapes : le rappel fautif (et ceux
    qui le précèdent) repart à chaque image, les suivants (dont ceux d'ACE) ne s'exécutent plus. Symptômes rapportés :
    alternance oui/non d'un interrupteur, téléphone et Arsenal ACE perdus jusqu'au redémarrage.

    Ici chaque rappel est retiré de la file AVANT d'être appelé : une erreur de script n'interrompt que l'image en cours
    de notre propre gestionnaire (EachFrame), jamais celui de CBA, et le rappel fautif n'est jamais rejoué. Les rappels
    encore en file reprennent à l'image suivante. Le rappel interrompu est repéré à l'image suivante (drapeau « en cours »
    resté posé) et noté dans le RPT, au plus une ligne toutes les 10 s.

    Coût : une comparaison par image quand les files sont vides ; un retrait en tête de file par rappel.

    ["init"]          enregistre le gestionnaire et vide les files (appelé à la demande par fn_nextFrame / fn_waitAndExec).
    ["run"]           corps du gestionnaire (une fois par image).
    Files (missionNamespace, remises à zéro à chaque mission) : COMSPEC_ATAK_NextFrameQ et COMSPEC_ATAK_WaitQ, entrées
    [code, arguments, clé] ; clé = numéro d'image de dépôt (image suivante) ou instant d'échéance CBA_missionTime (attente).
*/
params [["_mode", "run"]];

if (_mode isEqualTo "init") exitWith {
    COMSPEC_ATAK_NextFrameQ = [];
    COMSPEC_ATAK_WaitQ = [];
    COMSPEC_ATAK_FrameQueueBusy = [];
    private _old = missionNamespace getVariable ["COMSPEC_ATAK_FrameQueueEh", -1];
    if (_old >= 0) then { removeMissionEventHandler ["EachFrame", _old]; };
    missionNamespace setVariable ["COMSPEC_ATAK_FrameQueueEh", addMissionEventHandler ["EachFrame", { ["run"] call comspec_atak_native_fnc_frameQueue; }]];
    true
};

// Rappel resté « en cours » depuis l'image précédente : il a levé une erreur de script. Il est déjà hors de la file.
private _busy = COMSPEC_ATAK_FrameQueueBusy;
if (_busy isNotEqualTo []) then {
    COMSPEC_ATAK_FrameQueueBusy = [];
    private _tick = diag_tickTime;
    if (_tick - (missionNamespace getVariable ["COMSPEC_ATAK_FrameQueueWarnAt", -100]) > 10) then {
        missionNamespace setVariable ["COMSPEC_ATAK_FrameQueueWarnAt", _tick];
        diag_log format ["[COMSPEC ATAK NATIVE][WARN][FRAME] Rappel interrompu par une erreur de script (retiré de la file, jamais rejoué) : %1", (str (_busy select 0)) select [0, 200]];
    };
};

// Image suivante : tout ce qui a été déposé à une image antérieure.
private _frame = diag_frameNo;
private _q = COMSPEC_ATAK_NextFrameQ;
private _n = count _q;
while { _n > 0 && { (_q select 0 select 2) < _frame } } do {
    private _item = _q deleteAt 0;
    _n = _n - 1;
    COMSPEC_ATAK_FrameQueueBusy = _item;
    (_item select 1) call (_item select 0);
};

// Attentes échues, dans l'ordre des échéances (file triée à l'insertion par fn_waitAndExec). Au plus les entrées
// présentes au début de l'image : un rappel qui se réarme à délai nul attendra l'image suivante.
private _w = COMSPEC_ATAK_WaitQ;
_n = count _w;
if (_n > 0) then {
    private _now = missionNamespace getVariable ["CBA_missionTime", time];
    while { _n > 0 && { (_w select 0 select 2) <= _now } } do {
        private _item = _w deleteAt 0;
        _n = _n - 1;
        COMSPEC_ATAK_FrameQueueBusy = _item;
        (_item select 1) call (_item select 0);
    };
};
COMSPEC_ATAK_FrameQueueBusy = [];
