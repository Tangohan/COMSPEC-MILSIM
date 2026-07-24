# Nouvelles fonctionnalités roleplay ATAK

**Date** : 24 juillet 2026  
**Implémentation complète** : 3 commits

---

## 🎯 Vue d'ensemble

Extension majeure du système de roleplay ATAK avec **3 nouvelles fonctionnalités critiques** demandées :

1. ✅ **Mesure réelle du packet loss** depuis le mod
2. ✅ **Effets visuels avancés** (marqueurs sautants, positions obsolètes)
3. ✅ **Déconnexions complètes** côté mod

---

## 1️⃣ Mesure réelle du packet loss

### Problème résolu

Avant : L'indicateur "Pertes de paquets" affichait uniquement `—` ou la valeur simulée configurée par l'admin.

Maintenant : Le mod **mesure effectivement** le taux de perte en trackant chaque requête.

### Comment ça marche

```
Arma (fn_updatePosition)
  ↓ [recordPacketSent("req_123")]
  ↓ callExtension("UpdatePosition")
  ↓
Extension C#
  ↓ HTTP POST /api/atak/units
  ↓
Serveur PHP
  ↓ 200 OK (ou erreur)
  ↓
Extension C# → Callback Arma
  ↓ [recordPacketReceived("req_123")]
  ↓
Calcul : (sent - received) / sent * 100
```

### Architecture

**Fichiers SQF** :
- `fn_trackPacketLoss.sqf` : Calcul sur fenêtre glissante (100 dernières)
- `fn_recordPacketSent.sqf` : Enregistrement avant envoi
- `fn_recordPacketReceived.sqf` : Marquage des succès
- `fn_getPacketLossStats.sqf` : Export des statistiques
- `fn_handlePositionUpdateCallback.sqf` : Callback de l'extension

**Données trackées** :
- Total envoyé / reçu (depuis le début)
- Fenêtre glissante (100 derniers)
- Timestamp de chaque requête
- HashMap des requêtes en attente

**Nettoyage** :
- Toutes les 5 minutes
- Garde seulement les 100 dernières entrées
- Supprime les requêtes en attente anciennes

### Intégration

**Mod** :
```sqf
// Dans fn_updatePosition.sqf
private _requestId = [format ["pos_%1", time]] call comspec_overwatch_connect_fnc_recordPacketSent;

// Stats envoyées toutes les 10s
private _packetLossStats = "";
if ((time - _lastStatsTime) > 10) then {
    private _stats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    _packetLossStats = format [
        ",""packet_loss"":%1,""packets_sent"":%2,""packets_received"":%3",
        _stats get "packet_loss_percent",
        _stats get "packets_sent_window",
        _stats get "packets_received_window"
    ];
};
```

**Serveur** :
```php
// Dans AtakApiController::getMeasuredPacketLoss()
foreach ($units as $unit) {
    $extra = json_decode($unit['extra'], true);
    if (isset($extra['packet_loss'])) {
        $latestMeasurement = [
            'packet_loss_percent' => $extra['packet_loss'],
            'packets_sent' => $extra['packets_sent'],
            'packets_received' => $extra['packets_received'],
            'unit_callsign' => $unit['call_sign'],
            'measured_at' => $unit['updated_at'],
        ];
    }
}
```

**UI Web** :
```javascript
// Dans atak-roleplay-effects.js
this.updateNetworkQualityIndicators(null, null, measuredPacketLoss);

// Affichage :
// "2.45 % (mesuré)" avec tooltip "18/20 paquets reçus"
// vs
// "5.00 % (simulé)" si pas de mesure
```

### Affichage

| Source | Affichage | Tooltip |
|--------|-----------|---------|
| Mod mesuré | `2.45 % (mesuré)` | `Mesure réelle depuis le mod Arma. 18/20 paquets reçus` |
| Admin simulé | `5.00 % (simulé)` | `Pertes de paquets (indisponible tant que le mod ne remonte pas cette mesure)` |
| Aucun | `—` | — |

