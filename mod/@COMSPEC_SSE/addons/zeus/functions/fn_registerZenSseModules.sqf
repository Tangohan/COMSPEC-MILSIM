/*
    Modules SSE de Zeus Enhanced (ZEN) : dossier, identité et biométrie d'un sujet,
    dotation SEEK, présentation des documents. Repris du mod Overwatch : tout le SSE
    passe par ce pack. Les modules CfgVehicles équivalents servent à Eden.
*/
if (!hasInterface) exitWith {};
if (isNil "zen_custom_modules_fnc_register") exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZenSseModulesRegistered", false]) exitWith {};

private _icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";

// --- Dossier SSE actif ---------------------------------------------------
[
    "COMSPEC SSE · Mission",
    "Dossier SSE actif",
    {
        private _current = [] call comspec_sse_fnc_getCaseReference;
        [
            "Dossier SSE actif",
            [
                ["EDIT", ["Référence", "Dossier ouvert côté portail, par exemple SSE-2026-0007. Laisser vide efface le dossier actif."], _current]
            ],
            {
                params ["_values"];
                _values params ["_ref"];
                private _clean = toUpper (trim _ref);
                // Diffusé à tous (et aux joueurs qui rejoignent) : le dossier est un contexte d'élément.
                [_clean] remoteExecCall ["comspec_sse_fnc_setCaseReference", 2];
                [["Dossier SSE actif effacé.", format ["Dossier SSE actif : %1", _clean]] select (_clean isNotEqualTo "")] call comspec_sse_fnc_zeusNotify;
            },
            {},
            []
        ] call zen_dialog_fnc_create;
    },
    _icon
] call comspec_sse_fnc_zenModule;

// --- Profil d'identité ---------------------------------------------------
[
    "COMSPEC SSE · Personnes",
    "Profil d'identité SSE",
    {
        params ["_pos", "_obj"];

        if (isNull _obj || { !(_obj isKindOf "CAManBase") }) exitWith {
            ["Posez ce module sur une personne : c'est son profil que vous réglez.", "warn"] call comspec_sse_fnc_zeusNotify;
        };

        private _presets = ["list"] call comspec_sse_fnc_profilePreset;
        private _labels = ["labels"] call comspec_sse_fnc_profilePreset;

        [
            format ["Profil SSE — %1", name _obj],
            [
                ["LIST", ["Ce que la base doit répondre", "Génération automatique : verdict stable dérivé de la graine du sujet."], [_presets, _labels, 0]],
                ["EDIT", ["Alias connu", "Souvent le seul élément dont dispose le terrain. Vide = génération automatique."], ""],
                ["EDIT", ["Nom", "Vide = génération automatique."], ""],
                ["EDIT", ["Prénom", "Vide = génération automatique."], ""],
                ["EDIT", ["Nationalité déclarée", "Ce que le sujet déclare, pas ce qui est établi."], ""],
                ["EDIT", ["Langue parlée", "Détermine si un interprète est nécessaire."], ""],
                ["EDIT", ["Référence de dossier antérieur", "Affichée en cas de correspondance. Vide = génération automatique."], ""]
            ],
            {
                params ["_values", "_args"];
                _values params ["_preset", "_alias", "_last", "_first", "_nat", "_lang", "_ref"];
                _args params ["_obj"];

                private _profile = [_preset] call comspec_sse_fnc_profilePreset;
                {
                    _x params ["_key", "_v"];
                    if ((trim _v) isNotEqualTo "") then { _profile pushBack [_key, trim _v]; };
                } forEach [
                    ["alias", _alias],
                    ["last_name", _last],
                    ["first_name", _first],
                    ["nationality", _nat],
                    ["language", _lang],
                    ["record_ref", _ref]
                ];

                // Un seul écrivain : le serveur diffuse, les clients lisent.
                [_obj, _profile] remoteExecCall ["comspec_sse_fnc_applyProfile", 2];
                [format ["Profil SSE appliqué à %1.", name _obj], "info"] call comspec_sse_fnc_zeusNotify;
            },
            {},
            [_obj]
        ] call zen_dialog_fnc_create;
    },
    _icon
] call comspec_sse_fnc_zenModule;

