/*
    Géométrie de la carte « code + QR » (ligne "qrcard" de fn_formRender, onglet CODE de l'app Athena).
    Calcul pur (aucun contrôle, aucune variable globale) : il décide, d'après la largeur et la hauteur réellement
    disponibles, si le QR code se pose À CÔTÉ du texte (paysage) ou DESSOUS (colonne étroite, portrait), quel jeu de
    textes sert (complet ou court) et à quelle échelle, pour que l'adresse, le code, son état et les boutons tiennent
    dans la hauteur visible sans défilement, et que le QR reste assez grand pour être scanné.
    Avant (4.26.0) : le QR était une ligne "image" pleine largeur au format 16/9, plus haute que l'écran en paysage
    (un grand cadre noir, le QR hors champ) et rejetée sous les colonnes ; le code et ses boutons défilaient hors de vue.

    Params : [
        largeur de la carte (unités écran horizontales), hauteur visible restante (unités verticales),
        taille du texte structuré (COMSPEC_ATAK_FontBody), hauteur d'un bouton, marge intérieure,
        rapport pixelH / pixelW (hauteur d'un carré de largeur 1), taille plancher du texte (COMSPEC_ATAK_FontMin),
        [[longueurs en caractères des paragraphes du texte complet avant le code], [… après le code]],
        [[… du texte court avant le code], [… après le code]]
    ]
    Retour : [côte à côte, texte court, échelle, taille du texte, taille du code, côté du QR (unités verticales),
              largeur de la colonne de texte, hauteur estimée du texte et des boutons, hauteur estimée de la carte,
              tout tient dans la hauteur visible]
*/
params [
    ["_w", 0.5], ["_avail", 0.5], ["_fb", 0.03], ["_btnH", 0.04], ["_ip", 0.01], ["_ratio", 4 / 3], ["_fmin", 0],
    ["_full", [[], []]], ["_short", [[], []]]
];
// Constantes de la carte (reprises telles quelles par tests/Unit/AtakAthenaLinkCardAssetTest.php).
private _QR_FRAC = 0.36;      // part de la largeur donnée au QR, côte à côte
private _QR_FRAC_BELOW = 0.62; // part de la largeur donnée au QR, sous le texte
private _QR_MIN_FB = 6;       // côté minimal du QR, en tailles de texte (environ 4 px par module en 1080p)
private _SPLIT_CHARS = 28;    // largeur minimale de la colonne de texte, en caractères, pour le côte à côte
private _TEXT_REL = 0.85;     // taille du texte des étapes, relative au corps
private _URL_REL = 1.15;      // adresse à ouvrir
private _CODE_REL = 2.1;      // code à saisir
private _LINE = 1.12;         // interligne
private _CHAR_W = 0.46;       // largeur moyenne d'un caractère, en hauteurs de texte (comme _estH de fn_formRender)
private _SCALES = [1, 0.9, 0.8, 0.7, 0.6];

private _out = [];
private _qrMin = _QR_MIN_FB * _fb;
{
    _x params ["_isShort", "_paras", "_scale"];
    private _fh = (_fb * _TEXT_REL * _scale) max _fmin;
    private _fCode = _fb * _CODE_REL * _scale;
    private _cw = _fh * _CHAR_W / _ratio;
    // Côte à côte : le QR prend sa part de largeur, bornée par la hauteur visible.
    private _side = ((_w * _QR_FRAC * _ratio) min (_avail - 2 * _ip)) max _qrMin;
    private _tw = _w - _side / _ratio - 3 * _ip;
    private _wide = _tw >= _SPLIT_CHARS * _cw;
    if (!_wide) then { _tw = _w - 2 * _ip; };
    private _lines = 0;
    // Deuxième paragraphe avant le code : l'adresse, un peu plus grande.
    { _lines = _lines + ((ceil ((_x * _cw * ([1, _URL_REL] select (_forEachIndex isEqualTo 1 && {!_isShort}))) / (_tw max 0.01))) max 1) * ([1, _URL_REL] select (_forEachIndex isEqualTo 1 && {!_isShort})); } forEach (_paras select 0);
    { _lines = _lines + ((ceil ((_x * _cw) / (_tw max 0.01))) max 1); } forEach (_paras select 1);
    private _textH = _lines * _fh * _LINE + _fCode * _LINE + _ip + _btnH;
    private _cardH = 0;
    if (_wide) then {
        // QR à la hauteur du texte (pas de vide sous les boutons), jamais sous son côté minimal.
        _side = _side min (_textH max _qrMin);
        _cardH = (_textH max _side) + 2 * _ip;
    } else {
        _side = ((_w * _QR_FRAC_BELOW * _ratio) min ((_avail - _textH - 3 * _ip) max _qrMin)) max _qrMin;
        _cardH = _textH + _side + 3 * _ip;
    };
    // Côte à côte : toute la carte tient. Dessous : l'adresse, le code, son état et les boutons tiennent ; le QR suit.
    private _fits = [_textH + 2 * _ip <= _avail, _cardH <= _avail] select _wide;
    _out = [_wide, _isShort, _scale, _fh, _fCode, _side, _tw, _textH, _cardH, _fits];
    if (_fits) exitWith {};
} forEach (
    [[false, _full, 1], [false, _full, 0.9]] + (_SCALES apply { [true, _short, _x] })
);
_out
