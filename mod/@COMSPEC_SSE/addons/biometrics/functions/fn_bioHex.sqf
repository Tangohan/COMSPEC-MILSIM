/*
    Gabarit hexadécimal stable (affichage des codes d'empreinte, d'iris, de visage).
    [_key, _salt, _length] call comspec_sse_fnc_bioHex -> "3FA90C1E"
*/
params [["_key", "", [""]], ["_salt", "", ["", 0]], ["_length", 8, [0]]];

// La graine de campagne fait partie du gabarit : un même identifiant dans deux
// campagnes différentes désigne deux personnes différentes.
private _campaign = missionNamespace getVariable ["comspec_sse_bio_campaignSeed", ""];
if (!(_campaign isEqualType "") || {(trim _campaign) isEqualTo ""}) then { _campaign = missionName; };
private _digits = toArray "0123456789ABCDEF";
private _out = [];
private _block = 0;
while { (count _out) < _length } do {
    private _h = [format ["%1|%2|%3|%4", _campaign, _key, _salt, _block]] call comspec_sse_fnc_bioHash;
    for "_i" from 0 to 5 do {
        if ((count _out) < _length) then {
            _out pushBack (_digits select (_h mod 16));
            _h = floor (_h / 16);
        };
    };
    _block = _block + 1;
};
toString _out
