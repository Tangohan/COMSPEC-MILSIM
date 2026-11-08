/*
    Diapositive téléchargée pendant que la page Briefing l'attend (COMSPEC_ATAK_SlideWait, fn_pageBriefing) : le résultat
    est posé à la place de « Chargement », sans redessiner la page.
      image lisible par Arma : posée dans l'image déjà en place ;
      « WEB:<clé> » (Arma refuse les images hors PBO sans -filePatching) : navigateur intégré créé à la même place (fn_slideWebShow) ;
      échec : raison affichée à la place du message de chargement.
    Page occupée par autre chose (autre diapositive, autre onglet) : rien à faire.
    Params : [clé de la diapositive, résultat (chemin, « WEB:<clé> » ou ""), raison de l'échec]
    Retour : false si la page doit être redessinée (attente perdue, navigateur indisponible).
*/
params [["_key", ""], ["_res", ""], ["_why", ""]];
disableSerialization;
(uiNamespace getVariable ["COMSPEC_ATAK_SlideWait", ["", controlNull, controlNull]]) params ["_wKey", "_wPic", "_wMsg"];
if (_wKey isNotEqualTo _key) exitWith { true };
if (isNull _wPic || {isNull _wMsg}) exitWith { false };
private _done = { uiNamespace setVariable ["COMSPEC_ATAK_SlideWait", ["", controlNull, controlNull]]; true };
if (_res isEqualTo "") exitWith {
    if (_why isEqualTo "") exitWith { false };
    _wMsg ctrlSetStructuredText parseText ((format ["<t align='center' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1<br/>Touchez ACTUALISER pour réessayer.</t>", _why]) call comspec_atak_native_fnc_tr);
    call _done
};
if ((_res select [0, 4]) isNotEqualTo "WEB:") exitWith {
    _wPic ctrlSetText _res; // i18n:brut
    _wMsg ctrlShow false;
    call _done
};
((uiNamespace getVariable ["COMSPEC_ATAK_SlideWeb", createHashMap]) getOrDefault [_res select [4], ["jpeg", ""]]) params ["_mime", "_b64"];
if (_b64 isEqualTo "") exitWith { false };
private _wb = ["COMSPEC_RscWebBrowser", ctrlPosition _wPic] call comspec_atak_native_fnc_pageCtrl;
if (isNull _wb) exitWith { false };
_wMsg ctrlShow false;
[_wb, _mime, _b64] call comspec_atak_native_fnc_slideWebShow;
call _done
