# Empreintes SHA-256 des fichiers du mod ATAK natif

Relevé du dossier `mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native` (construction 4.32.1) au 23/11/2026 (commit `cb048217a` de main), **sans** le lanceur de collecte de journaux retiré dans #1881. Elles servent à :

- rechercher chaque fichier sur [VirusTotal](https://www.virustotal.com/gui/home/search) sans l'envoyer ;
- appuyer une demande de levée de faux positif auprès d'un éditeur d'antivirus ou de Steam ;
- vérifier qu'une copie du mod n'a pas été modifiée.

L'empreinte de l'archive RAR publiée n'est pas dans ce fichier : elle est calculée à chaque publication et affichée sur la page `/modpacks`.

| Fichier | Taille (octets) | SHA-256 |
| --- | ---: | --- |
| `COMSPECATAKNativeExtension_x64.dll` | 10 423 296 | `5de862be128c19b2627926413e416638ee4bbfcf114592271b39bec1b256f45e` |
| `COMSPECExtension_x64.dll` | 10 240 512 | `c9e852df69f72d7db257baf8e172db9a3ecbe17e4f87534aef7175bbe35e4b7e` |
| `addons/main.pbo` | 123 112 328 | `3dd5cefee86259c1cf9bf7f370716f6ee3c553d12f6b435dc042bd121e886418` |
| `addons/towers.pbo` | 56 199 301 | `d911087980d89e870877b9a61c19ad3e4d320cd00585fdcc448a25e294ef0b33` |
| `addons/comspec_overwatch_connect.pbo` | 25 460 297 | `bbcc7a9b629f8d30ae2efa5eb6d518f5323cc64bba51a9781a2bb6564fdf25be` |
| `addons/items.pbo` | 29 649 104 | `732615385d5a4a34d7f9fc3f0d7e6fc067fee3d1a806d3c867896fa08747fc31` |
| `addons/comspec_overwatch_main.pbo` | 178 077 | `417ee9b6152f75b3c378610180b88d0d83cd81ccc29c26ce92186c370e6aa30a` |
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