// --- Biométrie du sujet (moteur du pack SSE) ------------------------------
[
    "COMSPEC SSE · Personnes",
    "Biométrie du sujet",
    {
        params ["_pos", "_obj"];
        if (isNil "comspec_sse_fnc_bioSetProfile") exitWith {
            ["Pack COMSPEC SSE requis pour la biométrie.", "warn"] call comspec_sse_fnc_zeusNotify;
        };
        if (isNull _obj || { !(_obj isKindOf "CAManBase") }) exitWith {
            ["Posez ce module sur une personne.", "warn"] call comspec_sse_fnc_zeusNotify;
        };
        private _str = { params ["_v"]; private _x0 = _obj getVariable [_v, ""]; if (_x0 isEqualType "") then { _x0 } else { "" } };
        private _dbCodes = ["auto", "absent", "enrole", "bewl1", "bewl2", "bewl3", "bewl4"];
        private _dbLabels = ["Automatique (selon le camp)", "Inconnu de toute base", "Enrôlé, non signalé", "Watchlist tier 1 · DETAIN", "Watchlist tier 2 · QUESTION / DETAIN", "Watchlist tier 3 · QUESTION / DENY ACCESS", "Watchlist tier 4 · pour info"];
        private _dbIdx = (_dbCodes find (toLower (["COMSPEC_SSE_BioDb"] call _str))) max 0;
        private _fpCodes = ["auto", "oui", "non"];
        private _fpIdx = (_fpCodes find (toLower (["COMSPEC_SSE_BioFalsePapers"] call _str))) max 0;
        private _relCodes = ["frere", "parent", "enfant"];
        private _relIdx = (_relCodes find (toLower (["COMSPEC_SSE_BioRelation"] call _str))) max 0;
        private _irisCodes = ["auto", "cataracte", "prothese_od", "prothese_og", "lesion_od", "lesion_og"];
        private _irisIdx = (_irisCodes find (toLower (["COMSPEC_SSE_BioIrisState"] call _str))) max 0;

        [
            format ["Biométrie — %1", name _obj],
            [
                ["LIST", ["Fichier biométrique", "Présence dans la base et niveau de watchlist (BEWL)."], [_dbCodes, _dbLabels, _dbIdx]],
                ["EDIT", ["Motif de signalement", "Affiché par le SEEK en cas de correspondance. Vide = motif généré."], ["COMSPEC_SSE_BioReason"] call _str],
                ["LIST", ["Faux papiers", "Automatique : selon le taux du camp."], [_fpCodes, ["Automatique", "Oui", "Non"], _fpIdx]],
                ["EDIT", ["Vraie identité (si faux papiers)", "Prénom Nom réels : seule une correspondance biométrique les révèle."], ["COMSPEC_SSE_BioTrueName"] call _str],
                ["EDIT", ["Identifiant biométrique (campagne)", "Fixe les empreintes d'un personnage récurrent d'une mission à l'autre. Vide = automatique."], ["COMSPEC_SSE_BioId"] call _str],
                ["EDIT", ["Parent de (variable ou identifiant)", "Nom de variable d'une autre unité : l'ADN révélera la parenté."], ["COMSPEC_SSE_BioRelativeOf"] call _str],
                ["LIST", ["Lien de parenté", ""], [_relCodes, ["Frère / sœur", "Parent", "Enfant"], _relIdx]],
                ["EDIT", ["Doigts manquants (1-10)", "Numérotation FBI : 1 pouce D … 5 auriculaire D, 6 pouce G … 10 auriculaire G. Ex. 2,7"], ["COMSPEC_SSE_BioMissingFingers"] call _str],
                ["LIST", ["Iris", ""], [_irisCodes, ["Automatique", "Cataracte", "Prothèse œil droit", "Prothèse œil gauche", "Lésion œil droit", "Lésion œil gauche"], _irisIdx]]
            ],
            {
                params ["_values", "_args"];
                _values params ["_db", "_reason", "_fp", "_true", "_bioId", "_relOf", "_rel", "_missing", "_iris"];
                _args params ["_obj"];
                private _pairs = [
                    ["db", _db], ["reason", [_reason, "auto"] select ((trim _reason) isEqualTo "")],
                    ["falsePapers", _fp], ["trueName", [_true, "auto"] select ((trim _true) isEqualTo "")],
                    ["bioId", [_bioId, "auto"] select ((trim _bioId) isEqualTo "")],
                    ["relativeOf", [_relOf, "auto"] select ((trim _relOf) isEqualTo "")], ["relation", _rel],
                    ["missingFingers", [_missing, "auto"] select ((trim _missing) isEqualTo "")], ["irisState", _iris]
                ];
                [_obj, _pairs] remoteExecCall ["comspec_sse_fnc_bioSetProfile", 2];
                [format ["Biométrie réglée pour %1.", name _obj], "info"] call comspec_sse_fnc_zeusNotify;
            },
            {},
            [_obj]
        ] call zen_dialog_fnc_create;
    },
    "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa"
] call comspec_sse_fnc_zenModule;

