# IA de la zone (atelier IA)

Version anglaise : [ia_de_la_zone.en.md](ia_de_la_zone.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

La page **IA DE LA ZONE** de l'éditeur SSE règle, pour les IA proches ou sélectionnées, **qui elles sont au contrôle d'identité** : leur profil Athena, les papiers qu'elles portent, la nature de ces papiers (vrais, couverture, faux), leur comportement quand on les contrôle, leur camp. Elle sert aussi à inspecter et corriger les papiers d'une IA, et à **garder** une configuration pour les prochaines parties sur la même carte.

Sans elle, les IA reçoivent des papiers « selon leur camp » (réglages CBA Identité : civils qui présentent leurs papiers, insurgés porteurs de faux, IA sans papiers) et réagissent selon le contexte.

## Où la trouver

| Contexte | Chemin |
|---|---|
| Zeus avec ZEN | **COMSPEC · SSE › Configurer une IA** (sur une IA ou avec une sélection) |
| Zeus sans ZEN | Module **Éditeur SSE** posé sur une IA vivante, ou avec des IA sélectionnées |
| Depuis l'éditeur | Bouton **IA DE LA ZONE** en haut de la fenêtre |
| Automatique | Réglage CBA **Configurer une IA à sa pose (Zeus)** (COMSPEC SSE › Identité, non par défaut) : la page s'ouvre dès qu'un Zeus pose une IA |
| Script | `[unité ou liste, position] call comspec_sse_fnc_aiConfigOpen` |

Pas de page IA dans Eden (le bouton est masqué) : réglez les IA d'Eden par leurs attributs **COMSPEC SSE — Papiers** et **Profil au contrôle**.

## La fenêtre

### Colonne gauche

- **DÉTECTER** : rayon **25 / 50 / 100 / 250 / 500 m** (50 m par défaut), boutons **AUTOUR** (IA autour de la position du module) et **SÉLECTION ZEUS** (IA sélectionnées dans Zeus), compteur « N IA ». Au plus **60 IA** par balayage.
- **Liste des IA** : nom, camp, profil (« généré » ou la référence Athena), papiers (« à la demande », « aucun » ou leur nombre), groupe. Cliquez une ligne pour la régler.
- **SES PAPIERS** : les papiers de l'IA choisie, au format « Type · n° X · Prénom NOM · Vrai/Faux ». Boutons **OUVRIR** (visionneuse), **MARQUER VRAI**, **MARQUER FAUX** (même nom, qualité 50), puis une liste de type (Carte d'identité, Passeport, Permis de conduire, Carte militaire et les types des extensions) avec **AJOUTER CE PAPIER**.
- **GARDÉS POUR LES PROCHAINES PARTIES** : les configurations gardées sur Athena pour cette carte ; **RETIRER**, **RECHARGER**.

### Colonne droite « CONFIGURATION »

| Champ | Valeurs | Exemple |
|---|---|---|
| **Profil Athena** | **Générer une identité**, puis les profils du Bureau SSE (« réf · libellé (nom) ») | « P-014 · Passeur (Yannis Petrakis) » : l'IA prend ce nom, cette légende et ces papiers ; un contrôle N3 la reconnaît sur Athena |
| **Papiers** | Selon son camp · Aucun · Carte d'identité · Passeport · Permis de conduire · Carte et permis · Carte et passeport · Carte militaire | « Carte et permis » pour un chauffeur |
| **Nature** | Vrais papiers · Couverture · Faux papiers | « Faux papiers » pour un insurgé qui se fait passer pour un civil |
| **Comportement au contrôle** | Selon le contexte · Coopère · Nerveux · Ment avec aplomb · Refuse · S'enfuit · Devient agressif · Se rend | « Ment avec aplomb » pour un agent entraîné ; « S'enfuit » pour un guetteur |
| **Camp** | Ne pas changer · Civils · BLUFOR · OPFOR · Indépendants | Un changement de camp crée un nouveau groupe |

**CE QUE VERRA LE JOUEUR** : aperçu du nom, des papiers et de la réaction « Au contrôle ». Rappel : « Il répond au-dessus de sa tête. Un contrôle N3 le reconnaît sur Athena si ses papiers viennent du profil. »

Bouton **GARDER POUR LES PROCHAINES PARTIES : NON/OUI**, puis **APPLIQUER** (l'IA choisie), **À SON GROUPE**, **À TOUTE LA LISTE**.

## Pas à pas : un marché avec un insurgé caché

Situation : dix civils sur un marché, dont un guetteur insurgé avec de faux papiers ; l'équipe doit le trouver par les contrôles.

1. Zeus › **Configurer une IA** sur un civil du marché ; DÉTECTER 50 m › **AUTOUR** : « 10 IA ».
2. Sans rien sélectionner de particulier : Papiers **Carte d'identité**, Nature **Vrais papiers**, Comportement **Selon le contexte** › **À TOUTE LA LISTE**.
3. Cliquez le guetteur dans la liste : Nature **Faux papiers**, Comportement **Nerveux**, Camp **Ne pas changer** › **APPLIQUER**.
4. Dans **SES PAPIERS**, **OUVRIR** sa carte pour voir le nom imprimé ; **MARQUER FAUX** si besoin de refaire le faux.
5. Pour rejouer la scène la semaine prochaine : **GARDER POUR LES PROCHAINES PARTIES : OUI** avant APPLIQUER (IA nommées dans Eden de préférence).

Au contrôle, le guetteur hésite, regarde autour de lui ; l'examen visuel (N0) ou la MRZ (N1) révèle le faux selon sa qualité.

## Pièges fréquents

- **SÉLECTION ZEUS** : il faut fermer la fenêtre, sélectionner les IA dans Zeus, puis rouvrir.
- Les papiers d'une IA ne sont **fabriqués qu'à la première demande** (fouille, demande, mort) : la colonne « papiers » affiche « à la demande » tant que personne ne l'a contrôlée.
- **APPLIQUER efface les anciens papiers** de l'IA et pose les nouveaux.
- **Garder** exige la liaison Athena et l'habilitation Confidentiel ; sinon « Enregistrement sur Athena impossible … rien n'est gardé. » Les IA posées au Zeus sans nom de variable ne sont pas retrouvées à la partie suivante (retrouvées par nom de variable Eden, sinon par la même classe à moins de 20 m) : « Éléments gardés non retrouvés : … ».
- « Aucun profil Athena reçu sur ce serveur : créez-en dans le Bureau SSE (Profils IA), ou générez. »
- Tout passe par le serveur (`fn_idAiConfig`) : un client seul ne peut rien changer.
- Il n'y a **aucun curseur** sur cette page : les pourcentages (IA sans papiers, civils qui présentent, insurgés porteurs de faux) sont des réglages CBA.

## Athena

- Les **profils IA** (Bureau SSE › Profils IA) se choisissent dans « Profil Athena » ; une IA reliée à un profil est reconnue au contrôle N3 avec sa fiche Athena.
- **Garder pour les prochaines parties** enregistre la configuration (communauté + carte) sur Athena ; le serveur la réapplique au démarrage de la mission.
- Les identités préparées sur Athena pour les IA (camp, catégorie, zone) sont remises à leur premier contrôle (voir [controle_identite.md](controle_identite.md)).
