# Écussons « Donateur Athena »

Sources des écussons d'épaule offerts aux donateurs (paliers du portail : Soutien, Allié, Pilier).
Dessin original : cœur, chevrons au palier, laurier pour Pilier. Aucun logo tiers.

| Fichier | Rôle |
|---|---|
| `donor_<palier>.svg` | source vectorielle (à retoucher ici) |
| `donor_<palier>.png` | rendu 256x256, fond transparent |
| `connect/img/donor/donor_<palier>_ca.paa` | texture en jeu (DXT5, mipmaps), dans le PBO `connect` |

Les sources restent hors du dossier de l'addon : `build_mod.bat` (AddonBuilder `-packonly`) emballe tout le dossier.

## Régénérer les PAA

```
python3 mod/UptoDate/tools/png2paa.py donor_pilier.png ../../comspec-overwatch-addons/connect/img/donor/donor_pilier_ca.paa
python3 mod/UptoDate/tools/png2paa.py --check ../../comspec-overwatch-addons/connect/img/donor/donor_pilier_ca.paa apercu.png
```

Alternative officielle : Arma 3 Tools > ImageToPAA sur le PNG, en gardant le nom `_ca.paa`.

## En jeu

- Classes `CfgUnitInsignia` : `COMSPEC_Donor_soutien`, `COMSPEC_Donor_allie`, `COMSPEC_Donor_pilier` (`connect/config.cpp`).
- `comspec_overwatch_connect_fnc_applyDonorPerk` pose l'écusson du joueur local quand Athena renvoie `net_donor_tier`
  (expérience communauté), sans jamais remplacer un écusson de mission ; réglage CBA « Écusson Donateur Athena » pour le retirer.
- Les écussons sont aussi visibles dans l'arsenal (Insignes) : un joueur peut les choisir à la main.
- Après modification : `build_mod.bat`, `workshop-pack.ps1`, puis mise à jour de l'objet Workshop.
