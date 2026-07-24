/*
 * Auteur: COMSPEC
 * Convertit HashMap en string JSON
 * Helper function pour sérialisation avant envoi extension
 *
 * Arguments:
 * 0: HashMap <HASHMAP>
 *
 * Valeur de retour:
 * <STRING> - JSON string
 *
 * Exemple:
 * private _json = [_myHashMap] call comspec_overwatch_connect_fnc_hashMapToJson;
 */

params [
    ["_hashMap", createHashMap, [createHashMap]]
];

// Convertir hashmap en array de paires [key, value]
private _pairs = [];

{
    private _key = _x;
    private _value = _hashMap get _key;
    
    // Échapper chaînes et gérer types
    private _valueStr = "";
    
    switch (typeName _value) do {
        case "STRING": {
            // Échapper guillemets et backslashes
            private _escaped = _value;
            _escaped = _escaped splitString """" joinString "\""";
            _escaped = _escaped splitString "\" joinString "\\";
            _valueStr = format ["""%1""", _escaped];
        };
        case "SCALAR": {
            _valueStr = str _value;
        };
        case "BOOL": {
            _valueStr = if (_value) then {"true"} else {"false"};
        };
        case "ARRAY": {
            // Tableau simple
            private _elements = _value apply {
                if (typeName _x isEqualTo "STRING") then {
                    format ["""%1""", _x]
                } else {
                    str _x
                };
            };
            _valueStr = format ["[%1]", _elements joinString ","];
        };
        case "HASHMAP": {
            // Récursif pour sous-hashmap
            _valueStr = [_value] call comspec_overwatch_connect_fnc_hashMapToJson;
        };
        default {
            _valueStr = """unknown""";
        };
    };
    
    _pairs pushBack format ["""%1"":%2", _key, _valueStr];
} forEach (keys _hashMap);

// Assembler JSON
format ["{%1}", _pairs joinString ","]
