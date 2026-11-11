# Modules Zeus et Eden SSE

Version anglaise : [modules_zeus_eden.en.md](modules_zeus_eden.en.md). Sommaire : [index.md](index.md). Tutoriels et modules de génération en détail : [../guide/Modules_et_materiel_SSE.md](../guide/Modules_et_materiel_SSE.md) (certains noms y sont anciens : les noms exacts sont ci-dessous).

## À quoi ça sert

Trois catégories de modules, communes à Eden (Systèmes › Modules) et au Zeus (ZEN) : **COMSPEC · Renseignement (SSE)** (génération, sites, organisations, éditeur), **COMSPEC · Identité** (papiers, checkpoints, couvertures, HVT, BOLO) et **COMSPEC · Mission** (campagne, dossier, présentation des fiches, SEEK, scellés). S'y ajoutent **COMSPEC · IA RP** (texte libre avec les IA) et, dans Eden, les attributs des unités, groupes et objets.

## Zeus (ZEN) : COMSPEC · Identité

| Module | Cible | Champs | Effet |
|---|---|---|---|
| **Donner des papiers** | Personne | Fenêtre **Choisir un document** : Pays, Type (avec extensions), Aperçu, Nature, Procédé du faux, Qualité du faux (0-100, 50), Nom et prénom imprimés, champs du document, Cible (la personne / **Poser au sol**) ; **Numéro Athena** remplace tout | Crée le document ([documents_et_types.md](documents_et_types.md)) |
| **Révoquer des papiers** | Personne | Signalement : Volé / Perdu / Révoqué / Lever le signalement | Tous ses documents ; vu au N3 |
| **Voir les vrais papiers** | Personne | — | Vraie identité, nature, qualité, défauts, infiltration, HVT (Zeus seul) |
| **Poser un checkpoint IA** | Position | Rayon (0-100, 0 = réglage), Camp du poste (Automatique / BLUFOR / OPFOR / Indépendant / Civil (police)), Équipement du poste (Visuel seul / Scanner MRZ / Lecteur NFC / Liaison base), Sévérité (Laxiste / Normale / Stricte), Nom du poste | Les IA du camp dans la zone contrôlent |
| **Retirer un checkpoint IA** | Position | — | Retire le plus proche |
| **Déclencher un contrôle** | Personne | Équipement, Sévérité (si pas de checkpoint autour) | Le poste qui la couvre, sinon l'IA armée la plus proche (80 m) la contrôle |
| **Forcer un état IA** | Personne | État : Interpellé / Coopère / Ment (présente sa couverture) / Nerveux / Refuse / Fuit / Agressif / Se rend / Appelle des renforts / Calme (laisser partir) | Ordres réels ; l'IA redevient autonome après 30 s |
| **Configurer une IA** | Personne ou sélection | — | Ouvre la page [IA de la zone](ia_de_la_zone.md) |
| **Imposer un couvre-feu** | — | Couvre-feu : En vigueur / Levé | Civils dehors plus nerveux |
| **Donner une couverture** | Personne | Action (Infiltrer / Brûler la couverture / Lever l'infiltration), Captif tant que la couverture tient, Lui donner une carte de couverture, Nom et Prénom de la couverture | [ma_couverture.md](ma_couverture.md) |
| **Poser une zone interdite** | Position | Rayon (0-500, 100 ; 0 retire la plus proche), Nom | Un infiltré qui y entre accumule des soupçons |
| **Désigner une HVT** | Personne | Niveau (HVT (cible prioritaire) / Recherché), Alias, Vrai nom, Vrai prénom, Papiers connus, Notes, Lui donner une couverture, Nom et Prénom de la couverture | Au fichier, sur les avis (N3), au BOLO ; chasse HVT de l'app Tinder (FIND) |
| **Lancer un BOLO** | Personne (visage joint) ou position | Nom, Prénom, Alias, Niveau (Sous surveillance / Recherché / HVT), Papiers connus, Signalement | Diffusé aux checkpoints, à l'app Contrôle ID (onglet BOLO), à Recherchés, à Athena |
| **Lever un BOLO** | — | Liste des BOLO en cours | Retire (et lève sur Athena) |
| **Extensions** | — | Liste des extensions de documents, interrupteurs | [menu_extensions.md](menu_extensions.md) |

## Zeus (ZEN) : COMSPEC · Renseignement (SSE)

**Définir l'identité** (Ce que la base doit répondre, Alias connu, Nom, Prénom, Nationalité déclarée, Langue parlée, Référence de dossier antérieur) · **Régler la biométrie** (Fichier biométrique, Motif de signalement, Faux papiers, Vraie identité, Identifiant biométrique (campagne), Parent de, Lien de parenté, Doigts manquants, Iris) · **Ouvrir l'éditeur** · **Affecter à une organisation**, **Générer un réseau** (une seule personne : tout son groupe), **Rattacher des objets**, **Révéler une organisation**, **Retirer d'une organisation**, **Ouvrir le tableau des liens** · **Ajouter un renseignement** (sur un objet : Message, Document, Photographie, Contact, Coordonnée / point, Fréquence, Horaire, Manifeste, Objectif ; qualité Complet / Fragment / Peut être un leurre) · **Rendre extractible au câble** (DL-7) · modules de génération ([generateur.md](generateur.md)) · **Ouvrir le manuel**, **Choisir un chapitre du manuel**, **Poser un exercice** (5 civils d'une cellule, téléphone, documents, caisse).

