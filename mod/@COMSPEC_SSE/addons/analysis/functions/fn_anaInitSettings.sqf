/*
    Réglages CBA de l'analyse (COMSPEC SSE › Organisations et scellés) : personnalisation
    des rôles, natures, noms de code, découvertes et numérotation.
    Tous globaux : le serveur décide, les postes affichent les mêmes libellés.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["COMSPEC SSE", "Organisations et scellés"];
private _add = {
    params ["_var", "_type", "_label", "_value"];
    [_var, _type, _label, _cat, _value, 1, {}, false] call comspec_sse_fnc_addSetting;
};
["comspec_sse_ana_extraRoles", "EDITBOX", ["Rôles en plus", "Rôles propres à votre scénario, séparés par des virgules, au format code:Nom:rang (0 = chef, 5 = simple contact). Exemple : imam:Imam:1, passeur:Passeur:4, cuisinier:Cuisinier:5"], ""] call _add;
["comspec_sse_ana_extraKinds", "EDITBOX", ["Types d'organisation en plus", "Séparés par des virgules, au format code:Nom. Exemple : tribu:Tribu, cartel:Cartel, ong:ONG de façade"], ""] call _add;
["comspec_sse_ana_codenames", "EDITBOX", ["Noms de code des cellules", "Noms séparés par des virgules. Vide : liste fournie (Faucon, Scorpion, Cèdre...)."], ""] call _add;
["comspec_sse_ana_contactsPerExploit", "SLIDER", ["Contacts révélés par exploitation", "Combien de membres de sa cellule deviennent suspects quand on exploite une personne (son téléphone, son carnet)."], [0, 6, 2, 0]] call _add;
["comspec_sse_ana_bioConfirms", "CHECKBOX", ["La biométrie prouve l'appartenance", "Coché : une identification biométrique confirme qu'une personne fait partie d'une organisation. Décoché : elle la rend seulement suspecte ; la confirmation vient de Zeus ou de l'exploitation."], true] call _add;
["comspec_sse_ana_piecePrefix", "EDITBOX", ["Préfixe des pièces", "Les pièces à conviction sont numérotées PRÉFIXE-DOSSIER-0001."], "P"] call _add;
["comspec_sse_ana_sealPrefix", "EDITBOX", ["Préfixe des scellés", "Les scellés sont numérotés PRÉFIXE-000000."], "SC"] call _add;
true
