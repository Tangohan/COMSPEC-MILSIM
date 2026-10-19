/*
    Retour SSE à l'opérateur : notification du téléphone ATAK natif (centre de notifications, toast si le téléphone est
    affiché). Le toast de l'ATAK ne s'affiche que téléphone ouvert (fn_notificationsRender) : téléphone fermé, rangé
    ou absent, une bulle d'aide discrète (hintSilent) porte le même texte, sinon le joueur ne voit rien.
    Le texte est écrit en français dans le code : traduit ici (avant la mise en forme), échappé, puis affiché.
    [_text, _type, _title, _app] call comspec_sse_fnc_notify
      _text  : texte brut ; retour à la ligne "\n" (comme hint), endl ou <br/>
      _type  : INFO (défaut) | SUCCESS | WARNING | ERROR | TACTICAL
      _title : titre de la bulle (défaut « COMSPEC SSE »)
      _app   : page de l'ATAK ouverte au clic sur la notification (défaut « SSE »)
    Renvoie true si quelque chose a été affiché ou historisé.
*/
params [["_text", "", [""]], ["_type", "INFO", [""]], ["_title", "COMSPEC SSE", [""]], ["_app", "SSE", [""]]];
if (!hasInterface || {_text isEqualTo ""}) exitWith { false };
_type = toUpper _type;

// Lignes en français, sans vide : la notification les joint par « · », la bulle par des retours à la ligne.
private _lines = ((_text regexReplace ["\\n|<br ?/?>", endl]) splitString (toString [13, 10])) apply { trim _x };
_lines = _lines select { _x isNotEqualTo "" };
if (_lines isEqualTo []) exitWith { false };

// Téléphone ATAK natif : historisé dans le centre de notifications (traduit au rendu), toast s'il est ouvert.
private _phoneOpen = false;
if (!isNil "comspec_atak_native_fnc_notify") then {
    [_type, _lines joinString " · ", 6, [20, 40] select (_type in ["WARNING", "ERROR", "TACTICAL"]), _app] call comspec_atak_native_fnc_notify;
    _phoneOpen = !isNull ([] call comspec_atak_native_fnc_display);
};
if (_phoneOpen) exitWith { true };

// Téléphone fermé ou absent : bulle d'aide. Traduction d'abord (fn_tr ne traduit qu'une chaîne), puis échappement
// des valeurs (nom, remarque contenant « & » ou « < »), puis texte structuré.
private _esc = {
    private _s = _this regexReplace ["&", "&amp;"];
    _s = _s regexReplace ["<", "&lt;"];
    _s regexReplace [">", "&gt;"]
};
private _color = switch (_type) do {
    case "ERROR": { "#E0574F" };
    case "WARNING";
    case "TACTICAL": { "#F2B84B" };
    default { "#7FD08A" };
};
hintSilent parseText format [
    "<t font='PuristaMedium' size='1.1' color='%1'>%2</t><br/><t size='0.95' align='left'>%3</t>",
    _color,
    ((_title call comspec_sse_fnc_tr) call _esc),
    (_lines apply { (_x call comspec_sse_fnc_tr) call _esc }) joinString "<br/>"
];
true
