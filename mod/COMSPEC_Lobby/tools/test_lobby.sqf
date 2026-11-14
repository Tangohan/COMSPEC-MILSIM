/*
    Essai en jeu (Eden, solo, console de débogage) : logique pure du mod COMSPEC Lobby, sans écran des rôles ni serveur.
    Colle ce fichier dans la console (exec local) avec @COMSPEC_Lobby et CBA chargés. Résultat : lignes LOBBYTEST|OK / KO au RPT.
    Ne couvre PAS l'écran des rôles, #kick, ni ZEN : voir README, « État et premier essai ».
*/
private _fail = 0;
private _ok = 0;
private _t = {
    params ["_name", "_cond"];
    if (_cond) then { _ok = _ok + 1; diag_log format ["LOBBYTEST|OK|%1", _name]; } else { _fail = _fail + 1; diag_log format ["LOBBYTEST|KO|%1", _name]; };
};

// Étiquettes
["étiquette simple", ("[A-1] Chef de groupe" call { [_this] call comspec_lobby_fnc_tagParse }) isEqualTo ["A-1", false]] call _t;
["étiquette protégée", ("[A-1|P] Chef de groupe" call { [_this] call comspec_lobby_fnc_tagParse }) isEqualTo ["A-1", true]] call _t;
["étiquette protégée minuscule", ("[B2|p] Fusilier" call { [_this] call comspec_lobby_fnc_tagParse }) isEqualTo ["B2", true]] call _t;
["sans étiquette", ("Fusilier" call { [_this] call comspec_lobby_fnc_tagParse }) isEqualTo ["", false]] call _t;
["crochets vides", ("[] Fusilier" call { [_this] call comspec_lobby_fnc_tagParse }) isEqualTo ["", false]] call _t;
["étiquette avec espaces", ("[ A-1 | P ] Chef" call { [_this] call comspec_lobby_fnc_tagParse }) select 0 isEqualTo "A-1"] call _t;

// Table des codes (serveur : Eden solo)
if (isServer) then {
    missionNamespace setVariable ["COMSPEC_LOBBY_AthenaPins", [["T-ATH", "1111"], ["T-BOTH", "2222"], ["T-BAD", "12a4"]]];
    [] call comspec_lobby_fnc_pinLoad;
    private _tbl = missionNamespace getVariable ["comspec_lobby_pins", createHashMap];
    ["Athena : code valide chargé", ((_tbl getOrDefault ["T-ATH", []]) param [0, ""]) isEqualTo "1111"] call _t;
    ["Athena : source notée", ((_tbl getOrDefault ["T-ATH", []]) param [1, ""]) isEqualTo "ATHENA"] call _t;
    ["code invalide refusé", !("T-BAD" in _tbl)] call _t;
    ["étiquette en majuscules", ("T-BOTH" in _tbl)] call _t;
    ["comspec_lobby_pinSet", [ "t-zeus", "4821" ]] call CBA_fnc_localEvent;
    _tbl = missionNamespace getVariable ["comspec_lobby_pins", createHashMap];
    ["Zeus : code posé (appel local accepté)", ((_tbl getOrDefault ["T-ZEUS", []]) param [1, ""]) isEqualTo "ZEUS"] call _t;
    ["comspec_lobby_pinSet", ["T-ZEUS", ""]] call CBA_fnc_localEvent;
    _tbl = missionNamespace getVariable ["comspec_lobby_pins", createHashMap];
    ["Zeus : code retiré", !("T-ZEUS" in _tbl)] call _t;
    ["comspec_lobby_pinSet", ["T-NOPE", "12"]] call CBA_fnc_localEvent;
    ["Zeus : code de 2 chiffres refusé", !("T-NOPE" in (missionNamespace getVariable ["comspec_lobby_pins", createHashMap]))] call _t;
    // Nettoyage : ce qu'on a posé pour l'essai ne reste pas dans le profil du serveur.
    missionNamespace setVariable ["COMSPEC_LOBBY_AthenaPins", nil];
    profileNamespace setVariable ["COMSPEC_LOBBY_Pins", []];
    [] call comspec_lobby_fnc_pinLoad;
};
diag_log format ["LOBBYTEST|FIN|ok=%1|ko=%2", _ok, _fail];
