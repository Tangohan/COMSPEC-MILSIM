# Téléphone ATAK : scanner, apps SSE et fouille

Version anglaise : [scanner_atak_fouille.en.md](scanner_atak_fouille.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Le téléphone ATAK (mod COMSPEC ATAK natif) est l'outil de terrain du SSE : il **scanne** les documents d'identité par niveaux, reçoit les **notifications** (demandes de papiers, résultats, alertes), tient le **journal**, les **relevés de contrôle** et les **scellés**, et sert de terminal quand on n'a pas de SEEK. Tous les retours du SSE y passent (ou dans la ligne d'état des fenêtres, ou au RPT) : plus aucune alerte de l'interface Arma.

## Où le trouver

| App (section Renseignement) | Rôle |
|---|---|
| **SSE terrain** (Ctrl+Maj+S si le téléphone est porté) | Onglets **CIBLE** (objet ou personne visés à 6 m), **SITE** (50 m : avancement, feu vert d'exfiltration, Triage du site, Tableau des liens), **SCELLÉS** (Pièces que je détiens, Registre des scellés), **JOURNAL** (Journal SSE, **Guide SSE**, Journal technique, Campagne) |
| **Contrôle ID** | Onglets **CONTRÔLE** (N1 MRZ, N2 puce, N3 base, N4 visage, verdict, Transmettre, FRM, SALUTE, SPOTREP, Relever le document, Partager, NFC, Sur mon téléphone), **HISTORIQUE** (50 derniers contrôles), **BOLO** |
| **Ma couverture** | Fiche d'infiltré (voir [ma_couverture.md](ma_couverture.md)) |
| **Photo** | Photo de preuve (entités cadrées à 15 m) |
| **Extraction** (DATALINK) | Câble DL-7 : connexion, mot de passe, forçage, transfert |
| Autres apps COMSPEC | **FRS / FRM**, **SALUTE**, **Comptes rendus**, **Recherchés**, **Tinder** (chasse HVT : FIND / FIX / JACKPOT) |

## Scanner un document

Deux entrées : le bouton **SCANNER (ATAK)** de la visionneuse (ferme la visionneuse, ouvre Contrôle ID sur ce document) ou l'app **Contrôle ID** après une présentation. Puis, dans l'onglet CONTRÔLE : **Lire la MRZ (N1)** (3 s), **Lire la puce (N2)** (4 s, à 1,5 m), **Interroger la base (N3)** (3 s), **Comparer le visage (N4)** (8 s, à 3 m). Chaque niveau ajoute sa ligne et le bandeau de verdict se met à jour. Détail des niveaux : [controle_identite.md](controle_identite.md).

Ensuite : **Relever le document** (relevé dans Contrôles ; Athena « Contrôles subis »), **Transmettre à Athena**, **Joindre à une FRM**, **SALUTE** / **SPOTREP** (compte rendu pré-rempli à relire avant envoi), **Partager** (Bluetooth à un équipier appairé), **NFC** (téléphone le plus proche à 2 m), **Sur mon téléphone** (QR code vers votre vrai téléphone). **Confirmer le PID** apparaît pour un verdict HVT ou recherché.

## Notifications reçues

- « Papiers, s'il vous plaît » (un joueur ou un garde IA vous demande vos papiers : répondez par **Mes papiers**).
- Vous êtes fouillé ; un document vous est rendu ; une couverture brûlée (vibreur seul).
- « Contrôle ID : VERDICT — nom » ; personne signalée : vibration (sans son en mode discrétion).
- Phrases courtes des IA contrôlées à moins de 15 m ; questions de légende d'un checkpoint.
- Éditeur SSE : contenus « Au téléphone » et « Annonce » ; « SSE · Analyse » pour les notes d'analyse.
- Zeus et administrateurs : « Trouvé par un joueur » (éditeur), alertes des checkpoints.

## La fouille

| Action | Où | Durée | Ce qu'elle donne |
|---|---|---|---|
| **Fouiller** (SSE) | Menu ACE sur une personne › **SSE** | 5 s (fouille rapide, « Fouille SSE rapide… ») | Objets et données SSE de la personne (révélation partielle, qualité selon la compétence), palier d'exploitation +1, journal, fenêtre DOSSIER SSE |
| **Fouiller** (SSE) | Menu ACE sur un objet ou véhicule marqué SSE | 15 s (fouille complète, « Fouille SSE complète… ») | Idem ; véhicule : cabine et soute, « Exploitation véhicule : x % » |
| **Fouiller les papiers** | Menu ACE sur une personne › **Contrôle d'identité** | 6 s (4 s sur un corps) | Ses documents d'identité, sous **Documents trouvés** (180 s) : Examiner / Saisir ; sans accord, seulement morte, menottée, rendue ou inconsciente |
| Fouille par un **checkpoint IA** | Issue « fouiller » | 8 s | Armes, papiers à plusieurs noms : soupçons |
| **Relever les empreintes (kit)**, **Prélèvement ADN**, **Test de résidus de tir** | Menu ACE sur une personne › SSE (kit porté) | 8 s / 6 s / 6 s | Relevés hors SEEK |

Un joueur fouillé est **prévenu sur son ATAK**. Une barre de progression ; une seule action SSE à la fois.

## Pas à pas : un relevé de contrôle complet

1. Un civil vous tend sa carte : elle s'ouvre (réglage « Ouvrir les papiers tendus à l'écran »). **SCANNER (ATAK)**.
2. Contrôle ID › **Lire la MRZ (N1)** › **Lire la puce (N2)** › **Interroger la base (N3)** : RAS : DOCUMENT CONFORME.
3. **Relever le document** : « Relevé : ALF814703 noté dans votre téléphone (Contrôles) ». Le bouton passe à « Document relevé ».
4. **Transmettre à Athena** (devient « Transmis ») ; **HISTORIQUE** garde la ligne.
5. Plus tard, Athena › fiche du membre contrôlé › **Contrôles subis** montre votre relevé.

## Pièges fréquents

- **Sans téléphone**, pas de scanner (N1 à N3), pas de relevé, pas de notifications : seul le contrôle visuel reste.
- **Langue** : les textes des fenêtres suivent le réglage « Langue du téléphone » de l'ATAK natif ; sans ATAK natif, tout reste en français.
- Les contrôles et relevés partent **en file d'attente** hors liaison et au retour de la liaison.
- **Partager / NFC / Sur mon téléphone** demandent l'ATAK 3.0.2 ou plus.
- La fouille SSE d'une personne est **toujours rapide** (5 s) ; la fouille complète est réservée aux objets et véhicules.
- Un objet sans menu SSE n'est **pas marqué SSE** : module Rendre exploitable ou attribut Eden.
- Une fouille d'identité **sans accord** exige une personne morte, menottée, rendue ou inconsciente.

## Athena

- Contrôles (CTRL-…), relevés (Contrôles subis), FRM avec photo du document, SALUTE / SPOTREP, fiches de preuve : tout passe par le téléphone et COMSPEC Link.
- Documents et avis d'Athena (volés, perdus, révoqués, BOLO, recherchés de l'opération) reviennent dans le scanner (N3) et dans l'app Recherchés.
