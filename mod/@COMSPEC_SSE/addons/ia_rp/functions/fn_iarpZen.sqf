/*
    Zeus (ZEN), client, une fois ZEN chargé (XEH_postInitClient).
    Modules « COMSPEC SSE » (catégorie des modules de config, COMSPEC_Cat_Sse) :
      Atelier IA            sur une IA : elle et les IA sélectionnées par le curateur ; personnalité, dialogue,
                            bibliothèque, générer pour toutes, faire parler, import / export (fn_iarpWorkshop)
      Faire parler une IA   sur une IA : le Zeus tape sa réplique, bulle 3D au-dessus de sa tête et journal
      Journal des échanges  tous les échanges joueurs / IA, en direct (onglet JOURNAL du terminal)
    Menu contextuel (clic droit ZEN) « SSE · Roleplay de l'IA » : visible seulement quand la cible (IA survolée, sélection
    du curateur) compte au moins une IA vivante, jamais sur un joueur seul ; chaque action vaut pour toutes les IA visées.
      Profil d'IA           Atelier IA… · Appliquer un profil… · Rôle et caractère… · Savoir, secret et couverture… ·
                            Réponses de l'IA… (fn_iarpAnswersUi : réponse auto ou perso par question, questions perso) ·
                            Ce qu'elle peut évoquer… (cadre : rien de sensible, ce qu'elle sait, libre ; thèmes interdits)
                            (fenêtres ZEN pré-remplies avec le profil de la première IA, lu sur le serveur :
                            fn_iarpServer profileGet / profileSet / profileTweak)
      Identité et papiers   (addon identity) Voir ses vrais papiers · Donner des papiers… · Retirer ses papiers ·
                            Fiche dans l'éditeur SSE (page « IA de la zone », avec « Garder pour les prochaines parties »)
      État et comportement  Stop · Relâcher · Se rend · S'enfuit · Devient agressive · Calmer · Autre état… : automate des
                            IA réalistes (fn_idAiFsm) quand identity est chargé, sinon les ordres simples de l'IA RP
      Mémoire               Voir · Effacer · Effacer pour un joueur… (fn_iarpMem)
      Réactions…            tirs proches et témoin, pour ces IA seulement (fn_iarpReact)
      Faire parler… · Journal des échanges
    Tout est vérifié sur le serveur (fn_iarpServer : Zeus ou administrateur seulement). Conditions et actions ZEN
    reçoivent [position, objets sélectionnés, groupes, points de passage, marqueurs, entité survolée, argument] :
    comspec_sse_iarpZenTargets en tire les IA visées (ZEN ajoute déjà l'entité survolée aux objets sélectionnés).
*/
if (!hasInterface || {isNil "zen_custom_modules_fnc_register"}) exitWith { false };
private _cat = getText (configFile >> "CfgFactionClasses" >> "COMSPEC_Cat_Sse" >> "displayName");
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
private _sel = {
    params ["_obj"];
    private _l = [];
    if (!isNull _obj) then { _l pushBack _obj; };
    { if (_x isEqualType objNull) then { _l pushBackUnique _x; }; } forEach (curatorSelected param [0, []]);
    _l select { _x isKindOf "CAManBase" && {!isPlayer _x} && {alive _x} }
};
missionNamespace setVariable ["comspec_sse_iarpZenSel", _sel];
// « Faire parler » : la même fenêtre pour le module et pour le menu contextuel.
missionNamespace setVariable ["comspec_sse_iarpZenPuppet", {
    params [["_obj", objNull]];
    if (isNull _obj || {!(_obj isKindOf "CAManBase")} || {isPlayer _obj}) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoAi"]] call comspec_sse_fnc_iarpClient; };
    [format ["%1 — %2", localize "STR_COMSPEC_IARP_Z_Puppet", ["shown", _obj] call comspec_sse_fnc_iarpName],
        [["EDIT", [localize "STR_COMSPEC_IARP_Z_PuppetLine", localize "STR_COMSPEC_IARP_Z_PuppetLine_D"], ""]], {
        params ["_v", "_args"];
        ["send", ["puppet", [player, _args select 0, _v select 0]]] call comspec_sse_fnc_iarpClient;
    }, {}, [_obj]] call zen_dialog_fnc_create;
}];
if !(missionNamespace getVariable ["comspec_sse_iarpZenModulesOn", false]) then {
    missionNamespace setVariable ["comspec_sse_iarpZenModulesOn", true];
    [_cat, localize "STR_COMSPEC_IARP_Z_Workshop", {
        params ["_pos", "_obj"];
        ["open", ["zeus", [_obj] call comspec_sse_iarpZenSel]] call comspec_sse_fnc_iarpWorkshop;
    }, _icon] call zen_custom_modules_fnc_register;
    [_cat, localize "STR_COMSPEC_IARP_Z_Puppet", {
        params ["_pos", "_obj"];
        [_obj] call comspec_sse_iarpZenPuppet;
    }, _icon] call zen_custom_modules_fnc_register;
    [_cat, localize "STR_COMSPEC_IARP_Z_Log", {
        ["open", [objNull, "log"]] call comspec_sse_fnc_iarpUi;
    }, _icon] call zen_custom_modules_fnc_register;
};

