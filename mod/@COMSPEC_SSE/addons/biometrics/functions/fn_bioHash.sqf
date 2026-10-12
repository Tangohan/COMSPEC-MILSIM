/*
    Hachage déterministe 24 bits d'une chaîne.

    Les nombres SQF sont des flottants 32 bits : tout calcul reste sous 2^24 pour
    rester exact et identique sur le serveur et chaque client. Deux voies (Adler +
    djb2) puis quatre tours de Feistel sur deux moitiés de 12 bits, pour que deux
    clés voisines (« P100231|fp3 », « P100231|fp4 ») donnent des valeurs éloignées.

    [_text] call comspec_sse_fnc_bioHash -> Number 0..16777215
*/
params [["_text", "", [""]]];

private _a = 1;
private _b = 0;
private _c = 5381;
{
    _a = (_a + _x) mod 65521;
    _b = (_b + _a) mod 65521;
    _c = ((_c * 33) + _x) mod 65519;
} forEach (toArray _text);

private _hi = ((_b mod 4096) + (floor (_c / 16))) mod 4096;
private _lo = (_a + (_c mod 4096) * 3 + (floor (_b / 4096)) * 7) mod 4096;
for "_r" from 0 to 3 do {
    private _f = ((_hi * 2909) + (_r * 977) + 1013) mod 4096;
    private _t = _lo;
    _lo = _hi;
    _hi = (_t + _f) mod 4096;
};
(_hi * 4096) + _lo
