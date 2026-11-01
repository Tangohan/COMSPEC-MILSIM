/*
    Charge une tenue de la communauté : [tenue (10), infos étendues (HashMap)] au format ACE, ou [] (raison dans
    COMSPEC_ArsenalCloudLoadoutError : missing / too_large). À appeler depuis un spawn (extAwait : rien ne fige).
    Params : [identifiant, empreinte du site (colonne sha256 de la liste, facultative)].
    Cache de session par identifiant ET empreinte du site : une tenue modifiée sur le site est rechargée. Son
    empreinte de contenu (arsenalHash) est gardée dans COMSPEC_ArsenalSiteContent (même clé) pour l'état de synchro.
    Détail de plus de ~8 Ko : GetWardrobe répond « CHUNKED » et les morceaux arrivent par GetWardrobeChunk
    (« index \t total \t morceau »). Le morceau était lu comme le 3e champ d'un découpage par tabulation, alors que le
    premier morceau contient lui-même « id \t nom \t … » : toutes les tenues denses échouaient (« introuvable »),
    à l'aperçu, à l'import comme à « Enfiler ».
*/
params [["_id", "", [""]], ["_siteSha", "", [""]]];

private _setErr = { missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", _this, false]; };
if (_id isEqualTo "") exitWith { "missing" call _setErr; [] };
"" call _setErr;

private _cache = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadouts", nil];
if (isNil "_cache") then {
    _cache = createHashMap;
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadouts", _cache, false];
};
private _key = _id + "|" + toLower _siteSha;
if (_key in _cache) exitWith { _cache get _key };

// Texte coupé aux tabulations, champs vides gardés ; au-delà de _max champs, le reste forme le dernier.
private _fnSplit = {
    params ["_s", "_max"];
    private _tab = toString [9];
    private _out = [];
    private _i = _s find _tab;
    while { _i >= 0 && {(count _out) < (_max - 1)} } do { _out pushBack (_s select [0, _i]); _s = _s select [_i + 1]; _i = _s find _tab; };
    _out pushBack _s;
    _out
};
private _fnStoreName = {
    params ["_wid", "_wname"];
    private _names = missionNamespace getVariable ["COMSPEC_ArsenalCloudNames", nil];
    if (isNil "_names") then {
        _names = createHashMap;
        missionNamespace setVariable ["COMSPEC_ArsenalCloudNames", _names, false];
    };
    if (_wname isNotEqualTo "") then { _names set [_wid, _wname]; };
};

private _raw = [["COMSPECExtension", "GetWardrobe", [_id]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {
    private _err = if (_raw isEqualType "") then { _raw } else { str _raw };
    ([ "missing", "too_large"] select ((_err find "too_large") >= 0)) call _setErr;
    []
};

private _body = _raw select [3];
private _assembled = _body;
private _name = "";

if ((_body select [0, 8]) isEqualTo ("CHUNKED" + toString [9])) then {
    // CHUNKED · id · nom · morceaux · taille · longueur
    private _meta = [_body, 6] call _fnSplit;
    if ((count _meta) < 4) exitWith { _assembled = ""; };
    _name = _meta select 2;
    [_meta select 1, _name] call _fnStoreName;
    private _chunks = (parseNumber (_meta select 3)) max 1;
    private _buf = "";
    private _ok = true;
    for "_ci" from 0 to (_chunks - 1) do {
        private _cRaw = [["COMSPECExtension", "GetWardrobeChunk", [_id, str _ci]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
        if (!(_cRaw isEqualType "") || {_cRaw find "OK|" != 0}) exitWith { _ok = false; };
        private _cParts = [_cRaw select [3], 3] call _fnSplit;
        if ((count _cParts) < 3) exitWith { _ok = false; };
        _buf = _buf + (_cParts select 2);
    };
    _assembled = if (_ok) then { _buf } else { "" };
    if (_assembled isEqualTo "") then { "too_large" call _setErr; };
};
if (_assembled isEqualTo "") exitWith {
    if ((missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadoutError", ""]) isEqualTo "") then { "missing" call _setErr; };
    []
};

private _parts = [_assembled, 3] call _fnSplit;
if ((count _parts) < 3) exitWith { "missing" call _setErr; [] };
if (_name isEqualTo "") then { _name = _parts select 1; };
[_parts select 0, _name] call _fnStoreName;

private _ext = ["extended", _parts select 2] call comspec_overwatch_connect_fnc_arsenalAceStore;
if (_ext isEqualTo []) exitWith { "missing" call _setErr; [] };

_cache set [_key, _ext];
if (_siteSha isNotEqualTo "") then {
    private _sc = [_ext] call comspec_overwatch_connect_fnc_arsenalHash;
    if (_sc isNotEqualTo "") then {
        private _content = missionNamespace getVariable ["COMSPEC_ArsenalSiteContent", nil];
        if (isNil "_content") then { _content = createHashMap; missionNamespace setVariable ["COMSPEC_ArsenalSiteContent", _content, false]; };
        _content set [_key, _sc];
    };
};
_ext