---

## 2️⃣ Effets visuels avancés

### Marqueurs qui "sautent"

**Comportement** :
- Quand un paquet est perdu, le marqueur fait un "saut" visuel
- Animation CSS de 300ms avec déplacement aléatoire
- Simule l'instabilité du signal GPS

**Implémentation** :
```css
.atak-marker-jump {
  animation: atak-marker-jump-anim 0.3s ease-out;
}

@keyframes atak-marker-jump-anim {
  0% { transform: translate(0, 0); }
  25% { transform: translate(-10px, -15px); }
  50% { transform: translate(5px, -8px); }
  75% { transform: translate(-3px, -3px); }
  100% { transform: translate(0, 0); }
}
```

**Déclenchement** :
```javascript
// Automatique selon packet loss
if (this.config.packetLoss > 0 && Math.random() * 100 < this.config.packetLoss) {
  this.applyMarkerJumpEffect(marker);
}
```

### Positions obsolètes

**Comportement** :
- Quand un paquet est perdu, la dernière position connue est réutilisée
- Affichage en grisé avec animation fade-pulse
- Badge indiquant l'âge : "Données obsolètes (15s)"
- Position gardée max 30 secondes

**Cache** :
```javascript
// Map des dernières positions connues
this._oldPositions = new Map();

// Stockage
oldPositionsStore.set(unitId, {
  unit: { ...unit },
  timestamp: Date.now()
});

// Réutilisation si paquet perdu
const oldData = oldPositionsStore.get(unitId);
if (oldData && (now - oldData.timestamp < 30000)) {
  return {
    ...oldData.unit,
    _roleplay_obsolete: true,
    _roleplay_age: Math.floor((now - oldData.timestamp) / 1000)
  };
}
```

**Styles** :
```css
.atak-unit-obsolete {
  opacity: 0.6;
  filter: grayscale(30%);
  animation: atak-fade-pulse 2s ease-in-out infinite;
}

@keyframes atak-fade-pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 0.4; }
}

.atak-obsolete-badge {
  background: rgba(251, 191, 36, 0.2);
  color: #f59e0b;
  animation: atak-blink 2s ease-in-out infinite;
}
```

### Disparition temporaire

**Comportement** :
- Si packet loss > 10%, unités peuvent disparaître complètement
- Probabilité : `packet_loss / 2` %
- Marqueur devient invisible (`opacity: 0`)
- Simule perte totale de tracking

**Code** :
```javascript
// Simuler disparition temporaire (packet loss élevé)
if (this.config.packetLoss > 10 && Math.random() * 100 < (this.config.packetLoss / 2)) {
  return {
    ...unit,
    _roleplay_hidden: true
  };
}
```

```css
.atak-unit-hidden {
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.5s ease-out;
}
```

---

## 3️⃣ Déconnexions complètes côté mod

### Concept

**Vraies coupures réseau** :
- Tous les envois HTTP sont bloqués
- Durée aléatoire configurable
- Intervalle entre coupures configurable
- Notifications en jeu

### Architecture

**État persistant** :
```sqf
COMSPEC_NetworkDisconnectState = {
  is_disconnected: false,
  disconnect_until: -1,
  next_disconnect_at: time + 600,
  disconnect_count: 0
}
```

**PFH de gestion** :
```sqf
// Dans XEH_postInit.sqf
[{
    [] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;
}, 5, []] call CBA_fnc_addPerFrameHandler; // Toutes les 5s
```

### Workflow