Modules Zeus seulement (catégorie des modules) : **Ouvrir le panneau SSE**, **Consulter les données**, **Comparer connu et vérité**, **Gérer les sites** (Rayon 60), **Ouvrir le compte rendu (AAR)** (Rayon 150, Exporter le graphe), **Lister les modèles**, **Enregistrer un modèle**, **Réinitialiser un site** (Rayon 50), **Effacer des données**, **Inspecter (debug)**.

## Zeus (ZEN) : COMSPEC · Mission

**Charger une campagne**, **Sauvegarder la campagne**, **Voir l'état de la campagne**, **Remettre la campagne à zéro**, **Clore la mission**, **Exporter la campagne**, **Importer une campagne**, **Supprimer une sauvegarde**, **Envoyer la campagne à Athena**, **Campagne : récupérer depuis Athena** · **Doter d'un terminal SEEK** (sur un joueur : remis ; sinon Rayon 5-200, 30) · **Régler la présentation des documents** (Modèle préfait : Standard / Terrain / Brouillon / Archives / Bureau ; Aspect du papier ; Bandeau ; Pied de page) · **Altérer un scellé (exercice)** · **Fixer la référence du dossier** (module config).

## Eden : modules

| Catégorie | Modules |
|---|---|
| COMSPEC · Renseignement (SSE) | Générer un profil, Créer un site complet, Rendre exploitable, Poser un objet de renseignement, Appliquer un modèle, Créer un site d'entraînement, Lier des entités, Poser un site, Peupler un bâtiment, Semer des preuves, Relier plusieurs sites, Poser une caisse de kit (Kit SSE d'équipe / biométrie / exploitation numérique / Valise DOMEX), Créer une organisation (Nom, Nature, Rattachée à, Menace, Description, Rôles des personnes synchronisées, Graine, Au départ les joueurs savent), Marquer un support numérique, Fixer le palier d'accès, Poser un point carte, Préparer des données au câble, Ajouter un fichier au câble, Vider les données du câble, Effacer des données |
| COMSPEC · Identité | **Poser un checkpoint IA** (Rayon de la zone, Camp du poste, Équipement du poste, Sévérité, Nom du poste ; gardes = unités synchronisées), Définir l'identité d'un sujet |
| COMSPEC · Mission | Charger une campagne (Identifiant, Nom, Type Permanente / Récurrente / Temporaire, Copier la structure de, Sauvegarde automatique, Sauvegarder à la fin), Sauvegarder la campagne (Action, Motif), Générer depuis un brief, Diriger le scénario, Doter d'un terminal SEEK (Rayon 25), Régler la présentation des documents, Fixer la référence du dossier, Caisse de kits (Athena) |

Barre de menus **COMSPEC SSE** : Connexion Athena · Éditeur SSE · **Donner un document** · **Extensions** · Tableau de bord SSE · les chapitres du MANUEL SSE.

## Eden : attributs

