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
