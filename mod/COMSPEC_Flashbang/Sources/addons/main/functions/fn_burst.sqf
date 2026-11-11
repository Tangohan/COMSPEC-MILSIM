/*
    comspec_fb_fnc_burst : UNE détonation, sur cette machine (appelée exactement une fois par instant par fn_timeline).

    1. Position : l'engin synchronisé s'il existe (il a pu rouler ou être poussé), sinon la position reçue.
       Engin déjà vu puis disparu (supprimé par Zeus, détruit) : la grenade est annulée, plus aucune détonation.
    2. Interface : lumière et particules (fn_fx), bang pour la caméra locale (sourd derrière un mur, lointain
       au-delà de 150 m, retard de 343 m/s), couche grave au premier flash.
    3. Effets sur les unités LOCALES proches : le joueur (fn_playerHit), les IA (fn_aiHit). Spectateurs, Zeus et
       joueurs hors zone qui regardent la scène : simple éblouissement passager (fn_glare).
    [_id, _index] call comspec_fb_fnc_burst
*/
params ["_id", "_index"];
private _ctx = comspec_fb_events getOrDefault [_id, createHashMap];
if (count _ctx == 0 || {_ctx get "cancelled"}) exitWith {};
private _kind = _ctx get "kind";
private _tick0 = diag_tickTime;
// Instant prévu (horloge commune) et instant réel : l'écart est journalisé, avec un avertissement au-delà de 0,3 s.
private _tPlan = (_ctx getOrDefault ["t0", 0]) + ((_ctx getOrDefault ["offsets", []]) param [_index, 0]);
private _tExec = call comspec_fb_fnc_clock;
_ctx set ["tPlan", _tPlan];

// ------------------------------------------------------------------ position
private _device = _ctx get "device";
if (_kind != "area") then {
    if (isNull _device) then {
        _device = objectFromNetId (_ctx get "netId");
        if (!isNull _device) then { _ctx set ["device", _device]; _ctx set ["resolved", true] };
    };
    if (isNull _device && {_ctx get "resolved"}) then {
        _ctx set ["cancelled", true];
        if (isServer) then {
            private _owners = (comspec_fb_owners getOrDefault [_id, []]) - [2];
            if (isMultiplayer) then { { ["comspec_fb_cancel", [_id], _x] call CBA_fnc_ownerEvent } forEach _owners };
        };
    };
};
if (_ctx get "cancelled") exitWith {
    ["burst", format ["%1 : engin supprimé, détonation %2/%3 annulée", _id, _index + 1, _ctx get "n"]] call comspec_fb_fnc_log;
};
private _pos = if (isNull _device) then { _ctx get "pos" } else { (getPosASL _device) vectorAdd [0, 0, 0.06] };
// Plus de 9 détonations (GANG BANG BF) : la grenade saute entre deux bangs (engin local à cette machine), et chaque
// détonation part d'un point dispersé autour d'elle (0 à 1,5 m).
private _nTot = _ctx get "n";
if (_nTot > 9) then {
    if (!isNull _device && {local _device} && {random 1 < 0.3}) then {
        _device setVelocity [(random 2 - 1) * 1.5, (random 2 - 1) * 1.5, 0.8 + random 1.4];
    };
    private _r = random 1.5;
    private _a = random 360;
    _pos = _pos vectorAdd [_r * sin _a, _r * cos _a, 0];
};
_ctx set ["pos", _pos];
_ctx set ["done", (_ctx get "done") + 1];

// Intérieur / extérieur : analyse étalée (fn_envProbe) ; en attendant, un seul rayon vers le haut.
private _env = _ctx get "env";
if ((_env getOrDefault ["state", ""]) != "done" && {_kind != "area"}) then {
    if (missionNamespace getVariable ["comspec_fb_indoorDetection", true]) then {
        private _roof = [_pos, _device] call comspec_fb_fnc_roofAbove;
        _env = createHashMapFromArray [["enclosed", _roof], ["class", ["exterieur", "piece"] select _roof],
            ["mult", [1, 1 + ((missionNamespace getVariable ["comspec_fb_indoorMult", 1.4]) - 1) * 0.7] select _roof], ["radius", 5], ["maxAxis", 8]];
    } else {
        _env = createHashMap;
    };
};
private _extra = _ctx get "extra";
// Force relative de cette détonation (fn_plan : 1er bang 1, les suivants « roulent » entre 0,65 et 1).
private _g = (_ctx getOrDefault ["gains", []]) param [_index, 1];
private _showFx = _kind != "area" || {_extra param [2, true]};
// Journal : une ligne par détonation (au-delà de 9 : une sur 10, et la dernière).
if (_nTot <= 9 || {_index mod 10 == 0} || {_index == _nTot - 1}) then {
    ["burst", format ["%1 détonation %2/%3 %4 env=%5 force=%6 prévue t=%7 exécutée t=%8 (écart %9 s)", _id, _index + 1, _nTot, _kind,
        _env getOrDefault ["class", "?"], _g toFixed 2, _tPlan toFixed 2, _tExec toFixed 2, (_tExec - _tPlan) toFixed 3]] call comspec_fb_fnc_log;
};
if (_tExec - _tPlan > 0.3) then {
    ["burst", format ["ATTENTION %1 détonation %2 exécutée %3 s après l'instant prévu (image lente ou message en retard)", _id, _index + 1,
        (_tExec - _tPlan) toFixed 2]] call comspec_fb_fnc_log;
};

