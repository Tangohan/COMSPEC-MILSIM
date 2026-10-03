/* État de l'app Feux (saisie, cible, solution, journal), gardé pour la session. */
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Fires", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Fires", _f];
_f
