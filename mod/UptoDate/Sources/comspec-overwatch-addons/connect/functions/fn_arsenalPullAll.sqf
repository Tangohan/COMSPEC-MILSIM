/*
    Importe des tenues du site dans « Mes équipements » d'ACE (profil). À appeler depuis un spawn.
    Params : [applyId (facultatif), onlyIds (vide = toutes), keepLocal]
      keepLocal (Tout basculer, import groupé) : une tenue de l'arsenal qui porte déjà ce nom n'est jamais touchée
                (ni remplacée, ni doublée) ; seules arrivent les tenues que le joueur n'a pas.
      sinon (bouton « Mettre à jour mon arsenal » / « Prendre la version du site ») : la tenue du même nom est
                remplacée, à la demande du joueur.
    Idempotent : relancer ne change rien. Avant, le verrou n'était posé qu'après le chargement de la liste (réseau) :
    plusieurs clics lançaient plusieurs imports qui ajoutaient chacun les mêmes tenues, sans jamais les fusionner
    ensuite ; et une version différente arrivait à côté sous « nom · Athena », qui partait ensuite au site puis
    revenait en « nom · Athena · Athena »… L'écriture passe par arsenalAceStore (format ACE, un nom = une tenue).
    Retour : true si au moins une tenue a été écrite.
*/
params [["_applyId", "", [""]], ["_onlyIds", [], [[]]], ["_keepLocal", false, [false]]];

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Liaison Athena requise pour récupérer les tenues.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

// Verrou pris d'un bloc, avant tout appel réseau (un verrou de plus de 3 min est un import interrompu : ignoré).
private _busy = false;
isNil {
    private _t = missionNamespace getVariable ["COMSPEC_ArsenalPullBusy", -1];
    _busy = (_t isEqualType 0) && {_t >= 0} && {(diag_tickTime - _t) < 180};
    if (!_busy) then { missionNamespace setVariable ["COMSPEC_ArsenalPullBusy", diag_tickTime, false]; };
};
if (_busy) exitWith {
    ["Import des tenues déjà en cours, patientez.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};
private _release = { missionNamespace setVariable ["COMSPEC_ArsenalPullBusy", -1, false]; };

// Tout importer : liste relue sur le site ; tenues choisies dans le panneau : la liste gardée sur ce PC suffit.
private _lines = [["cache", "refresh"] select (_onlyIds isEqualTo [])] call comspec_overwatch_connect_fnc_arsenalListWardrobes;
if (_lines isEqualTo []) exitWith {
    call _release;
    private _probe = [["COMSPECExtension", "ListWardrobes", ["0", "cache"]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    if (!(_probe isEqualType "") || {_probe find "OK|" != 0}) then {
        ["Athena est injoignable : les tenues n'ont pas pu être listées.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    } else {
        [["Cette tenue n'est plus disponible.", "Aucune tenue dans la communauté."] select (_onlyIds isEqualTo []), "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    };
    false
};

// Lignes « id \t nom \t slug \t collection \t … \t mine (8) \t sha256 (9) … » : champs vides gardés, rognés.
private _want = _onlyIds apply { trim _x };
private _mine = [];
private _others = [];
{
    if (_x isEqualTo "") then { continue };
    private _s = _x; private _f = []; private _tab = toString [9]; private _i = _s find _tab;
    while { _i >= 0 } do { _f pushBack trim (_s select [0, _i]); _s = _s select [_i + 1]; _i = _s find _tab; };
    _f pushBack trim _s;
    if ((count _f) < 2 || {(_f select 0) isEqualTo ""} || {(_f select 1) isEqualTo ""}) then { continue };
    if (_want isNotEqualTo [] && {!((_f select 0) in _want)}) then { continue };
    private _row = [_f select 0, _f select 1, toLower (_f param [9, ""])];
    // Plusieurs tenues du même nom sur le site : la vôtre d'abord.
    if ((_f param [8, ""]) isEqualTo "1") then { _mine pushBack _row; } else { _others pushBack _row; };
} forEach _lines;
private _meta = _mine + _others;

if (_meta isEqualTo []) exitWith {
    call _release;
    [["Cette tenue n'est plus disponible.", "Aucune tenue dans la communauté."] select (_onlyIds isEqualTo []), "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _localKeys = createHashMap;
{ _localKeys set [["key", _x select 0] call comspec_overwatch_connect_fnc_arsenalAceStore, true]; } forEach ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts);

private _items = [];
private _ledger = [];
private _taken = createHashMap;
private _kept = 0;
private _failed = 0;
{
    _x params ["_id", "_name", "_siteSha"];
    private _k = ["key", _name] call comspec_overwatch_connect_fnc_arsenalAceStore;
    if (_k isEqualTo "" || {_k in _taken}) then { continue };
    _taken set [_k, true];
    // Déjà dans l'arsenal sous ce nom : rien à télécharger.
    if (_keepLocal && {_k in _localKeys}) then { _kept = _kept + 1; continue };
    private _ext = [_id, _siteSha] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
    if (_ext isEqualTo []) then { _failed = _failed + 1; continue };
    _items pushBack [_name, _ext];
    _ledger pushBack [_k, _name, _siteSha, [_ext] call comspec_overwatch_connect_fnc_arsenalHash];
} forEach _meta;

// Écriture d'un bloc ; sans remplacer, une tenue enregistrée sous ce nom pendant le téléchargement reste intacte.
(["upsert", _items, !_keepLocal] call comspec_overwatch_connect_fnc_arsenalAceStore) params ["_written", "_already", "_keys"];
_kept = _kept + _already;
// Registre de synchro : la tenue importée est celle du site (empreinte du site, contenu).
{
    _x params ["_k", "_name", "_siteSha", "_content"];
    if (_k in _keys) then { ["set", _name, _siteSha, _content] call comspec_overwatch_connect_fnc_arsenalLedger; };
} forEach _ledger;

missionNamespace setVariable ["COMSPEC_ArsenalLastPullAt", diag_tickTime, false];
call _release;
// Tenues téléchargées gardées dans Sync\wardrobes.json (COMSPEC Link écrit le fichier en fin d'import).
(["COMSPECExtension", "WardrobesCacheInfo", []] call comspec_overwatch_connect_fnc_extDirect);

private _msg = format ["%1 tenue(s) importée(s) dans votre arsenal ACE.", _written];
if (_kept > 0) then { _msg = _msg + format [" %1 déjà présente(s) sous le même nom : gardée(s) telle(s) quelle(s).", _kept]; };
if (_failed > 0) then { _msg = _msg + format [" %1 n'a (n'ont) pas pu être chargée(s).", _failed]; };
[_msg, "arsenal", ["warn", "ok"] select (_written > 0 || {_kept > 0}), true] call comspec_overwatch_connect_fnc_announce;

if (_applyId isNotEqualTo "") then { [_applyId] call comspec_overwatch_connect_fnc_arsenalApplyCloud; };

_written > 0
