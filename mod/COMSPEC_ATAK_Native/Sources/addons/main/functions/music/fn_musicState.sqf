/*
    App Musique : état du lecteur du joueur (missionNamespace COMSPEC_ATAK_Music), créé au premier appel.
      kind/ref/title/len : piste en cours (kind "" = rien ; file, url, srv)   start : heure (serverTime) du début de la piste
      paused, pausedAt   : pause et position (s)                             vol : 0-100 (profil)
      queue [[kind, ref, title, len]...], qi : file et index                 repeat : off / all / one ; shuffle
      speaker : haut-parleur (les joueurs proches entendent) ; hear : entendre les haut-parleurs des autres
      status : dernier retour DLL [état, position ms, durée ms, titre, erreur]
*/
private _m = missionNamespace getVariable ["COMSPEC_ATAK_Music", createHashMap];
if ((count _m) isEqualTo 0) then {
    _m = createHashMapFromArray [
        ["kind", ""], ["ref", ""], ["title", ""], ["len", 0], ["start", 0], ["paused", false], ["pausedAt", 0],
        ["vol", profileNamespace getVariable ["COMSPEC_ATAK_MusicVol", 60]], ["queue", []], ["qi", 0],
        ["repeat", profileNamespace getVariable ["COMSPEC_ATAK_MusicRepeat", "all"]], ["shuffle", profileNamespace getVariable ["COMSPEC_ATAK_MusicShuffle", false]],
        ["speaker", false], ["hear", profileNamespace getVariable ["COMSPEC_ATAK_MusicHear", true]],
        ["status", ["idle", 0, 0, "", ""]], ["errors", 0]
    ];
    missionNamespace setVariable ["COMSPEC_ATAK_Music", _m];
};
_m
