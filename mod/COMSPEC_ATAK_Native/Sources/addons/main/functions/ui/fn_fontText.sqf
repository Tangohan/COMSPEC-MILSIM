/*
    Texte structuré dans la police choisie (fn_fontSet) : les balises <t font='COMSPEC_Barlow…'> des apps prennent la
    famille choisie, et le tout est enveloppé dans <t font='…'> pour les balises sans police. Appelée par fn_tr quand une
    autre police que Barlow Condensed est choisie. Résultats mis en cache (vidé à chaque changement de police).
*/
private _v = _this;
private _hit = COMSPEC_ATAK_FontCache getOrDefault [_v, ""];
if (_hit isNotEqualTo "") exitWith { _hit };
COMSPEC_ATAK_FontSet params ["_reg", "_bold", "_light"];
private _r = [_v, "COMSPEC_BarlowBold", _bold] call CBA_fnc_replace;
_r = [_r, "COMSPEC_BarlowLight", _light] call CBA_fnc_replace;
_r = [_r, "COMSPEC_Barlow", _reg] call CBA_fnc_replace;
_r = format ["<t font='%1'>%2</t>", _reg, _r];
if ((count COMSPEC_ATAK_FontCache) > 4000) then { COMSPEC_ATAK_FontCache = createHashMap; };
COMSPEC_ATAK_FontCache set [_v, _r];
_r
