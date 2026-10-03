/*
    App Feux : 9-LINE (appui aérien), 5-LINE (hélico / gunship) et APPEL DE FEU (mortier, artillerie).
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
            ["edit", "n2", "2. Cap IP → cible (°)", ["n2"] call _v],
            ["edit", "n3", "3. Distance IP → cible (km)", ["n3"] call _v],
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
    default {
        // Position de tir : pièces du camp ou position manuelle.
        private _guns = [] call comspec_atak_native_fnc_firesGuns;
        private _gunItems = (_guns apply { [format ["%1 · %2 · %3", _x getVariable ["COMSPEC_ATAK_GunName", getText (configOf _x >> "displayName")], [_x, 8] call comspec_atak_native_fnc_gridRef, ["IA", "joueur"] select (isPlayer gunner _x)], netId _x] }) + [["Position manuelle (grille ci-dessous)", "MAN"]];
        private _sel = ["gun", (_gunItems select 0) select 1] call _v;
        _rows append [
            ["title", "Position de tir"],
            ["combo", "gun", "Pièce", _gunItems, _sel],
            ["edit", "gunGrid", "Grille de la pièce (manuel)", ["gunGrid"] call _v],
            ["buttons", [["PIÈCE À MA POSITION", { ["gunHere"] call comspec_atak_native_fnc_firesAction; }]]],
            ["title", "Cible"],
            ["buttons", [
                [["GRILLE", "● GRILLE"] select ((["mode", "GRID"] call _v) isEqualTo "GRID"), { ["mode", "GRID"] call comspec_atak_native_fnc_firesAction; }, (["mode", "GRID"] call _v) isEqualTo "GRID"],
                [["POLAIRE", "● POLAIRE"] select ((["mode", "GRID"] call _v) isEqualTo "POLAR"), { ["mode", "POLAR"] call comspec_atak_native_fnc_firesAction; }, (["mode", "GRID"] call _v) isEqualTo "POLAR"],
                ["SUR LA CARTE", { ["pick", "cff"] call comspec_atak_native_fnc_firesAction; }]
            ]]
        ];
        if ((["mode", "GRID"] call _v) isEqualTo "POLAR") then {
            _rows append [
                ["edit", "az", "Azimut depuis ma position (millièmes)", ["az", str round ((getDir player) * 6400 / 360)] call _v],
                ["edit", "dist", "Distance (m)", ["dist", "600"] call _v]
            ];
        } else {
            _rows pushBack ["edit", "tgtGrid", "Grille de la cible (8 ou 10 chiffres)", ["tgtGrid", _tgtGrid] call _v];
        };
        _rows append [
            ["combo", "ammo", "Munition", [["Explosif (HE)", "HE"], ["Éclairant", "ILLUM"], ["Fumigène", "SMOKE"]], ["ammo", "HE"] call _v],
            ["edit", "rounds", "Nombre de coups", ["rounds", "3"] call _v],
            ["buttons", [[["GERBE : NON", "● GERBE : OUI"] select (["sheaf", false] call _v), { ["sheaf"] call comspec_atak_native_fnc_firesAction; }, ["sheaf", false] call _v]]]
        ];
        private _sol = _f getOrDefault ["solution", createHashMap];
        if ((count _sol) > 0) then {
            _rows pushBack ["text", format [
                "<t color='#5cc76b' font='RobotoCondensedBold'>%1</t>   <t color='#8a9a93'>Distance</t> %2 m   <t color='#8a9a93'>Azimut</t> %3 mil<br/><t color='#8a9a93'>Durée de vol</t> %4   <t color='#8a9a93'>Portée</t> %5   <t color='#8a9a93'>Cible</t> %6",
                _sol getOrDefault ["gunName", "?"], round (_sol getOrDefault ["dist", 0]), round (_sol getOrDefault ["mils", 0]),
                if ((_sol getOrDefault ["eta", -1]) > 0) then { format ["%1 s", (_sol get "eta") toFixed 1] } else { "—" },
                ["<t color='#e5483a'>HORS PORTÉE</t>", "<t color='#5cc76b'>OK</t>"] select (_sol getOrDefault ["inRange", false]), _sol getOrDefault ["grid", ""]]];
        };
        _rows pushBack ["buttons", [
            ["CALCULER", { ["compute"] call comspec_atak_native_fnc_firesAction; }],
            ["FIN DE MISSION", { ["eom"] call comspec_atak_native_fnc_firesAction; }],
            ["TIR", { ["shot"] call comspec_atak_native_fnc_firesAction; }, true]
        ]];
        _rows pushBack ["title", "Journal des missions"];
        private _log = _f getOrDefault ["log", []];
        if ((count _log) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune mission pour l'instant.</t>"]; };
        { _rows pushBack ["text", _x]; } forEach (+_log call { reverse _this; _this select [0, 8] });
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
