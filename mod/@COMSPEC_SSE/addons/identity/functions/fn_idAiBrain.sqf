/*
    Cerveau d'une IA contrôlée (SERVEUR), appelé par l'automate CBA (fn_idAiFsm).
      [ia, état] call comspec_sse_fnc_idAiBrain           à chaque passage (une unité par image) ; contexte recalculé une
                                                           fois par seconde au plus, puis souhait d'état (comspec_sse_idWant)
      ["decide", [ia, état, réponse immédiate]] -> état souhaité ("" : rester)
      ["profile", [ia, contrôleur]] -> civil | insurge | hvt | agent | policier
    Profil : attribut Eden COMSPEC_ID_Profile, sinon biométrie (watchlist 1 = HVT, 2 = recherché), nature des papiers
    (faux = insurgé, couverture = agent), camp (civil ; ami du contrôleur = policier ; autre = insurgé).
    Contexte : menotté / rendu, inconscient, blessé, armé, mobile (à pied ou conducteur), soldats à 20 m arme levée, armes
    braquées sur lui (arme levée, 25 m au plus, ±8°), zone de checkpoint, couvre-feu (civils), contrôleur parti, faux découvert.
    Une arme portée ou baissée ne fait pas peur : seule une arme levée compte, braquée sur lui surtout, et d'autant plus
    près (10, 18, 26 à 25, 12, 5 m).
    Émotions de l'IA (IA RP, fn_iarpProfile « emoActive ») : sans « armes », armes braquées et soldats ne comptent pas ;
    sans peur, nervosité ni colère, tension nulle ; chaque état qui exprime une émotion décochée n'est plus choisi (fuite et
    reddition sous la menace : peur ; nerveux, refus par nervosité : nervosité ; agressif, renforts : colère).
    Tension 0-100 : profil, nerfs propres à la personne, armes braquées, soldats, couvre-feu, arme sur lui, zone,
    questions répétées, faux découvert (retombe en une minute), choc et pression de la conversation (IA RP,
    fn_iarpReact) ; menaces × nerfs de l'atelier IA (0,6 à 1,4) ; × agressivité (réglage). Monte vite, redescend lentement
    (8 % de l'écart par seconde) : la peur se voit retomber. Chaque variation nette (8 points) est notée avec sa raison au
    journal de l'IA RP (onglet JOURNAL de « Parler à »).
*/
params [["_p0", objNull], ["_p1", ""]];
if (!isServer) exitWith { "" };

