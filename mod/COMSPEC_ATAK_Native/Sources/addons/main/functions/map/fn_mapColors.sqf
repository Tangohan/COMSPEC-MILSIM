/*
    Couleurs de la carte du téléphone, en un seul endroit (avant : valeurs recopiées dans chaque fonction de dessin).
      affiliations   OTAN / APP-6 : ami bleu, ENI rouge, neutre vert, inconnu jaune ; « friend » et « self » = couleurs
                     choisies dans Réglages > Carte (alliés, moi) ; BLUE, CYAN… = ces choix (COMSPEC_ATAK_MapPalette,
                     aussi lue par la montre et le débriefing) ;
      états          sélection (blanc cerné de noir), pistes anciennes et perdues (alpha aStale / aLost) ;
      étiquettes     fond (opacité choisie dans le volet CONFIGURATION), texte, détail, trait, ENI ;
      grille         numéros des bords, sombres sur carte claire, clairs et détourés sur carte sombre.
    Deux teintes par couleur : soutenue sur carte claire (TOPO, CLAIR), claire sur carte sombre (SOMBRE, NUIT, satellite)
    et en mode nuit ou filtre JVN, qui voilent tout l'écran : la teinte claire garde l'écart avec le fond.
    « lift » [couleur, classe] : couleur de marqueur Arma gardée telle quelle ; sur carte sombre, noir, bleu pur et
                   autres couleurs trop sombres -> leur version vive (table par classe). « halo » : contour sous l'icône.
    Style tactique des marqueurs et traits (Réglages > Carte, COMSPEC_ATAK_MapDrawStyle : AUTO, allumé avec le fond ARDOISE ;
    TACTICAL toujours ; STANDARD jamais) : « tac » et ses couleurs, lus par les fonctions de dessin sans autre calcul.
    Table refaite seulement quand le fond, le mode nuit, les couleurs choisies, l'opacité ou le style changent.
    Params : [fond de carte (fn_mapStyle)] -> table ; sans paramètre : la dernière table (fonctions appelées par fn_mapOnDraw).
*/
params [["_bg", ""]];
private _pal = uiNamespace getVariable ["COMSPEC_ATAK_MapCol", createHashMap];
if (_bg isEqualTo "") exitWith { if ((count _pal) > 0) then { _pal } else { [[] call comspec_atak_native_fnc_mapStyle] call comspec_atak_native_fnc_mapColors } };
private _night = (profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"]) isNotEqualTo "OFF" || {profileNamespace getVariable ["COMSPEC_ATAK_NvgFilter", false]};
private _bgA = profileNamespace getVariable ["COMSPEC_ATAK_LblBgAlpha", 0.8];
if !(_bgA isEqualType 0) then { _bgA = 0.8; };
private _drawStyle = profileNamespace getVariable ["COMSPEC_ATAK_MapDrawStyle", "AUTO"];
private _sig = [_bg, _night, profileNamespace getVariable ["COMSPEC_ATAK_AllyColor", "BLUE"], profileNamespace getVariable ["COMSPEC_ATAK_SelfColor", "CYAN"], _bgA, _drawStyle];
if ((_pal getOrDefault ["sig", []]) isEqualTo _sig) exitWith { _pal };
private _dark = _bg in ["DARK", "NIGHT", "PHOTO", "SAT", "HYBRID", "SLATE"];
private _hi = _dark || _night;
// [carte claire, carte sombre ou mode nuit]
private _pairs = [
    ["BLUE", [0.10, 0.52, 0.96, 1], [0.42, 0.78, 1, 1]],
    ["CYAN", [0.00, 0.68, 0.80, 1], [0.30, 0.92, 1, 1]],
    ["GREEN", [0.12, 0.64, 0.24, 1], [0.42, 0.92, 0.48, 1]],
    ["WHITE", [0.96, 0.96, 0.96, 1], [0.97, 0.97, 0.97, 1]],
    ["YELLOW", [0.92, 0.74, 0.00, 1], [1, 0.90, 0.30, 1]],
    ["ORANGE", [1, 0.48, 0.05, 1], [1, 0.64, 0.28, 1]],
    ["PINK", [0.92, 0.30, 0.66, 1], [1, 0.55, 0.82, 1]],
    ["hostile", [0.88, 0.10, 0.10, 1], [1, 0.38, 0.35, 1]],
    ["neutral", [0.10, 0.64, 0.18, 1], [0.42, 0.92, 0.48, 1]],
    ["unknown", [0.92, 0.74, 0.00, 1], [1, 0.90, 0.30, 1]]
];
_pal = createHashMap;
{ _pal set [_x select 0, _x select ([1, 2] select _hi)]; } forEach _pairs;
_pal set ["friend", _pal getOrDefault [_sig select 2, _pal get "BLUE"]];
_pal set ["self", _pal getOrDefault [_sig select 3, _pal get "CYAN"]];
_pal set ["sel", [1, 1, 1, 1]];
_pal set ["selHalo", [0, 0, 0, 0.6]];
_pal set ["aStale", 0.65];
_pal set ["aLost", 0.3];
// Étiquettes : plaque sombre (un peu plus opaque la nuit), texte blanc, détail gris clair, ENI rouge clair.
_pal set ["lblBg", [0.03, 0.04, 0.045, [_bgA, (_bgA + 0.08) min 0.96] select _night]];
_pal set ["lblTxt", [1, 1, 1, 1]];
_pal set ["lblDim", [0.80, 0.86, 0.84, 1]];
_pal set ["lblHostile", [1, 0.52, 0.47, 1]];
_pal set ["lblEdge", [1, 0.38, 0.35, 1]];
_pal set ["lblLine", [0.86, 0.89, 0.92, 0.6]];
_pal set ["grid", [[0.05, 0.05, 0.05, 0.95], [0.90, 0.94, 0.91, 0.95]] select _dark];
_pal set ["gridSh", [0, 2] select _dark];
_pal set ["dark", _dark];
_pal set ["slate", _bg isEqualTo "SLATE"];
_pal set ["tac", _drawStyle isEqualTo "TACTICAL" || {_drawStyle isNotEqualTo "STANDARD" && {_bg isEqualTo "SLATE"}}];
// Hachures : brosse de marqueur du moteur, tuilée par drawRectangle / drawEllipse (pas de ligne calculée).
_pal set ["hatch", "\A3\ui_f\data\map\markerbrushes\bdiagonal_ca.paa"];
_pal set ["tacPlate", [0.035, 0.05, 0.055, 0.9]];
_pal set ["tacOrange", [1, 0.62, 0.12, 1]];
_pal set ["tacGreen", [0.45, 0.92, 0.55, 1]];
// Éclaircissement d'une couleur trop sombre (liseré des plaques d'étiquette, fond toujours sombre).
private _liftLbl = {
    params ["_c"];
    if (((_c select 0) * 0.3 + (_c select 1) * 0.59 + (_c select 2) * 0.11) >= 0.22) exitWith { _c };
    private _mx = (_c select 0) max (_c select 1) max (_c select 2);
    private _mn = (_c select 0) min (_c select 1) min (_c select 2);
    if (_mx < 0.25 || {(_mx - _mn) < 0.12}) exitWith { [0.78, 0.80, 0.80, _c select 3] };
    [(_c select 0) * 0.55 + 0.42, (_c select 1) * 0.55 + 0.42, (_c select 2) * 0.55 + 0.42, _c select 3]
};
// Icône de marqueur (2.9.40) : couleur exacte, pleinement saturée, comme la fiche du marqueur ; la lisibilité vient du
// halo dessiné dessous (fn_mapGameMarkers, fn_mapOnDraw). 2.9.38 mélangeait vers le blanc toute couleur sombre : rouge,
// bleu, violet délavés. Seules les couleurs vraiment trop sombres pour la carte sombre passent à leur version vive,
// par une table explicite par classe CfgMarkerColors.
private _vivid = createHashMapFromArray [
    ["colorblack", [0.82, 0.84, 0.84]], ["colorblue", [0.184, 0.42, 1]], ["colorwest", [0.25, 0.55, 1]],
    ["colorblufor", [0.25, 0.55, 1]], ["colorcivilian", [0.78, 0.38, 1]], ["colorciv", [0.78, 0.38, 1]],
    ["colorbrown", [0.80, 0.52, 0.24]], ["colorunknown", [0.80, 0.80, 0.80]], ["default", [0.82, 0.84, 0.84]]
];
// « lift » s'exécute plus tard, depuis les fonctions de dessin : une variable locale d'ici n'y existe plus. Table lue dans
// la palette (avant : _vivid indéfinie, erreur de script à chaque image sur fond sombre, dessin de la carte arrêté).
_pal set ["vivid", _vivid];
private _lift = {
    params ["_c", ["_cls", ""]];
    // « Default » : couleur du type (fn_markerColor), gardée sauf si elle est trop sombre pour la carte.
    if ((toLower _cls) isEqualTo "default" && {((_c select 0) * 0.3 + (_c select 1) * 0.59 + (_c select 2) * 0.11) >= 0.2}) exitWith { _c };
    private _v = ((uiNamespace getVariable ["COMSPEC_ATAK_MapCol", createHashMap]) getOrDefault ["vivid", createHashMap]) getOrDefault [toLower _cls, []];
    if ((count _v) isEqualTo 0) exitWith { _c };
    _v + [_c param [3, 1]]
};
_pal set ["lift", [{ _this select 0 }, _lift] select _dark];
_pal set ["liftLbl", _liftLbl];
_pal set ["halo", [0, 0, 0, 0.75]];
_pal set ["haloLight", [1, 1, 1, 0.7]];
_pal set ["sig", _sig];
uiNamespace setVariable ["COMSPEC_ATAK_MapCol", _pal];
uiNamespace setVariable ["COMSPEC_ATAK_MapPalette", _pal];
_pal
