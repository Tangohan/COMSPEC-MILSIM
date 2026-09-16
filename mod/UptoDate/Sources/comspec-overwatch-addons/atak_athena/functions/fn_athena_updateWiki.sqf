/*
    Remplit le corps Tutoriel / WIKI (liaison, connecté, sync, dépannage).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Wiki_group", controlNull];
if (isNull _group) exitWith {};

private _body = _group controlsGroupCtrl 9881;
if (isNull _body) exitWith {};

private _html = [
    "<t color='#7CFF9A' size='1.05'>Trois idées à ne pas confondre</t><br/><br/>",
    "<t color='#FFE08A'>Liaison (appareil / canal)</t><br/>",
    "<t color='#E8F2FA'>Le téléphone parle au poste. Sur le portail vous apparaissez « en liaison ». La barre OK / NOK parle surtout de ce canal (sync, fiabilité, perte).</t><br/><br/>",
    "<t color='#FFE08A'>Connecté (compte Athena en jeu)</t><br/>",
    "<t color='#E8F2FA'>La session Athena a chargé votre fiche : prénom, nom, indicatif. Sans ça, le jeu n’est pas vraiment connecté au compte — même si la liaison technique marche.</t><br/><br/>",
    "<t color='#FFE08A'>Synchronisation</t><br/>",
    "<t color='#E8F2FA'>Échange des données (position, messages, ordres…). La position peut remonter dès qu’il y a liaison ; la fiche complète exige le compte connecté.</t><br/><br/>",
    "<t color='#7CFF9A' size='1.05'>En liaison sur le portail, « non connecté » en jeu ?</t><br/>",
    "<t color='#E8F2FA'>C’est possible. Le poste voit votre appareil (indicatif TA1…), mais le jeu n’a pas ouvert la session compte. Le bandeau doit alors dire « En liaison — compte à ouvrir », pas un simple OK trompeur.</t><br/><br/>",
    "<t color='#FF8A80' size='1.05'>Dépannage — pseudo à la place du nom</t><br/>",
    "<t color='#E8F2FA'>Si vous voyez le pseudo de jeu (ex. NewPI) au lieu du prénom et du nom Athena : compte non connecté en jeu. Appuyez sur Entrer, ou Appairer / e-mail / Steam. Steam n’est pas obligatoire. Quand c’est bon, le vrai nom apparaît et le bandeau passe à Connecté.</t>"
] joinString "";

[_body, _html] call comspec_overwatch_connect_fnc_setPlainText;
_body ctrlShow true;
_body ctrlCommit 0;
