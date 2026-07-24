# Mode Troll ATAK Enhanced

Le **Mode Troll** est une fonctionnalité optionnelle qui force les joueurs à valider des captcha, tests anti-robot et autres vérifications absurdes avant d'accéder à l'ATAK Enhanced.

## Vue d'ensemble

Quand activé, le joueur qui ouvre l'ATAK Enhanced (touche `K`) peut être confronté à un écran de "vérification de sécurité" qui bloque l'accès au menu principal jusqu'à ce qu'il résolve un défi complètement stupide.

**C'est un mode 100% troll** destiné aux sessions de jeu détendues ou aux événements spéciaux.

## Configuration

### CBA Setting

**Paramètre** : `comspec_overwatch_troll_mode`

**Valeurs** :
- `0` : **Désactivé** (par défaut)
- `1` : **Occasionnel** (10% de chance à chaque ouverture)
- `2` : **Fréquent** (40% de chance)
- `3` : **Systématique** (100%, toujours déclenché)

**Cooldown** : 60 secondes minimum entre deux captchas (pour éviter le spam)

## Types de captcha

Le système choisit **aléatoirement** parmi 7 types de défis :

### 1. Vérification de l'âge

```
═══════════════════════════════════════
    Vérification de l'âge requise
═══════════════════════════════════════

Pour des raisons de sécurité, veuillez
confirmer votre date de naissance :

Né(e) le : 17 Juillet 1993

Cette information sera utilisée
conformément à notre politique de
confidentialité (2847 pages).

[Oui, c'est exact]
[Non, ce n'est pas ma date de naissance]
[Je ne me souviens plus]
```

**Particularité** : La date est générée aléatoirement (entre 18 et 80 ans).

### 2. Test Anti-Robot

Questions absurdes style :
- "Êtes-vous un robot ?" (réponses : Oui / Non / Peut-être / Je ne suis pas sûr)
- "Cochez TOUTES les cases qui ne contiennent PAS de véhicule militaire"
- "Combien font 2+2 en base 10 ?" (réponses : 4 / 22 / Poisson / La réponse D)
- "Tapez le mot 'ATAK' sans faute"

### 3. Vérification Cognitive Avancée (Maths impossibles)

Équations complexes comme :
- `∫₀^∞ e^(-x²) dx = ?`
- `log₂(1024) × sin(90°) = ?`
- "Si un train roule à 80 km/h et qu'il est 14h37, de quelle couleur est le cheval blanc d'Henri IV ?"

### 4. Sélection d'Images de Sécurité

```
════════════════════════════════════════
   Sélection d'Images de Sécurité
════════════════════════════════════════

Cliquez sur TOUTES les cases contenant un
         FEU TRICOLORE

[🚗] [🚦] [🏠]
[🌳] [🚦] [☁️]
[🚙] [🏢] [🚦]

Si aucune case ne correspond, cliquez sur 'Passer'
```

