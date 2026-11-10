# Éditeur SSE

Version anglaise : [editeur_sse.en.md](editeur_sse.en.md). Sommaire des guides : [index.md](index.md).

## À quoi ça sert

L'éditeur SSE est la fenêtre du Zeus (et du chef de mission dans Eden) pour **fabriquer un contenu de jeu et le remettre aux joueurs** sans écrire une ligne de script : une note trouvée sur un corps, l'identité complète d'un contact, un passeport à poser dans une boîte à gants, un kit « infiltration » (identité + papiers) pour un joueur, ou le dossier actif de la mission.

Cinq types de contenu, dans l'ordre des cartes de la fenêtre :

| Type | Carte « 1. QUE CRÉER ? » | Ce que c'est | Ce que les joueurs voient |
|---|---|---|---|
| Renseignement | **Renseignement** — « Note, rapport, carnet trouvé » | Un document texte avec un titre, une classification et une feuille | Une pièce « Documents » à ramasser, un papier trouvé en fouille ou un message dans l'ATAK |
| Identité | **Identité** — « Une personne et sa légende » | Une personne, vraie ou de couverture, avec sa légende | Des papiers à son nom, dont la légende est imprimée sur la fiche de la personne |
| Papier | **Papier** — « Carte, passeport, permis » | Un seul document rattaché à une identité | Le document lui-même (carte, passeport, permis, carte militaire) |
| Dossier | **Dossier** — « Suivi d'une cible ou d'un site » | La référence de l'affaire en cours | Rien directement : les pièces et fiches suivantes y sont rattachées (Athena) |
| Kit | **Kit** — « Lot prêt pour une mission » | Identité et lot de papiers pour une personne | Deux papiers cohérents au même nom (ex. carte + passeport) |

Tout ce que vous créez est gardé dans une **bibliothèque** (sur votre poste, et sur Athena si le serveur est relié) : on y revient, on duplique, on replace dans une autre partie.

## Où le trouver

