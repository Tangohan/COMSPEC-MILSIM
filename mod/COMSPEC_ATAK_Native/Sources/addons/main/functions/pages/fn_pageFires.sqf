/*
    App CAS : 9-LINE (appui aérien), 5-LINE (hélico / gunship) et APPEL DE FEU (mortier, artillerie, écran fn_firesCff).
    - 9-line : envoyé à Athena (/api/cas, vu par le pilote et le web) ; sinon en ordre CAS.
    - Appel de feu : pièce du camp (calculateur d'Arma), cible en grille, en polaire ou pointée sur la carte,
      munition, nombre de coups, gerbe ; calcul (distance, azimut en millièmes, durée de vol, portée),
      TIR (pièce IA : tir réel ; pièce tenue par un joueur : mission envoyée sur son téléphone), FIN DE MISSION.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _f = [] call comspec_atak_native_fnc_firesState;
private _tab = _f getOrDefault ["tab", "cff"];
private _tabBtn = {
    params ["_label", "_key"];
    [_label, compile format ["['tab', '%1'] call comspec_atak_native_fnc_firesAction;", _key], _tab isEqualTo _key]
};
// APPEL DE FEU : écran du clip (fn_firesCff), sous un bandeau d'onglets et de position façon « Call for Fire ».
if !(_tab in ["nine", "five"]) exitWith {
    private _fl = [] call comspec_atak_native_fnc_layoutGet;
    private _font = _fl get "font";
    private _fs = _fl get "fontSmall";
    private _pad = (_fl get "pad") * 1.6;
    private _hh = _font * 1.6;
    private _top = ["COMSPEC_RscText", [0, 0, _bw, _hh]] call comspec_atak_native_fnc_pageCtrl;
    _top ctrlSetBackgroundColor [0.020, 0.026, 0.023, 1];
    private _acc = ([] call comspec_atak_native_fnc_accent) select 0;
    private _topLine = ["COMSPEC_RscText", [0, _hh - pixelH, _bw, pixelH]] call comspec_atak_native_fnc_pageCtrl;
    _topLine ctrlSetBackgroundColor [_acc select 0, _acc select 1, _acc select 2, 0.45];
    private _tw = (_bw * 0.13) max (_fs * 5 * pixelH / pixelW);
    {
        _x params ["_t", "_k"];
        private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_tw + _pad / 2), _hh * 0.12, _tw, _hh * 0.76], _t] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_fs * 0.95);
        private _on = _tab isEqualTo _k || {_k isEqualTo "cff" && {!(_tab in ["nine", "five"])}};
        _b ctrlSetBackgroundColor ([[0.11, 0.135, 0.12, 1], [_acc select 0, _acc select 1, _acc select 2, 1]] select _on);
        _b ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.03, 0.05, 0.04, 1]] select _on);
        _b ctrlAddEventHandler ["ButtonClick", compile format ["['tab', '%1'] call comspec_atak_native_fnc_firesAction;", _k]];
    } forEach [["9-LINE", "nine"], ["5-LINE", "five"], ["APPEL DE FEU", "cff"]];
    // À droite : ma grille, mon altitude, le vent.
    private _info = ["COMSPEC_RscTextRight", [_pad + 3 * (_tw + _pad / 2), 0, _bw - _pad * 2 - 3 * (_tw + _pad / 2), _hh]] call comspec_atak_native_fnc_pageCtrl;
    _info ctrlSetFontHeight (_fs * 0.95);
    _info ctrlSetTextColor [0.78, 0.86, 0.82, 1];
    _info ctrlSetText ((format ["%1   %2   %3", [player, 10] call comspec_atak_native_fnc_gridRef,
        format [("ÉLÉV %1 m") call comspec_atak_native_fnc_tr, round ((getPosASL player) select 2)], ([] call comspec_atak_native_fnc_firesWind) select 0]) call comspec_atak_native_fnc_tr);
    [[0, _hh, _bw, _bh - _hh]] call comspec_atak_native_fnc_firesCff;
    true
};
private _rows = [["buttons", [["9-LINE", "nine"] call _tabBtn, ["5-LINE", "five"] call _tabBtn, ["APPEL DE FEU", "cff"] call _tabBtn]]];
private _hint = _f getOrDefault ["hint", ["", false]];
if ((_hint select 0) isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), _hint select 0]]; };
private _v = { params ["_k", ["_d", ""]]; _f getOrDefault [_k, _d] };
private _tgt = _f getOrDefault ["target", []];
private _tgtGrid = if ((count _tgt) >= 2) then { [_tgt, 8] call comspec_atak_native_fnc_gridRef } else { "" };

switch (_tab) do {
    case "nine": {
        private _laser = player getVariable ["ace_laser_code", -1];
        _rows append [
            ["text", "<t color='#8a9a93'>Pré-rempli depuis la cible pointée (bouton CIBLE SUR LA CARTE) et votre position.</t>"],
            ["buttons", [["CIBLE SUR LA CARTE", { ["pick", "nine"] call comspec_atak_native_fnc_firesAction; }], ["PRÉ-REMPLIR", { ["prefill9"] call comspec_atak_native_fnc_firesAction; }]]],
            ["edit", "n1", "1. IP / BP", ["n1"] call _v],
            ["edit", "n2", "2. Cap IP » cible (°)", ["n2"] call _v],
            ["edit", "n3", "3. Distance IP » cible (km)", ["n3"] call _v],
            ["edit", "n4", "4. Altitude cible (m)", ["n4"] call _v],
            ["edit", "n5", "5. Description de la cible", ["n5"] call _v],
            ["edit", "n6", "6. Position de la cible (grille)", ["n6", _tgtGrid] call _v],
            ["edit", "n7", "7. Marquage (laser, fumigène, IR…)", ["n7", ["", format ["Laser %1", _laser]] select (_laser isEqualType 0 && {_laser > 0})] call _v],
            ["edit", "n8", "8. Amis (direction et distance depuis la cible)", ["n8"] call _v],
            ["edit", "n9", "9. Dégagement", ["n9"] call _v],
            ["edit", "nr", "Remarques (menaces, restrictions, contrôle)", ["nr"] call _v],
            ["buttons", [["ENVOYER LE 9-LINE", { ["send9"] call comspec_atak_native_fnc_firesAction; }, true], ["EFFACER", { ["clear", "nine"] call comspec_atak_native_fnc_firesAction; }]]]
        ];
    };
    case "five": {
        _rows append [
            ["buttons", [["CIBLE SUR LA CARTE", { ["pick", "five"] call comspec_atak_native_fnc_firesAction; }], ["PRÉ-REMPLIR", { ["prefill5"] call comspec_atak_native_fnc_firesAction; }]]],
            ["edit", "f1", "1. Indicatif de l'observateur / mission", ["f1"] call _v],
            ["edit", "f2", "2. Position et marquage des amis", ["f2"] call _v],
            ["edit", "f3", "3. Position de la cible", ["f3", _tgtGrid] call _v],
            ["edit", "f4", "4. Description et marquage de la cible", ["f4"] call _v],
            ["edit", "f5", "5. Remarques (danger close, axe d'attaque…)", ["f5"] call _v],
            ["buttons", [["ENVOYER LE 5-LINE", { ["send5"] call comspec_atak_native_fnc_firesAction; }, true], ["EFFACER", { ["clear", "five"] call comspec_atak_native_fnc_firesAction; }]]]
        ];
    };
    default {};
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
