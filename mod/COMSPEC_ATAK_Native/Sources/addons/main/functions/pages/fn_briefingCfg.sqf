/*
    Actions du panneau PARAMÈTRES de l'app Briefing (fn_briefingSettings).
    ["open"] / ["close"]       ouvre l'engrenage / revient à l'onglet précédent
    ["source", "AUTO"|"ATHENA"|"GOOGLE"]
    ["test"]                   nettoie le lien saisi, puis charge la présentation sur ce téléphone seulement
    ["save"] / ["clear"]       enregistre le lien nettoyé / l'oublie
    ["share"]                  charge le lien et le diffuse à tous (même chemin que le tableau de briefing)
    ["athena"]                 reprend le lien Google publié sur Athena
    ["auto"]                   actualisation automatique des diapositives Athena (60 s)
    ["load", [lien, diffuser]] chargement Google Slides par COMSPEC Link ; résultat dans COMSPEC_ATAK_BriefTest
    ["step", index]            diapositive d'une présentation chargée sur ce téléphone seulement (sans diffusion)
    Le chargement ne passe pas par fn_loadGoogleBriefing (Overwatch) : celle-ci ouvre l'ancien tableau de briefing
    par-dessus le téléphone. La réponse de la DLL (google_deck_ready / google_deck_error) reste traitée par Overwatch.
*/
params [["_action", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _rerender = { [{ ["BRIEFING"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
// Saisie en cours gardée : chaque action redessine la page.
private _keepDraft = {
    if (!isNull ((uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["briefUrl", controlNull])) then {
        uiNamespace setVariable ["COMSPEC_ATAK_BriefUrlDraft", ["briefUrl", ""] call comspec_atak_native_fnc_formValue];
    };
};
// Résultat affiché sous le champ : [genre, [lignes]] (une ligne par phrase, traduites une à une).
private _setTest = { params ["_kind", "_msg"]; uiNamespace setVariable ["COMSPEC_ATAK_BriefTest", [_kind, [[_msg], _msg] select (_msg isEqualType [])]]; };
// Lien du champ, nettoyé ; remis propre dans le champ. Renvoie [valide, lien].
private _checked = {
    call _keepDraft;
    ([uiNamespace getVariable ["COMSPEC_ATAK_BriefUrlDraft", ""]] call comspec_atak_native_fnc_briefingUrl) params ["_ok", "_url", "_msg", "_fixes"];
    if (_ok) then {
        uiNamespace setVariable ["COMSPEC_ATAK_BriefUrlDraft", _url];
        ["ok", [_msg] + ([["Retouches : " + (_fixes joinString " · ")], []] select ((count _fixes) isEqualTo 0))] call _setTest;
    } else {
        ["err", _msg] call _setTest;
    };
    [_ok, _url]
};
private _save = {
    params ["_url"];
    profileNamespace setVariable ["COMSPEC_ATAK_BriefGoogleUrl", _url];
    [] call comspec_atak_native_fnc_saveProfile;
};

switch (_action) do {
    case "open": {
        if ((_s getOrDefault ["briefTab", "SLIDES"]) isNotEqualTo "SETTINGS") then { _s set ["briefPrevTab", _s getOrDefault ["briefTab", "SLIDES"]]; };
        _s set ["briefTab", "SETTINGS"];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefUrlDraft", profileNamespace getVariable ["COMSPEC_ATAK_BriefGoogleUrl", ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefTest", []];
        call _rerender;
    };
    case "close": {
        call _keepDraft;
        private _prev = _s getOrDefault ["briefPrevTab", "SLIDES"];
        _s set ["briefTab", [_prev, "SLIDES"] select (_prev isEqualTo "SETTINGS")];
        call _rerender;
    };
    case "source": {
        call _keepDraft;
        profileNamespace setVariable ["COMSPEC_ATAK_BriefSource", _arg];
        [] call comspec_atak_native_fnc_saveProfile;
        uiNamespace setVariable ["COMSPEC_ATAK_BriefAutoTried", ""];
        if (_arg isEqualTo "GOOGLE" && {(profileNamespace getVariable ["COMSPEC_ATAK_BriefGoogleUrl", ""]) isEqualTo ""}) then {
            ["warn", "Source Google Slides : enregistrez d'abord un lien ci-dessous."] call _setTest;
        };
        call _rerender;
    };
    case "save": {
        ([] call _checked) params ["_ok", "_url"];
        if (_ok) then {
            [_url] call _save;
            ["ok", ["Lien enregistré."] + ((uiNamespace getVariable ["COMSPEC_ATAK_BriefTest", ["", []]]) select 1)] call _setTest;
        };
        call _rerender;
    };
    case "clear": {
        [""] call _save;
        uiNamespace setVariable ["COMSPEC_ATAK_BriefUrlDraft", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefLocalDeck", ""];
        ["ok", "Lien effacé."] call _setTest;
        call _rerender;
    };
    case "athena": {
        uiNamespace setVariable ["COMSPEC_ATAK_BriefUrlDraft", missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefTest", []];
        call _rerender;
    };
    case "auto": {
        call _keepDraft;
        profileNamespace setVariable ["COMSPEC_ATAK_BriefAuto", !(profileNamespace getVariable ["COMSPEC_ATAK_BriefAuto", false])];
        [] call comspec_atak_native_fnc_saveProfile;
        call _rerender;
    };
    case "test";
    case "share": {
        ([] call _checked) params ["_ok", "_url"];
        if (_ok) then {
            if ((profileNamespace getVariable ["COMSPEC_ATAK_BriefGoogleUrl", ""]) isEqualTo "") then { [_url] call _save; };
            if (_action isEqualTo "share") then { [_url] call _save; };
            ["load", [_url, _action isEqualTo "share"]] call comspec_atak_native_fnc_briefingCfg;
        };
        call _rerender;
    };
    case "load": {
        _arg params [["_url", ""], ["_broadcast", false]];
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["warn", "Google Slides demande COMSPEC Link."] call _setTest; false };
        "COMSPECExtension" callExtension ["CancelGoogleDeck", []];
        private _rid = format ["gdeck_%1_%2", clientOwner, floor (diag_tickTime * 1000)];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", _rid];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingUrl", _url];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPendingIndex", 0];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefGErr", []];
        uiNamespace setVariable ["COMSPEC_ATAK_BriefOwnLoad", _rid];
        private _raw = ["COMSPECExtension" callExtension ["LoadGoogleDeck", [_url, "0", _rid]]] call comspec_overwatch_connect_fnc_extResult;
        if ((_raw find "accepted") < 0) exitWith {
            missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];
            ["err", ["COMSPEC Link refuse ce lien : il ne reconnaît pas l'identifiant de la présentation.", "COMSPEC Link ne répond pas (DLL absente ou trop ancienne)."] select (_raw isEqualTo "")] call _setTest;
            false
        };
        // Présentation chargée sur ce téléphone seulement : la navigation ne sera pas diffusée (voir ["step"]).
        uiNamespace setVariable ["COMSPEC_ATAK_BriefLocalDeck", [_url, ""] select _broadcast];
        // Diffusion : les autres joueurs chargent le même lien ; ce téléphone, déjà en cours, est ignoré par Overwatch.
        if (_broadcast) then { ["show", _url, 0] call comspec_overwatch_connect_fnc_broadcastGoogleBriefingState; };
        ["wait", "Chargement de la présentation par COMSPEC Link…"] call _setTest;
        [_rid, _url, _broadcast] spawn {
            params ["_rid", "_url", "_broadcast"];
            private _t0 = diag_tickTime;
            waitUntil { sleep 0.25; (missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""]) isNotEqualTo _rid || {diag_tickTime - _t0 > 45} };
            // Chargement remplacé par un autre (nouvel id, pas "" posé par la réponse de la DLL) : c'est lui qui rendra compte.
            private _cur = missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""];
            if (_cur isNotEqualTo _rid && {_cur isNotEqualTo ""}) exitWith {};
            private _err = uiNamespace getVariable ["COMSPEC_ATAK_BriefGErr", []];
            private _res = switch (true) do {
                case ((count _err) > 0): {
                    private _human = createHashMapFromArray [
                        ["private", "Présentation non publique : dans Google Slides, Partager > Accès général > « Tous les utilisateurs disposant du lien » (ou Fichier > Partager > Publier sur le Web)."],
                        ["not_found", "Présentation introuvable : le lien est peut-être incomplet ou la présentation a été supprimée."],
                        ["network", "Google ne répond pas (réseau ou pare-feu). Réessayez dans un moment."],
                        ["parse_failed", "Google a renvoyé une page illisible : vérifiez que le lien ouvre bien la présentation dans un navigateur."],
                        ["cancelled", "Chargement annulé par un autre chargement."]
                    ];
                    ["err", _human getOrDefault [_err param [1, ""], format ["Échec du chargement : %1", _err param [2, _err param [1, "?"]]]]]
                };
                case ((missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""]) isEqualTo _rid): { ["err", "Pas de réponse de Google après 45 s. Vérifiez le lien et votre connexion."] };
                case ((missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) && {(missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""]) isEqualTo _url}): {
                    ["ok", format [["Présentation chargée sur votre téléphone : %1 diapositive(s).", "Présentation chargée et diffusée à tous : %1 diapositive(s)."] select _broadcast, missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 0]]]
                };
                default { ["err", "Google n'a renvoyé aucune image pour cette présentation."] };
            };
            // Chargé sur ce téléphone seul : rien ne part aux autres joueurs tant que DIFFUSER À TOUS n'est pas touché.
            uiNamespace setVariable ["COMSPEC_ATAK_BriefTest", [_res select 0, [_res select 1] + ([[], ["Les autres joueurs ne la voient pas : touchez DIFFUSER À TOUS pour la leur montrer."]] select ((_res select 0) isEqualTo "ok" && {!_broadcast}))]];
            [(_res select 0) isEqualTo "ok", _res select 1] params ["_good", "_msg"];
            [["WARNING", "SUCCESS"] select _good, _msg, 4, 30] call comspec_atak_native_fnc_notify;
            [{
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "BRIEFING") exitWith {};
                if ((_s getOrDefault ["briefTab", "SLIDES"]) isEqualTo "SETTINGS") then {
                    if (!isNull ((uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["briefUrl", controlNull])) then {
                        uiNamespace setVariable ["COMSPEC_ATAK_BriefUrlDraft", ["briefUrl", ""] call comspec_atak_native_fnc_formValue];
                    };
                };
                ["BRIEFING"] call comspec_atak_native_fnc_pageRender;
            }] call CBA_fnc_execNextFrame;
        };
        true
    };
    case "step": {
        private _pid = missionNamespace getVariable ["COMSPEC_GoogleBriefingPresentationId", ""];
        if (_pid isEqualTo "") exitWith { false };
        "COMSPECExtension" callExtension ["CancelGoogleDeck", []];
        private _rid = format ["gslide_%1_%2", clientOwner, floor (diag_tickTime * 1000)];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", _rid];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPendingIndex", _arg];
        private _raw = ["COMSPECExtension" callExtension ["LoadGoogleSlide", [_pid, str _arg, _rid]]] call comspec_overwatch_connect_fnc_extResult;
        if ((_raw find "accepted") < 0) exitWith {
            ["load", [missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""], false]] call comspec_atak_native_fnc_briefingCfg;
        };
        true
    };
};
true
