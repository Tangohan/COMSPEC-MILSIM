/*
    Réglages CBA des extensions de documents (SSE ROLEPLAY › Extensions). Globaux : le serveur décide, et diffuse la
    liste des extensions coupées (fn_docExtServer) ; les clients la lisent dans comspec_sse_fnc_idDocTypes.
      comspec_sse_docExt_enabled  CHECKBOX  extensions servies (décoché : seuls les papiers intégrés du SSE)
      comspec_sse_docExt_off      EDITBOX   classes d'extensions coupées au lancement, séparées par des virgules
      comspec_sse_docExt_notify   CHECKBOX  notification ATAK du Zeus listant les extensions chargées
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["SSE ROLEPLAY", "Extensions"];
// Réglage changé en cours de partie (menu CBA d'un administrateur) : le serveur recalcule et rediffuse la liste.
private _recompute = { if (isServer && {missionNamespace getVariable ["comspec_sse_docExtReady", false]}) then { ["init"] call comspec_sse_fnc_docExtServer; }; };
private _add = {
    params ["_var", "_type", "_label", "_value", ["_code", {}]];
    [_var, _type, _label, _cat, _value, 1, _code, false] call comspec_sse_fnc_addSetting;
};

["comspec_sse_docExt_enabled", "CHECKBOX", ["Extensions de documents", "Papiers ajoutés par des extensions (addons tiers : carte de presse, laissez-passer…) servis en plus des quatre papiers intégrés du SSE. Décoché : seuls les papiers intégrés sont proposés, quelles que soient les extensions chargées. Exemple : décochez pour une mission « papiers classiques » sans retirer les mods."], true, _recompute] call _add;
["comspec_sse_docExt_off", "EDITBOX", ["Extensions désactivées", "Classes d'extensions (CfgComspecDocumentExtensions) coupées au lancement, séparées par des virgules. Exemple : example_documents. Le Zeus peut ensuite activer ou désactiver chacune dans la fenêtre SSE ROLEPLAY · Extensions (choix gardés sur le serveur)."], "", _recompute] call _add;
["comspec_sse_docExt_notify", "CHECKBOX", ["Annoncer les extensions au lancement", "Au début de la mission, le Zeus reçoit une notification ATAK listant les extensions de documents chargées et leur état. Exemple : « Documents d'exemple v1.0 · 2 modèles · actif »."], true] call _add;
true
