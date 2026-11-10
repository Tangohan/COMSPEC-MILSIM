/*
    Couverture née en jeu vers Athena (poste du joueur). Le compte relié au téléphone (jeton de jeu) dit à qui elle est :
    le site n'écrit que pour ce compte, jamais d'après un Steam ID envoyé ici. Canal SubmitIdCheck de COMSPEC Link
    (kind = cover_report, aucune nouvelle DLL ; contrat : docs/technical/ICD-ATHENA-ATAK.md, ICD-SSE-COVER-001).
    Pas de clé d'idempotence : un site antérieur refuse ce genre (422) au lieu d'y voir un contrôle.
    ["push", fiche]   envoie l'identité que le joueur utilise en jeu (origine athena, generated, game, cover, import) si
                      son contenu a changé et si la transmission automatique est permise (réglage CBA, celui de la
                      communauté l'emporte). Sans liaison ou en erreur : gardée sur le poste, nouvel essai 60 s plus tard au plus.
                      Le site ne l'écrit jamais dans l'identité Athena : il la compare (fiche membre › Différence avec le jeu).
    Résultat (jamais diffusé) : comspec_sse_idCoverSync, HashMap
      state  sending | sent | unlinked | error | nolink   at  date Athena (UTC)   sig  contenu envoyé   tried  dernier essai
    Les contacts et les papiers partent sans vérité de jeu : ni nature, ni vrai nom, ni cohérence. S'y ajoutent l'état de
    la livraison du paquet d'identité (étape, code d'erreur, relais, date) et le nom du serveur de jeu.
*/
params [["_op", ""], ["_sheet", createHashMap]];
if (!hasInterface || {_op isNotEqualTo "push"} || {(count _sheet) isEqualTo 0}) exitWith { false };
// Autorisation : réglage « Transmission automatique à Athena » (celui de la communauté, reçu avec le paquet, l'emporte).
// Décoché : rien ne part, la fiche reste sur le poste. Les appels passent par fn_extensionCall (liaison coupée : rien).
if !(missionNamespace getVariable ["comspec_sse_id_autoTransmitCommunity", missionNamespace getVariable ["comspec_sse_id_autoTransmit", true]]) exitWith { false };
// Identité utilisée en jeu (origine athena | generated | game | cover | import) : remontée elle aussi, pour que la fiche
// membre d'Athena montre la différence avec le jeu et ne l'écrase jamais (reprise champ par champ, sur le site).
private _origin = _sheet getOrDefault ["origin", ["generated", "cover"] select (_sheet getOrDefault ["prepared", false])];
private _st = missionNamespace getVariable ["comspec_sse_idCoverSync", createHashMap];
missionNamespace setVariable ["comspec_sse_idCoverSync", _st];
if ((_st getOrDefault ["state", ""]) isEqualTo "sending" && {(time - (_st getOrDefault ["tried", -100])) < 60}) exitWith { false };
private _ph = _sheet getOrDefault ["phone", createHashMap];
private _cover = createHashMapFromArray [
    ["name", _sheet getOrDefault ["name", ""]], ["dob", _sheet getOrDefault ["dob", ""]], ["pob", _sheet getOrDefault ["pob", ""]],
    ["country", _sheet getOrDefault ["country", ""]], ["address", _sheet getOrDefault ["address", ""]], ["city", _sheet getOrDefault ["city", ""]],
    ["profession", _sheet getOrDefault ["profession", ""]], ["employer", _sheet getOrDefault ["employer", ""]],
    ["phone", _ph getOrDefault ["number", ""]], ["travel", _sheet getOrDefault ["travel", ""]], ["family", _sheet getOrDefault ["family", ""]],
    ["contacts", _sheet getOrDefault ["contacts", []]],
    ["docs", (_sheet getOrDefault ["docs", []]) apply { [_x select 0, _x select 1, _x select 2] }],
    // Champ par champ pour la comparaison du site (rétrocompatible : un site antérieur les ignore).
    ["last", _sheet getOrDefault ["last", ""]], ["first", _sheet getOrDefault ["first", ""]], ["sex", _sheet getOrDefault ["sex", ""]],
    ["origin", _origin], ["identity_ref", _sheet getOrDefault ["athenaRef", ""]],
    ["carried", ((_sheet getOrDefault ["docs", []]) findIf { _x param [4, false] }) >= 0],
    // État de la chaîne du paquet d'identité vu par ce serveur (fiche membre › Transmission en jeu) : étape, code
    // d'erreur, relais (server | zeus | self), date du paquet reçu, nom du serveur de jeu. Rien sur les autres joueurs.
    ["pack", _sheet getOrDefault ["packStep", ""]], ["pack_code", _sheet getOrDefault ["packCode", ""]],
    ["pack_via", _sheet getOrDefault ["packVia", ""]], ["pack_at", _sheet getOrDefault ["packAt", ""]],
    ["server", serverName select [0, 80]]
];
private _payload = createHashMapFromArray [
    ["kind", "cover_report"], ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])],
    ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]], ["cover", _cover]
];
private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
if ((_st getOrDefault ["state", ""]) isEqualTo "sent" && {(_st getOrDefault ["sig", ""]) isEqualTo _json}) exitWith { false };
if ((_st getOrDefault ["sig", ""]) isEqualTo _json && {(time - (_st getOrDefault ["tried", -100])) < 60}) exitWith { false };
_st set ["sig", _json];
_st set ["tried", time];
_st set ["state", "sending"];
[_st, _json] spawn {
    params ["_st", "_json"];
    private _state = "nolink";
    if (["has"] call comspec_sse_fnc_idAthenaPack) then {
        private _parts = [];
        for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
        private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
        if !(_raw isEqualType "") then { _raw = str _raw; };
        private _p = _raw splitString "|";
        _state = "error";
        if ((_p param [0, ""]) isEqualTo "OK") then {
            // « saved;date » ou « unchanged;date » ; « unlinked » : clé de communauté seule, aucun compte relié.
            private _ref = (_p param [2, ""]) splitString ";";
            _state = ["sent", "unlinked"] select ((_ref param [0, ""]) isEqualTo "unlinked");
            _st set ["at", _ref param [1, ""]];
        } else {
            if ((_p param [1, ""]) in ["unauthorized", "forbidden", "no_tenant"]) then { _state = "unlinked"; };
        };
    };
    _st set ["state", _state];
    ["act", "synced"] call comspec_sse_fnc_idCoverPage;
};
true