```
1. Temps de jeu = 600s (10 min)
   ↓
2. Déclenche déconnexion
   ↓ Durée aléatoire : 15s (entre 5-30s)
   ↓
3. État : is_disconnected = true
   ↓ disconnect_until = time + 15
   ↓
4. fn_updatePosition vérifie isNetworkDisconnected()
   ↓ → true : exitWith (pas d'envoi)
   ↓
5. Notification : "Perte de liaison ATAK (15s)"
   ↓ LinkState = "offline"
   ↓
6. Temps écoulé : 15s
   ↓
7. État : is_disconnected = false
   ↓ next_disconnect_at = time + 600
   ↓
8. Notification : "Liaison ATAK rétablie"
   ↓ LinkState = "linked"
   ↓
9. Retour à l'étape 1
```

### Fonctions

**fn_simulateNetworkDisconnect.sqf** :
- Appelée par PFH toutes les 5s
- Vérifie si on est en déconnexion
- Vérifie si fin de déconnexion
- Vérifie si temps de déclencher nouvelle coupure
- Calcule durée aléatoire
- Met à jour l'état
- Envoie notifications

**fn_isNetworkDisconnected.sqf** :
- Retourne `true` si déconnecté
- Utilisée par `fn_updatePosition` pour bloquer envois
- Vérifie aussi le timing (sécurité)

**fn_getNetworkDisconnectInfo.sqf** :
- Retourne HashMap avec détails complets
- Utilisé pour afficher compte à rebours
- Debug et monitoring

### Intégration dans fn_updatePosition

```sqf
// Avant tout traitement
if (!_force && {[] call comspec_overwatch_connect_fnc_isNetworkDisconnected}) exitWith {
    ["network_disconnected_roleplay"] call _fnc_skip;
    
    // Hint informatif (une seule fois)
    if (!(missionNamespace getVariable ["COMSPEC_DisconnectHintShown", false])) then {
        missionNamespace setVariable ["COMSPEC_DisconnectHintShown", true, false];
        private _info = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
        private _remaining = _info get "remaining_seconds";
        hintSilent format ["Liaison ATAK perdue - Reconnexion dans %1s", _remaining];
    };
};
```

### Configuration future

**Actuellement** : Valeurs codées en dur
- Durée min : 5 secondes
- Durée max : 30 secondes
- Intervalle : 600 secondes (10 minutes)

**À venir** : Configuration via interface admin
- Ajout de champs dans `/admin/atak/roleplay`
- Synchronisation avec paramètres BDD existants
- Lecture depuis profil CBA ou variables de mission

### Logs

```
[COMSPEC Roleplay] Déconnexion simulée déclenchée: 15 secondes (occurrence #1)
[COMSPEC] UpdatePosition skip: network_disconnected_roleplay (state=10)
[COMSPEC Roleplay] Déconnexion terminée après 15 secondes
```

---

## 📊 Résumé des améliorations

| Fonctionnalité | Avant | Maintenant |
|----------------|-------|------------|
| **Packet loss** | Indicateur vide ou simulé | Mesure réelle depuis mod |
| **Positions obsolètes** | Jamais affichées | Réutilisées avec badge temporel |
| **Marqueurs instables** | Statiques | Animation de saut |
| **Disparitions** | Jamais | Temporaires si perte élevée |
| **Déconnexions** | Serveur uniquement (503) | Mod bloque tous envois |
| **Notifications** | Messages web | Hints en jeu + LinkState |

---

## 🎮 Impact sur le gameplay

### Scénario : Opération en zone hostile

**Configuration admin** :
- Packet loss : 10%
- Déconnexions : 20-40s toutes les 5 min
- Mode : hostile

**Expérience joueur** :

**T=0min** : Mission commence, liaison normale

**T=5min** : Première déconnexion
```
[In-game hint] "Perte de liaison ATAK (27s)"
→ Hub ATAK affiche "offline"
→ Aucune position n'est envoyée
→ Sur le web, l'unité reste à sa dernière position connue
→ Badge "Données obsolètes (15s)" apparaît progressivement
```

**T=5min27s** : Reconnexion
```
[In-game hint] "Liaison ATAK rétablie"
→ Hub affiche "linked"
→ Position envoyée immédiatement
→ Badge disparaît sur le web
```

