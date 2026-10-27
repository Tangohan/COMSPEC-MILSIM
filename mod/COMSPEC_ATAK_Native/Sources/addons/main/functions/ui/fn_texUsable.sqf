/*
    Image téléchargée par COMSPEC Link (portrait, diapositive, photo) réellement lisible par Arma ? Le jeu refuse toute
    texture hors d'un PBO (« Cannot load texture », journal de TTRD du 26/10 : texture [-1,-1]) : un RscPicture posé
    sur ce chemin restait noir. Essaie le chemin absolu, puis le même fichier relatif au dossier d'Arma
    (comspec_cache\…, lu quand le jeu est lancé avec -filePatching).
    Params : [chemin]. Renvoie le chemin utilisable, ou "" (l'appelant affiche alors l'initiale ou un autre rendu).
    Réponse gardée : un chemin lisible le reste ; un refus est revérifié au bout de 60 s.
*/
params [["_p", "", [""]]];
if (_p isEqualTo "") exitWith { "" };
// Texture d'un PBO (\z\…, \a3\…) ou procédurale : toujours bonne.
if ((_p select [0, 1]) isEqualTo "\" || {(_p select [0, 2]) isEqualTo "#("}) exitWith { _p };
private _memo = uiNamespace getVariable ["COMSPEC_ATAK_TexUsable", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_TexUsable", _memo];
(_memo getOrDefault [_p, ["", -1e9]]) params ["_ok", "_at"];
if (_ok isNotEqualTo "" || {diag_tickTime - _at < 60}) exitWith { _ok };
private _cands = [_p];
private _i = (toLower _p) find "comspec_cache/";
if (_i >= 0) then { _cands pushBack (((_p select [_i]) splitString "/") joinString "\"); };
private _res = "";
{
    private _ti = getTextureInfo _x;
    if (_ti isEqualType [] && {((_ti param [0, -1]) max 0) > 0}) exitWith { _res = _x; };
} forEach _cands;
if (count _memo > 400) then { _memo = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_TexUsable", _memo]; };
_memo set [_p, [_res, diag_tickTime]];
if (_res isEqualTo "" && {!(_p in (missionNamespace getVariable ["COMSPEC_ATAK_TexRefusedLogged", []]))}) then {
    private _l = missionNamespace getVariable ["COMSPEC_ATAK_TexRefusedLogged", []];
    _l pushBack _p;
    missionNamespace setVariable ["COMSPEC_ATAK_TexRefusedLogged", _l];
    ["INFO", "IMAGE", format ["Image refusée par Arma (hors PBO, -filePatching absent) : %1", _p]] call comspec_atak_native_fnc_log;
};
_res
