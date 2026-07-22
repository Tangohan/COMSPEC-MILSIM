/*
    Normalise le retour de callExtension (Arma 2.18+ : ["texte", code, err] ; ancien : string).
    Params: [_raw]
    Retourne: string (sans guillemets ajoutés)
    Effet de bord : mémorise le code d’erreur Arma dans COMSPEC_LastExtError (0 = OK,
    501 = introuvable, etc.) pour les messages de diagnostic.
*/
params [["_raw", ""]];
missionNamespace setVariable ["COMSPEC_LastExtError", 0, false];
missionNamespace setVariable ["COMSPEC_LastExtReturn", 0, false];
if (_raw isEqualType []) then {
    if ((count _raw) < 1) exitWith { "" };
    if ((count _raw) >= 2) then {
        private _rc = _raw select 1;
        if (_rc isEqualType 0) then {
            missionNamespace setVariable ["COMSPEC_LastExtReturn", _rc, false];
        };
    };
    if ((count _raw) >= 3) then {
        private _ec = _raw select 2;
        if (_ec isEqualType 0) then {
            missionNamespace setVariable ["COMSPEC_LastExtError", _ec, false];
        };
    };
    private _v = _raw select 0;
    if (_v isEqualType "") then { _v } else { format ["%1", _v] }
} else {
    if (_raw isEqualType "") then { _raw } else { format ["%1", _raw] }
}