| Catégorie (attributs de l'unité / objet) | Champs |
|---|---|
| **COMSPEC SSE — Papiers** (personnes) | Papiers portés (combo dynamique : Automatique, Aucun papier, Carte d'identité, Passeport, Permis de conduire, Carte d'identité et permis, Carte militaire, Carte d'identité et passeport, + types des extensions), Profil Athena, Comportement au contrôle, Pays des papiers, Nature des papiers (Vrais / Couverture préparée / Faux), Profil au contrôle (Automatique / Civil / Insurgé / HVT / Agent sous couverture / Policier, militaire ami), Qualité des faux papiers (60), Infiltré (couverture) |
| **COMSPEC SSE · Papiers du groupe** (groupes) | Profil Athena, Papiers, Comportement au contrôle |
| **COMSPEC SSE — Biométrie** | Fichier biométrique, Motif de signalement, Faux papiers, Vraie identité, Identifiant biométrique (campagne), Parent de, Lien de parenté, Doigts manquants, Iris ; objets : Empreintes latentes de |
| **COMSPEC SSE — Renseignement** | Activer le renseignement SSE, Nature de l'objet, Profil, Moment de la génération, Richesse, Identité, Ce que la base doit répondre, Nom, Prénom, Alias connu, Référence de dossier antérieur, Indice de confiance imposé, Graine, Rôle déclaré, Nationalité déclarée, Langue parlée, Numéro connu, Biométrie, Notes Zeus, ID réseau, Modèle SSE, Dataset mission, Rôle dans le dataset, Région / théâtre |
| **COMSPEC SSE — Documents / Téléphone / Ordinateur** | Pièces 1 à 3 ; Numéro, Modèle, Contacts, Messages, Notes, Lieux ; Nom, Compte, Fichiers, Courrier, Réseau, Indice d'accès |
| **COMSPEC SSE — Organisation** | Organisation, Cellule, Rôle (Chef … Sympathisant), Nom de guerre, Au départ les joueurs savent, Rattaché à l'organisation |
| **COMSPEC SSE · IA RP** (personnes) | **Question** (ce que le joueur peut lui demander, ex. « Où est le chef du village ? » ; les formulations proches marchent aussi), **Réponse de l'IA** (une ligne par réponse possible, `{prenom}`, `{ville}`…), Profil IA RP › **Atelier IA…** (caractère, histoire, réactions, **Toutes les questions et réponses…**, Options avancées : Ce qu'elle sait, Quand elle le dit) ; voir [reponses_ia.md](reponses_ia.md) |
| **COMSPEC SSE — Extraction DL-7** (objets) | Extractible au câble DL-7, Contenu, Fichiers saisis, Nom du support, Écran de connexion, Organisation affichée, Mot de passe, Indice, Essais permis (3), Trop d'essais, Forçage possible, Durée du forçage (90 s), Durée du transfert (45 s), Port (USB-C / Ethernet RJ45 / Série RS-232), Alarme, Effacé après le premier téléchargement |
| **Athena — Intelligence numérique** (objets) | Support exploitable (DOMEX), Identifiant, Type de support, Propriétaire apparent, Organisation, Réseau fictif, Exploitable, Accès physique, Accès distant, Sécurité, Profil de contenu, Durée d'exploitation, Palier d'accès, Paquets 1 et 2 |

## Pas à pas : une mission d'identité dans Eden

1. Module **Charger une campagne** (COMSPEC · Mission) : identifiant `OP-SABLE`, type Permanente.
2. Sur chaque civil clé : attributs **COMSPEC SSE — Papiers** (Papiers portés, Nature, Profil au contrôle) et **— Biométrie** (Fichier biométrique, Identifiant biométrique).
3. Module **Poser un checkpoint IA** au pont, gardes synchronisés, Équipement Scanner MRZ, Sévérité Normale.
4. Le joueur infiltré : attribut **Infiltré (couverture)**, Nature **Couverture préparée**.
5. Barre de menus › COMSPEC SSE › **Donner un document** pour un papier abandonné (cible Poser au sol).
6. Module **Fixer la référence du dossier** : `SSE-2026-0007`. Tableau de bord SSE pour vérifier.

## Pièges fréquents

- Les modules d'identité Zeus passent par le serveur et vérifient que le demandeur est Zeus ; un Zeus **sur un poste client** ne voit pas la nature des faux papiers ni la vraie identité dans « Régler la biométrie » (champs laissés sur « Inchangé »).
- **Poser un checkpoint IA** n'est un module Eden que pour l'éditeur ; au Zeus c'est l'entrée ZEN du même nom.
- Le **dossier actif** n'a plus d'entrée ZEN : module **Fixer la référence du dossier** ou éditeur SSE (type Dossier).
- Les anciens noms (« Générer un profil SSE », « Campagne SSE : sauvegarder », « Tutoriel : manuel du Zeus ») désignent les modules actuels (« Générer un profil », « Sauvegarder la campagne », « Ouvrir le manuel »).
- **Doter d'un terminal SEEK** : 25 m par défaut dans Eden, 30 m au Zeus.
- Les modules ZEN ont besoin de **Zeus Enhanced** ; sans ZEN, seuls les modules config (dont Éditeur SSE) sont au Zeus.

## Athena

Connexion Athena (barre de menus Eden) relie le poste ; Campagnes (envoyer / récupérer), Référence du dossier, profils IA et kits (Caisse de kits (Athena)) passent par COMSPEC Link. Le Tableau de bord SSE d'Eden est en lecture seule et n'envoie rien.
