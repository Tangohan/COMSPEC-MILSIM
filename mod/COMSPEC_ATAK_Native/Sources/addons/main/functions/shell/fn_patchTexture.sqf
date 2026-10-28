/*
    Texture du patch au dos choisi (profil COMSPEC_ATAK_Patch) :
      "none"           : aucun
      fr, us, skull…   : nos patchs (data/patch_<id>.paa, 3:2)
      "uniform"        : l'insigne porté sur l'uniforme (BIS_fnc_getUnitInsignia), sinon aucun
      "ins:<classe>"   : un insigne CfgUnitInsignia d'un mod chargé ou de la mission (carré)
    Params : [unité (player), choix (profil)]. Renvoie [texture ou "", carré (bool)].
*/
params [["_unit", player], ["_p", profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"]]];
if !(_p isEqualType "") exitWith { ["", false] };
private _ins = {
    params ["_cls"];
    private _cfg = missionConfigFile >> "CfgUnitInsignia" >> _cls;
    if !(isClass _cfg) then { _cfg = configFile >> "CfgUnitInsignia" >> _cls; };
    [getText (_cfg >> "texture"), true]
};
switch (true) do {
    case (_p in ["", "none"]): { ["", false] };
    case (_p isEqualTo "uniform"): {
        private _c = [_unit] call BIS_fnc_getUnitInsignia;
        if (_c isEqualType "" && {_c isNotEqualTo ""}) then { [_c] call _ins } else { ["", false] }
    };
    case ((_p select [0, 4]) isEqualTo "ins:"): { [_p select [4]] call _ins };
    case (_p in ["fr", "fr_sub", "us", "us_ir", "skull", "crusader", "alien", "comspec", "name"]): { [format ["\z\comspec_atak_native\addons\main\data\patch_%1.paa", _p], false] };
    default { ["", false] };
}
