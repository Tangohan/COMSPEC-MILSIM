/*
    « Tout basculer dans l'arsenal ACE » (panneau Tenues Athena). À appeler depuis un spawn.
    1. Importe dans « Mes équipements » d'ACE les tenues de l'organisation que vous n'avez pas encore
       (arsenalPullAll, keepLocal) : une tenue qui porte déjà ce nom chez vous n'est jamais remplacée ni doublée.
    2. Envoie au site les tenues de cet ordinateur qui n'y ont jamais été (nom absent du site, pas au registre). Le site garde
       la collection, la note et les photos d'une tenue renvoyée : rien n'y est effacé.
    Rien n'est supprimé, ici comme sur le site. Relancer ne change rien. Retour : true si quelque chose a été importé ou envoyé.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Liaison Athena requise pour basculer les tenues dans l’arsenal ACE.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};
private _running = { private _t = missionNamespace getVariable [_this, -1]; (_t isEqualType 0) && {_t >= 0} && {(diag_tickTime - _t) < 180} };
if (("COMSPEC_ArsenalPullBusy" call _running) || {"COMSPEC_ArsenalPushBusy" call _running}) exitWith {
    ["Import ou envoi des tenues déjà en cours, patientez.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _pulled = ["", [], true] call comspec_overwatch_connect_fnc_arsenalPullAll;

// Noms déjà sur le site (toutes les tenues de la communauté), relus après l'import.
private _siteKeys = createHashMap;
{
    private _f = _x splitString toString [9];
    if ((count _f) >= 2) then { _siteKeys set [["key", _f select 1] call comspec_overwatch_connect_fnc_arsenalAceStore, true]; };
} forEach ([] call comspec_overwatch_connect_fnc_arsenalListWardrobes);

private _toSend = ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts) apply { _x select 0 } select {
    private _k = ["key", _x] call comspec_overwatch_connect_fnc_arsenalAceStore;
    // Les anciennes copies « · Athena » viennent du site : elles n'y repartent pas. Une tenue déjà passée par le site
    // puis retirée (registre connu) n'est pas renvoyée d'office : c'est peut-être voulu (bouton du panneau pour ça).
    !(_k in _siteKeys) && {(_k find " · athena") < 0} && {(["get", _x] call comspec_overwatch_connect_fnc_arsenalLedger) isEqualTo []}
};
private _pushed = if (_toSend isEqualTo []) then { false } else {
    [_toSend] call comspec_overwatch_connect_fnc_arsenalPushAll
};

[
    format [("Arsenal ACE à jour : tenues de l’organisation récupérées, %1 tenue(s) envoyée(s) au site. Rien n’a été remplacé.") call comspec_overwatch_connect_fnc_tr, [0, count _toSend] select _pushed],
    "arsenal",
    "ok",
    true
] call comspec_overwatch_connect_fnc_announce;

_pulled || _pushed
