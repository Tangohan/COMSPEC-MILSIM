# 🎮 Résumé des fonctionnalités "Troll" / Roleplay

## Vue rapide

Le mod Overwatch intègre des mécaniques immersives qui peuvent **perturber le gameplay** de façon réaliste pour renforcer l'expérience milsim.

---

## 🔥 Top 5 des "Trolls" possibles

### 1. 💥 Destruction de l'ATAK par blessure
**Activation** : Niveau de réalisme 2 ou 3  
**Effet** : Prendre une balle dans le torse = écran ATAK cassé (ou appareil détruit)  
**Réparation** : Toolkit requis  
**Potentiel troll** : ⭐⭐⭐⭐⭐

```
Joueur : "Contact ennemi !"
*BANG*
Joueur : "Putain mon écran est cassé je vois plus rien !!"
```

### 2. 📡 Zone de brouillage surprise
**Activation** : Module Zeus "Jammer"  
**Effet** : Tous les joueurs dans un rayon perdent la liaison de façon intermittente  
**Potentiel troll** : ⭐⭐⭐⭐⭐

```
MJ : *Place brouilleur de 400m autour de l'objectif en silence*
Squad Lead : "Heu... quelqu'un a encore ATAK qui marche ?"
*Chaos radio ensues*
```

### 3. 🚫 Zone morte souterraine
**Activation** : Module Zeus "No Coverage"  
**Effet** : Déconnexion instantanée et totale dans la zone  
**Potentiel troll** : ⭐⭐⭐⭐

```
*Squad entre dans le bunker*
*TOUS les ATAK se coupent d'un coup*
Squad : "......merde."
```

### 4. 🌐 Micro-coupures aléatoires
**Activation** : Pannes réseau simulées (paramètre CBA)  
**Effet** : Déconnexions de 5-30s toutes les 10 minutes, imprévisibles  
**Potentiel troll** : ⭐⭐⭐

```
*En plein contact*
"Liaison ATAK perdue (18s)"
Squad Lead : "Bien sûr, maintenant..."
```

### 5. 🎯 Glitchs visuels progressifs
**Activation** : Zone "Interference" forte intensité  
**Effet** : Écran qui glitch, parasites, messages d'erreur  
**Potentiel troll** : ⭐⭐⭐

```
*Approche d'une zone ennemie*
*Écran commence à glitcher*
Joueur : "C'est normal que mon ATAK fait ça...?"
*GLITCH INTENSIFIES*
```

---

## 🎯 Scénarios de MJ sadique

### Le parcours du combattant électronique

```sqf
// Zone 1 : Départ normal
// Zone 2 : +200m, interférence légère (30%)
// Zone 3 : +400m, interférence forte (70%)
// Zone 4 : Objectif, brouilleur actif (90%)
```

**Résultat** : Plus les joueurs approchent, plus c'est la merde. GG WP.

### Le bunker de l'enfer

```sqf
// Rez-de-chaussée : OK
// Sous-sol 1 : Dégradé (50%)
// Sous-sol 2 : No coverage (100%)
// Boss final : Sans ATAK, en CQB, dans le noir
```

**Résultat** : Retour aux années 90, communication vocale uniquement.

### La patrouille maudite

```sqf
// Activer : Pannes réseau aléatoires
// Ne rien dire aux joueurs
// Regarder le chaos
```

**Résultat** : "Putain mais pourquoi ça se déco tout le temps ?!" × 10

### Le véhicule de guerre électronique

```sqf
// Attacher un brouilleur mobile à un véhicule ennemi
// Le véhicule patrouille
// Zone de 300m qui se balade
```

**Résultat** : Joueurs paniqués qui ne comprennent pas pourquoi ça marche puis ça marche plus.

---

## ⚙️ Configuration pour maximum chaos

### Paramètres recommandés (Evil Mode™)

```
✅ Activer le mode roleplay
✅ Effets visuels de dégradation
✅ Pannes réseau simulées
🎚️ Niveau de réalisme matériel ATAK : 3 (destruction complète)
```

### Zones recommandées

```sqf
// Mettre des brouilleurs PARTOUT
[] spawn {
    while {true} do {
        {
            private _pos = getPos _x;
            [_pos, 200, "jammer", "Zone mystère", 60 + random 40] 
                call comspec_overwatch_connect_fnc_createRoleplayZone;
        } forEach allPlayers;
        
        sleep 600; // Nouvelles zones toutes les 10 min
    };
};
```

**Note** : Vos joueurs vont vous détester. Mission accomplie.

---

## 🛠️ Contre-mesures (pour les joueurs)

### Comment survivre

