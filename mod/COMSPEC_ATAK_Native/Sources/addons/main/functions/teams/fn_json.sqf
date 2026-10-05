/*
    Valeur SQF -> texte JSON (HashMap -> objet, tableau -> liste, texte, nombre, booléen ; le reste -> null).
    Récursif, pour les envois structurés à Athena (escouades, temps d'écran).
*/
params ["_v"];
switch (typeName _v) do {
    case "HASHMAP": { "{" + ((keys _v) apply { ([_x] call comspec_atak_native_fnc_json) + ":" + ([_v get _x] call comspec_atak_native_fnc_json) } joinString ",") + "}" };
    case "ARRAY": { "[" + ((_v apply { [_x] call comspec_atak_native_fnc_json }) joinString ",") + "]" };
    case "STRING": {
        private _t = [_v, "\", "\\"] call CBA_fnc_replace;
        _t = [_t, """", "\"""] call CBA_fnc_replace;
        _t = [_t, toString [10], "\n"] call CBA_fnc_replace;
        _t = [_t, toString [13], ""] call CBA_fnc_replace;
        _t = [_t, toString [9], " "] call CBA_fnc_replace;
        """" + _t + """"
    };
    case "SCALAR": { if (_v isEqualTo (round _v) && {abs _v < 1e9}) then { str (round _v) } else { _v toFixed 3 } };
    case "BOOL": { ["false", "true"] select _v };
    default { "null" };
}
