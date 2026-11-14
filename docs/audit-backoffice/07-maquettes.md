# Document 7 : maquettes textuelles

Maquettes en texte des principales interfaces de l'architecture cible (document 3). Tout ce document est **PROPOSITION**. Les données affichées sont fictives. Les maquettes suivent les règles du shell actuel (barre latérale à gauche, barre supérieure, en-tête de page) et la règle « pas de surtitre au-dessus d'un titre » : chaque bloc a un titre, au besoin une phrase, jamais de petite étiquette au-dessus.

Conventions de dessin :

| Signe | Sens |
|---|---|
| `▾` / `▸` | Groupe ouvert / replié |
| `●` | Page active |
| `(3)` | Pastille de compteur |
| `↗` | Ouvre une autre application (Overwatch, portail SSE, extranet, Arsenal) |
| `[ Bouton ]` | Action |
| `│ Onglet │` | Onglet ; l'onglet actif est entre `▌ ▐`. Un onglet sans droit de lecture n'apparaît pas. |
| `Section › Page` en tête de maquette | Fil d'Ariane de la barre supérieure, pas un surtitre |

## 1. Menus par profil

### 1.1 Gestionnaire de communauté (`admin.organization`)

```
┌──────────────────────────────────┐
│ ATHENA · 1er RIMa                │
│ [ Rechercher…        Ctrl K ]    │
├──────────────────────────────────┤
│ ▾ Favoris                        │
│     Liste des membres            │
│     Agenda                       │
├──────────────────────────────────┤
│ ● Accueil                    (7) │
│ ▾ Membres                        │
│     Liste des membres            │
│     Ajouter un membre            │
│     Demandes RH              (4) │
│ ▾ Unités                         │
│     Organigramme                 │
│     Affectations             (2) │
│ ▾ Opérations                     │
│     Agenda                       │
│     Dossiers d'opération         │
│     Mur opérationnel             │
│     Situation tactique ↗         │
├──────────────────────────────────┤
│ ▸ Ressources humaines        (3) │
│ ▸ Roleplay                       │
│ ▸ Après-action et renseignement  │
│ ▸ Coopérations                   │
│ ▸ Terrain ATAK                   │
│ ▸ Équipement et dotation         │
│ ▸ Communauté                     │
├──────────────────────────────────┤
│ ▸ Configuration                  │
├──────────────────────────────────┤
│ ▸ Mon espace                     │
│   Portail ↗                      │
└──────────────────────────────────┘
```

Les groupes du niveau 1 sont ouverts, ceux des niveaux 2 et 3 repliés ; le groupe de la page active s'ouvre. Les pastilles viennent d'un seul calcul (lot 2.2).

### 1.2 Configuration dépliée (gestionnaire)

```
│ ▾ Configuration                  │
│   ▸ Organisation                 │
│   ▾ Référentiels                 │
│       Grades                     │
│       Emplois                    │
│       Qualifications et compét.  │
│       Décorations                │
│       Positions administratives  │
│       Organisation type          │
│       Identification             │
│       Disponibilité              │
│   ▸ Droits et accès              │
│     ATAK                         │
│   ▸ Intégrations                 │
│     Réglages RH                  │
```

### 1.3 Cadre RH (profil RH, sans droit global)

```
┌──────────────────────────────────┐
│ ATHENA · 1er RIMa                │
│ [ Rechercher…        Ctrl K ]    │
├──────────────────────────────────┤
│ ● Accueil                    (5) │
├──────────────────────────────────┤
│ ▾ Mon périmètre                  │
│   ▾ Membres                      │
│       Liste des membres          │
│       Ajouter un membre          │
│       Demandes RH            (4) │
│   ▾ Unités                       │
│       Affectations           (2) │
│   ▾ Ressources humaines          │
│       Recrutement            (1) │
│       Carrière                   │
│       Qualifications des membres │
│       Documents RH               │
│       Départs                    │
│       Discipline                 │
│       Qualité des fiches         │
│       Tableaux et exports        │
├──────────────────────────────────┤
│ ▸ Mon espace                     │
│   Portail ↗                      │
└──────────────────────────────────┘
```

Aujourd'hui ce profil voit le menu d'un simple membre (`back_office_sidebar.php:13-15`). Le contenu de « Mon périmètre » se calcule à partir de la déclaration unique des droits (lot 5a).

### 1.4 Chef d'unité (membre placé dans la chaîne de commandement)

