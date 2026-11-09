// Équipement (addon loadout du SSE) : pose d'un kit vanilla complet puis réexport JSON identique ; classes absentes
// retirées et listées, chargeur remplacé seulement sans ambiguïté, casse des classes corrigée ; catalogue des objets.
if !("equipement" call AT_fnc_groupe) exitWith {};

// Kit vanilla complet (getUnitLoadout à 10 entrées) : arme équipée (silencieux, pointeur, optique, bipied), lanceur,
// arme de poing, uniforme / gilet / sac avec contenu (arme rangée, chargeur partiel), casque, lunettes, jumelles, objets.
AT_LoKit = [
    ["arifle_MX_ACO_pointer_F", "muzzle_snds_H", "acc_pointer_IR", "optic_Aco", ["30Rnd_65x39_caseless_mag", 30], [], "bipod_01_F_snd"],
    ["launch_NLAW_F", "", "", "", ["NLAW_F", 1], [], ""],
    ["hgun_P07_F", "", "", "", ["16Rnd_9x21_Mag", 16], [], ""],
    ["U_B_CombatUniform_mcam", [["16Rnd_9x21_Mag", 2, 16], ["Chemlight_green", 1, 1]]],
    ["V_PlateCarrier1_rgr", [["acc_flashlight", 1], ["30Rnd_65x39_caseless_mag", 4, 30]]],
    ["B_AssaultPack_mcamo", [[["hgun_ACPC2_F", "", "", "", ["9Rnd_45ACP_Mag", 8], [], ""], 1], ["optic_Hamr", 1], ["30Rnd_65x39_caseless_mag", 1, 17]]],
    "H_HelmetB",
    "G_Combat",
    ["Binocular", "", "", "", [], [], ""],
    ["ItemMap", "ItemGPS", "ItemRadio", "ItemCompass", "ItemWatch", "NVGoggles"]
];
// Contenu des conteneurs trié (l'ordre rendu par getUnitLoadout n'est pas garanti) ; radio retirée si ACRE ou TFAR
// gère la radio (ils remplacent ItemRadio).
AT_fnc_loNorm = {
    private _n = +_this;
    {
        private _c = _n select _x;
        if (_c isEqualType [] && {(count _c) == 2}) then {
            private _items = (_c select 1) apply { str _x };
            _items sort true;
            _n set [_x, [_c select 0, _items]];
        };
    } forEach [3, 4, 5];
    if (isClass (configFile >> "CfgPatches" >> "acre_main") || {isClass (configFile >> "CfgPatches" >> "tfar_core")}) then {
        private _a = +(_n select 9);
        _a set [2, ""];
        _n set [9, _a];
    };
    _n
};
// Chargeurs compatibles d'un canon qui contiennent _n munitions (règle de remplacement de fn_loadoutApply).
AT_fnc_loCands = {
    params ["_w", "_muz", "_n"];
    private _s = [];
    {
        private _c = configFile >> "CfgMagazines" >> _x;
        if (isClass _c && {getNumber (_c >> "count") == _n}) then { _s pushBackUnique (configName _c); };
    } forEach (compatibleMagazines [_w, _muz]);
    _s
};

