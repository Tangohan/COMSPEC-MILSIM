/*
    comspec_fb_fnc_zenRegister : modules Zeus (Zeus Enhanced, facultatif), catégorie « COMSPEC Flashbang ».
      « Lancer flash »  : engin posé au point cliqué, nombre de détonations, grenade d'entraînement, mèche
                          (par défaut = réglage « Mèche », 3 s, 8 s ou personnalisée 0,5-20 s), son de la DRILL.
      « Effet de zone » : effet sur toutes les unités dans un rayon, sans grenade (scénario).
    Exécution côté serveur (événements CBA comspec_fb_serverDetonate / comspec_fb_serverArea).
*/
private _icon = "\z\comspec_flashbang\addons\main\ui\fb_module_ca.paa";
private _cat = "COMSPEC Flashbang";

[_cat, localize "STR_COMSPEC_FB_Zen_Throw", {
    params ["_pos"];
    // Position reçue de ZEN : ASL ou AGL selon les versions ; ramenée en ASL sans se tromper sur un étage.
    if ((abs ((_pos select 2) - (getTerrainHeightASL _pos))) >= ((abs (_pos select 2)) - 0.5)) then { _pos = AGLToASL _pos };
    private _keys = getArray (configFile >> "COMSPEC_FB_Sons" >> "drillChoix");
    [localize "STR_COMSPEC_FB_Zen_Throw", [
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Bursts", localize "STR_COMSPEC_FB_Module_Bursts_Tip"], [1, 9, 3, 0]],
        ["CHECKBOX", [localize "STR_COMSPEC_FB_Zen_Drill", localize "STR_COMSPEC_FB_Zen_Drill_Tip"], false],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_FuseMode", localize "STR_COMSPEC_FB_Zen_FuseMode_Tip"], [
            ["default", "3", "8", "custom"],
            [localize "STR_COMSPEC_FB_Fuse_DefaultZen", localize "STR_COMSPEC_FB_Fuse_3", localize "STR_COMSPEC_FB_Fuse_8", localize "STR_COMSPEC_FB_Fuse_Custom"],
            0]],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_FuseCustom", localize "STR_COMSPEC_FB_Module_FuseCustom_Tip"], [0.5, 20, 1.5, 1]],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_DrillSound", localize "STR_COMSPEC_FB_Module_DrillSound_Tip"], [
            [""] + _keys,
            [localize "STR_COMSPEC_FB_DrillSound_Setting"] + (_keys apply { localize format ["STR_COMSPEC_FB_DrillSound_%1", _x] }),
            0]]
    ], {
        params ["_values", "_pos"];
        _values params ["_n", "_drill", "_fuseMode", "_custom", "_snd"];
        _n = ((round _n) max 1) min 9;
        private _fuse = switch (_fuseMode) do {
            case "3": { 3 };
            case "8": { 8 };
            case "custom": { (_custom max 0.5) min 20 };
            default { ((missionNamespace getVariable ["comspec_fb_fuse", 1.5]) max 0.5) min 4 };
        };
        private _sfx = ["", format ["_D%1", _fuseMode]] select (_fuseMode in ["3", "8"]);
        private _ammo = if (_drill) then { "COMSPEC_FB_Ammo_Drill" + _sfx } else { format ["COMSPEC_FB_Ammo_%1%2", _n, _sfx] };
        ["comspec_fb_serverDetonate", [_ammo, _pos vectorAdd [0, 0, 0.05], [[0, 1, 0], [0, 0, 1]], [0, 0, 0], objNull,
            sideUnknown, _n, _fuse, -1, _snd]] call CBA_fnc_serverEvent;
    }, {}, _pos] call zen_dialog_fnc_create;
}, _icon] call zen_custom_modules_fnc_register;

[_cat, localize "STR_COMSPEC_FB_Zen_Area", {
    params ["_pos"];
    // Position reçue de ZEN : ASL ou AGL selon les versions ; ramenée en ASL sans se tromper sur un étage.
    if ((abs ((_pos select 2) - (getTerrainHeightASL _pos))) >= ((abs (_pos select 2)) - 0.5)) then { _pos = AGLToASL _pos };
    [localize "STR_COMSPEC_FB_Zen_Area", [
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Radius", localize "STR_COMSPEC_FB_Module_Radius_Tip"], [1, 50, 10, 0]],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Strength", localize "STR_COMSPEC_FB_Module_Strength_Tip"], [0.1, 1, 0.8, 2]],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Bursts", localize "STR_COMSPEC_FB_Module_Bursts_Tip"], [1, 9, 1, 0]],
        ["CHECKBOX", [localize "STR_COMSPEC_FB_Module_ShowFx", localize "STR_COMSPEC_FB_Module_ShowFx_Tip"], true],
        ["SLIDER", [localize "STR_COMSPEC_FB_Zen_AreaDelay", localize "STR_COMSPEC_FB_Zen_AreaDelay_Tip"], [0, 20, 0, 1]]
    ], {
        params ["_values", "_pos"];
        _values params ["_radius", "_strength", "_n", "_fx", "_lead"];
        ["comspec_fb_serverArea", [_pos vectorAdd [0, 0, 0.05], _radius, _strength, round _n, _fx, _lead]] call CBA_fnc_serverEvent;
    }, {}, _pos] call zen_dialog_fnc_create;
}, _icon] call zen_custom_modules_fnc_register;