**T=5min30s** : Packet loss naturel
```
→ Quelques positions ne sont pas reçues (10%)
→ Marqueurs "sautent" sur la carte web
→ Anciennes positions réutilisées avec badge temporel
→ Indicateur : "8.5 % (mesuré)"
```

**T=10min** : Deuxième déconnexion
```
[Cycle se répète]
```

### Effet psychologique

1. **Incertitude** : Le commandement ne sait plus si les positions sont actuelles
2. **Prise de décision** : Faut-il attendre des données fraîches ou agir ?
3. **Communication radio** : Importance accrue de la voix (TFAR/ACRE)
4. **Discipline** : Respect des procédures de liaison dégradée

---

## 🔧 Maintenance et debug

### Variables de debug

```sqf
// Activer logs détaillés
missionNamespace setVariable ["COMSPEC_Debug_PacketLoss", true, false];

// Résultat dans RPT :
[COMSPEC] Position update callback: success=true, requestId=pos_123.45, httpCode=200
```

### Inspection d'état

```sqf
// État déconnexion
private _info = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
hint format ["Déco: %1, Restant: %2s", _info get "is_disconnected", _info get "remaining_seconds"];

// Stats packet loss
private _stats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
hint format ["Loss: %1%%, Sent: %2, Rcv: %3", 
    _stats get "packet_loss_percent",
    _stats get "packets_sent_window",
    _stats get "packets_received_window"
];
```

### Reset manuel

```sqf
// Forcer fin de déconnexion
private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
_state set ["is_disconnected", false];
_state set ["disconnect_until", -1];

// Reset compteurs packet loss
missionNamespace setVariable ["COMSPEC_PacketStats", createHashMap, false];
```

---

## 📈 Métriques et monitoring

### Données collectées

**Par joueur** :
- Packet loss mesuré (%)
- Nombre de paquets envoyés (fenêtre)
- Nombre de paquets reçus (fenêtre)
- Nombre de déconnexions subies
- Durée totale déconnecté

**Agrégées** :
- Moyenne de packet loss de l'équipe
- Unités actuellement déconnectées
- Positions obsolètes affichées

### Dashboard futur

Interface admin prévue :
- Graphique packet loss temps réel
- Liste des unités en déconnexion
- Historique des coupures
- Comparaison mesuré vs simulé

---

## ✅ Checklist de test

### Mesure packet loss

- [ ] Vérifier compteur `total_sent` augmente
- [ ] Vérifier compteur `total_received` augmente
- [ ] Vérifier calcul `(sent - received) / sent * 100`
- [ ] Vérifier affichage "(mesuré)" dans UI web
- [ ] Vérifier tooltip avec détails
- [ ] Simuler perte serveur → vérifier loss augmente
- [ ] Attendre 5 min → vérifier nettoyage fenêtre

### Effets visuels

- [ ] Configurer packet loss 10% → observer marqueurs sauter
- [ ] Perdre paquet → vérifier position obsolète affichée
- [ ] Vérifier badge "Données obsolètes (Xs)"
- [ ] Vérifier animation fade-pulse
- [ ] Configurer packet loss 20% → observer disparitions
- [ ] Vérifier classe `atak-unit-hidden` appliquée
- [ ] Attendre 30s → position obsolète disparaît

### Déconnexions mod

- [ ] Activer roleplay CBA → attendre 10 min
- [ ] Vérifier hint "Perte de liaison ATAK (Xs)"
- [ ] Vérifier LinkState = "offline" dans hub
- [ ] Essayer envoyer position → vérifier bloquée
- [ ] Vérifier log RPT "network_disconnected_roleplay"
- [ ] Attendre fin → vérifier hint "rétablie"
- [ ] Vérifier LinkState = "linked"
- [ ] Vérifier position envoyée immédiatement après
- [ ] Attendre intervalle → vérifier nouvelle déco

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
