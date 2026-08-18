/*
    Ouverture de l’app « RENS » dans le tiroir ATAK.

    L’attente de l’opérateur est claire : il choisit ce menu pour écrire, pas
    pour lire un panneau. Le rédacteur plein cadre s’ouvre donc tout de suite, et
    la page du tiroir sert de point de retour quand il le referme.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Note_group", _group];

[] call comspec_overwatch_atak_athena_fnc_athena_updateNote;

// Rédacteur déjà ouvert (retour au tiroir sans avoir refermé) : ne pas empiler.
if (!isNull (findDisplay 9982)) exitWith {};

// Un frame de délai : le tiroir doit avoir fini de changer d’outil avant que le
// rédacteur ne se pose par-dessus, sinon BCE referme le nouveau display.
[] spawn {
    uiSleep 0.1;
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith {};
    [""] call comspec_overwatch_atak_athena_fnc_athena_openNote;
};
