# Eden — attributs COMSPEC SSE

Catégorie d'attributs objet : **COMSPEC SSE**

| Champ | Valeurs |
|-------|---------|
| SSE activé | OUI/NON |
| Profil | RANDOM / CIVILIAN / INSURGENT / MILITARY / HVT / CUSTOM |
| Génération | AUTO / MANUAL |
| Richesse | LIGHT / STANDARD / DETAILED / HIGH_VALUE |
| Identité / Téléphone / Support numérique / Documents | AUTO / NONE / CUSTOM |
| Biométrie | AUTO / NONE |
| Notes Zeus | texte |
| ID réseau | texte (cluster partagé) |
| Données avancées | SQF pairs compilées |

## Comportement

Si `SSE activé` + `Génération = AUTO` → `generateData` à l'init serveur.  
Si `MANUAL` → modèle lazy via `makeSearchable` uniquement.

```sqf
// Données avancées (exemple)
[["name","Karim Haddad"],["alias","ABU HAMZA"]]
```
