/*
    Pose des données à extraire au câble DL-7 sur n'importe quel objet (ordinateur, téléphone, clé USB, caisse,
    drone, corps…). Utilisé par les modules Zeus et Eden ; utilisable dans un script ou le champ init d'un objet :
      if (isServer) then { [this, [["name", "PC du QG"], ["password", "4471"], ["files", "ordre.txt | texte" + endl + "Repli à 04h00"]]] call comspec_atak_native_fnc_dataSetup; };
    Champ init sans « isServer » : ignoré sur les clients tant que leur postInit n'est pas passé (le champ init est rejoué
    sur chaque client JIP et remettrait à zéro verrouillage, effacement et données retirées par Zeus pour tout le monde).
    Params : [objet, réglages (HashMap ou paires [clé, valeur]), fusion]
      fusion = true : garde les réglages déjà posés et ajoute les fichiers (module Zeus « Ajouter un fichier »).
    Clés (absentes = valeur par défaut) :
      name       nom affiché                       kind      ordinateur | portable | serveur | telephone | tablette | usb | radio | drone | caisse | autre
      theme      windows | linux | militaire | scada | telephone | coffre        banner  organisation affichée à la connexion
      accent     couleur #rrggbb de l'écran (vide = celle du thème)              wallpaper  image de la mission en tête de l'écran de connexion
      password   mot de passe (vide = libre). Seule son empreinte est diffusée ; « - » en fusion retire le mot de passe.
      hint       indice sous le champ               attempts   essais avant sanction (0 = illimité)
      onFail     lock | wipe | alarm | none          crack      forçage possible        crackTime  secondes de forçage
      duration   secondes pour tout télécharger      port       usb | rj45 | serie
      alarm      none | connect | fail | download (sirène, Zeus prévenu, position du pirate révélée aux ennemis proches)
      oneShot    données effacées après le premier téléchargement complet
      files      [[nom, type, contenu, taille Ko]...] ou texte (fn_dataParseFiles)
      code       code SQF exécuté sur le serveur en fin de téléchargement, params ["_object", "_player", "_files"]
    Variable publique de l'objet : COMSPEC_ATAK_Data (paires, sans « code », tenu par le serveur). État tenu par le serveur : COMSPEC_ATAK_DataTries,
    COMSPEC_ATAK_DataLocked, COMSPEC_ATAK_DataWiped, COMSPEC_ATAK_DataTaken.
*/
params [["_obj", objNull, [objNull]], ["_cfg", [], [[], createHashMap]], ["_merge", false]];
if (isNull _obj) exitWith { false };
// Champ init rejoué sur un client (JIP compris) avant son postInit, ou sur un client headless (qui ne pose jamais
// COMSPEC_ATAK_DataInitDone) : le serveur a déjà posé les données.
if (!isServer && {!hasInterface || {isNil "COMSPEC_ATAK_DataInitDone"}}) exitWith { false };
if (_cfg isEqualType []) then { _cfg = createHashMapFromArray (_cfg select { _x isEqualType [] && {(count _x) isEqualTo 2} }); };
private _old = createHashMapFromArray (_obj getVariable ["COMSPEC_ATAK_Data", []]);
private _def = createHashMapFromArray [
    ["name", ""], ["kind", "ordinateur"], ["theme", "windows"], ["banner", ""], ["accent", ""], ["wallpaper", ""],
    ["pwHash", ""], ["hint", ""], ["attempts", 3], ["onFail", "lock"], ["crack", true], ["crackTime", 90],
    ["duration", 45], ["port", "usb"], ["alarm", "none"], ["oneShot", false], ["files", []], ["code", ""]
];
private _out = +_def;
if (_merge) then { { _out set [_x, _y]; } forEach _old; };
private _files = if (_merge) then { +(_out getOrDefault ["files", []]) } else { [] };
{
    switch (_x) do {
        case "password": {
            private _pw = if (_y isEqualType "") then { _y } else { str _y };
            if (_merge && {_pw isEqualTo ""}) exitWith {};
            _out set ["pwHash", ["", hashValue _pw] select (_pw isNotEqualTo "" && {_pw isNotEqualTo "-"})];
        };
        case "files": { _files append ([_y] call comspec_atak_native_fnc_dataParseFiles); };
        default { if (_x in _def) then { _out set [_x, _y]; }; };
    };
} forEach _cfg;
_out set ["files", _files];
// Valeurs bornées : des champs Eden ou Zeus peuvent arriver en texte.
{
    _x params ["_k", "_min", "_max"];
    private _v = _out get _k;
    if (_v isEqualType "") then { _v = parseNumber _v; };
    _out set [_k, (_v max _min) min _max];
} forEach [["attempts", 0, 20], ["crackTime", 5, 1800], ["duration", 3, 3600]];
// Cases à cocher reçues en nombre ou en texte (script, champ init) : « if !(…) » attend un booléen (forçage, usage unique).
{
    private _v = _out get _x;
    if (_v isEqualType 0) then { _out set [_x, _v > 0]; };
    if (_v isEqualType "") then { _out set [_x, (toLower trim _v) in ["true", "1", "oui", "yes"]]; };
} forEach ["crack", "oneShot"];
if !((_out get "theme") in ["windows", "linux", "militaire", "scada", "telephone", "coffre"]) then { _out set ["theme", "windows"]; };
if !((_out get "port") in ["usb", "rj45", "serie"]) then { _out set ["port", "usb"]; };
if !((_out get "onFail") in ["lock", "wipe", "alarm", "none"]) then { _out set ["onFail", "lock"]; };
if !((_out get "alarm") in ["none", "connect", "fail", "download"]) then { _out set ["alarm", "none"]; };
if ((_out get "name") isEqualTo "") then { _out set ["name", getText (configOf _obj >> "displayName")]; };
if ((_out get "name") isEqualTo "") then { _out set ["name", "Support numérique"]; };
// Code de fin jamais publié (variable d'objet diffusée et modifiable par tout client) : gardé par le serveur seul
// (fn_dataServer « code »). Serveur : directement ; client : seulement un Zeus, contrôlé de nouveau au serveur.
private _code = _out getOrDefault ["code", ""];
_out deleteAt "code";
if !(_code isEqualType "") then { _code = ""; };
if (isServer) then {
    if (!_merge || {"code" in _cfg}) then { ["code", _obj, objNull, _code] call comspec_atak_native_fnc_dataServer; };
} else {
    if ("code" in _cfg && {!isNull (getAssignedCuratorLogic player)}) then { ["code", _obj, player, _code] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2]; };
};
_obj setVariable ["COMSPEC_ATAK_Data", (keys _out) apply { [_x, _out get _x] }, true];
if (!_merge) then {
    _obj setVariable ["COMSPEC_ATAK_DataTries", 0, true];
    _obj setVariable ["COMSPEC_ATAK_DataLocked", false, true];
    _obj setVariable ["COMSPEC_ATAK_DataWiped", false, true];
};
// Liste des supports (app Extraction, actions ACE) tenue par le serveur.
["reg", _obj, objNull, []] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2];
true