// ------------------------------------------------------------------ lumière, particules, son (caméra locale)
if (hasInterface && _showFx) then {
    [_ctx, _pos, _env, _g] call comspec_fb_fnc_fx;
    private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
    private _dc = _cam vectorDistance _pos;
    // Retard de propagation (343 m/s) + micro-décalage aléatoire (jusqu'à 30 ms à 100 % de variation).
    private _delay = _dc / 343 + random (0.05 * (missionNamespace getVariable ["comspec_fb_variation", 0.6]));
    // Volume des flashbangs (client, 500 % par défaut) ; limiteur : -1 dB par bang au-delà du 4e, au plus -8 dB (une
    // série ne sature pas, le premier bang garde tout son impact).
    private _gain = (missionNamespace getVariable ["comspec_fb_bangVolume", 5]) * (10 ^ (-(((_index - 3) max 0) min 8) / 20)) * _g;
    // Très fort de près : x1,6 jusqu'à 30 m, puis retour à x1 vers 100 m (l'atténuation du moteur est rapide).
    _gain = _gain * linearConversion [30, 100, _dc, 1.6, 1, true];
    // Le bang n'est pas étouffé par l'ouïe déjà étouffée des flashs précédents : compensation de l'atténuation en cours.
    _gain = _gain / (((comspec_fb_state getOrDefault ["lastVol", 1]) max 0.25) min 1);
    private _wall = (lineIntersectsSurfaces [_pos vectorAdd [0, 0, 0.3], _cam, _device, vehicle (call CBA_fnc_currentUnit), true, 1, "GEOM", "VIEW"]) isNotEqualTo [];
    // Portée d'audibilité (réglage « Portée du son », 250 m) : moitié à travers un mur ou depuis un local fermé.
    // Elle ne concerne que le son : les effets restent limités au rayon maximal (~25 m).
    private _range = missionNamespace getVariable ["comspec_fb_soundRange", 250];
    if (_wall || {_env getOrDefault ["enclosed", false]}) then { _range = _range * 0.5 };
    if (_dc <= _range) then {
        if (_kind == "drill") then {
            // Son choisi pour la DRILL (réglage d'addon ou module), décidé par le serveur : une lecture par pop.
            _extra params [["_snd", "Pop", [""]], ["_vol", 1, [0]]];
            if (_snd != "Aucun") then {
                [format ["Drill_%1", _snd], _pos, _delay, _wall, _gain * _vol, _range, _dc] call comspec_fb_fnc_playBang;
            };
        } else {
            // Très fort sous 25 m, net jusqu'à ~100 m, au-delà bang lointain avec échos (retard 343 m/s).
            switch (true) do {
                case (_dc > 100 && {!_wall}): { ["BangLointain", _pos, _delay, false, _gain, _range] call comspec_fb_fnc_playBang };
                case (_wall): { ["BangEtouffe", _pos, _delay, true, _gain, _range] call comspec_fb_fnc_playBang };
                default {
                    ["BangUnique", _pos, _delay, false, _gain, _range, _dc] call comspec_fb_fnc_playBang;
                    if (_index == 0) then { ["BangGrave", _pos, _delay, false, _gain, _range, _dc] call comspec_fb_fnc_playBang };
                };
            };
        };
    };
    ["fx", format ["%1 : lumière et particules créées, son à %2 m (retard %3 s, %4, portée du son %5 m%6)", _id, round _dc, _delay toFixed 2,
        ["direct", "derrière un mur"] select _wall, round _range, [" : trop loin, pas de son", ""] select (_dc <= _range)]] call comspec_fb_fnc_log;
};

// ------------------------------------------------------------------ effets sur les unités locales
if (_kind == "drill") exitWith {
    // Entraînement : aucun effet, sauf (réglage) une lueur faible pour le joueur qui regarde.
    if (hasInterface && {missionNamespace getVariable ["comspec_fb_drillFlash", false]}) then {
        [_pos, 0.15, _device] call comspec_fb_fnc_glare;
    };
};

