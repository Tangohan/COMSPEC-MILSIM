/*
    Police du téléphone : réglage CBA comspec_atak_native_font (aussi dans Réglages > Police), Barlow Condensed par défaut.
    Pose COMSPEC_ATAK_FontSet = [courant, gras, léger] (familles CfgFontFamilies) et COMSPEC_ATAK_FontAlt (police autre
    que celle des classes de config) : fn_fontApply l'applique aux contrôles créés, fn_fontText aux balises <t font=…>.
    Appelée par le preInit, par le réglage CBA et par fn_layoutGet quand le réglage change. Params : [famille] (facultatif).
    Retour : la famille retenue.
*/
params [["_fam", missionNamespace getVariable ["comspec_atak_native_font", "barlow"], [""]]];
private _sets = createHashMapFromArray [
    ["barlow", ["COMSPEC_Barlow", "COMSPEC_BarlowBold", "COMSPEC_BarlowLight"]],
    ["b612", ["COMSPEC_B612", "COMSPEC_B612Bold", "COMSPEC_B612"]],
    ["rajdhani", ["COMSPEC_Rajdhani", "COMSPEC_RajdhaniBold", "COMSPEC_Rajdhani"]],
    ["sharetech", ["COMSPEC_ShareTech", "COMSPEC_ShareTech", "COMSPEC_ShareTech"]],
    ["roboto", ["RobotoCondensed", "RobotoCondensedBold", "RobotoCondensedLight"]],
    ["purista", ["PuristaMedium", "PuristaSemibold", "PuristaLight"]]
];
if !(_fam in _sets) then { _fam = "barlow"; };
COMSPEC_ATAK_FontFam = _fam;
COMSPEC_ATAK_FontSet = _sets get _fam;
COMSPEC_ATAK_FontAlt = _fam isNotEqualTo "barlow";
COMSPEC_ATAK_FontCache = createHashMap;
_fam
