/*
    Animation d'une analyse dans le téléphone (lecture MRZ, puce NFC, interrogation du fichier, SEEK II) : barre de
    balayage façon lecteur réel et étapes qui s'enchaînent, puis code de fin. L'écran n'est jamais redessiné pendant
    l'animation : la page pose une ligne « text » gardée sous le nom _var (fn_formRender), réécrite ici toutes les 0,08 s.
    Aucune action bloquée : le joueur peut bouger, changer d'app, revenir (la ligne est retrouvée au redessin).
      ["start", [clé, [[libellé, durée]…], _var, code de fin, arguments, sujet, code d'abandon]] -> durée totale
          sujet (objet, facultatif) : l'analyse s'arrête s'il s'éloigne à plus de 4 m (code d'abandon [arguments, motif])
      ["text", clé] -> texte structuré de l'état courant (pour le premier dessin de la page)
      ["busy", clé] -> true si cette analyse tourne        ["stop", clé]  arrêt sans fin ni abandon
*/
params [["_op", ""], ["_a", []]];
private _all = uiNamespace getVariable ["comspec_sse_scans", createHashMap];
uiNamespace setVariable ["comspec_sse_scans", _all];
private _txt = {
    params ["_e"];
    private _t = time - (_e get "t0");
    private _steps = _e get "steps";
    private _tot = _e get "total";
    private _p = ((_t / _tot) max 0) min 1;
    private _n = 24;
    private _f = floor (_p * _n);
    // Balayage : la tête « > » avance, une lueur repasse sur la partie lue.
    private _sweep = floor ((_t * 12) mod (_f max 1));
    private _bar = "";
    for "_i" from 0 to (_n - 1) do {
        _bar = _bar + (call {
            if (_i < _f) exitWith { ["=", "#"] select (_i isEqualTo _sweep) };
            if (_i isEqualTo _f) exitWith { ">" };
            "."
        });
    };
    private _acc = 0;
    private _lines = [];
    {
        _x params ["_l", "_d"];
        private _st = call {
            if (_t >= (_acc + _d)) exitWith { "<t color='#34d399'>OK</t>" };
            if (_t >= _acc) exitWith { format ["<t color='#f2c94c'>%1</t>", [".  ", ".. ", "..."] select (floor (_t * 4) mod 3)] };
            "<t color='#5b6673'>-- </t>"
        };
        _lines pushBack format ["<t font='EtelkaMonospacePro' size='0.8'>%1</t> <t size='0.85'>%2</t>", _st, (_l call comspec_sse_fnc_tr) regexReplace ["&", "&amp;"]];
        _acc = _acc + _d;
    } forEach _steps;
    format ["<t font='EtelkaMonospaceProBold' size='0.95' color='#8fd3ff'>[%1] %2 %%</t><br/>%3", _bar, round (_p * 100), _lines joinString "<br/>"]
};
switch (_op) do {
    case "text": { private _e = _all getOrDefault [_a param [0, ""], createHashMap]; if ((count _e) isEqualTo 0) exitWith { "" }; [_e] call _txt };
    case "busy": { (_a param [0, ""]) in _all };
    case "stop": {
        private _e = _all getOrDefault [_a param [0, ""], createHashMap];
        if ((count _e) > 0) then { [_e get "pfh"] call CBA_fnc_removePerFrameHandler; _all deleteAt (_a param [0, ""]); };
        true
    };
    case "start": {
        _a params [["_key", ""], ["_steps", []], ["_var", ""], ["_done", {}], ["_args", []], ["_subj", objNull], ["_abort", {}]];
        ["stop", [_key]] call comspec_sse_fnc_scanAnim;
        private _tot = 0;
        { _tot = _tot + (_x select 1); } forEach _steps;
        private _e = createHashMapFromArray [["key", _key], ["steps", _steps], ["var", _var], ["done", _done], ["args", _args], ["subj", _subj],
            ["abort", _abort], ["t0", time], ["total", _tot max 0.3]];
        _all set [_key, _e];
        private _h = [{
            params ["_e", "_h"];
            private _all = uiNamespace getVariable ["comspec_sse_scans", createHashMap];
            private _subj = _e get "subj";
            private _end = {
                [_h] call CBA_fnc_removePerFrameHandler;
                _all deleteAt (_e get "key");
            };
            if (!alive player || {!isNull _subj && {!alive _subj || {(player distance _subj) > 4}}}) exitWith {
                call _end;
                [_e get "args", ["Analyse interrompue : la personne s'est éloignée.", "Analyse interrompue."] select (alive player)] call (_e get "abort");
            };
            private _c = uiNamespace getVariable [_e get "var", controlNull];
            if (!isNull _c) then { _c ctrlSetStructuredText parseText ([_e] call (uiNamespace getVariable ["comspec_sse_scanTxt", {""}])); };
            if ((time - (_e get "t0")) >= (_e get "total")) exitWith {
                call _end;
                (_e get "args") call (_e get "done");
            };
        }, 0.08, _e] call CBA_fnc_addPerFrameHandler;
        uiNamespace setVariable ["comspec_sse_scanTxt", _txt];
        _e set ["pfh", _h];
        _tot
    };
    default { false };
};
