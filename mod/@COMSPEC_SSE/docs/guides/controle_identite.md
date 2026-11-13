# Contrôle d'identité

Version anglaise : [controle_identite.en.md](controle_identite.en.md). Sommaire : [index.md](index.md). Référence détaillée (IA réalistes, checkpoints, HVT, API) : [../guide/Controle_identite.md](../guide/Controle_identite.md).

## À quoi ça sert

Contrôler les papiers d'une personne (IA ou joueur) : les demander, les examiner à l'œil, les scanner avec le téléphone ATAK par niveaux (MRZ, puce, base, visage), les relever dans son carnet de contrôles, les saisir ou les rendre. Un contrôle détecte un faux, un document volé ou révoqué, une personne signalée, recherchée ou HVT, et alimente les fiches (FRM, SALUTE, SPOTREP) et Athena.

## Où le trouver

| Besoin | Chemin |
|---|---|
| Contrôler une personne | Menu ACE sur la personne › **Contrôle d'identité** |
| Contrôler les occupants d'un véhicule | Menu ACE du véhicule arrêté › **Contrôle d'identité › Contrôler les occupants** ou **Faire descendre** |
| Mes propres papiers | Menu ACE personnel › **Mes papiers** |
| Scanner, relevés, BOLO | Téléphone ATAK › Renseignement › app **Contrôle ID** (onglets **CONTRÔLE**, **HISTORIQUE**, **BOLO**) |
| Réglages | Échap › Options › Réglages des addons › **COMSPEC SSE › Identité** |

## Le menu ACE « Contrôle d'identité »

