# Nomenclatures de spécialités militaires par pays

Recherche du **17 novembre 2026** pour les catalogues RH/LMS d’ATHENA C2.
Ce document décrit des nomenclatures publiques. Les écrans de saisie structurée du
[dossier de schéma validé](../technical/specialites-militaires-proposition.md) deviennent
disponibles après la migration 4910. Pour les États-Unis : [AFSC, MOS et niveaux](../REFERENTIEL-METIERS-OPERATIONS-SPECIALES-US.md).

## Principes de classement

| Donnée | Exemple | Traitement |
|---|---|---|
| Spécialité nationale | AFSC `1Z2X1`, Marine `FUSIL` | Code rattaché à une nomenclature et à une armée. |
| Domaine / famille | Terre `INF`, AAE `341X` | Granularité indiquée ; ne prouve pas une spécialisation individuelle. |
| Niveau professionnel | AFSC `5` | Niveau propre à une spécialité et à son système. |
| Qualification / brevet | JTAC, BAT, BM2 | Certification séparée, avec ses conditions et sa validité. |
| Grade | OR-6, adjudant | Référentiel de grades existant. |
| Affectation | CPA 10, 160th SOAR, SAS | Unité et poste dans l’ORBAT. |

Un code numérique reste une chaîne : conserver les zéros initiaux, les lettres, les suffixes
et la casse officielle. Une clé interne ATHENA n’est jamais présentée comme un code officiel.
L’absence de code public vérifié se représente par une valeur absente, pas par un code inventé.
Les exemples ci-dessous ne constituent pas des équivalences professionnelles entre pays.

## France — Armée de Terre

