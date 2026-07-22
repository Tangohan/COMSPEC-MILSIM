# Radio proximité — Overwatch / Athena ATAK

## Résumé

Surveillance des **émissions radio** autour d’un opérateur (métadonnées BFT).  
**Aucune lecture audio dans le navigateur** : l’écoute d’un réseau se fait **en jeu** (même canal radio). Sur `/atak`, vous suivez **qui émet** (pastilles, liste, alertes).

## Avec / sans module radio

| | ACRE2 | TFAR | Absent |
|--|-------|------|--------|
| Pastille « Émet » | Oui | Oui | Non |
| Canal / réseau | Oui | Fréq. SW | — |
| Liste proximité | Oui | Oui | Message « Module radio non détecté » |
| Surveiller le réseau (jeu) | Bascule canal (ou écoute spectateur ACRE) | Message + réglage manuel | Impossible |
| Surveiller le réseau (web `/atak`) | Suivi canal : highlight, toast + bip, badge « À l’écoute » | Idem | Impossible |

## Où regarder

1. **Tacmap** `/atak` — marqueurs, Effectifs, onglet **Radio**
2. **Tablette Overwatch** — pastilles contacts, vue **Radio** → *Surveiller ce réseau* (bascule audio en jeu)

## CBA (COMSPEC Overwatch)

- Surveillance radio à proximité (on/off)
- Rayon (m) — défaut 75
- Intervalle scan (s) — défaut 2

## Données remontées (`extra` position)

`radio_speaking`, `radio_tx`, `radio_channel`, `radio_net`, `radio_module`, `radio_id` (+ `radio_freq` existant)

## Web `/atak` — ce qui marche

- Pastille orange **Émet** sur marqueurs BFT et cartes Effectifs (poll units)
- Onglet **Radio** : liste proximité (distance, canal, indicatif), rayon configurable, filtre « Émissions uniquement »
- **Surveiller ce réseau** : abonnement au canal → bandeau « À l’écoute », surbrillance des contacts du canal, toast + bip à chaque **démarrage** d’émission (sons ATAK, sauf Muet)
- Pas de voix dans le navigateur (pas de WebRTC)

## Retest rapide

1. ACRE + PTT → pastille orange « Émet » sur `/atak` (marqueur + Effectifs + onglet Radio)
2. Sans ACRE → bandeau module non détecté
3. Web → Radio → Surveiller ce réseau → badge « À l’écoute » ; un autre joueur PTT sur ce canal → toast « … émet »
4. Tablette → Surveiller ce réseau → notification + canal actif (audio en jeu)
5. Changer le rayon (UI web ou CBA) → liste proximité filtrée
