# Générateur SSE

Version anglaise : [generateur.en.md](generateur.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Le générateur fabrique en un clic le **contenu de renseignement** d'une personne, d'un objet ou d'un site complet : identité, téléphone (contacts, SMS, appels), documents, biométrie, liens entre cibles, supports numériques. Trois niveaux de pilotage : un **profil** (« fais-moi un financier crédible »), un **modèle** (recette toute prête, 24 intégrés + les vôtres), un **dataset** (scénario fixe : FALCON, mêmes noms et liens pour tout le monde).

## Où le trouver

Modules Eden et Zeus (catégorie **COMSPEC · Renseignement (SSE)**, et **COMSPEC · Mission** pour le brief et le scénario) ; Zeus : bouton **BRIEF / GÉNÉRER** de l'écran Contrôle Zeus SSE.

| Module | Cible | Champs |
|---|---|---|
| **Générer un profil** | Personne ou objet | Profil (Insurgé, Civil, Militaire, Chef / HVT, Courrier, Financier, Technicien / artificier, Renseignement, Logistique, Aléatoire) · Richesse (Légère — quelques indices / Standard / Détaillée / Haute valeur — dossier riche) · cases Identité réelle (Arma / Eden), Téléphone, Documents, Biométrie, Lier les cibles entre elles · Données inutiles (0 / 10 / 25 / 50 / 75 %) |
| **Créer un site complet** | Position | Rayon (15-150, 35) · Type de site (Cellule insurgée, Position militaire, Site civil, Poste de commandement, Nœud logistique, Réseau financier, Atelier technique / IED, Aléatoire) · Richesse · Objets exploitables (max) (0-30, 8) · Supports numériques, Documents, Liens réseau |
| **Rendre exploitable** | Objet | Nature de l'objet (Automatique, Objet divers, Document, Téléphone, Ordinateur, Radio, Conteneur / caisse, Arme, Véhicule) · Profil · Richesse |
| **Poser un objet de renseignement** | Position | Objet (Ordinateur portable, Smartphone, Téléphone basique, Téléphone satellite, Radio portable, Dossier papier, Photos / dossier, Valise, Caisse d'armes) · Profil · Richesse · Générer le contenu au lancement |
| **Appliquer un modèle** | Personne ou objet | Modèle intégré (« Choisir au Zeus (liste à la pose) » ou un `builtin_*`) · Autre modèle (identifiant) |
| **Enregistrer un modèle** (Zeus) | Cible générée | Nom du modèle (« Mon modèle SSE ») : la cible devient une recette réutilisable |
| **Lister les modèles** (Zeus) | — | Affiche « n modèle(s) SSE disponibles : • nom [source] — id » |
| **Générer depuis un brief** | Position | Brief narratif (« cellule de 3 financiers », « atelier IED », « chef et courrier ») · Pack scénario (Aucun — utiliser le brief, Cellule insurgée, Réseau de contrebande, Dépôt d'armes, Poste de commandement, Planque, Atelier IED, Nœud financier, Cellule renseignement, Dataset FALCON (Irak 2012)) · Rayon (20-200, 40) |
| **Diriger le scénario** | Position | Action (Appliquer le dataset / Changer le niveau seulement / Lister les datasets) · Dataset (« FALCON — cellule Irak 2012 (6 rôles) ») · Niveau de révélation (0 — Surface, 1 — Tactique, 2 — Terrain, 3 — Vérité complète) · Rayon (20-300, 50 : la personne la plus proche devient le chef) |
| **Créer un site d'entraînement** | Position | Scénario (Aléatoire + 8 packs) · Rayon (20-150, 60) |
| **Poser un site** / **Peupler un bâtiment** / **Semer des preuves** / **Relier plusieurs sites** | Position | Type de site (Planque, Labo d'IED, Atelier de faux papiers, Cache d'armes, PC de cellule, Boîte aux lettres morte, Nœud hawala / changeur), Occupants, Camp des occupants, Occupants armés, Richesse, Pièges, Dans un bâtiment proche ; Semer : Rayon 30, Nombre de pièces 6 ; Relier : Nombre de sites 4, Rayon 3 km |
| **Lier des entités** | Sélection | Relation (Associé, Contact, Propriétaire de, Fait référence à) · Confiance (0,3 / 0,5 / 0,7 / 0,9) · Disposition (Chaîne / Étoile) |

Attributs Eden équivalents sur chaque personne ou objet : **COMSPEC SSE — Renseignement** (Activer le renseignement SSE, Nature de l'objet, Profil, Moment de la génération, Richesse, Identité, Ce que la base doit répondre, Nom, Prénom, Alias connu, Graine, Rôle déclaré, Nationalité, Langue, Biométrie, Notes Zeus, Modèle SSE, Dataset mission, Rôle dans le dataset, Région / théâtre), **— Documents** (Pièce 1 à 3 : intitulé, contenu, grille, mot de code), **— Téléphone** (Numéro, Modèle, Contacts, Messages, Notes, Lieux), **— Ordinateur** (Nom, Compte, Fichiers, Courrier, Réseau, Indice d'accès).

## Pas à pas : une cellule en cinq minutes

Situation : un village, cinq IA civiles posées par le Zeus, à transformer en cellule logistique.

1. Zeus › **Générer depuis un brief** sur la place du village : Brief « cellule logistique de 5 personnes », Pack **Aucun — utiliser le brief**, Rayon 40. Notification « Réseau SSE généré (…) — rayon 40 m ». Les cinq IA ont identité, téléphone, documents, biométrie et liens.
2. **Générer un profil** sur le chef : Profil **Chef / HVT**, Richesse **Haute valeur — dossier riche**.
3. **Poser un objet de renseignement** dans la maison : Objet **Ordinateur portable**, Profil **Logistique**.
4. **Enregistrer un modèle** sur le chef : « Chef logistique village » ; à réutiliser par **Appliquer un modèle › Autre modèle (identifiant)**.
5. Vérifiez avec **Comparer connu et vérité** (écran Zeus) : tout est en « vérité », rien en « connu des joueurs ».

## Modèles intégrés

Cellule insurgée — Irak (`builtin_cellule_insurgee_irak`), Chef HVT, Réseau courriers, Financier, Technicien IED, Safehouse urbain, Contrebande frontière, Civil non pertinent (bruit), Cellule drone / ISR, Propagande / média, Irak 2010-2020 (Cache d'armes, Cellule IED, Chef de secteur, Courrier frontière, Relais financier, Planque urbaine), Russie 2020-2024 (Reconnaissance, Logistique, Poste de commandement, Cellule drone, Radio / EW, Info ops, Courrier civil, Civil couverture). Les `builtin_` ne se suppriment pas.

Dataset **FALCON** (Irak 2012, 6 rôles) : Chef de secteur ABU KARIM (HVT), Technicien IED « L'INGÉNIEUR », Courrier frontière « LE CHAUFFEUR », Relais financier « LE CHANGEUR », Gardien de planque « LE LOCATAIRE », Civil (bruit de fond). Attribut Eden **Rôle dans le dataset** pour fixer un rôle sur une personne.

## Pièges fréquents

- Le générateur **n'agit jamais sur les joueurs**.
- **Même graine, mêmes résultats** : Graine (0 = automatique) sur les attributs et modules ; un dataset est fixe pour tout le monde.
- Le brief ne comprend que quelques mots-clés (effectif 1-10, « trois », « cinq » ; financ, ied / engin / explosi, courrier, command / chef) ; pour le reste, utilisez un pack.
- **Données inutiles** (25 % par défaut) et réglages Génération (Part d'infos sans intérêt 0,25, Part de fausses pistes 0,05) ajoutent du bruit volontaire : c'est voulu.
- Un objet généré n'a son menu SSE que s'il est **marqué SSE** (le générateur le fait ; un objet posé à la main a besoin de **Rendre exploitable**).
- **Réinitialiser un site** (Zeus, Rayon 50) et **Effacer des données** (confirmation) nettoient avant de rejouer.

## Athena

- **Nom de code de la mission** et **Référence du dossier SSE** (réglages Réseau) accompagnent tout ce qui est transmis.
- Les datasets et modèles de mission se consultent dans Bureau SSE › **Modèles de mission** ; les profils IA du Bureau SSE se choisissent dans la page [IA de la zone](ia_de_la_zone.md).