private _profile = {
    params ["_u", ["_c", objNull]];
    private _p = _u getVariable ["comspec_sse_idProfile", ""];
    if (_p isNotEqualTo "") exitWith { _p };
    _p = toLower (_u getVariable ["COMSPEC_ID_Profile", "auto"]);
    // Menteur : il porte une couverture ou de faux papiers (il présentera ceux-là, avec l'histoire qui va avec).
    private _kinds = (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { (["doc", [_x]] call comspec_sse_fnc_idRegistry) getOrDefault ["kind", "civil"] };
    _u setVariable ["comspec_sse_idLiar", ("forged" in _kinds) || {"cover" in _kinds}];
    if !(_p in ["civil", "insurge", "hvt", "agent", "policier"]) then {
        private _db = toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"]);
        private _cs = [side group _c, sideUnknown] select (isNull _c);
        _p = call {
            if (_db isEqualTo "bewl1") exitWith { "hvt" };
            if (_db isEqualTo "bewl2" || {"forged" in _kinds}) exitWith { "insurge" };
            if ("cover" in _kinds) exitWith { "agent" };
            if ((side group _u) isEqualTo civilian) exitWith { "civil" };
            if (_cs isNotEqualTo sideUnknown && {[_cs, side group _u] call BIS_fnc_sideIsFriendly}) exitWith { "policier" };
            "insurge"
        };
    };
    _u setVariable ["comspec_sse_idProfile", _p];
    _p
};

private _context = {
    params ["_u"];
    private _c = _u getVariable ["comspec_sse_idCtrl", objNull];
    private _cs = [side group _c, sideUnknown] select (isNull _c);
    private _soldiers = 0;
    private _aimed = 0;
    private _aimNear = 1e6;
    private _nearest = 1e6;
    {
        if (alive _x && {_x isNotEqualTo _u} && {(_x isEqualTo _c) || {(side group _x) isEqualTo _cs} || {isPlayer _x && {(side group _x) isNotEqualTo civilian}}}
            && {!(_x getVariable ["ACE_isUnconscious", false])}) then {
            private _d = _x distance _u;
            _nearest = _nearest min _d;
            private _w = currentWeapon _x;
            if (_w isNotEqualTo "" && {_w isNotEqualTo (binocular _x)} && {!(weaponLowered _x)}) then {
                if (_d < 20) then { _soldiers = _soldiers + 1; };
                // Arme braquée sur lui : arme levée, canon à 8° de son buste ou, de près, à moins de 0,6 m de lui (à 3 m, viser
                // la tête sort du cône de 8°), 25 m au plus. Même règle que fn_iarpReact « aimedAt ».
                private _wd = _x weaponDirection _w;
                private _to = (aimPos _u) vectorDiff (eyePos _x);
                private _al = _to vectorDotProduct _wd;
                if (_d <= 25 && {_al > 0} && {(_wd vectorDotProduct (vectorNormalized _to)) > 0.99 || {(vectorMagnitude (_to vectorDiff (_wd vectorMultiply _al))) < 0.6}}) then { _aimed = _aimed + 1; _aimNear = _aimNear min _d; };
            };
        };
    } forEach (_u nearEntities ["CAManBase", 40]);
    // Émotions de l'IA (IA RP, fn_iarpProfile « emoActive ») ; sans IA RP : toutes. Sans peur des armes, armes braquées et
    // soldats arme levée ne comptent pas (ni tension, ni reddition, ni fuite empêchée).
    private _emo = if (isNil "comspec_sse_fnc_iarpProfile") then { ["peur", "armes", "nervosite", "colere", "mefiance", "soulagement"] } else { ["emoActive", _u] call comspec_sse_fnc_iarpProfile };
    if !("armes" in _emo) then { _soldiers = 0; _aimed = 0; _aimNear = 1e6; };
    private _veh = vehicle _u;
    private _civ = (side group _u) isEqualTo civilian;
    private _cf = missionNamespace getVariable ["comspec_sse_idCurfewZeus", missionNamespace getVariable ["comspec_sse_id_curfew", false]];
    private _cd = [_u distance _c, 1e6] select (isNull _c);
    if (_cd <= 30) then { _u setVariable ["comspec_sse_idSeen", time]; };
    createHashMapFromArray [
        ["emo", _emo], ["ctrl", _c], ["ctrlDist", _cd], ["soldiers", _soldiers], ["aimed", _aimed], ["aimNear", _aimNear], ["nearest", _nearest],
        ["restr", (_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]}],
        ["uncon", (_u getVariable ["ACE_isUnconscious", false]) || {(lifeState _u) isEqualTo "INCAPACITATED"}],
        ["wounded", (damage _u) > 0.4 || {(_u getHitPointDamage "HitLegs") > 0.5} || {(_u getVariable ["ace_medical_woundBleeding", 0]) > 0.02}],
        ["armed", (primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""}],
        ["mobile", (_veh isEqualTo _u) || {(driver _veh) isEqualTo _u}],
        ["curfew", _civ && {_cf}],
        ["inCp", ["inZone", [_u]] call comspec_sse_fnc_idCheckpoint]
    ]
};

