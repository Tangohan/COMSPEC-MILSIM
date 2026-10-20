/*
    Rend extractible au câble DL-7 tout support numérique généré par le SSE (serveur).
    Remplace les quinze actions ACE « Exploitation numérique » : on branche le téléphone ATAK
    sur l'appareil réellement présent, on force ou on saisit son code, on télécharge.
    [_entity] call comspec_sse_fnc_dl7Auto

    Sécurité tirée de la graine de la fiche (même résultat à chaque relance) :
      téléphone : sans code, code à 4 chiffres ou à 6 chiffres ; effacement après les essais (un sur trois)
      ordinateur : session libre ou mot de passe avec indice ; verrouillage après 3 essais
      clé USB, carte, radio : libres
    Un code écrit dans la fiche de l'appareil (pin / password) est toujours repris.
    Un objet configuré dans Eden (catégorie Extraction DL-7) garde ses réglages.
*/
params [["_e", objNull, [objNull]]];
if (!isServer || {isNull _e}) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_dl7Auto", true]) exitWith { false };
if (_e getVariable ["COMSPEC_SSE_Dl7_Enabled", false] || {_e isKindOf "CAManBase"}) exitWith { false };

private _devices = [_e, "digitalDevices"] call comspec_sse_fnc_getSection;
if (isNil "_devices" || {!(_devices isEqualType [])} || {_devices isEqualTo []}) exitWith { false };

// Rien de neuf depuis la dernière configuration : on ne retouche pas l'objet (essais, verrou gardés).
private _sig = hashValue (str _devices);
if (_sig isEqualTo (_e getVariable ["comspec_sse_dl7Sig", ""])) exitWith { false };
_e setVariable ["comspec_sse_dl7Sig", _sig];

private _seed = [_e] call comspec_sse_fnc_getSeed;
if (_seed < 0) then { _seed = [_e] call comspec_sse_fnc_entitySeed; };
private _roll = { params ["_salt", "_mod"]; ([_seed, _salt] call comspec_sse_fnc_hash) mod _mod };
private _digits = { params ["_n"]; private _s = ""; for "_i" from 1 to _n do { _s = _s + str (["pin" + str _i, 10] call _roll); }; _s };

private _d0 = (_devices select { _x isEqualType createHashMap }) param [0, createHashMap];
private _type = toUpper (_d0 getOrDefault ["deviceType", ""]);
private _kind = switch (true) do {
    case (_type in ["SMARTPHONE", "PHONE"]): { "telephone" };
    case (_type in ["LAPTOP"]): { "portable" };
    case (_type in ["COMPUTER", "PC"]): { "ordinateur" };
    case (_type in ["TABLET"]): { "tablette" };
    case (_type in ["USB", "SDCARD", "MEDIA", "HARDDRIVE"]): { "usb" };
    case (_type in ["RADIO"]): { "radio" };
    default { "autre" };
};

private _cfg = createHashMapFromArray [["content", "sse"], ["kind", _kind]];
private _known = _d0 getOrDefault ["pin", _d0 getOrDefault ["password", ""]];
if !(_known isEqualType "") then { _known = str _known; };
private _sec = ["security", 100] call _roll;

switch (_kind) do {
    case "telephone": {
        if (_known isNotEqualTo "" || {_sec >= 35}) then {
            private _six = _sec >= 85;
            _cfg set ["password", [_known, [[4, 6] select _six] call _digits] select (_known isEqualTo "")];
            _cfg set ["hint", ["Code à 4 chiffres", "Code à 6 chiffres"] select _six];
            _cfg set ["attempts", 5];
            _cfg set ["onFail", ["lock", "wipe"] select ((["wipe", 3] call _roll) isEqualTo 0)];
            _cfg set ["crack", true];
            _cfg set ["crackTime", [60 + (["crack", 60] call _roll), 150 + (["crack", 90] call _roll)] select _six];
        };
        _cfg set ["port", "usb"];
        // Un téléphone sur trois est sous contrôle à distance du réseau : sac de Faraday indispensable.
        if ((["rwipe", 3] call _roll) isEqualTo 0) then { [_e] call comspec_sse_fnc_remoteWipe; };
    };
    case "portable";
    case "ordinateur": {
        if (_known isNotEqualTo "" || {_sec >= 30}) then {
            private _words = ["sahara", "falcon", "baghdad", "amir", "zaytoun", "nour", "kilo", "omega"];
            private _w = _words select (["word", count _words] call _roll);
            _cfg set ["password", [_known, format ["%1%2", _w, [2] call _digits]] select (_known isEqualTo "")];
            _cfg set ["hint", format ["Mot de passe : mot courant suivi de 2 chiffres (%1…)", _w select [0, 2]]];
            _cfg set ["attempts", 3];
            _cfg set ["onFail", "lock"];
            _cfg set ["crack", true];
            _cfg set ["crackTime", 120 + (["crack", 120] call _roll)];
        };
        _cfg set ["port", ["usb", "rj45"] select (_kind isEqualTo "ordinateur")];
    };
    default {};
};

// Durée de téléchargement selon le volume réel de données.
private _files = ([_e] call comspec_sse_fnc_dl7FilesFromSse) select 0;
_cfg set ["duration", (20 + 3 * (count _files)) min 150];

[_e, _cfg] call comspec_sse_fnc_dl7Apply;
[format ["DL-7 auto %1 : %2, %3 fichier(s), code %4", _e, _kind, count _files, ["non", "oui"] select ("password" in _cfg)]] call comspec_sse_fnc_log;
true
