/*
    comspec_fb_fnc_zenRegister : modules Zeus (Zeus Enhanced, facultatif), catégorie « COMSPEC Flashbang ».
      « Lancer un flashbang » : engin posé au point cliqué, nombre de détonations, grenade d'entraînement, délai.
      « Effet sur une zone »  : effet sur toutes les unités dans un rayon, sans grenade (scénario).
    Exécution côté serveur (comspec_fb_fnc_serverDetonate / serverArea).
*/
private _icon = "\z\comspec_flashbang\addons\main\ui\fb_module_ca.paa";
private _cat = "COMSPEC Flashbang";

[_cat, localize "STR_COMSPEC_FB_Zen_Throw", {
    params ["_pos"];
    // Position reçue de ZEN : ASL ou AGL selon les versions ; ramenée en ASL sans se tromper sur un étage.
    if ((abs ((_pos select 2) - (getTerrainHeightASL _pos))) >= ((abs (_pos select 2)) - 0.5)) then { _pos = AGLToASL _pos };
    [localize "STR_COMSPEC_FB_Zen_Throw", [
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Bursts", localize "STR_COMSPEC_FB_Module_Bursts_Tip"], [1, 9, 3, 0]],
        ["CHECKBOX", [localize "STR_COMSPEC_FB_Zen_Drill", localize "STR_COMSPEC_FB_Zen_Drill_Tip"], false],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Delay", localize "STR_COMSPEC_FB_Module_Delay_Tip"], [0, 10, 1.5, 1]]
    ], {
        params ["_values", "_pos"];
        _values params ["_n", "_drill", "_delay"];
        _n = ((round _n) max 1) min 9;
        private _ammo = if (_drill) then { "COMSPEC_FB_Ammo_Drill" } else { format ["COMSPEC_FB_Ammo_%1", _n] };
        [_ammo, _pos vectorAdd [0, 0, 0.05], [[0, 1, 0], [0, 0, 1]], [0, 0, 0], objNull, sideUnknown, _n, _delay]
            remoteExecCall ["comspec_fb_fnc_serverDetonate", 2];
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
        ["CHECKBOX", [localize "STR_COMSPEC_FB_Module_ShowFx", localize "STR_COMSPEC_FB_Module_ShowFx_Tip"], true]
    ], {
        params ["_values", "_pos"];
        _values params ["_radius", "_strength", "_n", "_fx"];
        [_pos vectorAdd [0, 0, 0.05], _radius, _strength, round _n, _fx, 0] remoteExecCall ["comspec_fb_fnc_serverArea", 2];
    }, {}, _pos] call zen_dialog_fnc_create;
}, _icon] call zen_custom_modules_fnc_register;
