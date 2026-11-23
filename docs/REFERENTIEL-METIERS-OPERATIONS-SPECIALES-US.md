# Référentiel des métiers d’opérations spéciales américaines

> **Portée.** Ces fiches constituent une aide documentaire pour la simulation. Elles ne sont ni
> un support de recrutement, ni une procédure opérationnelle. Les critères d’admission, les
> cursus et les rattachements évoluent : la version en vigueur de la source officielle prévaut.
> Les détails classifiés, tactiques ou non confirmés sont volontairement exclus.

## 1. Méthode et sources de référence

**Recherche documentaire : 17 novembre 2026.** Complément : [nomenclatures France,
Royaume-Uni et OTAN](referentiels/SPECIALITES-MILITAIRES-PAR-PAYS.md). Le
[dossier d’audit et de schéma proposé](technical/specialites-militaires-proposition.md)
décrit l’intégration RH/LMS implémentée après validation du schéma, disponible après la migration 4910.

| Périmètre | Source primaire à vérifier | Usage dans ces fiches |
|---|---|---|
| U.S. Air Force | [Air Force Enlisted Classification Directory (AFECD), portail myFSS](https://myfss.us.af.mil/USAFCommunity/s/knowledge-detail?pid=kA08300000000sfCAA) et [Air Force recruiting — Special Warfare](https://www.airforce.com/careers/special-warfare-and-combat-support/special-warfare) | AFSC, mission, conditions d’accès et pipeline public |
| U.S. Army | [Smartbook DA PAM 611-21](https://www.milsuite.mil/book/groups/smartbook-da-pam-611-21) et [Army Publishing Directorate](https://armypubs.army.mil/) | MOS/AOC et règles de classification |
| 160th SOAR (A) | [Page officielle USASOC](https://www.army.mil/160thSOAR) et [recrutement officiel](https://go160thsoar.com/) | Mission, aéronefs, sélection et Green Platoon publics |
| Doctrine interarmées | [Joint Publications](https://www.jcs.mil/Doctrine/Joint-Doctrine-Pubs/) et [JP 3-05, Joint Doctrine for Special Operations](https://www.jcs.mil/Portals/36/Documents/Doctrine/pubs/jp3_05.pdf) | Vocabulaire, commandement et intégration interarmées |
| Historique institutionnel | [USASOC History Office](https://www.arsof-history.org/) | Éléments historiques déclassifiés uniquement |

### Règles de lecture

1. Un **AFSC** décrit une spécialité Air Force ; un **MOS/AOC** décrit une spécialité ou un
   domaine Army. Ni l’un ni l’autre ne prouve à lui seul l’affectation à une unité particulière.
2. **JTAC** est une qualification de contrôleur terminal, pas un synonyme universel de TACP.
3. « Série 15 » est une famille de métiers Army Aviation. Un pilote du 160th n’a donc pas un
   unique « MOS 15 » : son identifiant dépend notamment de son statut et de l’aéronef. L’unité
   sélectionne des aviateurs déjà qualifiés puis les forme à sa mission.
4. Les minima chiffrés (ASVAB, examen médical, test physique, âge, engagement) ne sont pas
   recopiés ici : ils changent plus vite que la doctrine. Ils doivent être relevés dans l’AFECD ou
   le DA PAM daté, puis confirmés par le canal officiel de recrutement.

### Nomenclature USAF : code de famille et niveau individuel

| Système | Code publié de la spécialité | Intitulé officiel | Abrégé usuel |
|---|---|---|---|
| AFSC enlisted | `1Z2X1` | Combat Control | CCT |
| AFSC enlisted | `1Z1X1` | Pararescue | PJ |
| AFSC enlisted | `1Z3X1` | Tactical Air Control Party | TACP |
| AFSC enlisted | `1Z4X1` | Special Reconnaissance | SR |

Ces quatre codes sont confirmés par la [publication institutionnelle USAF sur le champ 1Z,
effective au 31 octobre 2019](https://www.af.mil/News/Article/2039651/air-force-improves-efficiencies-for-special-warfare-airmen/)
et par le [catalogue de recrutement Air Force](https://www.airforce.com/careers/career-finder).
L’abrégé d’un métier ne constitue pas un autre code de spécialité.

Le [DAFMAN 36-2100, tableau de construction des AFSC enlisted](https://static.e-publishing.af.mil/production/1/af_a1/publication/dafman36-2100/dafman36-2100.pdf)
place le niveau au **quatrième caractère** du code de base à cinq caractères :

| Caractère de niveau | Appellation officielle | Sens pour la lecture du dossier |
|---|---|---|
| `1` | Helper | Entrée dans la spécialité, formation initiale. |
| `3` | Apprentice | Formation initiale de spécialité acquise. |
| `5` | Journeyman | Compétence professionnelle consolidée par la formation et l’expérience. |
| `7` | Craftsman | Expertise technique et capacité d’encadrement. |
| `9` | Superintendent | Supervision et gestion élargies du domaine professionnel. |
| `0` | Chief Enlisted Manager | Code de gestion de carrière ; ne pas le traiter comme un niveau inférieur à `1`. |

Ainsi, `1Z2X1` est une notation de catalogue ; dans `1Z251`, le `5` désigne le niveau
Journeyman. Cet exemple explique la syntaxe : la liste des codes effectivement attribuables
et les conditions de passage relèvent de l’AFECD et du CFETP applicables. Ne pas générer
toutes les variantes par remplacement de `X` : des regroupements de carrière peuvent modifier
le code, notamment aux niveaux supérieurs. Un niveau métier ne vaut ni grade, ni JTAC,
ni affectation Special Tactics. Les AFSC d’officiers ont une autre construction : la règle
enlisted `1/3/5/7/9` ne s’applique pas à `19ZXA`, `19ZXB` ou `19ZXC`.

**Limite de vérification.** Le tableau des niveaux du DAFMAN a été retrouvé dans l’index
public de la source officielle ; le téléchargement intégral renvoie HTTP 403 dans cet
environnement. L’édition AFECD courante n’a pas été consultée dans myFSS. Aucune liste
exhaustive de variantes individuelles n’est donc déclarée vérifiée ici.

### Nomenclature Army : métiers, options d’engagement et statuts

| Système | Code exact | Intitulé officiel / nature | Point d’attention |
|---|---|---|---|
| Warrant officer MOS | `153A` | Rotary Wing Aviator (Aircraft Nonspecific) | Code aviation sans désignation d’appareil ; ne signifie pas « pilote du 160th ». |
| Warrant officer MOS | `153E` | MH-60 Pilot | Spécialité sur appareil ; distincte d’une qualification de mission DAP. |
| Warrant officer MOS | `152C` | OH-6 Pilot | Conserver l’intitulé publié ; ne pas le rebaptiser automatiquement « MH-6 ». |
| Warrant officer MOS | `154E` | MH-47 Pilot | Spécialité sur appareil, distincte de l’affectation au régiment. |
| MOS enlisted | `15T` | UH-60 Helicopter Repairer | Maintenance / fonctions d’équipage selon qualifications ; ce n’est pas un MOS de pilote. |
| Option d’engagement présentée sous MOS | `18X` | Special Forces Candidate | Candidature au parcours ; ne certifie pas la réussite de la sélection ou la qualification SF. |
| MOS enlisted | `18B` | Special Forces Weapons Sergeant | Spécialité SF armes. |
| MOS enlisted | `18C` | Special Forces Engineer Sergeant | Spécialité SF génie. |
| MOS enlisted | `18D` | Special Forces Medical Sergeant | Spécialité SF santé. |
| MOS enlisted | `18E` | Special Forces Communications Sergeant | Spécialité SF transmissions. |

Sources primaires : [USAREC — 153A](https://recruiting.army.mil/ISO/AWOR/153A/),
[DA PAM 611-21, chapitre 8, section B, fichier publié le 4 décembre 2025, § 8-152 à 8-154](https://api.army.mil/e2/c/downloads/2025/12/04/720183c2/chapter-8b-wo-specifications.pdf),
[HRC — Aviation MOS, rubrique 15T](https://www.hrc.army.mil/content/48),
[GoArmy — 18X](https://www.goarmy.com/careers-and-jobs/ground-forces/firearms-ammunition/18x-special-forces-candidate)
et [DA PAM 600-25, CMF 18, 7 février 2025, chapitre 4](https://api.army.mil/e2/c/downloads/2025/02/07/4a45e99c/cmf-18-da-pam-600-25-7-feb-25.pdf).
Les deux documents Army ont été téléchargés et lus sur les passages concernés.

Le **MOS de base**, le **skill level**, les **SQI** (Special Qualification Identifiers)
et les **ASI** (Additional Skill Identifiers) sont des composantes distinctes. Exemple HRC :
`15T1O` est un MOSC de niveau `1`, avec la lettre `O` en dernière position ; `15T` reste
le code de base. Ne pas appliquer les niveaux AFSC à l’Army : le document CMF 18 distingue
notamment `18B3` et `18B4`, puis `18Z5` et `18Z6` pour d’autres responsabilités.

Enfin, le [160th SOAR — warrant officers](https://go160thsoar.com/warrant-officer/)
publie un processus de candidature et d’évaluation séparé de la qualification MOS.
Les [métiers enlisted et le Green Platoon](https://go160thsoar.com/enlisted/) constituent
également un parcours propre au régiment. Un code MOS valide ne doit donc jamais attribuer
automatiquement une unité, un poste navigant ou une qualification dans ATHENA.

---

## 2. Combat Controller (CCT)

### Code officiel et branche

**AFSC 1Z2X1 — Combat Control, United States Air Force.** Le niveau de compétence remplace le
« X » dans un dossier individuel. Le CCT appartient au champ **Air Force Special Warfare**.

### Résumé de la mission

Le Combat Controller établit et contrôle une capacité aérienne depuis un environnement austère
ou hostile. Il combine contrôle aérien, reconnaissance de zone d’atterrissage, commandement et
contrôle, communications et intégration des feux afin de relier la force au sol à la puissance
aérienne interarmées.

### Responsabilités clés

- reconnaître, ouvrir et contrôler des **airfields**, assault zones et drop zones ;
- fournir le contrôle de la circulation aérienne expéditionnaire et les données météo utiles ;
- établir des communications voix/données sécurisées et transmettre la situation tactique ;
- intégrer les moyens aériens et, lorsque qualifié et désigné, assurer le contrôle terminal JTAC ;
- préparer les insertions/extractions et conseiller le chef de force sur l’emploi de l’air ;
- maintenir les compétences de tir, mobilité terrestre, survie et médecine tactique de base.

### Compétences et matériels

Radios multibandes et SATCOM, balises et aides à la navigation, GPS/cartographie, procédures ATC,
désignation et observation, parachutisme militaire et insertion amphibie selon qualification. La
fiche ne prescrit ni fréquences, ni chiffrement, ni techniques d’assaut.

### Pipeline public, dans l’ordre général

1. **Basic Military Training** ;
2. **Special Warfare Candidate Course** puis **Assessment & Selection** ;
3. formation préparatoire et cours de contrôle aérien/Combat Control ;
4. écoles de mobilité applicables (parachutisme, survie, etc.) ;
5. **Special Tactics Training Squadron** : intégration des compétences et certification initiale ;
6. entraînement de mission au sein de l’unité, puis maintien périodique des qualifications.

Les noms, lieux et durées doivent être contrôlés sur la page carrière et dans l’AFECD courants.

### Scénario fictif

Une force conjointe doit évacuer des ressortissants depuis une piste endommagée. Inséré avec
l’élément avancé, le CCT évalue la surface, balise une zone utilisable, établit le contact avec les
aéronefs et séquence leur arrivée pour permettre l’extraction sous le contrôle du chef de mission.

---

## 3. Pararescue (PJ)

### Code officiel et branche

**AFSC 1Z1X1 — Pararescue, United States Air Force**, champ Air Force Special Warfare.
« Pararescueman » désigne un individu ; **Pararescue** est l’intitulé de la spécialité.

### Résumé de la mission

Le PJ recherche, rejoint, stabilise et récupère du personnel isolé ou blessé dans des milieux où
les secours conventionnels ne peuvent pas intervenir. Sa fonction associe sauvetage spécialisé,
médecine préhospitalière avancée et mobilité de combat.

### Responsabilités clés

- conduire la recherche et récupération de personnel (**personnel recovery**) ;
- assurer triage, stabilisation, soins prolongés adaptés au terrain et préparation d’évacuation ;
- mettre en œuvre sauvetage sur corde, en eau, en montagne ou en espace contraint selon cursus ;
- préparer et coordonner extraction, treuillage et transfert vers l’échelon médical ;
- entretenir matériels médicaux, oxygène, systèmes de levage et équipements individuels ;
- s’intégrer à un détachement Special Tactics ou à une force de récupération interarmées.

### Compétences et matériels

Médecine paramédicale certifiée selon le cursus en vigueur, gestion des voies aériennes et du
traumatisme, monitorage, immobilisation, cordage/treuillage, plongée et parachutisme lorsque
qualifié, navigation et communications tactiques. Les actes restent soumis au protocole médical,
à la compétence attestée et à la direction médicale.

### Pipeline public, dans l’ordre général

1. Basic Military Training ;
2. Special Warfare Candidate Course et Assessment & Selection ;
3. préparation et mobilité (airborne, dive, survival et parachutisme selon le cursus publié) ;
4. formation **EMT/paramedic** et Pararescue Apprentice Course ;
5. intégration tactique et certification initiale en unité ;
6. maintien des compétences cliniques et de récupération.

### Scénario fictif

Après l’atterrissage forcé d’un équipage en relief montagneux, l’équipe de récupération dépose le
PJ à distance. Il rejoint les survivants, traite une hémorragie, organise un point de treuillage et
transmet un bilan structuré à l’aéronef d’évacuation.

---

## 4. Tactical Air Control Party (TACP)

### Code officiel et branche

**AFSC 1Z3X1 — Tactical Air Control Party, United States Air Force**, champ Air Force Special
Warfare. Tous les TACP ne sont pas automatiquement JTAC ; cette qualification est acquise et
maintenue séparément selon les normes applicables.

### Résumé de la mission

Le spécialiste TACP accompagne principalement les formations terrestres et leur apporte
l’expertise d’intégration de la puissance aérienne. Il établit la liaison avec les réseaux de
commandement et conseille la manœuvre ; un TACP qualifié JTAC peut effectuer le contrôle terminal
des attaques conformément aux procédures et à l’autorité reçue.

### Responsabilités clés

- conseiller le commandement terrestre sur l’intégration air-sol et les effets disponibles ;
- installer et exploiter les réseaux de communication tactiques ;
- produire les demandes, comptes rendus et coordinations nécessaires à l’appui aérien ;
- prévenir les conflits d’espace et contribuer à la réduction des risques pour les forces amies ;
- observer, localiser et décrire les objectifs avec les moyens autorisés ;
- conserver les qualifications de mobilité, communications et contrôle terminal applicables.

### Compétences et matériels

Radios multibandes, données tactiques, GPS/cartographie, optiques et télémétrie, procédures de
**Close Air Support**, coordination de l’espace aérien et planification des feux. Cette description
n’est pas une check-list de contrôle terminal.

### Pipeline public, dans l’ordre général

1. Basic Military Training ;
2. Special Warfare Candidate Course et Assessment & Selection ;
3. **TACP Apprentice Course** et formations de terrain associées ;
4. entraînement en unité et qualification de combat mission-ready ;
5. cursus JTAC pour les personnels sélectionnés, évaluations et recertifications périodiques.

### Scénario fictif

Un bataillon interarmées est immobilisé par une position adverse. Le TACP consolide la situation,
conseille le commandant sur les effets et la séparation des volumes, puis coordonne l’aéronef avec
les autres appuis. Le contrôle terminal n’est exécuté que par le personnel qualifié et autorisé.

---

## 5. Pilote d’hélicoptère d’opérations spéciales — 160th SOAR (A)

### Code officiel et branche

**U.S. Army Aviation, Career Management Field 15.** L’affectation au **160th Special Operations
Aviation Regiment (Airborne)** n’est pas un MOS. Les aviateurs sont officiers ou warrant officers ;
les codes warrant officer comprennent notamment **153A** (Rotary Wing Aviator, Aircraft
Nonspecific), **153E** (MH-60 Pilot) et **154E** (MH-47 Pilot). Ils sont à distinguer du
**15T** (UH-60 Helicopter Repairer), métier enlisted de maintenance pouvant comporter des
fonctions d’équipage. Les sources et limites de ces codes figurent en section 1.

### Résumé de la mission

Le pilote planifie et conduit l’infiltration, l’exfiltration, le ravitaillement et l’appui aérien de
forces d’opérations spéciales, souvent de nuit, à basse altitude et dans des conditions exigeantes,
au sein d’un équipage complet.

### Responsabilités clés

- préparer mission, carburant, performances, météo, risques et solutions de dégagement ;
- piloter dans les limites de l’aéronef et des procédures de l’unité ;
- coordonner équipage, formation, contrôleurs et force embarquée ;
- conduire répétitions, briefings, débriefings et gestion des menaces ;
- maintenir aptitude au vol, qualifications appareil et standardisation ;
- contribuer à la disponibilité opérationnelle et à la sécurité aérienne.

### Compétences et aéronefs

Pilotage aux instruments et sous jumelles de vision nocturne, navigation tactique, vol en
formation, communications interarmées, gestion des ressources d’équipage et planification. La
flotte publiquement associée au régiment comprend les familles **MH/AH-6**, **MH-60** et **MH-47** ;
la qualification dépend du poste et de l’unité, elle n’est pas interchangeable.

### Pipeline public, dans l’ordre général

1. accession Army et formation initiale d’officier ou de warrant officer ;
2. **Initial Entry Rotary Wing** / cursus de vol et qualification sur type ;
3. expérience et aptitude requises pour le poste publié par le 160th ;
4. candidature, évaluation et sélection propres au régiment ;
5. **Special Operations Aviation Training Company / Green Platoon** ;
6. progression régimentaire, évaluation en unité et maintien des qualifications.

### Scénario fictif

Une force spéciale doit être déposée sur deux zones séparées avant l’aube. Le pilote coordonne la
formation, recalcule les performances avec la charge réelle, exécute l’approche sous JVN et reste
disponible pour une extraction sur critère défini par le commandement.

---

## 6. B Squadron / 1st SFOD-D : fiche d’unité prudente

### Désignation et hiérarchie

L’appellation historique **1st Special Forces Operational Detachment–Delta (1st SFOD-D)** est
publiquement associée à « Delta Force ». Les appellations **Combat Applications Group (CAG)** et
« The Unit » sont fréquemment employées dans les sources ouvertes. Les sources institutionnelles
publiques permettent de le situer dans l’écosystème U.S. Army Special Operations et JSOC, mais ne
publient pas un organigramme actuel détaillé. **Tier 1** est un terme courant de sources ouvertes,
pas un niveau d’habilitation à afficher comme une donnée administrative officielle.

### Profil des opérateurs

Les récits historiques publics décrivent une sélection interne ouverte à des militaires
expérimentés, notamment issus des Special Forces et du 75th Ranger Regiment, suivie d’un cursus
communément appelé **Operator Training Course (OTC)**. Une affectation antérieure ou un MOS de la
série 18 ne permet pas de déduire le code, le poste ou les habilitations d’un opérateur actuel.

### Organisation : ce qui peut et ne peut pas être affirmé

Des ouvrages et témoignages publics décrivent des **sabre squadrons** identifiés A, B, C et D,
composés de troops. Ils attribuent souvent deux assault troops et une reconnaissance/sniper troop
à un squadron, avec des compétences haute altitude, maritime et véhicules. Cette construction
doit être étiquetée **source ouverte historique**, et non « structure officielle actuelle » :
ni l’effectif, ni la composition présente du B Squadron, ni ses indicatifs ne sont confirmés ici.

### Domaines d’excellence documentés au niveau de l’unité

- contre-terrorisme et libération d’otages à haut risque ;
- action directe et opérations spéciales sensibles ;
- capture de personnes désignées à haute valeur (**HVT**) sous autorité compétente ;
- reconnaissance spéciale et coopération avec des capacités interarmées.

Ces missions génériques ne permettent pas d’attribuer une opération donnée au B Squadron.

### Culture et conduite

L’expression **Quiet Professionals** décrit plus largement l’éthique des forces spéciales :
discrétion, maîtrise, responsabilité et primauté de la mission. Les vêtements civils ou l’apparence
adaptée au milieu sont des éléments rapportés dans certains contextes historiques, pas un uniforme
permanent ni une règle distinctive du B Squadron. Autonomie ne signifie jamais absence de chaîne
de commandement, de règles d’engagement ou de droit applicable.

### Scénario fictif

Dans un exercice interarmées fictif, un élément désigné « B Squadron » prépare la récupération
d’otages avec des moyens aviation et renseignement. Les assault elements répètent l’intervention,
un élément d’observation confirme les changements autour du site, et le commandement conserve les
décisions d’exécution et d’abandon. Aucun détail ne représente une procédure réelle de l’unité.

---

## 7. Contrôle éditorial avant publication

- relever le numéro de version et la date de l’AFECD ou du DA PAM effectivement consulté ;
- distinguer une exigence réglementaire d’une simple information de recrutement ;
- conserver l’intitulé officiel anglais à côté de sa traduction française ;
- ne jamais présenter une qualification (JTAC, dive, military free fall) comme automatique ;
- dater tout organigramme et qualifier clairement les sources ouvertes historiques ;
- supprimer effectifs, indicatifs, fréquences, tactiques détaillées et données personnelles ;
- faire relire toute évolution par un référent doctrine avant synchronisation dans le catalogue.
