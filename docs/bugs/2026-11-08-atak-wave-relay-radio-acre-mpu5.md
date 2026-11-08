# App Wave Relay : MPU-5 d'ACRE reliée mais ligne « Radio : téléphone seul (sans MPU5) », aucun message ACRE

## Contexte

ATAK natif 4.1.0 (Steam), pack S.O.A.R., ACRE 2.14. Le joueur porte l'objet `ACRE_MPU5` (« MPU-5 Persistent Systems » dans l'arsenal ACE). Signalement : « J'ai bien le MPU mais pas de message ACRE ». Capture : app Wave Relay, onglet MON NŒUD, téléphone en paysage, seul sur le réseau (RÉSEAU (1)).

## Symptôme

- Bandeau « Réseau bon 3.0 Mbit/s · Latence 80 ms · perte 0 % » ; facteur « MPU5 canal 1 (câble EUD) — 0 saut(s) · 3.0 Mbit/s ».
- « Passerelle : vous-même · Chef de groupe (Ma radio) », « Signal : Excellent (4/4) ».
- Puis « Radio : téléphone seul · portée 3.0 km (sans MPU5) » : contradiction avec le facteur juste au-dessus.
- Aucun texte sur ACRE ; cadenas « EN CLAIR » sans explication à l'écran.

## Cause

Prouvée par le code (main au 2026-11-08, f1f6ecfb5) :

1. **Deux sources pour la même radio.** Le facteur de débit vient de `fn_linkQuality`, qui lit `fn_mpuLink "status"` (liaison réaliste : radio reliée par câble, `kind` = `mpu`). La ligne « Radio » de `fn_pageWaveRelay` lisait `fn_mpu5 "source"`, qui renvoie **toujours `[]`** quand la liaison réaliste est active (`if (["active"] call comspec_atak_native_fnc_mpuLink) exitWith { [] };`). Le réglage `comspec_atak_native_mpu_real` est coché par défaut : la ligne tombait donc toujours dans « téléphone seul … (sans MPU5) », avec la portée de repli `COMSPEC_ATAK_MeshRange` (3 000 m, d'où « 3.0 km »).
2. **La MPU-5 d'ACRE était bien reconnue.** `ACRE_MPU5` est défini par `ACRE_MPU5.pbo` du pack S.O.A.R. (`CfgPatches Iceman_ACRE_MPU5`, auteur « Cole / Codex ») : `class ACRE_MPU5: ACRE_BaseRadio`, `acre_baseClass = "ACRE_MPU5"`, `Iceman_WR_isMPU5 = 1`, et instances `ACRE_MPU5_ID_1` … via la macro `MPU5_ID(n)` (`acre_baseClass = "ACRE_MPU5"`). L'objet est cherché dans `items player + assignedItems player` (uniforme, gilet, sac). Le nom contient « mpu5 » : `fn_mpu5 "gear"` le classe `RADIOMPU`, mod `acre`, et `fn_mpuLink "tick"` le relie seul par câble, d'où le facteur « MPU5 canal 1 (câble EUD) ».
3. **Les « messages ACRE » possibles, et pourquoi aucun n'était à l'écran :**
   - notification « Réseau par votre radio MPU-5 Persistent Systems » (`fn_mpuLink "tick"`, liaison automatique) : une seule fois, au moment de la liaison, souvent avant l'ouverture du téléphone ;
   - case « RELAIS ATAK » près du cadre d'émission d'ACRE2 (`fn_relayTxBadge`) : seulement si `comspec_atak_native_mpu_voice` est coché (**décoché par défaut**), la radio reliée à la MPU5 (pont, action ACE) et un correspondant relié joignable par le maillage. Ici : réglage décoché et joueur seul, elle ne pouvait pas apparaître ;
   - « Voix par le réseau » : aucun état n'était affiché nulle part, même désactivé ; l'onglet VOIX ne montrait que les radios et les émissions.
4. **Cadenas « EN CLAIR »** : `fn_meshKeys "link"` renvoie « clear » quand les clés du maillage sont désactivées (`comspec_atak_native_mesh_keys`, décoché par défaut). Le détail n'était que dans l'infobulle et disait seulement « Clés du maillage désactivées sur ce serveur ».

## Correctif

- `fn_mpu5 "isMpu"` : une seule règle de reconnaissance (nom contenant « MPU5 », `acre_baseClass` contenant « MPU5 », `Iceman_WR_isMPU5`, réglage `comspec_atak_native_mpu5_items`), utilisée par "has", "gear" et `fn_mpuLink`.
- `fn_mpu5 "detected"` : une seule source pour la radio affichée (liaison réaliste : radio de `fn_mpuLink` ; ancien modèle : "source"). La ligne « Radio » montre le nom lisible, la portée et la liaison (câble EUD, Wi-Fi, non reliée), plus « Objet détecté » (classe exacte, par exemple `ACRE_MPU5_ID_1 · radio ACRE2`). Le facteur de débit prend le même nom (« MPU-5 Persistent Systems · canal 1 (câble EUD) »). Elles ne peuvent plus se contredire.
- Avec ACRE2 : section « ACRE2 » sur MON NŒUD, « Radio ACRE détectée : MPU-5 Persistent Systems · portée 6.0 km » puis « Voix par le réseau : désactivée (réglage admin) » et la façon de l'activer (`fn_mpuVoice "summary"`). L'onglet VOIX commence par le même état et un bouton de pont quand le serveur l'autorise (même geste que l'action ACE).
- Cadenas : détails réécrits (« Chiffrement inactif par défaut, ce n'est pas une panne : un admin l'active avec le réglage « Clés du maillage » », « Clé … non chargée : touchez CHARGER LES CLÉS … ») et ligne qui explique « EN CLAIR » dans l'app.
- Aucune écriture chez ACRE2 : lectures de config et appels protégés par `fn_radioGuard` seulement.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_mpu5.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_mpuLink.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_mpuVoice.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_meshKeys.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/core/fn_linkQuality.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageWaveRelay.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt`
- `mod/COMSPEC_ATAK_Native/missions/COMSPEC_Autotest.Stratis/scripts/radio.sqf`, `init.sqf`
- `mod/COMSPEC_ATAK_Native/tools/ingame_test.ps1`, `tools/INGAME_TEST.md`

## Vérification

- `hemtt check` : seulement les 2 erreurs acceptées (`ctrlWebBrowserAction`).
- Autotest ajouté, non lancé : `tools/ingame_test.ps1 -Groupes acre`, étape `acre_radio_detectee` (ACRE_MPU5, 152, 343 ; captures `acre_radio_*` et `acre_voix_*`).
- Rien n'a été vérifié en jeu.

## Supposé, à confirmer en jeu

- Que le joueur jouait avec la liaison réaliste active (défaut du réglage). La capture le montre indirectement (facteur « câble EUD », passerelle « Ma radio »), le réglage du serveur n'a pas été lu.
- Que l'objet était bien devenu `ACRE_MPU5_ID_n` : sans importance pour la détection (les deux classes sont reconnues).
- Que `acre_api_fnc_getCurrentRadioList` liste la MPU-5 d'ACRE comme radio ACRE2 (elle hérite d'`ACRE_BaseRadio`) : sert à l'onglet VOIX, pas à la détection.
- Le « message ACRE » attendu par le joueur n'est pas connu avec certitude : les trois lectures possibles sont décrites plus haut.

## Statut

corrigé à vérifier en jeu
