/*
    Dossier SSE actif de l'élément.

    Le renversement de fond du module : le dossier n'est pas une étiquette saisie
    après coup sur chaque fiche, c'est le contexte de travail. L'équipe le pose une
    fois en arrivant sur objectif, et tout ce qui suit s'y rattache sans ressaisie.

    Params: ["get"]                      → String, référence courante
            ["set", _reference, _share]  → pose la référence ; _share diffuse à
                                           tout l'élément (défaut : true)
            ["clear"]                    → efface

    La référence est diffusée en variable de mission publique : un opérateur qui
    rejoint l'élément la reçoit sans manipulation.
*/
params [["_mode", "get", [""]], ["_ref", "", [""]], ["_share", true, [false]]];

_mode = toLower _mode;

if (_mode isEqualTo "get") exitWith {
    private _v = missionNamespace getVariable ["COMSPEC_SSE_ActiveCase", ""];
    if (!(_v isEqualType "")) then { _v = ""; };
    _v
};

if (_mode isEqualTo "clear") exitWith {
    missionNamespace setVariable ["COMSPEC_SSE_ActiveCase", "", true];
    if (hasInterface) then {
        ["Dossier SSE actif effacé — les prochaines fiches partiront non classées.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    ""
};

if (_mode isEqualTo "set") exitWith {
    private _clean = toUpper (trim _ref);
    if (_clean isEqualTo "") exitWith {
        ["Indiquez la référence du dossier fournie par le poste de commandement.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        ""
    };

    // Diffusion à l'élément : le dossier est un contexte d'équipe, pas un réglage
    // individuel. On garde la possibilité de le poser localement pour les cas
    // particuliers (opérateur détaché).
    missionNamespace setVariable ["COMSPEC_SSE_ActiveCase", _clean, _share];
    profileNamespace setVariable ["COMSPEC_SseLastCaseCode", _clean];
    saveProfileNamespace;

    if (hasInterface) then {
        [
            format ["Dossier SSE actif : %1 — les fiches suivantes y seront classées.", _clean],
            "tactical",
            "info"
        ] call comspec_overwatch_connect_fnc_announce;
    };
    _clean
};

""
