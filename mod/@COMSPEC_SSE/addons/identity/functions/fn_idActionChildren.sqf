/*
    Sous-actions ACE du contrôle d'identité, calculées à l'ouverture du menu (insertChildren) : rien par image.
    [_mode, _target, _player, _arg] call comspec_sse_fnc_idActionChildren -> [[action, [], cible]…]
      tokens   documents que la cible m'a présentés (jeton encore valable) : examiner / saisir
      found    documents trouvés à la dernière fouille de la cible : examiner / saisir
      doc      [poignée] : examiner (2 s, N0) et saisir (2 s)
      return   documents que je lui ai saisis : rendre (1 s)
      present  mes documents : présenter / remettre (1 s)       consult  mes documents : lire
      questions  questions de légende à poser à un PNJ (1 s)    answers  réponses au choix à la question en attente
    Libellés en français, traduits par comspec_sse_fnc_aceAction. Durées : comspec_sse_fnc_progressAction.
*/
params [["_mode", ""], ["_target", objNull], ["_player", objNull], ["_arg", ""]];
private _out = [];
private _add = {
    params ["_id", "_name", "_code", "_args", ["_children", {}], ["_icon", ""]];
    _out pushBack [[_id, _name, _icon, _code, { true }, _children, _args] call comspec_sse_fnc_aceAction, [], _target];
};
private _nameOf = { getText (configFile >> "CfgWeapons" >> _this >> "displayName") };
private _mine = ([_player, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 };

switch (_mode) do {
    case "doc": {
        _arg params [["_h", ""]];
        ["COMSPEC_ID_View", "Examiner (contrôle visuel)", {
            params ["_t", "_p", "_args"];
            _args params ["_h"];
            ["send", ["begin", [_p, "view", _h]]] call comspec_sse_fnc_idClient;
            [2, "Examen du document…", { params ["_h"]; ["send", ["view", [player, _h]]] call comspec_sse_fnc_idClient; }, [_h], _t] call comspec_sse_fnc_progressAction;
        }, [_h], {}, "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa"] call _add;
        ["COMSPEC_ID_Seize", "Saisir", {
            params ["_t", "_p", "_args"];
            _args params ["_h"];
            ["send", ["begin", [_p, "seize", _t]]] call comspec_sse_fnc_idClient;
            [2, "Saisie du document…", { params ["_t", "_h"]; ["send", ["seize", [player, _t, _h]]] call comspec_sse_fnc_idClient; }, [_t, _h], _t] call comspec_sse_fnc_progressAction;
        }, [_h], {}, "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa"] call _add;
    };
    case "tokens": {
        private _tok = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
        {
            _y params ["_holder", "_label", "_end"];
            if (_holder isEqualTo _target && {_end > time}) then {
                [format ["COMSPEC_ID_Tok_%1", _x], _label, {}, [_x], { ["doc", _this select 0, _this select 1, [(_this select 2) select 0]] call comspec_sse_fnc_idActionChildren }] call _add;
            };
        } forEach _tok;
    };
    case "found": {
        (missionNamespace getVariable ["comspec_sse_idSearch", [objNull, [], -999]]) params ["_t", "_list", "_at"];
        if (_t isNotEqualTo _target || {(time - _at) > 180}) exitWith {};
        {
            _x params ["_h", "_cls", "_label"];
            // Saisi depuis : n'est plus sur lui.
            if (_cls isEqualTo "" || {!(_cls in _mine)}) then {
                [format ["COMSPEC_ID_Found_%1", _forEachIndex], _label, {}, [_h], { ["doc", _this select 0, _this select 1, [(_this select 2) select 0]] call comspec_sse_fnc_idActionChildren }] call _add;
            };
        } forEach _list;
    };
    case "return": {
        private _s = missionNamespace getVariable ["comspec_sse_idSeized", createHashMap];
        {
            if (_y isEqualTo (netId _target) && {_x in _mine}) then {
                [format ["COMSPEC_ID_Ret_%1", _x], format ["Rendre : %1", _x call _nameOf], {
                    params ["_t", "_p", "_args"];
                    ["send", ["begin", [_p, "return", _t]]] call comspec_sse_fnc_idClient;
                    [1, "Vous rendez le document…", { params ["_t", "_cls"]; ["send", ["return", [player, _t, _cls]]] call comspec_sse_fnc_idClient; }, [_t, _args select 0], _t] call comspec_sse_fnc_progressAction;
                }, [_x]] call _add;
            };
        } forEach _s;
    };
    case "present": {
        {
            [format ["COMSPEC_ID_Pres_%1", _forEachIndex], _x call _nameOf, {}, [_x], {
                params ["_t", "_p", "_args"];
                private _cls = _args select 0;
                [
                    [["COMSPEC_ID_PresOne", "Présenter", "", { ["present", [(_this select 2) select 0, "present"]] call comspec_sse_fnc_idPresentPrompt }, { true }, {}, [_cls]] call comspec_sse_fnc_aceAction, [], _t],
                    [["COMSPEC_ID_GiveOne", "Remettre", "", { ["present", [(_this select 2) select 0, "give"]] call comspec_sse_fnc_idPresentPrompt }, { true }, {}, [_cls]] call comspec_sse_fnc_aceAction, [], _t]
                ]
            }, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"] call _add;
        } forEach _mine;
    };
    case "questions": {
        {
            _x params ["_k", "_q"];
            [format ["COMSPEC_ID_Q_%1", _k], _q, {
                params ["_t", "_p", "_args"];
                ["send", ["begin", [_p, "question", _t]]] call comspec_sse_fnc_idClient;
                [1, "Vous lui posez la question…", { params ["_t", "_k"]; ["send", ["aiQuestion", [player, _t, _k]]] call comspec_sse_fnc_idClient; }, [_t, _args select 0], _t] call comspec_sse_fnc_progressAction;
            }, [_k]] call _add;
        } forEach [
            ["origin", "D'où venez-vous ?"], ["employer", "Pour qui travaillez-vous ?"], ["travel", "Raison du déplacement ?"],
            ["job", "Quel est votre métier ?"], ["pob", "Où êtes-vous né ?"], ["contact", "Qui peut confirmer ce que vous dites ?"]
        ];
    };
    case "answers": {
        (missionNamespace getVariable ["comspec_sse_idQuestion", []]) params [["_w", ""], ["_l", []], ["_end", 0], ["_done", createHashMap]];
        private _open = _l select { !((_x select 0) in _done) };
        if (_open isEqualTo [] || {_end <= time}) exitWith {};
        (_open select 0) params ["_qid", "_text", "_opts"];
        {
            [format ["COMSPEC_ID_A_%1", _forEachIndex], format ["%1 → %2", _text call comspec_sse_fnc_tr, _x call comspec_sse_fnc_tr], {
                params ["_t", "_p", "_args"];
                ["act", "answer", _args] call comspec_sse_fnc_idCoverPage;
            }, [_qid, _forEachIndex]] call _add;
        } forEach _opts;
    };
    case "consult": {
        {
            [format ["COMSPEC_ID_Read_%1", _forEachIndex], _x call _nameOf, {
                params ["_t", "_p", "_args"];
                // Mon propre papier : la visionneuse s'ouvre tout de suite (ni barre de progression ni message d'interruption).
                // Classe retenue pour le bouton PRÉSENTER de la visionneuse (fn_idP2pClient « fromViewer »).
                missionNamespace setVariable ["comspec_sse_idOwnViewCls", _args select 0];
                ["send", ["view", [player, _args select 0]]] call comspec_sse_fnc_idClient;
            }, [_x], {}, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"] call _add;
        } forEach _mine;
    };
};
_out
