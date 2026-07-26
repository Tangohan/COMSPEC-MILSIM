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
        "Liaison Athena bloquée (souvent BattlEye). Désactivez BattlEye dans le lanceur Arma, puis redémarrez complètement le jeu."
    };
    default {
        "Module Athena non chargé (souvent BattlEye). Désactivez BattlEye dans le lanceur Arma, puis redémarrez le jeu."
    };
};

if (!_forLog) exitWith { _short };

private _log = _short + " Journal technique : « insufficient system resources » = BattlEye qui refuse COMSPECExtension. DLL attendue (~5 Mo) à la racine du @COMSPECOverwatch réellement chargé.";
if (_err isEqualTo 501) then {
    _log = _log + " Extension introuvable côté Arma.";
};
_log
