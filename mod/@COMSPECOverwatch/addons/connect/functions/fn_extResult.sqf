/*
    Normalise le retour de callExtension (Arma 2.18+ : ["texte", code, err] ; ancien : string).
    Params: [_raw]
    Retourne: string (sans guillemets ajoutés)
*/
params [["_raw", ""]];
if (_raw isEqualType []) then {
    if ((count _raw) < 1) exitWith { "" };
    private _v = _raw select 0;
    if (_v isEqualType "") then { _v } else { format ["%1", _v] }
} else {
    if (_raw isEqualType "") then { _raw } else { format ["%1", _raw] }
}
