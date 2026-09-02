# 📚 Index - Documentation Roleplay/Troll Mod Overwatch

Cet index référence toute la documentation des fonctionnalités roleplay et "troll" du mod COMSPEC Overwatch.

---

## 📖 Documents disponibles

### 1. Guide technique complet
**Fichier** : [`MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md`](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)  
**Pour qui** : Développeurs, admins, utilisateurs avancés  
**Contenu** :
- Architecture complète du système roleplay
- Système de dommages ATAK (3 niveaux)
- Zones géographiques (4 types détaillés)
- Déconnexions réseau aléatoires
- Effets visuels et sonores (catalogue complet)
- Système de réparation ACE
- Configuration CBA exhaustive
- Variables de debug et troubleshooting
- FAQ technique

**Longueur** : ~700 lignes  
**Niveau** : 🔧🔧🔧 Avancé

---

### 2. Résumé fun et scénarios
**Fichier** : [`FONCTIONNALITES-TROLL-RESUME.md`](./FONCTIONNALITES-TROLL-RESUME.md)  
**Pour qui** : MJ, Zeus, joueurs curieux  
**Contenu** :
- Top 5 des "trolls" possibles avec exemples concrets
- Scénarios de MJ sadique (parcours du combattant, bunker de l'enfer...)
- Configuration "Evil Mode™"
- Tableau de frustration des joueurs
- Easter eggs cachés
- Témoignages (fictifs mais réalistes)
- Éthique du troll
- Idées d'évolutions futures

**Longueur** : ~300 lignes  
**Niveau** : 😈😈 Fun et accessible

---

### 3. Guide rapide Zeus
**Fichier** : [`GUIDE-ZEUS-ROLEPLAY-RAPIDE.md`](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)  
**Pour qui** : Zeus/MJ en mission  
**Contenu** :
- Cheat sheet pour chaque type de zone
- 4 scénarios pré-configurés prêts à l'emploi
- Commandes rapides copier-coller
- Tableau d'équilibrage et règles d'or
- Tips avancés (brouilleurs destructibles, zones mobiles...)
- Checklist pré-mission
- Troubleshooting en live
- Exemples de narratives

**Longueur** : ~450 lignes  
**Niveau** : 🎮🎮 Opérationnel

---

## 🎯 Quel document lire ?

### Je suis...

#### Joueur curieux
👉 **[FONCTIONNALITES-TROLL-RESUME.md](./FONCTIONNALITES-TROLL-RESUME.md)**  
Pour comprendre ce qui peut t'arriver en mission et comment t'y préparer.

#### Zeus/MJ qui veut tester
👉 **[GUIDE-ZEUS-ROLEPLAY-RAPIDE.md](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)**  
Pour placer tes premières zones rapidement avec les bonnes pratiques.

#### Admin qui configure
👉 **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)**  
Pour comprendre tous les paramètres et les ajuster finement.

#### Développeur qui modifie
👉 **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)**  
Pour voir l'architecture, les variables globales, les fonctions disponibles.

---

## 🔗 Documents techniques connexes

Ces documents sont référencés mais situés ailleurs dans le projet :

### Documentation mod existante

- `docs/archive/legacy-atak/ROLEPLAY-ATAK-ENHANCED.md` : note historique (absorbe dans TM-A3-21 / SOP-A3-01)
- `ROLEPLAY-EFFETS-INGAME.md` : Catalogue des effets visuels/sonores
- `ROLEPLAY-NOUVELLES-FONCTIONNALITES.md` : Fonctionnalités roleplay complètes
- `ROLEPLAY-ZONES-GEOGRAPHIQUES.md` : Guide complet des zones
- `docs/technique/atak-roleplay-simulation.md` : Architecture technique

### Fichiers code clés

```
mod/@COMSPECOverwatch/addons/connect/functions/
├── fn_checkAtakDamage.sqf              (dommages physiques)
├── fn_applyZoneEffects.sqf             (effets de zones)
├── fn_simulateNetworkDisconnect.sqf    (déconnexions aléatoires)
├── fn_updateAtakEnhancedRoleplay.sqf   (effets visuels web)
├── fn_repairAtak.sqf                   (réparation ACE)
├── fn_createRoleplayZone.sqf           (création zones)
├── fn_deleteRoleplayZone.sqf           (suppression zones)
├── fn_listRoleplayZones.sqf            (liste zones)
├── fn_getPlayerRoleplayZone.sqf        (détection zone joueur)
└── fn_isAtakFunctional.sqf             (diagnostic ATAK)

mod/@COMSPECOverwatch/addons/connect/
├── modules/module_roleplay_zone.hpp    (modules Zeus)
├── XEH_preInit.sqf                     (paramètres CBA)
└── XEH_postInit.sqf                    (initialisation)
```

---

## 📊 Comparaison rapide

| Aspect | Doc complète | Résumé fun | Guide Zeus |
|--------|--------------|------------|------------|
| **Longueur** | ~700 lignes | ~300 lignes | ~450 lignes |
| **Ton** | Technique | Humoristique | Pratique |
| **Public** | Avancé | Grand public | Opérationnel |
| **But** | Comprendre | S'amuser | Utiliser |
| **Code** | ✅ Détaillé | ⚠️ Exemples | ✅ Copy-paste |
| **Théorie** | ✅ Complète | ⚠️ Résumé | ❌ Minimale |
| **Pratique** | ⚠️ Exemples | ❌ Anecdotes | ✅ Scénarios |

