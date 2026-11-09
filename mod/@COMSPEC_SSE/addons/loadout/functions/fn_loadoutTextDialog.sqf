/*
    Fenêtre « texte à copier » (LOCAL, joueur) : [_title, _help, _text] call comspec_sse_fnc_loadoutTextDialog -> Boolean
    Champ multiligne déjà sélectionné : Ctrl+C suffit (copyToClipboard est sans effet en multijoueur).
    Sans fenêtre possible : texte au RPT et indication au joueur.
*/
params [["_title", "", [""]], ["_help", "", [""]], ["_text", "", [""]]];
if (!hasInterface) exitWith { false };
if (!createDialog "COMSPEC_SSE_LoadoutTextDialog") exitWith {
    diag_log text format ["[COMSPEC SSE] Équipement : %1 = %2", _title, _text];
    hint format ["%1 : fenêtre indisponible, le texte est écrit au RPT (fichier journal d'Arma).", _title];
    false
};
private _d = findDisplay 93850;
(_d displayCtrl 93851) ctrlSetText (" " + _title);
(_d displayCtrl 93852) ctrlSetText _help;
private _e = _d displayCtrl 93853;
_e ctrlSetText _text;
ctrlSetFocus _e;
_e ctrlSetTextSelection [0, count _text];
true
