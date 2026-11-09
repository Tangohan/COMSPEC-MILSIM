/*
    Modules Zeus (ZEN) « COMSPEC · IA RP » (client, une fois ZEN chargé) :
      Atelier IA            sur une IA : elle et les IA sélectionnées par le curateur ; personnalité, dialogue,
                            bibliothèque, générer pour toutes, faire parler, import / export (fn_iarpWorkshop)
      Faire parler une IA   sur une IA : le Zeus tape sa réplique, bulle 3D au-dessus de sa tête et journal
      Journal des échanges  tous les échanges joueurs / IA, en direct (onglet JOURNAL du terminal)
    Tout est vérifié sur le serveur (fn_iarpServer : Zeus ou administrateur seulement).
*/
if (!hasInterface || {isNil "zen_custom_modules_fnc_register"}) exitWith { false };
private _cat = getText (configFile >> "CfgFactionClasses" >> "COMSPEC_Cat_IaRp" >> "displayName");
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
private _sel = {
    params ["_obj"];
    private _l = [];
    if (!isNull _obj) then { _l pushBack _obj; };
    { if (_x isEqualType objNull) then { _l pushBackUnique _x; }; } forEach (curatorSelected param [0, []]);
    _l select { _x isKindOf "CAManBase" && {!isPlayer _x} && {alive _x} }
};
missionNamespace setVariable ["comspec_sse_iarpZenSel", _sel];
[_cat, localize "STR_COMSPEC_IARP_Z_Workshop", {
    params ["_pos", "_obj"];
    ["open", ["zeus", [_obj] call comspec_sse_iarpZenSel]] call comspec_sse_fnc_iarpWorkshop;
}, _icon] call zen_custom_modules_fnc_register;
[_cat, localize "STR_COMSPEC_IARP_Z_Puppet", {
    params ["_pos", "_obj"];
    if (isNull _obj || {!(_obj isKindOf "CAManBase")} || {isPlayer _obj}) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoAi"]] call comspec_sse_fnc_iarpClient; };
    [localize "STR_COMSPEC_IARP_Z_Puppet", [["EDIT", [localize "STR_COMSPEC_IARP_Z_PuppetLine", localize "STR_COMSPEC_IARP_Z_PuppetLine_D"], ""]], {
        params ["_v", "_args"];
        ["send", ["puppet", [player, _args select 0, _v select 0]]] call comspec_sse_fnc_iarpClient;
    }, {}, [_obj]] call zen_dialog_fnc_create;
}, _icon] call zen_custom_modules_fnc_register;
[_cat, localize "STR_COMSPEC_IARP_Z_Log", {
    ["open", [objNull, "log"]] call comspec_sse_fnc_iarpUi;
}, _icon] call zen_custom_modules_fnc_register;
true
