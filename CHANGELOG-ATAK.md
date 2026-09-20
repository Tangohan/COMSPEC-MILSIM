# CHANGELOG - Features ATAK

Toutes les modifications notables des features ATAK sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Journal développeur (style Bohemia) : [SPOTREP #00003](docs/dev/SPOTREP-00003.md) · [TECHREP #00003](docs/dev/TECHREP-00003.md).

Changelog Steam (copier-coller) : [Overwatch 1.6.3](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.3.md) · [Overwatch 1.5.78](docs/dev/STEAM-CHANGELOG-2026-09-15-overwatch-1.5.78.md) · [Overwatch 1.5.77](docs/dev/STEAM-CHANGELOG-2026-09-14-overwatch-1.5.77.md) · [Overwatch 1.5.76](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.76.md) · [Overwatch 1.5.75](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.75.md) · [Overwatch 1.5.74](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.74.md) · [Overwatch 1.5.73](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.73.md) · [Overwatch 1.5.72](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.72.md) · [Overwatch 1.5.71](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.71.md) · [Overwatch 1.5.70](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.70.md) · [Overwatch 1.5.69](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.69.md) · [Overwatch 1.5.68](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.68.md) · [Overwatch 1.5.36](docs/dev/STEAM-CHANGELOG-2026-09-11-overwatch-1.5.36.md).

---

## Vague 2026-09-20 — Overwatch 1.6.4 / SSE 0.7.22 / Athena 1.0.158

Changelog Steam (copier-coller) : [Overwatch 1.6.3](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.3.md).

### Nouveau — Boussole seule sous jumelles

Sous jumelles, vous pouvez n’afficher que votre boussole dans le tube, sans la grille, la distance ni l’heure. Choix : Paramètres du téléphone, ligne Dans le tube, ou options du jeu (Affichage situation).

### Correction — Manifeste de vol et repères texte

Le manifeste de vol s’enregistre même si l’emport est saisi en phrase libre. Les repères posés en jeu comme du texte seul, sans picto, apparaissent au poste dans un cadre coloré. Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Position différée, sans bandeau

Quand une position n’est plus à l’instant, l’indicatif reste lisible : le cadre passe en pointillés (ambre, puis rouge). Plus de bandeau « Différé » sous le nom. Le détail reste au survol et dans la fiche. Rechargez Overwatch Beta (Ctrl+F5).

### Amélioration — Bosquets en houppier

En 2D immersif, un groupe d’arbres dans un champ se lit comme un houppier collé à la photo, plus comme une grille de carrés verts. Les constructions restent des toits clairs. Rechargez Overwatch Beta (Ctrl+F5).

### Amélioration — Aide sur les options de vue

À côté des options de vue, un i ouvre une phrase d’aide : traces, densité de passages, replay, inspection des bâtiments, manques de relief.

### Correction — Positions figées et couvert végétal

Sans opérateur en liaison, le bandeau rouge ne parle plus de positions figées. Les arbres et le couvert n’apparaissent plus en pavés sur toute la carte : ils se lisent en se rapprochant, en taches de houppier. Un message indique le chargement du relevé.

### Amélioration — 2D immersif, emprises de constructions

En 2D immersif, les constructions du relevé se lisent comme des toits sur la photo : emprise claire, collée au terrain. Un rectangle sans nom posé en jeu n’affiche plus de nom technique au survol. Un clic ouvre toujours la fiche de la construction.

### Correction — Menu d’applications du téléphone

Le chevron reprend sa grille : icônes alignées, noms lisibles, Video Feeds à sa place. L’application réseau local s’ouvre toujours.

### Correction — Démarrage du téléphone ATAK

Le téléphone s’ouvre de nouveau au lancement. L’application réseau local ne bloquait plus le chargement.

### Nouveau — Affichage situation sous jumelles

Sous jumelles de vision nocturne, le champ reprend un tube à trois oculaires : grain, contraste, et un assombrissement si un véhicule éclaire vers vous. La boussole, la grille, la distance regardée et l’heure apparaissent dans le tube. Les pastilles sont des losanges. Un halo clair marque les alliés proches, un véhicule moteur allumé et le bâtiment désigné.

### Correction — Découpage d’étage

Quand un bâtiment est désigné et le découpage d’étage actif, une croix marque le plafond de l’étage regardé.

### Correction — Identité SEEK

Le module, les attributs de la personne et le pack SSE imposent le même nom et le même verdict (Signalé ou Recherché) au terminal SEEK.

### Correction — Surcharge mémoire

Moins d’arrêts liés aux tuiles de carte, au dépassement de carte et à un ordre mal formé.

### Nouveau — Canaux de discussion, photothèque, carte du poste

Canaux Commandement, Général, JTAC. Photothèque : envoi vers le site et le SSE. Carte du poste : prédiction de déplacement, pointage, données terrain. Dépannage de liaison via Échap.

Relancez Arma complètement (Overwatch 1.6.4, pack SSE 0.7.22, Athena 1.0.158).

---

## Pack SSE 0.7.22 — 2026-09-20

### Correction — Identité SEEK depuis l’éditeur du pack SSE

Dans l’éditeur, le pack SSE reprend désormais le même résultat que Overwatch : nom, prénom, alias, et le choix Signalé ou Recherché. Le terminal SEEK du pack affiche ce nom et ce verdict, même sans Overwatch. Relancez Arma complètement (pack SSE 0.7.22).

---

## Overwatch 1.6.3 — 2026-09-20

### Correction — Découpage d’étage sur la silhouette

Quand un bâtiment est désigné et le **découpage d’étage** actif, une croix marque désormais le plafond de l’étage que vous regardez. Les séparateurs d’étage restent visibles ; l’étage affiché se distingue clairement. Le choix découpage se mémorise au même endroit, que vous le changiez depuis le téléphone ATAK ou depuis les options du jeu. Relancez Arma complètement (Overwatch 1.6.3).

---

## Overwatch 1.6.2 — 2026-09-20

### Correction — Profil d’identité SSE dans l’éditeur

Dans l’éditeur, le module **Profil d’identité SSE** impose maintenant le même résultat que les attributs de la personne : nom, alias, et le choix Signalé ou Recherché. Le terminal SEEK voit ces informations sur les sujets synchronisés. Le mât Relais AT n’a plus de zone à redimensionner : la portée se règle uniquement en mètres. Relancez Arma complètement (Overwatch 1.6.2).

---

## Overwatch 1.6.1 — 2026-09-20

### Amélioration — Affichage situation sous jumelles

Sous jumelles de vision nocturne, le champ reprend un tube à trois oculaires : grain, contraste, et un assombrissement si un véhicule éclaire vers vous. La boussole, la grille, la distance regardée et l’heure apparaissent dans le tube. Les pastilles sont des losanges, le texte reste lisible, et les alliés lointains s’estompent. Un halo clair marque les alliés proches, un véhicule moteur allumé et le bâtiment désigné. Si une fusion thermique est déjà fournie par un autre pack, Overwatch n’ajoute pas la sienne. Relancez Arma complètement (Overwatch 1.6.1).

---

## Overwatch 1.6.0 / Athena 1.0.155 — 2026-09-19

### Amélioration — Manifeste de vol

Le manifeste reprend l’appareil dans lequel vous êtes : nom, personnes à bord, munitions, pods et autonomie. Vous confirmez les codes, le numéro de mission et le canevas d’attaque, puis vous transmettez. Le poste voit la même fiche. Relancez Arma complètement (Overwatch 1.6.0, Athena 1.0.155).

---

## Overwatch 1.5.99 — 2026-09-19

### Correction — Ordres C2

Un ordre donné en jeu n’arrête plus Arma. Il part sous votre nom Athena, pas sous le surnom du jeu. Le chat de groupe ne montre plus la ligne technique. Relancez Arma complètement (Overwatch 1.5.99).

---

## Overwatch 1.5.98 / Athena 1.0.154 — 2026-09-19

### Nouveau — Appui aérien et manifeste dans le téléphone

La demande d’appui aérien et le manifeste de vol s’ouvrent dans le téléphone, depuis le tiroir. Vous indiquez le type, la grille et une note, ou l’appareil, le rôle et les codes, sans recouvrir le terrain. Relancez Arma complètement (Overwatch 1.5.98, Athena 1.0.154).

---

## Overwatch 1.5.97 — 2026-09-19

### Amélioration — Manifeste de vol

Le manifeste de vol se remplit aussi depuis le sol. Vous choisissez le type d’appareil, le rôle, la destination, les personnes à bord et une note pour le poste. La grille et le carburant sont proposés automatiquement. En vol, l’appareil est toujours détecté. Une réponse **À poste** s’ajoute aux statuts pilote. Le poste affiche la fiche complète. Relancez Arma complètement (Overwatch 1.5.97).

---

## Overwatch 1.5.96 / Athena 1.0.153 — 2026-09-19

### Nouveau — Relais AT

Wave Relay devient Relais AT dans le tiroir du téléphone. L’application montre le mât le plus proche : position, débit, fiabilité, identité, adresse réseau, passerelle, certificat, places et puissance. La carte du poste affiche la même fiche. Un mât détruit passe hors service (débit et puissance à zéro). Posez et réglez le mât dans l’éditeur (Modules COMSPEC, notice de pose) ou via Zeus. Relancez Arma complètement (Overwatch 1.5.96, Athena 1.0.153).

### Nouveau — Annuler le pointage de bâtiment

Quand un bâtiment est désigné sous jumelles de vision nocturne, l’affichage situation propose d’annuler ce pointage. La silhouette, le badge et le repère local disparaissent.

---

## Overwatch 1.5.95 / Athena 1.0.152 — 2026-09-19

### Correction — Symboles Marker Dropper au poste

Un losange d’infanterie ou un autre symbole posé avec Marker Dropper sur le téléphone apparaît maintenant sur la carte du poste, au même endroit. Relancez Arma complètement (Overwatch 1.5.95).

---

## Overwatch 1.5.94 / Athena 1.0.151 — 2026-09-19

### Correction — Coupure sans panneau sur la carte

Une coupure de liaison n’affiche plus de panneau au milieu de la carte. Les barres de signal, à côté de l’heure et de la batterie, passent au rouge. La carte reste utilisable. Relancez Arma complètement (Overwatch 1.5.94).

---

## Athena 1.0.150 — 2026-09-19

### Correction — Paramètres ouverts sur la fiche

La page Paramètres s’ouvrait trop bas, sur la liaison au poste. Elle s’ouvre maintenant sur votre fiche (indicatif, rôle, carte, équipe). Enregistrer reste visible en haut. Relancez Arma complètement (Athena 1.0.150).

---

## Athena 1.0.149 — 2026-09-19

### Correction — Transférer dans Photo Library

Transférer et Tout transférer n’apparaissaient pas sous la liste. Ils se placent maintenant dans cette zone dès que Photo Library est ouverte. Relancez Arma complètement (Athena 1.0.149).

---

## Athena 1.0.148 — 2026-09-19

### Correction — P2P réseau local

P2P n’affichait plus que Retour et Send Data, sans correspondants ni messages. Le chat téléphone à téléphone revient. Relancez Arma complètement (Athena 1.0.148).

---

## Overwatch Beta — 2026-09-19

### Correction — Croquis qui disparaissaient

Un croquis tracé au crayon s’effaçait dès l’ouverture du panneau d’enregistrement. Le trait reste maintenant sur la carte. Dans le mode Tracé tactique, il se pose dès que vous relâchez le clic.

### Amélioration — Couleurs du tracé tactique

La barre au-dessus de la carte propose une couleur et une épaisseur de trait. Flèches, croquis, zones, surligneur et symboles OTAN utilisent cette teinte. Les pastilles ami, ennemi, neutre et inconnu restent disponibles, plus une teinte libre. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Plan rattaché à un bâtiment connu

Le plan d’étage se rattache à un bâtiment déjà désigné en jeu, ou à une construction relevée. Vous le choisissez dans la liste. Un clic sur une construction ouverte propose aussi d’ouvrir le plan. Rechargez Overwatch Beta (Ctrl+F5). Pour les désignations en jumelles, relancez Arma.

### Amélioration — À plat et 2D immersif

La vue À plat reste un plan : photo ou carte du jeu, sans volumes. Le 2D immersif montre les constructions du relevé collées au fond. Elles se chargent une fois à l’ouverture : zoomer ne les décale plus. Relief 3D et Tactique 3D dressent toujours le sol. Rechargez Overwatch Beta (Ctrl+F5).

### Amélioration — Onglet Contacts

Les contacts se séparent entre ceux encore en liaison et ceux qui ne le sont plus, par escouade. Chaque fiche montre le rôle, l’âge de la position, et si le lien est direct ou par relais. Un contact hors ligne se recentre sur sa dernière position connue.

### Amélioration — Fiche contact et groupes

La fiche d’un contact ne montre plus que l’essentiel : vitesse, altitude, dernière position, grille, puis centrer, suivre ou donner une tâche. Le téléphone et l’alerte plein écran se déplient à la demande. Dans Groupes, les tâches déjà transmises et l’alerte sont rangées de la même façon.

### Amélioration — Constructions du jeu

En 2D immersif, les bâtiments, forêts et obstacles relevés en jeu s’affichent collés à la carte. Un clic ouvre la fiche de la construction : marquer, poser un objectif, noter un étage. En Relief 3D, les volumes se dressent toujours au-dessus du sol. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Tracé tactique et briefing

Le crayon du rail ouvre une barre au-dessus de la carte : flèche, croquis, zone, surligneur, texte, plan de bâtiment. Les symboles OTAN posent un axe, une attaque, une ligne de phase, une limite de secteur, un rassemblement ou un objectif, selon l’affiliation (ami, ennemi, neutre, inconnu). Un plan d’étage se rattache à une position. Exporter PDF prépare une feuille de briefing (carte, légende, grille, horodatage, fil). Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Repères qui revenaient

Un compte rendu ou un repère retiré de la carte du poste réapparaissait quelques secondes plus tard. Le retrait tient désormais. Rechargez Overwatch Beta (Ctrl+F5).

---

## Athena 1.0.147 — 2026-09-19

### Amélioration — Icônes du menu

Athena, Briefing et Tutoriel avaient la même bulle. Chaque application a maintenant son pictogramme : tablette, presse-papiers, livre, bulles pour la messagerie. Relancez Arma complètement (Athena 1.0.147).

---

## Overwatch 1.5.93 — 2026-09-19

### Correction — Menu Échap

Le bouton COMSPEC Overwatch du menu Échap n’apportait plus rien d’utile. Il disparaît. Le dépannage de liaison reste disponible depuis Échap. Relancez Arma complètement (Overwatch 1.5.93).

---

## Athena 1.0.146 — 2026-09-19

### Nouveau — Transfert vers le poste

Dans Photo Library, **Transférer** envoie la vue sélectionnée vers le poste. **Tout transférer** envoie vos vues locales. Après l’envoi, elles quittent la bibliothèque du téléphone. Relancez Arma complètement (Athena 1.0.146).

---

## Athena 1.0.145 — 2026-09-19

### Correction — Icônes du menu

Les tuiles Athena du chevron n’affichaient plus d’icône. Le pictogramme revient au-dessus du nom, comme sur Video Feeds. Relancez Arma complètement (Athena 1.0.145).

---

## Athena 1.0.144 — 2026-09-19

### Correction — Menu d’applications

Le chevron ne montrait plus que deux ou trois icônes, mal placées. Le téléphone ne déplace plus les tuiles : la grille d’origine réapparaît. Relancez Arma complètement (Athena 1.0.144).

---

## Overwatch Beta — 2026-09-19

### Correction — Fond de carte en relief 3D

En vue Relief 3D ou Tactique 3D, le théâtre restait sombre : le plan et la photo aérienne n’arrivaient pas jusqu’au poste. Ils s’affichent de nouveau sur le relief. Rechargez Overwatch Beta (Ctrl+F5).

---

## Overwatch Beta — 2026-09-19

### Nouveau — Outils de commandement

Le poste mesure d’un clic à l’autre (distance, cap, grilles, temps de parcours). Un compte rendu de contact structuré, une 9-line et un CASEVAC se préparent depuis un point de la carte. Les contacts en mouvement montrent un vecteur d’anticipation. Le fil d’ordres se filtre par mot-clé. Des alertes sonores préviennent d’un contact trop proche d’un objectif ou d’un ralliement franchi. Replay et export du bilan sont accessibles depuis Mission et Plus. Une position qui n’est plus mise à jour est indiquée comme dernière position connue, figée. L’alerte plein écran à tous les opérateurs est réservée au commandement. Rechargez Overwatch Beta (Ctrl+F5).

---

## Athena 1.0.143 — 2026-09-19

### Correction — Menu d’applications

Le chevron mélangeait les icônes et les noms. Un clic ouvrait souvent Task et Video Feeds, et les applications Athena n’apparaissaient pas. Les tuiles reprennent la grille à trois colonnes du téléphone. Relancez Arma complètement (Athena 1.0.143).

---

## Overwatch 1.5.92 — 2026-09-19

### Amélioration — Journal du dépannage

Si le jeu s’arrête pendant le dépannage liaison, le journal de session indique désormais quelle étape était en cours. Relancez Arma complètement (Overwatch 1.5.92) avant le prochain essai.

---

## Athena 1.0.142 — 2026-09-19

### Correction — Quick Pictures vers le poste

Une vue prise depuis Quick Pictures n’arrivait pas au poste, ou seulement si l’on choisissait le bon destinataire. Dans Photo Library, le bouton **Vers Athena** envoie la photo sélectionnée au poste. Relancez Arma complètement (Athena 1.0.142).

---

## Overwatch 1.5.91 — 2026-09-19

### Correction — Photos du poste

Les photos reçues du terrain ou du dépannage affichaient l’année 1970 et ne proposaient que Envoyer. L’heure affichée est désormais celle de la prise ou de la réception. Sous chaque photo : Agrandir, Flouter, Passer en SSE, Supprimer. Rechargez Overwatch Beta. Relancez Arma complètement (Overwatch 1.5.91) pour les prochaines photos prises en jeu.

---

## Athena 1.0.141 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron déformait les tuiles : icônes décalées, libellés absents, cases vides. Les applications reprennent leur place en trois colonnes, avec le fond gris sombre. Relancez Arma complètement (Athena 1.0.141).

---

## Overwatch 1.5.90 — 2026-09-19

### Correction — File d’ordres

Les ordres déjà reçus du poste n’étaient pas remplacés à chaque lecture : la mémoire du téléphone s’allongeait jusqu’à fermer le jeu. Désormais seuls les ordres encore en attente au poste, plus ceux émis depuis le téléphone, restent en mémoire. Relancez Arma complètement (Overwatch 1.5.90).

---

## Overwatch 1.5.89 / Athena 1.0.140 — 2026-09-19

### Correction — Ordres à l’affichage

Pendant le dépannage, les ordres déjà en mémoire n’étaient pas marqués comme vus. Au passage à l’affichage, le téléphone les livrait tous d’un coup et le jeu se fermait. Désormais un seul ordre est affiché à la fois, et la file déjà reçue n’est plus rejouée. Relancez Arma complètement (Overwatch 1.5.89 · Athena 1.0.140).

---

## Overwatch 1.5.88 — 2026-09-19

### Amélioration — Dépannage liaison

Le dépannage envoie désormais un message de test, pose un repère de test, puis prend une photo et la transmet au poste. Le bandeau indique si l’essai est parti. Relancez Arma complètement (Overwatch 1.5.88).

---

## Athena 1.0.139 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron reprend le fond gris sombre et les libellés cyan. Les icônes ne se superposent plus : elles se calent en trois colonnes dans le tiroir.

---

## Overwatch 1.5.87 — 2026-09-19

### Correction — Ordres d’une partie précédente

Le téléphone ne reçoit plus les ordres encore en attente au poste s’ils ont été émis avant le début de cette partie. Le poste les conserve. Un ordre envoyé pendant la partie en cours arrive normalement. Relancez Arma complètement (Overwatch 1.5.87).

---

## Overwatch 1.5.86 — 2026-09-18

### Correction — Ordres d’une partie précédente

Les ordres encore en attente au poste, issus d’une partie précédente, ne sont plus livrés au téléphone. Seuls les ordres émis pendant la partie en cours apparaissent. Relancez Arma complètement (Overwatch 1.5.86).

---

## Athena 1.0.141 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron déformait les tuiles : icônes décalées, libellés absents, cases vides. Les applications reprennent leur place en trois colonnes, avec le fond gris sombre. Relancez Arma complètement (Athena 1.0.141).

---

## Overwatch 1.5.90 — 2026-09-19

### Correction — File d’ordres

Les ordres déjà reçus du poste n’étaient pas remplacés à chaque lecture : la mémoire du téléphone s’allongeait jusqu’à fermer le jeu. Désormais seuls les ordres encore en attente au poste, plus ceux émis depuis le téléphone, restent en mémoire. Relancez Arma complètement (Overwatch 1.5.90).

---

## Overwatch 1.5.89 / Athena 1.0.140 — 2026-09-19

### Correction — Ordres à l’affichage

Pendant le dépannage, les ordres déjà en mémoire n’étaient pas marqués comme vus. Au passage à l’affichage, le téléphone les livrait tous d’un coup et le jeu se fermait. Désormais un seul ordre est affiché à la fois, et la file déjà reçue n’est plus rejouée. Relancez Arma complètement (Overwatch 1.5.89 · Athena 1.0.140).

---

## Overwatch 1.5.88 — 2026-09-19

### Amélioration — Dépannage liaison

Le dépannage envoie désormais un message de test, pose un repère de test, puis prend une photo et la transmet au poste. Le bandeau indique si l’essai est parti. Relancez Arma complètement (Overwatch 1.5.88).

---

## Athena 1.0.139 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron reprend le fond gris sombre et les libellés cyan. Les icônes ne se superposent plus : elles se calent en trois colonnes dans le tiroir.

---

## Overwatch 1.5.87 — 2026-09-19

### Correction — Ordres d’une partie précédente

Le téléphone ne reçoit plus les ordres encore en attente au poste s’ils ont été émis avant le début de cette partie. Le poste les conserve. Un ordre envoyé pendant la partie en cours arrive normalement. Relancez Arma complètement (Overwatch 1.5.87).

---

## Overwatch 1.5.86 — 2026-09-18

### Correction — Ordres d’une partie précédente

Les ordres encore en attente au poste, issus d’une partie précédente, ne sont plus livrés au téléphone. Seuls les ordres émis pendant la partie en cours apparaissent. Relancez Arma complètement (Overwatch 1.5.86).

---

## Overwatch 1.5.85 — 2026-09-18

### Correction — Prise d’équipement

Récupérer le téléphone ATAK dans l’arsenal ne livre plus d’un coup les ordres déjà en attente et tous les repères du poste. Les échanges attendent la fermeture de l’arsenal. Les alertes et le fil n’apparaissent que lorsque le grand écran du téléphone est vraiment ouvert.

---

## Portail · Overwatch Beta — 2026-09-18

### Correction — Relief 3D vide

La vue Relief 3D restait un écran vert sombre, sans sol ni bâtiments. Après recharge de la page, le théâtre se relève et les constructions du relevé réapparaissent. Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Relief 3D figé au redimensionnement

Agrandir la fenêtre, ouvrir le comparatif 2D / 3D ou basculer Relief 3D ne fige plus la carte. Le sol et les bâtiments restent affichés. Rechargez Overwatch Beta.

### Nouveau — Visibilité, coupe et lecture 3D

Un clic sur un observateur affiche les portions de terrain visibles et masquées. L’horizon dessine la silhouette du relief. Une coupe de A vers B montre le sol et les constructions. La comparaison 2D / 3D aligne les deux lectures. Les symboles restent lisibles derrière un obstacle (réaliste, silhouette ou toujours visibles). Une pile remplace les icônes empilées. Une note, une photo ou une tâche peut s’ancrer à une façade, un étage ou un toit. Un volume a une altitude basse et haute. Le replay peut suivre l’action, avec traces de déplacement et densité de passages. Les vues de caméra s’enregistrent. Rechargez Overwatch Beta.

### Nouveau — Lecture 3D du théâtre

En vue Tactique 3D, les murs, clôtures, ponts et pylônes se dressent avec les bâtiments. Un clic sur une construction ouvre sa fiche (grille, orientation, niveaux, hauteur) avec les actions déjà connues : marquer, objectif, entrée, photo, tâche. Double-clic pour incliner la caméra, Nord pour revenir à plat, Unité pour suivre le groupe, Sol pour descendre près du terrain. La visée nomme l’obstacle. Un tracé affiche montée, descente et pente. Rechargez Overwatch Beta. Relancez le relevé de carte si les obstacles manquent.

### Amélioration — Relief 3D : clic, masses et lumière

En vue Relief 3D, cliquer un bâtiment ouvre le même menu que sur la carte à plat (marqueurs, ralliement, tâches). Les petites constructions lointaines se regroupent ; le détail revient en se rapprochant. Les ombres suivent l’heure et la météo du bandeau. Rechargez Overwatch Beta.

---

## Athena 1.0.138 — 2026-09-18

### Correction — Fil d’ordres

Le fil du téléphone n’est plus rempli tant que le grand écran ATAK n’est pas ouvert. Un mini-écran 3D à la prise de l’objet ne déclenche plus ce remplissage.

---

## Overwatch 1.5.84 — 2026-09-18

### Correction — Dépannage : étape Ordres

Le dépannage liaison ne livre plus les ordres sur le téléphone en même temps qu’il les reçoit. D’abord la réception, ensuite l’affichage. Le bandeau affiche le débit, le volume transmis, le poste, les versions, le compte identifié et le taux d’erreur. Le journal indique le nombre de messages, de repères et d’ordres reçus.

---

## Athena 1.0.137 — 2026-09-18

### Correction — Ordres pendant le dépannage

Pendant le dépannage, les ordres ne sont plus collés dans le fil du téléphone avant l’étape d’affichage.

---

## Overwatch 1.5.83 — 2026-09-18

### Nouveau — Contrôle de mission

Le commandement dispose d’un écran unique pour imposer le réalisme, voir les fonctions actives, consulter les relais et activer ou couper ce que la mission utilise. Les opérateurs en liaison reçoivent les règles sous environ une minute. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.136 — 2026-09-18

### Nouveau — Message : P2P ou Via Athena

En ouvrant Message, vous choisissez d’abord le canal. **P2P — Réseau local** : messages entre téléphones à proximité, comme d’habitude. **Via Athena** : messagerie de compte à compte, comme au poste.

---

## Overwatch 1.5.82 — 2026-09-18

### Correction — Plus de double démarrage en mission

Recocher Overwatch dans les paramètres d’addons ne relance plus tout le mod par-dessus une session déjà ouverte. Les menus, la liaison et les alertes restent un seul jeu. Relancez Arma complètement après la mise à jour.

---

## Overwatch 1.5.81 — 2026-09-18

### Correction — Dépannage liaison visible sur Échap

Le dépannage se lance depuis le bouton orange en haut à gauche du menu Échap. Plus besoin de chercher dans le panneau, plus de seconde fenêtre par-dessus la pause. Un bandeau reste à l’écran : Overwatch est coupé, puis chaque fonction est rallumée une par une. Relancez Arma complètement après la mise à jour.

---

## Overwatch 1.5.80 — 2026-09-18

### Nouveau — Dépannage liaison

Un outil coupe Overwatch, puis rallume chaque fonction de la liaison une par une, avec 55 secondes d’écart. Un bandeau reste à l’écran : si le jeu s’arrête, c’est la fonction affichée. Échap → COMSPEC Overwatch → Dépannage liaison. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.135 / Overwatch 1.5.79 — 2026-09-17

### Correction — Plus d’arrêt avec Overwatch actif

Le jeu ne se ferme plus tout seul une fois Overwatch coché, téléphone ouvert ou non. Les repères déjà affichés depuis le poste ne relancent plus une avalanche d’envois. Décochez Overwatch : la position et les messages s’arrêtent aussi. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.134 — 2026-09-17

### Correction — Messagerie lisible, plus d’arrêt sans téléphone

La liste des canaux n’est plus recouverte par Envoyer / Effacer. Sans téléphone en poche, le jeu ne se ferme plus tout seul : la liaison avec le poste n’attend plus que l’objet soit porté. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.133 — 2026-09-17

### Correction — Plus d’arrêt pendant la carte et les repères

Le jeu ne se ferme plus tout seul pendant l’affichage de la carte, la pose d’un repère ou le rafraîchissement des indicatifs. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.132 — 2026-09-17

### Correction — Menu d’applications avec les icônes

Le chevron ouvre le menu d’applications avec les icônes. Ce n’est plus un panneau gris vide. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.131 — 2026-09-17

### Correction — Plus d’arrêt après quelques dizaines de secondes

Le jeu ne se ferme plus tout seul une fois le téléphone ouvert sur la carte, ni après une pause sans activité visible. La page d’état de liaison affiche l’état déjà calculé, sans relancer le calcul à chaque rafraîchissement. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.130 — 2026-09-17

### Correction — Menu d’applications qui se referme

Le menu d’applications s’ouvre et se referme de nouveau avec le chevron et Retour. La carte reste dans le cadre du téléphone. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.129 — 2026-09-17

### Correction — Carte dans l’écran et menu du téléphone

La carte reste dans le cadre du téléphone. Le menu d’applications reprend celui du téléphone : les icônes s’affichent, Retour referme le menu. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.128 — 2026-09-17

### Correction — Arrêt brutal au bout de quelques secondes

Le jeu ne se ferme plus tout seul après quelques secondes, même sans ouvrir le téléphone. Le menu ACE ne se réempile plus à chaque mission de la même session. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.144 — 2026-09-16

### Correction — Alertes et écran du téléphone

Une alerte du poste n’ajoute plus un voile sur le téléphone en miniature. Les cartouches de la carte restent ceux du téléphone, sans cadre collé par-dessus. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.143 — 2026-09-16

### Changement — Plus de barre de données en bas de carte

La ligne verte en bas de la carte (sync, fiabilité, versions) n’apparaît plus. La carte reprend toute la hauteur. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.142 — 2026-09-16

### Correction — Menu qui reste ouvert, messages dans la messagerie

Le menu d’applications s’ouvre au chevron et y reste jusqu’à ce qu’on le referme. Les messages du poste restent dans la messagerie : ils ne s’empilent plus en bandeau en bas de la carte. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.141 — 2026-09-16

### Correction — Plus de fermeture sans ouvrir le téléphone

Le jeu ne se ferme plus tout seul si l’opérateur n’a pas le téléphone en main et ne l’a pas ouvert. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.140 — 2026-09-16

### Correction — Plus de recadrage de la carte

Athena ne déplace plus et ne redimensionne plus la carte ni le menu d’applications. Le menu s’affiche ou se masque seulement, à la demande du chevron. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.139 — 2026-09-16

### Correction — Menu qui reste ouvert

Le menu d’applications s’ouvre et reste ouvert jusqu’au chevron. Il ne clignote plus. Sortir le curseur du cadre du téléphone n’immobilise plus l’écran. Relancez Arma complètement après la mise à jour.

---

## Liaison 2.0.42 — 2026-09-16

### Correction — Photographies sans surcharge

L’envoi d’une photographie volumineuse depuis le téléphone n’encombre plus la mémoire du jeu. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.138 — 2026-09-16

### Correction — Menu qui ne revient plus tout seul

Le menu d’applications ne s’ouvre plus tout seul juste après l’écran. Il reste fermé jusqu’au chevron. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.137 — 2026-09-16

### Correction — Menu vraiment fermé à l’ouverture

Le menu d’applications reste fermé à chaque ouverture du téléphone, même si la session précédente l’avait laissé ouvert. Seul le chevron l’ouvre et le referme. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.136 — 2026-09-16

### Correction — Menu fermé à l’ouverture

Le menu d’applications n’est plus déployé dès l’ouverture du téléphone. Le chevron l’ouvre et le referme. La carte ne se rétrécit plus à chaque ouverture. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.135 — 2026-09-16

### Amélioration — Affichage IceMan

Les pages du téléphone, les cartouches de la carte, la barre de liaison et l’alerte du poste reprennent l’affichage IceMan : couleurs, alignement et sauts de ligne. L’identité Indicatif / Nom / Rôle reste celle du téléphone. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.134 — 2026-09-16

### Correction — Pages du téléphone en texte simple

Messagerie, connexion, comptes-rendus, réglages, wiki et retours d’écran s’affichent en texte simple. Plus de mise en forme fragile à l’ouverture d’une page.

---

## Athena 1.0.133 — 2026-09-16

### Correction — Textes de la carte

Les cartouches grille / identité, la barre de liaison et l’alerte du poste s’affichent en texte simple. Plus de mise en forme fragile qui fermait le jeu à l’ouverture du téléphone.

---

## Athena 1.0.132 — 2026-09-16

### Correction — Menu dans l’écran du téléphone

Le menu d’applications reste dans l’écran, à droite de la carte. Il ne flotte plus à côté du boîtier. Refermé, il disparaît et la carte reprend toute la largeur. Sur l’accueil, l’encart d’identité ne recouvre plus le bureau. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.131 — 2026-09-15

### Correction — Menu d’applications

Le menu d’applications se déroule de nouveau : retour et défilement sont utilisables. Les boutons du bas restent sous le menu. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.130 — 2026-09-15

### Changement — Écran d’accueil

L’écran d’accueil du téléphone n’affiche plus les raccourcis Connexion Athena, messagerie, Resynch et les autres icônes COMSPEC. Ces fonctions restent dans le menu d’applications et dans le menu ACE. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.129 — 2026-09-15

### Correction — Alerte plein écran

Une alerte envoyée depuis le poste recouvre tout l’écran du téléphone, y compris le menu d’applications s’il était ouvert. Le titre et le texte restent lisibles. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.128 — 2026-09-15

### Correction — Boutons du tiroir d’applications

À l’ouverture du menu d’applications, les boutons du bas (photos, recherche, radio) restent collés sous le tiroir. Ils ne recouvrent plus la carte.

---

## Overwatch 1.5.78 / Athena 1.0.127 — 2026-09-15

### Correction — Fermeture brutale avec le téléphone ouvert

Avec le téléphone ATAK ouvert et la liaison active, le jeu pouvait se fermer tout seul. Le cadre de l’écran est figé. Relancez Arma complètement après la mise à jour.

### Correction — Messagerie

Depuis un canal, le retour ramène à la liste des canaux.

### Nouveau — Overwatch Beta et téléphone

Le poste envoie une alerte plein écran, une tâche de groupe, des points à atteindre et un ralliement. Cap, vitesse, altitude et liaison du terrain s’affichent sur la fiche contact.

---

## Athena 1.0.127 — 2026-09-15

### Correction — Écran d’accueil et cartouches carte

Les boutons masqués de l’écran d’accueil ne se calent plus à une taille nulle. Les cartouches de la carte n’interprètent plus un nom d’opérateur comme du texte enrichi. Relancez Arma complètement après la mise à jour.

### Amélioration — Overwatch Beta : cap, vitesse et liaison du terrain

Le poste Overwatch Beta affiche désormais le cap, la vitesse, l’altitude, l’état de liaison et le mode de transmission réellement remontés depuis Arma. Ce sont les mêmes données que celles déjà envoyées par le téléphone.

---

## Athena 1.0.126 — 2026-09-15

### Correction — Fermeture brutale du jeu avec le téléphone ouvert

Avec le téléphone ATAK ouvert et la liaison active, le jeu pouvait se fermer tout seul, sans message. Le cadre de l’écran est désormais figé : la carte et le tiroir d’applications ne se calent plus hors limites. Relancez Arma complètement après la mise à jour.

---

## Portail · Overwatch Beta — 2026-09-14

### Nouveau — Détection des marqueurs posés en jeu

Les gestionnaires de la communauté décrivent quels marqueurs posés dans Arma 3 doivent être suivis : un libellé, un symbole, un rayon. Dès qu’un opérateur pose un point correspondant, il apparaît au poste. Si la règle le demande, le point est confirmé lorsqu’un téléphone ATAK entre dans le rayon, et les opérateurs sont prévenus sur leur écran. Les points d’objectif libellés PO restent suivis comme avant. Relancez Arma complètement après la mise à jour du pack jeu.

### Nouveau — Alerte plein écran sur les téléphones ATAK

Le commandement envoie une alerte qui recouvre tout l’écran du téléphone ATAK. Le message s’affiche aussi lorsque le téléphone est en position mini dans le coin de l’écran. L’opérateur le lit, puis appuie sur Fermer, ou l’alerte disparaît après quelques secondes. Relancez Arma complètement après la mise à jour du pack jeu.

### Nouveau — Points de ralliement

Le commandement pose un point de ralliement depuis Overwatch Beta. Un clic place un lieu de regroupement avec un anneau de 50 mètres, visible au poste et en jeu. Les opérateurs réellement présents dans le rayon sont indiqués. Ce n’est pas un point à atteindre : c’est un lieu où se rassembler.

### Nouveau — Tâches de groupe

Le commandement transmet une tâche à un groupe depuis Overwatch Beta. Le groupe, le type d’action, l’urgence et éventuellement un point à atteindre se choisissent dans des listes. La tâche arrive sur les téléphones ATAK des opérateurs concernés. Le poste suit le statut et peut annuler.

### Nouveau — Poser des points à atteindre

Le commandement pose une suite de points à atteindre depuis Overwatch Beta. Chaque point est numéroté, un anneau de 20 mètres s’affiche, et le point est confirmé dès qu’un téléphone ATAK y entre. La suite est transmise aux opérateurs pour le guidage en jeu.

### Nouveau — Points d’objectif PO confirmés à 20 m

Les marqueurs libellés PO deviennent des points d’objectif. Dès qu’un opérateur ATAK entre dans un rayon de 20 mètres, le point est confirmé atteint. Le poste affiche l’anneau et l’indicatif. En jeu, l’anneau passe au gris.

### Nouveau — Visée, anneaux de portée et suivi

Vous vérifiez si le relief masque une visée. Des anneaux de portée s’affichent autour du contact ouvert. Le poste peut suivre un opérateur. Un tracé se retire. La grille part sur le canal. Les contacts se filtrent par ami, hostile ou inconnu.

### Nouveau — Groupes reliés, mesures et dessins

Les opérateurs d’un même groupe sont reliés sur la carte. Un clic sur un contact affiche les distances vers ses coéquipiers, ainsi que le cap et la vitesse s’ils sont transmis. Vous mesurez cap, distance et surface. Vous dessinez un cercle, un rectangle, un croquis ou un texte, avec la couleur choisie dans les réglages.

### Nouveau — Appui, renseignement et outils de poste

Sur Overwatch Beta, le commandement prépare une 9-Line complète, ouvre une évacuation avec le triage, consulte les fiches de renseignement et le journal de mission. Un temps de parcours à pied ou en véhicule se calcule sur la carte. Le profil de relief s’affiche si le théâtre a déjà été relevé.

### Amélioration — Zones, météo, replay et personnalisation

Une alerte signale l’entrée ou la sortie d’une zone. La météo transmise par la mission apparaît en overlay. Le replay parcourt les trajectoires déjà enregistrées. Les symboles, couleurs et largeurs de colonnes restent mémorisés sur cet ordinateur.

### Correction — La carte du poste Beta s’affiche

Overwatch Beta a son propre écran, distinct de la carte ATAK habituelle. La carte de l’opération est visible. Les réglages sont à gauche, le tchat de mission à droite. Un avertissement s’affiche à la première ouverture.

### Nouveau — Trois lectures de fond Altis

Dans les réglages du poste Beta : Classique, Aerial avec relief renforcé, et Noir et blanc. Le choix est mémorisé sur cet ordinateur. Les contacts et les symboles ne changent pas.

---

## Athena 1.0.124 — 2026-09-14

### Correction — Messagerie : retour à la liste des canaux

Après avoir ouvert un canal, **Retour aux canaux** ramène à la liste. Le bandeau du bas n’est plus recouvert par Live Feed. Relancez Arma complètement.

---

## Portail · Carte tactique — 2026-09-13

### Correction — La page s’ouvre à nouveau

Opérations → Carte tactique s’affiche de nouveau. Un incident empêchait l’ouverture de la page.

---

## Portail · Intégrations — 2026-09-13

### Nouveau — Photos Quick Picture vers Discord

Sur Intégrations, vous pouvez envoyer les photos Quick Picture (téléphone en jeu) vers un salon Discord de la communauté. Chaque événement a trois choix clairs : ne pas publier, salon commun, ou un autre salon.

---

## Overwatch 1.5.77 / Athena 1.0.123 — 2026-09-14

### Amélioration — Pack Overwatch, Athena et liaison à jour

Tout le pack Overwatch, le téléphone Athena et la liaison sont livrés ensemble. En jeu : le numéro de carte de l’opération, la reprise des envois après un plantage, le Super ping, et les correctifs Quick Picture déjà décrits.

---

## Portail · Carte ATAK — 2026-09-14

### Nouveau — Photo aérienne d’Altis

Sur Altis, le poste peut afficher la photo aérienne à la place du plan. Le calque s’aligne sur la carte de l’opération. Le téléphone en jeu continue d’utiliser le plan Overwatch.

### Nouveau — Calques de fond dans les réglages

Dans Réglages du poste, vous choisissez le fond de la carte : Plan, Carte du jeu, ou Photo aérienne. Les positions et les tracés restent en place.

---

## Portail · Carte ATAK — 2026-09-13

### Correction — Appuis ouvre la 9-Line

Un clic sur Appuis affiche de nouveau le module d’appui aérien. La 9-Line et les demandes en cours apparaissent, ou un état vide pour en créer une. Le poste ouvre ce module pour tout le monde.

### Nouveau — Relecture par date et par opérateur

Dans Journal → Relecture, vous pouvez choisir la journée et l’opérateur à suivre. La chronologie, la carte et les événements clés se limitent à ce filtre. Le type d’événement (contacts, ordres…) reste disponible.

### Correction — Fiches de renseignement lisibles

Le type de fiche et l’urgence s’affichent de nouveau en toutes lettres dans leur case, au lieu de grandes barres vides qui poussaient le texte hors du panneau.

### Nouveau — Fenêtres épinglées à gauche

Le tchat, la radio, la liaison, les ordres, le médical et les pings peuvent être épinglés. Ils restent visibles en raccourci à gauche de la carte lorsque vous changez de domaine. Le tchat épinglé garde les derniers messages et le champ d’émission. Jusqu’à trois raccourcis, mémorisés sur le poste.

---

## Overwatch 1.5.76 / Athena 1.0.122 — 2026-09-13

### Nouveau — Plusieurs opérations en parallèle

Chaque mission indique son numéro de carte Athena. Deux opérations ne mélangent plus leurs positions.

### Amélioration — File d’attente conservée après un redémarrage

Les envois qui n’avaient pas encore atteint le poste sont repris après un plantage ou un redémarrage d’Arma.

---

## Overwatch 1.5.75 / Athena 1.0.121 — 2026-09-13

### Correction — Quick Picture : Discord retrouve la photo

Discord reçoit de nouveau la photo prise depuis Quick Picture. Le dossier indiqué pour l’envoi n’existait pas, alors que le cliché était bien enregistré. Le poste continue de recevoir la vue.

---

## Overwatch 1.5.74 / Athena 1.0.120 — 2026-09-13

### Correction — Quick Picture vers Discord

Une photo prise depuis Quick Picture continue d’être transmise vers Discord. Overwatch n’interrompt plus cet envoi. Le poste reçoit toujours la vue.

---

## Overwatch 1.5.73 / Athena 1.0.119 — 2026-09-13

### Nouveau — Super ping

Un Super ping place un pulse animé sur la carte : au poste et sur le téléphone. Clic droit → Super ping au poste. Maj + clic gauche sur la carte du téléphone. Les cercles s’agrandissent quelques secondes, visibles de tous.

---

## Overwatch 1.5.72 / Athena 1.0.118 — 2026-09-13

### Nouveau — Zone de déplacement depuis la dernière position

Sur le téléphone ATAK, un contact vu dans les quinze dernières minutes reste visible à sa dernière position connue s’il vient de perdre la liaison. Un clic sur ce contact, ou dans la liste des effectifs, dessine deux cercles qui s’agrandissent : à pied et en véhicule. Au poste, le même dessin s’ouvre en cliquant l’indicatif.

---

## Overwatch 1.5.71 / Athena 1.0.117 — 2026-09-13

### Correction — Quick Picture

Chaque photo prise depuis Quick Picture part au poste. La suivante n’est plus traitée comme un doublon de la vue précédente.

---

## Overwatch 1.5.70 / Athena 1.0.117 — 2026-09-13

### Nouveau — Messagerie : d’abord les canaux

En ouvrant Messagerie, la liste des canaux s’affiche avec le nombre de messages non lus. La section Création en bas permet d’ouvrir un nouveau canal.

### Nouveau — Fil complet d’un canal

Un clic ouvre tout le fil. Retour à la liste par Canaux. Date, retour à la ligne, couleurs selon l’auteur et le canal.

### Amélioration — Messages limités et lisibles

Un message tapé dans le téléphone est limité à 100 caractères et passe à la ligne.

---

## Overwatch 1.5.69 / Athena 1.0.116 — 2026-09-13

### Correction — Barre de liaison en bas

La barre OK / NOK se replace en bas de la carte du téléphone, y compris avec l’interface tablette actuelle.

### Correction — Menu ACE Athena rangé

Les anciennes actions à plat sous COMSPEC Athena sont retirées. Les rubriques reviennent au premier niveau.

---

## Overwatch 1.5.68 / Athena 1.0.115 — 2026-09-13

### Correction — Pack complet rechargé

Le pack Workshop / FN inclut maintenant toutes les nouveautés du jour. Rechargez le pack et quittez Arma complètement.

### Nouveau — Supprimer un canal dans Messagerie

Le bouton Supprimer retire le canal radio personnalisé sélectionné. Les canaux système restent protégés. L’historique disparaît aussi du journal du poste.

### Correction — Messagerie et transmissions après connexion

Après une connexion Athena par mot de passe, les messages et les autres envois vers le poste restent autorisés. Plus de session refusée juste après l’ouverture du canal.

### Amélioration — Boutons Messagerie

Créer, Supprimer, Envoyer et Effacer l’affichage local répondent de façon fiable au clic.

---

## Overwatch 1.5.56 / Athena 1.0.101 — 2026-09-12

### Amélioration — Découpage d’étage sous JVN

Sur un bâtiment désigné, le découpage d’étage (désactivé par défaut) coupe la silhouette au plafond choisi, met en évidence la dalle et marque les points intérieurs. ACE permet de changer d’étage. Les murs ne s’ouvrent pas (limite du moteur) ; ce n’est plus un réglage « à venir ».

---

## Overwatch 1.5.55 / Athena 1.0.101 — 2026-09-12

### Amélioration — Messagerie : canaux, fil et Groups IceMan

Dans Messagerie, vous créez un canal radio personnalisé depuis le téléphone. Le fil indique clairement De / Vous / Du poste avec un meilleur contraste. L’entrée IceMan Groups / Group Messages disparaît du tiroir : l’opérateur arrive sur Messagerie COMSPEC, en français.

---

## Overwatch 1.5.54 / Athena 1.0.99 / liaison 2.0.32 — 2026-09-12

### Correction — Marqueurs téléphone → carte du poste

Les repères posés sur le téléphone (INF, Marker Widget, cTab) remontent sur la carte Athena dès que le canal poste est ouvert, même si Effectifs est encore vide. Une liaison momentanément dégradée ne fait plus perdre le marqueur.

---

## Overwatch 1.5.52 / Athena 1.0.97 — 2026-09-12

### Correction — Position invisible au poste malgré liaison OK

Dès que le canal poste est ouvert, la position part automatiquement vers le poste. Vous apparaissez dans Effectifs sans ouvrir Transmettre. Si un frein temporaire s’applique, la fiche Athena l’indique clairement.

---

## Overwatch 1.5.49 / Athena 1.0.93 — 2026-09-12

### Nouveau — Messagerie ATAK avec canaux

Application **Messagerie** COMSPEC dans le téléphone : canaux radio (Groupe, Commandement, Général, JTAC, Air + personnalisés), fil par canal, envoi vers le poste. Libellés en français. Groups / Group Messages IceMan est masqué au profit de Messagerie.

---

## Overwatch 1.5.43 — 2026-09-11

### Nouveau — Affichage situation sous toutes les JVN

Sous jumelles de vision nocturne, les alliés, marqueurs et véhicules proches apparaissent dans le champ de vision avec la distance. Depuis ACE, **Marquer ce bâtiment** dessine la silhouette du bâtiment regardé. Si le mod F-PANO ECOTI est déjà chargé, Overwatch n’affiche pas le sien. Réglages : Options → Extensions → COMSPEC Overwatch → Affichage situation. Relancer Arma complètement.

---

## Athena 1.0.88 / Overwatch 1.5.37 / liaison 2.0.27 — 2026-09-11

### Correction — Tenues communauté denses et collections

Dans l’arsenal Athena, la première collection s’ouvre dès l’affichage. L’aperçu montre lunettes, jumelles, JVN, radio et le contenu des poches. Les tenues très chargées se chargent correctement (plus de blocage « trop volumineuse »). La liste communauté n’est plus coupée quand il y a beaucoup de tenues. Relancer Arma complètement.

---

## Athena 1.0.88 / Overwatch 1.5.36 / liaison 2.0.26 — 2026-09-11

### Nouveau — Canaux radio, Wave, zones de vue

Canaux radio (Groupe, Commandement, Général, JTAC, Air + personnalisés), pastilles Wave sur la carte, ETA d’itinéraire, zones de vue terrain vers le poste.

### Nouveau — Barre de liaison OK / NOK

Sous la barre d’état du téléphone : OK ou NOK, débit estimé, taux de perte ; teinte de l’icône signal.

### Amélioration — Première liaison, Paramètres, arsenal

Parcours Appairage clarifié, Paramètres / Athena plus lisibles, arsenal Mes tenues / Communauté, remontée forcée du temps de mission.

### Correction — Radio, fantômes carte, Appairer

Journal radio jeu ↔ poste rétabli ; opérateurs hors liaison retirés de la carte ; canal poste rouvert après Appairer ; temps de mission à nouveau remonté.

Relancer Arma complètement. Portail : Ctrl+F5 + migrations si besoin.

---

## Athena 1.0.83 / Overwatch 1.5.27 — 2026-09-11

### Correction — Indicatif, nom, rôle et groupe BFT

Sur la carte, les trois lignes d’identité (indicatif, nom, rôle) restent visibles en bas à droite. L’identifiant de groupe saisi dans les données techniques (ex. Alpha 2-2) est synchronisé vers le suivi d’effectif et remonté au poste. Relancer Arma complètement après mise à jour du pack et de la liaison.

---

## Athena / Overwatch — 2026-09-11

### Correction — Effectifs réellement en liaison

La position d’un joueur est désormais transmise même si son identifiant Steam tarde à être disponible après une arrivée en cours de partie ; la liaison Athena authentifiée suffit pendant ce bref délai. Sur la carte web, les joueurs déconnectés disparaissent dès leur passage hors liaison, tandis que les dernières positions des IA suivies restent disponibles pour le COP. Relancer Arma complètement après la mise à jour du mod.

---

## Athena — 2026-09-10

### Correction — Encart d’identité sur la carte

Sur la carte du téléphone, un encart se superpose comme les outils carte : indicatif, nom, groupe, fonction et position. Il reste en bas à gauche, au-dessus des outils, hors du tiroir. Le menu d’applications est désormais reconstruit à chaque nouvelle ouverture d’écran et l’identité native reste visible en secours si l’encart ne peut pas être créé. Relancer Arma complètement.

---

## Portail 1.5.98 — 2026-09-04

### Administration — remise à zéro ATAK et Steam

L’effacement des remontées ATAK propose maintenant une option explicite pour désynchroniser tous les comptes Steam de la communauté. Lorsqu’elle est cochée, les identifiants sont retirés et les sessions de jeu ainsi que les associations de poste sont révoquées ; les opérateurs doivent ensuite refaire leur liaison.

---

## Portail 1.5.97 — 2026-09-03

### Correction — Grandes photos terrain

Les captures PNG Arma de plus de 16 Mo sont désormais acceptées par le poste de commandement. Le plafond passe à 96 Mo par image, avec la marge nécessaire au formulaire d’envoi. Déployer le portail et laisser PHP recharger sa configuration.

## Overwatch 1.5.16 — 2026-09-03

### Correction — Photos en rafale

Une photo prise depuis le téléphone n’écrit plus une série de copies identiques dans le dossier Captures. Un déclenchement = un fichier. Relancer Arma complètement.

### Correction — Identifiant de groupe

L’identifiant du groupe en jeu reprend l’indicatif de l’opérateur lorsque le champ contenait encore le nom de profil. Un nom de groupe déjà choisi n’est pas modifié. Relancer Arma complètement.

---

## Athena 1.0.80 — 2026-09-03

### Correction — Photos en rafale

Une photo prise depuis le téléphone n’écrit plus une série de copies identiques dans le dossier Captures. Relancer Arma complètement.

### Correction — Bandeau sous l’heure

Sous l’heure du téléphone, une bande noire affiche l’indicatif, le rôle, la grille et la radio.

### Changement — Tuile Athena

La tuile Athena ne garde qu’un bouton Connexion, qui devient Liaison OK une fois le compte associé. Un journal court indique si Steam n’est pas lié ou si le compte n’est pas connecté. Quand tout est en ordre, le nom, le prénom, le rôle, la fonction et l’affectation s’affichent. Relancer Arma complètement.

---

## Overwatch 1.18.10 — 2026-09-03

### Correction — Photos terrain refusées

Les photos prises depuis le téléphone arrivent de nouveau au poste. Le cliché n’est plus refusé alors qu’il existe déjà sur le PC. Relancer Arma complètement.

---

## Overwatch 1.5.15 / SSE 0.7.20 — 2026-09-03

### Correction — Cockpit Hatchet

En s’asseyant aux commandes d’un appareil Hatchet, le menu d’actions personnelles se referme. Clic et molette reviennent au tableau de bord : batterie, groupe auxiliaire et démarreurs répondent de nouveau. Relancer Arma complètement.

---

## Overwatch 1.18.9 — 2026-09-03

### Correction — Identifiant Steam sur la liaison de secours

L’identifiant Steam reste associé au lancement, y compris si la liaison passe par le chemin de secours. Une position sans identifiant n’est plus envoyée : le jeu réessaie dès que Steam est prêt. Le journal Liaison n’enregistre plus qu’un refus « identifiant manquant » de temps en temps, plus une ligne toutes les secondes. Relancer Arma complètement.

---

## Athena 1.0.79 — 2026-09-03

### Correction — Bouton zoom et bandeau d’identité

Le bouton moins qui se décalait en haut à droite de la carte est retiré. Sous la météo et l’heure, une bande noire affiche l’indicatif, le rôle, la grille et la fréquence radio. Relancer Arma complètement.

---

## Athena 1.0.78 — 2026-09-03

### Correction — Carte du téléphone

Le cartouche Indicatif / Rôle / Groupe / Grille est de nouveau visible, en bas à gauche, au-dessus des outils carte. Le menu qui s’ouvrait au clic droit et les boutons Mesure, Grille, Itinéraire, Zone, Couches et Signet sont retirés. Les outils carte du téléphone restent affichés, sans clignoter. Relancer Arma complètement.

---

## Athena 1.0.77 — 2026-09-03

### Correction — Liaison Steam, fiche opérateur, photos

La session Athena s’ouvre automatiquement lorsque Steam est déjà associé au compte. La fiche opérateur se transmet de nouveau. Les photos prises sur le terrain partent au poste.

### Correction — Carte du téléphone

Le bouton des outils carte retrouve son aspect d’origine. Le cartouche Indicatif / Rôle / Groupe s’affiche en haut à gauche, sous la boussole, sans recouvrir les outils.

### Amélioration — Page Athena

Journal, Alerter, Rapporter et Poste occupent chacun leur écran. Les boutons ne se superposent plus.

### Nouveau — Carte : filtres, mesure, signalements

L’indicatif d’un opérateur se lit plus simplement quand on s’éloigne, et redevient détaillé en se rapprochant. Un opérateur sans position récente reste visible, plus pâle. Un clic droit pose un marqueur, mesure ou signale. Un ordre reçu se confirme depuis le bas de l’écran. Les outils carte du téléphone restent tels quels.

---

## Athena 1.0.76 — 2026-09-02

### Correction — Cartouche indicatif / rôle / groupe

Sur la carte du téléphone, le cartouche d’identité (indicatif, rôle, groupe, grille) est de nouveau en bas à gauche. Il n’est plus coincé sous le tiroir d’applications. Relancer Arma complètement.

---

## Portail 1.5.96 — 2026-09-02

### Correction — Barre de vue et bande Réseau / Journal

La barre N, 2D, 3D et Zoom reste à droite, sous Outils, sans passer derrière la carte. Réseau et Journal s’affichent dans une bande lisible en bas. Rechargez la page du poste.

---

## Portail 1.5.95 — 2026-09-02

### Correction — Fantômes hors liaison

Un opérateur bloqué à l’écran de connexion, ou sans signal récent, n’est plus affiché En liaison. Les anciennes positions et les anciens indicatifs quittent la carte et le relief. Rechargez la page du poste.

---

## Portail 1.5.94 — 2026-09-02

### Amélioration — Certificats sur les fiches terminaux

L’onglet Terminaux du poste affiche l’état du certificat, sa référence et son échéance. Un compte connecté peut en émettre un nouveau ; l’ancien n’est plus accepté. Rechargez la page du poste.

---

## Overwatch 1.5.12 — 2026-09-02

### Correction — Charge Uniquement depuis ATAK

Une charge réglée sur Uniquement depuis ATAK saute désormais en jeu quand vous la déclenchez depuis la tablette ou le poste. Le poste n’affiche « A explosé » qu’après. Le choix n’apparaît plus deux fois dans ACE. Relancer Arma complètement.

---

## Carte du poste — 2026-09-02

### Correction — Opérateurs illisibles sur la carte

Les opérateurs en liaison affichent de nouveau leur indicatif sous le symbole, y compris à plat. Rechargez la page du poste.

---

## Overwatch 1.5.11 / Athena 1.0.75 — 2026-09-01

### Amélioration — Compte non connecté

Sans compte associé, un bandeau ambre « Compte non connecté » s’affiche sur la carte, au-dessus de l’indicatif, et en haut de la tuile Athena. Relancer Arma complètement.

---

## [1.5.51] / Overwatch 1.5.10 / Athena 1.0.72 — 2026-09-01

### Corrigé — Boussole et outils carte

La boussole en haut à gauche et les outils carte en bas à gauche ne sont plus recouverts. Les cartouches de grille et d’unité passent à droite. Relancer Arma complètement.

---

## [1.5.50] / Overwatch 1.5.0 / Athena 1.0.63 — 2026-09-01

### Corrigé — Identité, chat et journal

Le pack affiche l’opérateur, pas le titre de la communauté. Relancer Arma complètement.

- Écran Environnement prêt : photo si elle existe, prénom et nom, indicatif, rôle, grade et fonction.
- Téléphone Paramètres et bandeau de carte : indicatif de la fiche Effectifs.
- Les messages du poste restent dans le téléphone et le journal ; le chat de bord d’Arma n’est plus recopié.
- Journal Athena du téléphone : filtre, liste et détail lisibles.

### Carte du poste (web)

- Les symboles des opérateurs suivent la taille d’icône et de libellé des réglages.
- Le journal d’activité n’ajoute plus une carte à chaque position reçue.
- Si la liaison est bonne mais qu’un accès manque, une fenêtre permet de demander les autorisations.

---

## [1.5.48] / Overwatch 1.4.97 / Athena 1.0.58 — 2026-09-01

### Nouveau — Lecture de la tablette ATAK (IceMan)

Sur la tablette ATAK Enhanced, la carte se lit désormais comme un poste de terrain : fond charbon, chiffres cyan, cartouches sous le curseur et sur l’unité suivie. Le tiroir d’applications à droite n’est pas recouvert. Relancer Arma complètement.

- Sous le curseur : grille, distance, altitude du sol, gisement, portée et écart d’altitude.
- Sur l’unité suivie : groupe, indicatif, grille, altitude, vitesse et heure.
- Cap en degrés vrais en haut à gauche, zoom plus et moins sur le bord de la carte.
- Le tiroir d’applications, Drone Ops et les fenêtres caméra déjà présentes reprennent le même charbon / cyan.

### Nouveau — Connexion Athena avant la session

Au menu principal, l’opérateur s’identifie (e-mail, code temporaire, ou Steam déjà associé). La communauté, l’indicatif et les habilitations arrivent tout seuls. Rien n’est transmis tant que l’environnement n’est pas prêt.

### Carte du poste (web)

- Les rapports (observation, situation, renseignement immédiat, contact) apparaissent en pastilles compactes, comme en mission : type en capitales, barre colorée, temps écoulé.
- L’outil Route trace un itinéraire : les opérateurs le voient en jeu, les points déjà atteints passent en gris.
- La barre Position / Annoter / Tracer reste visible ; seul Masquer la replie, Outils la ramène.
- Réglages du poste : icônes de la communauté visibles ; le gestionnaire ouvre la bibliothèque pour en choisir ou en ajouter.
- Terminaux : l’état de liaison reste calé à droite ; la version Overwatch se lit sur sa propre ligne.

---

## [1.5.48] / Overwatch 1.4.96 / liaison 1.17.9 — 2026-09-01

### Pack Overwatch 1.4.96 — tablette, Zeus, arsenal

- La tablette ATAK Enhanced se charge à nouveau avec le pack actuel.
- Zeus : SSE, ATAK et OVERWATCH tiennent en haut du panneau d’édition d’une unité, plus sur les filtres des objets éditables.
- Arsenal ACE : bandeau des tenues Athena en haut de l’écran, sans masquer « Mes équipements ».
- Relancer Arma complètement.

---

## [1.5.48] / Overwatch 1.4.95 / liaison 1.17.9 — 2026-08-30

### Pack Overwatch 1.4.95 — guidage GPS + zones ATAK

Itinéraire posé au poste : points numérotés et trait visibles en jeu. Zones tactiques (poser, danger, ralliement) synchronisées ; alerte à l’entrée d’une zone dangereuse. Relancer Arma complètement.

---

## [1.5.34] / Overwatch 1.4.67 / extension 2.0.14 — 2026-08-24

### Corrigé — météo en erreur rouge au spawn

Le bandeau météo n’est plus envoyé pendant le handshake. Un timeout ou un refus temporaire ne s’affiche plus comme une panne. Dès que le poste a bien reçu, le terminal s’arrête de renvoyer le même ciel.

---

## [1.5.33] / Overwatch 1.4.66 / extension 2.0.13 — 2026-08-24

### Corrigé — rafale 0 / -1 / 503 au spawn

Si Athena ne répond pas tout de suite (timeout, saturation), le terminal ne réessaie plus en boucle les caméras, la météo, les photos et la position. Il attend quelques secondes, puis reprend. Une photo refusée pour saturation part dès que le poste respire, sans marteler le même cliché. Le journal ne traite plus ça comme une panne rouge.

---

## [1.5.32] / Overwatch 1.4.65 — 2026-08-24

### Corrigé — IA alliée sur l’ATAK

Le suivi posé depuis Zeus reste après la fermeture du curateur. On peut le retirer (menu « Retirer l’IA de l’ATAK » ou case décochée), sans étendre tout le groupe.

---

## [1.5.31] / Overwatch 1.4.64 / Athena ATAK 1.0.46 — 2026-08-24

### Amélioré — sons ATAK (web et jeu)

Nouveau pack d’alertes, identique sur la carte web et en mission : bip radio court, carillon de réception d’ordre, confirmation d’acceptation, transmission de renseignement, signal médical Motorola (trois fois), démarrage.

---

## [1.5.30] / Overwatch 1.4.63 / extension 2.0.12 — 2026-08-24

Vague 2026.08c — voir SPOTREP #00001 pour le détail opérateur.

- Poste de situation (dossiers SSE + localisation téléphone)
- Parc de terminaux (retrait appareils / sessions web)
- Relecture : joueurs, IA alliées, téléphones, GPS
- Relief autour de l’équipe (`getTerrainHeightASL`), plus de spam 401
- Overlays liaison, Zeus SSE/ATAK/Overwatch, proximité téléphone

---

## [1.4.50] / extension 2.0.10 — 2026-08-23

### Corrigé — lancement et signalement

Fenêtre Windows unique au menu principal (conditions d’utilisation + disclaimer bêta). Plus de parade de dialogues en mission. Le signalement in-game s’ouvre depuis Échap → gestion du mod et part bien vers l’équipe.

---

## [1.4.49] / Athena ATAK 1.0.42 — 2026-08-23

### Corrigé — gel à la photo ATAK

Les clichés ATAK (JPEG BCE / IceMan) ne déclenchent plus un second `screenshot` PNG synchrone sur le thread jeu. Aligné sur le flux SOAR Discord : un JPEG, envoi en file. Un PNG de repli n’est pris que si le fichier JPEG est vraiment introuvable, hors de la frame du clic.

---

## [1.4.19] - 2026-08-18

### Ajouté — Fiches de renseignement simplifiées

Entre « rien à signaler » et « j'ouvre un dossier d'intérêt », il manquait la marche la plus basse : noter tout de suite ce qu'on vient de voir, sans formulaire à remplir et sans rien conclure. Une plaque relevée, une attitude inhabituelle, un axe soudain désert, une conversation avec un habitant — autant d'éléments qui se perdaient parce que les consigner coûtait plus cher que de les oublier.

- **Menu dédié RENS dans le tiroir ATAK.** Le rédacteur s'ouvre en plein cadre sur **toute la surface de l'ATAK**, pas dans un panneau de téléphone : on choisit ce menu pour écrire, pas pour lire. Également accessible par l'icône « Fiche RENS » de l'écran d'accueil ATAK et par l'action ACE « Rédiger une fiche de renseignement… ».
- **Rédacteur identique dans le portail** (`Pilotage → Fiches de renseignement`) : même disposition, mêmes libellés, mêmes limites. Un opérateur qui passe du jeu au portail ne réapprend rien.
- **Cinq informations, pas une de plus** : un texte libre de 1000 caractères avec compteur vivant, une date, un lieu, un à quatre thèmes, et jusqu'à quatre pièces jointes. Le reste — classement, qualification, rapprochements — se fait ensuite au bureau.
- **Cinq types de fiche** (mission, observation, contact, ambiance, technique) et **douze thèmes** colorés par gravité, qui orientent la fiche vers le bon analyste.
- **Pièces jointes depuis le terrain** : capture de la scène, photographie déjà prise dans la bibliothèque ATAK, ou relevé de position et d'instant. Depuis le portail : photo, capture, PDF ou texte.
- **Suivi côté bureau** : prise en compte, exploitation ou classement sans suite, avec une phrase de justification, et rattachement facultatif à un dossier validé. Le rattachement fait apparaître la fiche dans le dossier sans rien conclure sur les personnes citées.
- Chaque fiche alimente le **journal des transmissions terrain** et le journal d'activité, comme les fiches personnes et les sites.

### Choix de conception

- **Le brouillon survit à la fermeture du rédacteur.** Une fiche est presque toujours interrompue — contact, déplacement, ordre radio. Perdre la saisie à chaque fermeture aurait appris aux opérateurs à ne plus rien rédiger, ce qui est exactement l'inverse du but.
- **Les captures d'écran sont prises après la fermeture du rédacteur.** Prises pendant, elles n'auraient montré que l'interface. Le rédacteur se referme, la scène redevient visible, la capture part.
- **Une clé d'idempotence par fiche.** Une double validation, ou une retransmission après coupure de liaison, retombe sur la même fiche. Sans elle, le bureau recevrait deux fois le même constat — précisément ce que l'automatisme de détection de doublons signale ensuite comme suspect.
- **Liaison coupée : la fiche est conservée, pas perdue**, et le message le dit ainsi. L'annoncer comme un échec pousserait à ressaisir, et deux fiches du même constat arriveraient au rétablissement.
- **Le référentiel des libellés est dupliqué côté jeu**, pour rester lisible sans liaison. L'ordre des thèmes est contractuel — chaque bascule du rédacteur est câblée sur un rang, pas sur un code — et un test compare les deux référentiels pour empêcher la dérive.
- **Une fiche n'identifie personne et ne vaut pas preuve.** Le rappel figure dans le rédacteur, dans la file et sur la fiche : c'est un constat daté et situé, rien de plus.
- **Les pastilles de thème sont de vrais contrôles**, pas du texte structuré : Arma ne sait pas peindre un fond derrière un fragment de texte, et des étiquettes sans couleur auraient perdu la lecture d'un coup d'œil qui fait tout l'intérêt du bandeau.

---

## [1.4.14] - 2026-07-30

### Ajouté — Corrélation d'exploitation

- **Graphe de corrélation du dossier** (`Dossiers → Voir les corrélations`) : personnes, sites, pièces et saisies, classés par nombre de liens. Trois provenances distinguées visuellement et jamais confondues — **déduit** (recalculé depuis les saisies), **automatisme** (posé par une règle), **analyste** (hypothèse assumée).
- Les liens déduits ne sont pas stockés : corriger une fiche corrige le graphe. Un lien stocké se périmerait dès qu'une saisie est rectifiée, et personne ne penserait à aller le corriger.
- **Pose de relation à la main** entre deux personnes du dossier, avec nature du lien, niveau de fiabilité et justification. Retirable.
- Table `sse_relations`, clé unique par arête : réenregistrer la même relation met à jour la fiabilité et la note plutôt que de dupliquer.

### Ajouté — Automatismes SSE

Un automatisme propose, il ne décide pas. Aucune règle ne clôt un site, ne fusionne des fiches ni ne déclare une identité — une règle qui se trompe en silence coûte plus cher que dix rappels à faire à la main.

- **Classement automatique** — une fiche transmise sans code dossier rejoint le dossier ouvert **s'il n'y en a qu'un**. Avec plusieurs dossiers ouverts, la règle s'abstient : une fiche non classée se voit, une fiche mal classée passe inaperçue jusqu'au débriefing.
- **Doublon probable** — deux fiches portant le même relevé biométrique sont signalées et reliées par « même individu que ». Aucune fusion : elle détruirait la fiche la moins complète, qui est parfois celle qui porte l'observation utile.
- **Correspondance forte** — au-delà de 85 % de similarité avec une liste de surveillance, le dossier passe en exploitation et reçoit une note. Le libellé rappelle qu'un score n'est pas une identification.
- **Co-présence** — les fiches du même dossier saisies à moins de 45 minutes d'écart sont reliées, en « non vérifié » : c'est une proximité d'horodatage, pas un lien constaté. Plafonné à 5 liens par fiche.
- **Site prêt pour clôture** — checklist complète signalée au poste de commandement, sans clôturer.
- **Saisie sensible** — armement, munitions, supports numériques et documents remontent immédiatement, sans attendre la clôture du site.
- Chaque règle laisse une trace en clair dans le journal d'activité : on peut répondre à « pourquoi cette fiche est-elle dans ce dossier ? » sans lire le code. Les réponses d'API portent un champ `automation` déjà rédigé en français.

### Ajouté — Déclassification et caviardage

- **Version expurgée du dossier** (`Dossiers → Version expurgée`) : on choisit le niveau de diffusion visé, tout ce qui est au-dessus part au noir automatiquement. Cinq catégories caviardables — Identité, Lieu, Biométrie, Source, Horodatage — chacune avec son niveau minimal de lecture en clair.
- **La source est la catégorie la plus protégée** : on peut souvent dire *ce qui* a été trouvé sans dire *qui* l'a trouvé, l'inverse est rarement vrai.
- **Caviardage manuel** : noircir une zone précise sur une fiche précise, quel que soit le niveau, avec motif obligatoire — c'est lui qu'on relira pour décider de le lever. Levable à tout moment.
- La page ouvre par défaut sur le niveau le plus large, donc le plus caviardé : ouvrir l'écran ne doit jamais exposer plus que ce qu'on a demandé à voir. Un tableau annonce ce qui restera en clair **avant** de produire le document.
- **Le texte caviardé n'est jamais envoyé au navigateur.** La substitution est faite côté serveur. Un trait noir posé en habillage CSS laisserait le texte dans la page — copier-coller, code source, lecteur d'écran, cache — ce qui reviendrait à ne rien caviarder.
- La longueur des barres est quantifiée par pas de 4 et plafonnée : une barre exactement proportionnelle révélerait la longueur du nom, ce qui suffit souvent à identifier quelqu'un sur un dossier à trois personnes.
- Le caviardage est branché sur la source unique des deux comptes rendus : une catégorie ne peut pas être noircie dans le flash et lisible dans le compte rendu initial.
- La date de recueil reste, l'heure part : savoir « le 14 » n'a pas la même valeur que savoir « le 14 à 03h12 ».

### Ajouté — Habilitation de lecture SSE

- **Plafond d'habilitation par session** : le niveau de diffusion demandé sur l'écran de déclassification est rabattu sur ce que la session a le droit de lire. Le paramètre de l'adresse exprime une demande, il n'accorde rien — le forcer à la main sert la version autorisée et inscrit la tentative au journal.
- Trois permissions explicites (`atak.sse.clearance.encadrement`, `.confidentiel`, `.tres_restreint`), et à défaut un **report des rôles déjà en place** : administration → très restreint, gestion des dossiers → confidentiel, accès portail → encadrement, rien → interne. Sans ce report, la mise à jour mettrait tout le monde au plancher tant qu'un administrateur n'a pas assigné les nouvelles permissions.
- **Habilitation portée par un code d'accès invité**, choisie à l'émission. Par défaut Diffusion interne : un invité ne voit ni identité, ni lieu, ni source. On n'accorde jamais par défaut de valeur, seulement par défaut de refus.
- L'écran affiche l'habilitation **et d'où elle vient**. Une habilitation qu'on ne peut pas expliquer se conteste mal.
- Les niveaux au-dessus du plafond apparaissent verrouillés au lieu de disparaître : un refus doit se voir refusé, pas manquer.
- Un dossier tenu au-dessus de l'habilitation du lecteur est signalé. La consultation reste possible — verrouiller d'un coup des dossiers ouverts hier est un arbitrage d'exploitation, pas une décision technique.

### Corrigé

- **L'écran de déclassification ne vérifiait pas qui demandait quoi.** Le niveau était lu tel quel dans l'adresse : n'importe qui pouvant ouvrir un dossier, invité compris, obtenait la version intégrale en changeant un paramètre. Produire un document expurgé et restreindre qui peut le lire sont deux choses différentes ; la première seule ne protégeait rien.

### Ajouté — Diffusion dirigée des rapports tactiques (phase A)

- Le moteur de règles de diffusion **existait sans aucun appelant** : `atak_report_routing_rules`, `atak_report_routing_history` et `AtakReportRoutingRepository` étaient en place, avec conditions, destinataires, escalade et accusé de réception, mais rien ne les invoquait. Le chantier avait été commencé puis laissé avant branchement.
- Les règles s'appliquent désormais à la soumission d'un rapport tactique. Les destinataires apparaissent dans la réponse (`routed_to`) et sur la consultation du rapport (`routing`).
- **Pas d'interrupteur, volontairement** : sans règle enregistrée, le moteur ne route vers personne et n'écrit rien. Une table de règles vide *est* l'état désactivé, et ajouter un réglage donnerait deux endroits à vérifier quand un rapport n'arrive pas.
- Un échec de routage n'échoue jamais la soumission : le rapport est enregistré d'abord, la diffusion tentée ensuite et tracée si elle échoue. Perdre un compte rendu de contact parce qu'une règle est mal formée serait un échange calamiteux.
- **La cible a changé par rapport à la demande initiale.** Le branchement devait porter sur `atak_intel` ; cette table n'a ni `report_type`, ni `priority`, ni `tenant_id`, et la clé étrangère de l'historique de routage pointe sur `atak_tactical_reports`. Router `atak_intel` demanderait d'altérer une clé étrangère en base vivante et d'ajouter un cloisonnement à une table qui n'en a pas — ce n'est pas un branchement mais une phase de schéma, renvoyée à la suite du plan.

### Ajouté — Écran des règles de diffusion

- `/admin/atak-diffusion-rapports` : création, activation et suppression des règles. Sans lui, le moteur branché en phase A tournait à vide, faute de règle à appliquer.
- Conditions exprimées en clair — types de rapport, priorités, mots-clés — plutôt qu'en JSON brut. Aucune case cochée signifie « tous », ce qui est écrit sous chaque groupe.
- **Une règle sans destinataire est refusée** : elle donnerait l'illusion d'une diffusion en place tout en n'en produisant aucune.
- L'écran annonce lui-même qu'une liste vide est l'état normal après installation, pas une panne.
- **Les notifications ne sont pas émises, et l'écran le dit.** La diffusion enregistre qui doit lire et l'affiche sur la fiche du rapport ; l'envoi en jeu, par courriel ou vers Discord n'est pas branché. Mieux vaut le lire à l'écran que le découvrir en opération.

### Ajouté — Émission réelle des notifications de diffusion

- Une diffusion produit désormais une **notification effective**, écrite dans `atak_realtime_notifications` avec ses destinataires, sa position sur la carte et son urgence.
- **Une seule notification par rapport**, portant tous les destinataires — et non une par destinataire. Trois lignes identiques dans le bandeau pour un même compte rendu font passer l'alerte pour du bruit, et c'est le bruit qu'on finit par ignorer.
- L'urgence reprend celle du rapport : un contact `FLASH` s'affiche en critique, une routine en bas de pile. Un compte rendu immédiat n'a pas à ressembler à une routine.
- Expiration à deux heures : une alerte qui reste affichée une semaine devient un décor.
- **Nouvelle route de relève `GET /api/atak/notifications`.** `AtakNotificationRepository` avait `create()`, `listActive()` et `pollSince()` mais **aucune route ne l'exposait** : les notifications écrites n'étaient lisibles par personne. Sans cette relève, émettre revenait à écrire dans un tiroir fermé.
- Une notification qui échoue n'annule pas la diffusion — elle est tracée avec la mention explicite « destinataires à prévenir de vive voix », pour que le silence ne passe pas inaperçu.

### Corrigé — L'historique de diffusion affirmait des notifications jamais envoyées

- `createRoutingEntry()` inscrivait `notification_sent = 1` et le canal « in-game » alors qu'aucun envoi n'avait lieu. Le drapeau est désormais posé **après** émission réelle, par `markNotified()`, et décrit donc ce qui s'est passé.

### Corrigé — Angle mort du contrôle d'intégrité

- Le contrôle ne vérifiait que le premier argument de `view()`. Or le back-office passe la vraie vue par `'content' => …`, la mise en page seule étant en premier argument : **une vue de back-office absente échappait entièrement au contrôle**, alors que c'est précisément le cas qui produit une page blanche en HTTP 200. La couverture passe de 68 à 365 vues.

### Corrigé — Deux lectures inter-communautés sur les rapports tactiques

- `GET /api/atak/reports/{id}` chargeait le rapport **sans filtrer sur la communauté** : un identifiant deviné suffisait à lire le rapport d'une autre communauté.
- `POST /api/atak/reports/{id}/acknowledge` de même — et acquitter est un acte, pas une lecture.
- Les deux sont désormais cloisonnés. `atak_report_routing_history` ne portant pas de `tenant_id`, la lecture de l'historique de diffusion s'appuie sur le cloisonnement du rapport, ce qui est maintenant vrai et documenté à l'endroit qui en dépend.
- Même classe de défaut relevée sur `AtakPoiRepository` et `AtakMedevacRepository`, hors périmètre de cette phase et signalée dans le plan.

### Corrigé — Cinq appels réseau étaient rejetés en silence

`CfgRemoteExec >> Functions` est en `mode = 1`, c'est-à-dire liste blanche stricte : une fonction absente de la liste voit ses appels distants **rejetés sans message**. Cinq fonctions y manquaient alors qu'elles sont bien appelées via `remoteExec`.

- **`receiveOrder`** — les ordres émis n'arrivaient pas à leur destinataire en multijoueur.
- **`createRoleplayZoneFromZeus`** et **`createRoleplayZone`** — les zones posées depuis Zeus n'étaient jamais créées côté serveur.
- **`restoreAtakSession`** et **`clearDisconnectedAtakState`** — la reprise de session après plantage ne se faisait pas.

Chaque entrée porte un `allowedTargets` correspondant à la cible réelle de l'appel, au plus juste plutôt qu'au plus permissif.

- **Le moteur roleplay n'était pas activé sur les autres machines**, ce qui faisait paraître les zones inertes. L'activation passait par `remoteExecCall ["call", 0, true]`, or `Commands` est aussi en liste blanche et `call` n'y figure pas — l'appel était rejeté. L'y ajouter aurait ouvert l'exécution de code arbitraire à distance à n'importe quel client ; l'activation passe désormais par des variables publiques, ce qui couvre en plus les joueurs qui rejoignent en cours de partie — le drapeau JIP de l'ancien appel ne le pouvait pas, `jip = 0` étant posé dans la configuration.
- Seuls les deux réglages désactivés par défaut sont forcés. « Effets visuels de dégradation » est déjà actif par défaut : le forcer n'aurait touché que les joueurs l'ayant volontairement coupé.

### Corrigé — Administration : l'audit système était inaccessible

- **`SystemAuditController` ne se chargeait pas** : une méthode `rollback()` y était déclarée deux fois — un accesseur privé vers le service de reprise, et l'action de route publique. PHP échoue à la compilation de la classe, ce qui rendait inaccessibles les quatre routes `/admin/audit` (consultation, détail, reprise, alerte). `php -l` ne le détecte pas : ce n'est pas une erreur d'analyse syntaxique, ce qui explique que le défaut soit passé inaperçu.
- L'accesseur est renommé `rollbackService()`.

### Corrigé — Ce qui était saisi hors liaison était perdu

- Le mod simulait la coupure réseau mais **perdait les données saisies pendant**. Une fiche SSE renseignée dans une cave sans couverture partait dans le vide ; l'opérateur ne l'apprenait qu'au débriefing. La file d'attente existante ne couvrait que les marqueurs de carte.
- **Tampon hors ligne** pour tout ce qu'un humain rédige : fiche SSE et relevés, point d'intérêt, MEDEVAC, QRF. Rejoués dans l'ordre au rétablissement de la liaison.
- **Les positions ne sont jamais rejouées**, délibérément : restituer une position vieille de dix minutes montrerait l'élément là où il n'est plus. Une position périmée trompe le poste de commandement au lieu de l'informer.
- Le tampon vit dans le profil du joueur et survit à un plantage ou une reconnexion — la coupure qui fait perdre des données est rarement propre. Les entrées sont horodatées sur l'horloge murale et non sur le temps de mission : ce dernier repart à zéro à chaque partie, si bien qu'une entrée aurait été relue avec un âge négatif à la session suivante, donc jamais périmée — une demande MEDEVAC de samedi se serait rejouée le week-end suivant.
- Temporisation croissante entre les tentatives : une liaison qui vient de revenir est souvent instable, et marteler l'extension produit une salve d'échecs qui ressemble à une panne.
- **Péremption assumée et annoncée** : au-delà de 30 minutes ou 5 échecs, l'entrée est écartée et l'opérateur en est informé. Un compte rendu de contact arrivant trois quarts d'heure après les faits se lirait comme une information fraîche. L'abandon n'est jamais silencieux — sinon l'opérateur croit avoir rendu compte.
- La saisie hors liaison affiche « fiche conservée, ne la ressaisissez pas » plutôt qu'une erreur : une ressaisie produirait deux fiches du même sujet, que l'automatisme A2 signalerait ensuite comme doublon.
- Compteur `N EN ATTENTE` dans la barre d'état du terminal SEEK : un tampon invisible ne vaut guère mieux qu'une perte.

### Corrigé — Le compte rendu et le PDF contournaient la déclassification

- **`/compte-rendu` servait le dossier intégral sans aucun contrôle d'habilitation.** Il suffisait de ne pas passer par l'écran de déclassification pour obtenir le même contenu en clair : l'écran expurgé ne protégeait donc rien. Le compte rendu est désormais servi au plafond du lecteur, avec un bandeau quand il est partiel.
- **L'export PDF produisait lui aussi le dossier intégral.** C'était le pire des trois : un PDF circule seul une fois transmis, un caviardage manquant ne se rattrape plus. L'export est rabattu, et le document porte un bandeau rouge indiquant son niveau de production et les catégories noircies — sans quoi une version expurgée est indiscernable d'une version complète une fois imprimée.
- La mention « Ce document est intégral » du compte rendu était devenue fausse ; elle est remplacée.

### Ajouté — Caviardage des écrans de travail

- Second interrupteur, distinct du verrou : registre des personnes, fiche dossier et corrélations rabattus sur l'habilitation du lecteur.
- **Désarmé par défaut**, pour une raison différente du verrou : ces écrans sont ce que la cellule regarde toute la séance, et l'identité étant classée « Confidentiel », les armer retire les noms à un simple membre. À armer une fois les habilitations réparties, ou après ajustement de la doctrine des catégories.
- Les caviardages manuels d'un dossier s'appliquent aussi sur ces écrans : une zone noircie à la main doit l'être partout, sinon le caviardage ne veut rien dire.
- Restent intégraux dans les deux régimes : registre des sites, fiche site, écran de croisement.

### Ajouté — Verrou d'ouverture par classification

- La classification d'un dossier peut désormais **fermer** le dossier, et plus seulement le signaler : fiche, personnes rattachées, notes, preuves, corrélations, compte rendu et export deviennent inaccessibles à qui n'a pas l'habilitation.
- **Désarmé par défaut.** La classification n'a jamais filtré depuis la création du portail : les valeurs déjà posées ont été choisies sans conséquence, et les armer d'office les transformerait rétroactivement en décisions d'exclusion que personne n'a prises.
- **Écran de revue avant d'armer** : le registre porte une colonne « Qui pourra encore l'ouvrir » sur chaque dossier, la répartition par classification, et le nombre de dossiers que le verrou fermerait au lecteur courant. Le portail ne mesure l'effet que pour la session en cours — il ne parle pas à la place des habilitations des autres.
- Armement réservé aux détenteurs du droit d'octroi : le verrou ferme des dossiers à d'autres, il ne doit pas être desserrable par celui qu'il gêne. Armement et désarmement partent au journal.
- Si la table de réglages est injoignable, le verrou est considéré désarmé. Un portail qui verrouille tout parce qu'une table manque est plus dangereux qu'un verrou temporairement inactif : on découvre le second, on subit le premier en pleine opération.
- Nouvelle table `sse_portal_settings` — le portail n'avait aucun stockage serveur pour un réglage, le thème passant par un cookie, ce qui ne convient pas à un verrou.

### Ajouté — Configuration mission maker et Zeus

- **Attributs Eden sur l'unité**, catégorie « COMSPEC — Exploitation SSE » : ce que la base doit répondre (génération automatique, inconnu, signalé, recherché), état civil, nationalité déclarée, langue, référence de dossier antérieur, indice de confiance imposé, graine. Poser un module par PNJ était intenable sur trente civils.
- **Trois modules Eden / Zeus**, catégorie « COMSPEC SSE » : *Dossier SSE actif*, *Profil d'identité SSE*, *Doter en terminal SEEK*. Variantes Zeus Enhanced avec boîtes de dialogue, ignorées automatiquement quand les modules de configuration sont déjà visibles — même garde anti-doublon que les zones roleplay.
- Les champs laissés vides ne sont pas écrits : un profil partiel complète la génération déterministe au lieu de l'écraser. On peut ne forcer que l'alias d'un sujet.
- Le module « Doter en terminal SEEK » ne dote que les joueurs : un terminal récupérable sur un cadavre ennemi n'est pas l'effet recherché.
- Fixer la graine rend un sujet identique d'une session à l'autre — pour un scénario rejoué ou une séance de formation.

### Documentation

- Nouveau [guide SSE chef de mission / Zeus](mod/UptoDate/docs/guide-sse-chef-mission.md) : préparation Eden, pilotage Zeus, tableau des six automatismes avec ce que chaque règle ne fait pas, lecture du graphe, trame de séance, limites assumées.
- Contrat d'API complété (table `sse_relations`, champ `automation`, variables d'unité réglables par le chef de mission).

