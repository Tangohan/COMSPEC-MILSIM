# Preuves, scellés et renseignement

Version anglaise : [preuves.en.md](preuves.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Une preuve n'a de valeur que si l'on sait qui l'a eue entre les mains, quand et où. Le SSE tient ce registre : **collecter et mettre sous scellé** une pièce lui donne un numéro (P-dossier-0001) et un scellé (SC-000000) ; chaque geste (SAISIE, COLLECTE, MISE SOUS SCELLÉ, OUVERTURE DU SCELLÉ, EXPLOITATION, ANALYSE, REMISE, PHOTOGRAPHIE) s'inscrit dans un **journal infalsifiable** (chaque ligne porte l'empreinte de la précédente). Les **documents, téléphones, radios et ordinateurs** trouvés sur un site se lisent et s'exploitent par paliers (NONE → TACTICAL → FIELD → DETAILED → FUSION) et nourrissent le tableau des liens et Athena.

## Où le trouver

| Besoin | Chemin |
|---|---|
| Sur un objet SSE | Menu ACE › **SSE** › **Fouiller** (fouille complète, 15 s), **Mettre sous scellé** (un sachet consommé), **Relever des empreintes latentes**, **Mettre en sac de Faraday** (4 s, objet avec données), **Poser un cône de preuve** (3 s) |
| Sur un équipier | Menu ACE › **Remettre mes pièces scellées** (5 s, émargement ; inventaire plein : sachets posés à ses pieds) |
| Lire, exploiter, photographier | Téléphone ATAK › **SSE terrain** › onglet **CIBLE** (objet visé à 6 m) : **Photo de preuve**, **Approfondir**, **Documents** (6 s), **Chronologie**, **Exploiter la radio** (10 s), **Brancher le DL-7**, **Transmettre à Athena**, **Fiche FRS**, **Marquer exploité** |
| Registre des scellés | SSE terrain › onglet **SCELLÉS** (« Pièces que je détiens ») › **Registre des scellés** (écran « PREUVES — CHAÎNE DE CONSERVATION », bouton METTRE SOUS SCELLÉ) |
| Avancement du site | SSE terrain › onglet **SITE** : « Avancement du site », « EXFILTRATION : SSE INCOMPLET (x %) » / « FEU VERT », **Triage du site**, **Tableau des liens** |
| Matériel | « Sachet à scellés », « Gants d'examen », « Appareil photo de preuve », « Kit empreintes », « Kit ADN », « Écouvillons ADN / résidus de tir », « Sac à documents », « Sac de Faraday », « Cônes de preuve numérotés » ; caisses **Caisse kit SSE d'équipe**, **Caisse kit exploitation numérique**, **Valise DOMEX de terrain** (accélère le DL-7 à 10 m) |

Les objets et véhicules n'ont le menu SSE que s'ils sont **marqués SSE** (attribut Eden « Activer le renseignement SSE », module **Rendre exploitable**, site généré) ; les personnes l'ont toujours.

## Le cycle d'une pièce

1. **Photo de preuve** avant de toucher (app Photo de l'ATAK, entités cadrées à 15 m ; repli : bouton Photo de preuve de SSE terrain) : la pièce est marquée « en place », la qualité de collecte monte.
2. **Fouiller** (barre « Fouille SSE complète… ») : génération du contenu, qualité (70 ou 45 de base + compétence, 95 au plus), notification des lignes trouvées, fenêtre « DOSSIER SSE » (boutons FEUILLE, TRANSMETTRE, FERMER).
3. **Mettre sous scellé** (barre « Collecte SSE… ») : pièce P-…, scellé SC-…, vous devenez détenteur. Notification « Preuve collectée · Par · Heure · Grid · Qualité ».
4. **Approfondir** (SSE terrain › CIBLE) fait monter le palier d'exploitation ; exploiter une pièce scellée brise le scellé (inscrit) : rescellez (nouveau numéro).
5. **Remettre mes pièces scellées** au chef d'équipe : changement de détenteur émargé.
6. **Transmettre à Athena** : « Fiche envoyée vers Athena (file d'attente si hors liaison) ».

## Pas à pas : un ordinateur portable dans une planque

1. Visez l'ordinateur, SSE terrain › CIBLE › **Photo de preuve**.
2. Menu ACE › SSE › **Fouiller** (15 s) : « Exploitation : niveau TACTICAL », des fichiers et un courriel apparaissent.
3. **Mettre sous scellé** (un sachet) : P-SSE-2026-0007-0003, scellé SC-000112.
4. SSE terrain › CIBLE › **Approfondir** (palier FIELD, puis DETAILED) : contacts, lieux ; le tableau des liens gagne des nœuds soupçonnés.
5. Avec l'ATAK natif et un câble DL-7 : **Brancher le DL-7** (écran de connexion, mot de passe ou FORCER L'ACCÈS, transfert) ; une **Valise DOMEX** à 10 m accélère.
6. **Transmettre à Athena**, puis **Remettre mes pièces scellées** au chef.

## Pièges fréquents

- **Une action SSE à la fois** (« Une action SSE est déjà en cours. ») ; elle s'interrompt si vous montez en véhicule, vous éloignez de plus de 4 m ou si la cible disparaît. Un kit n'est décompté qu'en cas de succès.
- **Sachet obligatoire** pour sceller : « Sachet de preuve SSE (ou kit médical ACE compatible) requis. » ; « Pièce déjà collectée par un équipier. », « Pièce déjà sous scellé (…) ».
- **Sur une personne, la fouille est toujours rapide** (5 s) ; la fouille complète (15 s) est pour les objets et véhicules (cabine et soute).
- **Exploiter une pièce scellée brise le scellé** : c'est inscrit ; rescellez.
- Un **journal altéré** apparaît en rouge au registre avec la ligne fautive (module Zeus **Altérer un scellé (exercice)** pour s'entraîner).
- **Overwatch Connect** chargé : les écrans (terminal, registre…) s'ouvrent dans son hôte.
- « Exploitation déjà au niveau Fusion. » : rien de plus à tirer ; « Aucun document exploitable. » / « Aucune donnée radio exploitable. ».
- Réglages : Durées (fouille rapide 5 s, complète 15 s, empreintes 8 s, téléphone 15-60 s, ordinateur 30-120 s), Matériel SSE obligatoire, Accepter le matériel d'autres mods, Préfixe des pièces (P) et des scellés (SC), Intel (Niveau de l'opérateur SSE, Tri automatique après une fouille, Recoupement automatique).

## Athena

- **Transmettre à Athena** (SSE terrain, terminal, SEEK, fenêtre DOSSIER SSE) envoie la fiche avec le journal de la pièce vers l'affaire (**Référence du dossier SSE**, réglage ou module **Fixer la référence du dossier**).
- Le registre des scellés d'une campagne copiée dans Athena est lisible dans Bureau SSE › Campagnes.
- Photos de preuve : jointes aux fiches (4 au plus par FRM).
