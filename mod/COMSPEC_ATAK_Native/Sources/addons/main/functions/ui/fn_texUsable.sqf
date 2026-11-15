/*
    Image téléchargée par COMSPEC Link (portrait, diapositive, photo) réellement lisible par Arma ? Le jeu refuse toute
    texture hors d'un PBO (« Cannot load texture », journal de TTRD du 26/10 : texture [-1,-1]) : un RscPicture posé
    sur ce chemin restait noir. Seul le fichier rangé dans comspec_cache du dossier du jeu est lisible, par son chemin
    relatif (comspec_cache\…) : c'est là que COMSPEC Link 2.0.128 range les images.
    4.26.1 : le chemin absolu n'est plus essayé. Sur un dossier qu'Arma ne lit pas (Documents, où COMSPEC Link 2.0.124 à
    2.0.127 rangeait les images : portrait remplacé par les initiales), getTextureInfo ouvrait la fenêtre « Picture …
    not found » (journal du 15/11). fileExists d'abord, et seulement sur le chemin relatif.
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
private _cands = [];
private _norm = (_p splitString "\") joinString "/";
private _i = (toLower _norm) find "comspec_cache/";
if (_i >= 0) then { _cands pushBack (((_norm select [_i]) splitString "/") joinString "\"); };
// Chemin déjà relatif (image de la mission ou d'un dossier du jeu) : essayé tel quel.
if ((_p find ":") < 0) then { _cands pushBackUnique _p; };
private _res = "";
{
    if (fileExists _x) then {
        // Le fichier est dans le dossier du jeu : chemin relatif, puis le chemin complet reçu (même fichier).
        {
            private _ti = getTextureInfo _x;
            if (_ti isEqualType [] && {((_ti param [0, -1]) max 0) > 0}) exitWith { _res = _x; };
        } forEach [_x, _p];
    };
    if (_res isNotEqualTo "") exitWith {};
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
