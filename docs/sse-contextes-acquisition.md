# Contextes d'acquisition du portail SSE

Un contexte d'acquisition dit COMMENT une information, une photo, un papier, une empreinte ou un support a été obtenu.
C'est la liste du champ « Circonstances » d'une fiche de personne (colonne `sse_persons.circumstances`), au portail
(modale « Ajouter au dossier ») comme en jeu (dialogue de fiche SSE de COMSPEC Link, terminal SEEK).

Le catalogue par défaut décrit des forces spéciales américaines en opération à l'étranger (vocabulaire USSOCOM, JP 3-31,
ATP 3-18.x). Une communauté qui joue des policiers choisit le jeu « Police et gendarmerie (ancien) ».

- Catalogue : `config/sse_acquisition_contexts.php`
- Classe : `App\Support\SseAcquisitionContexts`
- Réglages par communauté : `sse_file_settings.settings_json` (clés `acquisition_set`, `acquisition_overrides`,
  `acquisition_custom`), aucune migration
- Écran : back-office › Renseignement › Fichiers SSE › « Contextes d'acquisition » (droit existant « gérer les fichiers SSE »)

## Catalogue (jeu « Forces spéciales US »)

| Groupe | Code stocké | Libellé français | Libellé anglais |
|---|---|---|---|
| Action sur objectif | `raid_action_directe` | Action directe : assaut sur l’objectif | Direct action: assault on the objective |
| Action sur objectif | `exploitation_site_sensible` | Exploitation de site sensible (SSE) | Sensitive site exploitation (SSE) |
| Action sur objectif | `interdiction_embuscade` | Interdiction ou embuscade | Interdiction or ambush |
| Contrôle et fouille | `fouille_haute_valeur` | Fouille d’une cible à haute valeur (HVT / HVI) à la capture | Search of a high-value target (HVT / HVI) at capture |
| Contrôle et fouille | `cordon_fouille` | Cordon et fouille (cordon and search) | Cordon and search |
| Contrôle et fouille | `point_controle_tactique` | Point de contrôle tactique (TCP / VCP) | Tactical checkpoint (TCP / VCP) |
| Contrôle et fouille | `fouille_patrouille` | Fouille opportuniste en patrouille (hasty search) | Hasty search on patrol |
| Renseignement humain | `interrogatoire_tactique` | Interrogatoire tactique sur le terrain (TQ) | Tactical questioning in the field (TQ) |
| Renseignement humain | `engagement_chef_local` | Rencontre avec un chef local (KLE) | Key leader engagement (KLE) |
| Renseignement humain | `source_humaine` | Collecte auprès d’une source humaine (HUMINT / CI) | Collection from a human source (HUMINT / CI) |
| Biométrie et détention | `enrolement_biometrique` | Enrôlement biométrique (BATS / HIIDE / SEEK) | Biometric enrolment (BATS / HIIDE / SEEK) |
| Biométrie et détention | `capture_champ_bataille` | Reddition ou capture sur le champ de bataille (EPW / détenu) | Surrender or capture on the battlefield (EPW / detainee) |
| Biométrie et détention | `remise_force_partenaire` | Remise à une force partenaire (handover) | Handover to a partner force |
| Exploitation de matériel | `materiel_ennemi_capture` | Matériel ennemi capturé (CEM) | Captured enemy materiel (CEM) |
| Exploitation de matériel | `document_media_capture` | Document ou média capturé (DOMEX / CELLEX) | Captured document or media (DOMEX / CELLEX) |
| Reconnaissance et récupération | `reconnaissance_speciale` | Reconnaissance spéciale (SR) et observation | Special reconnaissance (SR) and observation |
| Reconnaissance et récupération | `recuperation_personnel` | Récupération de personnel (sauvetage d’otage, évacuation sanitaire) | Personnel recovery (hostage rescue, medical evacuation) |
| Autre | `autre` | Autre (précisez) | Other (specify) |

Chaque contexte a aussi une description courte (FR et EN) dans le fichier de configuration ; elle s'affiche en infobulle
dans la liste du portail.

## Anciens codes

Les fiches déjà enregistrées (et le mod encore en service) utilisent les quatre codes d'origine. Ils ne sont ni supprimés
ni réécrits : la base garde exactement ce qui a été envoyé, seul l'affichage les rattache au contexte actuel.

| Ancien code | Équivalent actuel | Affichage dans le jeu « Forces spéciales US » |
|---|---|---|
| `controle` | `point_controle_tactique` | Point de contrôle tactique (TCP / VCP) · ancien : Contrôle |
| `perquisition` | `exploitation_site_sensible` | Exploitation de site sensible (SSE) · ancien : Perquisition |
| `reddition` | `capture_champ_bataille` | Reddition ou capture sur le champ de bataille (EPW / détenu) · ancien : Reddition |
| `autre` | `autre` | Autre (précisez) |

Un texte libre déjà présent dans la colonne (le portail l'acceptait) s'affiche tel quel. Un code inconnu n'est jamais
refusé : l'API `POST /api/sse/persons` garde la valeur reçue (balises et caractères de contrôle retirés). Les nouveaux
codes sont donc acceptés sans que le mod ait besoin d'être à jour, et le mod en production continue d'envoyer
`controle`, `perquisition`, `reddition` ou `autre`.

## Réglage « Jeu de contextes »

| Jeu | Contenu |
|---|---|
| Forces spéciales US (défaut) | Le catalogue ci-dessus |
| Police et gendarmerie (ancien) | Les quatre choix d'origine : Contrôle, Perquisition, Reddition, Autre |

Dans le jeu « police », les anciens codes gardent leur libellé d'origine sans mention « ancien ». Un contexte récent envoyé
par le mod à jour reste lisible (libellé du catalogue).

## Personnalisation par communauté

Depuis l'onglet « Contextes d'acquisition » : activer ou désactiver un contexte, le renommer (libellés français et
anglais), ajouter jusqu'à 20 contextes propres à la communauté (groupe au choix). Le code d'un contexte ajouté est fabriqué
une fois à partir du libellé (`c_` + libellé sans accents) et ne change plus. Un contexte désactivé disparaît de la liste
proposée mais reste lisible sur les fiches qui l'utilisent.

## Dans le mod

La liste déroulante « Circonstances » du dialogue de fiche SSE (`comspec_overwatch_connect`, fichier
`fn_ssePersonDialogOnLoad.sqf`) propose le même catalogue (libellés raccourcis) et envoie les nouveaux codes. Le terminal
SEEK présélectionne « Enrôlement biométrique ». Le mod SSE (`@COMSPEC_SSE`) envoie `exploitation_site_sensible` au lieu de
`perquisition` quand aucune valeur n'est fournie. Cela demande une reconstruction et une publication du mod.

## Vocabulaire alentour

Les libellés affichés du portail SSE suivent le vocabulaire militaire ; les clés techniques, colonnes, routes et droits ne
changent pas.

| Avant | Maintenant |
|---|---|
| Procès-verbal de saisie | Rapport de collecte (SSE report) |
| Scellé, registre des scellés, pièces sous scellé | Sous chaîne de possession, registre de chaîne de possession, pièces sous chaîne de possession |
| Suspect (catégorie des profils d'IA) | Personne d'intérêt (PoI) |
| Recherchés interpellés | Recherchés appréhendés |
| Contrôle d'identité (écrans du Bureau SSE) | Vérification d'identité sur le terrain |
| Sous garde (statut Détenu) | Détention tactique |
