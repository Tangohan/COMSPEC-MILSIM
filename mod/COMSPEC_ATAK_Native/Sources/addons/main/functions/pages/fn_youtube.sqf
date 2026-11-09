/*
    Liens YouTube : analyse sûre d'une adresse et lecture dans le téléphone (app « Vidéo YouTube », fn_pageYoutube).
    Vie privée : aucune requête vers YouTube / Google tant que le joueur n'a pas lancé une lecture (bouton LIRE). Ce mod
    n'envoie jamais un lien YouTube aux autres joueurs : chacun lance sa propre lecture.
    Params : [action, argument]
      "id" adresse        identifiant de 11 caractères ([A-Za-z0-9_-]) tiré de youtube.com/watch?v=, /embed/, /shorts/, /live/, /v/,
                          youtu.be/ ou d'un identifiant seul ; "" si l'adresse n'est pas reconnue. Aucun autre hôte, port,
                          identifiant de connexion ni caractère inattendu n'est accepté.
      "embed" id          adresse du lecteur intégré (youtube-nocookie.com/embed/ID) pour un identifiant déjà validé, sinon ""
      "enabled"           réglage comspec_atak_native_youtube (coché par l'administration : décoché par défaut)
      "steam" id          ouvre la vidéo dans la surcouche Steam (openYoutubeVideo), repli sans navigateur intégré
*/
params [["_act", ""], ["_arg", ""]];
private _valid = {
    params ["_s"];
    _s isEqualType "" && {(count _s) isEqualTo 11} && {((_s splitString "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-") isEqualTo [])}
};
switch (_act) do {
    case "enabled": { missionNamespace getVariable ["comspec_atak_native_youtube", false] };
    case "embed": {
        if !([_arg] call _valid) exitWith { "" };
        "https://www.youtube-nocookie.com/embed/" + _arg + "?rel=0&playsinline=1"
    };
    case "steam": {
        if !([_arg] call _valid) exitWith { false };
        openYoutubeVideo ("youtu.be/" + _arg);
        true
    };
    case "id": {
        if !(_arg isEqualType "") exitWith { "" };
        private _u = trim _arg;
        if ((count _u) > 300 || {(count _u) isEqualTo 0}) exitWith { "" };
        if ([_u] call _valid) exitWith { _u };
        private _lu = toLower _u;
        if ((_lu select [0, 8]) isEqualTo "https://") then { _u = _u select [8]; } else { if ((_lu select [0, 7]) isEqualTo "http://") then { _u = _u select [7]; }; };
        // Fragment (#t=…) ignoré.
        _u = (_u splitString "#") param [0, ""];
        private _cut = _u find "/";
        private _host = toLower ([_u, _u select [0, _cut]] select (_cut >= 0));
        private _rest = [_u select [_cut + 1], ""] select (_cut < 0);
        // Hôte exact : pas de port, de identifiant@ ni de sous-domaine inattendu.
        { if ((_host select [0, count _x]) isEqualTo _x) then { _host = _host select [count _x]; }; } forEach ["www.", "m.", "music."];
        if !(_host in ["youtube.com", "youtube-nocookie.com", "youtu.be"]) exitWith { "" };
        private _pq = _rest splitString "?";
        private _segs = (_pq param [0, ""]) splitString "/";
        private _query = _pq param [1, ""];
        private _id = "";
        if (_host isEqualTo "youtu.be") then {
            _id = _segs param [0, ""];
        } else {
            private _first = _segs param [0, ""];
            if (_first in ["embed", "shorts", "live", "v"]) then { _id = _segs param [1, ""]; };
            if (_first isEqualTo "watch") then {
                { (_x splitString "=") params [["_k", ""], ["_v", ""]]; if (_k isEqualTo "v") exitWith { _id = _v; }; } forEach (_query splitString "&");
            };
        };
        [_id, ""] select !([_id] call _valid)
    };
    default { "" };
};