---

## [1.4.13] - 2026-07-30

### Ajouté — Terminal SEEK

- **Terminal en pages, dans l'écran de l'appareil** : accueil à six tuiles (Sujet, Contexte, Biométrie, Constat, Photo, Dossier), navigation par flèches et bouton Home. Les contrôles sont posés dans la zone d'écran réelle de l'illustration, mesurée sur la texture ; les touches A1, A2, QUERY et SIGN sont posées sur le clavier de l'appareil.
- **Dossier SSE actif** : la référence est posée une fois pour l'élément, à l'arrivée sur objectif, puis héritée par toutes les fiches sans ressaisie. Visible dans la barre d'état du terminal, réglable depuis le menu ACE ou la page Dossier. Le champ de la fiche devient un repli manuel.
- **Requête d'identité** : bouton REQUÊTE, interrogation de la base fictive (6 s, barre ACE), verdict `Aucune correspondance` / `Correspondance possible` / `Correspondance confirmée` avec indice de confiance et référence de dossier. Affiché dans le panneau d'analyse et le bandeau LCD, transmis à Athena et repris sur la fiche du portail.
- **Résultat déterministe** : chaque personne reçoit une graine stable, dérivée de son identifiant réseau ou posée par le chef de mission. Deux interrogations du même sujet donnent le même verdict. La qualité des relevés module le résultat — une acquisition pauvre ne permet pas de confirmer.
- Le chef de mission peut imposer le verdict par variables d'objet : `COMSPEC_SSE_MatchResult`, `COMSPEC_SSE_Confidence`, `COMSPEC_SSE_RecordRef`.
- **Dotation du terminal SEEK** depuis Zeus (module Zeus Enhanced et module ACE Zeus), l'objet étant requis pour ouvrir une fiche.

