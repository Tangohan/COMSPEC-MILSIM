# Empreintes SHA-256 des fichiers du mod ATAK natif

Relevé du dossier `mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native` au 23/11/2026 (commit `ffa6624cf` de main), **sans** le lanceur de collecte de journaux retiré dans #1881. Elles servent à :

- rechercher chaque fichier sur [VirusTotal](https://www.virustotal.com/gui/home/search) sans l'envoyer ;
- appuyer une demande de levée de faux positif auprès d'un éditeur d'antivirus ou de Steam ;
- vérifier qu'une copie du mod n'a pas été modifiée.

L'empreinte de l'archive RAR publiée n'est pas dans ce fichier : elle est calculée à chaque publication et affichée sur la page `/modpacks`.

| Fichier | Taille (octets) | SHA-256 |
| --- | ---: | --- |
| `COMSPECATAKNativeExtension_x64.dll` | 10 391 040 | `4734f709d03b2a81f30993fb1082a59606f1afbf8bde75fe9e9e969a47cd8e8a` |
| `COMSPECExtension_x64.dll` | 10 208 256 | `c8268b17419ccf54e2cdc6dbc81e4197843b468b151fcc88883e62c4534213fc` |
| `addons/main.pbo` | 95 535 630 | `fa18b85c68e2263693b66a07e173f6bbafa57561247288e9ed5a52c6e8b9bfbe` |
| `addons/towers.pbo` | 89 380 262 | `4c3279859203ecaf6af0901694689488ff8de2b84f3bc284dae66963e652e3d9` |
| `addons/comspec_overwatch_connect.pbo` | 48 463 257 | `0190e854fd0c8eb436e32436e70eecf8d776c3d6fa90beda6be0ce21ecd423d2` |
| `addons/items.pbo` | 22 254 547 | `3f1e78b54c71906a6e988b0814c992893934a1367e01d380f430ec5225ccc441` |
| `addons/comspec_overwatch_main.pbo` | 178 077 | `3ddf6209fc1189b8829533391979e029b85496e80e4e1d221886b148e7d7a3bc` |
| `keys/comspec_atak_native.bikey` | 172 | `c8a0b9f9244b32c1f0fc6adfd6e464f84fa60dd7874619e30ec7594bbea36888` |
| `mod.cpp` | 514 | `17c8df972a923656d44b98ba0cd9b380747eab1ce409803406f6304565a66de4` |
| `logo.paa` | 11 061 | `f6cd849336486dbfa40ff067486dc2a4a42949b8967a1a1d99107a43627b6df9` |
| `LICENSE.txt` | 5 655 | `6dfd99f60124ca1f1923b4aafcaf543c539125ed31a75f5d802023f8ac33a702` |

Les fichiers `.bisign` (signatures Arma 3 des PBO, 572 octets chacun) ne sont pas listés : ils changent à chaque reconstruction.

## Recalculer

```powershell
Get-ChildItem -Recurse -File "mod\COMSPEC_ATAK_Native\@COMSPEC_ATAK_Native" |
  Where-Object { $_.Extension -notin '.bisign' } |
  Get-FileHash -Algorithm SHA256 |
  Select-Object Hash, @{n='Fichier';e={$_.Path}}
```

À refaire après chaque reconstruction de `main.pbo` ou des DLL : une empreinte périmée ne prouve rien.

## À propos des DLL

Les deux DLL sont des binaires .NET compilés en natif (sections `.managed` et `hydrated`), **non signés**. Les signer avec un certificat de signature de code reconnu est la mesure qui réduit le plus les faux positifs ; un certificat auto-signé n'y change rien. Voir `docs/bugs/2026-11-23-workshop-signale-av-lanceur-logs.md`.
