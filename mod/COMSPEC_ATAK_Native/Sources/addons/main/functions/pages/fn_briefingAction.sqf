/*
    Actions de l'app Briefing.
    ["tab", "SLIDES"|"MISSION"]  change d'onglet
    ["step", delta]              diapositive précédente / suivante
    ["goto", index]              saute à une diapositive
    ["refresh"]                  relit les diapositives publiées sur Athena
    ["present"]                  présente les diapositives Athena à son camp, ou arrête
    ["follow"]                   suit le présentateur ou navigue librement
    ["questions", id]            relit les questions de la diapositive id (-1 : celle en cours)
    ["ask"]                      envoie la question saisie (Athena + alerte au présentateur)
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
                private _live = ["get"] call comspec_atak_native_fnc_briefingLive;
                // Un auditeur qui suit et tourne une page passe en lecture libre.
                if ((count _live) > 0 && {(_live select 0) isNotEqualTo player} && {_s getOrDefault ["briefFollow", true]}) then {
                    _s set ["briefFollow", false];
                    ["INFO", "Lecture libre : touchez SUIVRE pour revenir au présentateur", 3, 20] call comspec_atak_native_fnc_notify;
                };
                missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", _target];
                if ((_live param [0, objNull]) isEqualTo player) then { ["publish"] call comspec_atak_native_fnc_briefingLive; };
                call _rerender;
            };
            default { [_target - _idx] call comspec_atak_native_fnc_briefingStep; };
        };
    };
    case "present": {
        private _live = ["get"] call comspec_atak_native_fnc_briefingLive;
        private _key = format ["COMSPEC_ATAK_BriefLive_%1", side group player];
        if ((_live param [0, objNull]) isEqualTo player) exitWith {
            missionNamespace setVariable [_key, [], true];
            ["INFO", "Présentation terminée", 3, 20] call comspec_atak_native_fnc_notify;
            call _rerender;
        };
        if ((count _live) > 0) exitWith { ["WARNING", format ["%1 présente déjà", _live select 1], 3, 20] call comspec_atak_native_fnc_notify; };
        if ((count (missionNamespace getVariable ["COMSPEC_BriefingSlides", []])) isEqualTo 0) exitWith { ["WARNING", "Aucune diapositive Athena à présenter : touchez ACTUALISER", 4, 20] call comspec_atak_native_fnc_notify; };
        ["publish"] call comspec_atak_native_fnc_briefingLive;
        ["SUCCESS", "Vous présentez : les téléphones de votre camp suivent vos diapositives", 4, 30] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "follow": {
        _s set ["briefFollow", !(_s getOrDefault ["briefFollow", true])];
        call _rerender;
    };
    case "questions": {
        private _id = _arg;
        if (_id isEqualTo -1) then {
            ([] call comspec_atak_native_fnc_briefingSignature) params ["", "_idx"];
            _id = ((missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) param [_idx, [-1]]) select 0;
        };
        private _q = createHashMapFromArray [["slide", _id], ["list", []], ["err", ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefQ", _q];
        private _raw = ["COMSPECExtension" callExtension ["BriefingComments", [str _id]]] call comspec_overwatch_connect_fnc_extResult;
        if ((_raw select [0, 3]) isNotEqualTo "OK|") then {
            _q set ["err", ["Athena ne répond pas.", "La DLL COMSPEC Link ne connaît pas encore les questions : mettez-la à jour."] select (_raw isEqualTo "")];
        } else {
            _q set ["list", ((_raw select [3]) splitString toString [10]) apply { [_x, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty }];
        };
        call _rerender;
    };
    case "ask": {
        private _txt = trim (["briefQ", ""] call comspec_atak_native_fnc_formValue);
        if (_txt isEqualTo "") exitWith { ["WARNING", "Écrivez votre question", 3, 20] call comspec_atak_native_fnc_notify; };
        private _id = (uiNamespace getVariable ["COMSPEC_ATAK_BriefQ", createHashMap]) getOrDefault ["slide", -1];
        if (_id isEqualTo -1) exitWith {};
        private _who = format ["%1 · %2", [player, true] call comspec_atak_native_fnc_unitCallsign, name player];
        private _raw = ["COMSPECExtension" callExtension ["BriefingComments", [str _id, _txt, _who]]] call comspec_overwatch_connect_fnc_extResult;
        if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { ["WARNING", "Question non envoyée : Athena ne répond pas", 4, 30] call comspec_atak_native_fnc_notify; };
        uiNamespace setVariable ["COMSPEC_ATAK_BriefQDraft", ""];
        // Le présentateur la voit tout de suite sur son téléphone.
        private _pres = (["get"] call comspec_atak_native_fnc_briefingLive) param [0, objNull];
        if (!isNull _pres && {_pres isNotEqualTo player}) then { ["comspec_atak_native_briefQ", [_who, _txt], _pres] call CBA_fnc_targetEvent; };
        ["SUCCESS", "Question envoyée", 3, 20] call comspec_atak_native_fnc_notify;
        ["questions", _id] call comspec_atak_native_fnc_briefingAction;
    };
    case "refresh": {
        if !([] call comspec_atak_native_fnc_bridge) exitWith {
            ["WARNING", "Diapositives Athena : COMSPEC Link requis", 4, 30] call comspec_atak_native_fnc_notify;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_SlideCache", createHashMap];
        private _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
        missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", 0];
        ["INFO", format ["%1 diapositive(s) publiée(s) sur Athena", count _slides], 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
};
true