// ---------------------------------------------------------------- menu contextuel « SSE · Roleplay de l'IA »
if (isNil "zen_context_menu_fnc_createAction" || {isNil "zen_context_menu_fnc_addAction"} || {isNil "zen_dialog_fnc_create"}) exitWith { true };
if (missionNamespace getVariable ["comspec_sse_iarpZenMenuOn", false]) exitWith { true };
missionNamespace setVariable ["comspec_sse_iarpZenMenuOn", true];

// IA visées par le menu : objets sélectionnés et entité survolée ; IA vivantes seulement, jamais un joueur.
missionNamespace setVariable ["comspec_sse_iarpZenTargets", {
    params ["", ["_objs", [], [[]]], "", "", "", ["_hover", objNull]];
    private _l = _objs select { _x isEqualType objNull };
    if (_hover isEqualType objNull && {!isNull _hover}) then { _l pushBackUnique _hover; };
    _l select { !isNull _x && {_x isKindOf "CAManBase"} && {!isPlayer _x} && {alive _x} }
}];
// Titre d'une fenêtre : « Rôle et caractère — Stavros VLACHOS » ou « … — 3 IA » ; nom du Zeus (vérité) s'il est connu.
missionNamespace setVariable ["comspec_sse_iarpZenTitle", {
    params ["_key", ["_units", []], ["_who", ""]];
    private _name = if (_who isEqualTo "") then { ["shown", _units param [0, objNull]] call comspec_sse_fnc_iarpName } else { ["label", _who] call comspec_sse_fnc_iarpName };
    format ["%1 — %2", localize _key, [_name, format [localize "STR_COMSPEC_IARP_W_NUnits", count _units]] select ((count _units) > 1)]
}];
// Fenêtre pré-remplie avec le profil de la première IA, lu sur le serveur (réponse « profile » de fn_iarpClient) :
// [IA visées, code appelé avec [ia, profil, nom pour le Zeus, IA visées]].
missionNamespace setVariable ["comspec_sse_iarpZenWithProfile", {
    params [["_units", []], ["_code", {}]];
    if (_units isEqualTo []) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoAi"]] call comspec_sse_fnc_iarpClient; };
    uiNamespace setVariable ["comspec_sse_iarpZenPending", [_units select 0, _code, _units, diag_tickTime]];
    ["send", ["profileGet", [player, _units select 0]]] call comspec_sse_fnc_iarpClient;
}];
// Ordre au serveur pour les IA visées : [op, arguments après la liste des IA] (fn_iarpServer, Zeus ou administrateur).
missionNamespace setVariable ["comspec_sse_iarpZenSend", {
    params ["_op", ["_rest", []], ["_units", []]];
    if (_units isEqualTo []) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoAi"]] call comspec_sse_fnc_iarpClient; };
    ["send", [_op, [player, _units] + _rest]] call comspec_sse_fnc_iarpClient;
}];

