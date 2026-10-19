/*
    Touches COMSPEC dans la langue du mod (Options > Contrôles > Configurer les addons) : les raccourcis d'ATAK natif,
    d'Overwatch et du SSE sont déclarés avec un nom et une aide en français ; CBA les range dans
    cba_keybinding_actions (clé « addon$action » : [nom, aide, touches, ...]) et relit le nom à chaque ouverture du
    menu. On repart du texte français gardé (COMSPEC_ATAK_KeyFr) et on le traduit avec le dictionnaire du mod.
    Retour joueur (Workshop, 18/10) : « The controls are all in French! » alors que le reste du mod était en anglais.
    Appelé après la déclaration de toutes les touches (XEH_postInitClient, image suivante) et à chaque changement de
    langue (fn_langApply).
*/
if (!hasInterface || {isNil "cba_keybinding_actions"}) exitWith {};
private _lang = missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"];
private _fr = uiNamespace getVariable ["COMSPEC_ATAK_KeyFr", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_KeyFr", _fr];
private _n = 0;
{
    if ((_x find "comspec") isEqualTo 0) then {
        private _info = cba_keybinding_actions getVariable _x;
        if (!isNil "_info" && {_info isEqualType []} && {(count _info) >= 2}) then {
            private _base = _fr getOrDefault [_x, [_info select 0, _info select 1]];
            _fr set [_x, _base];
            _base params ["_name", "_tip"];
            if (_lang isNotEqualTo "fr") then {
                if (_name isEqualType "" && {_name isNotEqualTo ""}) then { _name = _name call comspec_atak_native_fnc_tr; };
                if (_tip isEqualType "" && {_tip isNotEqualTo ""}) then { _tip = _tip call comspec_atak_native_fnc_tr; };
            };
            _info set [0, _name];
            _info set [1, _tip];
            _n = _n + 1;
        };
    };
} forEach (allVariables cba_keybinding_actions);
diag_log format ["[COMSPEC ATAK NATIVE][INFO][I18N] Touches COMSPEC en %1 : %2 raccourcis", _lang, _n];
