/*
    Réglages CBA de l'analyse (COMSPEC SSE › Organisations et scellés) : personnalisation
    des rôles, natures, noms de code, découvertes et numérotation.
    Tous globaux : le serveur décide, les postes affichent les mêmes libellés.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["COMSPEC SSE", "Organisations et scellés"];
private _add = {
    params ["_var", "_type", "_label", "_value"];
    [_var, _type, _label, _cat, _value, 1, {}, false] call CBA_fnc_addSetting;
};
["comspec_sse_ana_extraRoles", "EDITBOX", ["Rôles en plus", "Rôles propres à votre univers, séparés par des virgules : identifiant:Libellé:rang (0 = sommet, 5 = périphérie). Exemple : imam:Imam:1, passeur:Passeur:4, cuisinier:Cuisinier:5"], ""] call _add;
["comspec_sse_ana_extraKinds", "EDITBOX", ["Natures d'organisation en plus", "Séparées par des virgules : identifiant:Libellé. Exemple : tribu:Tribu, cartel:Cartel, ong:ONG de façade"], ""] call _add;
["comspec_sse_ana_codenames", "EDITBOX", ["Noms de code des cellules", "Liste séparée par des virgules ; vide = liste intégrée (Faucon, Scorpion, Cèdre...)."], ""] call _add;
["comspec_sse_ana_contactsPerExploit", "SLIDER", ["Contacts révélés par exploitation", "Nombre de membres de la même cellule soupçonnés quand on exploite une personne (téléphone, carnet)."], [0, 6, 2, 0]] call _add;
["comspec_sse_ana_bioConfirms", "CHECKBOX", ["L'identification biométrique confirme l'appartenance", "Désactivé : une identification ne fait que soupçonner ; la confirmation vient du Zeus ou de l'exploitation."], true] call _add;
["comspec_sse_ana_piecePrefix", "EDITBOX", ["Préfixe des pièces", "Numéro de pièce : <préfixe>-<dossier>-0001."], "P"] call _add;
["comspec_sse_ana_sealPrefix", "EDITBOX", ["Préfixe des scellés", "Numéro de scellé : <préfixe>-000000."], "SC"] call _add;
true
