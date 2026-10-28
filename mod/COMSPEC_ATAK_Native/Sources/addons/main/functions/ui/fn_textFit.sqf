/*
    Police d'un bouton réduite pour que son libellé tienne dans sa largeur (« PRÉCÉDEN », « SALLE K7Q2M · 3 » coupés).
    ctrlTextWidth ne mesure que le texte simple : le libellé est mesuré sur un texte provisoire, même police, même taille.
    Params : [contrôle, hauteur de police voulue, gras (police des boutons), réduction maximale (0,7 : jamais sous 70 %)]
    Renvoie la hauteur de police posée.
*/
params ["_c", "_fh", ["_bold", true], ["_minK", 0.7]];
disableSerialization;
if (isNull _c) exitWith { _fh };
_c ctrlSetFontHeight _fh;
private _txt = ctrlText _c;
private _avail = ((ctrlPosition _c) select 2) - pixelW * 10;
if (_txt isEqualTo "" || {_avail <= 0}) exitWith { _fh };
private _t = (ctrlParent _c) ctrlCreate ["COMSPEC_RscText", -1];
_t ctrlSetPosition [0, 0, 10, _fh * 2];
_t ctrlCommit 0;
_t ctrlSetFont (COMSPEC_ATAK_FontSet select ([0, 1] select _bold));
_t ctrlSetFontHeight _fh;
_t ctrlSetText _txt; // i18n:brut (déjà traduit)
private _tw = ctrlTextWidth _t;
ctrlDelete _t;
if (_tw <= _avail) exitWith { _fh };
private _h = (_fh * ((_avail / _tw) max _minK)) max (COMSPEC_ATAK_FontMin * 0.85);
_c ctrlSetFontHeight _h;
_h