private _has = { (_this call comspec_sse_iarpZenTargets) isNotEqualTo [] };
private _hasId = { !isNil "comspec_sse_fnc_idClient" && {(_this call comspec_sse_iarpZenTargets) isNotEqualTo []} };
private _hasFsm = { !isNil "comspec_sse_fnc_idAiFsm" && {(_this call comspec_sse_iarpZenTargets) isNotEqualTo []} };
private _root = "comspec_sse_iarp";
private _mk = {
    params ["_id", "_key", "_ic", "_code", "_cond", "_parent", "_prio", ["_arg", []]];
    [[_root + _id, localize _key, _ic, _code, _cond, _arg] call zen_context_menu_fnc_createAction, _parent, _prio] call zen_context_menu_fnc_addAction;
};
private _ip = "\z\comspec_sse\addons\zeus\data\";
["", "STR_COMSPEC_IARP_Z_Root", _icon, {}, _has, [], 6] call _mk;

// --- Profil d'IA ---------------------------------------------------------------------------------------------------
["_profile", "STR_COMSPEC_IARP_Z_MProfile", _ip + "mod_ai.paa", {}, _has, [_root], 90] call _mk;
["_workshop", "STR_COMSPEC_IARP_Z_Workshop", _icon, {
    ["open", ["zeus", _this call comspec_sse_iarpZenTargets]] call comspec_sse_fnc_iarpWorkshop;
}, _has, [_root, _root + "_profile"], 89] call _mk;
["_preset", "STR_COMSPEC_IARP_Z_Preset", _ip + "mod_ai.paa", {
    private _u = _this call comspec_sse_iarpZenTargets;
    if (_u isEqualTo []) exitWith {};
    private _pre = ["presets"] call comspec_sse_fnc_iarpProfile;
    if (_pre isEqualTo []) exitWith { ["notice", ["STR_COMSPEC_IARP_Z_N_NoPreset"]] call comspec_sse_fnc_iarpClient; };
    [["STR_COMSPEC_IARP_Z_Preset", _u] call comspec_sse_iarpZenTitle, [
        ["LIST", [localize "STR_COMSPEC_IARP_Z_PresetPick", localize "STR_COMSPEC_IARP_Z_PresetPick_D"], [_pre apply { _x select 0 }, _pre apply { _x select 1 }, 0, 8]],
        ["CHECKBOX", [localize "STR_COMSPEC_IARP_Z_PresetGen", localize "STR_COMSPEC_IARP_Z_PresetGen_D"], false]
    ], {
        params ["_v", "_args"];
        _v params ["_id", "_gen"];
        private _raw = ["serialize", ["preset", _id] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile;
        ["profileSet", [_raw, ["apply", "generate"] select (_gen isEqualTo true)], _args select 0] call comspec_sse_iarpZenSend;
    }, {}, [_u]] call zen_dialog_fnc_create;
}, _has, [_root, _root + "_profile"], 88] call _mk;
["_traits", "STR_COMSPEC_IARP_Z_Traits", _ip + "mod_person.paa", {
    [_this call comspec_sse_iarpZenTargets, {
        params ["_t", "_p", "_who", "_units"];
        private _roles = ["auto", "civil", "insurge", "hvt", "agent", "policier"];
        private _rl = ["RoleAuto", "RoleCivil", "RoleInsurge", "RoleHvt", "RoleAgent", "RolePolice"] apply { localize ("STR_COMSPEC_IARP_W_" + _x) };
        private _rv = ["auto", "coopere", "refuse", "fuit", "agressif", "rendu"];
        private _rvl = _rv apply { localize ("STR_COMSPEC_IARP_W_React_" + _x) };
        private _r = _p getOrDefault ["react", createHashMap];
        private _rows = [["LIST", [localize "STR_COMSPEC_IARP_W_Role", localize "STR_COMSPEC_IARP_W_Role_D"], [_roles, _rl, (_roles find (_p getOrDefault ["role", "auto"])) max 0]]];
        {
            _x params ["_lk", "_k"];
            _rows pushBack ["SLIDER", [localize ("STR_COMSPEC_IARP_W_" + _lk), localize ("STR_COMSPEC_IARP_W_" + _lk + "_D")], [0, 100, _p getOrDefault [_k, 50], 0]];
        } forEach [["Nerve", "nerve"], ["Talk", "talk"], ["Aggro", "aggro"], ["Honesty", "honesty"], ["Bribe", "bribe"], ["Fear", "fear"], ["Drunk", "drunk"]];
        {
            _x params ["_lk", "_k"];
            _rows pushBack ["LIST", [localize ("STR_COMSPEC_IARP_W_" + _lk), localize "STR_COMSPEC_IARP_W_React_D"], [_rv, _rvl, (_rv find (_r getOrDefault [_k, "auto"])) max 0]];
        } forEach [["ReactOrder", "order"], ["ReactThreat", "threat"], ["ReactSearch", "search"], ["ReactBribeR", "bribe"]];
        [["STR_COMSPEC_IARP_Z_Traits", _units, _who] call comspec_sse_iarpZenTitle, _rows, {
            params ["_v", "_args"];
            _v params ["_role", "_nerve", "_talk", "_aggro", "_honesty", "_bribe", "_fear", "_drunk", "_ro", "_rt", "_rs", "_rb"];
            private _rows = [["role", _role], ["nerve", round _nerve], ["talk", round _talk], ["aggro", round _aggro], ["honesty", round _honesty],
                ["bribe", round _bribe], ["fear", round _fear], ["drunk", round _drunk], ["react", [["order", _ro], ["threat", _rt], ["search", _rs], ["bribe", _rb]]]];
            ["profileTweak", [_rows], _args select 0] call comspec_sse_iarpZenSend;
        }, {}, [_units]] call zen_dialog_fnc_create;
    }] call comspec_sse_iarpZenWithProfile;
}, _has, [_root, _root + "_profile"], 87] call _mk;
["_know", "STR_COMSPEC_IARP_Z_Know", _ip + "mod_doc.paa", {
    [_this call comspec_sse_iarpZenTargets, {
        params ["_t", "_p", "_who", "_units"];
        private _nl = toString [10];
        private _ul = ["trust", "bribe", "keyword", "never"];
        private _ull = ["UnlockTrust", "UnlockBribe", "UnlockWord", "UnlockNever"] apply { localize ("STR_COMSPEC_IARP_W_" + _x) };
        [["STR_COMSPEC_IARP_Z_Know", _units, _who] call comspec_sse_iarpZenTitle, [
            ["EDIT:MULTI", [localize "STR_COMSPEC_IARP_W_Story", localize "STR_COMSPEC_IARP_W_Story_D"], [_p getOrDefault ["story", ""], { _this }, 6]],
            ["EDIT:MULTI", [localize "STR_COMSPEC_IARP_W_Know", localize "STR_COMSPEC_IARP_W_Know_D"], [_p getOrDefault ["know", ""], { _this }, 5]],
            ["LIST", [localize "STR_COMSPEC_IARP_W_Unlock", localize "STR_COMSPEC_IARP_W_Unlock_D"], [_ul, _ull, (_ul find (_p getOrDefault ["unlock", "trust"])) max 0]],
            ["EDIT", [localize "STR_COMSPEC_IARP_W_UnlockWord", localize "STR_COMSPEC_IARP_W_Unlock_D"], _p getOrDefault ["unlockWord", ""]],
            ["EDIT:MULTI", [localize "STR_COMSPEC_IARP_W_Secrets", localize "STR_COMSPEC_IARP_W_Secrets_D"], [(_p getOrDefault ["secrets", []]) joinString _nl, { _this }, 4]]
        ], {
            params ["_v", "_args"];
            _v params ["_story", "_know", "_unlock", "_word", "_secrets"];
            ["profileTweak", [[["story", _story], ["know", _know], ["unlock", _unlock], ["unlockWord", trim _word], ["secrets", _secrets]]], _args select 0] call comspec_sse_iarpZenSend;
        }, {}, [_units]] call zen_dialog_fnc_create;
    }] call comspec_sse_iarpZenWithProfile;
}, _has, [_root, _root + "_profile"], 86] call _mk;

["_answers", "STR_COMSPEC_IARP_Z_Answers", _ip + "mod_doc.paa", {
    ["open", ["units", _this call comspec_sse_iarpZenTargets]] call comspec_sse_fnc_iarpAnswersUi;
}, _has, [_root, _root + "_profile"], 85] call _mk;
["_frame", "STR_COMSPEC_IARP_Z_Frame", _ip + "mod_flag.paa", {
    [_this call comspec_sse_iarpZenTargets, {
        params ["_t", "_p", "_who", "_units"];
        private _ev = ["auto", "none", "known", "free"];
        private _th = ["themes"] call comspec_sse_fnc_iarpAnswers;
        private _ban = _p getOrDefault ["banned", []];
        private _rows = [["LIST", [localize "STR_COMSPEC_IARP_A_Evoke", localize "STR_COMSPEC_IARP_A_Evoke_D"],
            [_ev, _ev apply { localize ("STR_COMSPEC_IARP_A_Evoke_" + _x) }, (_ev find (_p getOrDefault ["evoke", "auto"])) max 0]]];
        { _rows pushBack ["CHECKBOX", [localize format ["STR_COMSPEC_IARP_A_Ban%1", _forEachIndex + 1], localize format ["STR_COMSPEC_IARP_A_Ban%1_D", _forEachIndex + 1]], _x in _ban]; } forEach _th;
        [["STR_COMSPEC_IARP_Z_Frame", _units, _who] call comspec_sse_iarpZenTitle, _rows, {
            params ["_v", "_args"];
            private _th = ["themes"] call comspec_sse_fnc_iarpAnswers;
            private _b = [];
            { if ((_v select (_forEachIndex + 1)) isEqualTo true) then { _b pushBack _x; }; } forEach _th;
            ["profileTweak", [[["evoke", _v select 0], ["banned", _b]]], _args select 0] call comspec_sse_iarpZenSend;
        }, {}, [_units]] call zen_dialog_fnc_create;
    }] call comspec_sse_iarpZenWithProfile;
}, _has, [_root, _root + "_profile"], 84] call _mk;

// --- Identité et papiers (addon identity : mêmes opérations serveur que ses modules « Donner des papiers », « Voir les
// vrais papiers » et que la page « IA de la zone » de l'éditeur SSE) --------------------------------------------------
private _idIcon = "\z\comspec_sse\addons\identity\data\icon_card_ca.paa";
["_id", "STR_COMSPEC_IARP_Z_MId", _idIcon, {}, _hasId, [_root], 80] call _mk;
["_truth", "STR_COMSPEC_IARP_Z_Truth", _ip + "mod_evidence.paa", {
    private _u = (_this call comspec_sse_iarpZenTargets) param [0, objNull];
    if (!isNull _u) then { ["send", ["truth", [player, _u]]] call comspec_sse_fnc_idClient; };
}, _hasId, [_root, _root + "_id"], 79] call _mk;
["_give", "STR_COMSPEC_IARP_Z_Give", _idIcon, {
    private _u = _this call comspec_sse_iarpZenTargets;
    if (_u isEqualTo []) exitWith {};
    [["STR_COMSPEC_IARP_Z_Give", _u] call comspec_sse_iarpZenTitle, [
        ["LIST", [localize "STR_COMSPEC_IARP_Z_GiveType", localize "STR_COMSPEC_IARP_Z_GiveType_D"], [["id_card", "passport", "driving_license", "military_id"],
            ["DocId", "DocPassport", "DocLicense", "DocMil"] apply { localize ("STR_COMSPEC_IARP_Z_" + _x) }, 0]],
        ["LIST", [localize "STR_COMSPEC_IARP_Z_GiveKind", localize "STR_COMSPEC_IARP_Z_GiveKind_D"], [["civil", "cover", "forged"], ["KindCivil", "KindCover", "KindForged"] apply { localize ("STR_COMSPEC_IARP_Z_" + _x) }, 0]],
        ["SLIDER", [localize "STR_COMSPEC_IARP_Z_GiveQuality", localize "STR_COMSPEC_IARP_Z_GiveQuality_D"], [0, 100, 50, 0]],
        ["EDIT", [localize "STR_COMSPEC_IARP_Z_GiveLast", localize "STR_COMSPEC_IARP_Z_GiveName_D"], ""],
        ["EDIT", [localize "STR_COMSPEC_IARP_Z_GiveFirst", localize "STR_COMSPEC_IARP_Z_GiveName_D"], ""]
    ], {
        params ["_v", "_args"];
        _v params ["_type", "_kind", "_q", "_last", "_first"];
        _args params ["_units"];
        // Un nom imprimé imposé ne vaut que pour une seule IA ; en lot, chaque IA garde (ou reçoit) le sien.
        private _one = (count _units) isEqualTo 1;
        private _spec = createHashMapFromArray [["type", _type], ["kind", _kind], ["quality", round _q], ["last", [ "", trim _last] select _one], ["first", ["", trim _first] select _one]];
        // Anti-flood du serveur d'identité (0,5 s par opération) : une remise toutes les 0,6 s.
        { [{ ["send", ["give", _this]] call comspec_sse_fnc_idClient; }, [player, _x, _spec], _forEachIndex * 0.6] call CBA_fnc_waitAndExecute; } forEach _units;
    }, {}, [_u]] call zen_dialog_fnc_create;
}, _hasId, [_root, _root + "_id"], 78] call _mk;
["_strip", "STR_COMSPEC_IARP_Z_Strip", _ip + "mod_trash.paa", {
    ["papersOff", [], _this call comspec_sse_iarpZenTargets] call comspec_sse_iarpZenSend;
}, _hasId, [_root, _root + "_id"], 77] call _mk;
["_editor", "STR_COMSPEC_IARP_Z_Editor", _ip + "mod_check.paa", {
    params ["_pos"];
    [_this call comspec_sse_iarpZenTargets, ASLToATL _pos] call comspec_sse_fnc_aiConfigOpen;
}, { !isNil "comspec_sse_fnc_aiConfigOpen" && {(_this call comspec_sse_iarpZenTargets) isNotEqualTo []} }, [_root, _root + "_id"], 76] call _mk;

// --- État et comportement ------------------------------------------------------------------------------------------
["_state", "STR_COMSPEC_IARP_Z_MState", _ip + "mod_person.paa", {}, _has, [_root], 70] call _mk;
private _order = { ["zenState", [_this param [6, ""]], _this call comspec_sse_iarpZenTargets] call comspec_sse_iarpZenSend; };
{
    _x params ["_act", "_key", "_prio", ["_needFsm", false]];
    ["_st_" + _act, _key, _ip + "mod_person.paa", _order, [_has, _hasFsm] select _needFsm, [_root, _root + "_state"], _prio, _act] call _mk;
} forEach [
    ["stop", "STR_COMSPEC_IARP_Z_Stop", 69], ["free", "STR_COMSPEC_IARP_Z_Free", 68], ["surrender", "STR_COMSPEC_IARP_Z_Surrender", 67],
    ["flee", "STR_COMSPEC_IARP_Z_Flee", 66], ["aggressive", "STR_COMSPEC_IARP_Z_Aggro", 65, true], ["calm", "STR_COMSPEC_IARP_Z_Calm", 64]
];
["_fsm", "STR_COMSPEC_IARP_Z_Fsm", _ip + "mod_person.paa", {
    private _u = _this call comspec_sse_iarpZenTargets;
    if (_u isEqualTo []) exitWith {};
    private _st = ["interpelle", "coopere", "ment", "nerveux", "refuse", "fuit", "agressif", "rendu", "renforts", "calme"];
    [["STR_COMSPEC_IARP_Z_Fsm", _u] call comspec_sse_iarpZenTitle, [
        ["LIST", [localize "STR_COMSPEC_IARP_Z_FsmState", localize "STR_COMSPEC_IARP_Z_FsmState_D"], [_st, _st apply { localize ("STR_COMSPEC_IARP_Z_St_" + _x) }, 0, 10]]
    ], {
        params ["_v", "_args"];
        ["zenState", ["fsm:" + (_v select 0)], _args select 0] call comspec_sse_iarpZenSend;
    }, {}, [_u]] call zen_dialog_fnc_create;
}, _hasFsm, [_root, _root + "_state"], 63] call _mk;

// --- Mémoire par joueur --------------------------------------------------------------------------------------------
["_mem", "STR_COMSPEC_IARP_Z_MMem", _ip + "mod_data.paa", {}, _has, [_root], 60] call _mk;
["_memView", "STR_COMSPEC_IARP_Z_MemView", _ip + "mod_data.paa", {
    private _u = (_this call comspec_sse_iarpZenTargets) param [0, objNull];
    if (!isNull _u) then { ["send", ["memGet", [player, _u]]] call comspec_sse_fnc_iarpClient; };
}, _has, [_root, _root + "_mem"], 59] call _mk;
["_memClear", "STR_COMSPEC_IARP_Z_MemClear", _ip + "mod_trash.paa", {
    ["memClear", [""], _this call comspec_sse_iarpZenTargets] call comspec_sse_iarpZenSend;
}, _has, [_root, _root + "_mem"], 58] call _mk;
["_memPlayer", "STR_COMSPEC_IARP_Z_MemClearPlayer", _ip + "mod_trash.paa", {
    private _u = _this call comspec_sse_iarpZenTargets;
    if (_u isEqualTo []) exitWith {};
    private _pl = (allPlayers select { !(_x isKindOf "HeadlessClient_F") });
    if (!isNull player) then { _pl pushBackUnique player; };
    if (_pl isEqualTo []) exitWith {};
    // Même identifiant que la mémoire de l'IA (fn_iarpServer) : Steam ID, sinon « N: » et le nom.
    private _ids = _pl apply { private _i = getPlayerUID _x; [_i, "N:" + name _x] select (_i isEqualTo "") };
    [["STR_COMSPEC_IARP_Z_MemClearPlayer", _u] call comspec_sse_iarpZenTitle, [
        ["LIST", [localize "STR_COMSPEC_IARP_Z_MemPlayer", localize "STR_COMSPEC_IARP_Z_MemPlayer_D"], [_ids, _pl apply { name _x }, 0, 8]]
    ], {
        params ["_v", "_args"];
        ["memClear", [_v select 0], _args select 0] call comspec_sse_iarpZenSend;
    }, {}, [_u]] call zen_dialog_fnc_create;
}, _has, [_root, _root + "_mem"], 57] call _mk;

// --- Réactions de ces IA (tirs proches, témoin) ----------------------------------------------------------------------
["_react", "STR_COMSPEC_IARP_Z_React", _ip + "mod_flag.paa", {
    private _u = _this call comspec_sse_iarpZenTargets;
    if (_u isEqualTo []) exitWith {};
    private _off = (_u select 0) getVariable ["comspec_sse_iarpReactOff", []];
    if !(_off isEqualType []) then { _off = []; };
    [["STR_COMSPEC_IARP_Z_React", _u] call comspec_sse_iarpZenTitle, [
        ["CHECKBOX", [localize "STR_COMSPEC_IARP_Z_ReactShots", localize "STR_COMSPEC_IARP_Z_ReactShots_D"], !("shots" in _off)],
        ["CHECKBOX", [localize "STR_COMSPEC_IARP_Z_ReactWitness", localize "STR_COMSPEC_IARP_Z_ReactWitness_D"], !("witness" in _off)]
    ], {
        params ["_v", "_args"];
        ["reactSet", [(_v select 0) isEqualTo true, (_v select 1) isEqualTo true], _args select 0] call comspec_sse_iarpZenSend;
    }, {}, [_u]] call zen_dialog_fnc_create;
}, _has, [_root], 50] call _mk;

// --- Faire parler, journal ----------------------------------------------------------------------------------------
["_puppet", "STR_COMSPEC_IARP_Z_Puppet", _icon, {
    [(_this call comspec_sse_iarpZenTargets) param [0, objNull]] call comspec_sse_iarpZenPuppet;
}, _has, [_root], 40] call _mk;
["_log", "STR_COMSPEC_IARP_Z_Log", _ip + "mod_doc.paa", {
    ["open", [objNull, "log"]] call comspec_sse_fnc_iarpUi;
}, _has, [_root], 30] call _mk;
true
