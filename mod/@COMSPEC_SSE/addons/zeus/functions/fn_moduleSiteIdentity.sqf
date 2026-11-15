/*
    Module « Identité du site (Athena) » : pose sur un PNJ une personne choisie dans les listes du site de la communauté
    (comspec_sse_fnc_catalogue) au lieu d'un nom tapé à la main.
      - personne du lore de la session (identifiant d'IA, référence du registre, nom s'il est connu des fichiers) ;
      - avis de recherche actif à lui associer ;
      - contexte d'acquisition et dossier de classement des fiches transmises pour ce sujet.
    Zeus : formulaire à la pose (listes du site, repli local, filtre). Eden : attributs du module, appliqués par le
    serveur au lancement de la mission (les valeurs sont des identifiants du site, résolus à ce moment-là).
    L'application se fait sur le serveur : comspec_sse_fnc_siteIdentityApply.
*/
private _args = _this call comspec_sse_fnc_moduleArgs;
if (_args isEqualTo []) exitWith { true };
_args params ["_logic", "_units", "_activated"];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _targets = [_logic, _units, 15] call comspec_sse_fnc_moduleTargets;
private _text = {
    params ["_name"];
    private _v = _logic getVariable [_name, ""];
    if (_v isEqualType "") then { trim _v } else { "" }
};

if (_ctx get "zeus") then {
    if (_targets isEqualTo []) exitWith {
        ["Identité du site : posez le module sur une personne.", "warn"] call comspec_sse_fnc_zeusNotify;
    };
    private _first = _targets select 0;
    // Contextes d'acquisition du mod : repli quand le site ne répond pas (mêmes codes que le SEEK II).
    private _ctxCodes = ["exploitation_site_sensible", "raid_action_directe", "cordon_fouille", "point_controle_tactique", "fouille_patrouille", "interrogatoire_tactique", "capture_champ_bataille", "autre"];
    private _ctxLabels = ["Exploitation de site sensible (SSE)", "Action directe : assaut sur l’objectif", "Cordon et fouille", "Point de contrôle tactique (TCP)", "Fouille opportuniste en patrouille", "Interrogatoire tactique (TQ)", "Reddition ou capture au combat (EPW)", "Autre (précisez)"];
    [
        "Identité du site",
        [
            ["CATALOG", "Personne du lore", "Personnes de la session de lore de cette opération, sur le site de la communauté.", ["lore", [], [], _first getVariable ["comspec_sse_aiUid", ""], "Ne pas changer l'identité"]],
            ["CATALOG", "Avis de recherche", "Avis de recherche actif à associer à ce sujet.", ["wanted", [], [], _first getVariable ["comspec_sse_wantedRef", ""], "Aucun avis"]],
            ["CATALOG", "Contexte d'acquisition", "Contexte porté par les fiches transmises pour ce sujet.", ["contexts", _ctxCodes, _ctxLabels, _first getVariable ["comspec_sse_acqContext", ""], "Laisser le terminal décider"]],
            ["CATALOG", "Dossier", "Dossier où classer les fiches de ce sujet. Vide : dossier actif de la mission.", ["cases", [], [], _first getVariable ["comspec_sse_caseRef", ""], "Dossier actif de la mission"]]
        ],
        {
            params ["_values", "_args"];
            _values params ["_lore", "_wanted", "_context", "_case"];
            // Libellés du site lus dans le catalogue : le serveur n'a pas forcément de liaison pour les relire.
            private _label = {
                params ["_section", "_code"];
                if (_code isEqualTo "") exitWith { "" };
                (["choices", [_section, [], []]] call comspec_sse_fnc_catalogue) params ["_codes", "_labels"];
                private _i = _codes find _code;
                if (_i < 0) then { "" } else { _labels param [_i, ""] }
            };
            [_args select 0, _lore, ["lore", _lore] call _label, _wanted, ["wanted", _wanted] call _label, _context, _case] remoteExecCall ["comspec_sse_fnc_siteIdentityApply", 2];
            [format ["Identité du site appliquée à %1 sujet(s).", count (_args select 0)]] call comspec_sse_fnc_zeusNotify;
        },
        [_targets],
        "Les listes viennent du site de la communauté. Si le site ne répond pas, seules les listes du mod sont proposées."
    ] call comspec_sse_fnc_uiForm;
} else {
    // Eden : identifiants enregistrés dans la mission, appliqués par le serveur au lancement.
    if (isServer && {_targets isNotEqualTo []}) then {
        [_targets, ["AiUid"] call _text, ["AiLabel"] call _text, ["WantedRef"] call _text, ["WantedLabel"] call _text, ["Context"] call _text, ["CaseRef"] call _text] call comspec_sse_fnc_siteIdentityApply;
    };
};

deleteVehicle _logic;
true
