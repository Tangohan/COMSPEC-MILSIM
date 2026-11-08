/*
    Licence d'utilisation du mod COMSPEC ATAK (note de service du menu principal, fn_ndaTexts).
    Params : [_accept] — true : enregistre sur le profil l'acceptation de la version courante (sauvegarde par l'appelant).
    Retour : [version, acceptée]
    Changer _version réaffiche la note à tous les joueurs au prochain lancement (nouvelle version de la licence).
*/
params [["_accept", false]];

private _version = "2";
if (_accept) then {
    profileNamespace setVariable ["comspec_overwatch_licence_ack", _version];
};
[_version, (profileNamespace getVariable ["comspec_overwatch_licence_ack", ""]) isEqualTo _version]
