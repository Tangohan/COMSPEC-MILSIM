/*
    Tinder : profils particuliers qu'on croise de temps en temps, tirés des IA de la carte.
      HVT : IA marquée HVT par le Zeus (module « Tinder : profil HVT / SSE ») ou personne SSE au profil « Chef / HVT ».
      SSE : IA fouillable par le SSE (comspec_sse_enabled) ou marquée SSE par le Zeus.
    Leur fréquence suit le réglage de mission « Tinder : profils HVT et SSE » (Jamais, Rare, Parfois, Souvent) ;
    seules les IA à moins de 5 km apparaissent. Leur photo est le vrai visage de l'IA (fn_datingCam) : à reconnaître.
    Un match ouvre une conversation qui livre des indices (lieu-dit le plus proche, carroyage approximatif, heure).
    Params :
      ["pool"]              → IA éligibles, les plus proches d'abord
      ["hvts"]              → HVT vivants de toute la carte (chasse : tuyaux des informateurs, fn_hvtIntel)
      ["kind", unité]       → "HVT", "SSE" ou ""
      ["key", unité]        → identifiant du profil ("ai:" + netId)
      ["unit", clé]         → unité de ce profil (objNull si disparue)
      ["profile", unité]    → HashMap name, age, bio, tags, kind, dist
      ["roll"]              → profil particulier à montrer au prochain tour, tiré selon le réglage (objNull sinon)
      ["clue", unité]       → [message d'indice, position floue, vrai] ; un HVT ment une fois sur quatre (rendez-vous
                               piège à 1 ou 2 km de lui) : l'indice entre dans son dossier comme tuyau « contact direct »
      ["chance", unité]     → probabilité de match (0 à 1)
*/
params [["_act", "pool"], ["_arg", objNull]];
private _seed = { params ["_s", "_n"]; private _h = 0; { _h = (_h * 31 + _x) mod 100003; } forEach toArray _s; (_h mod (_n max 1)) };
private _kindOf = {
    params ["_u"];
    private _z = toUpper (_u getVariable ["COMSPEC_ATAK_TinderKind", ""]);
    if (_z in ["HVT", "SSE"]) exitWith { _z };
    if (_z isEqualTo "OFF") exitWith { "" };
    if ((toUpper (_u getVariable ["comspec_sse_profile", ""])) in ["HVT", "COMMANDER"]) exitWith { "HVT" };
    if (_u getVariable ["comspec_sse_enabled", false]) exitWith { "SSE" };
    ""
};
switch (_act) do {
    case "kind": { [_arg] call _kindOf };
    case "key": { format ["ai:%1", _arg call BIS_fnc_netId] };
    case "unit": {
        private _u = (_arg select [3]) call BIS_fnc_objectFromNetId;
        [objNull, _u] select (!isNil "_u" && {_u isEqualType objNull})
    };
    case "pool": {
        if ((missionNamespace getVariable ["comspec_atak_native_dating_specials", 1]) <= 0) exitWith { [] };
        private _list = allUnits select {
            !isPlayer _x && {alive _x} && {_x isKindOf "CAManBase"} && {(player distance2D _x) < 5000} && {([_x] call _kindOf) isNotEqualTo ""}
        };
        [_list, [], { player distance2D _x }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "hvts": {
        if ((missionNamespace getVariable ["comspec_atak_native_hvt_tips", 2]) <= 0) exitWith { [] };
        allUnits select { !isPlayer _x && {alive _x} && {_x isKindOf "CAManBase"} && {([_x] call _kindOf) isEqualTo "HVT"} }
    };
    case "roll": {
        private _p = [0, 0.1, 0.25, 0.5] select ((missionNamespace getVariable ["comspec_atak_native_dating_specials", 1]) max 0 min 3);
        if (random 1 >= _p) exitWith { objNull };
        private _seen = (missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []]) + (missionNamespace getVariable ["COMSPEC_ATAK_RencardPassed", []]);
        private _left = (["pool"] call comspec_atak_native_fnc_datingSpecials) select { !((["key", _x] call comspec_atak_native_fnc_datingSpecials) in _seen) };
        if (_left isEqualTo []) exitWith { objNull };
        // Plutôt les plus proches : la première moitié de la liste.
        _left select floor (random (ceil ((count _left) / 2)))
    };
    case "profile": {
        private _u = _arg;
        private _kind = [_u] call _kindOf;
        private _first = trim (_u getVariable ["COMSPEC_SSE_FirstName", ""]);
        private _alias = trim (_u getVariable ["COMSPEC_SSE_Alias", ""]);
        // Nom du Zeus, sinon surnom (HVT) ou prénom, comme sur l'app.
        private _name = trim (_u getVariable ["COMSPEC_ATAK_TinderName", ""]);
        if (_name isEqualTo "") then {
            _name = switch (true) do {
                case (_kind isEqualTo "HVT" && {_alias isNotEqualTo ""}): { _alias };
                case (_first isNotEqualTo ""): { _first };
                default { ((name _u) splitString " ") param [0, name _u] };
            };
        };
        private _key = format ["%1%2", _u call BIS_fnc_netId, name _u];
        private _bios = if (_kind isEqualTo "HVT") then {
            ["Entrepreneur, je voyage beaucoup. Discret.", "J'aime le pouvoir, les belles voitures et la loyauté.", "Toujours bien entouré, rarement seul.",
             "Import-export. Ne pose pas trop de questions.", "Je cherche quelqu'un de fidèle. Très fidèle.", "Mes amis disent que je suis difficile à trouver."]
        } else {
            ["Simple habitant, je connais tout le monde ici.", "Commerçant le jour, je cherche quelqu'un de sérieux.", "Je répare tout ce qui a un moteur.",
             "Je connais les routes que personne ne prend.", "Je rends des services. Beaucoup de services.", "Pas de photo de groupe, merci."]
        };
        private _tags = if (_kind isEqualTo "HVT") then { ["Voitures", "Montres", "Téléphones jetables", "Thé", "Échecs", "Chasse", "Cigares", "Discrétion"] }
            else { ["Marché", "Football", "Mécanique", "Radio", "Famille", "Cuisine", "Moto", "Café"] };
        private _custom = trim (_u getVariable ["COMSPEC_ATAK_TinderBio", ""]);
        private _t = [_key, count _tags] call _seed;
        private _d = player distance2D _u;
        createHashMapFromArray [
            ["name", _name], ["kind", _kind], ["age", 24 + ([_key + "a", 32] call _seed)],
            ["bio", [_bios select ([_key, count _bios] call _seed), _custom] select (_custom isNotEqualTo "")],
            ["tags", [_tags select _t, _tags select ((_t + 3) mod count _tags), _tags select ((_t + 5) mod count _tags)]],
            // Distance arrondie au demi-kilomètre : l'app ne donne jamais la position exacte.
            ["dist", [format ["à ~%1 km", ((ceil (_d / 500)) * 0.5) toFixed 1], "à moins de 500 m"] select (_d < 500)]
        ]
    };
    case "chance": {
        private _u = _arg;
        private _z = _u getVariable ["COMSPEC_ATAK_TinderChance", -1];
        if (_z isEqualType 0 && {_z >= 0}) exitWith { _z min 1 };
        [0.65, 0.45] select (([_u] call _kindOf) isEqualTo "HVT")
    };
    case "clue": {
        private _u = _arg;
        if (isNull _u || {!alive _u}) exitWith { ["Ce profil n'existe plus.", [], false] };
        private _hvt = ([_u] call _kindOf) isEqualTo "HVT";
        // Position floue : 300 m pour un HVT (prudent), 150 m sinon. Un HVT méfiant donne parfois un faux rendez-vous.
        private _r = [150, 300] select _hvt;
        private _true = !_hvt || {random 1 >= 0.25};
        private _p = if (_true) then { (getPosATL _u) getPos [random _r, random 360] } else { (getPosATL _u) getPos [1000 + random 1500, random 360] };
        private _locs = nearestLocations [_p, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal", "Hill", "NameMarine"], 2500];
        private _loc = if (_locs isEqualTo []) then { "" } else { text (_locs select 0) };
        private _grid = [_p, 6] call comspec_atak_native_fnc_gridRef;
        private _hour = [((dayTime + 1 + random 2) mod 24), "HH:MM"] call BIS_fnc_timeToString;
        private _lines = if (_hvt) then {
            [format ["Je ne reste jamais longtemps au même endroit. En ce moment, du côté de %1.", [_loc, "la colline"] select (_loc isEqualTo "")],
             format ["Retrouve-moi vers %1. Viens seul, mes amis n'aiment pas les inconnus.", _grid],
             format ["Pas avant %1. Je te dirai où au dernier moment. On se voit vers %2 ?", _hour, _grid]]
        } else {
            [format ["Coucou ! Je suis pas loin de %1, vers %2.", [_loc, "chez moi"] select (_loc isEqualTo ""), _grid],
             format ["Je finis vers %1. Passe vers %2 si tu veux.", _hour, _grid],
             format ["Je suis encore vers %1. Tu viens ?", _grid]]
        };
        [selectRandom _lines, _p, _true]
    };
    default { [] };
};