### Ajouté — Exploitation de site

- Dossiers de site avec référence lisible, **checklist de fouille prégarnie selon le type** (habitation, dépôt, poste ennemi, cache, véhicule), saisies catégorisées rattachables à une pièce et à une personne, compte rendu de clôture.
- Endpoints `/api/sse/sites` (ouverture, consultation, pièces, saisies, clôture) et écrans portail « Sites exploités ».

### Ajouté — Portail SSE

- **Comptes rendus d'exploitation** : flash et compte rendu initial structuré (Situation, Exploitation du site, Personnel, Matériel, Faits marquants, Appréciation, Suites à donner), générés à la lecture depuis les éléments déjà versés au dossier. Remplace l'inventaire par un produit de renseignement : chaque personne y est reprise avec ses relevés et son verdict d'identité, chaque site avec les pièces non traitées.
- **Sites rattachés au dossier** : un site ouvert depuis le terrain avec la référence active rejoint le dossier, qui agrège désormais personnes, sites et saisies.

- **Charte « SSE Case File »** : palette de station de travail (vert `#12d18e`, trois couleurs sémantiques), Archivo condensé et JetBrains Mono, vignette à balayage sur les portraits, hachures pour les portraits absents.
- Portrait d'enrôlement et **chaîne de possession** sur la fiche personne — les événements étaient enregistrés depuis la 1.4.0 sans être affichés nulle part.
- Volumétrie des dossiers (personnes, notes, pièces) et jauge de similarité sur les croisements.