private _tension = {
    params ["_u", "_cx", "_prof"];
    private _key = [_u] call comspec_sse_fnc_bioPersonKey;
    private _aimed = _cx get "aimed";
    private _near = _cx getOrDefault ["aimNear", 1e6];
    private _aimW = if (_aimed > 0) then { ([10, 18, 26] select ((parseNumber (_near <= 12)) + (parseNumber (_near <= 5)))) + 8 * ((_aimed - 1) min 2) } else { 0 };
    // Choc (tirs proches, violence vue) et pression de la conversation (IA RP), qui retombent de 30 par minute.
    private _shock = if (isNil "comspec_sse_fnc_iarpReact") then { 0 } else { ["shock", _u] call comspec_sse_fnc_iarpReact };
    // Nerfs (atelier IA, 50 par défaut) : les menaces comptent de 0,6 à 1,4 fois.
    private _k = 1;
    if (!isNil "comspec_sse_fnc_iarpProfile") then { _k = 0.6 + 0.8 * (((["get", _u] call comspec_sse_fnc_iarpProfile) getOrDefault ["nerve", 50]) / 100); };
    private _bust = _u getVariable ["comspec_sse_idBust", 0];
    private _sold = (((_cx get "soldiers") - 1) max 0) min 4;
    private _t = ((createHashMapFromArray [["civil", 10], ["policier", 0], ["agent", 20], ["insurge", 35], ["hvt", 45]]) getOrDefault [_prof, 10])
        + (([_key, "idnerve"] call comspec_sse_fnc_bioRand) * 20 - 10)
        + _k * (_aimW + _shock)
        + 2 * _sold
        + _bust;
    if (_cx get "curfew") then { _t = _t + 12; };
    if ((_cx get "armed") && {_prof in ["insurge", "hvt"]}) then { _t = _t + 12; };
    if (_cx get "inCp") then { _t = _t + ([8, -6] select (_prof isEqualTo "civil")); };
    if (_prof in ["insurge", "hvt", "agent"]) then { _t = _t + 4 * ((((_u getVariable ["comspec_sse_idAsks", 1]) - 1) max 0) min 3); };
    if (_prof isEqualTo "policier") then { _t = 0; };
    // Ni peur, ni nervosité, ni colère (IA neutre ou impassible) : aucune tension, quelle qu'en soit la cause.
    if (((_cx get "emo") findIf { _x in ["peur", "nervosite", "colere"] }) < 0) then { _t = 0; };
    _t = _t * (0.6 + 0.8 * ((missionNamespace getVariable ["comspec_sse_id_aiAggro", 50]) / 100));
    _t = (_t max 0) min 100;
    private _old = _u getVariable ["comspec_sse_idTension", _t];
    // Monte vite (moitié de l'écart), redescend lentement (8 % de l'écart, 0,5 au moins) : la peur se voit retomber.
    private _new = if (_t >= _old) then { (_old * 0.5) + (_t * 0.5) } else { (_old - (((_old - _t) * 0.08) max 0.5)) max _t };
    _new = (_new max 0) min 100;
    _u setVariable ["comspec_sse_idTension", _new];
    // Faux découvert : retombe en une minute.
    if (_bust > 0) then { _u setVariable ["comspec_sse_idBust", (_bust - 0.5) max 0]; };
    // Journal de la peur (IA RP) : variation nette depuis la dernière ligne, avec ce qui la cause.
    if (!isNil "comspec_sse_fnc_iarpServer") then {
        private _last = _u getVariable ["comspec_sse_idFearLogged", -1];
        if (_last < 0) then { _u setVariable ["comspec_sse_idFearLogged", _new]; _last = _new; };
        if ((abs (_new - _last)) >= 8) then {
            _u setVariable ["comspec_sse_idFearLogged", _new];
            private _r = [];
            if (_aimed > 0) then { _r pushBack format ["%1 arme(s) braquée(s) sur elle, à %2 m", _aimed, round _near]; };
            if (_shock >= 5) then { _r pushBack format ["choc ou pression de la conversation (%1)", round _shock]; };
            if (_shock <= -5) then { _r pushBack "apaisée par la conversation"; };
            if (_sold > 0) then { _r pushBack format ["%1 soldats arme levée autour", _sold + 1]; };
            if (_bust > 0) then { _r pushBack "faux découvert"; };
            if (_cx get "curfew") then { _r pushBack "couvre-feu"; };
            if (_r isEqualTo []) then { _r pushBack "plus aucune menace : la peur redescend"; };
            ["logFear", [_cx get "ctrl", _u, round (_new - _last), _r joinString ", ", round _new]] call comspec_sse_fnc_iarpServer;
        };
    };
    _new
};

