/*
    Met à jour le carnet de bord Athena (sujet + fiche) après une liaison réussie.
    createDiaryRecord renvoie un DiaryRecord (objet) — ne pas comparer à un entier.
*/
if (!hasInterface) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _codeHint = if (_state isEqualTo "linked" && {count _key > 0}) then {
    "Compte lié — liaison Athena active."
} else {
    if (count _key > 0) then {
        "Paramètres enregistrés, liaison en attente de confirmation."
    } else {
        "Pas encore de clé — K → Compte Athena (saisir un code) ou Connecter mon téléphone."
    }
};

player createDiarySubject ["Athena", "Athena ATAK"];

private _body = format [
    "Connected to <font color='#7dffb3'>%1</font><br/><br/>%2<br/><br/>Sur le site : ATAK → <font color='#7dffb3'>Connexion en jeu</font> pour générer un code.<br/>En jeu : touche <font color='#7dffb3'>K</font> → Compte Athena, ou action <font color='#7dffb3'>Connecter mon téléphone</font> pour le QR.",
    _label,
    _codeHint
];

private _rec = missionNamespace getVariable ["COMSPEC_DiaryRecord", nil];
private _needCreate = isNil "_rec" || {_rec isEqualType 0} || {_rec isEqualTo false};
if (!_needCreate) then {
    // Anciennes versions stockaient parfois un handle invalide.
    _needCreate = false;
};

if (_needCreate) then {
    _rec = player createDiaryRecord ["Athena", ["Liaison Overwatch", _body]];
    missionNamespace setVariable ["COMSPEC_DiaryRecord", _rec, false];
} else {
    player setDiaryRecordText [["Athena", _rec], ["Liaison Overwatch", _body]];
};
