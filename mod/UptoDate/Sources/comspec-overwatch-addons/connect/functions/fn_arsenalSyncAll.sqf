/*
    « Tout basculer dans l'arsenal ACE » (panneau Tenues Athena, menu ACE Tenues).
    1. Récupère toutes les tenues de l'organisation dans « Mes équipements » d'ACE (arsenalPullAll, keepLocal) :
       une tenue locale du même nom mais différente n'est jamais remplacée, la version du site arrive à côté sous
       « nom · Athena » ; une tenue identique ne bouge pas.
    2. Envoie au site les tenues de cet ordinateur qui n'y ont jamais été (nom absent du site, pas au registre). Le site garde
       la collection, la note et les photos d'une tenue renvoyée : rien n'y est effacé.
    Rien n'est supprimé, ici comme sur le site. Retour : true si quelque chose a été importé ou envoyé.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Liaison Athena requise pour basculer les tenues dans l’arsenal ACE.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _pulled = ["", [], true] call comspec_overwatch_connect_fnc_arsenalPullAll;

// Noms déjà sur le site (toutes les tenues de la communauté), relus après l'import.
private _siteNames = [];
{
    private _tab = _x find toString [9];
    if (_tab > 0) then {
        private _rest = _x select [_tab + 1];
        private _t2 = _rest find toString [9];
        _siteNames pushBack toLower trim (if (_t2 >= 0) then { _rest select [0, _t2] } else { _rest });
    };
} forEach ([] call comspec_overwatch_connect_fnc_arsenalListWardrobes);

private _toSend = ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts) apply { _x select 0 } select {
    private _k = toLower trim _x;
    // Les copies « · Athena » viennent du site : elles n'y repartent pas. Une tenue déjà passée par le site puis
    // retirée (registre connu) n'est pas renvoyée d'office : c'est peut-être voulu (bouton du panneau pour ça).
    !(_k in _siteNames) && {(_k find (toLower " · Athena")) < 0} && {(["get", _x] call comspec_overwatch_connect_fnc_arsenalLedger) isEqualTo []}
};
private _pushed = if (_toSend isEqualTo []) then { false } else {
    // Le dernier envoi groupé bloque 8 s ; une liste nommée n'est pas concernée.
    [_toSend] call comspec_overwatch_connect_fnc_arsenalPushAll
};

[
    format [("Arsenal ACE à jour : tenues de l’organisation récupérées, %1 tenue(s) envoyée(s) au site. Rien n’a été remplacé.") call comspec_overwatch_connect_fnc_tr, [0, count _toSend] select _pushed],
    "arsenal",
    "ok",
    true
] call comspec_overwatch_connect_fnc_announce;

_pulled || _pushed
