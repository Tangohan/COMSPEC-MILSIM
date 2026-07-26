# Sécurité de distribution — COMSPEC Overwatch

Document honnête : ce qui est fait, ce qui reste **impossible** sur un client Arma 3, et les obligations de licence.

## Verdict court

Un mod Arma **n’est jamais inviolable**. Les PBO se décompressent, le SQF se lit, une DLL Native AOT se reverse (plus difficile, pas impossible). L’objectif réaliste est :

1. **Ne pas offrir** sources, PDB et secrets sur un plateau (Workshop propre).
2. **Rendre le vol inutile** fonctionnellement : sans clé / code communauté Athena, le mod ne parle pas au portail.
3. Respecter les **licences** (GPL sur les parties dérivées cTab vs assets / glue COMSPEC).

Ne jamais prétendre « impossible à voler ».

---

## Ce qui est fait (protections réalistes)

| Mesure | Détail |
|--------|--------|
| Pack Workshop filtrant | `mod/workshop-pack.ps1` → `mod/publisher/@COMSPECOverwatch` : PBO + DLL + `mod.cpp` + `CREDITS.md` uniquement |
| Pas de secrets hardcodés | Aucune clé Athena / `.env` dans le SQF ni dans la DLL ; la clé arrive via réglages CBA ou code de liaison |
| Logique réseau dans la DLL | HTTP / auth (`X-COMSPEC-KEY`) côté Native AOT ; le SQF orchestre l’UI |
| Gate serveur Athena | Routes tactiques protégées (`ComspecApiKeyAuth`) : clé plateforme ou clé d’accès communauté |
| Native AOT | Extension compilée native (~5 Mo), plus coûteuse à reverse que du .NET managé + PDB |
| Documentation packaging | Voir [PACKAGING.md](PACKAGING.md) |

### Auth côté Athena (valeur réelle du mod)

Sans **clé d’accès communauté** (ou clé plateforme) / sans **compte Athena lié** (Steam ou code) :

- les appels `/api/atak/*` protégés renvoient 401/403 ;
- `client-init` avec Steam non lié est refusé ; un jeton de session court est émis seulement si Steam est reconnu pour la communauté ;
- position / chat / écritures jeu : Steam fourni doit être lié ; un UID différent de la session d’init est rejeté (anti-spoof) ;
- la génération QR téléphone, sync position, etc. ne fonctionnent pas en production sans liaison valide.

Copier le PBO ne donne **pas** l’accès à votre instance Athena. Tournez la clé en admin si elle a fuité.

### Modèle de confiance

Le **client Arma est hostile** (SQF lisible, PBO décompressable). Le **serveur Athena fait foi** : clé + Steam lié + jeton de session émis à l’init. L’extension Native AOT envoie Steam de façon fiable (pas seulement le SQF).

---

## Ce qui reste impossible / limité

| Limite | Pourquoi |
|--------|----------|
| PBO « illisibles » | AddonBuilder / outils publics unpackent toujours le SQF |
| DRM client / anti-copie magique | Contournable ; hors scope (et hors éthique pour ce projet) |
| Empêcher le republish Workshop | Steam / communauté : DMCA / signalement, pas une tech locale |
| Cacher complètement les URLs | L’URL portail est un réglage (défaut public) ; ce n’est pas un secret |
| Chemins de build dans la DLL | Native AOT peut encore embarquer un chemin de machine de build ; réduire via rebuild Release + PathMap (voir PACKAGING) |
| Obfuscation SQF « solide » | Aucun outil fiable dans ce dépôt — ne pas inventer un obfuscateur fragile |

---

## Licences (attention « vol » vs obligations)

Voir aussi [CREDITS.md](CREDITS.md).

- **cTab / dérivés (ctav-b2, idées SIT / cTab IRL)** : base **GNU GPL v2**. Le code *dérivé* de cTab reste sous GPL : redistribution du code correspondant impose le respect de la GPL (disponibilité du source, licence, notices).
- **Glue COMSPEC / Athena** (extension `COMSPECExtension`, intégration portail, assets UI custom COMSPEC non dérivés) : propriété COMSPEC ; redistribution non autorisée hors accord — **mais** cela ne retire **pas** les obligations GPL sur les parties dérivées cTab.
- « Voler » un asset PNG custom ≠ « se soustraire à la GPL » sur du SQF dérivé de cTab. Les deux sujets sont distincts : propriété intellectuelle **et** copyleft.

En cas de republication abusive du wrapper COMSPEC : signalement Workshop + recours légaux ; pour les parties GPL, la bonne pratique est de garder `CREDITS.md` et d’honorer la licence d’origine.

---

## Recommandations opératoires

1. **Toujours** publier via `workshop-pack.ps1`, jamais le dossier de travail brut.
2. Ne jamais committer / uploader : `Sources/`, `Extension.cs`, `obj/`, `bin/`, `*.pdb`, `net8.0/`, `.biprivatekey`, `.env`.
3. Clés communauté : une par unité ; rotation si fuite ; ne pas coller la clé dans un README public.
4. BattlEye / whitelist extension : sujet séparé (chargement DLL), pas anti-copie.
5. Signature PBO (clés BI) : authentifie l’auteur auprès des serveurs qui exigent la signature — **ne chiffre pas** le contenu. Voir PACKAGING.md.

---

## Checklist rapide avant upload Steam

- [ ] `workshop-pack.ps1` exécuté sans erreur  
- [ ] Staging sans `*.sqf` / `*.pdb` / `net8.0`  
- [ ] DLL ≥ ~1 Mo (idéalement ~5 Mo Native AOT)  
- [ ] `CREDITS.md` présent  
- [ ] Aucune clé réelle dans les fichiers du pack  
