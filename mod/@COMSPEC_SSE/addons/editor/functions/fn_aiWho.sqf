/*
    Nom unique d'une IA (SERVEUR) : ce qu'elle donne (ses papiers, sinon son profil Athena) et qui elle est vraiment.
    Le nom d'unité Arma (« Khrushchev ») n'est jamais présenté comme le nom de l'IA : il est rendu à part (unitName).
    [_unit] call comspec_sse_fnc_aiWho -> HashMap (vide hors serveur ou unité nulle)
      given      nom donné : « Prénom NOM » imprimé sur sa pièce principale ; sans papier : son identité unique
                 (fn_idIdentityOf), sinon le nom du profil Athena qui la deviendra
      true       vrai nom : vraie identité de la pièce (trueFirst / trueLast) ; sans papier : son identité unique
                 (fn_idIdentityOf : nom donné par la mission, sinon tirée une fois et gardée ; celle de ses futurs papiers)
      truth      true (identité vraie) · lies (papier vrai d'un autre nom : fausse identité, il ment) · forged (faux
                 papiers) · cover (couverture) · pending (papiers décidés à la première demande) · none (sans papier)
      label      libellé lisible de truth (stringtable de l'éditeur)
      unitName   nom d'unité Arma · docs  nombre de papiers · decided  papiers déjà décidés
      profileId  identifiant du profil Athena ("" sinon) · profileRef  référence PRF-… ("" sinon)
      side       civ | west | east | guer · role  civilian | military | suspect | hvt (profil, sinon camp et fiche bio)
    Même règle que la bulle et la fenêtre de discussion (ia_rp) : l'identité SSE d'abord, l'unité Arma en gris.
*/
params [["_u", objNull, [objNull]]];
if (!isServer || {isNull _u}) exitWith { createHashMap };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _str = { if (_this isEqualType "") then { _this } else { str _this } };
private _key = [_u] call comspec_sse_fnc_bioPersonKey;
private _docs = (((_reg get "units") getOrDefault [_key, []]) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry }) select { (count _x) > 0 };
private _decided = (_reg get "decided") getOrDefault [_key, false];
private _pid = (_u getVariable ["COMSPEC_ID_AiProfile", ""]) call _str;
private _prof = (_reg getOrDefault ["aiProfiles", createHashMap]) getOrDefault [_pid, createHashMap];
private _full = { params ["_f", "_l"]; trim format ["%1 %2", _f, toUpper _l] };
private _given = "";
private _true = "";
private _truth = "pending";
if (_docs isNotEqualTo []) then {
    private _d = _docs select 0;
    _given = [_d getOrDefault ["first", ""], _d getOrDefault ["last", ""]] call _full;
    _true = [_d getOrDefault ["trueFirst", _d getOrDefault ["first", ""]], _d getOrDefault ["trueLast", _d getOrDefault ["last", ""]]] call _full;
    // Plusieurs papiers : la nature la plus grave l'emporte (faux > couverture > vrai).
    private _rank = ["generated", "civil", "alias", "cover", "forged"];
    private _worst = 0;
    { _worst = _worst max ((_rank find (_x getOrDefault ["kind", "civil"])) max 0); } forEach _docs;
    // Papiers vrais d'un profil Athena : l'identité du profil est la sienne (le tirage propre de l'unité ne compte plus).
    if (_worst <= 1 && {_pid isNotEqualTo ""} && {(count _prof) > 0}) then { _true = _given; };
    _truth = switch (_worst) do {
        case 4: { "forged" };
        case 3: { "cover" };
        default { ["true", "lies"] select ((toLower _given) isNotEqualTo (toLower _true)) };
    };
} else {
    // Sans papier : son identité unique (nom donné, sinon tirée une fois et gardée), celle que porteront ses papiers.
    private _own = ["get", [_u]] call comspec_sse_fnc_idIdentityOf;
    _true = [_own getOrDefault ["first", ""], _own getOrDefault ["last", ""]] call _full;
    _given = _true;
    private _pn = _prof getOrDefault ["name", ""];
    if (_pn isEqualType "" && {_pn isNotEqualTo ""} && {!(_u getVariable ["COMSPEC_SSE_NameAuthored", false])}) then {
        private _p = _pn splitString " ";
        _given = [_p param [0, ""], (_p select [1, 9]) joinString " "] call _full;
        _true = _given;
    };
    if (_decided) then { _truth = "none"; };
};
private _sideKey = ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (side group _u)) max 0);
private _role = _prof getOrDefault ["category", ""];
if !(_role in ["civilian", "military", "suspect", "hvt"]) then {
    private _bdb = toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"]);
    _role = call {
        if (_bdb isEqualTo "bewl1") exitWith { "hvt" };
        if (_bdb in ["bewl2", "bewl3"]) exitWith { "suspect" };
        if (_sideKey isEqualTo "civ") exitWith { "civilian" };
        "military"
    };
};
createHashMapFromArray [
    ["given", _given], ["true", _true], ["truth", _truth], ["label", localize ("STR_COMSPEC_SSE_ED_Truth_" + _truth)],
    ["unitName", name _u], ["docs", count _docs], ["decided", _decided],
    ["profileId", _pid], ["profileRef", (_prof getOrDefault ["ref", ""]) call _str], ["side", _sideKey], ["role", _role]
]
