# Prérequis détectés dans les catalogues de qualifications

Généré par `php tools/qualifications/detect-prerequisites.php --write` (ne pas modifier à la main : relancer l’outil).

Chaque ajout ci-dessous est écrit dans le champ `prerequisites_detected` de l’entrée du catalogue (confiance ≥ 0.8). Ils ne s’appliquent jamais seuls : à l’import ou depuis la fiche, l’encart « Prérequis détectés » les propose cochés, la personne valide. Les détections moins sûres restent des suggestions non cochées (liste en fin de rapport).

À relire : la phrase source justifie-t-elle l’ajout ? Un ajout faux se retire en corrigeant l’extracteur (`app/Support/QualificationPrerequisiteExtractor.php`, `QualificationPrerequisiteResolver.php`) puis en relançant l’outil.

## Synthèse

| Pack | Qualifications | Mesurés (jeu, site) | Attestés | Grade | Total |
|---|---|---|---|---|---|
| us | 72 | 3 | 1 | 3 | 79 |
| fr | 25 | 2 | 0 | 0 | 27 |
| de | 19 | 3 | 2 | 2 | 26 |
| cn | 4 | 0 | 0 | 0 | 4 |
| ru | 3 | 0 | 0 | 0 | 3 |
| gb | 5 | 0 | 0 | 7 | 12 |
| my | 11 | 1 | 1 | 2 | 15 |
| nato | 2 | 0 | 0 | 10 | 12 |
| **Total** | | | | | **178** |

## US

### `us-sfas` — Évaluation et sélection des forces spéciales (SFAS)

- **grade minimum OR-3 (E-3)** — confiance 0.85, prérequis n° 1
  - FR : « Militaire de l’Army à partir de E-3, ou officier 1LT promouvable / CPT »
  - EN : “Army soldier E-3 or above, or officer 1LT (P) / CPT”

### `us-sfqc` — Cours de qualification des forces spéciales (SFQC, « Q Course »)

