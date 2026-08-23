/*
    Enregistre les modules SSE dans Zeus Enhanced (ZEN) si présent.
    Les modules CfgVehicles restent pour Eden (scopeCurator = 0).
*/
if (!hasInterface) exitWith {};
if (isNil "zen_custom_modules_fnc_register") exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZenSseModulesRegistered", false]) exitWith {};

private _icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";

// --- Dossier SSE actif ---------------------------------------------------
[
    "COMSPEC SSE",
    "Dossier SSE actif",
    {
        private _current = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
        [
            "Dossier SSE actif",
            [
                ["EDIT", ["Référence", "Dossier ouvert côté portail, par exemple SSE-2026-0007. Laisser vide efface le dossier actif."], _current]
            ],
            {
                params ["_values"];
                _values params ["_ref"];
                private _clean = toUpper (trim _ref);
                if (_clean isEqualTo "") then {
                    ["clear"] remoteExecCall ["comspec_overwatch_connect_fnc_sseActiveCase", 0];
                } else {
                    // Diffusé à tous : le dossier est un contexte d'élément.
                    ["set", _clean, true] remoteExecCall ["comspec_overwatch_connect_fnc_sseActiveCase", 0];
                };
            },
            {},
            []
        ] call zen_dialog_fnc_create;
    },
    _icon
] call zen_custom_modules_fnc_register;

// --- Profil d'identité ---------------------------------------------------
[
    "COMSPEC SSE",
    "Profil d'identité SSE",
    {
        params ["_pos", "_obj"];

        if (isNull _obj || { !(_obj isKindOf "CAManBase") }) exitWith {
            ["Posez ce module sur une personne : c'est son profil que vous réglez.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        };

        private _presets = ["list"] call comspec_overwatch_connect_fnc_sseProfilePreset;
        private _labels = ["labels"] call comspec_overwatch_connect_fnc_sseProfilePreset;

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

                private _profile = [_preset] call comspec_overwatch_connect_fnc_sseProfilePreset;
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
                [_obj, _profile] remoteExecCall ["comspec_overwatch_connect_fnc_sseApplyProfile", 2];
                [format ["Profil SSE appliqué à %1.", name _obj], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
            },
            {},
            [_obj]
        ] call zen_dialog_fnc_create;
    },
    _icon
] call zen_custom_modules_fnc_register;

// --- Doter en terminal SEEK ----------------------------------------------
[
    "COMSPEC SSE",
    "Doter en terminal SEEK",
    {
        params ["_pos", "_obj"];

        if (!isNull _obj && { isPlayer _obj }) exitWith {
            [_obj, "COMSPEC_Item_SeekTerminal"] remoteExecCall ["comspec_overwatch_connect_fnc_giveSeekTerminal", _obj];
            [format ["Terminal SEEK remis à %1.", name _obj], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
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
                    ["Aucun joueur dans le rayon désigné.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
                };
                {
                    [_x, "COMSPEC_Item_SeekTerminal"] remoteExecCall ["comspec_overwatch_connect_fnc_giveSeekTerminal", _x];
                } forEach _players;
                [
                    format ["Terminal SEEK distribué à %1 joueur(s).", count _players],
                    "tactical",
                    "info"
                ] call comspec_overwatch_connect_fnc_announce;
            },
            {},
            [_pos]
        ] call zen_dialog_fnc_create;
    },
    "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa"
] call zen_custom_modules_fnc_register;

missionNamespace setVariable ["COMSPEC_ZenSseModulesRegistered", true];
["INFO", "SSE", "Modules SSE enregistrés dans Zeus Enhanced"] call comspec_overwatch_connect_fnc_log;
