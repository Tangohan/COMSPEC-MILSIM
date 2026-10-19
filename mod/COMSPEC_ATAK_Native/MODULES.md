# COMSPEC Modules : créer une app pour le téléphone COMSPEC ATAK

Un module est un addon Arma 3 séparé qui ajoute une app au téléphone, ou un calque à sa carte,
sans modifier COMSPEC ATAK. Il se charge à côté de `@COMSPEC_ATAK_Native`.

## 1. Déclarer l'app

Dans le `config.cpp` de votre addon :

```cpp
class CfgPatches {
    class mon_module {
        name = "Mon module COMSPEC";
        requiredAddons[] = {"comspec_atak_native_main"};
        units[] = {}; weapons[] = {};
        requiredVersion = 2.14;
    };
};

class CfgFunctions {
    class mon_module {
        class main {
            file = "\mon_module\functions";
            class pageMeteoLocale {};
            class calqueCarte {};
        };
    };
};

// Ajoute une app au lanceur du téléphone.
class COMSPEC_ATAK_Apps {
    class MonModule {
        name = "Météo locale";           // nom sous l'icône
        page = "MONMODULE";              // identifiant de page, unique, en majuscules
        section = "Mission";             // section du lanceur (Opérations, Renseignement, Mission, Système…)
        order = 400;                     // position dans la section
        dock = 0;                        // 1 = proposée dans le dock
        icon = "\mon_module\data\icon.paa";
        function = "mon_module_fnc_pageMeteoLocale";   // fonction qui dessine la page
    };
};

// Optionnel : dessine sur la carte du téléphone à chaque image.
class COMSPEC_ATAK_MapLayers {
    class MonCalque { function = "mon_module_fnc_calqueCarte"; };
};
```

## 2. Dessiner la page

La fonction reçoit `[page, [0, 0, largeur, hauteur]]`. Le plus simple est le formulaire défilant
du téléphone, qui gère les thèmes, la taille de police et le défilement :

```sqf
// mon_module\functions\fn_pageMeteoLocale.sqf
params ["_page", "_rect"];
private _rows = [
    ["title", "Météo locale"],
    ["info", "Vent", format ["%1 m/s", round (vectorMagnitude wind)]],
    ["info", "Brouillard", format ["%1 %%", round (fog * 100)]],
    ["buttons", [["ACTUALISER", { ["MONMODULE"] call comspec_atak_native_fnc_pageRender; }, true]]]
];
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
```

Types de lignes disponibles : `title`, `text`, `edit`, `memo`, `combo`, `toggle`, `switch`, `segment`,
`buttons`, `section`, `info`, `hero`, `person`, `image`, `gap`. Le détail est en tête de
`functions/ui/fn_formRender.sqf`. Une valeur saisie se relit avec `["clé", défaut] call comspec_atak_native_fnc_formValue`.

Pour une mise en page libre, créez vos contrôles avec
`["COMSPEC_RscButton", [x, y, l, h], "TEXTE"] call comspec_atak_native_fnc_pageCtrl` :
ils sont supprimés automatiquement au changement de page.

## 3. Fonctions utiles

| Fonction | Rôle |
|---|---|
| `["INFO", "texte", durée, priorité] call comspec_atak_native_fnc_notify` | notification du téléphone (INFO, SUCCESS, WARNING, MESSAGE) |
| `[] call comspec_atak_native_fnc_vibrate` | vibration |
| `["PAGE"] call comspec_atak_native_fnc_navigate` | ouvre une autre app |
| `[code, arguments, "libellé", Ko] call comspec_atak_native_fnc_netSend` | envoi soumis au réseau simulé (délai, pertes, file sans réseau) |
| `[] call comspec_atak_native_fnc_linkQuality` | qualité du signal (barres, débit, latence) |
| `[unité] call comspec_atak_native_fnc_unitGroup` | groupe Athena d'une unité |
| `[position, 8] call comspec_atak_native_fnc_gridRef` | grille à 6, 8 ou 10 chiffres |
| `[player] call comspec_atak_native_fnc_hasDevice` | le joueur a-t-il un téléphone |

Événement CBA local `comspec_atak_native_pageOpened` : `[page]`, émis à chaque affichage d'app.

## 4. Règles

- Restez réaliste : le téléphone ne sait que ce qu'il peut capter. Passez vos envois par `netSend`.
- Ne modifiez pas les variables `COMSPEC_ATAK_*` du téléphone : lisez-les seulement.
- Un serveur peut retirer n'importe quelle app, module compris, avec le réglage CBA
  « Apps désactivées » (noms de classe séparés par des virgules).
- Publiez votre module sous votre propre licence. COMSPEC ATAK est non commercial (APL-SA).

## 5. Brouilleurs : brouiller l'ATAK depuis une mission ou un autre mod

Un brouilleur déclaré ici agit comme ceux de l'app Guerre électronique et du module Zeus « ATAK · Brouilleur de zone » :
débit, GPS, suivi des forces amies, drones, vidéo et relais dans son rayon (plein effet au centre, plus faible vers le
bord), et la goniométrie de l'app GE peut le relever.

```sqf
// Serveur (l'init d'un objet dans Eden tourne aussi sur le serveur). Ailleurs, la demande part au serveur.
// [source (objet ou position), rayon m, force 1-100, durée s (0 = sans fin), camp épargné ("" , "WEST", "EAST", "GUER", "CIV"), id facultatif]
if (isServer) then { ["add", [this, 800, 80]] call comspec_atak_native_fnc_jammer; };
private _id = ["add", [getMarkerPos "zone_ge", 1500, 60, 600, "EAST"]] call comspec_atak_native_fnc_jammer;
["remove", _id] call comspec_atak_native_fnc_jammer;   // ou ["remove", objet]
```

- Un objet brouilleur s'arrête quand il est détruit. Pour l'éteindre sans le retirer :
  `objet setVariable ["COMSPEC_ATAK_JamActive", false, true]` (la variable `a3f_jammer_active` des scripts
  « TFAR Radio Jammer » est aussi respectée).
- Sans script, l'admin peut désigner des objets ou véhicules d'autres mods dans le réglage CBA
  « Objets brouilleurs d'autres mods » : `Classe:rayon:force`, séparés par des virgules (classes parentes acceptées).
- Déjà pris en compte sans rien faire : brouilleurs du mod Crows Electronic Warfare, et scripts de brouillage radio TFAR
  qui baissent `tf_sendingDistanceMultiplicator` du joueur (réglage « Brouillage des scripts TFAR »).

**English.** Any mission or mod can jam the ATAK with `["add", [object or position, radius m, strength 1-100,
duration s (0 = endless), spared side, optional id]] call comspec_atak_native_fnc_jammer` (runs on the server; returns
the id) and `["remove", id or object]`. A jammer object stops when destroyed; set `COMSPEC_ATAK_JamActive` (or
`a3f_jammer_active`) to `false` on it to switch it off. Admins can also list jammer classes from other mods in the CBA
setting "Jammer objects from other mods" (`Class:radius:strength`). Crows Electronic Warfare jammers and TFAR jamming
scripts (via `tf_sendingDistanceMultiplicator`) are already picked up.
