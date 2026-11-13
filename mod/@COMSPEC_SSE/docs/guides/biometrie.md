# Biométrie et terminal SEEK II

Version anglaise : [biometrie.en.md](biometrie.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Chaque personnage a ses empreintes (dix doigts, minuties, classification de Henry), ses iris, son visage et son ADN ; deux personnages ne partagent jamais une biométrie. Le **terminal SEEK II** relève ces traits sur une personne (vivante, menottée, inconsciente ou morte), les compare à la base (locale hors liaison, ABIS complet en liaison) et rend un verdict : MATCH, correspondance possible, inconnu. Une identification certaine **confirme l'appartenance** au tableau des liens et démasque les faux papiers.

## Où le trouver

| Besoin | Chemin |
|---|---|
| Ouvrir le terminal | **Ctrl+Maj+S** (raccourci CBA « Ouvrir l'outil SSE (ATAK / SEEK II) ») : téléphone ATAK › app SSE terrain si porté, sinon BII-10, sinon SEEK sur la personne visée à 6 m (sinon sur soi) |
| Depuis l'ATAK | App **SSE terrain** › onglet **CIBLE** (une personne visée) › **SEEK II** |
| Relevés à la main (kits) | Menu ACE sur la personne › **SSE** › **Relever les empreintes (kit)** (8 s, 10 usages ; masqué si vous portez un SEEK), **Prélèvement ADN (écouvillon buccal)** (6 s), **Test de résidus de tir** (6 s), **Enrôlement biométrique (SEEK II)** |
| Latentes | Menu ACE sur une arme au sol › **Relever des empreintes latentes** (kit empreintes) |
| Sac de tête | Menu ACE sur un prisonnier › **Mettre un sac sur la tête** (4 s) / **Retirer le sac de tête** (2 s) |
| Matériel | Objets « SEEK II », « Kit empreintes », « Kit ADN », « Écouvillons ADN / résidus de tir » ; caisse **Caisse kit biométrie** (Eden / Zeus **Poser une caisse de kit**) ; module **Doter d'un terminal SEEK** |

Sans terminal : « Terminal SEEK II (ou tablette cTab / Android ATAK compatible) requis. » ; aucun outil : « Aucun terminal SSE n'a pu s'ouvrir. Emportez un téléphone ATAK, un SEEK ou un BII-10. »

## La fenêtre SEEK II

Titre « SEEK II — BIOMETRIC TERMINAL », sous-titre « Photo · empreintes · iris · identité · score · qualité ».

| Bouton | Effet |
|---|---|
| **EMPREINTES**, **IRIS**, **VISAGE**, **ADN** | Relevé du trait (barre de progression et bips ; l'opérateur s'agenouille sur un corps, le sujet coopérant tend la main ou regarde l'appareil) |
| **CAPTURE ALL** | Tout relever |
| **IDENTIFIER** | Recherche dans la base (5 s hors liaison, 12 s en liaison ; ADN : résultat au labo après 15 min) |
| **TRANSMETTRE** | Envoie la fiche à Athena |
| **TERMINAL SSE** | Ouvre le terminal SSE terrain (écran « TERMINAL SSE — TERRAIN ») |
| **FERMER** | Ferme |

Panneau **ACQUISITION BIOMÉTRIQUE** : Record SSE, Identité supposée, Alias ; Prélèvements Empreintes / Iris / Visage / ADN (NONE au départ) ; Qualité moyenne du prélèvement (%) ; Résultat de recherche (« Aucune recherche effectuée »), Score de correspondance, Référence, Statut (READY). Il se rafraîchit 15 s après une capture.

## Lire le résultat

| Ligne | Sens |
|---|---|
| **MATCH · TIER n · score** | Identité trouvée (au moins deux doigts ou un iris concordants, score au-dessus du seuil 60). Tier = niveau de la watchlist : 1 DETAIN, 2 QUESTION / DETAIN, 3 QUESTION / DENY ACCESS, 4 pour info |
| **POSSIBLE MATCH · score** | Score intermédiaire (seuil 25) : à confirmer par un autre relevé |
| **QUALITÉ INSUFFISANTE · RECAPTURER** | Relevé trop dégradé |
| **INCONNU DES BASES · EMPREINTES SUR OBJETS SAISIS** | Pas au fichier, mais ses empreintes sont sur une pièce saisie |
| **NO MATCH** | Inconnu de la base consultée |
| **ALERTE : identité déclarée « … » différente du dossier (faux papiers ?)** | Les papiers ne correspondent pas au dossier |

Puis : Dossier (réf., nom, alias), Motif, Conduite, Source, « Déjà rencontré n fois », « Avis de recherche Athena », concordance doigts / iris / visage, minuties (seuil 12), distance de Hamming de l'iris (seuil 0,32), similarité du visage, classification de Henry, « Autre candidat » (fausse alerte possible), latentes, et la portée : « Base locale du terminal (hors liaison) » ou « ABIS complet (en liaison) ».

## Pas à pas : enrôler un prisonnier

1. Le sujet est menotté (ACE). Approchez à moins de 6 m, **Ctrl+Maj+S** (ou SSE terrain › CIBLE › SEEK II).
2. **EMPREINTES** puis **IRIS** puis **VISAGE** : chaque trait passe de NONE à sa note de qualité. Sa main blessée ? Soignez-la d'abord (ACE), sinon le relevé est refusé.
3. **IDENTIFIER** : « MATCH · TIER 2 · 78 », Dossier HVT-ABOU-SAMIR, Motif, Conduite : QUESTION / DETAIN. Son appartenance passe en confirmé au tableau des liens.
4. **ADN** (kit ADN ou écouvillons) : le résultat arrive 15 min plus tard, avec recherche familiale.
5. **TRANSMETTRE** vers Athena. Ensuite seulement, **Mettre un sac sur la tête** (le sac empêche visage et iris).

## Régler la biométrie d'un personnage

- Eden, attributs de l'unité › **COMSPEC SSE — Biométrie** : Fichier biométrique (Automatique / Inconnu de toute base / Enrôlé, non signalé / Watchlist tier 1 à 4), Motif de signalement, Faux papiers (Automatique / Oui / Non), Vraie identité (si faux papiers), Identifiant biométrique (campagne) (ex. `HVT-ABOU-SAMIR` : même personnage d'une mission à l'autre), Parent de + Lien de parenté (Frère / sœur, Parent, Enfant : recherche familiale ADN), Doigts manquants (1-10), Iris (Cataracte, Prothèse, Lésion). Sur les objets : **Empreintes latentes de**.
- Zeus : ZEN › **COMSPEC · Renseignement (SSE) › Régler la biométrie** (mêmes champs) et **Définir l'identité** (« Ce que la base doit répondre », Alias connu, Nom, Prénom, Nationalité déclarée, Langue parlée, Référence de dossier antérieur).

## Pièges fréquents

- **La qualité compte** : nuit, pluie, sujet agité, corps ancien, mains ou visage blessés (ACE) dégradent le relevé ; au-delà du seuil (réglage « Blessure qui empêche le relevé », 1,2), impossible. Un doigt amputé ou un œil artificiel manquent.
- **Hors liaison**, la base locale ne connaît que les recherchés jusqu'au niveau du réglage « Base hors ligne du terminal » (2) et les rencontres de la mission.
- **Fausses alertes** (4 %, réglage) : un candidat ressemblant avec peu de minuties ; confirmez par un autre trait.
- **Sac de tête posé** : plus de visage ni d'iris. Relevez d'abord.
- **Gants d'examen** : sans eux vous laissez vos propres empreintes et votre ADN sur les objets.
- Le SEEK **sans cible valide à 6 m s'ouvre sur vous-même**.
- Le bouton ACE « Relever les empreintes (kit) » disparaît quand vous **portez un SEEK** (le terminal fait mieux).
- Réglages (COMSPEC SSE › Biométrie) : Biométrie réaliste, Score pour une correspondance (60) et possible (25), Fausses alertes (4 %), Effet des conditions (1), Blessure qui empêche le relevé (1,2), Base hors ligne du terminal (2), Durée de recherche hors ligne (5 s) / en ligne (12 s), Délai du résultat ADN (900 s), Empreintes laissées sur les objets, Avis de recherche Athena, Photo du visage en studio, Modèle de la cagoule de prisonnier, Garder les rencontres d'une mission à l'autre ; **Biométrie : population** (Déjà fichés et Recherchés par camp, Civils / Combattants sous faux papiers).

## Athena

- **TRANSMETTRE** envoie la fiche personne puis la biométrie vers l'affaire du Bureau SSE (file d'attente hors liaison).
- **Avis de recherche Athena** (réglage) : les recherchés de la communauté et de l'opération sont importés dans la base du terminal.
- En liaison, IDENTIFIER interroge l'ABIS complet (12 s) ; la campagne copiée dans Athena garde les rencontres biométriques.
