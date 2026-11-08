/*
    Archive sur le site (/m/radio/prives) des SMS que ce joueur a envoyés et qui sont arrivés, au format MP|de|à|texte, envoyés par COMSPEC Link comme les messages privés cTab.
    Seulement le joueur lui-même : jamais un SMS reçu, jamais le contenu du téléphone d'un autre. Rien sans compte lié (COMSPEC Link prêt). Mode avion : le SMS attend ici et part à la sortie du mode avion.
    Le destinataire le voit aussi sur son compte : le champ « à » porte son indicatif et son nom, le site reconnaît l'un ou l'autre.
      ["send", message] : depuis fn_p2pAck, au premier accusé ; ["flush"] : tick de 2 s, sans travail si la file est vide
*/
params [["_act", ""], ["_arg", createHashMap]];
private _clean = { params ["_t"]; ((((_t splitString "|") joinString " · ") splitString toString [10]) joinString " · ") splitString toString [13] joinString "" };
switch (_act) do {
    case "send": {
        private _m = _arg;
        if (_m getOrDefault ["siteDone", false]) exitWith {};
        _m set ["siteDone", true];
        if (_m getOrDefault ["noSite", false] || {!([] call comspec_atak_native_fnc_bridge)}) exitWith {};
        private _q = missionNamespace getVariable ["COMSPEC_ATAK_P2pArch", []];
        _q pushBack [_m getOrDefault ["siteTo", _m getOrDefault ["peer", ""]], _m getOrDefault ["body", ""]];
        while { (count _q) > 50 } do { _q deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_P2pArch", _q];
        ["flush"] call comspec_atak_native_fnc_p2pArchive;
    };
    case "flush": {
        private _q = missionNamespace getVariable ["COMSPEC_ATAK_P2pArch", []];
        if (_q isEqualTo []) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) exitWith {};
        if (isNil "comspec_overwatch_connect_fnc_sendIntel" || {isNil "comspec_overwatch_connect_fnc_isReady"} || {!([] call comspec_overwatch_connect_fnc_isReady)}) exitWith {};
        if (!isNil "comspec_overwatch_connect_fnc_canStartSync" && {!([] call comspec_overwatch_connect_fnc_canStartSync)}) exitWith {};
        private _from = if (isNil "comspec_overwatch_connect_fnc_getCallsign") then { "" } else { [] call comspec_overwatch_connect_fnc_getCallsign };
        if (isNil "_from" || {_from isEqualTo ""}) then { _from = name player; };
        // Au plus 5 par passage : un retour de réseau ne doit pas saturer COMSPEC Link.
        for "_i" from 1 to 5 do {
            if (_q isEqualTo []) exitWith {};
            (_q deleteAt 0) params ["_to", "_body"];
            [player, "CHAT", format ["MP|%1|%2|%3", [_from] call _clean, [_to] call _clean, trim ([_body] call _clean)], "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;
        };
    };
};
