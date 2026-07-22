/*
    Historiquement : détection ItemAndroid / S7 pour autoriser la position.
    La condition d’équipement a été retirée — toujours vrai pour compatibilité
    des appels restants (config / éventuels scripts).
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };
true
