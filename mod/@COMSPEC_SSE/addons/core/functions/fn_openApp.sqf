/*
    Lien profond vers une app du téléphone ATAK, pour tout le SSE (actions ACE, fenêtre d'un document, SEEK II…).
    [_page, _params, _prep, _after] call comspec_sse_fnc_openApp -> Boolean (false : pas de téléphone sur soi)
      Même contrat que comspec_atak_native_fnc_openApp (ATAK natif 4.2.x) : téléphone pris en main sur l'app, contexte
      _params (HashMap) pré-rempli par _prep avant le dessin, _after joué une fois l'app dessinée, ouverture reportée au
      déverrouillage, retour à l'écran d'avant par la touche retour.
      ATAK plus ancien (sans fn_openApp) : sortie en main puis navigation à l'image suivante, comme avant.
    L'app lit son contexte avec ["take", _page] call comspec_sse_fnc_openApp (HashMap, vide s'il n'y en a pas).
*/
if ((_this param [0, ""]) isEqualTo "take") exitWith {
    if (isNil "comspec_atak_native_fnc_openApp") exitWith {
        private _k = format ["comspec_sse_deep_%1", toUpper (_this param [1, ""])];
        private _v = uiNamespace getVariable [_k, createHashMap];
        uiNamespace setVariable [_k, nil];
        _v
    };
    ["take", _this param [1, ""]] call comspec_atak_native_fnc_openApp
};
params [["_page", "", [""]], ["_params", createHashMap, [createHashMap]], ["_prep", {}, [{}]], ["_after", {}, [{}]]];
if (!hasInterface || {_page isEqualTo ""}) exitWith { false };
if (!isNil "comspec_atak_native_fnc_openApp") exitWith { [_page, _params, _prep, _after] call comspec_atak_native_fnc_openApp };
if (isNil "comspec_atak_native_fnc_open" || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith { false };
uiNamespace setVariable [format ["comspec_sse_deep_%1", toUpper _page], _params];
_params call _prep;
if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
    [true] call comspec_atak_native_fnc_open;
};
[{ params ["_page", "_params", "_after"]; [_page] call comspec_atak_native_fnc_navigate; _params call _after; }, [toUpper _page, _params, _after]] call CBA_fnc_execNextFrame;
true
