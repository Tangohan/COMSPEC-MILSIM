/*
    Horloge murale, en secondes, comparable d'une session à l'autre.

    `time` ne convient pas dès qu'une valeur est écrite dans le profil du joueur :
    c'est le temps écoulé depuis le début de la mission, et il repart à zéro à la
    mission suivante. Un horodatage posé à `time = 3600` relu au début de la
    session d'après donne un âge négatif — donc « jamais périmé », alors que
    l'entrée date de la semaine précédente.

    On dérive donc un compteur continu de `systemTimeUTC` : jour julien × 86400,
    plus l'heure du jour. UTC et non l'heure locale, sinon un changement d'heure
    fait sauter le compteur d'une heure en arrière deux fois par an.

    Returns: Number — secondes, croissantes et comparables entre sessions
*/
private _st = systemTimeUTC;
if (!(_st isEqualType []) || { (count _st) < 6 }) exitWith { 0 };

_st params ["_year", "_month", "_day", "_hour", "_minute", "_second"];

// Jour julien (algorithme « days from civil ») : donne un numéro de jour
// strictement croissant, y compris à travers les changements de mois et d'année.
private _a = floor ((14 - _month) / 12);
private _y = _year + 4800 - _a;
private _m = _month + (12 * _a) - 3;

private _jdn = _day
    + floor (((153 * _m) + 2) / 5)
    + (365 * _y)
    + floor (_y / 4)
    - floor (_y / 100)
    + floor (_y / 400)
    - 32045;

(_jdn * 86400) + (_hour * 3600) + (_minute * 60) + _second