1. **Toolkit** : TOUJOURS en avoir un
2. **Communication radio** : ACRE/TFAR devient critique
3. **Autonomie** : Mémoriser les positions, pas de dépendance ATAK
4. **Réparation rapide** : Actions ACE dès que possible
5. **Zones identifiées** : Repérer les zones problématiques et les éviter

### Réparations disponibles

```
ACE Self-Interact > Équipement

- Rallumer ATAK (gratuit, si éteint)
- Réparer écran (Toolkit, 5s)
- Réparation complète (Toolkit, 10s)
- État ATAK (diagnostic)
```

---

## 📊 Tableau de frustration

| Situation | Niveau de frustration | Réalisme |
|-----------|----------------------|----------|
| ATAK éteint 30s | 😐 Acceptable | ⭐⭐⭐⭐ |
| Écran cassé, Toolkit dispo | 😑 Gérable | ⭐⭐⭐⭐⭐ |
| Écran cassé, pas de Toolkit | 😤 Énervant | ⭐⭐⭐⭐⭐ |
| ATAK détruit (niveau 3) | 😡 RAGE | ⭐⭐⭐⭐⭐ |
| Zone brouillage 90% | 😤 "C'est chiant" | ⭐⭐⭐⭐ |
| Coupures aléatoires pendant contact | 😡 "PUTAIN" | ⭐⭐⭐⭐⭐ |
| No coverage dans objectif | 😑 "On fait avec" | ⭐⭐⭐⭐ |

---

## 🎪 Easter Eggs et détails

### Messages cachés

Certains messages d'erreur alternent FR/EN de façon "réaliste" :

```
⚠️ ÉCRAN ENDOMMAGÉ
Signal: CONNECTED
Display: BROKEN

// Puis

⚠️ SCREEN DAMAGED  
Liaison: CONNECTÉE
Affichage: CASSÉ
```

### Sons immersifs

Tous les sons sont des assets Arma 3 natifs, aucun fichier custom. Les devs ont réutilisé :
- Sons d'alarmes
- Parasites radio
- Bips de ciblage
- Bruits de collision

### Variables debug secrètes

```sqf
// Voir TOUT
COMSPEC_Debug_PacketLoss = true;

// Forcer destruction (test)
private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
_state set ["screen_destroyed", true];
_state set ["device_destroyed", true];

// Spam de déconnexions
while {true} do {
    [] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;
    sleep 10;
};
```

**⚠️ NE PAS UTILISER EN PRODUCTION** (ou si, pour le chaos)

---

## 🎭 Témoignages de joueurs

> "J'ai pris une balle, mon ATAK est mort, on était au milieu de nulle part, j'avais pas de toolkit. J'ai passé 30 minutes à suivre l'équipe en espérant ne pas me perdre. 10/10 immersion."  
> — Joueur masochiste

> "Le MJ a mis un brouilleur mobile sur un hélico ennemi qui faisait des cercles. On comprenait rien. C'était génial et horrible en même temps."  
> — Squad Lead traumatisé

> "Quelqu'un peut m'expliquer pourquoi MON écran se casse mais pas celui de Jean-Pierre qui a pris 3 balles ??"  
> — Joueur victime du RNG

---

## 📚 Documentation complète

Pour tout savoir sur le système (configuration, développement, scripting avancé) :

👉 **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)**

---

## ⚖️ Éthique du troll

### ✅ Bon usage

- Améliorer l'immersion dans des scénarios prévus
- Créer des challenges tactiques réalistes
- Récompenser la préparation et l'adaptabilité
- Utiliser avec modération

### ❌ Mauvais usage

- Troll non-annoncé sur des joueurs débutants
- Paramètres extrêmes sans prévenir
- Zones invisibles partout sans logique
- Destruction d'ATAK sans possibilité de réparation

### 🎯 L'équilibre parfait

```
Frustration créative + Possibilité de s'adapter = Fun
```

---

## 🚀 Évolutions futures possibles

Idées pour les devs sadiques :

- [ ] ATAK qui redémarre aléatoirement (écran bleu de la mort)
- [ ] Virus informatique qui se propage entre joueurs proches
- [ ] Batterie qui se décharge (besoin de recharge)
- [ ] Capteur de fréquence cardiaque buggé (affiche 300 BPM)
- [ ] GPS qui dérive lentement (position erronnée de 50m)
- [ ] Autocorrect agressif dans les messages (comme sur téléphone)

---

*"With great power comes great trollability."*  
— Uncle Ben (probablement)

---

**Généré le 2026-07-24**  
**Pour COMSPEC-MILSIM**  
**Utilisez ces pouvoirs avec sagesse... ou pas. 😈**
