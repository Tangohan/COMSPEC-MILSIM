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
            private _escaped = _value;
            private _dq = toString [34];
            private _bs = toString [92];
            _escaped = (_escaped splitString _bs) joinString (_bs + _bs);
            _escaped = (_escaped splitString _dq) joinString (_bs + _dq);
            _valueStr = _dq + _escaped + _dq;
        };
        case "SCALAR": {
            // Point décimal invariant — `str` met une virgule FR et casse le JSON.
            if (_value == (floor _value) && {abs _value < 1e12}) then {
                _valueStr = str (round _value);
            } else {
                _valueStr = _value toFixed 4;
            };
        };
        case "BOOL": {
            _valueStr = if (_value) then {"true"} else {"false"};
        };
        case "ARRAY": {
            // Tableau simple
            private _elements = _value apply {
                switch (typeName _x) do {
                    case "STRING": {
                        private _dq = toString [34];
                        private _bs = toString [92];
                        private _esc = (_x splitString _bs) joinString (_bs + _bs);
                        _esc = (_esc splitString _dq) joinString (_bs + _dq);
                        _dq + _esc + _dq
                    };
                    case "SCALAR": {
                        if (_x == (floor _x) && {abs _x < 1e12}) then {
                            str (round _x)
                        } else {
                            _x toFixed 4
                        };
                    };
                    case "BOOL": { if (_x) then {"true"} else {"false"} };
                    case "HASHMAP": { [_x] call comspec_overwatch_connect_fnc_hashMapToJson };
                    default { """" };
                };
            };
            _valueStr = format ["[%1]", _elements joinString ","];
        };
        case "HASHMAP": {
            // Récursif pour sous-hashmap
            _valueStr = [_value] call comspec_overwatch_connect_fnc_hashMapToJson;
        };
        default {
            _valueStr = (toString [34]) + "unknown" + (toString [34]);
        };
    };
    
    _pairs pushBack format ["%1%2%1:%3", toString [34], _key, _valueStr];
} forEach (keys _hashMap);

// Assembler JSON
format ["{%1}", _pairs joinString ","]
