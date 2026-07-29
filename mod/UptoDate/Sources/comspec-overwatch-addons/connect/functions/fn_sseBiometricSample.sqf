/*
    Prélèvement biométrique simulé (roleplay) — empreintes, iris ou ADN.
    Rien n’est reconnu ni comparé en jeu : on produit un échantillon fictif
    (indice de qualité + référence de laboratoire) joint à la fiche à l’envoi.

    Params: [_kind] — "empreintes" | "iris" | "adn"
*/
params [["_kind", "empreintes", [""]]];

if (!hasInterface) exitWith {};

_kind = toLower _kind;
if (!(_kind in ["empreintes", "iris", "adn"])) then { _kind = "empreintes"; };

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };

private _label = switch (_kind) do {
    case "iris": { "Relevé iris" };
    case "adn": { "Prélèvement ADN" };
    default { "Relevé d’empreintes" };
};
private _duration = switch (_kind) do {
    case "iris": { 10 };
    case "adn": { 20 };
    default { 8 };
};

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];

// Un seul échantillon par type.
private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
private _already = false;
{
    if ((_x isEqualType []) && {(count _x) > 0} && {(_x select 0) isEqualTo _kind}) exitWith { _already = true; };
} forEach _samples;
if (_already) exitWith {
    [format ["%1 déjà enregistré pour cette personne.", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
};

// Publié dans uiNamespace : la barre de progression ACE exécute son code de fin dans
// une portée séparée, où une variable locale ne serait plus visible.
uiNamespace setVariable ["COMSPEC_SseSampleFinish", {
    params ["_kind", "_label"];

    // Qualité simulée : l’ADN est le plus fiable, l’iris souffre du terrain.
    private _quality = switch (_kind) do {
        case "adn": { 88 + floor (random 11) };
        case "iris": { 61 + floor (random 30) };
        default { 70 + floor (random 26) };
    };

    private _prefix = switch (_kind) do {
        case "adn": { "ADN" };
        case "iris": { "IRI" };
        default { "EMP" };
    };
    // 1000..9999 : toujours quatre chiffres, pas de remplissage à gérer.
    private _labRef = format [
        "LAB-%1-%2%3",
        (date select 0),
        _prefix,
        1000 + floor (random 9000)
    ];

    private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
    if (!(_samples isEqualType [])) then { _samples = []; };
    _samples pushBack [_kind, _quality, _labRef];
    uiNamespace setVariable ["COMSPEC_SsePerson_Samples", _samples];

    // Compatibilité 1.4.0 : le drapeau global reste alimenté.
    uiNamespace setVariable ["COMSPEC_SsePerson_BioPending", true];

    if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };

    [
        format ["%1 : échantillon exploitable (qualité %2%3) — réf. %4", _label, _quality, "%", _labRef],
        "tactical",
        "info"
    ] call comspec_overwatch_connect_fnc_announce;
}];

// Barre de progression ACE si disponible : le relevé prend du temps et s’interrompt.
if (!isNil "ace_common_fnc_progressBar") exitWith {
    [
        _duration,
        [_kind, _label],
        {
            (_this select 0) params ["_kind", "_label"];
            [_kind, _label] call (uiNamespace getVariable ["COMSPEC_SseSampleFinish", {}]);
        },
        {
            ["Relevé interrompu.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        },
        format ["%1…", _label],
        {
            private _t = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
            isNull _t || {(player distance _t) < 5}
        },
        ["isNotInside"]
    ] call ace_common_fnc_progressBar;
};

// Sans ACE : temporisation simple, même durée.
[format ["%1 en cours…", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
[{ _this call (uiNamespace getVariable ["COMSPEC_SseSampleFinish", {}]) }, [_kind, _label], _duration] call CBA_fnc_waitAndExecute;
