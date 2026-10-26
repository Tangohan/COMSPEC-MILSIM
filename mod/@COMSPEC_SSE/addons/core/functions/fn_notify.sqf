/*
    Retour SSE à l'opérateur : notification du téléphone ATAK natif (centre de notifications, toast si le téléphone est
    affiché). Règle : rien ne s'affiche par-dessus l'écran de jeu. Téléphone fermé ou rangé, la notification reste
    historisée dans le centre de notifications de l'ATAK (lue à l'ouverture) ; sans ATAK natif, rien n'est affiché.
    Le texte est écrit en français dans le code : traduit au rendu par l'ATAK (ligne par ligne ici s'il y en a plusieurs).
    [_text, _type, _title, _app] call comspec_sse_fnc_notify
      _text  : texte brut ; retour à la ligne "\n" (comme hint), endl ou <br/>
      _type  : INFO (défaut) | SUCCESS | WARNING | ERROR | TACTICAL
      _title : ignoré (ancienne bulle à l'écran, supprimée) ; gardé pour les appelants existants
      _app   : page de l'ATAK ouverte au clic sur la notification (défaut « SSE »)
    Renvoie true si la notification a été remise à l'ATAK natif.
*/
params [["_text", "", [""]], ["_type", "INFO", [""]], ["_title", "COMSPEC SSE", [""]], ["_app", "SSE", [""]]];
if (!hasInterface || {_text isEqualTo ""}) exitWith { false };
_type = toUpper _type;

// Lignes en français, sans vide : la notification les joint par « · ».
private _lines = ((_text regexReplace ["\\n|<br ?/?>", endl]) splitString (toString [13, 10])) apply { trim _x };
_lines = _lines select { _x isNotEqualTo "" };
if (_lines isEqualTo []) exitWith { false };

// Téléphone ATAK natif : historisé dans le centre de notifications (traduit au rendu), toast s'il est ouvert.
private _stored = false;
if (!isNil "comspec_atak_native_fnc_notify") then {
    // Plusieurs lignes : chacune traduite ici (le rendu ne traduit le texte joint que d'un bloc, sans correspondance).
    private _joined = if ((count _lines) > 1) then { (_lines apply { _x call comspec_sse_fnc_tr }) joinString " · " } else { _lines select 0 };
    [_type, _joined, 6, [20, 40] select (_type in ["WARNING", "ERROR", "TACTICAL"]), _app] call comspec_atak_native_fnc_notify;
    _stored = true;
};
// Téléphone fermé, rangé ou absent : aucune bulle à l'écran (règle « tout dans l'ATAK ») ; la notification attend
// dans le centre de notifications du téléphone.
_stored