private _decide = {
    params ["_u", "_st", ["_now", false]];
    private _cx = [_u] call _context;
    private _c = _cx get "ctrl";
    private _prof = [_u, _c] call _profile;
    private _t = [_u, _cx, _prof] call _tension;
    if (_cx get "uncon") exitWith { "" };
    private _since = time - (_u getVariable ["comspec_sse_idT", 0]);
    private _roll = _u getVariable ["comspec_sse_idRoll", 0.5];
    private _aimed = _cx get "aimed";
    private _sold = _cx get "soldiers";
    private _near = _cx get "nearest";
    private _aggro = (missionNamespace getVariable ["comspec_sse_id_aiAggro", 50]) / 100;
    // Émotions décochées : fuite et reddition sous la menace (peur), combat et renforts (colère), nervosité et refus par
    // nervosité ne sont plus choisis. Restent : menottes ou reddition ACE déjà faites, état imposé (Zeus, Eden), retour au calme.
    private _emo = _cx get "emo";
    private _nerv = "nervosite" in _emo;
    private _canFlee = (_cx get "mobile") && {!(_cx get "wounded")} && {_aimed isEqualTo 0} && {"peur" in _emo};
    private _canFight = (_cx get "armed") && {_prof in ["insurge", "hvt"]} && {!(_cx get "wounded")} && {"colere" in _emo}
        && {_aimed <= ([0, 1] select (_prof isEqualTo "hvt"))} && {_sold <= (2 + round (_aggro * 2))};
    private _yield = (_aimed > 0) && {"peur" in _emo} && {_sold >= 2 || {_aimed >= 2}}
        && {_roll < (((createHashMapFromArray [["civil", 0.95], ["agent", 0.75], ["insurge", 0.55], ["hvt", 0.3], ["policier", 1]]) getOrDefault [_prof, 0.6]) + 0.15 * (_aimed - 1))};
    private _gone = isNull _c || {!alive _c} || {(time - (_u getVariable ["comspec_sse_idSeen", time])) > 15};
    private _calm = ["coopere", "ment"] select (_u getVariable ["comspec_sse_idLiar", false]);
    // État imposé par le Zeus : gardé 30 s avant que la personne réagisse de nouveau d'elle-même.
    if ((time - (_u getVariable ["comspec_sse_idForced", -100])) < 30 && {!_now}) exitWith { "" };
    if ((_cx get "restr") && {_st isNotEqualTo "rendu"}) exitWith { "rendu" };
    switch (_st) do {
        case "calme": { call { ["", "interpelle"] select (!_gone) } };
        case "fuit": {
            call {
                if ((_cx get "wounded") && {_near < 40}) exitWith { "rendu" };
                if (_yield && {_near < 30}) exitWith { "rendu" };
                if (_canFight && {_near < 15}) exitWith { "agressif" };
                if (_near > 150 && {_since > 20}) exitWith { "calme" };
                ""
            }
        };
        case "agressif": {
            call {
                if ((_cx get "wounded") || {_aimed >= 3 && {"peur" in _emo}} || {_yield && {_aimed >= 2}}) exitWith { "rendu" };
                if (_near > 300 && {_since > 60}) exitWith { "calme" };
                ""
            }
        };
        case "renforts": {
            call {
                if (_since < 2 && {!_now}) exitWith { "" };
                if (_canFight) exitWith { "agressif" };
                if (_canFlee) exitWith { "fuit" };
                if (_yield) exitWith { "rendu" };
                "refuse"
            }
        };
        case "rendu": {
            call {
                // Menotté : il reste là (le contrôle continue, jusqu'à ce qu'on le libère).
                if (_u getVariable ["ace_captives_isHandcuffed", false]) exitWith { "" };
                if (_near > 50 && {_since > 90}) exitWith { ["calme", "fuit"] select (_prof in ["insurge", "hvt"]) };
                ""
            }
        };
        default {
            // interpelle, coopere, ment, nerveux, refuse
            call {
                if (_gone && {!_now} && {_st isNotEqualTo "interpelle" || {_since > 20}}) exitWith { "calme" };
                if (_st in ["coopere", "ment"] && {_since > 300} && {!_now}) exitWith { "calme" };
                if (_t >= 80 && {_canFight}) exitWith { ["agressif", "renforts"] select (!(_u getVariable ["comspec_sse_idCalled", false]) && {_roll < 0.6}) };
                if (_t >= 65 && {_canFlee} && {_prof isNotEqualTo "policier"} && {_st isNotEqualTo "coopere" || {(_u getVariable ["comspec_sse_idBust", 0]) > 0}}) exitWith { "fuit" };
                if (_t >= 55 && {_yield} && {_st in ["nerveux", "refuse"]}) exitWith { "rendu" };
                switch (_st) do {
                    case "interpelle": {
                        call {
                            if (_since < 1.5 && {!_now}) exitWith { "" };
                            if (_t >= 45 && {_nerv}) exitWith { "nerveux" };
                            // Civil sollicité : présente selon le réglage ; redemandé, une chance sur deux de plus.
                            if (_now && {_prof isEqualTo "civil"}) exitWith {
                                private _rate = (missionNamespace getVariable ["comspec_sse_id_civComply", 90]) / 100;
                                private _n = _u getVariable ["comspec_sse_idAsks", 1];
                                if (_n > 1) then { _rate = _rate + (1 - _rate) * 0.5; };
                                [["refuse", "coopere"] select (([[_u] call comspec_sse_fnc_bioPersonKey, format ["ai|comply|%1", _n]] call comspec_sse_fnc_bioRand) < _rate), _calm] select (_calm isEqualTo "ment")
                            };
                            _calm
                        }
                    };
                    case "nerveux": {
                        call {
                            if (_now) exitWith { ["nerveux", "refuse"] select (_t >= 60 && {_nerv}) };
                            if (_since < 5) exitWith { "" };
                            if (_t >= 58 && {_nerv}) exitWith { "refuse" };
                            if (_t < 38) exitWith { _calm };
                            ""
                        }
                    };
                    case "refuse": { call { if (_now && {_t < 55}) exitWith { _calm }; "" } };
                    default { call { if (_t >= 50 && {_since > 3} && {_nerv}) exitWith { "nerveux" }; "" } };
                }
            }
        };
    }
};