// --- Doter en terminal SEEK ----------------------------------------------
[
    "COMSPEC SSE · Mission",
    "Doter en terminal SEEK",
    {
        params ["_pos", "_obj"];

        if (!isNull _obj && { isPlayer _obj }) exitWith {
            [_obj] remoteExecCall ["comspec_sse_fnc_giveSeek", _obj];
            [format ["Terminal SEEK remis à %1.", name _obj], "info"] call comspec_sse_fnc_zeusNotify;
        };

        [
            "Doter en terminal SEEK",
            [
                ["SLIDER", ["Rayon (m)", "Joueurs à doter autour du point désigné."], [5, 200, 30, 0]]
            ],
            {
                params ["_values", "_args"];
                _values params ["_radius"];
                _args params ["_pos"];

                private _players = (allPlayers select { _x distance _pos <= _radius });
                if (_players isEqualTo []) exitWith {
                    ["Aucun joueur dans le rayon désigné.", "warn"] call comspec_sse_fnc_zeusNotify;
                };
                {
                    [_x] remoteExecCall ["comspec_sse_fnc_giveSeek", _x];
                } forEach _players;
                [format ["Terminal SEEK distribué à %1 joueur(s).", count _players], "info"] call comspec_sse_fnc_zeusNotify;
            },
            {},
            [_pos]
        ] call zen_dialog_fnc_create;
    },
    "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa"
] call comspec_sse_fnc_zenModule;

// --- Présentation des documents / fiches --------------------------------
[
    "COMSPEC SSE · Mission",
    "Présentation des documents",
    {
        private _current = missionNamespace getVariable ["COMSPEC_SSE_DocChrome", createHashMap];
        private _prefab = if (_current isEqualType createHashMap) then {
            _current getOrDefault ["prefab", "standard_restreint"]
        } else {
            "standard_restreint"
        };
        private _footer = if (_current isEqualType createHashMap) then {
            _current getOrDefault ["footer", ""]
        } else {
            ""
        };
        private _banner = if (_current isEqualType createHashMap) then {
            _current getOrDefault ["banner", ""]
        } else {
            ""
        };
        private _prefabCodes = [
            "standard_restreint",
            "terrain_tache",
            "brouillon_froisse",
            "bureau_jauni",
            "formel_propre"
        ];
        private _prefabLabels = [
            "Standard — diffusion restreinte",
            "Terrain — feuille tachée",
            "Brouillon — papier froissé",
            "Archives — papier jauni",
            "Bureau — papier impeccable"
        ];
        private _prefabIdx = _prefabCodes find _prefab;
        if (_prefabIdx < 0) then { _prefabIdx = 0; };
        private _styles = ["", "clean", "stained", "crumpled", "aged"];
        private _styleLabels = [
            "Selon le modèle",
            "Papier propre (bureau)",
            "Papier taché (terrain)",
            "Papier froissé",
            "Papier jauni / usé"
        ];

        [
            "Présentation des documents SSE",
            [
                ["LIST", ["Modèle préfait", "Textes et aspect de base. Les champs libres remplacent le modèle."], [_prefabCodes, _prefabLabels, _prefabIdx]],
                ["LIST", ["Aspect du papier", "Optionnel : force un aspect différent du modèle."], [_styles, _styleLabels, 0]],
                ["EDIT", ["Bandeau", "Vide = texte du modèle."], _banner],
                ["EDIT", ["Pied de page", "Mention du type « Ne constitue pas une preuve… ». Vide = texte du modèle."], _footer]
            ],
            {
                params ["_values"];
                _values params ["_prefab", "_style", "_banner", "_footer"];
                if (isNil "comspec_sse_fnc_getDocumentChrome") exitWith {
                    ["Pack SSE requis pour la présentation des documents.", "warn"] call comspec_sse_fnc_zeusNotify;
                };
                private _chrome = ["apply_prefab", _prefab] call comspec_sse_fnc_getDocumentChrome;
                private _ov = createHashMap;
                if ((trim _style) isNotEqualTo "") then { _ov set ["paper_style", trim _style]; };
                if ((trim _banner) isNotEqualTo "") then { _ov set ["banner", trim _banner]; };
                if ((trim _footer) isNotEqualTo "") then { _ov set ["footer", trim _footer]; };
                if ((count _ov) > 0) then {
                    ["set", _ov] call comspec_sse_fnc_getDocumentChrome;
                };
                [format ["Présentation SSE appliquée (%1).", _prefab], "info"] call comspec_sse_fnc_zeusNotify;
            },
            {},
            []
        ] call zen_dialog_fnc_create;
    },
    "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa"
] call comspec_sse_fnc_zenModule;

missionNamespace setVariable ["COMSPEC_ZenSseModulesRegistered", true];
["Modules SSE enregistrés dans Zeus Enhanced", "INFO"] call comspec_sse_fnc_log;
