# Tableau des liens

Version anglaise : [tableau_des_liens.en.md](tableau_des_liens.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Le **tableau des liens** montre ce que l'équipe sait des personnes, des organisations, des pièces, des appareils, des documents et des lieux, et comment tout cela se relie. Il se remplit au fil de la mission : contrôler ou enrôler une personne l'y fait entrer, une identification biométrique confirme son appartenance, exploiter un téléphone ou un carnet fait soupçonner des membres de sa cellule, exploiter un objet rattaché à une organisation la révèle. Le Zeus a une **vue Zeus** qui montre tout, l'inconnu des joueurs en gris, et peut **révéler** un nœud.

Ne pas confondre avec le **graphe de renseignement** de l'ancien terminal (écran GRAPHE, « Nœuds et relations établies sur le terrain », bouton PIVOT) : c'est la vue d'un seul record lié ; le tableau des liens est la vue complète.

## Où le trouver

| Contexte | Chemin |
|---|---|
| Joueur | Téléphone ATAK › Renseignement › app **SSE terrain** › onglet **SITE** › bouton **Tableau des liens** (le téléphone se range, le tableau s'ouvre) |
| Zeus | ZEN › **COMSPEC · Renseignement (SSE) › Ouvrir le tableau des liens** (vue Zeus) |
| Script | `[false] call comspec_sse_fnc_chartOpen` (joueur) · `[true]` (vue Zeus) |

Il n'y a plus de menu ACE personnel « COMSPEC SSE » ni de raccourci clavier pour le tableau.

## La fenêtre

Titre **TABLEAU DES LIENS** (en vue Zeus : **TABLEAU DES LIENS — VUE ZEUS** sur fond orangé), marque « COMSPEC SSE · ANALYSE », panneau latéral **DÉTAIL**.

| Bouton | Bulle d'aide / effet |
|---|---|
| **GRAPHE** | « Personnes, organisations, objets et données reliés » : chaque organisation au centre de ses cellules et membres, chaque objet autour de son détenteur, les lieux mentionnés |
| **ORBAT** | « Organigramme : organisations, cellules, membres par rôle » : organisation en haut, cellules dessous, membres du chef au sympathisant |
| **TOUT · PERSONNES · ORGANISATIONS · OBJETS ET DONNÉES** | Filtres (« Personnes et organisations », « Pièces, appareils, documents, lieux et qui y touche ») |
| **CONFIRMÉ SEUL** | « Masque ce qui n'est que soupçonné » |
| **RECADRER** | Recentre le canevas |
| **RÉVÉLER** (vue Zeus) | « Zeus : rend le nœud choisi connu des joueurs (confirmé) » ; motif inscrit : « renseignement transmis par le PC ». Seules les personnes et organisations se révèlent |
| **FERMER** | Ferme |

Canevas : **clic** = fiche du nœud dans DÉTAIL, **double-clic** = zoom, **molette** = échelle. Légende : organisation (couleur = menace : faible vert, moyenne jaune, élevée orange, critique rouge) · personne (blanc bleuté) · document · appareil (bleu clair) · lieu (violet) ; **plein = confirmé, « ? » = soupçonné**, en Zeus **gris = inconnu des joueurs**.

Fiche **DÉTAIL** : Alias, Appartenance, Capture (sac sur la tête, vivant / décédé), Effets saisis, Membres identifiés, Menace, Nature, Organisation, Position, Rattachée à, Repérée par, Rôle, Source (et heure de la découverte), LIENS (propriétaire, associé, contact, lieu, membre, famille, finance, fournit, utilise, dirige, communications, possède). Vide : « Choisissez un nœud sur le tableau. Le tableau se remplit au fil de la mission… »

Le tableau se met à jour tout seul (événement `comspec_sse_anaChanged`, regroupé sur 0,5 s).

## Pas à pas : reconstituer une cellule

Situation : après l'assaut, l'équipe a un prisonnier, un corps, un téléphone et un carnet.

1. SEEK II sur le prisonnier › IDENTIFIER : **MATCH** ; il entre au tableau avec son appartenance **confirmée** (plein).
2. ACE sur le téléphone › SSE › **Fouiller**, puis app SSE terrain › CIBLE › **Approfondir** : deux « contact de… » apparaissent en **soupçonné** (« ? »).
3. Ouvrir **SSE terrain › SITE › Tableau des liens** : **ORBAT** montre l'organisation, sa cellule, le prisonnier (rôle) et les deux contacts.
4. Clic sur un contact : DÉTAIL › Source : « exploité, téléphone de … ». Il reste à le contrôler ou l'enrôler pour confirmer.
5. Au débriefing, le Zeus ouvre la **vue Zeus** : ce que les joueurs ont manqué apparaît en gris ; **RÉVÉLER** sur le chef simule un renseignement du PC.

## Pièges fréquents

- **Vide au départ, c'est normal** : contrôlez, identifiez, exploitez.
- Un nœud **soupçonné** n'est pas une preuve : **CONFIRMÉ SEUL** pour ne garder que l'établi.
- **Overwatch Connect** chargé : les écrans du terminal s'ouvrent dans l'hôte Overwatch, pas le tableau (qui reste le même).
- Le **graphe** du terminal (GRAPHE / PIVOT : « Aucun record pour pivot. », « Pivot : n entité(s) liée(s) ») ne montre que le record lié.
- Les objets rattachés sont mis en cache 60 s : un rattachement Zeus très récent peut demander de fermer et rouvrir.
- Rien ne part vers Athena depuis le tableau : l'envoi se fait **par fiche** (TRANSMETTRE / Transmettre à Athena) ; le Zeus peut **EXPORTER LE GRAPHE** (écran Contrôle Zeus SSE) et ouvrir le compte rendu **AAR**.
- Réglages (COMSPEC SSE › Organisations et scellés) : Contacts révélés par exploitation (2), La biométrie prouve l'appartenance (oui), Rôles en plus, Types d'organisation en plus, Noms de code des cellules.

## Athena

- Les fiches transmises (personne, biométrie, pièces) remplissent l'affaire du Bureau SSE ; l'ORBAT des organisations avec ce que les joueurs savent est lisible dans **Bureau SSE › Campagnes** quand la campagne est copiée dans Athena.
- Le module Zeus **Ouvrir le compte rendu (AAR)** avec « Exporter le graphe » produit le graphe de mission (`comspec_sse_mission_graph`) joint au compte rendu.
