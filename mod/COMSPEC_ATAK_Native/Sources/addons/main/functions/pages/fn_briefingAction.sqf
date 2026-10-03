/*
    Actions de l'app Briefing.
    ["tab", "SLIDES"|"MISSION"]  change d'onglet
    ["step", delta]              diapositive précédente / suivante
    ["goto", index]              saute à une diapositive
    ["refresh"]                  relit les diapositives publiées sur Athena
*/
params [["_action", ""], ["_arg", 0]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _rerender = { [{ ["BRIEFING"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };

switch (_action) do {
    case "tab": { _s set ["briefTab", _arg]; call _rerender; };
    case "step";
    case "goto": {
        ([] call comspec_atak_native_fnc_briefingSignature) params ["_src", "_idx", "_total"];
        if (_total < 1) exitWith {};
        private _target = if (_action isEqualTo "step") then { ((_idx + _arg) mod _total + _total) mod _total } else { (_arg max 0) min (_total - 1) };
        if (_target isEqualTo _idx) exitWith {};
        switch (_src) do {
            // Google : chargement asynchrone par la DLL, la page se redessine quand l'image arrive.
            case "GOOGLE": {
                [_target - _idx] call comspec_overwatch_connect_fnc_googleBriefingStep;
                ["INFO", format ["Diapositive %1 / %2 en chargement…", _target + 1, _total], 2, 20] call comspec_atak_native_fnc_notify;
            };
            case "ATHENA": {
                missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", _target];
                call _rerender;
            };
            default { [_target - _idx] call comspec_atak_native_fnc_briefingStep; };
        };
    };
    case "refresh": {
        if !([] call comspec_atak_native_fnc_bridge) exitWith {
            ["WARNING", "Diapositives Athena : Overwatch connect requis", 4, 30] call comspec_atak_native_fnc_notify;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_SlideCache", createHashMap];
        private _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
        missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", 0];
        ["INFO", format ["%1 diapositive(s) publiée(s) sur Athena", count _slides], 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
};
true