**Note** : Les feux tricolores sont aux cases 2, 5 et 9 (mais c'est une simulation texte).

### 5. Conditions Générales d'Utilisation

Texte dense et illisible avec des clauses absurdes :

```
Article 1.2.3.b - L'utilisateur reconnaît avoir lu
et accepté l'intégralité des 847 pages de conditions
générales, incluant mais ne se limitant pas aux
clauses 12.4.7.a concernant la collecte de données
biométriques, la revente de son âme à des tiers
non spécifiés, et l'utilisation de ses données de
géolocalisation à des fins publicitaires ciblées...

[J'ai tout lu et j'accepte]
[Je n'ai pas lu mais j'accepte quand même]
[Défiler jusqu'en bas puis accepter]
```

### 6. Questions de Sécurité Obligatoires

Questions impossibles à répondre :
- "Quel est le nom de jeune fille de la mère de votre arrière-grand-père paternel ?"
- "Combien de fenêtres aviez-vous dans votre maison quand vous aviez 7 ans ?"
- "Quelle est la couleur préférée de votre animal de compagnie imaginaire ?"

### 7. Puzzle de Vérification

```
═════════════════════════════════════
      Puzzle de Vérification
═════════════════════════════════════

Faites glisser les pièces pour
reconstituer l'image

[3][1][2]
[6][4][5]
[8][7][_]

Astuce : la case vide doit être en bas à droite

[Déplacer 7] [Déplacer 8]
[Réinitialiser] [C'est bon !]
```

## Mécanisme de validation

### Réponse correcte

Quand le joueur donne **la bonne réponse**, il a :
- **70% de chance** : Validation réussie → accès au Hub
- **30% de chance** : **TROLL** → Rejeté même si correct → Nouveau captcha

Messages de succès (exemples) :
- "Validation réussie !"
- "Bravo ! Vous n'êtes probablement pas un robot."
- "Accès accordé. Votre session expirera dans 30 secondes."
- "Tentatives : 3 · Record : 1 · Vous êtes 250% plus lent que la moyenne"

### Réponse incorrecte

Messages d'erreur variés :
- "Réponse incorrecte. Veuillez réessayer."
- "Mauvaise réponse. Êtes-vous sûr de ne pas être un robot ?"
- "Le système a détecté une incohérence dans votre réponse."

→ Nouveau captcha affiché après 1.5 secondes.

### Messages d'erreur troll (même si correct)

Quand le système rejette volontairement une bonne réponse :
- "Erreur 418 : Je suis une théière."
- "Réponse trop rapide. Êtes-vous un robot ?"
- "Cette réponse est correcte dans 47% des cas. Pas celui-ci."
- "Le serveur de validation est temporairement indisponible. Réessayez dans 3... 2... 1... Maintenant !"
- "Tentative de connexion depuis un appareil non reconnu. Captcha réinitialisé."

## Interface

Le dialog captcha (IDD **9950**) remplace temporairement le Hub ATAK Enhanced :

- **Fond noir** semi-transparent (92% opacité) qui bloque tout
- **Boîte blanche** au centre avec :
  - **En-tête bleu** avec logo militaire
  - **Titre** (variable selon le type)
  - **Message** central formaté
  - **2 à 4 boutons** selon le captcha
  - **Footer** : "Cette vérification permet de garantir la sécurité de votre connexion ATAK"
  - **Liens fake** : "Politique de confidentialité · Conditions d'utilisation · Cookies"

### Effets sonores

- **Échec** : `FD_CP_Not_Clear_F` (son d'échec de checkpoint)
- **Succès** : `FD_Finish_F` (son de réussite)
- **Début** : `FD_Start_F` (son de démarrage)

### Animation

Effet fade-in progressif du message (50ms par step, de 0% à 100% opacité).

## Flux complet

1. **Joueur appuie sur K** pour ouvrir l'ATAK Enhanced
2. **fn_shouldShowTrollCaptcha** vérifie le niveau troll + cooldown
3. Si déclenché :
   - **Hub se ferme** immédiatement (500ms après ouverture)
   - **fn_showTrollCaptcha** choisit un type aléatoire
   - **Dialog 9950** s'ouvre avec le défi
4. **Joueur clique** sur un bouton
5. **fn_validateTrollCaptcha** vérifie la réponse
6. Si **succès** (et pas de troll) :
   - Hint de félicitations
   - **Hub se réouvre** après 1 seconde
7. Si **échec** ou **troll** :
   - Hint d'erreur
   - **Nouveau captcha** après 1.5-2 secondes
8. **Répéter 6-7** jusqu'à succès

## Compatibilité

- **Compatible** avec le mode roleplay (peut se combiner)
- **Compatible** avec les effets visuels (glitches, déconnexions)
- **Compatible** avec le système de dommages ATAK (le captcha apparaît AVANT la vérification d'écran cassé)

## Cas d'usage

### Mode Occasionnel (Niveau 1)

Bon pour ajouter une touche d'humour **rare** sans trop gêner le gameplay. Le joueur tombe dessus une fois toutes les 10 ouvertures environ.

### Mode Fréquent (Niveau 2)

Pour des missions "funny" ou des événements communautaires. Le joueur doit régulièrement "prouver qu'il n'est pas un robot" pour accéder à son ATAK.

### Mode Systématique (Niveau 3)

**ATTENTION** : Ce mode est **très chiant**. Chaque ouverture du Hub déclenche un captcha (avec cooldown de 60s).

À réserver pour :
- Des sessions de test/démo
- Des punitions humoristiques
- Des événements spéciaux où c'est assumé

## Notes techniques

### Variables globales

- `COMSPEC_TrollCaptchaData` : HashMap contenant le captcha actuel (type, titre, message, boutons, réponse correcte)
- `COMSPEC_TrollCaptchaAttempts` : Compteur de tentatives (réinitialisé au succès)
- `COMSPEC_LastTrollCaptchaTime` : Timestamp du dernier captcha (pour le cooldown)

### Fonctions SQF

| Fonction | Rôle |
|----------|------|
| `fn_shouldShowTrollCaptcha` | Détermine si on doit afficher un captcha (probabilité + cooldown) |
| `fn_showTrollCaptcha` | Génère un captcha aléatoire, stocke les données, ouvre le dialog |
| `fn_validateTrollCaptcha` | Vérifie la réponse, gère le troll, affiche succès/échec |
| `fn_updateTrollCaptchaDisplay` | Met à jour le contenu du dialog avec les données du captcha |

### Display

- **IDD** : 9950
- **IDC Title** : 9951
- **IDC Message** : 9952
- **IDC Buttons** : 9961, 9962, 9963, 9964

## Exemples de combinaisons

### Roleplay + Troll Léger

```sqf
comspec_overwatch_roleplay_enabled = true;
comspec_overwatch_troll_mode = 1; // Occasionnel
```

Le joueur a des effets réseau réalistes **ET** occasionnellement un captcha absurde.

### Full Chaos

```sqf
comspec_overwatch_roleplay_enabled = true;
comspec_overwatch_roleplay_network_failures = true;
comspec_overwatch_atak_realism = 3; // Destruction complète
comspec_overwatch_troll_mode = 3; // Systématique
```

Le joueur doit :
1. Ne pas être blessé au torse (sinon ATAK détruit)
2. Ne pas être dans une zone sans couverture
3. Valider un captcha à chaque ouverture
4. Subir des déconnexions réseau aléatoires

**Résultat** : MILSIM absolu ou chaos total selon le point de vue 😂

## Désactivation rapide

Pour désactiver en urgence pendant une session :

```sqf
// Dans la console debug Arma (ou via Zeus)
missionNamespace setVariable ["comspec_overwatch_troll_mode", 0];
```

Ou via les **CBA Settings** in-game (Échap → Addon Options → COMSPEC Overwatch — Roleplay).

## Fichiers impliqués

### SQF
- `functions/fn_shouldShowTrollCaptcha.sqf`
- `functions/fn_showTrollCaptcha.sqf`
- `functions/fn_validateTrollCaptcha.sqf`
- `functions/fn_updateTrollCaptchaDisplay.sqf`

### Configuration
- `display_troll_captcha.hpp` : Définition du dialog 9950
- `config.cpp` : Déclaration des fonctions + include du display
- `XEH_preInit.sqf` : CBA setting `comspec_overwatch_troll_mode`

### Intégration
- `display_hub.hpp` : Appel de `fn_shouldShowTrollCaptcha` dans le `onLoad`

## FAQ

**Q : Peut-on skip le captcha ?**  
R : Non, le dialog est bloquant (pas d'ESC, pas de bouton fermer). Il faut répondre.

**Q : Combien de tentatives maximum ?**  
R : Illimité. Le compteur augmente juste pour le message final.

**Q : Le captcha peut-il se cumuler avec un écran cassé ?**  
R : Oui. Si l'ATAK est éteint/cassé, le captcha s'affiche quand même (pour troller encore plus).

**Q : Est-ce compatible multijoueur ?**  
R : Oui, chaque joueur a son propre captcha indépendant (client-side).

**Q : Les réponses sont-elles sauvegardées ?**  
R : Non, tout est en mémoire temporaire (session Arma).

---

**Bon courage aux joueurs !** 🤖
