/*
    Message actionnable quand l’extension ne répond pas (réponse vide / non chargée).
    Ne présume PAS d’un stub ~32 Ko : avec une DLL Native AOT correcte (~5 Mo), la cause
    réelle la plus fréquente est le blocage BattlEye
    (RPT : « Insufficient system resources » / « insufficient system resources »).
    Params optionnels: [_context] ("link" | "connect" | "generic"), [_forLog] (bool)
*/
params [["_context", "generic"], ["_forLog", false]];

private _err = missionNamespace getVariable ["COMSPEC_LastExtError", 0];

private _short = switch (_context) do {
    case "link";
    case "connect": {
        "Athena module blocked (often BattlEye). Disable BattlEye in launcher, then restart Arma completely."
    };
    default {
        "Athena module not loaded (often BattlEye). Disable BattlEye in launcher, then restart Arma."
    };
};

if (!_forLog) exitWith { _short };

private _log = _short + " Dans le journal .rpt : « insufficient system resources » = BattlEye qui refuse COMSPECExtension (ACE passe car whitelisté). DLL attendue : COMSPECExtension_x64.dll (~5 Mo) à la racine du @COMSPECOverwatch réellement chargé (ex. !Workshop\\@COMSPECOverwatch). Un fichier ~32 Ko serait un stub managé — ce n’est PAS détecté ici, seulement une réponse vide.";
if (_err isEqualTo 501) then {
    _log = _log + format [" Code Arma 501 (extension introuvable)."];
};
_log