private _me = if (hasInterface) then { call CBA_fnc_currentUnit } else { objNull };
private _meInGame = !isNull _me && {alive _me} && {isNull curatorCamera};
private _area = _kind == "area";
private _radius = if (_area) then { _extra param [0, 10] } else {
    ((missionNamespace getVariable ["comspec_fb_radiusMax", 25]) * (_env getOrDefault ["mult", 1])) max 60
};
private _center = ASLToAGL _pos;
private _units = _center nearEntities [["CAManBase"], _radius];
{ _units append (crew _x) } forEach (_center nearEntities [["LandVehicle", "Air", "Ship", "StaticWeapon"], _radius min 40]);
_units = _units arrayIntersect _units;
private _friendly = missionNamespace getVariable ["comspec_fb_friendly", true];
private _side = _ctx get "side";
private _aiOn = missionNamespace getVariable ["comspec_fb_aiAffected", true];
private _meHit = false;
private _dbg = [true] call comspec_fb_fnc_logOn;
if (_dbg) then {
    ["unités", format ["%1 : %2 unité(s) dans %3 m ; joueur local %4", _id, count _units, round _radius,
        switch (true) do {
            case (isNull _me): { "absent (serveur ou headless)" };
            case (!alive _me): { "mort : pas d'effet, éblouissement de spectateur seulement" };
            case (!isNull curatorCamera): { "en Zeus : pas d'effet, éblouissement de spectateur seulement" };
            case !(_me in _units): { format ["hors rayon (%1 m)", round (_me distance _center)] };
            default { "dans le rayon" };
        }]] call comspec_fb_fnc_log;
};
{
    private _u = _x;
    private _isMe = _meInGame && {_u == _me};
    private _concerned = local _u && {alive _u}
        && {_friendly || {_side isEqualTo sideUnknown} || {side group _u != _side}}
        && {_isMe || {!isPlayer _u && _aiOn}};
    if (_dbg && {local _u} && {!_concerned}) then {
        ["unité", format ["%1 %2 m : ignorée (%3)", _u, round (_u distance _center), switch (true) do {
            case (!alive _u): { "morte" };
            case (!_friendly && {side group _u == _side}): { "même camp que le lanceur, réglage « Effet sur son camp » coupé" };
            case (isPlayer _u && {!_isMe}): { "joueur en Zeus ou spectateur" };
            case (!_aiOn): { "réglage « IA affectée » coupé" };
            default { "?" };
        }]] call comspec_fb_fnc_log;
    };
    if (_concerned) then {
        private _exp = if (_area) then {
            private _s = (_extra param [1, 0.8]) * (parseNumber ((_u distance _center) <= (_extra param [0, 10])));
            [_s, _s, createHashMapFromArray [["dist", _u distance _center], ["durMult", 1], ["angle", 0]]]
        } else {
            [_u, _ctx, _pos, _env, _isMe] call comspec_fb_fnc_exposure
        };
        _exp params ["_vis", "_aud", "_info"];
        // Hasard par victime et par détonation (moyenne inchangée) ; sensibilité propre au joueur pour la session.
        private _rv = (1 + ([0.1] call comspec_fb_fnc_rand)) * _g;
        private _ra = (1 + ([0.1] call comspec_fb_fnc_rand)) * _g;
        if (_isMe) then {
            if (isNil "comspec_fb_sens") then { comspec_fb_sens = 1 + ([0.25] call comspec_fb_fnc_rand) };
            _rv = _rv * comspec_fb_sens;
            _ra = _ra * comspec_fb_sens;
        };
        _vis = _vis * _rv;
        _aud = _aud * _ra;
        _info set ["tirage", [_rv, _ra]];
        if (_dbg) then {
            ["unité", format ["%1 %2 : %3 m, angle %4°, vue %5, %6, vision %7, audition %8 (tirage x%10 / x%11) -> %9", _u, ["IA", "JOUEUR"] select _isMe,
                (_info getOrDefault ["dist", 0]) toFixed 1, round (_info getOrDefault ["angle", -1]),
                ["directe", "bloquée"] select (_info getOrDefault ["blocked", false]), _env getOrDefault ["class", "?"],
                _vis toFixed 2, _aud toFixed 2,
                ["aucun effet (trop faible : distance, mur ou dos tourné)", "effets appliqués"] select (_vis > 0.02 || {_aud > 0.02}),
                _rv toFixed 2, _ra toFixed 2]] call comspec_fb_fnc_log;
        };
        if (_vis > 0.02 || {_aud > 0.02}) then {
            if (_isMe) then {
                _meHit = true;
                _info set ["tPlan", _tPlan];
                [_vis, _aud, _info, _pos] call comspec_fb_fnc_playerHit;
            } else {
                [_u, _vis, _aud, _pos, _info] call comspec_fb_fnc_aiHit;
            };
            // Blessure légère à bout portant (réglage, désactivé par défaut ; jamais avec ACE Médical).
            if (!_area && {missionNamespace getVariable ["comspec_fb_damage", false]} && {(_info get "dist") < 1}
                && {!isClass (configFile >> "CfgPatches" >> "ace_medical")} && {isDamageAllowed _u}) then {
                _u setDamage ((damage _u) + 0.08);
            };
        };
    };
} forEach _units;

if (_nTot > 9 && {_index mod 10 == 9 || {_index == _nTot - 1}}) then {
    ["burst", format ["%1 coût de la détonation %2 sur ce poste : %3 ms", _id, _index + 1, ((diag_tickTime - _tick0) * 1000) toFixed 2]] call comspec_fb_fnc_log;
};

// Observateurs : spectateur, Zeus, mort, ou joueur hors de la zone qui voit le flash.
if (hasInterface && _showFx && {!_meHit}) then {
    [_pos, 1, _device] call comspec_fb_fnc_glare;
};
