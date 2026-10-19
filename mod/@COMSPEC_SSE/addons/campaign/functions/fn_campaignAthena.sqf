/*
    Campagnes SSE ⇄ Athena (serveur, COMSPEC Link 2.0.67). Dans Athena : Bureau SSE › Campagnes,
    gestion (copie pour un drill, remise à zéro, archivage) et catalogue partagé entre communautés.

    [_mode, _args, _requester] call comspec_sse_fnc_campaignAthena
      "push"  [id]                     envoie la sauvegarde (vide = campagne en cours)
      "list"  []                       campagnes présentes dans Athena → formulaire Zeus du demandeur
      "pull"  [clé, nouvel id, charger] récupère le texte, l'importe, et le charge si demandé

    Le texte voyage en morceaux (entrée et sortie de callExtension limitées) : 4000 caractères
    à l'envoi, 6000 octets au retour.
*/
params [["_mode", "", [""]], ["_args", [], [[]]], ["_req", objNull, [objNull]]];
if (!isServer) exitWith { ["comspec_sse_campAthena", [_mode, _args, player]] call CBA_fnc_serverEvent; false };
if (!canSuspend) exitWith { [_mode, _args, _req] spawn comspec_sse_fnc_campaignAthena; true };
// Demande d'un client : Zeus ou administrateur seulement (envoi et récupération touchent les sauvegardes du serveur).
if (!isNull _req && {!([_req] call comspec_sse_fnc_isAuthority)}) exitWith {
    diag_log text format ["[COMSPEC SSE] Campagne Athena : « %1 » refusé à %2 (ni Zeus ni administrateur).", _mode, name _req];
    ["comspec_sse_campNote", ["action réservée au Zeus ou à l'administrateur."], _req] call CBA_fnc_targetEvent;
    false
};

private _note = {
    params ["_msg"];
    diag_log text ("[COMSPEC SSE] Campagne Athena : " + _msg);
    if (!isNull _req) then { ["comspec_sse_campNote", [_msg], _req] call CBA_fnc_targetEvent; };
};
private _err = {
    params ["_r"];
    private _code = _r select [4];
    switch (true) do {
        case (_r isEqualTo ""): { "COMSPEC Link 2.0.67 requis sur le serveur" };
        case (_code isEqualTo "unauthorized"): { "le serveur n'est pas relié à la communauté dans Athena (clé d'accès)" };
        case (_code isEqualTo "not_found"): { "campagne absente d'Athena" };
        default { _code };
    }
};
private _slug = { params ["_s"]; toUpper (((trim _s) splitString " '/\.,:;") joinString "-") };

switch (toLower _mode) do {
    case "push": {
        _args params [["_id", "", [""]]];
        if (_id isEqualTo "") then { _id = (missionNamespace getVariable ["comspec_sse_campaign", createHashMap]) getOrDefault ["id", ""]; };
        _id = [_id] call _slug;
        private _raw = profileNamespace getVariable [format ["comspec_sse_camp_%1", _id], []];
        if (_raw isEqualTo []) exitWith { ["rien à envoyer : sauvegardez d'abord la campagne."] call _note; false };
        private _text = str ["COMSPEC_SSE_CAMPAIGN", 1, _raw];
        private _size = 4000;
        private _parts = ceil ((count _text) / _size) max 1;
        private _who = if (isNull _req) then { "Serveur de jeu" } else { name _req };
        private _r = "";
        for "_i" from 0 to (_parts - 1) do {
            _r = ["SseCampaignPush", [_id, _i, _parts, _text select [_i * _size, _size], _who]] call comspec_sse_fnc_extensionCall;
            if ((_r find "OK|") != 0) exitWith {};
        };
        if ((_r find "OK|") != 0) exitWith { [format ["envoi refusé : %1.", [_r] call _err]] call _note; false };
        private _bits = _r splitString "|";
        private _msg = format ["« %1 » %2 dans Athena (Bureau SSE › Campagnes).", _id, ["mise à jour", "ajoutée"] select ((_bits param [1, "0"]) isEqualTo "1")];
        if ((parseNumber (_bits param [2, "0"])) > 0) then {
            _msg = _msg + " ATTENTION : une autre campagne utilise la même graine biométrique, voir la fiche dans Athena.";
        };
        [_msg] call _note;
        true
    };

    case "list": {
        private _r = ["SseCampaignList", []] call comspec_sse_fnc_extensionCall;
        if ((_r find "OK|") != 0) exitWith { [format ["liste indisponible : %1.", [_r] call _err]] call _note; false };
        private _rows = ((_r select [3]) splitString endl) select { _x isNotEqualTo "" } apply { _x splitString toString [9] };
        _rows = _rows select { (count _x) >= 3 };
        if (_rows isEqualTo []) exitWith { ["aucune campagne dans Athena pour cette communauté."] call _note; false };
        if (!isNull _req) then { ["comspec_sse_campAthenaList", [_rows], _req] call CBA_fnc_targetEvent; };
        true
    };

    case "pull": {
        _args params [["_key", "", [""]], ["_newId", "", [""]], ["_load", false, [false]]];
        _key = [_key] call _slug;
        if (_key isEqualTo "") exitWith { false };
        private _text = "";
        private _offset = 0;
        private _total = -1;
        private _ok = true;
        private _guard = 0;
        while { _ok && {_total < 0 || {_offset < _total}} && {_guard < 2000} } do {
            _guard = _guard + 1;
            private _r = ["SseCampaignPull", [_key, _offset]] call comspec_sse_fnc_extensionCall;
            if ((_r find "OK|") != 0) then {
                _ok = false;
                [format ["récupération refusée : %1.", [_r] call _err]] call _note;
            } else {
                // OK|total|suivant|morceau : le morceau peut contenir des « | ».
                private _rest = _r select [3];
                private _p1 = _rest find "|";
                _total = parseNumber (_rest select [0, _p1]);
                _rest = _rest select [_p1 + 1];
                private _p2 = _rest find "|";
                private _next = parseNumber (_rest select [0, _p2]);
                _text = _text + (_rest select [_p2 + 1]);
                if (_next <= _offset) then { _ok = _total == 0; _offset = _total; } else { _offset = _next; };
            };
        };
        if (!_ok) exitWith { false };
        private _id = [[_newId, _key] select (_newId isEqualTo "")] call _slug;
        if !(["import", [_text, _id], _req] call comspec_sse_fnc_campaignOp) exitWith { false };
        if (_load) then {
            ["load", [_id, "", "", "", (missionNamespace getVariable ["comspec_sse_campaign", createHashMap]) getOrDefault ["auto", 0]], _req] call comspec_sse_fnc_campaignOp;
        };
        true
    };
    default { false };
}