La Terre utilise notamment le **répertoire des métiers**, les **domaines de spécialités**
et les filières associées. La [circulaire 4025 du 18 juin 2025 pour le recrutement
semi-direct 2026](https://www.defense.gouv.fr/sites/default/files/bulletin-officiel/Circulaire%20N%C2%B0%204025%20ARM%20RH-AT%20SDEP%20BPRH%20du%2018%20juin%202025.pdf)
demande un trigramme conforme à ce répertoire. Elle cite notamment les domaines `MAI`,
`SIC`, `RGE`, `GRH`, `PBF`, `ADM` et `RHL`. Ce sont des domaines, pas des MOS individuels.

| Code | Domaine | État de la preuve publique |
|---|---|---|
| `INF` | Infanterie / combat de l’infanterie | Attesté dans les textes historiques ci-dessous ; détail actuel du répertoire à confirmer. |
| `AER` | Aéromobilité | Attesté historiquement ; ne désigne pas à lui seul un pilote du 4e RHFS. |
| `SIC` | Systèmes d’information et de communications | Trigramme également cité dans la circulaire pour 2026. |
| `RGE` | Renseignement | Trigramme également cité dans la circulaire pour 2026. |
| `MAI` | Maintenance | Trigramme également cité dans la circulaire pour 2026. |

Les développements des trigrammes sont documentés dans la [circulaire 4319 du
5 décembre 2018, sélection 2019](https://www.defense.gouv.fr/sites/default/files/sga/12%20CIRCULAIRE%20N%C2%B0%204319ARMRH-ATEPPRHSOFF.pdf).
La [circulaire 4120 du 21 novembre 2019](https://www.defense.gouv.fr/sites/default/files/sga/16%20CIRCULAIRE%20N%C2%B0%204120ARMRH-ATEPPRHSOFF.pdf)
mentionne aussi `INF`. Ce dernier texte est **abrogé**, comme l’indique
l’[index officiel du BOC de décembre 2019](https://www.defense.gouv.fr/bulletin-officiel/textes-officiels-du-ministere-armees/textes-officiels-du-ministere-armees-partir-juin-2019/boc-5).
Ces preuves historiques ne suffisent pas à déclarer une liste exhaustive en vigueur en 2026.
Aucun code individuel « opérateur 1er RPIMa » ou « pilote 4e RHFS » n’a été validé ici.

### Brevets et progression

La publication institutionnelle [Terre Information Magazine 333, octobre 2022, p. 30](https://www.terremag.defense.gouv.fr/sites/default/files/2022-12/TIM-333.pdf)
décrit la refonte du parcours des sous-officiers :

| Brevet | Lecture de la réforme publiée |
|---|---|
| `BM1` | Remplace le BSAT (brevet de spécialiste de l’armée de Terre). |
| `BM2` | Remplace le BSTAT (brevet supérieur de technicien de l’armée de Terre). |
| `BM3` | Brevet supplémentaire associé aux responsabilités de chef de section. |
| `BM4` | Succède aux épreuves de sélection professionnelle. |

Conserver BSAT/BSTAT dans les dossiers historiques avec leur date. Les BM sont des brevets :
ne pas fabriquer de code de spécialité `INF3`, de grade, ou de conversion AFSC à partir de
leur numéro. L’éligibilité réelle reste régie par les textes de parcours professionnel applicables.

## France — Armée de l’Air et de l’Espace

L’AAE publie des indices de spécialité / spécialisation ; leur construction diffère des AFSC.
Les annexes IV et V de l’[arrêté du 12 février 2021, texte consolidé](https://www.legifrance.gouv.fr/loda/id/JORFTEXT000043162528/)
attestent les codes suivants. La version consultée affiche une mise à jour au 1er octobre 2026.

| Code publié | Appellation dans le texte | Granularité à conserver |
|---|---|---|
| `341X` | Fusilier, commando ou maître-chien ; équipier parachutiste selon catégorie | Regroupement de spécialités, pas certification CPA 10. |
| `3411`, `3416` | Équipier fusilier ou maître-chien de l’air | Deux indices regroupés dans la même ligne réglementaire ; ne pas attribuer à chacun un intitulé détaillé non vérifié. |
| `3220` | Opérateur de surveillance aérienne | Spécialité. |
| `3230` | Technicien planification et conduite des opérations aériennes | Spécialité. |
| `342X` | Défense sol-air | Regroupement. |

Une [monographie officielle, instruction 243](https://www.defense.gouv.fr/sites/default/files/sga/Texte%204%20INSTRUCTION%20N%C2%B0%20243.pdf)
emploie également `341X` pour les fusiliers commandos de l’air. Son texte intégral n’a pas
pu être téléchargé lors de cet audit ; sa date et ses éventuelles mises à jour restent à vérifier.
Le `X` ne reçoit pas la signification du quatrième caractère d’un AFSC. Les brevets
parachutistes, stages commandos et qualifications JTAC restent distincts du code de spécialité.

## France — Marine nationale

Les annexes de l’[arrêté du 20 décembre 2024, texte consolidé](https://www.legifrance.gouv.fr/loda/id/JORFTEXT000050830779/)
publient des sigles de spécialités ; l’[annexe III dans la publication initiale](https://www.legifrance.gouv.fr/jorf/article_jo/JORFARTI000050830892)
permet également de les retrouver.

| Code | Intitulé publié | Limite |
|---|---|---|
| `FUSIL` | Fusilier | Ne vaut pas brevet commando ni affectation à un commando Marine. |
| `PLONG` | Plongeur démineur | Ne pas assimiler au nageur de combat d’un commando. |
| `NAVIT` | Navigateur-timonier | Spécialité de navigation. |
| `RECOM` | Réseaux et télécommunications | Spécialité de communications. |
| `SYNUM` | Systèmes numériques | Ne pas remplacer par un ancien sigle sans vérifier la date. |

Les brevets **BAT** (brevet d’aptitude technique), **BS** (brevet supérieur) et **BM**
(brevet de maîtrise) constituent une progression de formation, présentée sur le site
[La Marine recrute — vie professionnelle](https://lamarinerecrute.gouv.fr/se-projeter/la-vie-de-marin/s-affirmer-dans-la-marine?idfamille=31&profil=profil-non-officier).
Le BM Marine et les BM1–BM4 Terre appartiennent à des cursus distincts. Les numéros
de niveau RNCP/NSF éventuellement associés à une certification civile ne sont pas des
codes de spécialité militaire. Stocker un BAT FUSIL comme une qualification liée à FUSIL,
sans inventer un numéro AFSC équivalent.

## Royaume-Uni — trades, branches et groupes de métiers

Les appellations de métier et les regroupements administratifs doivent être conservés dans
leur contexte de service et de publication.

| Service / structure | Appellation attestée | Code / regroupement vérifié | Utilisation proposée |
|---|---|---|---|
| Royal Air Force | RAF Regiment Gunner | **Trade Group 8**, attesté par le MOD en 2021 et dans des données au 1er janvier 2023 | Conserver la valeur publiée `8` avec le système « RAF Trade Group » ; `TG8` n’est ici qu’un abrégé de lecture. Actualité en 2026 à confirmer. |
| British Army / UKSF(R) | SAS – Reserve | Aucun code individuel vérifié dans les pages consultées | Appellation de rôle et rattachement d’unité ; code absent. |
| UKSF | United Kingdom Special Forces | Sigle de structure, pas un MOS | Conserver dans le référentiel des organisations. |

Sources : [MOD, publication FOI 2021/00680](https://www.gov.uk/government/publications/foi-responses-released-by-mod-week-commencing-22-february-2021),
[MOD, FOI 2023-05478](https://assets.publishing.service.gov.uk/media/65573a7c046ed400148b9aa2/FOI2023-05478.pdf),
[RAF Recruitment — Regiment Gunner](https://recruitment.raf.mod.uk/roles/roles-finder/ground-combat-security/raf-regiment-gunner),
[British Army — UK Special Forces Reserve](https://www.army.mod.uk/learn-and-explore/about-the-army/corps-regiments-and-units/uk-special-forces-reserve/)
et [Army Jobs — SAS Reserve](https://jobs.army.mod.uk/roles/infantry/sas-reserve/).

La page carrière RAF décrit la formation de spécialité et un Trade Ability Test. Elle ne
publie pas une progression AFSC. Ne pas déduire un code individuel universel, un niveau
enlisted américain ou la réussite d’une sélection UKSF à partir d’un grade britannique.
« Aucun code vérifié » décrit le résultat de cette recherche, pas l’absence certaine de
codification dans les systèmes RH internes britanniques.

## OTAN — portée des standards

L’OTAN est une organisation internationale, pas un pays ISO 3166-1. Le pack existant
`config/job_catalog/nato.json` peut décrire des fonctions interalliées sans créer de faux pays
ni de faux codes de spécialité nationaux.

| Référence | Ce qu’elle décrit | Destination dans ATHENA |
|---|---|---|
| STANAG 2116, codes `OF-…` / `OR-…` | Correspondance des grades | Grades, pas spécialités. |
| STANAG 6001, profil linguistique SLP | Compétences linguistiques, par aptitude | Qualifications linguistiques. |
| JTAC / JFO | Qualifications et fonctions dans le domaine des feux | Qualifications / emplois selon le cas, pas code métier multinational unique. |
| AJP-3.5 | Doctrine des opérations spéciales | Référentiel doctrinal. |

Sources primaires : [rapport OTAN, tableau des grades STANAG 2116](https://www.nato.int/content/dam/nato/webready/documents/genad/NCGP_Full_Report_2019.pdf),
[NATO School — exemple d’exigence linguistique STANAG 6001](https://www.natoschool.nato.int/s/academics/course-catalogue/w3-learning-course/a0Hdr00000A79amEAB/lc1625),
[BILC — profil linguistique et niveaux STANAG 6001](https://natobilc.org/documents/LanguageTesting/Level%204%20Proficiency%20-%20Executive%20Summary.pdf)
et [AJP-3.24, § 4.42, renvoi à AJP-3.5](https://assets.publishing.service.gov.uk/media/65e8683f5b65240011f21a82/AJP-3_24_Peace_Support_EdA_V1-O.pdf).
Aucune nomenclature OTAN publique universelle remplaçant les MOS/AFSC nationaux n’a été
établie par cette recherche. Ne pas créer de code « NATO-SOF » ou d’équivalence automatique.

## Traçabilité et limites de la recherche

Les pages HTML Légifrance, recrutement RAF, Army et 160th ont été consultées. Les PDF Army
CMF 18 et DA PAM 611-21 chap. 8B ont été téléchargés et lus ; la publication GOV.UK identifie
explicitement le Trade Group 8. Plusieurs PDF `defense.gouv.fr` ont expiré au téléchargement :
leurs passages ont été recoupés dans l’index public officiel, et les limites de date ou de
statut sont signalées ci-dessus. Les PDF Air Force ont renvoyé HTTP 403.

Pour une entrée dynamique, conserver l’éditeur, l’URL, la date de publication connue, la
date de vérification, le passage de référence et le statut de preuve. Une date de consultation
ne signifie pas que l’ensemble du système est à jour. Une source ancienne ou une correspondance
de code incertaine reste marquée comme telle et n’alimente pas une attribution automatique.
