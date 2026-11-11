# IA de la zone

Version anglaise : [ia_de_la_zone.en.md](ia_de_la_zone.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

La fenêtre **IA de la zone** de l'éditeur SSE règle, pour les IA proches ou sélectionnées, **qui elles sont au contrôle d'identité** : profil Athena, papiers qu'elles portent et leur nature (vrais, couverture, faux), comportement quand on les contrôle, camp. Elle sert aussi à voir et corriger les papiers d'une IA, à écrire **ce qu'elle répond** quand on lui parle (bouton **Réponses…**) et à **garder** une IA pour les prochaines parties sur la même carte.

Sans elle, les IA reçoivent des papiers « selon leur camp » (réglages CBA Identité) et répondent seules, selon leur caractère et leur couverture.

## Où la trouver

| Contexte | Chemin |
|---|---|
| Zeus (centre Zeus de l'ATAK) | **IA de la zone** sur une IA ou une sélection |
| Zeus avec ZEN | Clic droit sur une ou plusieurs IA › **SSE · Roleplay de l'IA › Identité et papiers › Fiche dans l'éditeur SSE (IA de la zone)…** |
| Zeus sans ZEN | Module **Éditeur SSE** posé sur une IA (tout son groupe est listé) |
| Script | `[unité ou liste, position] call comspec_sse_fnc_aiConfigOpen` |

Dans Eden, réglez les IA par leurs attributs (**COMSPEC SSE › Papiers**, **Profil au contrôle**, **Profil IA RP** et son bouton **Atelier IA… › Réponses et limites…**) : tout est enregistré dans la mission.

## La fenêtre

### En haut à gauche : trouver les IA

- **Rayon** (25, 50, 100, 250, 500 m), **Détecter autour** (autour du module, de la caméra Zeus ou de vous) et **Sélection Zeus** (les IA sélectionnées avant d'ouvrir). Compteur « N IA », 60 au plus.
- **Rechercher** (nom donné, vrai nom, unité, groupe, profil ; ex. « Vlachos », « Alpha ») et trois filtres : camp, rôle, état (profil Athena, identité générée, papiers à la 1re demande, gardée).

### La liste

Une ligne par IA : case cochée, **Nom donné** (ce que disent ses papiers), **Vrai nom** (vous seul le voyez), **Vérité** (identité vraie, fausse identité, faux papiers, couverture, décidée à la 1re demande), camp, rôle, profil, nombre de papiers, groupe.

Un clic coche ou décoche une IA et montre sa fiche. **Tout cocher**, **Décocher**, « N IA cochée(s) ». Les réglages et boutons portent sur **les IA cochées**.

**Réponses…** (à droite du compteur) ouvre la fenêtre **Réponses de l'IA** sur les IA cochées : voir [reponses_ia.md](reponses_ia.md).

### Gardés pour les prochaines parties

La liste des IA enregistrées pour cette carte (serveur, Athena ou les deux), avec **Recharger l'IA enregistrée** et **Supprimer l'enregistrement**.

### La fiche

| Réglage | Valeurs | Exemple |
|---|---|---|
| **Profil Athena** | **Générer une identité**, puis les profils du Bureau SSE (recherche, **Actualiser**) | « PRF-0012 · Vendeur (Yannis Petrakis) » : l'IA prend ce nom, cette légende et ces papiers ; un contrôle N3 la reconnaît sur Athena |
| **Papiers qu'il porte** | Selon son camp, Aucun, Carte d'identité, Passeport, Permis, Carte et permis, Carte et passeport, Carte militaire, types des extensions | « Carte et permis » pour un chauffeur |
| **Nature** | Vrais papiers, Couverture, Faux papiers | « Faux papiers » pour un insurgé qui se fait passer pour un civil |
| **Comportement au contrôle** | Selon le contexte, Coopère, Nerveux, Ment avec aplomb, Refuse, S'enfuit, Devient agressif, Se rend | « Nerveux » : il hésite, répond à côté |
| **Camp** | Ne pas changer, Civils, BLUFOR, OPFOR, Indépendants | un civil passé OPFOR devient hostile |

Chaque réglage est expliqué sous sa case.

### Ses papiers

Type, pays, numéro, nature. **Voir le papier** (tel que le verra un joueur), **Le rendre vrai**, **Le rendre faux**, et un type à **Donner ce papier maintenant**.

### Ce que verra le joueur

Au contrôle : le nom sous lequel il se présente, ses papiers, sa réaction. Pour vous seulement : son vrai nom et sa vérité, et ce qui changera après **Appliquer**.

### En bas

**Garder pour les prochaines parties** (applique, puis enregistre), **Appliquer au groupe** (tout le groupe de chaque IA cochée), **Appliquer** (les IA cochées ; leurs papiers sont refaits).

## Pas à pas : un marché avec un guetteur

Dix civils sur un marché, dont un guetteur avec de faux papiers, et un vendeur qui connaît le mot de passe du réseau.

1. Zeus › **IA de la zone** sur un civil du marché ; rayon 50 m › **Détecter autour** : « 10 IA ».
2. **Tout cocher** ; Papiers **Carte d'identité**, Nature **Vrais papiers**, Comportement **Selon le contexte** › **Appliquer**.
3. **Décocher**, cochez le guetteur : Nature **Faux papiers**, Comportement **Nerveux** › **Appliquer**.
4. Cochez le vendeur seul › **Réponses…** : à « Qui es-tu ? », **Réponse perso** « Je suis {prenom}, je vends des fruits ici depuis vingt ans. » ; **Ajouter une question perso** : déclencheurs « mot de passe », réponse « Le faucon vole bas. » › **Appliquer**.
5. Pour rejouer la scène : cochez les deux et **Garder pour les prochaines parties** (IA nommées dans Eden de préférence). Les réponses écrites au point 4 sont gardées avec le vendeur.

## Pièges fréquents

- **Sélection Zeus** lit la sélection faite avant d'ouvrir la fenêtre.
- Les papiers d'une IA ne sont **fabriqués qu'à la première demande** : « à la demande » tant que personne ne l'a contrôlée.
- **Appliquer refait les papiers** des IA cochées.
- **Réponses…** applique tout de suite, sans passer par Appliquer ; **Garder** emporte ensuite ces réponses avec l'IA.
- **Garder** enregistre sur ce serveur, et sur Athena si la liaison et l'habilitation Confidentiel le permettent. Une IA sans nom de variable est retrouvée par sa classe à moins de 20 m, sinon : « IA enregistrée introuvable ».
- Tout passe par le serveur (Zeus ou administrateur seulement).

## Athena

- Les **profils IA** (Bureau SSE › Profils IA) se choisissent dans « Profil Athena » ; une IA reliée à un profil est reconnue au contrôle N3.
- **Garder** copie la configuration sur Athena (communauté + carte), réponses du maître du jeu comprises ; le serveur la remet au démarrage.
- Les réponses écrites ne font pas partie des profils IA du site : elles vivent dans la mission (Eden) ou dans l'IA gardée.