```
┌──────────────────────────────────┐
│ ATHENA · 1er RIMa                │
├──────────────────────────────────┤
│ ● Accueil                    (2) │
├──────────────────────────────────┤
│ ▾ Mon périmètre                  │
│     2e section (espace d'unité)  │
│     Avis à donner            (2) │
├──────────────────────────────────┤
│ ▾ Mon espace                     │
│     Mon service              (2) │
│     Ma situation                 │
│     Ma liaison ATAK              │
│     Mes démarches                │
│     Boîte de réception           │
│ ▾ Terrain                        │
│     Overwatch ↗                  │
│     Événements               (1) │
│ ▾ Mon unité                      │
│     Mon unité                    │
│     Extranet d'unité ↗           │
│   Paramètres ↗                   │
└──────────────────────────────────┘
```

### 1.5 Membre (opérateur)

```
┌──────────────────────────────────┐
│ ATHENA · 1er RIMa                │
├──────────────────────────────────┤
│ ● Accueil                        │
│ ▾ Mon espace                     │
│     Mon service              (1) │
│     Ma situation                 │
│     Ma liaison ATAK              │
│     Mes démarches                │
│     Boîte de réception       (3) │
│ ▾ Terrain                        │
│     Overwatch ↗                  │
│     Événements               (1) │
│ ▾ Mon unité                      │
│     Mon unité                    │
│     Extranet d'unité ↗           │
│   Paramètres ↗                   │
└──────────────────────────────────┘
```

## 2. Accueil

### 2.1 Gestionnaire

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Accueil · Bonjour Cne Martin                         [ Publier ] [ Personnaliser ]       │
│ 48 membres actifs sur 52 · prochaine activité dans 2 jours · 7 éléments à traiter        │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Démarrage de la communauté : 6 points sur 8                          [ Continuer ] [ ✕ ] │
├───────────────────────────────────────────────┬──────────────────────────────────────────┤
│ À traiter                         [ Tout voir ]│ Prochaine opération                      │
│                                               │ OP NOIR LYNX · sam. 21:00 · Altis        │
│ Filtre : [ Tout ▾ ]                           │ ▓▓▓▓▓▓▓▓▓░░░  31 oui · 6 peut-être       │
│ ● Candidature · Sgt Leroy (dépôt il y a 2 j) │            4 non · 9 sans réponse        │
│   [ Ouvrir ]                                  │ [ Relancer ] [ Ouvrir le dossier ]       │
│ ● Élévation · Cpl Diaz → Sergent             │                                          │
│   avis du chef : favorable    [ Décider ]     ├──────────────────────────────────────────┤
│ ● Correction · Sdt Roux, changement d'unité  │ Agenda                                   │
│   [ Décider ]                                 │ mar. 20:30 · Entraînement CQB            │
│ ● Signalement · message du 12/11 [ Traiter ] │ jeu. 21:00 · Formation radio             │
│ ● 3 dossiers à compléter          [ Voir ]    │ [ Calendrier ]                           │
│ ● 2 membres sans unité            [ Affecter ]│                                          │
├───────────────────────────────────────────────┴──────────────────────────────────────────┤
│ ▸ Chiffres clés   ▸ Participation   ▸ État des unités   ▸ Connexions   ▸ Ce qui a bougé  │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

- « À traiter » remplace « À décider », « Tâches en attente » et « À traiter et alertes » (RD-14).
- Les blocs hebdomadaires sont repliés et ne sont calculés qu'à l'ouverture.
- « Publier » ouvre Communauté › Publier (et non plus le formulaire bandeau).

### 2.2 Cadre RH

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Accueil · Bonjour Adj Ferrand                                                            │
│ 5 éléments à traiter dans votre périmètre                                                │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ À traiter                                                                    [ Tout voir ]│
│ ● Correction · Sdt Roux, changement d'unité                              [ Décider ]     │
│ ● Absence · Cpl Diaz, 15-22/11                                           [ Valider ]     │
│ ● Élévation · Cpl Diaz → Sergent · niveau d'accès demandé                                │
│   En attente d'un responsable des accès (vous pouvez donner un avis)    [ Avis ]         │
│ ● 3 dossiers à compléter                                                 [ Voir ]        │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Arrivées récentes                                                                        │
│ Sdt Morel · 2e section · intégration 3/5 étapes                          [ Ouvrir ]      │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

La ligne d'élévation illustre la correction S4 : le profil RH ne décide plus seul d'une demande qui accorde un niveau d'accès.

## 3. Fiche membre centrale

