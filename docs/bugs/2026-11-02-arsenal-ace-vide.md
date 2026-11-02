# Arsenal ACE : liste de gauche réduite à l'équipement porté

## Contexte
Pack FN, ATAK natif 2.9.31, capture d'un joueur (02/11, 15:07) : arsenal ACE ouvert par l'armurier de la mission
(`[player, player, true] call ace_arsenal_fnc_openBox`, arsenal complet), ACEAX et EFAK chargés.

## Symptôme
Pour chaque catégorie, la liste de gauche ne montre que « <Vide> » et l'objet porté (ici « [TFJ] CAG HK416D 10.4 SMR »).

## Cause
Non trouvée dans les mods COMSPEC :
- ni l'ATAK natif, ni Overwatch connect, ni le SSE ne lisent ou n'écrivent `ace_arsenal_virtualItems`, n'appellent
  `initBox`, `addVirtualItems`, `removeVirtualItems` ou `openBox`, ni ne touchent aux réglages d'arsenal d'ACE ;
- configuration : seuls des objets COMSPEC sont ajoutés (scope 2), aucune classe de base ni `ace_arsenal_hide` ;
- le panneau Tenues Athena ne fait que créer ses propres contrôles et lire ceux d'ACE ;
- RPT de la session : aucune erreur de script liée à l'arsenal, `preStart` d'ACE complet.
ACE affiche exactement ce symptôme quand sa liste d'objets est vide ou en mode « Objets : Favoris » (bouton du bas,
`fnc_addListBoxItem` ne garde alors que les objets portés et les favoris). ACEAX (`aceax_arsenal`) remplace la liste à
chaque ouverture (« Use filteredVirtualItems ») et EFAK (mis à jour dans le pack FN le 02/11 à 05:00) modifie l'écran
de l'arsenal : à vérifier sans eux.

## Correctif
Diagnostic seulement (2.9.33) : ligne `[COMSPEC ATAK NATIVE][INFO][ARSENAL]` 1 s après l'ouverture (lignes de la liste,
objets reçus par ACE, arsenal complet, mode Favoris, boîte). Rien n'est modifié.

## Fichiers touchés
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/XEH_postInitClient.sqf`

## Vérification
Rouvrir l'arsenal concerné, relever la ligne `[ARSENAL]` et le texte du bouton « Objets : Tous / Favoris ».
Objets reçus à 0 : liste vide fournie à ACE (mission, ACEAX). Mode Favoris vrai : cliquer le bouton.

## Statut
identifié en partie, diagnostic livré (2.9.33)
