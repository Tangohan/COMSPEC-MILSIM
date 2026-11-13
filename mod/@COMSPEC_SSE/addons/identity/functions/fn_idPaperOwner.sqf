/*
    API publique pour les mods et les scripts (SERVEUR) : propriétaire et porteur d'un papier en jeu.
    [_paper] call comspec_sse_fnc_idPaperOwner -> [ownerUid, ownerName, holder, holderUid, foreign]
      _paper     classe d'exemplaire (« COMSPEC_ID_Card_007 ») ou docId (« ID0007 »)
      ownerUid   Steam ID du membre à qui le papier a été remis ("" : IA, papier né en jeu sans joueur)
      holder     porteur connu (unité ou conteneur au sol / corps ; objNull si inconnu)
      foreign    vrai : un autre joueur que le propriétaire l'a ramassé
    Papier inconnu ou appel hors serveur : ["", "", objNull, "", false].
*/
params [["_paper", "", [""]]];
if (!isServer) exitWith { ["", "", objNull, "", false] };
private _p = [_paper] call comspec_sse_fnc_idPaperOf;
if ((count _p) isEqualTo 0) exitWith { ["", "", objNull, "", false] };
[_p get "ownerUid", _p get "ownerName", _p get "holder", _p get "holderUid", _p get "foreign"]
