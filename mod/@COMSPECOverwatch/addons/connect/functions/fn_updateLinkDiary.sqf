/*
    Met à jour le carnet de bord Athena (sujet + fiche) après une liaison réussie.
    Inspiré cTab IRL / SIT : code court + URL lisibles pour le joueur.
*/
if (!hasInterface) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
private _keyLen = count (missionNamespace getVariable ["comspec_overwatch_api_key", ""]);
private _codeHint = if (_keyLen > 0) then {
    "Compte lié (clé d’accès enregistrée)."
} else {
    "Pas encore de clé — K → Compte Athena (saisir un code) ou Connecter mon téléphone."
};

player createDiarySubject ["Athena", "Athena ATAK"];

private _body = format [
    "Connecté à <font color='#7dffb3'>%1</font><br/><br/>%2<br/><br/>Sur le site : ATAK → <font color='#7dffb3'>Connexion en jeu</font> pour générer un code.<br/>En jeu : touche <font color='#7dffb3'>K</font> → Compte Athena, ou action <font color='#7dffb3'>Connecter mon téléphone</font> pour le QR.",
    _label,
    _codeHint
];

private _rec = missionNamespace getVariable ["COMSPEC_DiaryRecord", -1];
if (_rec < 0) then {
    _rec = player createDiaryRecord ["Athena", ["Liaison Overwatch", _body]];
    missionNamespace setVariable ["COMSPEC_DiaryRecord", _rec, false];
} else {
    player setDiaryRecordText [["Athena", _rec], ["Liaison Overwatch", _body]];
};