["equipement_kit_complet", {
    if (["comspec_sse_fnc_loadoutApply", "comspec_sse_fnc_loadoutExport", "comspec_sse_fnc_loadoutParse"] call AT_fnc_nil) exitWith {};
    private _avant = getUnitLoadout player;
    ([player, AT_LoKit, "Autotest", true] call comspec_sse_fnc_loadoutApply) params ["_ok", "_manq"];
    [_ok, "tenue non posée (fn_loadoutApply)"] call AT_fnc_check;
    [_manq isEqualTo [], format ["objets vanilla signalés absents : %1", _manq]] call AT_fnc_check;
    uiSleep 1;
    private _txt = [player, false] call comspec_sse_fnc_loadoutExport;
    (format ["export : %1 caractères", count _txt]) call AT_fnc_info;
    [(_txt select [0, 2]) isEqualTo "[[", format ["export inattendu : %1", _txt select [0, 40]]] call AT_fnc_check;
    private _back = parseSimpleArray _txt;
    [_back isEqualTo getUnitLoadout player, "export différent de getUnitLoadout"] call AT_fnc_check;
    if (_back isEqualTo AT_LoKit) then {
        "réexport strictement identique au kit d'origine" call AT_fnc_info;
    } else {
        { if ((_back param [_x, []]) isNotEqualTo (AT_LoKit select _x)) then { (format ["écart entrée %1 : posé %2 / relu %3", _x, AT_LoKit select _x, _back param [_x, []]]) call AT_fnc_info; }; } forEach [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        // Écart toléré : ordre du contenu des conteneurs, radio remplacée par ACRE / TFAR.
        [(_back call AT_fnc_loNorm) isEqualTo (AT_LoKit call AT_fnc_loNorm), "réexport différent du kit d'origine (hors ordre du contenu et radio ACRE/TFAR)"] call AT_fnc_check;
    };
    // Formes acceptées par la lecture : JSON, str SQF, [nom, texte], [tenue, infos étendues], [tenue, version].
    [([_txt] call comspec_sse_fnc_loadoutParse) isEqualTo _back, "JSON exporté non relu par fn_loadoutParse"] call AT_fnc_check;
    [([str AT_LoKit] call comspec_sse_fnc_loadoutParse) isEqualTo AT_LoKit, "str SQF non relu"] call AT_fnc_check;
    [([["Fusilier", _txt]] call comspec_sse_fnc_loadoutParse) isEqualTo _back, "emballage [nom, texte] non relu"] call AT_fnc_check;
    [([[AT_LoKit, [["ace_earplugs", false]]]] call comspec_sse_fnc_loadoutParse) isEqualTo AT_LoKit, "emballage [tenue, infos ACE] non relu"] call AT_fnc_check;
    [([[AT_LoKit, 2]] call comspec_sse_fnc_loadoutParse) isEqualTo AT_LoKit, "emballage [tenue, version] non relu"] call AT_fnc_check;
    [(["[1,2,3]"] call comspec_sse_fnc_loadoutParse) isEqualTo [], "texte quelconque pris pour une tenue"] call AT_fnc_check;
    ["equipement_kit_complet"] call AT_fnc_shot;
    player setUnitLoadout _avant;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["equipement_classes_absentes", {
    if (["comspec_sse_fnc_loadoutApply"] call AT_fnc_nil) exitWith {};
    private _avant = getUnitLoadout player;
    private _kit = +AT_LoKit;
    // Casse différente : corrigée sans être signalée.
    _kit set [3, ["u_b_combatuniform_MCAM", +((AT_LoKit select 3) select 1)]];
    _kit set [6, "H_COMSPEC_Nexiste_Pas"];
    private _vest = +(AT_LoKit select 4);
    _vest set [1, ((_vest select 1) + [["COMSPEC_Objet_Inexistant", 1]])];
    _kit set [4, _vest];
    private _pri = +(AT_LoKit select 0);
    _pri set [4, ["30Rnd_COMSPEC_Inexistant", 30]];
    _kit set [0, _pri];
    private _lau = +(AT_LoKit select 1);
    _lau set [4, ["NLAW_COMSPEC_Inexistant", 1]];
    _kit set [1, _lau];
    ([player, _kit, "Autotest absents", true] call comspec_sse_fnc_loadoutApply) params ["_ok", "_manq"];
    (format ["manquants : %1", _manq]) call AT_fnc_info;
    uiSleep 1;
    private _lo = getUnitLoadout player;
    [_ok, "tenue non posée"] call AT_fnc_check;
    [(uniform player) isEqualTo "U_B_CombatUniform_mcam", format ["uniforme en casse différente non posé (%1)", uniform player]] call AT_fnc_check;
    [(_manq findIf { (_x select 0) == "u_b_combatuniform_MCAM" }) < 0, "classe en casse différente signalée absente"] call AT_fnc_check;
    [["H_COMSPEC_Nexiste_Pas", ""] in _manq, "casque inexistant non listé"] call AT_fnc_check;
    [(headgear player) isEqualTo "", "casque inexistant non retiré"] call AT_fnc_check;
    [["COMSPEC_Objet_Inexistant", ""] in _manq, "objet inexistant du gilet non listé"] call AT_fnc_check;
    [!("COMSPEC_Objet_Inexistant" in (vestItems player)), "objet inexistant posé dans le gilet"] call AT_fnc_check;
    {
        _x params ["_slot", "_old", "_nom"];
        private _w = (AT_LoKit select _slot) select 0;
        private _c = [_w, "this", ((AT_LoKit select _slot) select 4) select 1] call AT_fnc_loCands;
        private _got = (_lo select _slot) param [4, []];
        if ((count _c) == 1) then {
            [[_old, _c select 0] in _manq, format ["%1 : chargeur absent non remplacé par l'unique compatible %2", _nom, _c select 0]] call AT_fnc_check;
            [(_got param [0, ""]) isEqualTo (_c select 0), format ["%1 : chargeur engagé %2 au lieu de %3", _nom, _got, _c select 0]] call AT_fnc_check;
        } else {
            (format ["%1 : %2 chargeurs compatibles de même contenance (ambigu, retiré)", _nom, count _c]) call AT_fnc_info;
            [[_old, ""] in _manq, format ["%1 : chargeur absent ambigu non listé comme retiré", _nom]] call AT_fnc_check;
            [_got isEqualTo [], format ["%1 : chargeur ambigu inventé (%2)", _nom, _got]] call AT_fnc_check;
        };
    } forEach [[0, "30Rnd_COMSPEC_Inexistant", "MX"], [1, "NLAW_COMSPEC_Inexistant", "NLAW"]];
    ["equipement_classes_absentes"] call AT_fnc_shot;
    player setUnitLoadout _avant;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["equipement_catalogue", {
    if (["comspec_sse_fnc_loadoutCatalog"] call AT_fnc_nil) exitWith {};
    private _t0 = diag_tickTime;
    AT_LoCat = [];
    // Hors planificateur, comme dans la console de débogage.
    isNil { AT_LoCat = [false] call comspec_sse_fnc_loadoutCatalog; };
    private _objs = AT_LoCat param [4, []];
    (format ["catalogue : %1 objets, %2 mods, %3 s", count _objs, count (AT_LoCat param [3, []]), (diag_tickTime - _t0) toFixed 1]) call AT_fnc_info;
    [(AT_LoCat param [0, ""]) isEqualTo "comspec-equipment-catalog" && {(AT_LoCat param [1, 0]) isEqualTo 1}, "en-tête du catalogue"] call AT_fnc_check;
    [(count _objs) > 300, format ["catalogue trop court (%1 objets)", count _objs]] call AT_fnc_check;
    private _get = { private _c = _this; _objs param [_objs findIf { (_x select 0) == _c }, []] };
    private _mx = "arifle_MX_F" call _get;
    if ([_mx isNotEqualTo [], "arifle_MX_F absent du catalogue"] call AT_fnc_check) then {
        (format ["MX : %1", _mx]) call AT_fnc_info;
        [(_mx select 1) isEqualTo "w" && {(_mx select 7) isEqualTo 1}, format ["MX : racine %1, type %2 (attendu w, 1)", _mx select 1, _mx select 7]] call AT_fnc_check;
        [(((_mx select 10) param [1, []]) findIf { _x == "optic_Aco" }) >= 0, "MX : optic_Aco absent des optiques compatibles"] call AT_fnc_check;
        [((_mx select 11) findIf { _x == "30Rnd_65x39_caseless_mag" }) >= 0, "MX : 30Rnd_65x39_caseless_mag absent des chargeurs"] call AT_fnc_check;
        [(_mx select 5) > 0, "MX : masse inconnue"] call AT_fnc_check;
    };
    private _bp = "B_AssaultPack_mcamo" call _get;
    [_bp isNotEqualTo [] && {(_bp select 1) isEqualTo "v"} && {(_bp select 6) > 0}, format ["B_AssaultPack_mcamo : charge maximale absente (%1)", _bp]] call AT_fnc_check;
    private _vest = "V_PlateCarrier1_rgr" call _get;
    [_vest isNotEqualTo [] && {(_vest select 6) > 0}, format ["V_PlateCarrier1_rgr : charge maximale absente (%1)", _vest]] call AT_fnc_check;
    private _mag = "30Rnd_65x39_caseless_mag" call _get;
    [_mag isNotEqualTo [] && {(_mag select 1) isEqualTo "m"} && {(_mag select 8) isEqualTo 30}, format ["30Rnd_65x39_caseless_mag : munitions (%1)", _mag]] call AT_fnc_check;
    private _g = "G_Combat" call _get;
    [_g isNotEqualTo [] && {(_g select 1) isEqualTo "g"}, "G_Combat absent (CfgGlasses)"] call AT_fnc_check;
    private _txt = str AT_LoCat;
    (format ["texte du catalogue : %1 Ko", round ((count _txt) / 1024)]) call AT_fnc_info;
    [(_objs findIf { ((_x select 4) find """") >= 0 || {((_x select 4) find "\") >= 0} }) < 0, "nom avec guillemet ou barre oblique inverse dans le catalogue (JSON invalide)"] call AT_fnc_check;
    AT_LoCat = nil;
    call AT_fnc_done;
}, 150] call AT_fnc_run;
