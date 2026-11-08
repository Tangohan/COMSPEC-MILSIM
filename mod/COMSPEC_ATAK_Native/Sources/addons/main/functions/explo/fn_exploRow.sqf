/*
    Fiche d'une charge dans l'app Explosifs : même contenu au dessin de la page et à la mise à jour sur place.
    Params : [entrée de fn_exploList, numéro de pose, clés cochées]
    Renvoie [texte structuré, boutons [[libellé, code, style ("" | "main" | "red"), actif, aide]...]]. Le nombre de boutons ne dépend que du type.
*/
params ["_c", "_num", "_sel"];
private _th = [] call comspec_atak_native_fnc_theme;
private _k = _c get "key";
private _e = _c get "obj";
private _kind = _c get "kind";
private _dim = _th get "hexTextDim";
private _mmss = { params ["_t"]; _t = (ceil _t) max 0; format ["%1:%2", floor (_t / 60), [str (_t mod 60), "0" + str (_t mod 60)] select ((_t mod 60) < 10)] };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _distM = player distance2D _e;
private _distT = [format ["%1 m", round _distM], format ["%1 km", (_distM / 1000) toFixed 1]] select (_distM >= 1000);
private _dir = player getDir _e;
(["can", _c] call comspec_atak_native_fnc_exploAction) params ["_can", "_why", "_note"];
private _isNet = _kind isEqualTo "net";
private _armed = _c get "armed";
private _tag = switch (_kind) do {
    case "net": { [["RÉSEAU · DÉSARMÉE", _th get "hexOk"], ["RÉSEAU · ARMÉE", _th get "hexAlert"]] select _armed };
    case "atak": { ["ATAK", _th get "hexWarn"] };
    case "command": { ["CLACKER", _th get "hexWarn"] };
    case "timer": { ["MINUTERIE", _th get "hexAlert"] };
    case "cell": { ["TÉLÉPHONE ACE", _th get "hexInfo"] };
    case "dead": { ["HOMME MORT", _th get "hexWarn"] };
    case "vanilla": { ["TÉLÉCOMMANDE", _th get "hexWarn"] };
    default { ["PIÈGE", _dim] };
};
private _line3 = switch (_kind) do {
    case "timer": {
        if ((_c get "remaining") >= 0) then {
            format ["<t color='%1' font='COMSPEC_BarlowBold'>Saute dans %2</t> · minuterie de %3", _th get "hexAlert", [_c get "remaining"] call _mmss, [_c get "fuse"] call _mmss]
        } else { "Minuterie lancée, temps restant inconnu" };
    };
    case "cell": { "Composez son code sur le téléphone ACE" };
    case "dead": { "Part si vous tombez : pas de mise à feu d'ici" };
    case "mine": { "Piège passif : pas de mise à feu à distance" };
    default {
        if (_why isNotEqualTo "") then { format ["<t color='%1'>%2</t>", _th get (["hexAlert", "hexWarn"] select (_why isEqualTo "Désarmée : armez-la pour tirer")), _why] } else { _note }
    };
};
private _sq = _k in _sel;
private _text = format ["<t font='COMSPEC_BarlowBold'>C%1 · %2</t>  <t color='%3' size='0.85' font='COMSPEC_BarlowBold'>%4</t>%5<br/><t size='0.85' color='%6'>%7 · %8 · %9° %10</t><br/><t size='0.85' color='%6'>%11</t>",
    _num, _c get "label", _tag select 1, _tag select 0, ["", "  <t color='" + (_th get "hexOk") + "' size='0.85'>✓ séquence</t>"] select _sq, _dim,
    _c get "grid", _distT, round _dir, [_dir] call _card, _line3];
private _call = { params ["_act"]; compile format ["[%1, %2] call comspec_atak_native_fnc_exploAction;", str _act, str _k] };
private _btns = [];
if (_isNet) then {
    _btns pushBack [["ARMER", "DÉSARMER"] select _armed, ["toggleArm"] call _call, ["main", ""] select _armed, true, "La commande passe par le réseau"];
};
if (_kind in ["net", "atak", "command", "vanilla"]) then {
    _btns pushBack ["FEU", ["ask"] call _call, ["", "red"] select _can, _can, ["Confirmation demandée", _why] select (_why isNotEqualTo "")];
    _btns pushBack [["COCHER", "DÉCOCHER"] select _sq, ["sel"] call _call, "", true, "Pour la séquence"];
};
_btns pushBack ["CARTE", ["map"] call _call, "", true, "Centre la carte sur la charge"];
_btns pushBack ["SIGNALER", ["share"] call _call, "", true, "Ping à votre camp : position de la charge"];
[_text, _btns]
