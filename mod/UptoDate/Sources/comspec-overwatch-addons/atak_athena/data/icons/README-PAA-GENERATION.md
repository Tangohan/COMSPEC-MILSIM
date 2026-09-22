# Génération des icônes .paa pour ATAK

## Contexte

Les fichiers `.paa` sont le format d'image propriétaire de Bohemia Interactive pour Arma 3. Ils sont optimisés pour les performances en jeu et supportent les mipmaps et la compression DXT.

## Fichiers

- `*.png` : Source PNG haute résolution (256x256, RGBA)
- `*.paa` : Fichier PAA compilé pour Arma 3

## Processus de génération

### Option 1 : Arma 3 Tools (Recommandé)

1. Installer **Arma 3 Tools** depuis Steam (Tools > Arma 3 Tools)
2. Lancer **TexView 2**
3. Ouvrir le fichier PNG source
4. Configurer les paramètres :
   - Format : **DXT5** (supporte alpha/transparence)
   - Mipmaps : **Auto-generate**
   - Filter : **Lanczos** (meilleure qualité)
5. Exporter au format `.paa`

### Option 2 : ImageToPAA CLI

```bash
# Depuis Arma 3 Tools
ImageToPAA.exe -rgba input.png output.paa
```

### Option 3 : Batch conversion (PowerShell)

```powershell
# Convertir tous les PNG du dossier
$toolsPath = "C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools\ImageToPAA"
Get-ChildItem *.png | ForEach-Object {
    $output = $_.BaseName + ".paa"
    & "$toolsPath\ImageToPAA.exe" -rgba $_.FullName $output
}
```

## Icônes actuelles

| Icône | Taille | Format | Utilisation |
|-------|--------|--------|-------------|
| `app_athena_ca.paa` | 256x256 | DXT5 | App Athena C2 |
| `app_bda_ca.paa` | 256x256 | DXT5 | App BDA (Battle Damage Assessment) |
| `app_briefing_ca.paa` | 256x256 | DXT5 | App Briefing |
| `app_comms_ca.paa` | 256x256 | DXT5 | App Comms |
| `app_wiki_ca.paa` | 256x256 | DXT5 | App Wiki |
| `app_relay_ca.paa` | 256x256 | DXT5 | App Relais AT |

## Notes importantes

1. **Fichiers temporaires** : Actuellement, `app_relay_ca.paa` est une copie temporaire de `app_comms_ca.paa`. Il DOIT être régénéré à partir de `app_relay_ca.png` avant la release.

2. **Transparence** : Toujours utiliser le format **DXT5** pour préserver la transparence alpha.

3. **Mipmaps** : Activez la génération automatique des mipmaps pour des performances optimales en jeu.

4. **Nom de fichier** : Le suffixe `_ca` est une convention BI pour "Color+Alpha".

5. **Chemin dans config.cpp** :
   ```cpp
   textureNoShortcut = "\z\comspec_overwatch\addons\atak_athena\data\icons\app_relay_ca.paa";
   ```

## Checklist avant compilation finale

- [ ] Tous les fichiers `.png` source sont présents
- [ ] Tous les fichiers `.paa` ont été régénérés depuis leurs `.png`
- [ ] Les chemins dans `config.cpp` sont corrects
- [ ] Test en jeu : icônes visibles sur le téléphone ATAK
- [ ] Pas d'erreur de chargement dans le RPT

## Références

- [Arma 3 Tools Documentation](https://community.bistudio.com/wiki/Arma_3_Tools)
- [TexView 2 Guide](https://community.bistudio.com/wiki/TexView_2)
- [PAA File Format](https://community.bistudio.com/wiki/PAA_File_Format)
