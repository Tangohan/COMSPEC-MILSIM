/*
    Tableau de bord SSE : données du site Athena lues depuis Eden, par les commandes que la DLL COMSPEC Link
    connaît déjà (SseCampaignList, SseCampaignPull, GetWantedNotices) : aucune nouvelle version de la DLL requise.

    Dans Eden, le rappel asynchrone de la DLL n'est pas branché (le XEH_postInit du réseau ne tourne qu'en mission) :
    les appels sont directs et l'éditeur attend la réponse (quelques secondes au plus). Sans liaison ouverte depuis le
    démarrage du jeu, la session Athena mémorisée par la DLL est restaurée, sinon la clé de communauté du profil est
    utilisée, comme au lancement d'une mission.

    [_mode, _args, _display] spawn comspec_sse_fnc_dashSite
      "refresh" [clé]  campagnes de la communauté, une campagne (clé donnée, sinon celle du module Campagne d'Eden,
                       sinon la première), avis de recherche
      "pull"    [clé]  une seule campagne
    Résultat dans uiNamespace « comspec_sse_dashSite » (HashMap), puis le tableau de bord est redessiné :
      state (busy, ok, offline, error) · detail · campaigns [[clé, nom, type, missions, personnes, organisations, mise à jour]]
      key · campaign (campagne dépaquetée) · campaignError · wanted [[nature, id, réf., nom, alias, niveau, détails]] · wantedError · at
*/
params [["_mode", "refresh", [""]], ["_args", [], [[]]], ["_d", displayNull, [displayNull]]];
if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_dashSite; };
private _site = uiNamespace getVariable ["comspec_sse_dashSite", createHashMap];
uiNamespace setVariable ["comspec_sse_dashSite", _site];
if ((_site getOrDefault ["state", ""]) isEqualTo "busy" && {diag_tickTime - (_site getOrDefault ["busyAt", 0]) < 90}) exitWith {};
_site set ["state", "busy"];
_site set ["busyAt", diag_tickTime];
_site set ["detail", ["Lecture des campagnes sur le site…", "Récupération de la campagne…"] select (_mode isEqualTo "pull")];
if (!isNull _d) then { [_d] call comspec_sse_fnc_dashRender; };
uiSleep 0.05;

