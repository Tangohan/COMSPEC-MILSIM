# Tutoriel — Diapositives de briefing (écrans Eden)

Ce guide explique comment préparer, en éditeur Eden, un **grand écran** (interaction) et un **écran de salle de briefing** (affichage mural), pour que les joueurs consultent les diapositives publiées depuis Athena.

Les scripts ci-dessous sont ceux recommandés par le mod COMSPEC Overwatch (identiques à l’aide « Afficher dans Arma » du back-office Athena).

---

## Objectif

Afficher les **diapositives de briefing** de votre communauté :

1. **Sur un grand écran** : le joueur s’approche, choisit « Consulter le briefing », et ouvre le tableau plein écran (navigation Précédente / Suivante).
2. **Sur un écran de salle de briefing** : la première diapositive visible en jeu apparaît **directement sur l’objet** (comme un vrai panneau mural).

Les deux objets se complètent souvent dans une même salle : l’un sert de point d’interaction, l’autre projette l’image.

---

## Prérequis

- Mod **COMSPEC Overwatch** chargé dans la mission (et côté client pour tester).
- Au moins une diapositive **Visible en jeu** dans Athena (voir [Définir les diapositives](#définir--changer-les-diapositives-athena)).
- Connexion plateforme / extension opérationnelle (sinon le jeu indiquera qu’aucune diapositive n’est disponible).

---

## Vue d’ensemble Eden

| Rôle | Objet Eden typique | Nom de variable | Champ Init |
|------|--------------------|-----------------|------------|
| Interaction (menu) | Grand écran renforcé (noir) — ou tout autre objet | *facultatif* | Script « Consulter le briefing » |
| Affichage mural | Écran de salle de briefing | **obligatoire** (ex. `briefingScreen1`) | Script texture |

Vous pouvez aussi mettre **les deux scripts sur le même objet** si un seul écran doit à la fois afficher l’image et proposer le menu.

---

## Étape 1 — Placer l’écran d’affichage

1. Dans Eden, placez un **Écran de salle de briefing**  
   (*Choses → Électronique → Écran de salle de briefing*).
2. Ouvrez ses propriétés (**double-clic**).
3. Dans **Nom de la variable**, saisissez exactement : `briefingScreen1`  
   (vous pouvez choisir un autre nom, mais il devra être **identique** dans le script Init).
4. Collez le script suivant dans le champ **Init** :

```sqf
this setVariable ["comspec_briefingScreenIndex", 0];
[briefingScreen1, 0] spawn {
    params ["_obj", "_selIdx"];
    waitUntil { !isNull _obj };
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    if (count _slides == 0) then { _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides; };
    if (count _slides > 0) then {
        private _path = [_slides select 0] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
        if (_path != "") then { _obj setObjectTexture [_selIdx, _path]; };
    };
};
```

5. Validez avec **OK**.

### Remplacer le nom de variable

Si votre écran s’appelle par exemple `briefingScreenOps` :

- champ **Nom de la variable** : `briefingScreenOps`
- dans le script, remplacez **les deux** occurrences de `briefingScreen1` par `briefingScreenOps`  
  (uniquement la ligne `[briefingScreenOps, 0] spawn {` — le reste reste inchangé).

### Indice d’affichage (`0`)

Le `0` (après la virgule, et dans `comspec_briefingScreenIndex`) désigne la **face texturable** du modèle.  
Pour les écrans vanilla courants (grand écran renforcé, écran de salle de briefing), **`0` convient en général**.  
Si l’image n’apparaît pas sur le bon panneau, essayez `1` (ou vérifiez le modèle avec la console développeur).

---

## Étape 2 — Placer le grand écran (interaction)

1. Placez un **Grand écran renforcé (noir)** (ou tout objet sur lequel les joueurs pourront interagir).
2. Ouvrez ses propriétés.
3. **Nom de la variable** : facultatif pour ce rôle.
4. Collez **uniquement** ceci dans **Init** :

```sqf
this addAction ["Consulter le briefing", { [] call comspec_overwatch_connect_fnc_openBriefingBoard; }];
```

5. Validez avec **OK**.

En jeu, s’approcher de l’objet et utiliser le menu d’actions affichera **Consulter le briefing**.

### Variante — un seul objet (interaction + texture)

Si vous n’avez qu’un écran nommé `briefingScreen1` et qu’il doit faire les deux :

```sqf
this addAction ["Consulter le briefing", { [] call comspec_overwatch_connect_fnc_openBriefingBoard; }];
this setVariable ["comspec_briefingScreenIndex", 0];
[briefingScreen1, 0] spawn {
    params ["_obj", "_selIdx"];
    waitUntil { !isNull _obj };
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    if (count _slides == 0) then { _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides; };
    if (count _slides > 0) then {
        private _path = [_slides select 0] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
        if (_path != "") then { _obj setObjectTexture [_selIdx, _path]; };
    };
};
```

Le **Nom de la variable** de cet objet doit alors être `briefingScreen1`.

---

## Définir / changer les diapositives (Athena)

Les images ne se collent **pas** dans Eden : elles sont gérées côté plateforme.

1. Connectez-vous à Athena (compte avec droits d’administration de la communauté).
2. Ouvrez **Diapositives de briefing**  
   (back-office tactique / ATAK — entrée *Diapositives de briefing*).
3. Pour chaque image :
   - **Titre affiché** (légende dans le tableau en jeu)
   - **Ordre d’affichage** (du plus petit au plus grand : 0, 1, 2…)
   - Case **Visible en jeu** cochée pour la publier aux joueurs
   - Image **JPG** (recommandé) ou PNG, max. 12 Mo (redimensionnée automatiquement)
4. Enregistrez.

Les diapositives en brouillon (non visibles en jeu) restent masquées pour Arma.

Après une modification Athena, en jeu utilisez le bouton **Actualiser** du tableau de briefing, ou rouvrez le menu.

### Option Google Slides (fragile)

Sur la même page Athena, section **Présentation Google Slides** : collez un lien de présentation partagée avec toute personne disposant du lien. En jeu, tablette → Briefing → charger ce lien (ou coller un autre lien).

Cette fonction télécharge les images via l’extension Overwatch. Elle **dépend de Google** et peut casser sans préavis. Pour les briefs critiques, restez sur les diapositives images Athena.

En Eden, enregistrez les écrans avec :

```sqf
[[briefingScreen1, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;
```

---

## Contrôle en jeu

### Sans objet Eden

Le mod ajoute déjà au joueur l’action **Tableau de briefing** (menu d’actions).  
Elle ouvre le même tableau plein écran.

### Depuis le grand écran (Init étape 2)

- Action locale : **Consulter le briefing**

### Dans le tableau plein écran

| Contrôle | Effet |
|----------|--------|
| **Précédente** / **Suivante** | Naviguer dans les diapositives |
| **Actualiser** | Relire la liste depuis Athena |
| **Fermer** | Quitter le tableau |

### Sur l’écran mural (Init étape 1)

Au démarrage de la mission, l’objet affiche la **première** diapositive visible (ordre le plus bas).  
La navigation multi-pages se fait via le tableau plein écran (action grand écran ou menu joueur), pas en cliquant sur le mur.

---

## Dépannage courant

| Symptôme | Piste |
|----------|--------|
| Message « Aucune diapositive de briefing disponible » | Aucune image « Visible en jeu » côté Athena, ou extension / connexion plateforme indisponible. |
| « Impossible de charger cette diapositive » | Problème réseau ou cache image ; réessayez **Actualiser**, vérifiez le fichier côté Athena. |
| Menu « Consulter le briefing » absent | Init du grand écran non collé, ou objet trop loin ; le menu joueur **Tableau de briefing** reste disponible. |
| Écran mural noir / texture absente | Nom de variable différent de celui dans le script ; indice `0` incorrect pour le modèle ; aucune diapo visible ; mission lancée sans le mod. |
| Mauvaise face de l’écran texturée | Changez le `0` en `1` (variable + ligne `[nom, 1] spawn`). |
| Ancienne image après changement Athena | Bouton **Actualiser** dans le tableau, ou relancer la mission. |
| Script Init tronqué dans Eden | Recopiez le bloc **complet** de ce tutoriel (le champ Init doit contenir toutes les lignes jusqu’à la dernière `};`). |

---

## Rappel — ne pas mélanger les rôles

Sur les captures Eden typiques :

- le **grand écran** porte l’action « Consulter le briefing » ;
- l’**écran de salle de briefing** s’appelle `briefingScreen1` et porte le script de texture.

Évitez de coller le script texture du second objet **uniquement** sur le premier sans nommer correctement l’écran cible : le nom dans `[briefingScreen1, 0]` doit correspondre à un objet réellement nommé ainsi dans la mission.

---

## Référence rapide (copier-coller)

### A — Action seule (grand écran)

```sqf
this addAction ["Consulter le briefing", { [] call comspec_overwatch_connect_fnc_openBriefingBoard; }];
```

### B — Texture murale (écran nommé `briefingScreen1`)

```sqf
this setVariable ["comspec_briefingScreenIndex", 0];
[briefingScreen1, 0] spawn {
    params ["_obj", "_selIdx"];
    waitUntil { !isNull _obj };
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    if (count _slides == 0) then { _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides; };
    if (count _slides > 0) then {
        private _path = [_slides select 0] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
        if (_path != "") then { _obj setObjectTexture [_selIdx, _path]; };
    };
};
```
