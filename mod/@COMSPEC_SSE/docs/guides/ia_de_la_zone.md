# IA de la zone

Version anglaise : [ia_de_la_zone.en.md](ia_de_la_zone.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

La fenêtre **IA de la zone** de l'éditeur SSE règle, pour les IA proches ou sélectionnées, **qui elles sont au contrôle d'identité** : profil Athena, papiers qu'elles portent et leur nature (vrais, couverture, faux), comportement quand on les contrôle, camp. Elle sert aussi à voir ses papiers tels que le joueur les verra, à les corriger, à écrire **ce qu'elle répond** quand on lui parle (**Questions et réponses…**) et à la garder en **mémoire de la carte** pour les prochaines parties.

Sans elle, les IA reçoivent des papiers « selon leur camp » (réglages CBA Identité) et répondent seules, selon leur caractère et leur couverture.

## Où la trouver

| Contexte | Chemin |
|---|---|
| Zeus (centre Zeus de l'ATAK) | **IA de la zone** sur une IA ou une sélection |
| Zeus avec ZEN | Clic droit sur une ou plusieurs IA › **SSE · Roleplay de l'IA › Identité et papiers › Fiche dans l'éditeur SSE (IA de la zone)…** |
| Zeus sans ZEN | Module **Éditeur SSE** posé sur une IA (tout son groupe est listé) |
| Script | `[unité ou liste, position] call comspec_sse_fnc_aiConfigOpen` |

Dans Eden, réglez les IA par leurs attributs (**COMSPEC SSE › Papiers**, **Profil au contrôle**, **Profil IA RP** et son bouton **Atelier IA… › Réponses et limites…**) : tout est enregistré dans la mission.

## La fenêtre, en trois étapes

La fenêtre se lit de gauche à droite : **1. choisir les IA**, **2. les régler**, **3. appliquer ou garder**. À la première ouverture, une visite guidée en trois bulles montre chaque zone (bouton **?** pour la revoir ; **Passer** la ferme pour cette fois, **Ne plus afficher** pour de bon). Rien ne change en jeu avant **Appliquer** : la ligne d'état en bas le rappelle (« Modifié, pas encore appliqué ») et dit ce qui s'est passé (« 3 IA mises à jour. »). Aucune alerte Arma.

### 1. Choisir les IA (à gauche)

- **Détecter autour** et son **rayon** (25 à 500 m) : les IA autour du module, de la caméra Zeus ou de vous. **Sélection Zeus** : les IA sélectionnées avant d'ouvrir. 60 IA au plus.
- **Chercher un nom…** (nom donné, vrai nom, unité, groupe, profil ; ex. « Vlachos », « Alpha ») et **Filtres** (camp, rôle, état : profil Athena, tirée au sort, papiers à venir, en mémoire ; **Tout afficher** les efface).
- La liste : case, **nom donné** (ce que disent ses papiers), rôle, icône **papiers** et son nombre, icône **profil Athena**. Couleur de l'icône papiers : vert vrais, rouge faux, orange il ment ou couverture, gris pas encore donnés (légende sous la liste). Survolez un nom : son **vrai nom** (vous seul). Un clic sur un en-tête trie la liste.
- Un clic coche ou décoche une IA. **Tout cocher**, **Décocher**, « N IA cochée(s) ». Liste vide : un panneau explique quoi faire, avec les deux boutons.

### 2. Régler (au milieu, un onglet par sujet)

En haut : le nom de l'IA, puis pour vous seul son vrai nom, sa vérité (« Fausse identité (ment) ») et son groupe. Plusieurs IA cochées : « Réglages appliqués aux N IA cochées ».

| Onglet | Réglages | Exemple |
|---|---|---|
| **Identité** | **D'où vient son identité** (tirée au sort, ou un profil Athena), **Authenticité de ses papiers** (vrais, couverture, faux ; avant : « Nature »), **Pays de ses papiers** (selon la mission, ou un pays). Volet **Importer depuis Athena** : nombre de profils et date du paquet, recherche, **Actualiser** | « Profil Athena : PRF-0012 · Vendeur » : l'IA prend ce nom, cette légende et ces papiers ; un contrôle N3 la reconnaît |
| **Papiers** | **Papiers qu'il portera** (selon son camp, aucun, carte, passeport, permis, carte militaire, types des extensions) ; **Papiers qu'il a sur lui** (papier et pays, numéro, badge **Vrai / Faux** ; avant : « Vérité ») ; **Voir en grand**, **Rendre faux** / **Rendre vrai**, un type et **Donner maintenant** | une carte volée : **Rendre faux** |
| **Comportement** | **Au contrôle, il…** (selon le contexte, coopère, nerveux, ment avec aplomb, refuse, s'enfuit, devient agressif, se rend) ; **Questions et réponses…** (fenêtre [Réponses de l'IA](reponses_ia.md)) | « Nerveux » : il hésite, répond à côté |
| **Camp** | **Son camp après « Appliquer »** (ne pas changer, civils, BLUFOR, OPFOR, indépendants) | un civil passé OPFOR devient hostile |

Chaque choix est expliqué sous sa case, en une phrase.

### Ce que verra le joueur (à droite)

Le papier choisi dans l'onglet Papiers, **dessiné comme dans la visionneuse du joueur** (gabarit, champs, MRZ, hologramme ; **Recto / verso**), avec sa légende (« Carte d'identité · République d'Altis · recto »). Sans papier : une phrase dit pourquoi (« Pas encore de papier… »). Dessous, **Au contrôle** : le nom sous lequel il se présente, ses papiers, sa réaction ; pour vous seul, la vérité ; et ce qui changera après **Appliquer**.

### 3. Appliquer ou garder (en bas)

- **Appliquer** (IA cochées ; un point « ● » quand des réglages attendent) et **Appliquer à tout le groupe** (tout le groupe de chaque IA cochée). La sélection reste après.
- **Mémoire de la carte…** (avant : « Gardés pour les prochaines parties ») ouvre un volet : **Garder les IA cochées** (applique, puis enregistre sur ce serveur pour cette carte, et sur Athena si la liaison est ouverte : remises en place au début de chaque partie), la liste **Déjà en mémoire**, **Recharger**, **Supprimer**.

## Pas à pas : un marché avec un guetteur

Dix civils sur un marché, dont un guetteur avec de faux papiers, et un vendeur qui connaît le mot de passe du réseau.

1. Zeus › **IA de la zone** sur un civil du marché ; rayon 50 m › **Détecter autour** : « 10 IA ».
2. **Tout cocher** ; onglet Papiers **Carte d'identité**, onglet Identité **Vrais papiers**, onglet Comportement **Selon le contexte** › **Appliquer**.
3. **Décocher**, cochez le guetteur : onglet Identité **Faux papiers**, onglet Comportement **Nerveux** › **Appliquer** (à droite, son papier et « Au contrôle » montrent le résultat).
4. Cochez le vendeur seul › onglet Comportement › **Questions et réponses…** : à « Qui es-tu ? », **Réponse perso** « Je suis {prenom}, je vends des fruits ici depuis vingt ans. » ; **Ajouter une question perso** : déclencheurs « mot de passe », réponse « Le faucon vole bas. » › **Appliquer**.
5. Pour rejouer la scène : cochez les deux › **Mémoire de la carte…** › **Garder les IA cochées** (IA nommées dans Eden de préférence). Les réponses écrites au point 4 sont gardées avec le vendeur.

## Pièges fréquents

- **Sélection Zeus** lit la sélection faite avant d'ouvrir la fenêtre.
- Les papiers d'une IA ne sont **fabriqués qu'à la première demande** : « à la demande » tant que personne ne l'a contrôlée.
- **Appliquer refait les papiers** des IA cochées.
- **Questions et réponses…**, **Rendre vrai / faux** et **Donner maintenant** agissent tout de suite, sans passer par Appliquer ; **Garder** emporte ensuite les réponses avec l'IA.
- **Mémoire de la carte** enregistre sur ce serveur, et sur Athena si la liaison et l'habilitation Confidentiel le permettent. Une IA sans nom de variable est retrouvée par sa classe à moins de 20 m, sinon : « IA en mémoire introuvable ».
- Tout passe par le serveur (Zeus ou administrateur seulement).

## Athena

- Les **profils IA** (Bureau SSE › Profils IA) se choisissent dans l'onglet Identité (« D'où vient son identité », volet « Importer depuis Athena ») ; une IA reliée à un profil est reconnue au contrôle N3.
- **Garder** copie la configuration sur Athena (communauté + carte), réponses du maître du jeu comprises ; le serveur la remet au démarrage.
- Les réponses écrites ne font pas partie des profils IA du site : elles vivent dans la mission (Eden) ou dans l'IA gardée.
