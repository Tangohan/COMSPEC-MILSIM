/*
    Variables d'une réplique remplacées dans une langue (pur, toute machine).
    [modèle, variables, langue "fr" | "en"] call comspec_sse_fnc_iarpFill -> texte
      variables  HashMap clé -> texte, ou [texte fr, texte en]. Clé absente : la marque {…} est retirée.
    Exemple : ["Je viens de {city}.", createHashMapFromArray [["city", "Kavala"]], "fr"] -> "Je viens de Kavala."
*/
params [["_tpl", "", [""]], ["_vars", createHashMap, [createHashMap]], ["_lang", "fr", [""]]];
if ((_tpl find "{") < 0) exitWith { _tpl };
private _s = _tpl;
private _i = [0, 1] select (_lang isEqualTo "en");
{
    if ((_s find ("{" + _x + "}")) >= 0) then {
        private _v = _y;
        if (_v isEqualType []) then { _v = _v param [_i, _v param [0, ""]]; };
        if !(_v isEqualType "") then { _v = str _v; };
        _s = [_s, "{" + _x + "}", _v] call CBA_fnc_replace;
    };
} forEach _vars;
// Marques restées sans valeur : retirées (jamais de « {city} » à l'écran).
private _guard = 0;
while { _guard < 20 && {(_s find "{") >= 0} } do {
    _guard = _guard + 1;
    private _a = _s find "{";
    private _b = (_s select [_a]) find "}";
    if (_b < 0) exitWith {};
    _s = (_s select [0, _a]) + "…" + (_s select [_a + _b + 1]);
};
_s