### 3.1 Synthèse

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Membres › Liste des membres › Sgt « Viper » Durand                                       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ ┌──────┐  Sgt « Viper » Durand                                       [ Demander… ▾ ]     │
│ │photo │  Chef de groupe · 2e section, 1re compagnie                  [ Plus ▾ ]          │
│ └──────┘  Matricule 1RIMA-0142 · indicatif VIPER 2-1                                     │
│           En service actif · compte actif · dossier complet à 92 %                       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│▌Synthèse▐│Identité et papiers│Affectation et poste│Carrière│Qualifications et distinctions│
│ │Accès et habilitations│Position et statut│Suivi RH│Équipement│Terrain ATAK│Roleplay│    │
│ │Historique│                                                                             │
├───────────────────────────────────────────────┬──────────────────────────────────────────┤
│ À traiter pour ce membre                      │ Place dans l'organisation                │
│ ● Qualification « Tireur de précision »       │ Poste : Chef de groupe (2e sect.)        │
│   expire le 30/11                 [ Voir ]    │ Emploi attendu : Chef de groupe ✓        │
│ ● Papier « Carte d'identité » à renouveler    │ Grade attendu : Sergent ✓                │
├───────────────────────────────────────────────┤ Chaîne : Ltn Robert → Adj Ferrand        │
│ Intégration                                   ├──────────────────────────────────────────┤
│ Terminée le 02/09 · référent Sgt Leroy        │ Présence (90 j) : 84 %                   │
│                                               │ Dernière connexion : hier 22:14          │
│                                               │ [ Voir la fiche publique ↗ ]             │
└───────────────────────────────────────────────┴──────────────────────────────────────────┘
```

### 3.2 Affectation et poste

```
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Synthèse │…│▌Affectation et poste▐│ Carrière │ …                                         │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Unité            [ 2e section, 1re compagnie            ▾ ]                              │
│ Poste tenu       [ Chef de groupe — groupe 2 (vacant)   ▾ ]  postes vacants de l'unité   │
│ Intitulé         [ Chef du groupe Viper                   ]                              │
│ Emplois          [x] Chef de groupe (principal)  [x] Radio  [ + Ajouter ]                │
│                  ⚠ Radio : prérequis « Formation radio » manquant · [ Dérogation ]       │
│                                                          [ Annuler ] [ Enregistrer ]     │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Chaîne de commandement                                                                   │
│ Ltn Robert (chef de section) → Sgt Durand → 8 subordonnés           [ Voir l'unité ]     │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Derniers mouvements                                                                      │
│ 02/09 · affecté 2e section (intégration terminée)                                        │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

Unité, poste tenu, intitulé et emplois restent quatre champs distincts (glossaire, document 3 §2) mais un seul enregistrement, par le service unique d'affectation (lot 4.1).

### 3.3 Accès et habilitations

```
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ … │▌Accès et habilitations▐│ Position et statut │ …                                       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Niveaux d'accès                                                   [ + Ajouter un niveau ]│
│ Niveau        Périmètre             Depuis      Jusqu'au    Accordé par                  │
│ Membre        Toute la communauté   12/03/2025  —           Système                      │
│ RH adjoint    2e section            01/10/2026  31/12/2026  Cne Martin   [ Gérer ▾ ]     │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Droits individuels                                                [ + Ajouter un droit ] │
│ + personnel.callsign.manage   Toute la communauté   accordé le 03/10 (élévation n° 41)   │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Habilitations                                                                            │
│ Renseignement : CONFIDENTIEL · code émis le 05/10           [ Révoquer ]                 │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Droits effectifs (calculés)                                       [ Rechercher un droit ]│
│ Effectifs : voir, modifier les profils (2e section)                                      │
│ Identification : gérer les indicatifs                                                    │
│ … 23 droits                                                       [ Tout afficher ]      │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

La position administrative n'apparaît pas ici : elle est dans l'onglet « Position et statut » (elle ne donne aucun droit, 02 §3.3).

### 3.4 Historique

```
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ … │▌Historique▐│                                                                          │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Filtrer : [x] Grade [x] Affectation [x] Droits [x] Papiers [x] Qualifications            │
│           [x] Distinctions [x] Discipline [x] Position [ ] Journal d'audit complet        │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ 03/10/2026  Droits         + personnel.callsign.manage (élévation n° 41, Cne Martin)     │
│ 01/10/2026  Droits         + RH adjoint, 2e section, jusqu'au 31/12 (Cne Martin)          │
│ 15/09/2026  Grade          Caporal-chef → Sergent (campagne « Automne 2026 »)            │
│ 02/09/2026  Position       En formation → En service actif                               │
│ 02/09/2026  Affectation    Centre d'instruction → 2e section                             │
│ 20/08/2026  Distinction    Médaille de la défense nationale, échelon bronze              │
│ …                                                                     [ Plus ancien ]    │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

## 4. Espace d'unité

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Unités › Organigramme › 2e section                                                       │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ 2e section · 1re compagnie                                   [ Modifier ] [ Extranet ↗ ] │
│ Chef : Ltn Robert · adjoint : Adj Ferrand · 24 membres · disponibilité 78 %              │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│▌Vue d'ensemble▐│ Membres │ Postes │ Chaîne │ Équipes │ Qualifications │ Dotation type │  │
│ │ Opérations │ Identification │                                                         │
├───────────────────────────────────────────────┬──────────────────────────────────────────┤
│ Postes                                        │ Alertes                                  │
│ 22 occupés · 3 vacants                        │ ● Groupe 3 sans chef                     │
│ ● Chef de groupe 3 — vacant  [ Pourvoir ]     │ ● 4 qualifications expirent ce mois      │
│ ● Tireur d'élite — vacant    [ Pourvoir ]     │ ● 2 membres sans emploi principal        │
│ ● Infirmier — vacant         [ Pourvoir ]     │                                          │
├───────────────────────────────────────────────┼──────────────────────────────────────────┤
│ Sous-unités                                   │ Prochaines opérations                    │
│ Groupe 1 (8) · Groupe 2 (8) · Groupe 3 (6)    │ OP NOIR LYNX · sam. · 18 inscrits        │
└───────────────────────────────────────────────┴──────────────────────────────────────────┘
```

« Pourvoir » ouvre la liste des membres de l'unité qui ont l'emploi attendu ; le choix met à jour le poste et l'affectation ensemble (lot 3.4).

## 5. Dossier d'opération

### 5.1 Synthèse

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Opérations › Dossiers d'opération › OP NOIR LYNX                                         │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ OP NOIR LYNX · TF DAGGER · code NL-26-11                     État : Publié               │
│ 21/11/2026 21:00 (DTG 212100ZNOV26) · carte Altis                                        │
│                                        [ Passer en live ] [ Paquet PDF ] [ Plus ▾ ]      │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│▌Synthèse▐│Créneau et inscriptions│Organisation et postes│Briefing│Conditions de jeu│     │
│ │Exécution│Après-action│Renseignement│Publication│                                      │
├───────────────────────────────────────────────┬──────────────────────────────────────────┤
│ Préparation                                   │ Liens                                    │
│ [x] Créneau créé (31 oui)                     │ Événement : ✓ lié                        │
│ [x] Organisation : 34 postes, 31 pourvus      │ Espace opérationnel ↗ : ✓ lié            │
│ [x] Briefing : 12 diapositives, 3 documents   │ Plan TOC ↗ : ✓ lié                       │
│ [ ] Consignes et matériel conseillé à relire  │ Cycle de mission : s'ouvrira au live     │
│ [x] Conditions de jeu vérifiées               │ Mur opérationnel : fiche publiée         │
│ [ ] Publication JNET                          │                                          │
└───────────────────────────────────────────────┴──────────────────────────────────────────┘
```

### 5.2 Organisation et postes

```
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ … │▌Organisation et postes▐│ …                                                          │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ [ Importer depuis l'organigramme ] [ Importer les inscrits ] [ + Groupe opérationnel ]   │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ ▾ HQ TF DAGGER (4/4)                                                                     │
│     Chef TF ............ Cne Martin                                                      │
│     Adjoint ............ Ltn Robert                                                      │
│ ▾ Groupe Viper (8/9)                                                                     │
│     Chef de groupe ..... Sgt Durand                                                      │
│     Radio .............. Cpl Diaz                                                        │
│     Infirmier .......... — vacant —          [ Affecter ▾ ] 3 inscrits qualifiés         │
│ ▸ Groupe Cobra (8/8)                                                                     │
│ ▸ Appui (5/6)                                                                            │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Prévu / détecté en jeu : disponible après le passage en live                             │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

Les groupes opérationnels appartiennent à l'opération ; les unités permanentes de l'organigramme ne sont pas modifiées.

### 5.3 Après-action

```
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ … │▌Après-action▐│ …                                                                    │
├───────────────────────────────────────────────┬──────────────────────────────────────────┤
│ CR des joueurs                                │ Compte rendu officiel (AAR)              │
│ Imposé à 31 · remis 24 · lus 20               │ Brouillon créé à la clôture              │
│ [ Relancer les 7 ] [ Voir les CR ]            │ 5 actions correctives, 2 faites          │
├───────────────────────────────────────────────┤ [ Ouvrir l'AAR ] [ PDF ]                 │
│ Partie jouée                                  ├──────────────────────────────────────────┤
│ Session Arma du 21/11 · rattachée             │ Retour d'expérience de coopération       │
│ [ Relecture ↗ ]                               │ non applicable                           │
└───────────────────────────────────────────────┴──────────────────────────────────────────┘
```

## 6. Configuration ATAK unifiée

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Configuration › ATAK                                                                     │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Configuration ATAK                                        [ Voir l'état en jeu ↗ ]       │
│ Chaque réglage n'existe qu'ici. L'état en jeu se lit dans Terrain ATAK › Supervision.    │
├─────────────────────────────┬────────────────────────────────────────────────────────────┤
│ Démarrage            7/9    │ Réalisme et réseau                                         │
│ Connexion et serveur  ✓     │                                                            │
│ Expérience et règles  ✓     │ Version en vigueur : n° 14 du 10/11 (Cne Martin)           │
│ ● Réalisme et réseau        │ [ Historique ] [ Restaurer… ] [ Vérifier ]                 │
│ Chiffrement                 │                                                            │
│ Cartes et terrain     ⚠     │ Simulation réseau                                          │
│ Symboles et marqueurs       │ Latence de base      [ 120 ] ms                            │
│ Mods et serveurs            │ Perte de paquets     [   2 ] %                             │
│ Diffusion des rapports      │ Coupures aléatoires  [x] activées · toutes les [ 15 ] min  │
│ Consignes et matériel       │ [ Adopter les conditions réseau mesurées ]                 │
│ Bandeau photo terrain       │                                                            │
│ Données                     │ Relais radio                                               │
│                             │ Liaison par relais   [x] obligatoire hors portée directe   │
│                             │                                                            │
│                             │ Zones roleplay                                             │
│                             │ 3 zones · [ Modifier sur la carte ]                        │
│                             │                                                            │
│                             │ Chiffrement du renseignement                               │
│                             │ Domaine par défaut : 1RIMA-INT · [ Gérer les domaines ]    │
│                             │                                                            │
│                             │ Remise à zéro                                              │
│                             │ [ Remettre les réglages par défaut… ] (confirmation)       │
│                             │                                  [ Enregistrer la version ]│
└─────────────────────────────┴────────────────────────────────────────────────────────────┘
```

- Une seule source par réglage : la version enregistrée ici est celle que lit le jeu (lot 3.5, à confirmer avec le mod).
- « Remettre les réglages par défaut » est un formulaire POST avec confirmation (correction S3).
- `⚠` sur Cartes et terrain : une carte personnalisée sans tuiles, par exemple.

### 6.1 Section Cartes et terrain

```
├─────────────────────────────┬────────────────────────────────────────────────────────────┤
│ …                           │ Cartes et terrain                                          │
│ ● Cartes et terrain   ⚠     │                                                            │
│ …                           │ Carte par défaut   [ Altis                ▾ ]              │
│                             │                                                            │
│                             │ Cartes disponibles                                         │
│                             │ Carte        Tuiles      Relief (MNT)   Source             │
│                             │ Altis        ✓ 6 niv.    ✓              catalogue          │
│                             │ Stratis      ✓ 5 niv.    ✓              catalogue          │
│                             │ Kunduz       ✗           ✓ (mod)        carte perso   ⚠    │
│                             │ [ + Ajouter une carte personnalisée ]                      │
│                             │                                                            │
│                             │ Passerelles avec d'autres communautés                      │
│                             │ Aucune · [ Créer ] [ Rejoindre ]                           │
└─────────────────────────────┴────────────────────────────────────────────────────────────┘
```

La production des tuiles reste un outil en ligne de commande (`tools/map_stitch.py`) ; la page montre seulement ce qui existe.

## 7. Demandes RH (file unique)

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│ Membres › Demandes RH                                                                    │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Demandes RH                                                                              │
│ Type [ Tous ▾ ]  État [ À décider ▾ ]  Unité [ Toutes ▾ ]  [ Rechercher… ]               │
├──────────────────────────────────────────────────────────────────────────────────────────┤
│ Type         Membre          Demande                     Avis du circuit   Action        │
│ Élévation    Cpl Diaz        Sergent + niveau RH adjoint  chef : favorable  [ Décider ]  │
│ Correction   Sdt Roux        2e → 3e section              —                 [ Décider ]  │
│ Mobilité     Sgt Leroy       vers l'appui                 chef : en attente [ Voir ]     │
│ Absence      Cpl Diaz        15-22/11                     —                 [ Valider ]  │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

Les anciennes adresses (`EFF/elevations`, `BO/personnel/corrections`, `EFF/mobilite`) ouvrent cette page avec le filtre de type correspondant. Les droits de décision restent ceux de chaque type.
