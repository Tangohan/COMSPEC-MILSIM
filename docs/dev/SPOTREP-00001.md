# SPOTREP #00001

reported on August 24, 2026

TECHREP #00001

```
FROM:     État-major COMSPEC
TO:       Communautés Athena, opérateurs ATAK, Zeus
UNIT:     Branche principale
ACTIVITY: Vague 2026.08c — poste de situation, planification, Overwatch 1.4.63
SIZE:     Portail 1.5.30 · Overwatch 1.4.63 · extension 2.0.12 · Athena ATAK 1.0.45
```

# NOTES

- Période couverte : **17–24 août 2026** (PR #182 à #198).
- Le pack jeu à charger est `UptoDate/@COMSPECOverwatch`. Une relance complète d’Arma (pas seulement la mission) est nécessaire après mise à jour.
- La planification de mission (#197) est déjà sur la branche principale. Le poste de situation, le relief autour de l’équipe et le pack 1.4.63 sont dans la [PR #198](https://github.com/Tangohan/COMSPEC-MILSIM/pull/198).
- En cas de souci : signalement depuis Échap → gestion du mod, ou le canal habituel de la communauté.

# CHANGELOG

## PORTAIL ATHENA

- Added: Poste de situation — vue des dossiers SSE avec au moins une identité, et pose / arrêt d’une localisation de téléphone depuis le back-office
- Added: Planification de mission — organisation de combat, affectations, documents d’ordre, paquet exportable, lecture sur la carte ATAK
- Added: Fiches de renseignement simplifiées (plein écran, thèmes, pièces, suivi bureau)
- Added: Rapports de théâtre (prise de contact, opérateur à terre, BDA, FRAGO, SALUTE)
- Added: Déclenchement d’une charge posée depuis le poste de commandement (double confirmation, sans toucher à la minuterie)
- Added: Modèles de comptes rendus d’après-action (champs libres métier, pas une saisie technique)
- Tweaked: Parc de terminaux — retrait d’appareils et de sessions web, colonne d’actions toujours visible
- Tweaked: Relecture de mission — joueurs, IA alliées, téléphones et balises GPS dans la timeline et le PDF
- Tweaked: Barre d’outils et bloc-notes ATAK web plus aérés
- Fixed: Connexion téléphone / terminal Android et photos de reconnaissance
- Fixed: Export PDF des dossiers (lisibilité, marges, pièces)
- Fixed: Colonne de relief de théâtre sur certaines bases (lecture du calque ombrage)
- Fixed: Journal produit public (`/nouveautés`) et écran de connexion en deux volets

## ATAK WEB

- Added: Panneau des fiches de renseignement dans la vue carte
- Added: Alerte de proximité des téléphones sous localisation
- Added: Traces colorées, perte de liaison et prévision de déplacement plus lisibles
- Added: Journal d’erreurs remonté depuis le jeu (lisible, pas une page technique)
- Tweaked: Overlays de liaison (signal faible, perte, écran cassé) alignés sur le roleplay
- Fixed: Coupures temporaires du poste de commandement (rafales de refus serveur)
- Fixed: Équipes de feu bloquées (alerte tactique non reconnue)

## OVERWATCH (JEU)

- Added: Relevé du relief **autour de l’équipe** (sol de la carte Arma), plus toute la carte d’un coup
- Added: Localisation de téléphone reçue depuis le poste de situation
- Added: Ordres de déplacement pour IA alliée depuis ATAK
- Added: Panneau Zeus — éditer SSE / ATAK / Overwatch sur la sélection
- Added: Couper le suivi téléphone / GPS depuis ACE
- Tweaked: Fenêtre unique au menu principal (conditions + accès anticipé), plus de défilé en mission
- Tweaked: Photos ATAK : un JPEG, plus de second cliché qui gelait le jeu
- Tweaked: Moins de bips vanilla répétés
- Fixed: Doublon d’ouverture de note Athena
- Fixed: Suivi GPS, IA alliée et pastilles trop grandes sur la carte web
- Fixed: Overlay « sans couverture » qui recouvrait tout l’écran Zeus

## SSE

- Added: Atelier de modèles de mission Arma
- Added: DOMEX Zeus en direct et file à exploiter
- Tweaked: Fiches personnes, biométrie simulée et exploitation numérique plus stables à la transmission
- Fixed: Libellés et identifiants affichés en notation scientifique sur certaines fiches
- Fixed: Photos visage introuvables selon le dossier Screenshots du profil
