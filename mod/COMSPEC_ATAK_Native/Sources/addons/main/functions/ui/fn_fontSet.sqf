/*
    Police du téléphone : réglage CBA comspec_atak_native_font (aussi dans Réglages > Police), Roboto Condensed par défaut (les polices embarquées sont retirées du choix en 1.84.0, mal affichées en jeu).
    Pose COMSPEC_ATAK_FontSet = [courant, gras, léger] (familles CfgFontFamilies) et COMSPEC_ATAK_FontAlt (police autre
    que celle des classes de config) : fn_fontApply l'applique aux contrôles créés, fn_fontText aux balises <t font=…>.
    Appelée par le preInit, par le réglage CBA et par fn_layoutGet quand le réglage change. Params : [famille] (facultatif).
    Retour : la famille retenue.
*/
params [["_fam", missionNamespace getVariable ["comspec_atak_native_font", "roboto"], [""]]];
// COMSPEC_Barlow* = Roboto Condensed du jeu (ui/fonts.hpp), comme les classes de config.
private _sets = createHashMapFromArray [
    ["roboto", ["COMSPEC_Barlow", "COMSPEC_BarlowBold", "COMSPEC_BarlowLight"]],
    ["purista", ["PuristaMedium", "PuristaSemibold", "PuristaLight"]],
    // Barlow Condensed embarquée (tools/gen_fonts.py), en test sous un nouveau nom (barlow2) : un ancien choix « barlow » de la 1.83.0, mal affichée en jeu, ne la réactive pas.
    ["barlow2", ["COMSPEC_BarlowC", "COMSPEC_BarlowCBold", "COMSPEC_BarlowCLight"]]
];
if !(_fam in _sets) then { _fam = "roboto"; };
COMSPEC_ATAK_FontFam = _fam;
COMSPEC_ATAK_FontSet = _sets get _fam;
COMSPEC_ATAK_FontAlt = _fam isNotEqualTo "roboto";
COMSPEC_ATAK_FontCache = createHashMap;
_fam