| Action | Durée | Condition | Effet |
|---|---|---|---|
| **Demander les papiers** | 2 s (« Papiers, s'il vous plaît… ») | Personne vivante, à pied, à 3 m | Une IA répond (tend ses papiers, ment, refuse, fuit…). Un joueur reçoit la demande sur son ATAK et répond par **Mes papiers** |
| **Contrôler l'identité › <document>** | | Tant que la présentation est valable (120 s, réglage) | **Examiner (contrôle visuel)** (2 s) ouvre la visionneuse ; **Saisir** (2 s) met le document dans votre inventaire avec chaîne de possession |
| **Fouiller les papiers** | 6 s (4 s sur un corps) | Personne morte, menottée, rendue ou inconsciente | Les documents trouvés sont listés sous **Documents trouvés** (180 s) : Examiner / Saisir ; un joueur fouillé est prévenu sur son ATAK |
| **Rendre un document › Rendre : <nom>** | 1 s | Vous détenez un de ses documents | Le lui restitue |
| **Poser une question** | 1 s | IA consciente, à 3 m | « D'où venez-vous ? », « Pour qui travaillez-vous ? », « Raison du déplacement ? », « Quel est votre métier ? », « Où êtes-vous né ? », « Qui peut confirmer ce que vous dites ? » : la réponse s'affiche dans l'app Contrôle ID (comparez avec les papiers) |

Avec le réglage **Ouvrir les papiers tendus à l'écran** (oui par défaut), un document présenté s'ouvre tout de suite dans la visionneuse.

## Le menu « Mes papiers » (côté contrôlé)

- **Présenter mes papiers › <document> › Présenter** (le contrôleur peut l'examiner 120 s) ou **Remettre** (l'objet passe dans son inventaire).
- **Refuser** (si une demande est en attente).
- **Répondre** (questions de légende d'un checkpoint IA : « question → réponse »).
- **Ouvrir mes papiers** : la visionneuse sur vos propres documents.

## La visionneuse de document

Fenêtre ouverte par Examiner, Ouvrir mes papiers, ou la page IA de la zone (OUVRIR).

| Bouton | Effet |
|---|---|
| **RECTO / VERSO** | Bascule la face (sans effet sur un passeport, une seule page) |
| **ZOOM** | Agrandit le document (de 46 % à 70 % de la hauteur de l'écran) |
| **SCANNER (ATAK)** | Ferme la visionneuse, ouvre l'ATAK sur l'app Contrôle ID avec ce document. Sans téléphone : « Pas de téléphone ATAK sur vous : impossible de scanner le document. » |
| **RELEVER** | Note le document (type, pays, numéro, nom, prénom, naissance, expiration, porteur, lieu et heure, examen visuel) dans votre **relevé de contrôle** : téléphone › Contrôles / Fichiers, puis Athena « Contrôles subis ». Ligne d'état : « Relevé : ALF814703 noté dans votre téléphone (Contrôles) ». Si impossible, le bouton le dit (« Pas de téléphone », « Déjà relevé à 20:37 ») |
| **FERMER** | Ferme |

Les retours s'affichent dans la **ligne d'état** de la fenêtre, jamais en alerte Arma. L'examen visuel (N0) se fait ici : « Hologramme de sécurité absent. », « La photo semble rapportée : bord décollé, plastification reprise. », « Caractères du nom irréguliers : police différente du reste du document. », « Document expiré depuis le … », « Carte militaire, mais la personne est en civil. », ou « Examen visuel : rien d'anormal. ». La nuit : « Trop sombre pour bien examiner le document : éclairez-le. »

## Les niveaux de contrôle (app Contrôle ID › onglet CONTRÔLE)

| Niveau | Bouton | Durée | Il faut | Ce qu'il dit |
|---|---|---|---|---|
| **N0 · visuel** | (visionneuse) | 2 s | De la lumière, un œil formé (`COMSPEC_ID_Expert` : meilleur examen) | Défauts physiques du faux, selon sa qualité et la difficulté |
| **N1 · MRZ** | **Lire la MRZ (N1)** | 3 s | Téléphone ATAK | Chiffres de contrôle ICAO, concordance avec le recto : « MRZ conforme ICAO, chiffres de contrôle justes, identique au recto. » ou « MRZ : chiffre de contrôle du numéro faux. », « MRZ différente du recto : le nom ne correspond pas. », « Pas de bande MRZ sur ce document. » |
| **N2 · puce** | **Lire la puce (N2)** | 4 s, à 1,5 m | Téléphone (NFC) | « Puce lue : signature valide, données identiques au document. », « Puce lue : la photo enregistrée n'est pas celle imprimée. », « Aucune puce détectée sur un document qui doit en avoir une. », « Ce document n'a pas de puce. » (permis) |
| **N3 · base** | **Interroger la base (N3)** | 3 s | Téléphone ; Athena en ligne complète la base locale | « Base du pays : document délivré, valide, aucun signalement. », « Numéro inconnu de la base du pays : jamais délivré. », « Document signalé VOLÉ. », « Document RÉVOQUÉ par l'autorité. », « BOLO : document à surveiller (réf. …) », « AVIS : personne RECHERCHÉE / HVT (réf. …) », « Athena injoignable : base locale seule. » |
| **N4 · visage** | **Comparer le visage (N4)** | 8 s, à 3 m | Téléphone ou SEEK ; réglage « Comparaison du visage (N4) » | Visage comparé à la base biométrique et à la photo du document, avec un taux de confiance ; une HVT est reconnue même sous un faux nom |

Bandeau de verdict : **RAS : DOCUMENT CONFORME** · **DOUTE : À APPROFONDIR** · **DOCUMENT EXPIRÉ** · **NUMÉRO INCONNU : FAUX PROBABLE** · **FAUX DOCUMENT** · **DOCUMENT VOLÉ** · **DOCUMENT RÉVOQUÉ** · **PERSONNE SOUS SURVEILLANCE** · **PERSONNE RECHERCHÉE** · **HVT : CIBLE PRIORITAIRE**. Une personne signalée fait vibrer le téléphone.

Autres boutons de l'app : **Transmettre à Athena** (devient « Transmis »), **Joindre à une FRM**, **Confirmer le PID** (verdict HVT ou recherché), **Relever le document** (devient « Document relevé »), **SALUTE**, **SPOTREP**, **Partager**, **NFC**, **Sur mon téléphone**, **Voir**. Onglet **HISTORIQUE** : vos 50 derniers contrôles. Onglet **BOLO** : les avis en cours.

## Pas à pas : un contrôle au barrage

Situation : votre binôme tient un barrage ; une voiture s'arrête, deux civils à bord.

1. Menu ACE du véhicule › **Contrôle d'identité › Faire descendre**, puis sur le conducteur › **Demander les papiers**. Il tend une carte d'identité : elle s'ouvre dans la visionneuse.
2. **RECTO / VERSO**, **ZOOM** : la police du nom paraît différente. Ligne d'état : « Caractères du nom irréguliers… ».
3. **SCANNER (ATAK)** › **Lire la MRZ (N1)** : « MRZ différente du recto : le nom ne correspond pas. » Verdict **FAUX DOCUMENT**.
4. **Interroger la base (N3)** : « AVIS : personne RECHERCHÉE (réf. …) ». Le téléphone vibre.
5. **Relever le document** (ou RELEVER dans la visionneuse) : le relevé va dans votre carnet et sur Athena. **Joindre à une FRM** puis **SPOTREP** pour le rendre compte.
6. Menottez (ACE) : menu ACE › **Contrôle d'identité › Saisir** le document (chaîne de possession) ; **Fouiller les papiers** pour le reste.

## Les checkpoints IA

Module Eden **Poser un checkpoint IA** (catégorie COMSPEC · Identité) ou Zeus ZEN **COMSPEC · Identité › Poser un checkpoint IA** : **Rayon (m)** (0 = réglage « Distance des checkpoints », 25 m), **Camp du poste** (Automatique / BLUFOR / OPFOR / Indépendant / Civil (police)), **Équipement du poste** (Visuel seul N0 / Scanner MRZ N1 / Lecteur NFC N2 / Liaison base N3), **Sévérité** (Laxiste / Normale / Stricte), **Nom du poste**. Gardes : unités synchronisées, sinon IA du camp dans la zone.

Un joueur qui entre : « Papiers, s'il vous plaît » sur l'ATAK, 45 s (réglage) pour **Mes papiers › Présenter** à 3 m du garde ; examen selon l'équipement ; 2 questions de légende (3 au poste strict) ; issue : laisser passer · fouiller (8 s) · retenir (3 min, Zeus prévenu) · alerter · ouvrir le feu. **Retirer un checkpoint IA**, **Déclencher un contrôle** (sur une personne, par le poste qui la couvre ou l'IA armée la plus proche), **Forcer un état IA** (Interpellé, Coopère, Ment, Nerveux, Refuse, Fuit, Agressif, Se rend, Appelle des renforts, Calme) et **Imposer un couvre-feu** complètent la panoplie Zeus.

## Réglages (COMSPEC SSE › Identité)

Contrôle d'identité (oui) · Difficulté des contrôles (Arcade / **Standard** / Réaliste) · Configurer une IA à sa pose (Zeus) (non) · IA sans papiers (0 %) · Photo d'identité en direct (oui) · Pays des papiers (Auto (selon la carte), pays réels puis fictifs) · Pays par défaut des papiers (Altis) (Selon la carte) · Papiers des IA (oui) · Rendre les papiers du kit au respawn (oui) · Civils qui présentent leurs papiers (90 %) · Insurgés porteurs de faux papiers (60 %) · Durée d'une présentation (120 s) · Transmission automatique à Athena (oui ; le réglage de la communauté l'emporte) · Documents et avis d'Athena (oui) · Opération Athena (clé) · IA réalistes au contrôle (oui) · Agressivité des IA (50 %) · Distance des checkpoints (25 m) · Ouvrir les papiers tendus à l'écran (oui) · Délai pour présenter ses papiers (45 s) · Les checkpoints contrôlent aussi les IA (oui) · Couvre-feu (non) · Infiltré captif tant que sa couverture tient (oui) · Soupçons avant que la couverture saute (100) · Distance des témoins (50 m) · Questions de légende (oui) · Délai pour répondre (20 s) · Comparaison du visage (N4) (oui) · Durée de la comparaison du visage (8 s) · Notifier les BOLO (oui).

## Pièges fréquents

- **Sans téléphone ATAK**, seul le N0 est possible : SCANNER répond « Pas de téléphone ATAK sur vous ».
- **La nuit**, éclairez le document (lampe d'arme) : sinon « Trop sombre pour bien examiner ».
- Le tirage de l'examen visuel est **reproductible** : refaire le même contrôle donne le même résultat ; changez de niveau.
- La **présentation expire** (120 s) : le sous-menu « Contrôler l'identité » disparaît ; redemandez.
- **Un bon faux passe le N0 et parfois le N1** (qualité élevée) : allez jusqu'à la base ou le visage.
- Une **couverture préparée** passe N0 à N3 ; seule la biométrie (N4, SEEK) la trahit.
- **RELEVER n'est pas une photo** : le relevé est un texte (la capture d'écran d'Arma ne prend pas l'interface) ; les défauts physiques ne sont pas relevés, seul votre examen visuel l'est.
- **Déjà relevé** : le bouton le dit avec l'heure ; un document ne se relève qu'une fois par contrôle.
- **Faux positif au N4** : cagoule, foulard, nuit et distance dégradent le cliché ; confirmez au SEEK (empreintes, iris).
- Un joueur fouillé **est prévenu** sur son ATAK ; un joueur contrôlé répond lui-même (Présenter, Remettre, Refuser).

## Athena

- Chaque contrôle transmis (**Transmettre à Athena**, ou automatiquement) crée une entrée Bureau SSE › **Contrôles** (référence CTRL-…) : document, identité imprimée, résultats N0 à N4, verdict, carroyage, contrôleur ; sans liaison, file d'attente (« Pas de liaison Athena : contrôle mis en file d'attente, envoyé au retour de la liaison. »).
- **RELEVER** / **Relever le document** alimente votre carnet (téléphone › Contrôles) et Athena « **Contrôles subis** » sur la fiche du membre contrôlé.
- **Documents et avis d'Athena** (réglage) : documents volés, perdus, révoqués, BOLO et recherchés de l'opération reviennent en jeu au N3.
- La FRM envoyée et sa photo de document sont rattachées au contrôle sur Athena.
