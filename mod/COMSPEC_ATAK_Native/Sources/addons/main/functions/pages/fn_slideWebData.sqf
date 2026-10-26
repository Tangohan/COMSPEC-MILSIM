/*
    Diapositive qu'Arma refuse en texture (image hors d'un PBO, texture [-1,-1]) : lue en base64 par COMSPEC Link
    (ImageDataChunk) pour le navigateur intégré (CT_WEBBROWSER), dans l'app Briefing comme sur les écrans COMSPEC.
    À appeler dans un script planifié (spawn) : chaque morceau attend la DLL sans figer le jeu (fn_extAwait).
    Params : [source, clé]
      source  adresse Athena (un .paa est remplacé par la copie .jpg du site, même nom), ou « local:<chemin> » pour une
              image déjà téléchargée par la DLL (diapositive Google Slides de comspec_cache, COMSPEC Link 2.0.98+)
      clé     nom du fichier de travail côté DLL (lettres, chiffres, _ et -)
    Renvoie [type MIME ("jpeg" | "png"), base64, ""] ou ["", "", raison lisible].
*/
params [["_src", "", [""]], ["_key", "slide", [""]]];
if (_src isEqualTo "") exitWith { ["", "", "aucune image"] };
private _arg = _src;
if !((toLower (_src select [0, 6])) isEqualTo "local:") then {
    private _lu = toLower _src;
    if ((_lu select [((count _lu) - 4) max 0]) isEqualTo ".paa") then { _arg = (_src select [0, (count _src) - 4]) + ".jpg"; };
};
private _b64 = "";
private _mime = "jpeg";
private _off = 0;
private _err = "";
for "_n" from 0 to 400 do {
    private _r = [(["COMSPECExtension", "ImageDataChunk", [_arg, _key, _off]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
    if (isNil "_r") then { _r = ""; };
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    if !(_r isEqualType "") then { _r = str _r; };
    private _f = _r splitString "|";
    if ((_f param [0, ""]) isNotEqualTo "OK") exitWith { _err = [_f param [1, ""], "unknown"] select (_r isEqualTo ""); };
    _b64 = _b64 + (_f param [3, ""]);
    _off = parseNumber (_f param [2, "0"]);
    if ((count _f) > 4) then { _mime = _f select 4; };
    if (_off >= parseNumber (_f param [1, "0"])) exitWith {};
};
if (_err isEqualTo "" && {_b64 isEqualTo ""}) then { _err = "image vide"; };
if (_err isEqualTo "") exitWith { [_mime, _b64, ""] };
private _local = (toLower (_src select [0, 6])) isEqualTo "local:";
["", "", switch (true) do {
    case (_err in ["unknown", "unknown_command"]): { ["COMSPEC Link trop ancienne pour le navigateur intégré (2.0.96 requise)", "COMSPEC Link trop ancienne pour afficher les diapositives Google (2.0.98 requise)"] select _local };
    // COMSPEC Link 2.0.97 et avant : « local: » pris pour une adresse d'un autre site.
    case (_local && {_err in ["host_not_allowed", "path_not_allowed"]}): { "COMSPEC Link 2.0.98 requise pour afficher les diapositives Google" };
    case (_err isEqualTo "not_found"): { ["le site n'a pas encore la copie JPEG : renvoyez la diapositive sur Athena", "image Google introuvable dans le cache : touchez ACTUALISER"] select _local };
    case (_err isEqualTo "too_large"): { "image trop lourde pour le navigateur intégré (1,4 Mo au plus)" };
    default { _err };
}]
