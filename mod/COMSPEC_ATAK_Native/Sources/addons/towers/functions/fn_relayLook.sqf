/*
    Aspect d'un relais 3D posé dans Eden (attributs « Peinture » et « Équipement », towers/config.cpp) : texture de la
    structure (sélection « camo ») et équipements masqués (animations eq1 / eq2 du modèle). Aspect seulement : la portée
    et le débit du relais ne changent pas.
      [relais, "" | "galva" | "rw" | "olive" | "sand", -1 | 0 nu | 1 léger | 2 complet] call comspec_atak_native_fnc_relayLook
*/
params [["_o", objNull, [objNull]], ["_paint", "", [""]], ["_equip", -1, [0]]];
if (isNull _o || {!local _o}) exitWith {};
if (_paint isNotEqualTo "") then {
    private _tex = ["relay_steel_co", "relay_steel_rw_co", "relay_steel_olive_co", "relay_steel_sand_co"] param [["galva", "rw", "olive", "sand"] find _paint, ""];
    if (_tex isNotEqualTo "") then { _o setObjectTextureGlobal [0, format ["\z\comspec_atak_native\addons\towers\data\%1.paa", _tex]]; };
};
if (_equip >= 0) then {
    _o animateSource ["eq1", parseNumber (_equip < 1), true];
    _o animateSource ["eq2", parseNumber (_equip < 2), true];
};
