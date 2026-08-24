COMSPEC Overwatch 1.4.73 / Athena ATAK 1.5.47 — 24/08/2026

• Aéronefs occupés remontés vers Appui aérien sans déclaration de vol
• Charges : mode uniquement depuis ATAK, tout déclencher limité aux charges du joueur

COMSPEC Overwatch 1.4.64 / Athena ATAK 1.0.46 — 24/08/2026

Sons ATAK

• Nouveau pack d’alertes (web et jeu) : bip radio, réception / acceptation d’ordre, renseignement, signal médical (trois fois)

COMSPEC Overwatch 1.4.63 / extension 2.0.12 — 24/08/2026

SPOTREP #00001 · TECHREP #00001

• Relief du sol autour de l’équipe (plus toute la carte d’un coup)
• Localisation téléphone depuis le poste de situation
• Overlays de liaison, menus Zeus, moins de bips vanilla
• Altitude sol (`terrain_z`) en plus de l’altitude opérateur

COMSPEC Overwatch 1.4.50 / extension 2.0.10 — 23/08/2026

Correctifs — lancement et signalement

• Fenêtre Windows (conditions + bêta) au menu principal, plus de défilé en mission
• Signaler un problème depuis Échap → gestion du mod

COMSPEC Overwatch 1.4.49 / Athena ATAK 1.0.42 — 23/08/2026

Correctif — gel à la photo ATAK

• Les photos ATAK (JPEG BCE / IceMan) ne relancent plus un cliché PNG synchrone
• Envoi Athena en file ; PNG de secours seulement si le JPEG est introuvable

COMSPEC Overwatch 1.4.8 — 29/07/2026

Médical ACE / KAT, carte web, photos recon

• Détection automatique ACE : inconscient, arrêt cardiaque (états transmissibles en roleplay via l’ATAK)
• Effets roleplay sur photos (dégradation liaison / écran cassé côté web)
• Outils carte web : traits, zones, périmètres, formulaire icône en liste déroulante
• Photos recon : flou, commentaire, masquage, transfert SSE ; correctif flou en aperçu agrandi
• Détections médicales auto retirées quand l’opérateur est hors liaison
• Correctifs upload photos, marqueurs web, scroll page statut ATAK

COMSPEC Overwatch 1.4.5 — 29/07/2026

Correctif critique — zones brouillage / intensité / casse

• Les zones Zeus (brouilleur, interférence, dégradé, sans couverture) appliquent enfin leurs effets
• Intensité : perte de paquets, coupures, latence et effets visuels proportionnels
• Brouilleur fort : coupures intermittentes + gel terminal (« casse »)
• Correction stop effets PP + activation auto du mode roleplay à la pose de zone

COMSPEC Overwatch 1.4.4 — 29/07/2026

Correctif — manifeste de vol

• Le journal Athena affiche l’indicatif réel (ex. N-10) au lieu de « Unknown »
• À pied : plus de modèle fantassin (« Artilleur GMG ») — « Déclaration sol » + champs modifiables
• Envoi JSON robuste (hashmap) + normalisation côté extension

COMSPEC Overwatch 1.4.3 / Athena 1.0.16 — 29/07/2026

Correctif — layout ATAK « _fade » indéfini

• Plus d’erreur SQF à l’ouverture du menu ATAK (fn_ATAK_Check_Layout)
• Rechargement forcé de la fonction au démarrage (évite le cache Arma / BCE)
• Quitter complètement Arma (pas seulement la mission) après mise à jour

Correctif — modules zones roleplay (Zeus / Eden)

• Plus d’erreur SQF « Type Chaîne, Objet attendu » sur fn_moduleApplyRoleplayZone
• Cause : appel module avec un libellé d’événement (chaîne) en premier argument au lieu de l’objet logique
• Entrée souple : logic seul, [logic, unités, activé], ou [événement, logic, …]

COMSPEC Overwatch 1.4.2 — 29/07/2026

Correctif — État ATAK vide

• L’app État ATAK affiche de nouveau latence / liaison / certificats (plus d’écran noir)
• Cause : BCE expose la page « AtakStatus » alors que le refresh n’acceptait que « COMSPEC_ATAK_Status »

Correctif critique — messagerie TOC → jeu

• Les messages envoyés depuis ATAK web (tchat) arrivent de nouveau dans l’inbox Athena en jeu
• Cause : la limite 8 Ko de l’extension coupait les messages les plus récents
• Poll incrémental (after id) + anti-écho par empreinte (plus de blocage si même indicatif TOC/joueur)

Correctifs ATAK (1.4.1 — rebuild requis)

• Layout Athena : plus de fond bleu Desktop à gauche — carte + panneau se repositionnent correctement
• Layout Athena : correctif erreur SQF « _fade » indéfini à l’ouverture du menu
• Journal : tentatives / échecs de transmission, erreurs ACE Overwatch et POST HTTP async
• Photos ATAK : recherche élargie Screenshots (profil / jpg↔png), attente écriture, repli capture native
• Vibrations / notifs : ne se rejouent plus à chaque reconnexion une fois confirmées
• IDC boutons Appui aérien / Manifeste / Briefing (conflit avec les onglets)

Renseignement interpersonnel (SSE)

• Terminal « Renseignement interpersonnel » : enregistrez une personne contrôlée (identité, statut, circonstances)
• Photo du visage jointe à la fiche (capture récente ou Photothèque)
• Empreintes / iris en simulation (gameplay) — aucune biométrie réelle
• Armement et équipement détectés sur la cible (préremplissage inventaire)
• Remontée automatique vers le poste de commandement Athena (onglet Personnes)
• Menu ACE : ATAK Tactique → Enregistrer une personne
• Module activable par communauté (configuration ATAK / Overwatch)

Portail Athena (compagnon)

• Portail classifié /atak/sse : dossiers d’affaire, notes, preuves, croisements
• Codes d’accès temporaires délivrés par le commandement (membre ou invité)
• Export PDF classifié + sélection « Accès renseignement » dans le profil de session ATAK web

Rappel — déjà en place (1.3.x)

• Réalisme liaison, messagerie Groups ↔ TOC, photos auto, marqueurs Marker Widget, FRAGO lisibles
• Terminal & certificat, zones Zeus, reprise de session

Après mise à jour : relancez Arma complètement. Rebuild : connect.pbo + main.pbo + mavik_compat.pbo + atak_athena.pbo + COMSPECExtension_x64.dll. Hard-refresh sur le portail Athena. Déployer aussi le PHP Athena (API chat ?after=).