### Corrigé

- **Le terminal ne pouvait plus enregistrer de fiche** (HTTP 422 « identité requise » alors que l'alias était saisi) : l'échappement JSON tronquait les chaînes accentuées — « Décédée » apparaît systématiquement pour un sujet décédé — et n'échappait pas les caractères de contrôle. Le motif de refus du serveur est désormais affiché au lieu d'un générique « vérifiez la liaison ».
- **Panneau biométrique tronqué** : trois modalités sur deux lignes débordaient, l'ADN était coupé à l'écran.
- Diagnostic de résolution photo : les dossiers réellement balayés sont listés.

---

## [1.4.12] - 2026-07-29

### Ajouté — Terminal biométrique SEEK (renseignement SSE)

- **Nouvelle interface du terminal** : châssis durci, colonne identité, colonne relevé (platine de lecture, analyse, bandeau LCD) et pied de page procédure. Remplace le formulaire à une colonne.
- **Objet transportable** « Terminal biométrique SEEK » (sac, gilet, uniforme) : sans lui, plus de fiche. Réglage CBA « Terminal SEEK requis » pour les communautés qui préfèrent l’accès sans objet.
- **Fiche SSE depuis le menu ACE** sur une autre personne (blessé, inconscient, détenu, corps) : nouveau PBO optionnel `sse_ace`, nœud « Renseignement SSE ». Aucun écran ACE / KAT n’est modifié ; sans ACE chargé, la couche se retire en silence.
- **Constat de terrain ACE Medical** repris automatiquement sur la fiche : état, pouls, volémie, douleur, localisation des lésions. Les localisations alimentent « signes distinctifs ». Conforme à la règle 1.4.8 — ni SpO2, ni voies aériennes, ni donnée KAT.
- **Relevés biométriques simulés** : empreintes, iris et ADN, avec barre de progression, indice de qualité, nombre de points caractéristiques, algorithme et référence de laboratoire fictive. Analyse locale explicitement simulée — le rapprochement reste à la main du poste de commandement.
- **Code dossier SSE** saisi sur le terrain : la fiche est classée directement dans le dossier correspondant du portail. Code inconnu = fiche enregistrée mais non classée.
- **Signature par l’ATAK** : indicatif, identifiant de terminal et horodatage scellés dans la fiche, en guise de procès-verbal.
- **Exploitation d’un corps** : le terminal préremplit désormais identité, armement et équipement sur une personne décédée (le formulaire restait vide).

### Ajouté — Portail SSE

- **Comptes rendus d'exploitation** : flash et compte rendu initial structuré (Situation, Exploitation du site, Personnel, Matériel, Faits marquants, Appréciation, Suites à donner), générés à la lecture depuis les éléments déjà versés au dossier. Remplace l'inventaire par un produit de renseignement : chaque personne y est reprise avec ses relevés et son verdict d'identité, chaque site avec les pièces non traitées.
- **Sites rattachés au dossier** : un site ouvert depuis le terrain avec la référence active rejoint le dossier, qui agrège désormais personnes, sites et saisies.

- **Registre des personnes** en fiches plutôt qu’en tableau : constat de terrain, relevés biométriques avec jauge de qualité, état de signature et classement.
- `GET /api/sse/persons/by-unit` — fiche déjà ouverte pour une unité Arma donnée.
- Table `sse_biometric_samples`, colonnes de constat et de signature, index de recherche par unité.

### Corrigé — Erreurs de script

- `fn_canTransmit` : condition d’écran endommagé mal parenthésée — `exitWith` recevait un bloc de code (`Error exitwith: Type code, if attendu`), l’état « position seule » n’était jamais évalué.
- `fn_updatePosition` : deux conditions d’anomalie de suivi écrites `if {…} && {…}` (Code au lieu de Booléen) — immobilité et déplacement incohérent ne se déclenchaient pas.

### Corrigé — Zeus

- **Effets ATAK sans effet en solo** : le relais Zeus rejetait toute cible dont `owner` vaut 0, ce qui est le cas de toutes les unités hors multijoueur. Casse d’écran, gel, brouillage et extinction fonctionnent à nouveau ; le routage se fait sur la localité.
- **« ID ATAK » vide** dans le panneau « ATAK — Éditer joueur » : les identifiants étaient lus juste après un appel asynchrone, donc avant la synchronisation. Cible locale = lecture directe ; cible distante = une seule nouvelle tentative après l’aller-retour réseau.
- **Modules roleplay en double** dans l’arbre Zeus : les quatre zones étaient déclarées à la fois en config et comme modules Zeus Enhanced. La variante ZEN n’est plus enregistrée quand les modules config sont visibles.

### Corrigé — Photos

- **Captures ATAK introuvables** : les dossiers `Screenshot` des mods Workshop (`…\Arma 3\!Workshop\@<mod>\`) n’étaient pas balayés, alors que BCE y écrit ses clichés — et le chemin qu’il annonce peut pointer vers une installation qui n’existe plus sur le poste.
- **Dossier de captures COMSPEC** : toute capture résolue est recopiée dans `Documents\Arma 3 - COMSPEC\Captures` (200 fichiers conservés), emplacement stable indépendant de l’endroit où Arma ou BCE écrivent.
- `file_not_found` indique désormais si le dossier d’origine existait et combien de dossiers ont été balayés.
- Séparateur de chemin corrigé dans la collecte des photos locales (SQF n’échappe pas les antislashs : `"\\"` en produisait deux).

---

## [1.4.11] - 2026-07-29

### Corrigé

- Gel violent à la prise de photo ATAK : résolution fichier image allégée dans `COMSPECExtension` (fin du polling 7 s + scans récursifs disque à chaque cliché) ; délais pré-upload SQF réduits.

### Ajouté — Carte web

- Style **Point discret** pour les effectifs (même repère violet que les clichés terrain), choix à l’étape profil de session et dans Compte → Affichage.
- Option pour **masquer les points des photos** sur la carte (panneau Cams inchangé).
- Fin du doublon **Photo tablette + Aperçu casque** à chaque cliché ATAK (aperçu auto ne recycle plus la capture BCE).
- **Demande caméra casque** depuis le menu contextuel d’un opérateur en liaison : photo, photo HD, ou flux d’aperçus rapides (~5 s / 3 min).
- **Écran endommagé** : l’opérateur reste visible sur le web (position seule, liaison dégradée) au lieu de disparaître ; libellé terminal corrigé (plus de « éteint · écran endommagé » cumulé).
- **Fin de brouillage** : la liaison ne reste plus bloquée sur « Hors liaison » (recalcul automatique de l’état Athena).
- **Signalement in-game** : le formulaire ACE peut joindre le **journal de session** Overwatch (fichier + tampon mémoire) pour faciliter le diagnostic côté admin.
- **Journaux Overwatch** : un **nouveau fichier par lancement Arma** (`%LOCALAPPDATA%\\Arma 3\\COMSPEC\\logs\\COMSPEC_*.log`), purge auto des plus anciens (12 conservés).
- **parseSimpleArray** : plus de crash Arma sur les réponses extension mal formées ou chemins Windows.
- **Ordres C2** : « En cours » uniquement après **Confirmé** ; refus possible dès la réception (Reçu/Émis).
- **Points de mission** : clic droit carte → ordre de déplacement avec grille, itinéraire, ETA ; transmission ATAK ; confirmé/refusé in-game ; marqueur + trait après acceptation.
- **Marker Dropper** : journal web affiche le libellé (« helico », etc.) au lieu de `_USER_DEFINED #…` ; moins de doublons au resync.

---

## [1.4.8] - 2026-07-29

### Ajouté — Médical ACE (roleplay)

- Détection auto : inconscient, arrêt cardiaque (ACE Medical uniquement — états transmissibles via l’ATAK)

### Retiré — Détections KAT non roleplay

- Plus d’alertes auto voies obstruées, pneumothorax ou hypoxie SpO2 (données internes KAT non visibles sur un terminal tactique)

### Ajouté — Portail ATAK (carte & photos)

- Barre d’outils carte : traits, zones, périmètres, mesure, personnalisation
- Formulaire zones : icône au centre en liste déroulante
- Photos recon : flou, commentaire, masquage local, transfert SSE, effets roleplay visuels
- Détections « Au sol / suivi » retirées à la déconnexion opérateur

### Corrigé

- Flou photo recon en aperçu agrandi (lightbox)
- Upload photos sans gel ; marqueurs web JSON ; scroll page statut ATAK

---

## [1.4.1] - 2026-07-28

### Ajouté — Portail SSE classifié (`/atak/sse`)

- Sas d’accès double entrée : membre habilité + code, ou invité code seul
- Dossiers d’affaire (classification, notes, preuves, rattachement fiches personnes)
- Codes temporaires délivrés par le commandement (`atak.sse.grant`)
- Croisements listes de surveillance + export PDF classifié
- Permissions `atak.sse.*`, update config tenants `SSE_PORTAL_V1`
- Guide / formation module 7 + lien depuis l’onglet Personnes du Tacmap

---

## [1.4.0] - 2026-07-28

### Ajouté — Renseignement interpersonnel (SSE)

#### Mod
- Terminal « Renseignement interpersonnel » (idd 9991) : identité, statut, circonstances, déclarations
- Photo du visage (`UploadSsePhoto`) + simulation empreintes (`SubmitSseBiometricsSim`)
- Préremplissage inventaire / ACE restrain ; menu ACE « Enregistrer une personne »
- Extension : `SubmitSsePerson`, `UploadSsePhoto`, `SubmitSseBiometricsSim`
- Versions addons `main` / `connect` / `mavik_compat` → **1.4.0**

#### Portail
- API `/api/sse/persons` (+ photos, biométrie sim)
- Tables `sse_persons`, `sse_person_photos` (+ sites / watchlist / custody préparées)
- Onglet TOC **Personnes** (`atak-sse-persons.js`)
- Module pont `sse_person` + update config tenants `SSE_PERSONS_V1`
- Guide Overwatch + formation module 7 mis à jour ; changelog site `/nouveautes`

Voir aussi `mod/UptoDate/STEAM_CHANGELOG.txt` et `mod/UptoDate/docs/contrat-api-sse.md`.

---

## [1.3.1] - 2026-07-28

### Corrigé — Messagerie Groups / radio jeu ↔ web

#### Cause
- Le pont `Iceman_ATAK_GroupMessage` n’envoyait plus vers Athena (journal local seul)
- Aucun poll chat pour faire remonter les messages TOC → inbox Athena en jeu

#### Mod
- Restauration envoi `GROUPE|…` via `SendChat` (`fn_athena_bridgeIcemanGroup`)
- `GetChatMessages` (extension) + `fn_pollChatMessages` → messages web / HQ dans l’inbox Athena
- Rebuild : `connect.pbo`, `atak_athena.pbo`, `COMSPECExtension_x64.dll`

#### Portail
- Enrichissement `GroupMessageParser` sur `POST /api/chat`

### Ajouté — Signalements tactiques lisibles (FRAGO / Reports)

#### Mod
- Alertes tactiques : corps métier seul (plus de duplication type / indicatif / grille)
- FRAGO : SMEAC structuré + `ORDER_ID` pour ouvrir l’ordre lié
- Inbox Athena : détail FRAGO par rubriques

#### Portail
- `TacticalAlertParser` : `cleanSummary`, `parseFragoSections`, `activityLabel`, `order_id` / `frago`
- `tacmap-tactical-alerts.js` : cartes + modal **Ouvrir le FRAGO** / **Ouvrir** / carte / ordre
- `atak-activity.js` / `atak-chat.js` / `atak-orders.js` : ouverture fiche + `ATAKOpenOrder`
- Styles modal / boutons (`tacmap.css`, `atak.css`)

### Ajouté — Photos CTAB automatiques vers ATAK web

#### Mod (`atak_athena`)
- Upload auto à la capture (EH BCE / Iceman + poll Photo Library)
- Retry si fichier pas encore écrit ou liaison absente ; flush à la reconnexion
- UI : « Renvoyer la photo » en secours uniquement

### Corrigé — Marqueurs Marker Widget / Dropper (BCE) vers ATAK web

#### Cause
- Widget = BCE Compat cTab (`_USER_DEFINED` / `_IcTab_DEFINED #…` + `setMarker*Local`), pas Iceman
- Filtre / timing COMSPEC rataient ces marqueurs ; « Forcer une resynchronisation » ne poussait que la position

#### Mod (`connect` / `atak_athena`)
- `fn_isSyncableMapMarker` / `fn_forceSyncMapMarkers`
- Acceptation noms BCE ; re-sync différé ; hooks PlaceMarker / onMapDoubleClick
- EH marqueurs dès PostInit ; diagnostic **Renvoyer les marqueurs carte**
- Force sync hub / pause manager inclut les marqueurs

### Versions
- `connect` **1.3.1** · `atak_athena` **1.0.11**
- Rebuild : `connect.pbo`, `atak_athena.pbo`, `COMSPECExtension_x64.dll` + déploiement PHP/JS/CSS Athena

---

## [1.3.0] - 2026-07-28

### Ajouté — Réalisme liaison ATAK (roleplay réseau & appareil)

#### Mod (`connect` 1.3.0)
- **`fn_canTransmit`** : gate central avant envois extension (position, polls, marqueurs) — modes `full` / `position_only` / `none`
- **Hub overlays** IDC 9200–9204 : déconnexion, zone, pertes, écran cassé, glitch (`display_hub.hpp`)
- **Dommages enrichis** : chocs Hit/Explosion, bras blessé, lien KAM (pneumothorax, SpO2 → capteur HR roleplay)
- **`fn_getMedicalState`** : champs KAM optionnels (SpO2, voies aériennes, pneumothorax) propagés vers Athena
- **État crash ATAK** : gel terminal distinct offline réseau (`fn_triggerAtakCrash`, ppEffects)
- **Modules Zeus/Eden** réactivés : `COMSPEC_Module_NoCoverage`, `Interference`, `Degraded`, `Jammer`
- **Sync zones portail** : `fn_pollRoleplayConfig` / `fn_syncRoleplayZonesFromPortal` (poll 90 s)
- **Reprise JIP** : `fn_initCrashRecovery`, `fn_restoreAtakSession`, `fn_clearDisconnectedAtakState`
- **Callbacks extension** : `NetworkDisconnected` / `NetworkReconnected`
- **Assets** : overlays roleplay + logo web (brouillons IA) — doc `docs/design/atak-assets-roleplay.md`

#### Extension `COMSPECExtension`
- `GetRoleplayConfig` → `GET /api/atak/roleplay-stats` (format tabulaire SQF)
- `GetSessionRestore` → `GET /api/atak/session-restore`

#### Portail Athena
- `roleplayStats` : `zones_enabled`, `zones_json`, `session_ttl_sec`
- `GET /api/atak/session-restore?steam_uid=…` — snapshot TTL 10 min (`AtakDisconnectRecoveryRepository`)
- Snapshot auto à chaque `POST /api/atak/position` (indicatif, liaison, position)
- Assets web : `atak-eagle-logo.png`, `atak-link-lost-icon.png` (alerte roleplay JS)

### Modifié
- Versions addons `main` / `connect` / `mavik_compat` → **1.3.0**
- `atak-roleplay-effects.js` : icône liaison perdue au lieu de l’emoji seul

### Rebuild pack
- **Obligatoire** : `connect.pbo`, `COMSPECExtension_x64.dll`
- Recommandé : `main.pbo`, `mavik_compat.pbo` (version affichée hub)

---

## [1.2.2] - 2026-07-27

### Ajouté — Waypoints partagés & itinéraires de patrouille (portail)

#### API & schéma
- Tables `atak_waypoint_routes` / `atak_waypoints` (`migrations/2026_07_27_001_atak_waypoints.sql`) ; filet lazy `AtakWaypointsSchema` si la base était déjà installée
- Routes : `/api/atak/waypoint-routes` (CRUD), `/api/atak/waypoints` (CRUD), `POST /api/atak/waypoints/{id}/reached`
- Création d’itinéraire avec points en un seul appel ; `GET …/waypoint-routes/{id}` expose `next_waypoint` pour le guidage client
- Progression automatique de l’itinéraire : Planifié → Actif (1er point atteint) → Terminé

#### Pack mod
- Changelog Workshop / dépôt pack alignés sur **1.2.2** ; versions addons `main` / `connect` / `mavik_compat` → `1.2.2`, `atak_athena` → `1.0.7`
- Guidage SQF (marqueurs numérotés + sondage `reached`) prévu dans une prochaine livraison pack — API déjà consommable

### Ajouté — Réalisme ATAK (terminal & certificat)

#### API mod ↔ registre communauté
- `GET/POST /api/atak/terminals` et `POST /api/atak/certificates` — protégés par clé d’accès communauté (pas d’exemption dans `config/tactical_api.php`)
- Le mod (extension `RegisterTerminal`, `RegisterCertificate`, `GetTerminalRealism`) s’authentifie via la clé ATAK ; résolution `user_id` par `steam_uid` à l’enregistrement
- `GET /api/atak/terminals?terminal_uid=…` : état terminal + dernier certificat + réglages `atak_defaults` (dont `automatic_pairing`) ; le client jeu ne peut pas lister tous les terminaux
- `POST` certificat refusé si `automatic_pairing` désactivé pour la communauté (`403 automatic_pairing_disabled`)
- Back-office **Certificats et terminaux** (`/back-office/atak/realisme`) ; tables `atak_terminals` / `atak_certificates` (migration lazy)

### Ajouté — Réglages d’exécution portail (runtime admin)

#### Persistance & API
- `TenantAdminSettingsRepository` — réglages `admin_runtime` par communauté (fusion dans `tenant_settings`)
- `GET/POST /api/back-office/runtime-settings` — lecture / enregistrement pour les admins organisation
- Quatre blocs métier : **Portail**, **Notifications**, **Sécurité**, **Défauts ATAK** (sanitisation serveur, valeurs bornées)

#### Portail
- Inscriptions publiques, validation manuelle des comptes, mur public, fuseau horaire

#### Notifications
- Rappels RSVP automatiques, notifications Discord, SMS d’urgence, récapitulatif hebdomadaire

#### Sécurité
- Authentification à deux facteurs, expiration de session, verrouillage après échecs, journal d’audit étendu

#### Défauts ATAK (consommés par le réalisme terminal)
- Appairage automatique des certificats, version client minimale, durée de validité des certificats, partage de position hors opération

### Ajouté — Comptes rendus post-op structurés (AAR)

- Back-office **Comptes rendus post-op** (`/back-office/atak/comptes-rendus`) : liste, fiche, édition, dépôt
- Lien optionnel avec un **cycle de mission** ; statuts **En attente** / **Validé**
- Champs structurés : synthèse, points forts et faibles, actions ouvertes / clôturées, scores et métriques opérationnelles
- Filtres rapides (en attente, validés, actions ouvertes) ; KPIs en tête de liste
- Table `aar_reports` (migration lazy `aar_reports_migration`)

### Ajouté — Matrice rôles & permissions

- Page **Rôles & permissions** (`/back-office/roles-permissions`) : vue matricielle par rôle et par module
- Modules : Membres, Opérations, ATAK, Finances, Systèmes — niveaux d’accès en libellés métier (Complet, Sa section, Lecture, etc.)
- Filtres (recherche, périmètre, niveau, actif), édition inline, export CSV, marquage **revue d’accès** trimestrielle
- APIs `/api/admin/roles-permissions` (liste, export, sauvegarde par rôle)
- Migration `role_permission_matrix_migration`

### Ajouté — Réponses nominatives (événements)

- Vue **Réponses nominatives** (`/back-office/events/{id}/reponses-nominatives`) depuis la fiche créneau
- Tableau par membre : réponse RSVP (Confirmé, Peut-être, Sans réponse, Décliné), section, état terminal / certificat ATAK
- Filtres par réponse, section et état ATAK ; export CSV ; édition des métadonnées orga par ligne
- APIs `/api/events/{id}/reponses-nominatives` (liste, export, mise à jour)
- Migration `community_event_rsvp_nominative_migration`

### Ajouté — Contrôle d’accès modules (`ModuleFeatureAccess`)

- Garde unifiée `guardAtak` / `guardOperations` / `guardSystems` branchée sur la matrice RBAC
- Appliquée au réalisme ATAK, aux comptes rendus post-op, aux réponses nominatives et à la matrice permissions
- Redirection back-office avec message métier si droits insuffisants (pas de vocabulaire technique côté utilisateur)

### Ajouté — Préférences carte ATAK (panneau compte)

- **Recentrage personnel** : recadrer la carte sur sa position dès qu’elle remonte en début de session
- **Contacts en retard** : afficher ou masquer les effectifs dont la position arrive avec délai
- **Alertes sonores par catégorie** : liaison, ordres / urgences, médical — cases à cocher + persistance locale (`atak_alert_categories`)
- Volume et style des sons d’alerte regroupés dans le même panneau (complète la barre latérale)

### Ajouté — Quick wins Athena / Tacmap

#### Overwatch — libellés français
- Panneaux **Relecture mission**, **État logistique**, **Calculateur d’appui-feu**, **Zones de danger**, **Identification ami / ennemi**
- Onglet et santé « Relecture » ; bilan après-action en libellés métier (instantanés, signalements, anomalies)

#### Barre d’outils — profils TOC / Chef d’équipe / Médecin
- Presets dans **Personnaliser** : TOC (tout), Chef d’équipe, Médecin (outils adaptés)
- Préférence enregistrée en localStorage (`atak_map_tools_visible_v1` + `atak_map_tools_preset_v1`)

#### Badge opérateur téléphone + temps restant
- Sur Tacmap en session téléphone : badge **Opérateur téléphone** + compte à rebours jusqu’à expiration

#### Cams — aperçus assumés + demande de vue
- Rappel UI : aperçus photo uniquement (pas de vidéo en direct)
- Bouton **Demander une nouvelle vue** → journal TOC + message radio (confirmation)

#### Effectifs BFT — Vibrer
- Action **Vibrer** sur la liste / tableau des effectifs (API existante), avec confirmation

#### Cycle de mission (briefing → exécution → après-action)
- Hub **Cycle de mission** (`/back-office/atak/cycle-mission`) : créer, ouvrir, clôturer
- Statuts métier : Préparation · En cours · Clôturée (`theatre_mission_cycles`)
- Badge mission sur Tacmap / ATAK ; à la clôture, relecture + bilan bornés (`from` / `to`)
- API `/api/mission-cycle/*` ; migration idempotente + ensure lazy

#### Équipes de feu sur la carte (prio. moyenne)
- Filtre BFT par équipe de feu (liste Effectifs + marqueurs carte)
- Panneau **Composition des équipes de feu** pendant l’opération (couleur, liaison)
- Couleurs d’équipe déjà sur marqueurs / puces conservées

#### Identification IFF de conduite (prio. moyenne)
- Alertes TOC + Overwatch pour contact / véhicule **inconnu**, **suspect**, défi **expiré**
- Compte à rebours d’expiration du défi ; délai de grâce (5 min) → « Contact inconnu »
- Panneau Identification TOC opérationnel (plus seulement Overwatch)

#### Logistique mission (prio. moyenne)
- Seuils stock bas / critique (≤ 35 % / ≤ 15 %) + pastilles d’alerte
- Bouton **Ravitailler** → demande dans le journal d’activité TOC
- Lien vers les évacuations sanitaires en cours depuis le suivi logistique

#### Briefing diapos (prio. moyenne)
- Actions **Monter / Descendre** pour l’ordre ; publication rapide Visible en jeu / brouillon
- Présence briefing enrichie (compteurs téléphone / tableau en jeu)

#### Rapports bugs Overwatch (prio. moyenne)
- Suivi métier **Nouveau → En cours → Corrigé** (`new` / `in_progress` / `fixed`)
- Affichage **version du pack** (+ extension) ; filtre par statut

### Ajouté — Pack priorité haute (TOC / terrain)

#### Parité téléphone ATAK
- Session `/connect` : entrée directe carte (plus de hub invité) + caps BFT / médical / journal radio
- Badge **Opérateur téléphone** + TTL (déjà en place) ; auteur radio = libellé téléphone
- Journal d’activité à l’ouverture carte téléphone

#### Fidélité des repères
- Normalisation API enrichie (couleur Arma → hex, type, forme, dir, alpha)
- Libellés FR ACE (POI, MEDEVAC, renfort, service) ; couleurs hex sans `#`

#### Photos terrain
- Messages d’échec métier (manquant / trop lourd / liaison dégradée)
- Galerie TOC horodatée Zulu + message si image indisponible
- Notifs mod selon détail d’échec d’envoi

#### SITREP ops-ready
- Overwatch : plus de cadrage « test » — signalement poste de commandement
- ACE SITREP / CONTACT / SPOTREP → tableau de situation fusionné

#### Replay AAR exploitable
- Timeline contacts / MEDEVAC / ordres / repères (`/api/replay/events`)
- Bilan + PDF export en français avec compteurs opérationnels

### Déploiement
- Cache-bust Tacmap `?v=202607270730` (session-profile, chat, sitrep, cams, replay, arma-map-markers, atak-sounds, atak-map, atak.css)
- FTP Hostinger → `athena.ttrd.fr` (`public_html` + dual `assets/`) ; `.env` non modifié
- Rebuild mod OK (`build_mod.bat`) — réalisme terminal / certificat + sync liaison
- Lancer `run-migrations` (ou UI migrations) : `atak_realism_registry`, `aar_reports`, `role_permission_matrix`, `community_event_rsvp_nominative` ; `theatre_mission_cycles` et `workflow_status` rapports mod si absent
---

## [1.2.1] - 2026-07-26

### Ajouté — Athena WEB / TOC ATAK (branchement Overwatch)

#### Carte — marqueurs Marker Dropper & cTab
- Les repères posés en jeu (Marker Dropper, marqueurs carte Arma, marqueurs utilisateur cTab / ATAK Enhanced) remontent sur la carte Athena du poste de commandement
- Pont cTab : écoute immédiate des mises à jour + file d’attente si la liaison Athena n’est pas encore prête
- Marqueurs `_USER_DEFINED` (Dropper / carte Arma) inclus dans le miroir web
- Sync immédiate vers le miroir web dès qu’un repère est posé ou mis à jour (sans attendre le prochain cycle long)
- Les points d’intérêt ACE (LZ d’évacuation, renfort, service véhicule, POI) s’affichent aussi sur la carte web
- **Diamants hostiles** : alerte, destruction, objectif ou points rouges simples en diamant (lisibles d’un coup d’œil, sans confusion avec un effectif ami)
- **Badges de préfixe** : libellés courts type « T », « T1 », « A-3 » en pastille à côté du symbole
- Libellés français sur la carte et dans l’historique (« Alerte », « Objectif », « Point d’intérêt », « Repère · … »)
- Déduplication : un point anonyme ou au même indicatif qu’un contact déjà en liaison ne double plus le symbole OTAN de l’effectif
- Pop-up carte : précision « ce point n’est pas un effectif en liaison — c’est un repère posé sur la carte »

#### En-tête carte tactique (Tacmap)
- Barre d’en-tête redesignée : actions regroupées en **clusters** (contexte mission, liaison, état système)
- Bouton **Lier le jeu** (code d’appariement Arma ↔ compte) intégré au cluster liaison
- Badge **BÊTA** à côté de la marque (accès anticipé) ; tagline d’état recentrée sur **Liaison** (plus de libellé « théâtre » en en-tête)
- Plus de bouton fluo / accent agressif : actions primaires sobres, cohérentes avec le reste du TOC

#### Connexion téléphone — page `/atak/connect`
- Page web téléphone : saisie du **code affiché sur le PC / tablette** pour ouvrir la carte ATAK mobile sans compte
- Flux : code → token de session → vue connectée (expiration gérée avec message clair)
- Complète l’écran de liaison en jeu (adresse mobile + code d’appariement)

#### Rapports d’erreurs mod → Athena
- Remontée des diagnostics / bugs Overwatch vers le portail (`POST /api/atak/mod-report`)
- Journal admin **Rapports erreurs** (liste, filtre, retrait)
- Exemption de clé d’accès pour ce chemin (signalement possible avant ou sans liaison complète) + rate-limit
- Correctif `Database::getPdo` : accès PDO stable pour le dépôt des rapports (évite échec au boot / lazy-init)

#### Menu contact — Faire vibrer le terminal
- Action **Faire vibrer le terminal** dans le menu contextuel d’un contact
- Disponible uniquement si le contact est **en liaison** (sinon message clair : hors liaison)
- Confirmation opérateur : « Le terminal de [indicatif] vibre en jeu »
- Journal d’activité TOC : « Terminal — vibration — [indicatif] » (signal haptique, pas un ordre de manœuvre)
- Action voisine : **Envoyer une notification…** (bandeau cliquable sur le terminal du joueur)

#### Blue Force / indicatif / identifiant de suivi
- Chaque contact en liaison reçoit un **identifiant de suivi** stable lié à son indicatif (réutilisé d’une session à l’autre)
- Affichage liste BFT : ligne **« Suivi … »** sous l’indicatif
- Fiche pop-up unité : ligne **« Identifiant de suivi »**
- Même identité partagée entre carte, tablette et TOC

#### Messagerie HQ (poste de commandement)
- Contact permanent **HQ** dans la messagerie ATAK / cTab en jeu : messages destinés au PC → journal radio / messagerie Athena
- Journal d’activité TOC : **« Message HQ — [auteur] : … »**

#### Sons & alertes TOC
- Préférences sonores TOC avec libellés métier (silencieux avec/sans vibration, ambiance tension, signal médical)
- Assistances médicales : bandeaux et toasts restent visibles même si le son est coupé
- Escalade médicale : les alertes moins graves du même indicatif sont clôturées pour éviter le doublon

### Modifié

#### Journal radio — moins de bruit technique
- Messages de **réglages d’affichage** (camps adversaire / indépendants / civils) appliqués en silence côté carte et **hors journal radio** du TOC
- Variantes anciennes (auteur « REGLAGES » + corps « AFFICHAGE|… ») également filtrées
- Sync Blue Force / positions : hors journal d’activité par défaut

#### Messages de groupe vs canal HQ
- Les **messages de groupe** restent en jeu et **ne spamment plus** le journal radio web du TOC
- Canal officiel jeu → TOC : destinataire **HQ**

#### Ordres / signaux terminal
- Types « Faire vibrer » et « Notifier » traités comme **signaux terminal**, distincts des ordres de manœuvre

### Corrigé

- Confusion repère / effectif : libellé court type indicatif préfixé **« Repère · … »** ; ne remplace plus le symbole Blue Force
- Messages techniques d’affichage camps filtrés côté serveur et interface
- Contacts hors liaison : vibration / notification refusées avec message opérateur compréhensible
- Demande de renfort (QRF) sans position de contact : réponse **400** avec message métier (« Indiquez la position du contact pour demander le renfort ») au lieu d’un échec opaque

### Stabilité & ACE (volet jeu Overwatch)
- REAPP / respawn durci ; menu ACE ATAK rebranché ; NDA qui ne revient plus à chaque lancement
- Terminal ATAK requis par défaut ; features prioritairement dans ATAK Enhanced / cTab
- Adresse mobile + code d’appariement sur l’écran de liaison téléphone
- Briefing / diaporama, demande d’appui aérien (CAS) et manifeste de vol branchés côté mod (formulaires dédiés + viewer 9-lignes) — détail Steam / changelog Overwatch

### Déploiement prod (Athena WEB — 26/07/2026)

- FTP Hostinger → `athena.ttrd.fr` (`/domains/athena.ttrd.fr/public_html/`)
- Assets synchronisés en dual : `assets/` **et** `public/assets/` (JS + CSS)
- Cache-bust TOC : `views/atak.php` — `?v=202607261735` sur scripts / styles critiques
- Contrôle post-upload : ping HTTPS **200** ; JS vérifiés (vibrer, diamants, suivi, filtre réglages)
- Périmètre web : carte / marqueurs, unités BFT, menu vibrer, tchat filtré, contrôleur & dépôts ATAK, routes
- `.env` non modifié ; pas de rebuild PBO dans ce déploiement web (packs déjà rebuild côté mod)

Voir aussi `mod/UptoDate/STEAM_CHANGELOG.txt` (texte Steam) et `mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md`.

---

## [1.2.0] - 2026-07-24

### Ajouté - Phase 2.5 : Intelligence & Automatisation

[Contenu complet Phase 2.5 déjà inséré ci-dessus]

### Ajouté - Phase MOD : Intégration Arma 3

[Contenu complet Phase MOD déjà inséré ci-dessus]

---

## [1.0.0] - 2026-07-24

### Ajouté - Phase 1 : Fondations coordination

#### Système de rapports tactiques structurés
- **Tables** : `atak_tactical_reports`, `atak_report_attachments`, `atak_report_templates`
- **Vue** : `v_atak_tactical_reports`
- **Repository** : `AtakTacticalReportRepository` avec 9 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/reports` : Liste rapports avec filtres (type, priorité, statut, émetteur, dates)
  - `POST /api/atak/reports` : Créer rapport (SPOTREP, SITREP, SALUTE, CONTACT)
  - `GET /api/atak/reports/{id}` : Détail rapport avec attachements
  - `POST /api/atak/reports/{id}/acknowledge` : Acquitter rapport
- **Features** :
  - Support 4 types : SPOTREP, SITREP, SALUTE, CONTACT
  - Génération automatique numéro rapport (`SPOTREP-20260724-001`)
  - Données structurées JSON (SALUTE : Size, Activity, Location, Unit, Time, Equipment)
  - Classification : UNCLASSIFIED, RESTRICTED, CONFIDENTIAL, SECRET
  - Priorités : ROUTINE, PRIORITY, IMMEDIATE, FLASH
  - Statuts : DRAFT, SUBMITTED, ACKNOWLEDGED, ACTIONED, ARCHIVED
  - Système visibilité : ALL, COMMAND, RESTRICTED, PRIVATE
  - Géolocalisation (pos_x, pos_y, grid_reference)
  - Multi-tenant et context-aware

#### Système POI (Points d'Intérêt) tactiques
- **Tables** : `atak_poi`, `atak_poi_observations`, `atak_poi_photos`
- **Vue** : `v_atak_poi` avec compteurs observations/photos
- **Repository** : `AtakPoiRepository` avec 10 méthodes publiques
- **API** : 3 endpoints REST
  - `GET /api/atak/poi` : Liste POI avec filtres (catégorie, affiliation, statut, menace)
  - `POST /api/atak/poi` : Créer POI
  - `PUT /api/atak/poi/{id}` : Mettre à jour POI
- **Features** :
  - 13 catégories : OBJECTIVE, BUILDING, CACHE, ENEMY_POSITION, HVT, PATROL_BASE, CHECKPOINT, STRUCTURE, INFRASTRUCTURE, ROUTE, TERRAIN, HAZARD, OTHER
  - Affiliation : FRIENDLY, ENEMY, NEUTRAL, UNKNOWN
  - Certitude : CONFIRMED, PROBABLE, POSSIBLE, DOUBTFUL
  - Niveau menace : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - Statuts : ACTIVE, NEUTRALIZED, DESTROYED, ABANDONED, OCCUPIED, UNDER_SURVEILLANCE
  - Recherche proximité géographique (`findNearPosition`)
  - Historique observations multiples
  - Photos géolocalisées
  - Source fiabilité (échelle A-F NATO)
  - Génération automatique code POI

#### Zones tactiques enrichies
- **Tables** : `atak_tactical_zones`, `atak_zone_alerts`
- **Vue** : `v_atak_active_zones` avec calcul `is_currently_active`
- **Repository** : `AtakTacticalZoneRepository` avec 14 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/zones` : Liste zones avec filtres
  - `POST /api/atak/zones` : Créer zone
  - `POST /api/atak/zones/check-position` : Vérifier position dans zones
  - `GET /api/atak/zones/alerts` : Liste alertes non acquittées
- **Features** :
  - 9 types zones : LZ, DZ, OBJECTIVE, DANGER_ZONE, NO_GO_AREA, PATROL_AREA, SECTOR, BOUNDARY, OTHER
  - 3 géométries : CIRCLE, RECTANGLE, POLYGON
  - Algorithmes géométriques :
    - `isInCircle()` : Calcul distance euclidienne
    - `isInRectangle()` : Test rotation + bounds
    - `isInPolygon()` : Ray casting algorithm
  - Système alertes entrée/sortie configurable
  - Sons alertes personnalisables
  - Temporalité (`active_from`, `active_until`)
  - Priorités et niveaux menace
  - Style visuel (couleurs, opacité, contours)
  - Log détaillé alertes avec position exacte

### Ajouté - Phase 2 : Capacités spécialisées

#### Extension système MEDEVAC 9-Line avec triage TCCC
- **Tables** : `atak_medevac_requests`, `atak_medevac_patients`, `atak_medevac_status_updates`
- **Vue** : `v_atak_active_medevac` avec golden hour et patients
- **Triggers** :
  - `trg_medevac_golden_hour` : Calcul automatique golden hour pour patients T1
  - `trg_medevac_status_log` : Logging changements statut
- **Repository** : `AtakMedevacRepository` avec 12 méthodes publiques
- **API** : 6 endpoints REST
  - `GET /api/atak/medevac` : Liste MEDEVAC
  - `POST /api/atak/medevac` : Créer demande MEDEVAC 9-Line
  - `GET /api/atak/medevac/{id}` : Détail avec patients
  - `PATCH /api/atak/medevac/{id}/status` : Mettre à jour statut
  - `POST /api/atak/medevac/{id}/assign` : Assigner asset
  - `POST /api/atak/medevac/{id}/patients` : Ajouter patient
- **Features** :
  - Format 9-Line NATO complet
  - Triage TCCC : T1 (urgent), T2 (urgent surgical), T3 (delayed), T4 (expectant)
  - Golden hour tracking automatique (T1)
    - Calcul expiration : request_time + 60min
    - Statut : `OK` (> 30min), `WARNING` (15-30min), `CRITICAL` (< 15min), `EXPIRED` (> 60min)
    - Minutes restantes calculées
  - Catégories patients : Litter vs Ambulatory
  - Équipement spécialisé : hoist, ventilator, blood, etc.
  - Statut sécurité LZ : NO_ENEMY, POSSIBLE_ENEMY, ENEMY_IN_AREA, ENEMY_SUPPRESSED
  - Marquage LZ : NONE, SMOKE, PANEL, STROBE, FLARE, VS17, MIRROR
  - Contamination NBC tracking
  - Workflow complet : REQUESTED → ACKNOWLEDGED → ASSIGNED → INBOUND → ON_SITE → EVACUATING → COMPLETED
  - Historique changements statut avec timestamps
  - Données médicales détaillées par patient :
    - Conscience : ALERT, VERBAL, PAIN, UNRESPONSIVE
    - Respiration : NORMAL, ABNORMAL, ABSENT
    - Circulation : NORMAL, COMPROMISED, ABSENT
    - Blessures structurées (location, type, severity)
    - Traitements appliqués
    - Médicaments administrés (nom, dose, heure)

#### Système QRF (Quick Reaction Force)
- **Tables** : `atak_qrf_requests`, `atak_qrf_sitrep_updates`, `atak_qrf_waypoints`
- **Vue** : `v_atak_active_qrf` avec distance et urgence
- **Trigger** : `trg_qrf_urgency_deadline` : Calcul deadline urgence
- **Repository** : `AtakQrfRepository` avec 13 méthodes publiques
- **API** : 5 endpoints REST
  - `GET /api/atak/qrf` : Liste QRF
  - `POST /api/atak/qrf` : Créer demande QRF
  - `POST /api/atak/qrf/{id}/assign` : Assigner QRF
  - `POST /api/atak/qrf/{id}/position` : Mettre à jour position QRF
  - `POST /api/atak/qrf/{id}/sitrep` : Ajouter SITREP
- **Features** :
  - Types menace : AMBUSH, ATTACK, TROOPS_IN_CONTACT, CASEVAC_URGENT, IED_STRIKE, OTHER
  - Taille ennemi : FIRE_TEAM, SQUAD, PLATOON, COMPANY, UNKNOWN
  - Statut unité amie : SECURE, ENGAGED, PINNED, OVERRUN, RETREATING
  - Workflow : REQUESTED → ACKNOWLEDGED → QRF_ASSIGNED → QRF_ENROUTE → QRF_ENGAGED → SITUATION_STABILIZED → COMPLETED
  - Tracking position QRF temps réel
  - Calcul distance vers zone contact (formule euclidienne)
  - ETA dynamique
  - SITREP multi-source (demandeur + QRF)
  - Types SITREP : STATUS_CHANGE, POSITION_UPDATE, SITUATION_UPDATE, CONTACT_REPORT
  - Waypoints route QRF
  - Deadline urgence (FLASH : 5min, IMMEDIATE : 15min, PRIORITY : 30min)
  - Support demandé multiples : infantry, armor, aviation, cas, medevac, eod, engineers

#### Suivi véhicules et assets lourds enrichi
- **Tables** : `atak_vehicle_tracking`, `atak_vehicle_position_history`, `atak_vehicle_events`, `atak_vehicle_service_requests`
- **Vue** : `v_atak_active_vehicles` avec statut fuel/ammo
- **Triggers** :
  - `trg_vehicle_deployed` : Logging déploiement
  - `trg_vehicle_destroyed` : Logging destruction
- **Repository** : `AtakVehicleTrackingRepository` avec 16 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atek/vehicles` : Liste véhicules
  - `POST /api/atak/vehicles` : Upsert véhicule (create or update intelligent)
  - `POST /api/atak/vehicles/{id}/service` : Demander service
  - `GET /api/atak/vehicles/service-requests` : Liste demandes service
- **Features** :
  - 10 classes véhicules : LIGHT_VEHICLE, TRUCK, APC, IFV, TANK, ARTILLERY, HELICOPTER, FIXED_WING, UAV, BOAT
  - Côté : BLUFOR, OPFOR, INDEPENDENT, CIVILIAN
  - Statuts : OPERATIONAL, DAMAGED, IMMOBILIZED, DESTROYED, ABANDONED
  - Types mission : TRANSPORT, COMBAT, RECON, SUPPLY, MEDEVAC, CAS, CAP, PATROL, LOGISTICS
  - Tracking complet :
    - Position GPS (pos_x, pos_y)
    - Cap et vitesse
    - Fuel % (alerte < 20%)
    - Munitions % (alerte < 30%)
    - Santé composants : moteur, coque, chenilles/roues, tourelle
  - Upsert intelligent par callsign :
    - Si véhicule existe : mise à jour sélective (seulement champs fournis)
    - Si nouveau : création complète
    - Update automatique `last_seen_at`
  - Historique positions (table séparée pour replay)
  - Événements automatiques :
    - DEPLOYED, DESTROYED, DAMAGED, REPAIRED, ABANDONED, RECOVERED, REFUELED, REARMED
  - Demandes service :
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Priorités : LOW, MEDIUM, HIGH, CRITICAL
    - Statuts : REQUESTED, ACKNOWLEDGED, IN_PROGRESS, COMPLETED, CANCELLED
  - Calcul distance vers destination
  - Statut "véhicule actif" (vu < 30min)
  - Labels fuel/ammo : CRITICAL, LOW, MEDIUM, OK, FULL
  - Équipage et passagers tracking

### Documentation

#### Guides utilisateur et intégration
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Guide complet 31 endpoints
  - Exemples SQF pour mod Arma
  - Exemples JavaScript pour interface web
  - Formats requêtes/réponses détaillés
  - Codes erreurs
  - Notes performance et sécurité

#### Documentation technique
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Synthèse technique complète
  - Architecture système
  - Détails base de données (15 tables, 5 vues, 4 triggers)
  - Pattern repositories
  - Sécurité et performance
  - Tests recommandés
  - Roadmap Phase 3-5

#### Proposition features
- **`docs/NOUVELLES-FEATURES-ATAK-MOD.md`** : Proposition 15 features sur 5 phases
  - Features détaillées avec cas d'usage
  - Notes implémentation
  - Priorités P0/P1/P2

#### Documentation produit
- **`docs/COMPARAISON-PRODUIT-COMSPEC-CTAB-SIT.md`** : Comparaison produits
- **`docs/ATAK-WEB-DOCUMENTATION-PRODUIT.md`** : Doc ATAK Web
- **`docs/ATHENA-MYTHOLOGIE.md`** : Lien mythologique
- **Variantes forum** : `*-VERSION-FORUM.md` (sans URLs/tableaux)

### Migration

#### Scripts SQL
- **`migrations/2026_07_24_001_atak_tactical_reports.sql`** : Phase 1.1
- **`migrations/2026_07_24_002_atak_poi_intelligence.sql`** : Phase 1.2
- **`migrations/2026_07_24_003_atak_tactical_zones.sql`** : Phase 1.3
- **`migrations/2026_07_24_004_atak_medevac_extended.sql`** : Phase 2.1
- **`migrations/2026_07_24_005_atak_qrf_system.sql`** : Phase 2.2
- **`migrations/2026_07_24_006_atak_vehicle_tracking.sql`** : Phase 2.3

Toutes les migrations :
- Sont idempotentes (`IF NOT EXISTS`)
- Commentées en détail
- Incluent contraintes FK
- Définissent index stratégiques
- Multi-tenant natives

### Sécurité

#### Multi-tenant
- Isolation complète par `tenant_id` + `context_id`
- Contraintes FK vers `tenants` et `contextes`
- Filtrage systématique dans repositories

#### Soft delete
- Implémenté sur : reports, POI, zones
- Permet restauration et audit
- Vues filtrent automatiquement

#### Protection SQL
- Requêtes préparées PDO
- Pas de concaténation SQL
- Paramètres bindés

### Performance

#### Optimisations base de données
- Index composites sur (tenant_id, context_id)
- Index géographiques sur (pos_x, pos_y)
- Colonnes calculées STORED
- Vues enrichies pour éviter N+1 queries

#### Optimisations API
- Pagination par défaut (limit: 100-200)
- Filtres côté SQL
- Sélection colonnes via vues

### Ajouté - Phase 2.5 : Intelligence & Automatisation

#### Auto-routage intelligent des rapports
- **Tables** : `atak_report_routing_rules`, `atak_report_routing_history`
- **Repository** : `AtakReportRoutingRepository` avec 7 méthodes publiques
- **Features** :
  - Règles routage configurables par tenant
  - Critères : type rapport, priorité, mots-clés, position géographique
  - Distribution automatique aux destinataires pertinents (rôles, utilisateurs)
  - Escalade temporelle (ROUTINE : +24h, PRIORITY : +4h, IMMEDIATE : +1h)
  - Historique complet distributions
  - État lecture/accusé réception par destinataire
  - Méthode `applyRoutingRules()` : matching intelligent
  - Méthode `routeReport()` : distribution multi-canal

#### Calcul dynamique menace zones
- **Tables** : `atak_zone_events`
- **Repository** : `AtakZoneThreatRepository` avec 9 méthodes publiques
- **Trigger** : `trg_zone_threat_recalc` : Recalcul automatique après événement
- **Vue enrichie** : `v_atak_zone_threat` avec `current_threat_level`
- **Features** :
  - Événements trackés : CONTACT, EXPLOSION, GUNFIRE, IED, CASUALTY, OBSERVATION
  - Impact événement sur score menace (CONTACT: +30, EXPLOSION: +40, IED: +50)
  - Expiration temporelle (2h par défaut, décroissance progressive)
  - Prise en compte POI hostiles dans rayon 500m
  - Seuils adaptatifs : LOW (<30), MEDIUM (30-60), HIGH (60-80), CRITICAL (>80)
  - Recalcul automatique toutes zones affectées
  - Méthode `calculateThreatImpact()` : formule complexe
  - Méthode `countNearbyThreats()` : agrégation géospatiale

#### Notifications temps réel enrichies
- **Table** : `atak_realtime_notifications`
- **Repository** : `AtakNotificationRepository` avec 8 méthodes publiques
- **Features** :
  - Types : REPORT, MEDEVAC, QRF, VEHICLE, ZONE, GENERAL
  - Priorités : INFO, WARNING, URGENT, CRITICAL
  - Destinataires : ALL, COMMAND, SPECIFIC_ROLE, SPECIFIC_USER
  - Expiration automatique (TTL configurable)
  - État lu/non-lu par utilisateur
  - Polling endpoint `/api/atak/notifications/poll?since={timestamp}`
  - Notifications spécialisées :
    - Golden hour warnings (< 15min)
    - Alertes véhicule critique (fuel <5%, dégâts >70%)
    - Zone menace CRITICAL dépassée
    - Nouveaux rapports IMMEDIATE/FLASH
  - Cleanup automatique notifications expirées

#### Scoring urgence MEDEVAC & Asset optimal
- **Table** : `atak_medical_assets`
- **Repository** : `AtakMedevacIntelligenceRepository` avec 8 méthodes publiques
- **Features** :
  - **Scoring urgence patients** :
    - Patients T1 (urgent) : +50 points
    - Golden hour < 30min : +30 points
    - Zone pickup menace HIGH/CRITICAL : +20 points
    - Conditions météo défavorables : -10 points
    - Formule finale : `urgency_score = base_score + modifiers`
  - **Sélection asset optimal** :
    - Calcul ETA précis (distance euclidienne + vitesse asset)
    - Capacité patients (litter vs ambulatory)
    - Disponibilité temps réel
    - Risque trajet (zones menace traversées)
    - Méthode `findOptimalAsset()` : algorithme complet
    - Score asset : `(100 - distance_score) + capacity_score - risk_score`
  - **Évaluation menace LZ** :
    - Comptage POI hostiles <500m
    - Événements récents zone
    - Niveau menace zone tactique
    - Retour : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - **Vue enrichie** : `v_atak_medevac_urgency` avec score et asset recommandé

#### Route QRF optimale
- **Table** : `atak_qrf_coordination`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes QRF)
- **Features** :
  - **Calcul route optimale** :
    - Algorithme A* avec pénalités zones menace
    - Évitement NO-GO zones (pénalité infinie)
    - Contournement zones HIGH threat (+40% distance)
    - Préférence zones LOW threat (-10% distance)
    - Méthode `calculateOptimalQrfRoute()` : pathfinding complet
  - **Génération waypoints** :
    - Waypoints intermédiaires espacés 500m
    - Ordre séquence automatique
    - Calcul distance totale route
    - ETA basé vitesse unité
    - Méthode `generateWaypoints()` : interpolation intelligente
  - **Hazards route** :
    - Détection obstacles/menaces sur trajet
    - Liste zones menace traversées
    - Suggestions contournement
    - Méthode `findHazardsAlongRoute()` : analyse géospatiale
  - **Coordination multi-QRF** :
    - Évite duplication efforts
    - Suggestions split objectifs
    - Calcul distance inter-QRF
    - Table `atak_qrf_coordination` pour sync

#### Maintenance prédictive véhicules
- **Table** : `atak_vehicle_maintenance_log`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes véhicules)
- **Vue enrichie** : `v_atak_vehicle_health` avec `maintenance_score`
- **Features** :
  - **Calcul score maintenance** :
    - Distance parcourue depuis dernière maintenance (40% poids)
    - Historique pannes/dommages (30% poids)
    - Santé composants actuels (30% poids)
    - Formule : `score = distance_factor * 0.4 + history_factor * 0.3 + health_factor * 0.3`
    - Échelle 0-100 (100 = maintenance urgente)
  - **Prédiction panne** :
    - Temps estimé avant panne critique
    - Basé tendance dégradation composants
    - Machine learning simple (régression linéaire)
    - Méthode `predictFailureTime()` : extrapolation
  - **Recommandations automatiques** :
    - "Maintenance préventive sous 48h" (score 60-80)
    - "Inspection moteur urgente" (engine_health < 50%)
    - "Remplacement chenilles recommandé" (track_health < 40%)
    - "Véhicule à immobiliser" (score > 90)
    - Méthode `generateMaintenanceRecommendations()` : règles métier
  - **Log maintenance détaillé** :
    - Type : ROUTINE, PREVENTIVE, CORRECTIVE, EMERGENCY, INSPECTION
    - Composants concernés
    - Pièces remplacées
    - Coût et durée
    - Technicien et lieu

#### Corrélation POI intelligence
- **Table** : `atak_poi_correlations`, `atak_intelligence_analysis`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes POI)
- **Vue enrichie** : `v_atak_poi_enriched` avec `confidence_score`
- **Features** :
  - **Détection corrélations** :
    - Proximité géographique (<500m)
    - Affiliation identique (ENEMY)
    - Compatibilité type (CACHE ↔ ENEMY_POSITION)
    - Temporalité (observations <24h)
    - Méthode `detectPoiCorrelations()` : matching multi-critères
  - **Scoring confiance POI** :
    - Nombre observations (20% poids)
    - Source fiabilité (30% poids)
    - Fraîcheur temporelle (25% poids)
    - Corrélations avec autres POI (25% poids)
    - Formule : `confidence = obs_score + source_score + fresh_score + corr_score`
    - Échelle 0-100 (100 = haute confiance)
    - Méthode `calculatePoiConfidence()` : algorithme complet
  - **Analyse intelligence** :
    - Patterns détectés (clusters hostiles)
    - Suggestions tactiques ("Surveillance zone recommandée")
    - Niveau confiance analyse
    - Méthode `analyzePoiPair()` : corrélation détaillée
  - **Types corrélation** :
    - PROXIMITY : Proximité géographique simple
    - PATTERN : Pattern comportemental
    - ACTIVITY : Activité liée
    - TEMPORAL : Séquence temporelle
    - NETWORK : Réseau hostile

### Migration Phase 2.5

#### Script SQL
- **`migrations/2026_07_24_007_atak_intelligence_enhancements.sql`** : Phase 2.5
  - 9 nouvelles tables
  - 5 vues enrichies
  - 1 trigger calcul menace
  - ~400 lignes SQL commentées
  - Alter tables existantes (ajout colonnes calculées)

### Documentation Phase 2.5

#### Guide technique enrichissements
- **`docs/PHASE-2.5-INTELLIGENCE-ENRICHMENTS.md`** : Documentation complète
  - Détails 6 capacités intelligence
  - Algorithmes expliqués (pseudocode)
  - Cas d'usage opérationnels
  - Diagrammes workflow
  - ~1000 lignes

#### Mise à jour guides existants
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Ajout endpoints Phase 2.5
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Mise à jour stats (15 tables → 24 tables)
- **`CHANGELOG-ATAK.md`** : Mise à jour version 1.1.0

### Ajouté - Phase MOD : Intégration Arma 3

#### Fonctions SQF tactiques (11 fichiers, ~800 lignes)
- **Localisation** : `mod/@COMSPECOverwatch/addons/connect/functions/`
- **Fonctions principales** :
  - `fn_submitTacticalReport.sqf` : Soumettre rapport (SPOTREP, CONTACT, SITREP, SALUTE)
    - Validation type rapport
    - Sérialisation données structurées
    - Appel extension HTTP
    - Feedback visuel (hint + son confirmation)
    - Logging RPT détaillé
  - `fn_createPOI.sqf` : Créer POI
    - Position automatique (player ou cursorTarget)
    - Marker local temporaire (5min)
    - Catégories prédéfinies
    - Transmission immédiate backend
  - `fn_requestMEDEVAC.sqf` : Demander MEDEVAC 9-Line
    - Format standard NATO
    - Calcul automatique patients litter/ambulatory
    - Intégration ACRE/TFAR (fréquence radio)
    - Hints critiques visuels
    - Son radio transmission
  - `fn_requestQRF.sqf` : Demander QRF
    - Types menace standardisés
    - Estimation force amie automatique (count units group)
    - Suggestions support selon situation
    - Marker local contact (cercle rouge)
    - Son alerte urgence
  - `fn_updateVehicleTracking.sqf` : Update véhicule
    - Données complètes (callsign, classe, side, crew)
    - Position, heading, speed
    - Fuel %, ammo %, health composants
    - Détection automatique état critique
    - Trigger service requests si nécessaire
  - `fn_requestVehicleService.sqf` : Service véhicule
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Feedback priorité (fumée verte si CRITICAL)
    - Marker temporaire service
    - Son alerte si urgent
  - `fn_initVehicleTracking.sqf` : Init tracking auto
    - Event handlers : GetInMan, GetOutMan, Killed
    - CBA PerFrameHandler (update 10s)
    - Start/stop automatique
    - Report destruction immédiat
- **Fonctions helpers** :
  - `fn_hashMapToJson.sqf` : Sérialisation HashMap → JSON
    - Support types : STRING, SCALAR, BOOL, ARRAY, HASHMAP
    - Récursif pour structures imbriquées
    - Échappement strings correct
    - ~100 lignes
  - `fn_formatTimestamp.sqf` : Format timestamp SQL
    - Input : systemTime array Arma
    - Output : `YYYY-MM-DD HH:MM:SS`
    - Padding zéros

#### Système menus ACE Interact (~250 lignes)
- **Fichiers** :
  - `fn_initATAKMenu.sqf` : Création menus ACE
  - `fn_initATAK.sqf` : Initialisation principale
  - `XEH_postInitClient.sqf` : Hook CBA Extended Event Handlers
- **Structure menus** :
  ```
  ACE Self-Interact → 📡 ATAK Tactique
    ├─ 📝 Rapports Tactiques
    │   ├─ SPOTREP (Observation)
    │   ├─ CONTACT (Ennemi) [priorité IMMEDIATE auto]
    │   └─ SITREP (Situation)
    ├─ 📍 Marquer POI
    │   ├─ Cache d'armes (ENEMY, PROBABLE)
    │   ├─ Position Ennemie (CONFIRMED)
    │   └─ Objectif (NEUTRAL)
    ├─ 🚁 Demander Appui
    │   ├─ MEDEVAC (9-Line, transmission radio auto)
    │   └─ QRF (marker contact + alerte)
    └─ 🔧 Service Véhicule [si dans véhicule]
        ├─ ⛽ Ravitaillement [si fuel <30%]
        ├─ 🔫 Réarmement
        └─ 🔨 Réparation [si damage >20%]
  ```
- **Conditions dynamiques** :
  - Sous-menu véhicule uniquement si `vehicle player != player`
  - Actions service selon état véhicule (fuel, damage)
- **Feedback visuel** :
  - Hints notifications structurées
  - Markers locaux temporaires (POI, contact, service)
  - Fumée signalisation (verte pour demandes critiques)
  - Sons : radio, alerte, confirmation
- **Raccourcis clavier CBA** :
  - **Shift+R** : Rapport contact rapide (`comspec_atak_quick_report`)
  - **Shift+P** : POI rapide position actuelle (`comspec_atak_quick_poi`)
- **Initialisation automatique** :
  - Vérification extension au boot (`GetVersion`)
  - Init tracking véhicules
  - Création menus ACE (si addon détecté)
  - Event handlers respawn (réinit système)
  - Boucle maintenance 60s (polling futures)

#### Extension C# v2.0 (~350 lignes)
- **Localisation** : `mod/@COMSPECOverwatch/extension-source-example/`
- **Fichiers** :
  - `ExtensionMain.cs` : Code principal
  - `COMSPECExtension.csproj` : Projet .NET 6
  - `build.sh` / `build.bat` : Scripts compilation
  - `README.md` : Documentation complète
- **Architecture** :
  ```
  Arma 3 (SQF)
    ↓ callExtension "COMSPECExtension"
  RVExtensionArgs (C# DLL)
    ↓ ProcessCommand(command, jsonData)
  SendHttpRequest(method, endpoint, json)
    ↓ HttpClient.PostAsync()
  Backend API PHP
  ```
- **Commandes implémentées** :
  - `GetVersion` → Retourne "2.0"
  - `Connect` → Init API (URL + Token)
  - `SubmitTacticalReport` → POST `/api/atak/reports`
  - `CreatePOI` → POST `/api/atak/poi`
  - `RequestMEDEVAC` → POST `/api/atak/medevac`
  - `RequestQRF` → POST `/api/atak/qrf`
  - `UpdateVehicleTracking` → POST `/api/atak/vehicles`
  - `RequestVehicleService` → POST `/api/atak/vehicles/service`
- **Optimisations** :
  - HttpClient singleton (connection pooling)
  - Retry policy 3x avec backoff exponentiel (5xx seulement)
  - Timeout 10s configurable
  - Cache vehicle_id local (évite lookups répétés)
  - Logs détaillés : `%LOCALAPPDATA%\Arma 3\COMSPECExtension.log`
- **Gestion erreurs** :
  - Codes retour : `OK`, `ERROR`, `NETWORK_ERROR`, `TIMEOUT`
  - Messages structurés JSON : `["status", "message"]`
  - Logging toutes erreurs avec timestamp
- **Sécurité** :
  - Tokens passés dynamiquement (config CBA)
  - Headers `X-ATAK-Token`, `User-Agent`
  - HTTPS obligatoire production
  - Validation inputs côté SQF avant envoi
- **Technologies** :
  - .NET 6.0
  - Newtonsoft.Json (sérialisation)
  - UnmanagedExports (export DLL pour Arma)

#### Configuration et intégration
- **`mod/@COMSPECOverwatch/addons/connect/config.cpp`** (modifié) :
  - Déclarations 11 nouvelles fonctions
  - Hook CBA Extended Event Handlers :
    ```cpp
    class Extended_PostInit_EventHandlers {
        class comspec_overwatch_connect {
            clientInit = "call compile preprocessFileLineNumbers '...XEH_postInitClient.sqf'";
        };
    };
    ```
  - Version bumped : 1.1.3 → 1.2.0
- **Prérequis** :
  - Arma 3
  - CBA A3 (obligatoire)
  - ACE3 (recommandé pour menus)
  - Extension `COMSPECExtension_x64.dll` v2.0
- **Configuration CBA** :
  - **Option A : Liaison rapide** (recommandée)
    1. Athena → ATAK → "Générer code liaison"
    2. En jeu : K → "Connecter compte" → coller code
  - **Option B : Manuelle**
    - URL : `https://athena.ttrd.fr/public`
    - Token ATAK : Généré sur interface web
    - ID Communauté : Fourni par admin
- **BattlEye** :
  - Whitelist DLL dans `battleye/beserver_x64.cfg` :
    ```cpp
    allowedLoadFileExtensions[] = {"dll"};
    allowedPreloadFileExtensions[] = {"dll"};
    ```

### Documentation MOD

#### Guides utilisateur (4 documents, ~2000 lignes)
- **`mod/@COMSPECOverwatch/README.md`** : Documentation principale enrichie
  - Section complète features ATAK détaillées
  - Raccourcis clavier (ajout Shift+R, Shift+P)
  - Menus ACE avec hiérarchie complète
  - Tracking véhicules automatique
  - Prérequis et installation
- **`mod/@COMSPECOverwatch/GUIDE-INSTALLATION-TEST.md`** : Guide complet
  - Installation étape par étape (mod, config CBA, extension)
  - 7 tests manuels détaillés (rapport, POI, MEDEVAC, QRF, tracking, service)
  - Script test automatique SQF
  - Troubleshooting complet (extension not found, 401, menus absents, tracking inactif)
  - Checklist déploiement production
- **`mod/@COMSPECOverwatch/EXTENSION_C#_SPECIFICATION.md`** : Spec technique extension
  - Détails 8 commandes avec formats JSON
  - Exemples C# implémentation
  - Configuration HTTP client (headers, timeout, retry)
  - Gestion erreurs et logging
  - Optimisations recommandées (cache, batching, async)
  - Tests recommandés (unitaires + intégration)
  - Compilation et déploiement
- **`mod/@COMSPECOverwatch/extension-source-example/README.md`** : Doc extension
  - Prérequis développement (Visual Studio, .NET 6)
  - Commandes disponibles avec exemples
  - Architecture code (diagramme)
  - Performance et optimisations
  - Contribution et ajout nouvelles commandes
  - Sécurité (tokens, HTTPS)
  - Troubleshooting compilation

### Documentation projet

#### Synthèses et récapitulatifs
- **`docs/RECAPITULATIF-INTEGRATION-MOD-ATAK.md`** : Synthèse MOD
  - Composants livrés détaillés
  - Structure menus ACE complète
  - Statistiques code (~2900 lignes MOD)
  - Intégration backend (31 endpoints)
  - Tests et validation
  - Déploiement et configuration
- **`docs/SYNTHESE-FINALE-INTEGRATION-ATAK.md`** : Document maître (~850 lignes)
  - Vue d'ensemble Backend + MOD
  - Architecture complète (BDD, repos, API, SQF, extension)
  - Algorithmes intelligence Phase 2.5 détaillés
  - Statistiques globales (17100 lignes produites)
  - Fonctionnalités opérationnelles (joueurs + commandement)
  - Tests & validation
  - Guide déploiement complet
  - Roadmap futures phases

### Dépendances

#### Backend
- PHP >= 8.0
- MySQL >= 8.0
- Extension PDO MySQL
- Authentification COMSPEC existante

#### Frontend (en attente Phase JS)
- Leaflet.js
- JavaScript ES6+

#### Mod Arma 3
- ✅ CBA A3 (obligatoire)
- ✅ ACE3 (recommandé)
- ✅ Extension C# .NET 6
- ✅ SQF functions (11 fichiers)
- ✅ Arma 3 >= 2.0

---

## [À venir] - Phase 3 : Coordination avancée

### Planifié

#### Waypoints et routes partagées
- Table `atak_shared_waypoints`
- Synchronisation bidirectionnelle web ↔ jeu
- Calcul distance et temps estimé
- Routes partagées entre unités
- Visualisation temps réel sur carte

#### Timeline mission interactive
- Table `atak_mission_timeline`
- Agrégation tous événements (rapports, contacts, MEDEVAC, QRF, etc.)
- Filtres par type, unité, criticité
- Navigation temporelle
- Export PDF/Excel pour AAR

#### Contrôle artillerie et mortiers
- Table `atak_fire_missions`
- Calcul balistique (élévation, azimut, charge)
- Visualisation zone impact
- Workflow mission feu NATO
- Corrections tir (shot, splash, impact)

---

## [À venir] - Phase 4 : Capacités avancées

### Planifié

#### Système UAV et reconnaissance
- Table `atak_uav_tracking`
- Flux vidéo (captures périodiques)
- Détection automatique contacts
- Zones surveillance
- Handoff entre opérateurs

#### IFF avancé
- Extension système IFF existant
- Interrogation active
- Code du jour dynamique
- Alertes véhicule inconnu
- Intégration avec véhicules

#### Intégration météo opérationnelle
- Table `atak_weather_log`
- Impact visibilité/portée
- Alertes conditions critiques
- Prévisions mission
- Historique pour AAR

---

## [À venir] - Phase 5 : Immersion totale

### Planifié

#### Mode replay complet
- Reconstruction mission 3D
- Contrôles vidéo (play, pause, vitesse)
- Changement point de vue
- Export MP4
- Analyse post-mission

#### Système certifications LMS
- Intégration avec LMS existant
- Déblocage capacités selon certification
- Badges visibles in-game
- Progression utilisateur
- Rapports performance

#### Contrôle caméra et observation
- Stream images caméras terrain
- Demande vues spécifiques
- Contrôle PTZ (pan, tilt, zoom)
- Archive pour AAR
- Multi-flux simultanés

---

## Versionning

**Format** : [MAJOR.MINOR.PATCH]

- **MAJOR** : Changements incompatibles API
- **MINOR** : Ajout features rétrocompatibles
- **PATCH** : Corrections bugs rétrocompatibles

**Version actuelle** : 1.2.0 (Phases 1, 2, 2.5 + MOD Arma 3 complètes)

---

*Document maintenu par l'équipe développement COMSPEC*