private _call = {
    params ["_cmd", ["_a", []]];
    private _r = "COMSPECExtension" callExtension [_cmd, _a];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    if !(_r isEqualType "") then { _r = str _r; };
    _r
};
// Lignes « a\tb\tc » : les colonnes vides sont gardées (splitString les supprimerait et décalerait les champs).
private _rows = {
    params ["_r"];
    (((_r select [3]) splitString endl) select { _x isNotEqualTo "" }) apply { [_x, toString [9]] call CBA_fnc_split }
};
private _clean = {
    params ["_s"];
    if !(_s isEqualType "") then { _s = str _s; };
    _s = trim _s;
    while { (count _s) >= 2 && {(_s select [0, 1]) in ["'", """"]} && {(_s select [(count _s) - 1]) in ["'", """"]} } do { _s = trim (_s select [1, (count _s) - 2]); };
    _s
};
private _ensureLink = {
    private _url = if (isNil "comspec_overwatch_connect_fnc_portalUrl") then { "https://athena-comspec.eu/public" } else { [] call comspec_overwatch_connect_fnc_portalUrl };
    private _steam = [profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""]] call _clean;
    if ((["RestoreSession", [_url, "", _steam]] call _call) find "OK|READY" == 0) exitWith { true };
    private _key = [profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""]] call _clean;
    if ((count _key) < 16) exitWith { false };
    private _tenant = [profileNamespace getVariable ["comspec_overwatch_saved_tenant_id", ""]] call _clean;
    ((["Connect", [_url, _key, _tenant, _steam]] call _call) find "OK") == 0
};
private _why = {
    params ["_r"];
    private _code = _r select [4];
    switch (true) do {
        case (_r isEqualTo ""): { "la DLL COMSPEC Link ne répond pas (mod COMSPEC Link absent, ou bloqué par BattlEye)." };
        case (_code isEqualTo "not_connected"): { "pas de liaison avec Athena : bouton « Connexion Athena » (ou menu COMSPEC SSE), puis actualisez." };
        case (_code in ["unauthorized", "no_tenant"]): { "compte non relié à une communauté dans Athena : bouton « Connexion Athena » (ou menu COMSPEC SSE), puis actualisez." };
        case (_code isEqualTo "not_found"): { "campagne absente d'Athena (archivée ou jamais envoyée)." };
        case (_code isEqualTo "timeout"): { "le site n'a pas répondu à temps." };
        default { format ["réponse du site : %1", _code] };
    }
};
private _now = { format ["%1:%2", [systemTime select 3, 2] call CBA_fnc_formatNumber, [systemTime select 4, 2] call CBA_fnc_formatNumber] };

private _pull = {
    params ["_key"];
    _site set ["key", _key];
    _site set ["campaign", createHashMap];
    _site set ["campaignError", ""];
    if (_key isEqualTo "") exitWith {};
    private _text = "";
    private _offset = 0;
    private _total = -1;
    private _err = "";
    private _guard = 0;
    while { _err isEqualTo "" && {_total < 0 || {_offset < _total}} && {_guard < 2000} } do {
        _guard = _guard + 1;
        private _r = ["SseCampaignPull", [_key, _offset]] call _call;
        if ((_r find "OK|") != 0) then { _err = [_r] call _why; } else {
            // OK|total|suivant|morceau : le morceau peut contenir des « | ».
            private _rest = _r select [3];
            private _p1 = _rest find "|";
            _total = parseNumber (_rest select [0, _p1]);
            _rest = _rest select [_p1 + 1];
            private _p2 = _rest find "|";
            private _next = parseNumber (_rest select [0, _p2]);
            _text = _text + (_rest select [_p2 + 1]);
            if (_next <= _offset) then { _offset = _total; } else { _offset = _next; };
        };
    };
    if (_err isNotEqualTo "") exitWith { _site set ["campaignError", _err]; };
    private _arr = if (_text isEqualTo "") then { [] } else { parseSimpleArray _text };
    if (!(_arr isEqualType []) || {(_arr param [0, ""]) isNotEqualTo "COMSPEC_SSE_CAMPAIGN"}) exitWith { _site set ["campaignError", "texte de campagne illisible."]; };
    private _c = [_arr select 2] call comspec_sse_fnc_bioUnpack;
    if !(_c isEqualType createHashMap) exitWith { _site set ["campaignError", "campagne illisible."]; };
    _site set ["campaign", _c];
};

private _r = ["SseCampaignList"] call _call;
if ((_r find "ERR|") == 0 && {(_r select [4]) in ["not_connected", "unauthorized", "no_tenant", "invalid_url"]}) then {
    if (call _ensureLink) then { _r = ["SseCampaignList"] call _call; };
};
if ((_r find "OK|") != 0) then {
    _site set ["state", ["error", "offline"] select (_r isEqualTo "" || {(_r select [4]) isEqualTo "not_connected"})];
    _site set ["detail", [_r] call _why];
} else {
    private _list = ([_r] call _rows) select { (count _x) >= 3 };
    _site set ["campaigns", _list];
    _site set ["state", "ok"];
    _site set ["detail", ""];
    private _key = toUpper (trim (_args param [0, ""]));
    if (_mode isEqualTo "refresh") then {
        if (_key isEqualTo "") then {
            // Même identifiant que le jeu : majuscules, mots reliés par des tirets.
            private _eden = ((_d getVariable ["comspec_sse_dashModel", createHashMap]) getOrDefault ["campaignId", ""]);
            _eden = toUpper (((trim _eden) splitString " '/\.,:;") joinString "-");
            if ((_list findIf { (_x select 0) isEqualTo _eden }) >= 0) then { _key = _eden; };
        };
        if (_key isEqualTo "" && {_list isNotEqualTo []}) then { _key = (_list select 0) select 0; };
        [_key] call _pull;
        private _w = ["GetWantedNotices"] call _call;
        if ((_w find "OK|") == 0) then {
            _site set ["wanted", [_w] call _rows];
            _site set ["wantedError", ""];
        } else {
            _site set ["wanted", []];
            _site set ["wantedError", [_w] call _why];
        };
    } else {
        [_key] call _pull;
    };
};
_site set ["at", call _now];
if (!isNull _d) then { [_d] call comspec_sse_fnc_dashRender; };
