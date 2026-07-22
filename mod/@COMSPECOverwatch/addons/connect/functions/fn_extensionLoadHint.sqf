/*
    Message actionnable quand l’extension ne répond pas (réponse vide / non chargée).
    Ne présume PAS d’un stub ~32 Ko : avec une DLL Native AOT correcte (~5 Mo), la cause
    réelle la plus fréquente est le blocage BattlEye
    (RPT : « Insufficient system resources » / « ressources système insuffisantes »).
    Params optionnels: [_context] ("link" | "connect" | "generic"), [_forLog] (bool)
*/
params [["_context", "generic"], ["_forLog", false]];

private _err = missionNamespace getVariable ["COMSPEC_LastExtError", 0];

private _short = switch (_context) do {
    case "link";
    case "connect": {
        "Module Athena bloqué (souvent BattlEye). Désactivez BattlEye dans le lanceur, puis relancez Arma complètement."
    };
    default {
        "Module Athena non chargé (souvent BattlEye). Désactivez BattlEye dans le lanceur, puis relancez Arma."
    };
};

if (!_forLog) exitWith { _short };

private _log = _short + " Dans le journal .rpt : « ressources système insuffisantes » = BattlEye qui refuse COMSPECExtension (ACE passe car whitelisté). DLL attendue : COMSPECExtension_x64.dll (~5 Mo) à la racine du @COMSPECOverwatch réellement chargé (ex. !Workshop\\@COMSPECOverwatch). Un fichier ~32 Ko serait un stub managé — ce n’est PAS détecté ici, seulement une réponse vide.";
if (_err isEqualTo 501) then {
    _log = _log + format [" Code Arma 501 (extension introuvable)."];
};
_log