| Contexte | Chemin |
|---|---|
| Zeus sans ZEN | Modules › COMSPEC SSE › **Éditeur SSE** (module posé n'importe où ; posé sur une IA vivante ou avec des IA sélectionnées, il ouvre directement la page « IA de la zone ») |
| Zeus avec ZEN | Catégorie **COMSPEC · SSE** › **Ouvrir l'éditeur** ; **Configurer une IA** ouvre la page « IA de la zone » |
| Eden | Barre de menus › **COMSPEC SSE** › **Éditeur SSE** (page « IA de la zone » absente dans Eden) |
| Script | `['open', [[], objNull]] call comspec_sse_fnc_editor` |

Il n'y a pas d'accès par le menu ACE ni par le téléphone : l'éditeur est un outil de conduite, pas un outil joueur.

## La fenêtre

En-tête commun : logo, titre de la page (« SSE / ÉDITEUR », « SSE / BIBLIOTHÈQUE », « SSE / IA DE LA ZONE »), **ligne d'état** (« Pas encore enregistré », « Brouillon sur ce poste », « Enregistré sur Athena », « Publié dans cette partie », « Brouillon enregistré à HH:MM ») et trois boutons : **IA DE LA ZONE**, **BIBLIOTHÈQUE** (qui devient **NOUVEAU** depuis les autres pages) et **FERMER**.

Au premier lancement, un guide en trois étapes (« Bienvenue dans l'éditeur SSE », « Voyez ce que verront les joueurs », « Placez dans le monde ») s'affiche une seule fois ; boutons **SUIVANT** puis **COMMENCER**.

### 1. QUE CRÉER ?

Cliquez une des cinq cartes. Sous les cartes, la ligne **PARTIR DE** propose trois modèles par type qui pré-remplissent les champs :

- Renseignement : **Rapport de source** · **Carnet manuscrit** · **Ordre intercepté**
- Identité : **Résident** · **Couverture** · **Cible**
- Papier : **Papier civil** · **Papier de couverture** · **Faux grossier**
- Dossier : **Cible** (réf. CIBLE-01) · **Site** (SITE-01) · **Réseau** (RESEAU-01)
- Kit : **Infiltration** · **Militaire** · **Journaliste**

### Les champs

| Champ | Pour | Exemple |
|---|---|---|
| **Titre** | Renseignement | « Liste de paie, cellule de Kavala » |
| **Prénom et nom** | Identité, papier, kit | « Alexandros Nikolaidis » (obligatoire : « Donnez d'abord un prénom et un nom. ») |
| **Référence du dossier** | Dossier | « SSE-2026-0007 » (la référence Athena de l'affaire, ou votre code interne) |
| **Classification** | Renseignement | NON PROTÉGÉ · DIFFUSION RESTREINTE · CONFIDENTIEL (bandeau imprimé sur la feuille) |
| **Type de papier** | Papier | Carte d'identité · Passeport · Permis de conduire · Carte militaire (+ les types des extensions chargées) |
| **Lot de papiers** | Kit | Carte et passeport · Carte militaire et carte · Carte et permis |
| **Nature** | Identité, papier, kit | **Vraie** (papiers officiels), **Couverture** (préparée, passe tout sauf la biométrie), **Faux** (détectable selon la qualité) |
| **Papier** | Renseignement | **Automatique (selon la pièce)**, une feuille de la liste (vignette), ou **Feuille unie (ancien rendu)** |
| **Contenu** | Renseignement | Le texte lu par le joueur |
| **Légende (imprimée sur la fiche de la personne)** | Identité, kit | « Né à Charkia, mécanicien au port, cousin de Stavros » |
| **Notes (bibliothèque seulement, non envoyées)** | Dossier, papier | Vos notes de conduite, jamais vues des joueurs |

Le panneau de droite **APERÇU EN JEU** (« Vu comme : le joueur qui le trouve ») montre la feuille telle que le joueur l'ouvrira : « RÉF. SSE-<id> » (ou « NOUVEAU »), tampon, titre, texte. Champs vides : un exemple grisé s'affiche.

### 2. COMMENT LES JOUEURS L'OBTIENNENT

Trois cartes, selon le type (aucune pour un dossier : « Devient le dossier actif : les pièces et fiches suivantes y sont rattachées. »).

Renseignement :

| Carte | Aide | Effet |
|---|---|---|
| **À ramasser** | Sur un objet choisi dans le monde | Une pièce « Documents » est posée au point cliqué ; les joueurs la ramassent |
| **En fouille** | Sur une personne ou dans un véhicule | Le document apparaît dans la fouille de la personne ou du véhicule cliqué |
| **Au téléphone** | À un groupe ou à un joueur | Arrive directement dans l'ATAK des destinataires (groupe visé, sinon le camp du Zeus) |

Identité, papier, kit :

| Carte | Aide | Effet |
|---|---|---|
| **Au sol** | Posés à l'endroit choisi | Les papiers sont posés au sol au point cliqué |
| **Sur quelqu'un** | Une personne ou un véhicule à cliquer | Remis dans l'inventaire de la personne, ou dans le véhicule |
| **Annonce** | Message au téléphone, aucun papier | Un message ATAK annonce ces papiers ; aucun objet n'est créé |

### Pied de page

« Rien n'est envoyé aux joueurs avant « Placer et publier ». Le contenu reste enregistré sur ce poste. » (avec Athena : « …est aussi enregistré sur Athena. »)

- **ENREGISTRER** : versionne le contenu dans la bibliothèque (titre ou nom obligatoire).
- **PLACER ET PUBLIER** : enregistre, ferme la fenêtre, puis attend un **clic dans le monde** (au Zeus : sous la souris ; à pied : au centre de l'écran ; **Échap annule**). Si le module Zeus donnait déjà une position, la pose est immédiate. Pour un dossier, le bouton devient **DEVENIR LE DOSSIER ACTIF**.

## Pas à pas : un carnet sur le corps du chef

Situation : l'équipe va assaillir une maison ; vous voulez qu'elle trouve, sur le corps du chef, un carnet qui désigne le prochain lieu de rendez-vous.

1. Zeus › **Ouvrir l'éditeur** (ou module Éditeur SSE).
2. Carte **Renseignement**, modèle **Carnet manuscrit**.
3. Titre : « Carnet, pages du 3 au 7 ». Classification : NON PROTÉGÉ. Papier : une feuille froissée de la liste.
4. Contenu : « Mardi, livraison à la ferme de Sofia, 2 camions. Demander Yannis. ». Vérifiez l'aperçu à droite.
5. Carte **En fouille**.
6. **PLACER ET PUBLIER** › cliquez sur le chef (vivant ou mort). Ligne d'état : « Publié dans cette partie ».
7. Quand un joueur fouille le corps et lit le carnet, vous recevez une notification et la bibliothèque note **« Trouvé par un joueur »**.

Variante kit : carte **Kit**, modèle **Infiltration**, « Prénom et nom » du joueur infiltré, lot **Carte et passeport**, nature **Couverture**, légende, carte **Sur quelqu'un** › cliquez le joueur. Il reçoit ses papiers et sa légende apparaît dans son app **Ma couverture**.

## La bibliothèque

Bouton **BIBLIOTHÈQUE**.

- Filtres : **Tout · Renseignements · Identités · Papiers · Dossiers · Kits · Modèles · Archives**.
- Recherche : « Rechercher : titre, personne, opération, numéro de papier » (porte sur le titre, l'opération, le contenu et le lien).
- Liste : icône d'état, titre, type, opération, état (**Brouillon · Modèle · Placé en mission · Archivé**). Vide : « Rien ici pour le moment. »
- Panneau **SÉLECTION** : **Modifier · Dupliquer · Placer dans le monde · Archiver** ; **HISTORIQUE** (versions, auteur, date, et « Trouvé par un joueur » en tête) ; **LIÉ À** (texte libre : référence d'affaire, nom de cible).
- Trois modèles sont créés au premier lancement : « Kit infiltration, papiers et légende », « Rapport de source », « Dossier cible ». Modifier un modèle en fait une copie : l'original ne change jamais.

## Pièges fréquents

- **Rien ne part avant PLACER ET PUBLIER.** Enregistrer ne fait que garder un brouillon.
- **Échap pendant le clic de pose annule** : le contenu reste « Brouillon », rien n'est posé.
- **Seul un Zeus ou un administrateur peut poser** (le serveur vérifie ; sinon « pose refusée » au RPT).
- **Eden** : seul un renseignement « À ramasser » ou « En fouille » se place (champ Init de l'objet ; pour la fouille, sélectionnez d'abord une personne ou un véhicule). Le reste affiche « Gardé dans la bibliothèque : à placer en mission, depuis le module Zeus ».
- **Au téléphone / Annonce** passent par l'ATAK natif ; sans lui, c'est une notification SSE ordinaire. Un envoi au téléphone n'est jamais rejoué d'une partie à l'autre.
- **La bibliothèque est dans votre profil** (profileNamespace) ; Athena n'en est que le miroir. Hors ligne, les contenus restent marqués à envoyer et repartent à la prochaine ouverture. Dans Eden, après un échec Athena, pas de nouvel essai pendant 60 s.
- **Classification** n'a d'effet que sur un renseignement.
- **« Garder pour les prochaines parties »** (page IA de la zone) exige la liaison Athena et l'habilitation Confidentiel ; sinon « Enregistrement sur Athena impossible (liaison, ou habilitation Confidentiel) : rien n'est gardé. »
- Débogage : `comspec_sse_id_editorDebug = true` (variable de mission) écrit le détail au RPT.

## Athena

- La bibliothèque est synchronisée avec **Athena › Bureau SSE** quand le serveur est relié (envoi par COMSPEC Link) ; on y retrouve brouillons, modèles et contenus placés, avec l'historique.
- Un **dossier** rendu actif range les pièces et fiches transmises ensuite dans l'affaire Athena de même référence.
- « Trouvé par un joueur » est aussi inscrit sur Athena (file d'attente hors ligne).
- Les profils IA du Bureau SSE (Profils IA) alimentent la page « IA de la zone » : voir [ia_de_la_zone.md](ia_de_la_zone.md).
