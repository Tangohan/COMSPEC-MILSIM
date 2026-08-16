/*
    Mappe menace BII (Green/Orange/Red/Black) → profil SSE.
*/
params [["_threat", "Orange", [""]]];

private _t = toUpper _threat;
switch (_t) do {
    case "GREEN": { "CIVILIAN" };
    case "ORANGE": { "INSURGENT" };
    case "RED": { "INSURGENT" };
    case "BLACK": { "HVT" };
    default { "INSURGENT" };
}
