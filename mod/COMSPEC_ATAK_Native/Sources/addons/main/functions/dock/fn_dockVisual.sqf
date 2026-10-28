/*
    Rendu du socle de recharge (serveur) : sélections du modèle charging_dock.p3d
      0-9   phone1..10  : coque du téléphone posé (vest_phone{,_od,_black}_co, couleur du propriétaire), transparent si libre
      10-19 screen1..10 : écran de charge (dock_screen_0..5_co : < 20 %, < 40, < 60, < 80, < 100, chargé)
      20-29 led1..10    : voyant (éteint, rouge < 20 %, orange en charge, vert chargé)
    Seules les textures qui changent sont renvoyées (setObjectTextureGlobal).
    Params : [socle, emplacements publics [[UID, nom, charge, couleur, objet] ou []]]
*/
params ["_dock", "_slots"];
private _data = "\z\comspec_atak_native\addons\main\data\";
// Emplacement libre : téléphone et écran transparents (le modèle porte sinon un téléphone par défaut).
private _none = "#(argb,8,8,3)color(0,0,0,0,ca)";
private _want = [];
private _screens = [];
private _leds = [];
{
    if ((count _x) < 4) then {
        _want pushBack _none;
        _screens pushBack _none;
        _leds pushBack "#(argb,8,8,3)color(0.03,0.03,0.03,1,co)";
    } else {
        _x params ["", "", "_lv", "_tint"];
        _want pushBack format ["%1vest_phone%2_co.paa", _data, ["", "_" + _tint] select (_tint in ["od", "black"])];
        _screens pushBack format ["%1dock_screen_%2_co.paa", _data, [floor ((_lv min 99.9) / 20), 5] select (_lv >= 100)];
        _leds pushBack (["#(argb,8,8,3)color(1,0.55,0.05,1,co)", "#(argb,8,8,3)color(0.15,1,0.3,1,co)"] select (_lv >= 100));
        if (_lv < 20) then { _leds set [_forEachIndex, "#(argb,8,8,3)color(1,0.08,0.05,1,co)"]; };
    };
} forEach _slots;
_want append _screens;
_want append _leds;
private _had = _dock getVariable ["COMSPEC_ATAK_DockTex", []];
{
    if ((_had param [_forEachIndex, "?"]) isNotEqualTo _x) then { _dock setObjectTextureGlobal [_forEachIndex, _x]; };
} forEach _want;
_dock setVariable ["COMSPEC_ATAK_DockTex", _want];
