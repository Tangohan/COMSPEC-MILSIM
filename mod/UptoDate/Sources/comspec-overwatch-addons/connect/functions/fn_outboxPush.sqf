/*
    Met une transmission en attente de liaison.

    Le mod savait déjà simuler la coupure réseau ; il perdait ce qui était saisi
    pendant. Une fiche SSE renseignée dans une cave sans couverture partait dans le
    vide, et l'opérateur ne l'apprenait qu'au débriefing. Une radio militaire, on
    rejoue le message — c'est le principe repris ici.

    ## Ce qu'on met en file, et ce qu'on n'y met pas

    Seulement ce qu'un humain a rédigé : fiche SSE, compte rendu, photo, ordre,
    demande. **Jamais les positions ni les interrogations périodiques.** Rejouer
    une position vieille de dix minutes au retour de liaison ne restitue pas
    l'information, elle la fausse : le poste de commandement verrait l'élément là
    où il n'est plus. Une position périmée vaut moins que pas de position.

    ## Persistance

    Le tampon vit dans le profil du joueur, donc il survit à un plantage ou à une
    reconnexion. C'est ce qui distingue un vrai stockage hors ligne d'un simple
    tampon mémoire : la coupure qui fait perdre les données est rarement propre.

    Params: [_cmd, _args, _label, _linkCat]
    Returns: Bool — mis en file
*/
params [
    ["_cmd", "", [""]],
    ["_args", [], [[]]],
    ["_label", "", [""]],
    ["_linkCat", "report", [""]]
];

if (!hasInterface) exitWith { false };
if (_cmd isEqualTo "") exitWith { false };
if (_label isEqualTo "") then { _label = _cmd; };

private _max = missionNamespace getVariable ["COMSPEC_OutboxMax", 50];
if (!(_max isEqualType 0) || { _max < 1 }) then { _max = 50; };

private _queue = profileNamespace getVariable ["COMSPEC_Outbox", []];
if (!(_queue isEqualType [])) then { _queue = []; };
_queue = _queue select {
    !((toLower (_x param [0, ""])) in ["syncwardrobe", "syncwardrobesbatch"])
};

if ((toLower _cmd) in ["syncwardrobe", "syncwardrobesbatch"]) exitWith {
    profileNamespace setVariable ["COMSPEC_Outbox", _queue];
    saveProfileNamespace;
    false
};

// Plafond : une coupure longue ne doit pas construire un tampon sans fin. On
// écarte la plus ancienne plutôt que de refuser la nouvelle — ce que l'opérateur
// vient de saisir compte davantage que ce qu'il a saisi il y a une demi-heure.
if ((count _queue) >= _max) then {
    private _dropped = _queue select 0;
    _queue deleteAt 0;
    [
        "DEBUG",
        "Outbox",
        format ["Tampon plein (%1) — transmission la plus ancienne écartée : %2", _max, _dropped param [2, "?"]]
    ] call comspec_overwatch_connect_fnc_log;
};

private _dup = _queue findIf {
    (_x param [0, ""]) isEqualTo _cmd
    && { (_x param [2, ""]) isEqualTo _label }
};
if (_dup >= 0) exitWith { true };

// Horloge murale et non `time` : le tampon vit dans le profil, il traverse les
// missions. Un horodatage de temps de mission relu à la session suivante donne un
// âge négatif, donc une entrée qui ne périme jamais.
private _now = [] call comspec_overwatch_connect_fnc_wallClockSeconds;
_queue pushBack [_cmd, _args, _label, _linkCat, _now, 0];
profileNamespace setVariable ["COMSPEC_Outbox", _queue];
saveProfileNamespace;

["DEBUG", "Outbox", format ["En attente : %1 (%2 au total)", _label, count _queue]] call comspec_overwatch_connect_fnc_log;

private _lastAnn = missionNamespace getVariable ["COMSPEC_OutboxLastAnnounce", -1e9];
if ((diag_tickTime - _lastAnn) > 8) then {
    missionNamespace setVariable ["COMSPEC_OutboxLastAnnounce", diag_tickTime];
    [
        format ["Liaison indisponible — « %1 » sera transmis au rétablissement (%2 en attente).", _label, count _queue],
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
};

true
