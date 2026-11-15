/*
    Grille commune des bandeaux posés sur la carte du téléphone : le bandeau des coordonnées et, empilés au-delà de lui, les
    bandeaux des apps épinglées (fn_mapPinnedApp). Fonction pure : des nombres en entrée, des rectangles en sortie, aucun
    contrôle, aucune variable lue ailleurs (AtakMapPinsPanelAssetTest refait le calcul à partir des constantes ci-dessous).

    Règles :
      - les bandeaux d'apps ne se posent jamais à côté du bandeau des coordonnées (sa hauteur change avec son contenu,
        fn_mapOverlayUpdate « bandFit ») : ils forment des rangées au-dessus de lui (bandeau en bas) ou en dessous (en haut),
        calées sur son bord gauche ;
      - toutes les cases ont la même largeur et la même hauteur ; une rangée se remplit de gauche à droite, la suivante
        s'éloigne du bandeau ; jamais une case décalée seule ;
      - la rangée prend la largeur du bandeau des coordonnées quand cela ne coûte pas une rangée de plus, sinon toute la
        largeur libre (case plafonnée à « maxChars » caractères) ;
      - en grand : cases de deux lignes ; si la pile dépasse « maxFrac » de la hauteur de la carte, cases compactes d'une
        ligne ; en petit format : toujours compactes ; ce qui dépasse encore n'est pas dessiné ;
      - carte trop étroite pour une case de « minCharsCompact » caractères : aucune case.

    Appels :
      ["const"]                                  -> HashMap des constantes
      ["height", [taille du texte, marge, compacte]] -> hauteur d'une case
      ["layout", [n, mini, x0, xLim, edgeY, top, bandW, bandH, mapH, chW, cellIn, pad, taille du texte, marge]]
          n : nombre d'apps ; x0 : bord gauche de la grille ; xLim : bord droit à ne pas dépasser ; edgeY : bord de la carte où
          le bandeau est posé, marge retirée (bas : y du bas ; haut : y du haut) ; top : bandeau en haut ; bandW, bandH :
          bandeau des coordonnées (0 : masqué) ; mapH : hauteur de la carte ; chW : largeur d'un caractère à chasse fixe.
          -> [cases [[x, y, w, h]...], compactes, hauteur prise depuis edgeY (bandeau compris), colonnes, largeur de case, hauteur de case]
*/
params [["_mode", "layout"], ["_a", []]];
private _K = createHashMapFromArray [
    ["minChars", 22], ["minCharsCompact", 16], ["maxChars", 31], ["gap", 0.5], ["maxFrac", 0.5],
    ["line1", 1.45], ["line2", 1.12], ["padV", 1.5], ["lineCompact", 1.3], ["padVCompact", 1]
];
if (_mode isEqualTo "const") exitWith { _K };
private _cellH = {
    params ["_fs", "_in", ["_compact", false]];
    if (_compact) exitWith { _fs * (_K get "lineCompact") + _in * (_K get "padVCompact") };
    _fs * ((_K get "line1") + (_K get "line2")) + _in * (_K get "padV")
};
if (_mode isEqualTo "height") exitWith { _a call _cellH };

_a params [["_n", 0], ["_mini", false], ["_x0", 0], ["_xLim", 0], ["_edgeY", 0], ["_top", false], ["_bandW", 0], ["_bandH", 0], ["_mapH", 1], ["_chW", 0.01], ["_cellIn", 0], ["_pad", 0], ["_fs", 0.02], ["_in", 0]];
private _gap = _pad * (_K get "gap");
private _availW = _xLim - _x0;
// Distance entre le bord de la carte et la première rangée : le bandeau des coordonnées et un intervalle, ou rien.
private _base = [0, _bandH + _gap] select (_bandH > 0);
// Colonnes, largeur et hauteur de case, rangées, pour des cases compactes ou non.
private _plan = {
    params ["_compact"];
    private _h = [_fs, _in, _compact] call _cellH;
    private _minW = (_K get (["minChars", "minCharsCompact"] select _compact)) * _chW + _cellIn * 2;
    private _colsMax = (floor ((_availW + _gap) / (_minW + _gap))) min _n;
    if (_colsMax < 1) exitWith { [0, 0, 0, _h] };
    private _bw = _bandW min _availW;
    private _colsBand = [0, (floor ((_bw + _gap) / (_minW + _gap))) min _n] select (_bandW > 0);
    private _useBand = _colsBand >= 1 && {(ceil (_n / _colsBand)) isEqualTo (ceil (_n / _colsMax))};
    private _cols = [_colsMax, _colsBand] select _useBand;
    private _colW = (([_availW, _bw] select _useBand) - (_cols - 1) * _gap) / _cols;
    if (!_useBand) then { _colW = _colW min ((_K get "maxChars") * _chW + _cellIn * 2); };
    [_cols, _colW, ceil (_n / _cols), _h]
};
private _fits = { params ["_rows", "_h"]; (_base + _rows * _h + ((_rows - 1) max 0) * _gap) <= (_mapH * (_K get "maxFrac")) };
private _compact = _mini;
([_compact] call _plan) params ["_cols", "_colW", "_rows", "_h"];
// En grand, case de deux lignes trop large pour la carte ou pile trop haute : cases compactes (plus étroites, une ligne).
if (!_compact && {_cols < 1 || {!([_rows, _h] call _fits)}}) then {
    _compact = true;
    ([true] call _plan) params ["_c2", "_w2", "_r2", "_h2"];
    _cols = _c2; _colW = _w2; _rows = _r2; _h = _h2;
};
if (_cols < 1 || {_n < 1}) exitWith { [[], _compact, _bandH, 0, 0, _h] };
// Ce qui dépasse encore la part de carte permise n'est pas dessiné (rangées entières, les plus loin du bandeau d'abord).
while { _rows > 0 && {!([_rows, _h] call _fits)} } do { _rows = _rows - 1; };
private _shown = _n min (_rows * _cols);
private _cells = [];
for "_i" from 0 to (_shown - 1) do {
    private _off = _base + (floor (_i / _cols)) * (_h + _gap);
    _cells pushBack [_x0 + (_i mod _cols) * (_colW + _gap), [_edgeY - _off - _h, _edgeY + _off] select _top, _colW, _h];
};
[_cells, _compact, [_bandH, _base + _rows * _h + ((_rows - 1) max 0) * _gap] select (_rows > 0), _cols, _colW, _h]
