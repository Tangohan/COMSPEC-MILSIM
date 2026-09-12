/*
    Clic sur un bouton d’action TASK (action mémorisée sur le contrôle).
    Repli : resynchronise les boutons / l’id sélectionné si l’action manque.
*/
params [["_ctrl", controlNull, [controlNull]]];

if (isNull _ctrl) exitWith {};

private _action = _ctrl getVariable ["COMSPEC_TaskAction", ""];
if (!(_action isEqualType "")) then { _action = ""; };

if (_action isEqualTo "") then {
    private _selId = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
    if (!(_selId isEqualType "") || {_selId isEqualTo ""}) then {
        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
        if (!isNull _group) then {
            private _list = _group controlsGroupCtrl 9902;
            if (!isNull _list) then {
                private _idx = lbCurSel _list;
                if (_idx >= 0) then {
                    [_list, _idx] call comspec_overwatch_atak_athena_fnc_athena_taskSelect;
                };
            };
        };
    } else {
        [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
    };
    _action = _ctrl getVariable ["COMSPEC_TaskAction", ""];
};

if (!(_action isEqualType "") || {_action isEqualTo ""}) exitWith {
    ["Sélectionnez un ordre, puis choisissez une action.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

[_action] call comspec_overwatch_atak_athena_fnc_athena_taskRespond;