- **qualification « Évaluation et sélection des forces spéciales (SFAS) » (`us-sfas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection SFAS réussie »
  - EN : “Passed SFAS”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 2
  - FR : « Qualification parachutiste (Basic Airborne Course) »
  - EN : “Airborne qualified (Basic Airborne Course)”

### `us-sf-medical-sergeant` — Cours de sergent médical des forces spéciales (18D)

- **qualification « Infirmier de combat des opérations spéciales (SOCM) » (`us-socm`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours SOCM (Special Operations Combat Medic) »
  - EN : “Special Operations Combat Medic (SOCM) course”

### `us-cdsc` — Cours de chef de palanquée plongeur de combat (Dive Supe)

- **qualification « Cours de qualification de plongeur de combat (CDQC) » (`us-cdqc`)** — confiance 0.85, prérequis n° 1
  - FR : « Qualification de plongeur de combat (CDQC) »
  - EN : “Combat diver qualification (CDQC)”

### `us-mffpc` — Cours de parachutiste à ouverture commandée retardée (MFF)

- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet de parachutiste (Basic Airborne Course) »
  - EN : “Parachutist qualification (Basic Airborne Course)”

### `us-mffjm` — Chef largueur chute libre (MFF Jumpmaster)

- **qualification « Chef largueur (Jumpmaster, ouverture automatique) » (`us-jumpmaster`)** — confiance 0.90, prérequis n° 1
  - FR : « Basic Airborne Course, MFFPC et Jumpmaster (ouverture automatique) »
  - EN : “Basic Airborne Course, MFFPC and static-line Jumpmaster”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 1
  - FR : « Basic Airborne Course, MFFPC et Jumpmaster (ouverture automatique) »
  - EN : “Basic Airborne Course, MFFPC and static-line Jumpmaster”
- **qualification « Cours de parachutiste à ouverture commandée retardée (MFF) » (`us-mffpc`)** — confiance 0.85, prérequis n° 1
  - FR : « Basic Airborne Course, MFFPC et Jumpmaster (ouverture automatique) »
  - EN : “Basic Airborne Course, MFFPC and static-line Jumpmaster”
- **50 saut(s) en chute libre, attestés par un instructeur** — confiance 0.85, prérequis n° 2
  - FR : « Au moins un an comme parachutiste de chute libre et 50 sauts en chute libre »
  - EN : “At least one year as a free-fall parachutist and 50 military free-fall jumps”

### `us-eic` — Cours d’instructeur évaluateur (EIC)

- **qualification « Cours d’instructeur (CFDIC) » (`us-cfdic`)** — confiance 0.85, prérequis n° 1
  - FR : « Instructeur qualifié (CFDIC) »
  - EN : “Qualified instructor (CFDIC)”

### `us-basic-airborne` — Brevet de parachutiste militaire (Basic Airborne Course)

- **5 saut(s) en parachute, mesurés par le mod** — confiance 0.80, description
  - Phrase : « Three-week course (ground week, tower week, jump week) open to all Department of Defense services: students make five static-line jumps and receive the Parachutist Badge. »

### `us-jumpmaster` — Chef largueur (Jumpmaster, ouverture automatique)

- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet de parachutiste militaire »
  - EN : “Military parachutist qualification”

### `us-rasp-2` — Programme d’évaluation et de sélection Ranger, niveau 2 (RASP 2)

- **grade minimum OR-6 (E-6)** — confiance 0.85, prérequis n° 1
  - FR : « Sous-officier supérieur (E-6 et plus), officier ou warrant officer d’active »
  - EN : “Active-duty senior NCO (E-6 and above), officer or warrant officer”

### `us-poqc` — Cours de qualification aux opérations psychologiques

- **qualification « Évaluation et sélection des opérations psychologiques (POAS) » (`us-poas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection POAS réussie »
  - EN : “Passed POAS”

### `us-caqc` — Cours de qualification aux affaires civiles

- **qualification « Évaluation et sélection des affaires civiles (CAAS) » (`us-caas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection CAAS réussie »
  - EN : “Passed CAAS”

### `us-soidc` — Infirmier indépendant des opérations spéciales (SOIDC)

- **qualification « Infirmier de combat des opérations spéciales (SOCM) » (`us-socm`)** — confiance 0.85, prérequis n° 1
  - FR : « Hospital corpsman qualifié SOCM »
  - EN : “SOCM-qualified hospital corpsman”

### `us-slc` — Cours de chef supérieur (SLC)

- **qualification « Cours de chef avancé (ALC) » (`us-alc`)** — confiance 0.90, prérequis n° 1
  - FR : « Advanced Leader Course »
  - EN : “Advanced Leader Course”

### `us-160-bmq` — Qualification de base aux missions (BMQ) du 160th SOAR

- **qualification « Green Platoon (combat skills du 160th SOAR) » (`us-160-green-platoon`)** — confiance 0.90, prérequis n° 1
  - FR : « Green Platoon (Officer Combat Skills) »
  - EN : “Green Platoon (Officer Combat Skills)”

### `us-160-fmq` — Pleinement qualifié aux missions (FMQ) du 160th SOAR

- **qualification « Qualification de base aux missions (BMQ) du 160th SOAR » (`us-160-bmq`)** — confiance 0.85, prérequis n° 1
  - FR : « Basic Mission Qualified (BMQ) »
  - EN : “Basic Mission Qualified (BMQ)”

### `us-160-nrcm` — Cours de membre d’équipage non navigant MH-60 / MH-47 (NRCM)

- **qualification « Green Platoon (combat skills du 160th SOAR) » (`us-160-green-platoon`)** — confiance 0.90, prérequis n° 1
  - FR : « Enlisted Combat Skills (Green Platoon) »
  - EN : “Enlisted Combat Skills (Green Platoon)”

### `us-af-sma` — Formation technique de Special Missions Aviator

- **qualification « Formation militaire de base de l’US Air Force (BMT) » (`us-af-bmt`)** — confiance 0.90, prérequis n° 1
  - FR : « Basic Military Training (7,5 semaines) »
  - EN : “Basic Military Training (7.5 weeks)”
- **qualification « Cours des fondamentaux du personnel navigant (Aircrew Fundamentals) » (`us-af-aircrew-fundamentals`)** — confiance 0.90, prérequis n° 2
  - FR : « Aircrew Fundamentals Course »
  - EN : “Aircrew Fundamentals Course”

### `us-af-rpa-sensor` — Formation technique d’opérateur capteurs de drone (RPA Sensor Operator)

- **qualification « Formation militaire de base de l’US Air Force (BMT) » (`us-af-bmt`)** — confiance 0.90, prérequis n° 1
  - FR : « Basic Military Training (7,5 semaines) »
  - EN : “Basic Military Training (7.5 weeks)”

### `us-af-sw-as` — Évaluation et sélection des forces spéciales de l’Air Force (A&S)

- **qualification « Formation militaire de base de l’US Air Force (BMT) » (`us-af-bmt`)** — confiance 0.90, prérequis n° 1
  - FR : « Basic Military Training »
  - EN : “Basic Military Training”

### `us-af-zulu` — Cours de compétences communes des forces spéciales de l’Air Force (Zulu)

- **qualification « Évaluation et sélection des forces spéciales de l’Air Force (A&S) » (`us-af-sw-as`)** — confiance 0.90, prérequis n° 1
  - FR : « Special Warfare Assessment and Selection »
  - EN : “Special Warfare Assessment and Selection”

### `us-af-combat-dive` — Cours de plongée de combat de l’Air Force (AFCDC)

- **qualification « Cours de préparation à la plongée de combat (Pre-Dive) » (`us-af-predive`)** — confiance 0.90, prérequis n° 1
  - FR : « Special Warfare Pre-Dive Course »
  - EN : “Special Warfare Pre-Dive Course”

### `us-af-pj-apprentice` — Cours d’apprenti pararescueman (béret marron)

- **qualification « Formation de paramédic des pararescuemen » (`us-af-pj-paramedic`)** — confiance 0.90, prérequis n° 1
  - FR : « Pararescue EMT-Paramedic Training »
  - EN : “Pararescue EMT-Paramedic Training”

### `us-af-cct-apprentice` — Cours d’apprenti contrôleur de combat

- **qualification « Cours de contrôle aérien des contrôleurs de combat (certification FAA) » (`us-af-atc`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de contrôle aérien (certification FAA) »
  - EN : “Air traffic control course (FAA certification)”

### `us-af-tacp-icc` — Cours de certification initiale TACP / JTAC (ICC)

- **qualification « Cours d’apprenti TACP » (`us-af-tacp-apprentice`)** — confiance 0.90, prérequis n° 1
  - FR : « TACP Apprentice Course »
  - EN : “TACP Apprentice Course”

### `us-marsoc-itc` — Cours Marine Raider (ex-Individual Training Course)

- **qualification « Évaluation et sélection MARSOC (A&S) » (`us-marsoc-as`)** — confiance 0.90, prérequis n° 1
  - FR : « Sélection MARSOC (A&S) réussie »
  - EN : “Passed MARSOC Assessment and Selection”

### `us-sqt` — Formation de qualification SEAL (SQT) et Trident

- **qualification « Formation de base démolition sous-marine / SEAL (BUD/S) » (`us-buds`)** — confiance 0.85, prérequis n° 1
  - FR : « BUD/S »
  - EN : “BUD/S”

### `us-swcc-cqt` — Formation de qualification des équipiers SWCC (CQT)

- **qualification « Sélection de base des équipiers SWCC (BCS, « The Tour ») » (`us-swcc-bcs`)** — confiance 0.90, prérequis n° 1
  - FR : « Basic Crewman Selection et Basic Crewman Training »
  - EN : “Basic Crewman Selection and Basic Crewman Training”

### `us-swcc-senior-master` — Qualifications SWCC confirmé et maître (insignes Senior et Master)

- **qualification « Formation de qualification des équipiers SWCC (CQT) » (`us-swcc-cqt`)** — confiance 0.85, prérequis n° 1
  - FR : « Insigne SWCC de base (CQT) »
  - EN : “Basic SWCC insignia (CQT)”

### `us-sfwoac` — Cours avancé des warrant officers des forces spéciales

- **qualification « Cours de certification technique et tactique des adjudants des forces spéciales (SFWOTTC) » (`us-sfwottc`)** — confiance 0.85, prérequis n° 1
  - FR : « Warrant officer 180A qualifié (SFWOTTC) »
  - EN : “Qualified 180A warrant officer (SFWOTTC)”

### `us-160-flight-lead` — Qualification chef de patrouille (Flight Lead, 160th SOAR)

- **qualification « Pleinement qualifié aux missions (FMQ) du 160th SOAR » (`us-160-fmq`)** — confiance 0.90, prérequis n° 1
  - FR : « Statut FMQ (pleinement qualifié en mission) »
  - EN : “Fully Mission Qualified (FMQ) status”

### `us-160-soamic` — Cours d’initiation des infirmiers de l’aviation des opérations spéciales (SOAMIC)

- **qualification « Formation de spécialiste 68W (combat medic) » (`us-army-68w-ait`)** — confiance 0.85, prérequis n° 1
  - FR : « Spécialité 68W »
  - EN : “MOS 68W”

### `us-160-sof-gray-eagle` — Cours Gray Eagle SOF (160th SOAR)

- **qualification « AIT 15W (opérateur de drones) » (`us-army-15w-ait`)** — confiance 0.85, prérequis n° 1
  - FR : « Spécialité 15W »
  - EN : “MOS 15W”

### `us-af-ip-ep` — Qualification pilote instructeur / évaluateur

- **qualification « Qualification commandant de bord (AFSOC) » (`us-afsoc-ac-upgrade`)** — confiance 0.90, prérequis n° 1
  - FR : « Qualification commandant de bord »
  - EN : “Aircraft commander qualification”

### `us-marsoc-tcc` — Team Commander Course (stage chef d’équipe MARSOC)

- **qualification « Évaluation et sélection MARSOC (A&S) » (`us-marsoc-as`)** — confiance 0.90, prérequis n° 1
  - FR : « Évaluation et sélection MARSOC (A&S) »
  - EN : “MARSOC Assessment and Selection (A&S)”

### `us-nsw-plc` — Cours NSW des chefs de peloton

- **qualification « Cours NSW de chef de groupe (LPO) » (`us-nsw-lpo-course`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours NSW LPO »
  - EN : “NSW LPO Course”

### `us-nsw-tlc` — Cours NSW des chefs de troop

- **qualification « Cours NSW des chefs de peloton » (`us-nsw-plc`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours NSW des chefs de peloton »
  - EN : “NSW Platoon Leaders Course”

### `us-pathfinder` — Brevet de guide aéromobile et aéroporté (Pathfinder Badge)

- **qualification « Brevet d’assaut aéromobile (Air Assault Badge) » (`us-air-assault`)** — confiance 0.85, prérequis n° 1
  - FR : « Qualification Air Assault recommandée »
  - EN : “Air Assault qualification recommended”

### `us-sf-tab` — Insigne des forces spéciales (Special Forces Tab)

- **qualification « Cours de qualification des forces spéciales (SFQC, « Q Course ») » (`us-sfqc`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de qualification des forces spéciales (SFQC) terminé »
  - EN : “Completed the Special Forces Qualification Course (SFQC)”

### `us-sf-18a` — Cours d’officier de détachement des forces spéciales (18A)

- **qualification « Évaluation et sélection des forces spéciales (SFAS) » (`us-sfas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection SFAS réussie »
  - EN : “Passed SFAS”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 3
  - FR : « Qualification parachutiste (Basic Airborne Course) »
  - EN : “Airborne qualified (Basic Airborne Course)”

### `us-sf-18b` — Cours de sergent armement des forces spéciales (18B)

- **qualification « Évaluation et sélection des forces spéciales (SFAS) » (`us-sfas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection SFAS réussie »
  - EN : “Passed SFAS”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 3
  - FR : « Qualification parachutiste (Basic Airborne Course) »
  - EN : “Airborne qualified (Basic Airborne Course)”

### `us-sf-18c` — Cours de sergent génie des forces spéciales (18C)

- **qualification « Évaluation et sélection des forces spéciales (SFAS) » (`us-sfas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection SFAS réussie »
  - EN : “Passed SFAS”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 3
  - FR : « Qualification parachutiste (Basic Airborne Course) »
  - EN : “Airborne qualified (Basic Airborne Course)”

### `us-sf-18e` — Cours de sergent transmissions des forces spéciales (18E)

- **qualification « Évaluation et sélection des forces spéciales (SFAS) » (`us-sfas`)** — confiance 0.85, prérequis n° 1
  - FR : « Sélection SFAS réussie »
  - EN : “Passed SFAS”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 3
  - FR : « Qualification parachutiste (Basic Airborne Course) »
  - EN : “Airborne qualified (Basic Airborne Course)”

### `us-eib` — Badge de fantassin expert (EIB)

- **qualification « Qualification de tir « expert » de l’Army (badge de tir) » (`us-army-marksman-expert`)** — confiance 0.90, prérequis n° 2
  - FR : « Qualification de tir « expert » de l’Army au fusil »
  - EN : “Army Marksmanship Qualification Badge (Expert) on the rifle”

### `us-efmb` — Badge d’expert en médecine de terrain (EFMB)

- **qualification « Qualification de tir « expert » de l’Army (badge de tir) » (`us-army-marksman-expert`)** — confiance 0.90, prérequis n° 1
  - FR : « Qualification de tir « expert » de l’Army au fusil M4, dans les 12 derniers mois »
  - EN : “Army Marksmanship Qualification Badge (Expert) on the M4, within the last 12 months”

### `us-army-sniper` — Cours de tireur d’élite de l’Army (Army Sniper Course)

- **qualification « Qualification de tir « expert » de l’Army (badge de tir) » (`us-army-marksman-expert`)** — confiance 0.90, prérequis n° 2
  - FR : « Qualification de tir « expert » de l’Army au fusil »
  - EN : “Army Marksmanship Qualification Badge (Expert) on the rifle”

### `us-ammc` — Cours de montagnard militaire avancé (AMMC)

- **qualification « Cours de montagnard militaire de base (BMMC) » (`us-bmmc`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de montagnard militaire de base (BMMC) réussi »
  - EN : “Passed the Basic Military Mountaineer Course (BMMC)”

### `us-army-aqc-ah64` — Cours de qualification sur appareil AH-64E Apache (AQC)

- **qualification « Formation initiale au pilotage d’hélicoptère (IERW) » (`us-army-ierw`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale au pilotage d’hélicoptère (IERW) »
  - EN : “Initial Entry Rotary Wing (IERW) flight training”

### `us-army-aqc-uh60` — Cours de qualification sur appareil UH-60M Black Hawk (AQC)

- **qualification « Formation initiale au pilotage d’hélicoptère (IERW) » (`us-army-ierw`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale au pilotage d’hélicoptère (IERW) »
  - EN : “Initial Entry Rotary Wing (IERW) flight training”

### `us-army-aqc-ch47` — Cours de qualification sur appareil CH-47F Chinook (AQC)

- **qualification « Formation initiale au pilotage d’hélicoptère (IERW) » (`us-army-ierw`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale au pilotage d’hélicoptère (IERW) »
  - EN : “Initial Entry Rotary Wing (IERW) flight training”

### `us-army-amc` — Désignation de chef de mission aérienne (Air Mission Commander)

- **qualification « Désignation de commandant de bord (Pilot in Command) » (`us-army-pic`)** — confiance 0.90, prérequis n° 1
  - FR : « Pilot in Command (PIC) expérimenté »
  - EN : “Experienced Pilot in Command (PIC)”

### `us-mcmap-gray` — Programme d’arts martiaux du Marine Corps : ceinture grise (MCMAP)

- **qualification « Programme d’arts martiaux du Marine Corps : ceinture tan (MCMAP) » (`us-mcmap-tan`)** — confiance 0.90, prérequis n° 1
  - FR : « Ceinture tan MCMAP »
  - EN : “MCMAP Tan Belt”

### `us-mcmap-green` — Programme d’arts martiaux du Marine Corps : ceinture verte (MCMAP)

- **qualification « Programme d’arts martiaux du Marine Corps : ceinture grise (MCMAP) » (`us-mcmap-gray`)** — confiance 0.90, prérequis n° 1
  - FR : « Ceinture grise MCMAP »
  - EN : “MCMAP Gray Belt”

### `us-mcmap-brown` — Programme d’arts martiaux du Marine Corps : ceinture marron (MCMAP)

- **qualification « Programme d’arts martiaux du Marine Corps : ceinture verte (MCMAP) » (`us-mcmap-green`)** — confiance 0.90, prérequis n° 1
  - FR : « Ceinture verte MCMAP »
  - EN : “MCMAP Green Belt”

### `us-mcmap-black` — Programme d’arts martiaux du Marine Corps : ceinture noire (1er degré) (MCMAP)

- **qualification « Programme d’arts martiaux du Marine Corps : ceinture marron (MCMAP) » (`us-mcmap-brown`)** — confiance 0.90, prérequis n° 1
  - FR : « Ceinture marron MCMAP »
  - EN : “MCMAP Brown Belt”

### `us-mcmap-mai` — Instructeur d’arts martiaux du Marine Corps (MAI)

- **qualification « Programme d’arts martiaux du Marine Corps : ceinture verte (MCMAP) » (`us-mcmap-green`)** — confiance 0.90, prérequis n° 1
  - FR : « Ceinture verte MCMAP au moins »
  - EN : “At least MCMAP Green Belt”

### `us-usmc-scout-sniper` — Cours de tireur d’élite éclaireur des Marines (Scout Sniper)

- **grade minimum OR-3 (lance corporal)** — confiance 0.85, prérequis n° 1
  - FR : « Grade de Lance Corporal au moins »
  - EN : “Lance Corporal or above”

### `us-navy-usmc-parachutist` — Insigne de parachutiste de la Navy et du Marine Corps (ailes dorées)

- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 1
  - FR : « Badge de parachutiste (Basic Airborne Course) »
  - EN : “Parachutist badge (Basic Airborne Course)”
- **5 saut(s) en parachute, mesurés par le mod** — confiance 0.85, prérequis n° 2
  - FR : « Cinq sauts supplémentaires à ouverture automatique »
  - EN : “Five additional static-line jumps”

### `us-navy-efmws` — Spécialiste de guerre de la Fleet Marine Force (EFMWS, « FMF »)

- **ancienneté 365 jours, mesurée (dossier / arrivée)** — confiance 0.85, prérequis n° 1
  - FR : « Un an de service dans une unité du Marine Corps »
  - EN : “One year of service with a Marine Corps unit”

### `us-navy-sarc` — Infirmier de reconnaissance amphibie spéciale (SARC, NEC L11A)

- **qualification « École « A » d’infirmier de la Navy (Hospital Corps School) » (`us-navy-hm-a`)** — confiance 0.90, prérequis n° 1
  - FR : « École d’infirmier de la Navy (HM « A » School) »
  - EN : “Navy Hospital Corpsman “A” School”
- **qualification « Technicien du service médical de campagne (FMST) » (`us-fmst`)** — confiance 0.90, prérequis n° 2
  - FR : « Field Medical Service Technician (FMST) »
  - EN : “Field Medical Service Technician (FMST)”
- **qualification « Cours de reconnaissance de base des Marines (BRC) » (`us-usmc-brc`)** — confiance 0.90, prérequis n° 3
  - FR : « Basic Reconnaissance Course (BRC) »
  - EN : “Basic Reconnaissance Course (BRC)”
- **qualification « Brevet de parachutiste militaire (Basic Airborne Course) » (`us-basic-airborne`)** — confiance 0.90, prérequis n° 4
  - FR : « Basic Airborne Course »
  - EN : “Basic Airborne Course”
- **qualification « Cours de plongeur de combat du Marine Corps (MCD) » (`us-usmc-combatant-diver`)** — confiance 0.90, prérequis n° 5
  - FR : « Marine Corps Combatant Diver Course »
  - EN : “Marine Corps Combatant Diver Course”
- **qualification « Infirmier de combat des opérations spéciales (SOCM) » (`us-socm`)** — confiance 0.90, prérequis n° 6
  - FR : « Cours SOCM (Special Operations Combat Medic) »
  - EN : “Special Operations Combat Medic (SOCM) course”
- **qualification « Batterie de tests d’aptitude ASVAB (score GT) » (`us-asvab`)** — confiance 0.85, prérequis n° 7
  - FR : « Score ASVAB GT de 100 au moins »
  - EN : “ASVAB GT score of 100 or higher”

### `us-af-ftu` — Qualification initiale sur avion d’arme (FTU / IQT)

- **qualification « Formation initiale des pilotes de l’US Air Force (UPT) » (`us-af-upt`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de pilote de l’Air Force (UPT) terminée »
  - EN : “Completed USAF Undergraduate Pilot Training (UPT)”

### `us-jtac-evaluator` — Évaluateur JTAC

- **qualification « Contrôleur d’attaque terminale interarmées (JTAC) » (`us-jtac`)** — confiance 0.90, prérequis n° 1
  - FR : « Joint Terminal Attack Controller (JTAC) qualifié et à jour »
  - EN : “Qualified and current Joint Terminal Attack Controller (JTAC)”

### `us-jcws` — École de planification interarmées et multinationale (JCWS, JPME II)

- **qualification « Cours d’officier d’état-major (CGSOC / ILE) » (`us-cgsoc`)** — confiance 0.85, prérequis n° 1
  - FR : « Officier ayant suivi la phase I de l’enseignement militaire interarmées (JPME I, par ex. CGSOC / ILE) »
  - EN : “Officer with JPME Phase I (e.g. CGSOC / ILE)”

## FR

### `fr-brevet-parachutiste` — Brevet militaire de parachutiste

- **5 saut(s) en parachute, mesurés par le mod** — confiance 0.80, description
  - Phrase : « Training lasts about two weeks at the Airborne Troops School in Pau: originally six jumps, now five are enough, including clean jumps without weapons, a night jump and an equipment jump with weapon and gear bag. »

### `fr-chuteur-operationnel` — Brevet de chuteur opérationnel (SOGH)

- **qualification « Brevet militaire de parachutiste » (`fr-brevet-parachutiste`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet militaire de parachutiste »
  - EN : “Military parachutist badge”

### `fr-moniteur-parachutiste` — Brevet militaire de moniteur parachutiste

- **qualification « Brevet militaire de parachutiste » (`fr-brevet-parachutiste`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet militaire de parachutiste »
  - EN : “Military parachutist badge”

### `fr-cehm` — Brevet de chef d’équipe de haute montagne (été / hiver)

- **qualification « Brevet d’alpiniste militaire (BAM) » (`fr-bam`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet d’alpiniste militaire (BAM) »
  - EN : “Military mountaineering badge (BAM)”

### `fr-sas-po` — Spécialité SAS plongeur offensif (PO)

- **qualification « Formation de base SAS (cursus SAS du 1er RPIMa) » (`fr-cursus-sas`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base SAS »
  - EN : “SAS basic course”

### `fr-sas-jungle` — Équipier SAS jungle

- **qualification « Formation de base SAS (cursus SAS du 1er RPIMa) » (`fr-cursus-sas`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base SAS »
  - EN : “SAS basic course”

### `fr-sas-montagne` — Équipier SAS montagne

- **qualification « Formation de base SAS (cursus SAS du 1er RPIMa) » (`fr-cursus-sas`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base SAS »
  - EN : “SAS basic course”

### `fr-patsas` — Stage patrouille SAS (PATSAS)

- **qualification « Formation de base SAS (cursus SAS du 1er RPIMa) » (`fr-cursus-sas`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base SAS »
  - EN : “SAS basic course”

### `fr-protection-rapprochee` — Stage de protection rapprochée (1er RPIMa)

- **qualification « Formation de base SAS (cursus SAS du 1er RPIMa) » (`fr-cursus-sas`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base SAS »
  - EN : “SAS basic course”

### `fr-cte-ra` — Certificat technique élémentaire recherche aéroportée (CTE RA)

- **qualification « Brevet militaire de parachutiste » (`fr-brevet-parachutiste`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet militaire de parachutiste »
  - EN : “Military parachutist badge”

### `fr-chef-cellule-ra` — Brevet de chef de cellule en équipe de recherche aéroportée

- **qualification « Certificat technique élémentaire recherche aéroportée (CTE RA) » (`fr-cte-ra`)** — confiance 0.90, prérequis n° 1
  - FR : « Certificat technique élémentaire RA »
  - EN : “CTE RA”

### `fr-chef-equipe-ra` — Insigne de chef d’équipe de recherche aéroportée

- **qualification « Tests d’agrément forces spéciales » (`fr-tests-agrement-fs`)** — confiance 0.90, prérequis n° 2
  - FR : « Tests d’agrément forces spéciales »
  - EN : “Special forces screening tests”

### `fr-auxiliaire-sanitaire` — Formation de combattant auxiliaire sanitaire (CeFOS)

- **qualification « Formation initiale du militaire du rang (CFIM) » (`fr-cfim`)** — confiance 0.85, prérequis n° 1
  - FR : « Formation initiale en CFIM »
  - EN : “CFIM initial training”

### `fr-jedburgh` — Stage commando « Jedburgh » (GCP)

- **qualification « Brevet militaire de parachutiste » (`fr-brevet-parachutiste`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet militaire de parachutiste »
  - EN : “Military parachutist badge”

### `fr-bs-fusilier` — Brevet supérieur (BS) de fusilier marin

- **qualification « Brevet d’aptitude technique (BAT) de fusilier marin » (`fr-bat-fusilier`)** — confiance 0.85, prérequis n° 1
  - FR : « Brevet d’aptitude technique (BAT) »
  - EN : “Technical aptitude badge (BAT)”

### `fr-bm-fusilier` — Brevet de maîtrise (BM) de fusilier marin

- **qualification « Brevet supérieur (BS) de fusilier marin » (`fr-bs-fusilier`)** — confiance 0.85, prérequis n° 1
  - FR : « Brevet supérieur (BS) »
  - EN : “Senior badge (BS)”

### `fr-nageur-de-combat` — Certificat de nageur de combat

- **qualification « Brevet élémentaire commando (stage commando de la Marine) » (`fr-brevet-commando-marine`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet élémentaire commando »
  - EN : “Basic commando badge”

### `fr-palmeur` — Qualification de palmeur (commandos Marine)

- **qualification « Brevet élémentaire commando (stage commando de la Marine) » (`fr-brevet-commando-marine`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet élémentaire commando »
  - EN : “Basic commando badge”

### `fr-matou` — Stage Matou (fusiliers commandos de l’air)

- **qualification « Stage Maquis (fusiliers commandos de l’air) » (`fr-maquis`)** — confiance 0.90, prérequis n° 1
  - FR : « Stage Maquis »
  - EN : “Maquis course”

### `fr-commando-parachutiste-air` — Diplôme de commando parachutiste de l’air

- **6 saut(s) en parachute, mesurés par le mod sur 365 jours** — confiance 0.80, description
  - Phrase : « The trade requires at least six parachute jumps a year. »

### `fr-commandant-bord-helicoptere` — Qualification de commandant de bord (hélicoptère, AAE)

- **qualification « Brevet de pilote d’hélicoptère (armée de l’Air et de l’Espace) » (`fr-pilote-helicoptere-aae`)** — confiance 0.90, prérequis n° 1
  - FR : « Brevet de pilote d’hélicoptère »
  - EN : “Helicopter pilot wings”

### `fr-gign-tir-haute-precision` — Tir de haute précision (GIGN)

- **qualification « Formation initiale des opérationnels du GIGN » (`fr-gign-formation`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale des opérationnels du GIGN »
  - EN : “GIGN operator initial training”

### `fr-gign-effraction` — Effraction mécanique et à l’explosif (GIGN)

- **qualification « Formation initiale des opérationnels du GIGN » (`fr-gign-formation`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale des opérationnels du GIGN »
  - EN : “GIGN operator initial training”

### `fr-specialite-13rdp` — Formation spécialisée au 13e RDP (renseignement et SIC des forces spéciales)

- **qualification « Formation initiale du militaire du rang (CFIM) » (`fr-cfim`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale du militaire du rang (CFIM) »
  - EN : “Enlisted initial training (CFIM)”

### `fr-chef-de-bord-alat` — Qualification de chef de bord (hélicoptère de l’ALAT)

- **qualification « Formation de pilote d’hélicoptère de l’ALAT (EALAT) » (`fr-pilote-ealat`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de pilote d’hélicoptère de l’ALAT (EALAT) »
  - EN : “Army Light Aviation helicopter pilot training (EALAT)”

### `fr-technicien-simulateur-ealat` — Formation de technicien simulateur de vol (EALAT, Dax)

- **qualification « Formation initiale de sous-officier (ENSOA) » (`fr-ensoa`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale de sous-officier (ENSOA) »
  - EN : “NCO initial training (ENSOA)”

### `fr-gign-plongee` — Plongée d’intervention (GIGN)

- **qualification « Formation initiale des opérationnels du GIGN » (`fr-gign-formation`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale des opérationnels du GIGN »
  - EN : “GIGN operator initial training”

## DE

### `de-ksk-pfv` — Procédure d’évaluation du potentiel KSK (PFV)

- **ancienneté 730 jours, mesurée (dossier / arrivée)** — confiance 0.90, prérequis n° 1
  - FR : « Militaires du rang et sous-officiers : 32 ans au plus et au moins deux ans de service ; sous-officiers supérieurs : 35 ans au plus ; officiers : de Leutnant à Hauptmann (A11) »
  - EN : “Enlisted and junior NCOs: aged 32 or under with at least two years of service; senior NCOs: 35 or under; officers: Leutnant to Hauptmann (A11)”
- **qualification « Habilitation de sécurité (Sicherheitsüberprüfung Ü1 à Ü3) » (`de-sicherheitsueberpruefung`)** — confiance 0.90, prérequis n° 3
  - FR : « Habilitation de sécurité SÜ1 réalisée, SÜ2 engagée »
  - EN : “SÜ1 security clearance completed, SÜ2 initiated”

### `de-ksk-aufklaerungssoldat` — Formation de soldat de reconnaissance commando (KSK)

- **ancienneté 730 jours, mesurée (dossier / arrivée)** — confiance 0.85, prérequis n° 1
  - FR : « Deux ans de service préalable (militaires du rang et sous-officiers) »
  - EN : “Two years of prior service (enlisted and NCOs)”

### `de-ksk-rettungsspezialist` — Spécialiste du sauvetage KSK (niveau NSOMT)

- **qualification « Ambulancier d’urgence (Notfallsanitäter) » (`de-notfallsanitaeter`)** — confiance 0.90, prérequis n° 1
  - FR : « Pour l’accès comme Feldwebel : formation de Notfallsanitäter achevée »
  - EN : “For entry as a Feldwebel: completed Notfallsanitäter training”

### `de-fallschirmspringer` — Brevet de parachutiste de la Bundeswehr

- **5 saut(s) en parachute, mesurés par le mod** — confiance 0.80, description
  - Phrase : « The course certificate also certifies the Fallschirmspringerabzeichen special badge, awarded in bronze after 5 jumps, silver after 20 and gold after 50; wear is authorised once entered in the military parachutist logbook. »

### `de-tandemmaster` — Pilote de saut en tandem (Tandemmaster)

- **qualification « Chuteur opérationnel (chute libre militaire) » (`de-freifall`)** — confiance 0.90, prérequis n° 1
  - FR : « Chuteur opérationnel confirmé »
  - EN : “Experienced military free-fall jumper”

### `de-gleitfallschirm-truppfuehrer` — Chef d’équipe de chute libre sous voile (Truppführer)

- **150 saut(s) en chute libre, attestés par un instructeur** — confiance 0.90, prérequis n° 1
  - FR : « Au moins 150 sauts en chute libre avant le cours (Freifalltruppführer) »
  - EN : “At least 150 free-fall jumps before the course (free-fall team leader)”

### `de-combat-control-team` — Équipe de contrôle de combat (Combat Control Team)

- **qualification « Chuteur opérationnel (chute libre militaire) » (`de-freifall`)** — confiance 0.90, prérequis n° 1
  - FR : « Chuteur opérationnel (Freifalllehrgang) »
  - EN : “Military free-fall qualified (Freifalllehrgang)”

### `de-heereshochgebirgsspezialist` — Spécialiste de haute montagne de l’Heer

- **grade minimum OR-6 (feldwebel)** — confiance 0.85, prérequis n° 1
  - FR : « Grade de Feldwebel au minimum (selon le pack d’emplois) »
  - EN : “At least the rank of Feldwebel (per the job pack)”

### `de-scharfschuetze-gebirge` — Tireur d’élite en montagne

- **qualification « Tireur d’élite (Scharfschützenlehrgang) » (`de-scharfschuetze`)** — confiance 0.90, prérequis n° 1
  - FR : « Tireur d’élite (Scharfschützenlehrgang) »
  - EN : “Qualified sniper (Scharfschützenlehrgang)”

### `de-schiffstaucher` — Scaphandrier de la Marine (Schiffstaucher)

- **170 h de plongée, attestées par un instructeur** — confiance 0.85, prérequis n° 1
  - FR : « Examen Schwimmtaucher réussi et 170 heures de plongée »
  - EN : “Schwimmtaucher examination passed and 170 diving hours”

### `de-nh90-muster` — Qualification de type NH90

- **qualification « Formation de base de pilote d’hélicoptère » (`de-hubschrauberfuehrer`)** — confiance 0.90, prérequis n° 1
  - FR : « Hubschrauberführergrundausbildung »
  - EN : “Basic helicopter pilot training”

### `de-tiger-muster` — Qualification de type Tiger (centre franco-allemand)

- **qualification « Formation de base de pilote d’hélicoptère » (`de-hubschrauberfuehrer`)** — confiance 0.90, prérequis n° 1
  - FR : « Hubschrauberführergrundausbildung »
  - EN : “Basic helicopter pilot training”

### `de-personenschutz` — Garde du corps de la police militaire (Personenschützer Feldjäger)

- **qualification « Formation de Feldjäger (police militaire) » (`de-feldjaeger`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation complète de Feldjäger »
  - EN : “Full Feldjäger training”

### `de-lgan` — Cours d’état-major national (LGAN, Führungsakademie)

- **grade minimum OF-2 (capitaine)** — confiance 0.85, prérequis n° 1
  - FR : « Officier de carrière sélectionné (grade de capitaine/commandant) »
  - EN : “Selected career officer (captain/major rank)”

### `de-ksk-waffenspezialist` — Spécialiste armes et tactique du Kommandotrupp (KSK)

- **qualification « Formation de base commando KSK et insigne de commando » (`de-ksk-basisausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base commando KSK »
  - EN : “KSK commando basic training”

### `de-ksk-fernmeldespezialist` — Spécialiste transmissions du Kommandotrupp (KSK)

- **qualification « Formation de base commando KSK et insigne de commando » (`de-ksk-basisausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base commando KSK »
  - EN : “KSK commando basic training”

### `de-ksk-sanitaetsspezialist` — Spécialiste sanitaire du Kommandotrupp (KSK)

- **qualification « Formation de base commando KSK et insigne de commando » (`de-ksk-basisausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base commando KSK »
  - EN : “KSK commando basic training”

### `de-ksk-sprengspezialist` — Spécialiste explosifs et effraction du Kommandotrupp (KSK)

- **qualification « Formation de base commando KSK et insigne de commando » (`de-ksk-basisausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base commando KSK »
  - EN : “KSK commando basic training”

### `de-ksk-amphibisch` — Spécialisation opérations amphibies (KSK)

- **qualification « Formation de base commando KSK et insigne de commando » (`de-ksk-basisausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base commando KSK »
  - EN : “KSK commando basic training”

### `de-hochgebirgsjaeger` — Chasseur de haute montagne (Hochgebirgsjäger)

- **qualification « Formation de chasseur de montagne (ski et alpinisme militaires) » (`de-gebirgsjaeger-ausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de chasseur de montagne »
  - EN : “Mountain infantry training”

### `de-ksm-medic` — Spécialisation sanitaire du nageur de combat (Medic, KSM)

- **qualification « Nageur de combat (Kampfschwimmer) » (`de-kampfschwimmer`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de nageur de combat »
  - EN : “Combat swimmer training”

### `de-ksm-breacher` — Spécialisation effraction du nageur de combat (Breacher, KSM)

- **qualification « Nageur de combat (Kampfschwimmer) » (`de-kampfschwimmer`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de nageur de combat »
  - EN : “Combat swimmer training”

### `de-ksm-funker` — Spécialisation transmissions du nageur de combat (Funker, KSM)

- **qualification « Nageur de combat (Kampfschwimmer) » (`de-kampfschwimmer`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de nageur de combat »
  - EN : “Combat swimmer training”

### `de-infanterie-sga` — Formation de spécialité d’infanterie (Spezialgrundausbildung Infanterie)

- **qualification « Formation de base militaire générale (Allgemeine Grundausbildung) » (`de-grundausbildung`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base militaire générale »
  - EN : “General basic military training”

### `de-ch53-muster` — Qualification de type CH-53

- **qualification « Formation de base de pilote d’hélicoptère » (`de-hubschrauberfuehrer`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de base de pilote d’hélicoptère »
  - EN : “Basic helicopter pilot training”

## CN

### `cn-army-command-college-course` — Formation de commandement et d’état-major (Collège de commandement de l’Armée de terre)

- **qualification « Formation initiale d’officier (académies militaires de l’APL) » (`cn-officer-initial-education`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation initiale d’officier »
  - EN : “Initial officer education”

### `cn-ndu-senior-command` — Formation supérieure de commandement (Université de la défense nationale)

- **qualification « Formation de commandement et d’état-major (Collège de commandement de l’Armée de terre) » (`cn-army-command-college-course`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation de commandement et d’état-major »
  - EN : “Command and staff training”

### `cn-armoured-vehicle-commander` — Chef d’engin blindé (装甲车车长)

- **qualification « Conducteur de véhicule blindé amphibie (两栖装甲车驾驶员) » (`cn-amphibious-vehicle-driver`)** — confiance 0.90, prérequis n° 1
  - FR : « Conducteur de véhicule blindé amphibie »
  - EN : “Amphibious armoured vehicle driver”

### `cn-amphibious-recon` — Formation de reconnaissance amphibie (两栖侦察)

- **qualification « Formation amphibie du Corps des marines (两栖作战训练) » (`cn-amphibious-training`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation amphibie du Corps des marines »
  - EN : “Marine Corps amphibious training”

## RU

### `ru-parashyutist-instruktor` — Parachutiste-instructeur (парашютист-инструктор)

- **qualification « Instruction aéroportée avec sauts en parachute (воздушно-десантная подготовка, ВДП) » (`ru-vdp`)** — confiance 0.85, prérequis n° 1
  - FR : « Instruction aéroportée (ВДП) »
  - EN : “Airborne training (VDP)”

### `ru-parashyut-krylo` — Saut sous voilure aile (parachute PV-3)

- **qualification « Instruction aéroportée avec sauts en parachute (воздушно-десантная подготовка, ВДП) » (`ru-vdp`)** — confiance 0.85, prérequis n° 1
  - FR : « Instruction aéroportée (ВДП) »
  - EN : “Airborne training (VDP)”

### `ru-boevoy-plovets` — Nageur de combat (боевой пловец)

- **qualification « Formation à la plongée militaire (водолазная подготовка) » (`ru-vodolaznaya-podgotovka`)** — confiance 0.90, prérequis n° 1
  - FR : « Formation à la plongée militaire »
  - EN : “Military diving training”

## GB

### `gb-swimmer-canoeist` — Swimmer Canoeist (opérateur du SBS)

- **qualification « Sélection des forces spéciales britanniques (UKSF) » (`gb-uksf-selection`)** — confiance 0.85, prérequis n° 1
  - FR : « Réussite de la sélection UKSF »
  - EN : “Passed UKSF selection”

### `gb-psbc` — Cours de sous-officier adjoint de section (Platoon Sergeants' Battle Course, « Senior Brecon »)

- **qualification « Cours de chef de groupe (Section Commanders' Battle Course, « Junior Brecon ») » (`gb-scbc`)** — confiance 0.90, prérequis n° 2
  - FR : « Section Commanders' Battle Course »
  - EN : “Section Commanders' Battle Course”

### `gb-pcbc` — Cours de chef de section (Platoon Commanders' Battle Course)

- **qualification « Formation initiale d’officier de Sandhurst (Regular Commissioning Course) » (`gb-rmas-rcc`)** — confiance 0.90, prérequis n° 1
  - FR : « Officier d’infanterie nouvellement breveté (Regular Commissioning Course) »
  - EN : “Newly commissioned infantry officer (Regular Commissioning Course)”

### `gb-jwic` — Instructeur combat en jungle (Jungle Warfare Instructors Course)

- **grade minimum OR-4 (corporal)** — confiance 0.85, prérequis n° 1
  - FR : « Caporal à capitaine »
  - EN : “Corporal to Captain”

### `gb-sniper-section-commander` — Chef de groupe de tireurs d’élite (Sniper Section Commander's Course)

- **qualification « Tireur d’élite de l’Army (Sniper Operator Course) » (`gb-sniper`)** — confiance 0.90, prérequis n° 1
  - FR : « British Army Sniper Operator Course »
  - EN : “British Army Sniper Operator Course”
- **grade minimum OR-4 (OR4)** — confiance 0.85, prérequis n° 2
  - FR : « Caporal à sergent (OR4 à OR6) »
  - EN : “Corporal to Sergeant (OR4 to OR6)”

### `gb-rm-ml1` — Mountain Leader 1 des Royal Marines (ML1)

- **qualification « Mountain Leader des Royal Marines (ML2) » (`gb-rm-mountain-leader`)** — confiance 0.85, prérequis n° 1
  - FR : « Au moins deux ans d’expérience comme ML2 »
  - EN : “At least two years' experience as an ML2”

### `gb-rm-landing-craft` — Chalands de débarquement des Royal Marines (Landing Craft LC2)

- **grade minimum OR-4 (caporal)** — confiance 0.85, prérequis n° 1
  - FR : « Caporal ou candidat à la promotion au grade de caporal, qualifié et expérimenté au niveau LC3 »
  - EN : “Corporal or candidate for promotion to Corporal, qualified and experienced at LC3 level”

### `gb-acsc` — Cours supérieur d’état-major (Advanced Command and Staff Course)

- **grade minimum OF-3 (OF3)** — confiance 0.85, prérequis n° 1
  - FR : « Officier sélectionné (niveau commandant / OF3-OF4) »
  - EN : “Selected officer (Major / OF3-OF4 level)”

### `gb-icsc-land` — Cours intermédiaire d’état-major terre (ICSC(L))

- **grade minimum OF-3 (OF3)** — confiance 0.85, prérequis n° 1
  - FR : « Officier jusqu’au grade de major (OF3) »
  - EN : “Officer up to the rank of Major (OF3)”

### `gb-jttc` — Ciblage tactique interarmées (Joint Tactical Targeting Course)

- **grade minimum OR-4 (OR4)** — confiance 0.85, prérequis n° 1
  - FR : « Grade OR4 à OF4 »
  - EN : “Rank OR4 to OF4”

### `gb-army-pilots-course` — Cours de pilote de l’Army (Army Air Corps)

- **grade minimum OR-3 (lance corporal)** — confiance 0.85, prérequis n° 1
  - FR : « Officier ou sous-officier de l’Army (au moins caporal suppléant pour les sous-officiers, selon les réponses officielles « Ask a Soldier ») »
  - EN : “Army officer or NCO (at least Lance Corporal for NCOs, according to official 'Ask a Soldier' answers)”

## MY

### `my-sniper-gerak-khas` — Tireur d'élite des forces spéciales (Kursus Ahli Sniper Gerak Khas)

- **qualification « Cours de base commando (Kursus Asas Komando) » (`my-kursus-asas-komando`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base commando (Kursus Asas Komando) »
  - EN : “Basic Commando Course (Kursus Asas Komando)”

### `my-penyelia-sniper-gerak-khas` — Superviseur de tireurs d'élite des forces spéciales (Kursus Penyelia Sniper Gerak Khas)

- **qualification « Tireur d'élite des forces spéciales (Kursus Ahli Sniper Gerak Khas) » (`my-sniper-gerak-khas`)** — confiance 0.90, prérequis n° 1
  - FR : « Tireur d'élite qualifié (Kursus Ahli Sniper Gerak Khas) »
  - EN : “Qualified sniper (Kursus Ahli Sniper Gerak Khas)”

### `my-kursus-terjun-bebas` — Cours de chute libre (Kursus Terjun Bebas)

- **qualification « Cours de base de parachutisme (Kursus Asas Payung Terjun) » (`my-kursus-asas-payung-terjun`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base de parachutisme (Kursus Asas Payung Terjun) »
  - EN : “Basic Parachute Course (Kursus Asas Payung Terjun)”
- **21 saut(s) en chute libre, attestés par un instructeur** — confiance 0.80, description
  - Phrase : « The free fall parachutist badge requires the basic badge, this course and 21 free fall jumps, four of them with combat gear. »

### `my-kursus-ketua-penerjunan` — Chef largueur (Kursus Ketua Penerjunan)

- **qualification « Cours de base de parachutisme (Kursus Asas Payung Terjun) » (`my-kursus-asas-payung-terjun`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base de parachutisme (Kursus Asas Payung Terjun) »
  - EN : “Basic Parachute Course (Kursus Asas Payung Terjun)”
- **21 saut(s) en parachute, mesurés par le mod** — confiance 0.80, description
  - Phrase : « The matching badge requires the senior parachutist badge and 21 static-line jumps, 18 of them with combat gear. »

### `my-kursus-pandu-arah` — Cours d'éclaireur parachutiste (Kursus Pandu Arah, pathfinder)

- **qualification « Cours de base de parachutisme (Kursus Asas Payung Terjun) » (`my-kursus-asas-payung-terjun`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base de parachutisme (Kursus Asas Payung Terjun) »
  - EN : “Basic Parachute Course (Kursus Asas Payung Terjun)”

### `my-kursus-juruselam` — Cours de plongeur (Kursus Juruselam)

- **qualification « Cours de base commando (Kursus Asas Komando) » (`my-kursus-asas-komando`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base commando (Kursus Asas Komando) »
  - EN : “Basic Commando Course (Kursus Asas Komando)”

### `my-kursus-asas-letupan` — Cours de base explosifs (Kursus Asas Letupan)

- **qualification « Cours de base commando (Kursus Asas Komando) » (`my-kursus-asas-komando`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base commando (Kursus Asas Komando) »
  - EN : “Basic Commando Course (Kursus Asas Komando)”

### `my-kursus-letupan-khas` — Cours d'explosifs spéciaux (Kursus Letupan Khas)

- **qualification « Cours de base explosifs (Kursus Asas Letupan) » (`my-kursus-asas-letupan`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base explosifs (Kursus Asas Letupan) »
  - EN : “Basic Demolition Course (Kursus Asas Letupan)”

### `my-kursus-apac` — Cours de base de la force de déploiement rapide (Kursus Asas Pasukan Aturgerak Cepat, APAC)

- **qualification « Cours de base de parachutisme (Kursus Asas Payung Terjun) » (`my-kursus-asas-payung-terjun`)** — confiance 0.90, prérequis n° 1
  - FR : « Cours de base de parachutisme (Kursus Asas Payung Terjun) »
  - EN : “Basic Parachute Course (Kursus Asas Payung Terjun)”

### `my-kursus-asas-para-bebas-69-komando` — Chute libre de base 69 Komando (Kursus Asas Para Bebas 69 Komando) — police

- **qualification « Parachutisme tactique de la police (Kursus Taktikal Payung Terjun) » (`my-kursus-taktikal-payung-terjun-pdrm`)** — confiance 0.90, prérequis n° 1
  - FR : « Parachutisme tactique de la police (Kursus Taktikal Payung Terjun) »
  - EN : “Police Tactical Parachute Course (Kursus Taktikal Payung Terjun)”

### `my-kursus-asas-taktikal-despatcher` — Largueur tactique (Kursus Asas Taktikal Despatcher) — police

- **qualification « Parachutisme tactique de la police (Kursus Taktikal Payung Terjun) » (`my-kursus-taktikal-payung-terjun-pdrm`)** — confiance 0.90, prérequis n° 1
  - FR : « Parachutisme tactique de la police (Kursus Taktikal Payung Terjun) »
  - EN : “Police Tactical Parachute Course (Kursus Taktikal Payung Terjun)”

### `my-kursus-mtat` — Cours d'état-major interarmées (Kursus Maktab Turus Angkatan Tentera)

- **grade minimum OF-3 (major)** — confiance 0.85, prérequis n° 1
  - FR : « Grade de commandant (major) ou équivalent »
  - EN : “Rank of major or equivalent”

### `my-kursus-mpat` — Cours du Collège de défense (Kursus Maktab Pertahanan Angkatan Tentera)

- **grade minimum OF-4 (lieutenant colonel)** — confiance 0.85, prérequis n° 1
  - FR : « Grade de lieutenant-colonel ou équivalent »
  - EN : “Rank of lieutenant colonel or equivalent”

## NATO

### `nato-imed` — Prise en charge médicale des irradiés (I-MED)

- **qualification « Profil linguistique SLP 2222 « fonctionnel » (STANAG 6001) » (`nato-slp-2`)** — confiance 0.85, prérequis n° 2
  - FR : « Anglais SLP 2222 selon le STANAG 6001 »
  - EN : “English SLP 2222 under STANAG 6001”

### `nato-wit` — Équipe de renseignement sur les armes (WIT)

- **grade minimum OR-4 (OR-4)** — confiance 0.85, prérequis n° 1
  - FR : « Grade OR-4 à OR-8 ou OF-1 à OF-3 (ou équivalent civil) »
  - EN : “Rank OR-4 to OR-8 or OF-1 to OF-3 (or civilian equivalent)”

### `nato-attack-the-network` — Attaque des réseaux – niveau opératif (AtNOC)

- **grade minimum OR-8 (OR-8)** — confiance 0.85, prérequis n° 1
  - FR : « Officier OF-3 et plus, ou sous-officier OR-8 et plus (ou équivalent civil) »
  - EN : “Officer OF-3 and above, or NCO OR-8 and above (or civilian equivalent)”

### `nato-domex` — Exploitation de documents et de supports (DOMEX)

- **grade minimum OR-3 (OR-3)** — confiance 0.85, prérequis n° 2
  - FR : « Grade OR-3 à OR-9 ou OF-1 à OF-4 (ou équivalent) »
  - EN : “Rank OR-3 to OR-9 or OF-1 to OF-4 (or equivalent)”

### `nato-cimic-field-worker` — Opérateur CIMIC de terrain (NCFWC)

- **grade minimum OR-5 (OR-5)** — confiance 0.85, prérequis n° 1
  - FR : « Personnel CIMIC OR-5 à OF-4 ou équivalent civil »
  - EN : “CIMIC personnel OR-5 to OF-4 or civilian equivalent”

### `nato-cimic-staff-worker` — Officier d’état-major CIMIC (NCSWC)

- **grade minimum OR-5 (OR-5)** — confiance 0.85, prérequis n° 1
  - FR : « Personnel CIMIC OR-5 à OF-4, étudiant M-CMI ou équivalent civil »
  - EN : “CIMIC personnel OR-5 to OF-4, M-CMI student or civilian equivalent”

### `nato-bifec` — Cours d’exploitation de terrain de base (BIFEC) – C-IED COE

- **grade minimum OR-4 (OR-4)** — confiance 0.85, prérequis n° 2
  - FR : « Grade OR-4 à OR-8 ou OF-1 à OF-3 »
  - EN : “Rank OR-4 to OR-8 or OF-1 to OF-3”

### `nato-cbrn-consequence-management` — Cours de gestion des conséquences NRBC – JCBRN Defence COE

- **grade minimum OR-7 (OR-7)** — confiance 0.85, prérequis n° 1
  - FR : « Officier OF-3 à OF-5, sous-officier OR-7 à OR-9 ou civil équivalent »
  - EN : “Officer OF-3 to OF-5, NCO OR-7 to OR-9 or civilian equivalent”

### `nato-hpac` — Cours de prédiction et d’évaluation des risques NRBC (HPAC) – JCBRN Defence COE

- **grade minimum OR-5 (OR-5)** — confiance 0.85, prérequis n° 1
  - FR : « Sous-officier OR-5 à OR-9, officier OF-1 à OF-3 ou civil équivalent »
  - EN : “NCO OR-5 to OR-9, officer OF-1 to OF-3 or civilian equivalent”

### `nato-cimic-higher-command` — Cours CIMIC de haut niveau de l’OTAN (NCHCC) – CIMIC COE

- **grade minimum OF-3 (OF-3)** — confiance 0.85, prérequis n° 1
  - FR : « Grade OF-3 à OF-6 ou civil équivalent »
  - EN : “Rank OF-3 to OF-6 or civilian equivalent”

### `nato-cimic-functional-specialist` — Cours de spécialiste fonctionnel CIMIC de l’OTAN (NCFSC) – CIMIC COE

- **grade minimum OR-5 (OR-5)** — confiance 0.85, prérequis n° 1
  - FR : « Grade OR-5 à OF-4 ou civil équivalent »
  - EN : “Rank OR-5 to OF-4 or civilian equivalent”

### `nato-sfa-operators` — Cours d’opérateur d’assistance aux forces de sécurité (SFA) – NATO SFA COE

- **qualification « Cours à distance ADL 362 « Introduction à l’assistance aux forces de sécurité » – NATO SFA COE » (`nato-sfa-adl362`)** — confiance 0.85, prérequis n° 1
  - FR : « Cours ADL 362 Introduction to SFA »
  - EN : “ADL 362 Introduction to SFA course”

## Restent en suggestion (200)

Non écrits dans les catalogues : proposés non cochés dans l’encart « Prérequis détectés ».

| Entrée | Détection | Confiance | Phrase |
|---|---|---|---|
| `us-sfwottc` | grade OR-5 (nco) | 0.45 | NCO holding a CMF 18 MOS |
| `us-sfisc` | qualification `us-sf-18b` | 0.55 | Primary CMF 18 MOS (18B, C, D or E) |
| `us-sfisc` | seniority_days ≥ 730 | 0.60 | Two years on an SFOD-A or SFOD-B |
| `us-mffpc` | attestation freefall_jumps ≥ 30 | 0.50 | Four-week course at the Yuma school: one week of wind-tunnel and packing training, then about thirty HALO and HAHO jumps from 10,000 to 25,… |
| `us-mffpc` | qualification `cn-parachute-training` | 0.70 | Brevet de parachutiste (Basic Airborne Course) |
| `us-mffjm` | seniority_days ≥ 365 | 0.65 | At least one year as a free-fall parachutist and 50 military free-fall jumps |
| `us-atic` | qualification `de-freifall` | 0.70 | Experienced military free-fall parachutist |
| `us-rasp-1` | attestation fitness_test | 0.70 | Passed the pre-RASP fitness test |
| `us-rasp-1` | attestation fitness_test | 0.70 | Test physique pré-RASP réussi |
| `us-rasp-1` | attestation fitness_test | 0.45 | Sélection de huit semaines du 75e régiment de Rangers pour les militaires du rang et jeunes sous-officiers (E-1 à E-5) : une première phase… |
| `us-alc` | grade OR-5 (sergeant) | 0.65 | Step of Army NCO professional military education, taken at the rank of sergeant, that replaced the Basic NCO Course in 2008; it is required… |
| `us-slc` | grade OR-6 (staff sergeant) | 0.65 | Step of Army NCO professional military education taken at the rank of staff sergeant; it is required for promotion to sergeant first class. |
| `us-mlc` | grade OR-7 (sergeant first class) | 0.65 | Step of Army NCO professional military education taken at the rank of sergeant first class; it is required for promotion to master sergeant. |
| `us-smc` | grade OR-8 (master sergeant) | 0.70 | Master sergeant / first sergeant |
| `us-160-green-platoon` | attestation fitness_test | 0.70 | Passed the Army fitness test |
| `us-160-green-platoon` | attestation fitness_test | 0.70 | Test physique de l’Army réussi |
| `us-160-bmq` | grade OF-1 (officer) | 0.45 | Green Platoon (Officer Combat Skills) |
| `us-160-bmq` | qualification `us-army-pic` | 0.50 | Aviator qualified on the aircraft, preferably pilot in command (PIC) |
| `us-af-upt` | grade OF-1 (officer) | 0.45 | Air Force officer (OTS, USAFA or AFROTC) |
| `us-af-uct` | grade OF-1 (officer) | 0.45 | Air Force officer (OTS, USAFA or AFROTC) |
| `us-afsoc-aircrew-qual` | qualification `us-af-upt` | 0.55 | Initial specialty training (UPT, UCT or Special Missions Aviator) |
| `us-afsoc-aircrew-qual` | qualification `us-af-uct` | 0.55 | Initial specialty training (UPT, UCT or Special Missions Aviator) |
| `us-af-cro` | grade OF-1 (officer) | 0.45 | Air Force officer (OTS, USAFA or AFROTC) |
| `us-af-tacp-icc` | qualification `us-sere-c` | 0.55 | Airborne School and SERE |
| `us-marsoc-as` | qualification `us-marsoc-sof-level1` | 0.55 | Active-duty Marine screened by a MARSOC recruiter |
| `us-marsoc-itc` | qualification `us-marsoc-sof-level1` | 0.55 | Sélection MARSOC (A&S) réussie |
| `us-marsoc-mdioc` | qualification `us-sere-c` | 0.55 | SERE |
| `us-buds` | attestation fitness_test | 0.70 | Physical Screening Test (PST) |
| `us-buds` | attestation fitness_test | 0.70 | Test de sélection physique (PST) |
| `us-jtac-instructor` | qualification `us-jtac` | 0.55 | Qualified and current JTAC |
| `us-jtac-instructor` | qualification `nato-jtac` | 0.70 | JTAC qualifié et à jour |
| `us-army-cap` | attestation fitness_test | 0.45 | Multi-day assessment created in 2020 at Fort Knox to select lieutenant colonels for battalion command (Centralized Selection List): cogniti… |
| `us-af-ip-ep` | qualification `fr-chef-de-bord-alat` | 0.70 | Aircraft commander qualification |
| `us-af-swo-as` | grade OF-1 (officier) | 0.45 | Nomination officier |
| `us-af-swo-as` | attestation fitness_test | 0.45 | Processus de sélection des candidats officiers Special Tactics, Combat Rescue et TACP de l’Air Force : épreuves physiques et mentales, éval… |
| `us-air-assault` | attestation fitness_test | 0.70 | Marche de 12 miles avec sac en moins de trois heures |
| `us-rslc` | grade OR-5 (nco) | 0.45 | Officer or NCO assigned to a reconnaissance and surveillance unit |
| `us-sf-18a` | qualification `us-sere-c` | 0.55 | Earlier phases: orientation, language, small unit tactics and SERE |
| `us-sf-18a` | grade OF-2 (capitaine) | 0.70 | Cours de capitaine (Captains Career Course) terminé |
| `us-sf-18b` | qualification `us-sere-c` | 0.55 | Earlier phases: orientation, language, small unit tactics and SERE |
| `us-sf-18c` | qualification `us-sere-c` | 0.55 | Earlier phases: orientation, language, small unit tactics and SERE |
| `us-sf-18e` | qualification `us-sere-c` | 0.55 | Earlier phases: orientation, language, small unit tactics and SERE |
| `us-sf-sniper` | attestation marksmanship | 0.75 | Current weapons qualification |
| `us-sf-sniper` | attestation marksmanship | 0.75 | Qualification de tir à jour |
| `us-eib` | qualification `us-sf-medical-sergeant` | 0.55 | Infantry (CMF 11) or Special Forces (CMF 18) MOS, except 18D |
| `us-eib` | attestation marksmanship | 0.75 | Army Marksmanship Qualification Badge (Expert) on the rifle |
| `us-eib` | attestation fitness_test | 0.45 | Skills badge for infantry and Special Forces soldiers (except 18D): fitness test, expert rifle qualification, 12-mile march with about 70 l… |
| `us-eib` | attestation marksmanship | 0.75 | Qualification de tir « expert » de l’Army au fusil |
| `us-eib` | attestation fitness_test | 0.70 | Marche de 12 miles avec charge en moins de trois heures |
| `us-eib` | attestation fitness_test | 0.45 | Badge de compétences réservé aux fantassins et aux forces spéciales (sauf le 18D) : test physique, qualification « expert » au fusil, march… |
| `us-esb` | attestation fitness_test | 0.45 | Skills badge created in 2019 for soldiers eligible for neither the EIB nor the EFMB: warrior and mission-essential tasks chosen by the comm… |
| `us-esb` | attestation fitness_test | 0.70 | Marche de 12 miles et test ACFT réussis |
| `us-esb` | attestation fitness_test | 0.45 | Badge de compétences créé en 2019 pour les militaires qui ne peuvent prétendre ni à l’EIB ni à l’EFMB : tâches de combat et de spécialité c… |
| `us-efmb` | attestation marksmanship | 0.75 | Army Marksmanship Qualification Badge (Expert) on the M4, within the last 12 months |
| `us-efmb` | attestation marksmanship | 0.75 | Qualification de tir « expert » de l’Army au fusil M4, dans les 12 derniers mois |
| `us-efmb` | attestation fitness_test | 0.70 | Marche de 12 miles avec équipement en moins de trois heures |
| `us-efmb` | attestation fitness_test | 0.45 | Badge de compétences médicales parmi les plus sélectifs (7 % de réussite en 2017) : épreuve écrite, ateliers de combat notés (soins TCCC, é… |
| `us-army-marksman-expert` | attestation marksmanship | 0.75 | Expert score at the individual weapon qualification |
| `us-army-sniper` | attestation marksmanship | 0.75 | Army Marksmanship Qualification Badge (Expert) on the rifle |
| `us-army-sniper` | qualification `fr-tireur-elite` | 0.70 | Désigné par son unité pour un poste de tireur d’élite |
| `us-army-sniper` | attestation marksmanship | 0.75 | Qualification de tir « expert » de l’Army au fusil |
| `us-mg-bradley` | attestation marksmanship | 0.75 | Chef d’équipage ou tireur qualifié sur Bradley |
| `us-mg-stryker` | attestation marksmanship | 0.75 | Chef d’équipage ou tireur qualifié sur Stryker |
| `us-army-ierw` | grade OF-1 (officer) | 0.45 | Selected as a student aviator (officer or warrant officer) |
| `us-mcmap-green` | qualification `us-mcmap-tan` | 0.55 | Ceinture grise MCMAP |
| `us-mcmap-brown` | qualification `us-mcmap-tan` | 0.55 | Ceinture verte MCMAP |
| `us-mcmap-black` | qualification `us-mcmap-tan` | 0.55 | Ceinture marron MCMAP |
| `us-mcmap-mai` | qualification `us-mcmap-tan` | 0.55 | Ceinture verte MCMAP au moins |
| `us-usmc-brc` | attestation fitness_test | 0.70 | Test d’aptitude au premier jour : 8 tractions au moins, course de 3 miles en 22 min 30 et 500 m de nage en 20 min en treillis |
| `us-usmc-rifle-expert` | attestation marksmanship | 0.75 | Expert score at the annual rifle qualification |
| `us-usmc-smlc` | attestation fitness_test | 0.70 | First-class Physical Fitness Test (PFT) |
| `us-usmc-wmlc` | attestation fitness_test | 0.70 | First-class Physical Fitness Test (PFT) |
| `us-usmc-assault-climber` | attestation fitness_test | 0.70 | First-class Physical Fitness Test (PFT) |
| `us-navy-efmws` | attestation fitness_test | 0.70 | Passed the Marine Combat Fitness Test (CFT) |
| `us-navy-efmws` | attestation fitness_test | 0.45 | Qualification of sailors serving with Marines, mostly hospital corpsmen: one year in a Marine Corps unit, Marine Combat Fitness Test (CFT),… |
| `us-uscg-ast` | attestation fitness_test | 0.70 | Passed the entry physical test (swim, pull-ups, push-ups) |
| `us-uscg-ast` | attestation fitness_test | 0.70 | Test physique d’entrée réussi (nage, tractions, pompes) |
| `us-af-ift` | grade OF-1 (officier) | 0.45 | Officier de l’Air Force sélectionné pour le pilotage |
| `us-af-ift` | flight_hours ≥ 25 | 0.60 | Premier vol des futurs pilotes et officiers systèmes de combat de l’Air Force, à l’aéroport de Pueblo (Colorado) : jusqu’à 25 heures de vol… |
| `us-af-weapons-school` | grade OF-3 (major) | 0.70 | Selected by a major command board |
| `us-jtac-evaluator` | qualification `us-jtac-instructor` | 0.50 | JTAC Instructor recommended |
| `us-jcws` | grade OF-1 (officer) | 0.45 | Officer with JPME Phase I (e.g. CGSOC / ILE) |
| `fr-chuteur-operationnel` | attestation freefall_jumps ≥ 80 | 0.50 | It is awarded after the 13-week high-altitude operational jump course (A TAP 3 400 CHUTEUR OPS) at ETAP: about 80 jumps, delayed-opening ju… |
| `fr-sotgh` | qualification `fr-chuteur-operationnel` | 0.55 | Brevet de chuteur opérationnel ou qualification de parachutiste spécialisé |
| `fr-paraspe-marine` | qualification `my-kursus-terjun-bebas` | 0.50 | Same application conditions as the ETAP free-fall course |
| `fr-paraspe-marine` | qualification `de-freifall` | 0.70 | Conditions de candidature identiques à celles du stage de chuteur opérationnel de l’ETAP |
| `fr-paraspe-air` | qualification `my-kursus-terjun-bebas` | 0.50 | Same application conditions as the ETAP free-fall course |
| `fr-paraspe-air` | qualification `de-freifall` | 0.70 | Conditions de candidature identiques à celles du stage de chuteur opérationnel de l’ETAP |
| `fr-bam` | seniority_days ≥ 365 | 0.55 | Together with at least one year of mountain experience, it is required to enter the high mountain team leader course. |
| `fr-cehm` | seniority_days ≥ 365 | 0.65 | At least one year of mountain experience |
| `fr-chef-equipe-ra` | grade OR-6 (senior nco) | 0.50 | Lieutenant from a branch school or senior NCO |
| `fr-commando-parachutiste-air` | attestation fitness_test | 0.70 | Selection, medical and specialty physical tests |
| `fr-commando-parachutiste-air` | attestation fitness_test | 0.70 | Tests de sélection, médicaux et épreuves physiques de la spécialité |
| `fr-mecanicien-navigant-aae` | qualification `de-fluggeraetmechaniker` | 0.70 | Experience as an aircraft mechanic |
| `fr-gign-selection` | attestation fitness_test | 0.45 | Processus de sélection ouvert aux gendarmes volontaires : épreuves physiques, de tir, psychotechniques et entretiens, en plusieurs phases, … |
| `de-ksk-pfv` | grade OR-5 (ncos) | 0.45 | Enlisted and junior NCOs: aged 32 or under with at least two years of service; senior NCOs: 35 or under; officers: Leutnant to Hauptmann (A… |
| `de-ksk-pfv` | qualification `de-einzelkaempfer` | 0.50 | Fitness for lone-combatant training (Einzelkämpfertauglichkeit), parachuting and fast roping / speed rappelling, exercise ECG |
| `de-ksk-pfv` | attestation fitness_test | 0.70 | Minimum standards: 5 × 1,000 m in 4:30 down to 3:50, 7 pull-ups, 20 sit-ups and 22 push-ups in 30 s, 200 m swim in uniform in 7:30, 20 km m… |
| `de-ksk-pfv` | attestation fitness_test | 0.45 | A first one-week phase combines individual fitness tests with computer-based psychological and cognitive tests (higher cognitive standards … |
| `de-ksk-pfv` | attestation fitness_test | 0.70 | Normes minimales : 5 × 1 000 m en 4:30 à 3:50, 7 tractions, 20 relevés de buste et 22 pompes en 30 s, 200 m de nage en tenue en 7:30, march… |
| `de-ksk-basisausbildung` | qualification `de-ksk-pfv` | 0.55 | Successful completion of the KSK Potenzialfeststellungsverfahren (PFV) |
| `de-ksk-basisausbildung` | qualification `de-ksk-aufklaerungssoldat` | 0.55 | Successful completion of the KSK Potenzialfeststellungsverfahren (PFV) |
| `de-ksk-aufklaerungssoldat` | grade OR-5 (ncos) | 0.45 | Two years of prior service (enlisted and NCOs) |
| `de-ksk-aufklaerungssoldat` | attestation fitness_test | 0.70 | Minimum standards: 5 × 1,000 m in 5:10 down to 4:30, 15 sit-ups and 15 push-ups in 30 s, 200 m swim in uniform in 7:30 |
| `de-ksk-aufklaerungssoldat` | qualification `de-einzelkaempfer` | 0.50 | Fitness for lone-combatant training, parachuting and fast roping; SÜ1 completed, SÜ2 initiated |
| `de-ksk-aufklaerungssoldat` | attestation fitness_test | 0.70 | Normes minimales : 5 × 1 000 m en 5:10 à 4:30, 15 relevés de buste et 15 pompes en 30 s, 200 m de nage en tenue en 7:30 |
| `de-ksk-rettungsspezialist` | grade OR-6 (feldwebel) | 0.70 | For entry as a Feldwebel: completed Notfallsanitäter training |
| `de-ksk-rettungsspezialist` | qualification `de-einzelkaempfer` | 0.50 | Fitness for parachuting, lone-combatant training and fast roping / speed rappelling |
| `de-egb` | attestation fitness_test | 0.70 | EGB selection: 7 km march with 20 kg in at most 52 min, 200 m swim in clothes (per the job pack) |
| `de-egb` | attestation fitness_test | 0.70 | Sélection EGB : marche de 7 km avec 20 kg en 52 min au plus, 200 m de nage habillé (selon le pack d’emplois) |
| `de-ksm-pfv` | attestation fitness_test | 0.70 | Fitness test: 5,000 m in under 22 min, 1,000 m swim in 24 min, 60 s breath-hold, 30 m underwater swim, 8 pull-ups, 15 bench presses at 50 kg |
| `de-ksm-pfv` | qualification `de-ksk-pfv` | 0.55 | PFV I and modules 1 and 2 of the Schwimmtaucher course required before PFV II |
| `de-ksm-pfv` | attestation fitness_test | 0.45 | PFV I combines a fitness test with a diving fitness examination (Taucherverwendungsfähigkeit) at the naval medical institute in Kiel; |
| `de-ksm-pfv` | attestation fitness_test | 0.70 | Test sportif : 5 000 m en moins de 22 min, 1 000 m de nage en 24 min, 60 s d’apnée, 30 m de nage sous l’eau, 8 tractions, 15 développés cou… |
| `de-ksm-pfv` | attestation fitness_test | 0.45 | Le PFV I associe test sportif et visite d’aptitude à la plongée (Taucherverwendungsfähigkeit) à l’institut de médecine maritime de Kiel ; l… |
| `de-kampfschwimmer` | qualification `de-ksk-pfv` | 0.55 | Successful completion of KSM PFV I–III |
| `de-sbk-ausbildung` | attestation fitness_test | 0.70 | Fitness test (including 50 kg bench press, obstacle course) |
| `de-sbk-ausbildung` | attestation fitness_test | 0.45 | After a fitness test it includes a specialist navigation course of about six months (navigation, mathematics, meteorology), followed by hea… |
| `de-sbk-ausbildung` | attestation fitness_test | 0.70 | Test sportif (dont 50 kg au développé couché, parcours d’obstacles) |
| `de-sbk-ausbildung` | attestation fitness_test | 0.45 | Après un test sportif, il comprend un cours spécialisé de navigation d’environ six mois (navigation, bases mathématiques, météorologie), pu… |
| `de-somst` | qualification `de-notfallsanitaeter` | 0.55 | Qualified medical personnel (Notfallsanitäter or military doctor) |
| `de-freifall` | qualification `cn-parachute-training` | 0.70 | Brevet de parachutiste (Fallschirmspringerlehrgang) |
| `de-absetzer` | qualification `cn-parachute-training` | 0.70 | Brevet de parachutiste et expérience de saut confirmée |
| `de-einzelkaempfer` | attestation fitness_test | 0.70 | Test d’entrée : course de 7 km avec 20 kg en 52 min au plus, 100 m de nage habillé en 4 min, orientation de jour et de nuit |
| `de-notfallsanitaeter` | grade OR-6 (feldwebel) | 0.65 | The Bundeswehr trains Notfallsanitäter in the medical service Feldwebel career; the qualification is required, for example, for KSK rescue … |
| `de-bordeinsatz` | attestation fitness_test | 0.70 | Course de 5 km en moins de 25 min et 300 m de nage habillé en moins de 9 min (selon le pack d’emplois) |
| `de-sof-aviator` | grade OF-1 (officer) | 0.45 | Officer helicopter pilot |
| `de-diensthundefuehrer` | qualification `de-ksk-aufklaerungssoldat` | 0.55 | Preliminary assessment course (KSK special dog platoon, per the job pack) |
| `de-ksk-pionierspezialist` | grade OR-5 (unteroffizier) | 0.70 | Completed craft apprenticeship (Unteroffizier career) |
| `de-jtac-evaluator` | qualification `fr-jtac` | 0.70 | JTAC qualification and confirmed experience |
| `de-fallschirmsprunglehrer` | qualification `cn-parachute-training` | 0.70 | Brevet de parachutiste et expérience de saut |
| `de-ksm-medic` | qualification `cn-combat-diver` | 0.70 | Formation de nageur de combat |
| `de-ksm-breacher` | qualification `cn-combat-diver` | 0.70 | Formation de nageur de combat |
| `de-ksm-funker` | qualification `cn-combat-diver` | 0.70 | Formation de nageur de combat |
| `cn-army-command-college-course` | grade OF-1 (officer) | 0.45 | Initial officer education |
| `cn-sihui-instructor` | qualification `nato-national-sof-selection` | 0.70 | Special forces selection |
| `cn-special-reconnaissance` | qualification `nato-national-sof-selection` | 0.70 | Special forces selection |
| `cn-demolition` | qualification `nato-national-sof-selection` | 0.70 | Special forces selection |
| `cn-close-quarters-combat` | qualification `nato-national-sof-selection` | 0.70 | Special forces selection |
| `cn-army-top-ten-team-leader` | qualification `nato-national-sof-selection` | 0.70 | Special forces selection |
| `ru-kontrakt-otbor` | attestation fitness_test | 0.45 | Entry procedure into Ministry of Defence contract service: medical and psychological examination, physical tests and signature of a fixed-t… |
| `ru-kontrakt-otbor` | attestation fitness_test | 0.45 | Procédure d’entrée dans le service sous contrat du ministère de la Défense : examen médical et psychologique, épreuves physiques et signatu… |
| `ru-tssn-fsb-otbor` | attestation fitness_test | 0.45 | Selection for Directorates A (Alpha) and V (Vympel): age and height limits, physical tests, multi-round hand-to-hand bouts, medical and psy… |
| `ru-tssn-fsb-otbor` | attestation fitness_test | 0.45 | Sélection des directions « A » (Alpha) et « V » (Vympel) : limites d’âge et de taille, épreuves physiques, combat au corps à corps en plusi… |
| `gb-sfsg-selection` | qualification `gb-aacc` | 0.55 | Passed P Company, the All Arms Commando Course or the RAF Regiment pre-parachute selection (according to Wikipedia) |
| `gb-ranger-selection` | grade OF-1 (officer) | 0.45 | Serving British Army soldier or officer |
| `gb-ranger-selection` | attestation fitness_test | 0.70 | 15 km march with 25 kg (excluding water) passed on day one of the RQC(IA) |
| `gb-ranger-selection` | attestation fitness_test | 0.45 | Pathway into the Ranger Regiment, the British Army land special operations regiment: optional Ranger Preparation Course, initial assessment… |
| `gb-ranger-selection` | attestation fitness_test | 0.70 | Marche de 15 km avec 25 kg (hors eau) réussie le premier jour du RQC(IA) |
| `gb-ranger-selection` | attestation fitness_test | 0.45 | Parcours d’accès au Ranger Regiment, régiment d’opérations spéciales terrestres de la British Army : Ranger Preparation Course facultatif, … |
| `gb-pathfinder-selection` | grade OF-1 (officer) | 0.45 | Serving British Army soldier or officer |
| `gb-rm-commando-training` | attestation fitness_test | 0.45 | Elle s’achève par les Commando Tests, dont l’endurance course (73 minutes) et la marche de 30 miles à travers Dartmoor en huit heures ; la … |
| `gb-aacc` | attestation fitness_test | 0.70 | RM Basic Swimming Test: 40 m in clothing and 3 min treading water after entry from 3 m |
| `gb-aacc` | attestation fitness_test | 0.70 | Passed the Potential Commando Physical Assessment (PCPA) and individual training requirements in date |
| `gb-aacc` | attestation fitness_test | 0.70 | Test de natation RM : 40 m en tenue et 3 min de nage sur place après entrée depuis 3 m |
| `gb-aacc` | attestation fitness_test | 0.70 | Réussite de la Potential Commando Physical Assessment (PCPA) et tests individuels à jour |
| `gb-basic-parachute-course` | qualification `gb-aacc` | 0.55 | Passed P Company or the All Arms Commando Course depending on posting (according to Wikipedia) |
| `gb-scbc` | grade OR-3 (lance corporal) | 0.70 | Lance Corporal or Corporal of a rifle company |
| `gb-psbc` | grade OR-4 (corporal) | 0.70 | Corporal or Sergeant of a rifle company |
| `gb-mortar-mfc` | grade OF-1 (officer) | 0.45 | Officer or warrant officer destined to command a mortar platoon |
| `gb-javelin-platoon-commander` | grade OR-6 (snco) | 0.50 | Infantry officer or SNCO |
| `gb-rm-mountain-leader` | attestation fitness_test | 0.70 | Passed ML Selection (arduous physical tests) |
| `gb-rm-mountain-leader` | attestation fitness_test | 0.70 | Réussite de la sélection ML (épreuves physiques éprouvantes) |
| `gb-rm-ml1` | seniority_days ≥ 730 | 0.65 | At least two years' experience as an ML2 |
| `gb-rm-ml1` | grade OR-6 (senior nco) | 0.50 | Senior NCO (SNCO) |
| `gb-rm-landing-craft` | attestation fitness_test | 0.70 | Basic Swim Test, Royal Marines Fitness Test and LC2 entrance exam |
| `gb-rm-landing-craft` | attestation fitness_test | 0.70 | Test de natation de base, Royal Marines Fitness Test et examen d’entrée LC2 |
| `gb-rm-lc1` | grade OR-4 (corporal) | 0.70 | Sergeant or senior Corporal holding LC2 |
| `gb-rm-lc1` | qualification `gb-rm-landing-craft` | 0.55 | Sergeant or senior Corporal holding LC2 |
| `gb-rm-lcoqc` | attestation fitness_test | 0.70 | CP2 colour perception, Basic Swim Test and Royal Marines Fitness Test |
| `gb-rm-lcoqc` | attestation fitness_test | 0.70 | Perception des couleurs CP2, test de natation de base et Royal Marines Fitness Test |
| `gb-rmas-ccs` | grade OF-1 (officer) | 0.45 | Reserve officer candidate or special-entry candidate |
| `gb-jtac` | qualification `us-af-tacp-apprentice` | 0.35 | Service member designated for an air-to-ground fires control role (FST, TACP) |
| `gb-fst-assistant` | grade OR-4 (bombardier) | 0.70 | Royal Artillery Bombardier |
| `gb-cmt` | qualification `us-af-bmt` | 0.55 | Basic military training (13 weeks at Pirbright or Winchester, 23 weeks at Harrogate for junior soldiers) |
| `gb-military-driver` | qualification `us-af-bmt` | 0.70 | Basic military training completed |
| `gb-ammunition-technician` | qualification `us-af-bmt` | 0.70 | Basic military training; Ammunition Technician selection board at Kineton |
| `gb-iedd` | qualification `gb-ammunition-technician` | 0.55 | Qualified Ammunition Technician or Ammunition Technical Officer |
| `gb-cbrn` | qualification `nato-cbrn-live-agent` | 0.35 | Service member nominated by their unit for CBRN defence |
| `gb-cbrn-cell-controller` | qualification `nato-cbrn-warning-reporting` | 0.70 | Service member posted to a CBRN warning and reporting centre |
| `my-penyelia-sniper-gerak-khas` | qualification `fr-tireur-elite` | 0.70 | Tireur d'élite qualifié (Kursus Ahli Sniper Gerak Khas) |
| `my-kursus-asas-payung-terjun` | halo_jumps ≥ 8 | 0.60 | Static-line parachute course of the PULPAK parachute wing at Sungai Udang: ground training, tower week then jump week (generally eight jump… |
| `my-kursus-asas-paskau` | attestation fitness_test | 0.45 | Il comprend cinq phases (camp, jungle, marche de 160 km, mer, SERE) et donne le béret bleu PASKAU ; |
| `my-kursus-tempur-pegawai-muda` | grade OF-1 (officer) | 0.45 | Newly commissioned officer |
| `my-pegawai-kadet-alk-upnm` | attestation fitness_test | 0.45 | Cursus des élèves officiers de l'Université nationale de défense : études universitaires et formation militaire encadrée par l'ALK (tests p… |
| `nato-pecc` | qualification `nato-mimms` | 0.50 | Useful: basic knowledge of MIMMS or MASCAL procedures |
| `nato-wit` | qualification `us-navy-eod` | 0.35 | EOD, engineer, intelligence, military police, special operations or infantry background |
| `nato-eod-sot` | grade OF-1 (OF-1) | 0.55 | Rank OF-1 to OF-4, or senior staff NCO |
| `nato-eod-sot` | qualification `nato-slp-2` | 0.50 | English SLP 2222 desired under STANAG 6001 |
| `nato-hme-advanced` | qualification `us-navy-eod` | 0.35 | EOD qualification, WTI specialty or SOF membership |
| `nato-hme-advanced` | qualification `nato-slp-2` | 0.50 | English SLP 2222 desired under STANAG 6001 |
| `nato-maritime-iedd` | qualification `us-navy-eod` | 0.35 | EOD qualification |
| `nato-cbrn-warning-reporting` | qualification `nato-cbrn-live-agent` | 0.55 | CBRN staff responsible for a warning and reporting plan or organisation |
| `nato-mountain-leader` | qualification `nato-slp-2` | 0.50 | English SLP 2222 minimum, 3232 recommended, under STANAG 6001 |
| `nato-domex` | qualification `nato-wit` | 0.55 | Trained member of a field exploitation team (FET), a WIT or equivalent specialist |
| `nato-cimic-staff-worker` | qualification `nato-cimic-field-worker` | 0.50 | Recommended: CIMIC Field Worker Course (NCFWC) |
| `nato-bifec` | qualification `us-navy-eod` | 0.35 | Background in EOD, engineering, intelligence, military police, special operations or infantry |
| `nato-cimic-higher-command` | qualification `nato-cimic-staff-worker` | 0.55 | NCSWC or ADL modules 037, 143 and 169 |
| `nato-nso-legal-advisor` | qualification `de-sicherheitsueberpruefung` | 0.70 | National security clearance |
| `nato-sfa-strategic-advising` | qualification `nato-sfa-operators` | 0.55 | Practical SFA experience |