if (_p0 isEqualType "") exitWith {
    switch (_p0) do {
        case "decide": { _p1 call _decide };
        case "profile": { _p1 call _profile };
        default { "" };
    }
};

// Passage de l'automate : une fois par seconde au plus par unité (le reste du temps : rien).
private _u = _p0;
private _st = _p1;
if (isNull _u || {time < (_u getVariable ["comspec_sse_idNext", 0])}) exitWith {};
_u setVariable ["comspec_sse_idNext", time + 1];
if (!alive _u) exitWith { _u setVariable ["comspec_sse_idDone", true]; };
if (_st isEqualTo "calme" && {isNull (_u getVariable ["comspec_sse_idCtrl", objNull])}) exitWith {};
private _want = [_u, _st, false] call _decide;
if (_want isNotEqualTo "" && {_want isNotEqualTo _st}) then { _u setVariable ["comspec_sse_idWant", _want]; };
// Indices visibles : regards de côté (nerveux, refus ; menteur moins souvent, jamais en réaliste), toutes les 4 à 7 s.
if (_st in ["nerveux", "refuse", "ment"] && {time > (_u getVariable ["comspec_sse_idCue", 0])}) then {
    _u setVariable ["comspec_sse_idCue", time + 4 + random 3];
    if (_st isEqualTo "ment" && {(missionNamespace getVariable ["comspec_sse_id_difficulty", 1]) >= 2 || {(random 1) < 0.6}}) exitWith {};
    ["comspec_sse_idAiDo", [_u, "glance", [_u getVariable ["comspec_sse_idCtrl", objNull]]], _u] call CBA_fnc_targetEvent;
};