---

## 🎓 Parcours d'apprentissage recommandé

### Débutant (MJ/Zeus)

1. **[FONCTIONNALITES-TROLL-RESUME.md](./FONCTIONNALITES-TROLL-RESUME.md)** (15 min)
   - Comprendre le concept général
   - Voir les possibilités

2. **[GUIDE-ZEUS-ROLEPLAY-RAPIDE.md](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)** (30 min)
   - Apprendre les commandes de base
   - Tester un scénario pré-configuré

3. **Pratique** (1-2h)
   - Créer une mission de test
   - Placer 2-3 zones
   - Tester avec des joueurs

### Intermédiaire

4. **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)** - Sections spécifiques (1h)
   - Configuration avancée
   - Système de réparation
   - Troubleshooting

5. **Pratique avancée** (2-3h)
   - Missions scénarisées complexes
   - Zones mobiles/progressives
   - Brouilleurs destructibles

### Expert

6. **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)** - Complet (2h)
   - Architecture technique
   - Variables globales
   - Développement custom

7. **Code source** (variable)
   - Lecture des `.sqf`
   - Modifications/extensions
   - Contribution au projet

---

## 🔧 Cas d'usage par document

### Scénario : "Je veux ajouter un brouilleur dans ma mission"

**Solution** : 
1. Ouvrir **[GUIDE-ZEUS-ROLEPLAY-RAPIDE.md](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)**
2. Section "Brouilleur actif 🚫"
3. Copier-coller le code d'exemple
4. Ajuster position/rayon

**Temps** : 2 minutes

---

### Scénario : "Un joueur signale que son ATAK est cassé, c'est normal ?"

**Solution** :
1. Ouvrir **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)**
2. Section "Système de dommages ATAK"
3. Vérifier le niveau de réalisme configuré
4. Expliquer au joueur comment réparer (Toolkit)

**Temps** : 5 minutes

---

### Scénario : "Je veux comprendre ce que fait le mod avant de l'utiliser"

**Solution** :
1. Lire **[FONCTIONNALITES-TROLL-RESUME.md](./FONCTIONNALITES-TROLL-RESUME.md)**
2. Section "Top 5 des trolls"
3. Section "Scénarios de MJ sadique"

**Temps** : 10-15 minutes

---

### Scénario : "Je veux créer une mission complexe avec progression"

**Solution** :
1. Lire **[GUIDE-ZEUS-ROLEPLAY-RAPIDE.md](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)**
2. Section "Scénarios pré-configurés" → Scénario 1
3. Adapter à sa carte/mission
4. Référence **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)** pour détails

**Temps** : 30-45 minutes

---

## 📝 Changelog documentation

### 2026-07-24 - Version initiale

**Créé** :
- `MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md` (guide technique)
- `FONCTIONNALITES-TROLL-RESUME.md` (résumé fun)
- `GUIDE-ZEUS-ROLEPLAY-RAPIDE.md` (guide opérationnel)
- `INDEX-DOCS-ROLEPLAY-TROLL.md` (ce fichier)

**Total** : ~1600 lignes de documentation

---

## 🤝 Contribution

### Signaler une erreur

Ouvrir une issue GitHub avec :
- Document concerné
- Section concernée
- Description de l'erreur
- Correction proposée (optionnel)

### Proposer une amélioration

Pull request bienvenue sur :
- Clarifications
- Exemples supplémentaires
- Corrections orthographiques
- Nouveaux scénarios

### Ajouter un scénario

Format recommandé dans **[GUIDE-ZEUS-ROLEPLAY-RAPIDE.md](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)** :

```markdown
### Scénario X : [Nom évocateur]

**Durée** : XX min  
**Difficulté** : ⭐⭐⭐

[Code SQF]

**Effet** : [Description de l'expérience joueur]
```

---

## 📞 Support

**Questions techniques** : GitHub Issues  
**Discussions** : Discord #roleplay-atak  
**Bugs** : GitHub Issues avec logs + config  
**Suggestions** : Discord #suggestions

---

## 📜 Licence

Documentation sous même licence que le projet COMSPEC-MILSIM.

Les exemples de code sont librement réutilisables dans vos missions.

---

## 🎯 Objectifs de cette documentation

1. ✅ **Accessibilité** : Tout le monde peut comprendre et utiliser
2. ✅ **Exhaustivité** : Tous les aspects couverts (débutant → expert)
3. ✅ **Praticité** : Exemples copy-paste fonctionnels
4. ✅ **Ludique** : Documentation agréable à lire
5. ✅ **Maintenabilité** : Facile à mettre à jour

---

## 🌟 Remerciements

- **Équipe dev** : Pour le système roleplay complet
- **Testeurs** : Pour les retours et scénarios
- **Communauté** : Pour les idées et suggestions
- **MJ sadiques** : Pour l'inspiration 😈

---

**Dernière mise à jour** : 2026-07-24  
**Version documentation** : 1.0  
**Version mod** : 1.0.0

---

*"Good documentation is like a good mission: well-structured, clear objectives, and rewarding to complete."*

---

## 🗺️ Navigation rapide

- 📘 [Guide technique complet](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)
- 😈 [Résumé fun et scénarios](./FONCTIONNALITES-TROLL-RESUME.md)
- 🎮 [Guide rapide Zeus](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)
- 🏠 [Retour README principal](../README.md)
