/*
    Applique la police choisie (fn_fontSet) à un contrôle qui vient d'être créé depuis une classe COMSPEC_Rsc… :
    les classes de config portent Barlow Condensed, police par défaut. Sans effet tant que Barlow est choisie.
    Le gras suit la classe (boutons, pastilles, libellés de champ) ; un ctrlSetFont posé ensuite par la page l'emporte.
    Params : [contrôle, nom de la classe, forcer (contrôles fixes du display, qui gardent la police précédente)]
*/
params ["_c", ["_class", ""], ["_force", false]];
if (!(_force || {missionNamespace getVariable ["COMSPEC_ATAK_FontAlt", false]}) || {isNull _c}) exitWith {};
private _w = switch (true) do {
    case (_class in ["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary", "COMSPEC_RscBadge", "COMSPEC_RscLabel", "COMSPEC_RscChip", "Title"]): { 1 };
    case (_class in ["COMSPEC_RscText", "COMSPEC_RscTextRight", "COMSPEC_RscTextCenter", "COMSPEC_RscTextCenterMulti",
        "COMSPEC_RscEdit", "COMSPEC_RscEditMulti", "COMSPEC_RscCombo", "COMSPEC_RscListBox", "Clock", "WeatherText"]): { 0 };
    default { -1 };
};
if (_w >= 0) then { _c ctrlSetFont (COMSPEC_ATAK_FontSet select _w); };
