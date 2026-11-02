# COMSPEC SSE — historique des versions

## 1.1.0 — 03/11/2026 · Contrôle d'identité

**Relancer Arma complètement.** Niveaux en ligne (documents Athena, base Athena, transmission) : COMSPEC Link 2.0.118
ou plus (pack ATAK 3.0.0). Avant : tout fonctionne hors ligne (base locale, file d'attente).

- **Papiers d'identité fictifs** : carte d'identité, passeport, permis de conduire de quatre pays imaginaires (Altis,
  Tanoa, Livonie, Malden), marqués SIMULATION. Bande MRZ ICAO et puce NFC simulées.
- **Contrôle par ACE** : demander les papiers, examiner un document (visionneuse recto / verso, zoom), fouiller les
  papiers d'une personne morte, menottée, rendue ou inconsciente, saisir et rendre, contrôler les occupants d'un
  véhicule, faire descendre.
- **Joueur à joueur** : demande sur l'ATAK, réponse par le menu ACE personnel « Mes papiers » (présenter, remettre,
  refuser).
- **IA** : les civils présentent leurs papiers, les insurgés de faux papiers, une HVT sa couverture, un recherché armé
  refuse ou s'enfuit.
- **App « Contrôle ID » de l'ATAK** : contrôle par niveaux (visuel, MRZ, puce, base), verdict, alerte discrète,
  historique, transmission à Athena, fiche FRM pré-remplie.
- **Eden** : catégorie « COMSPEC SSE — Papiers » (papiers portés, pays, nature, qualité des faux).
- **Zeus** : donner des papiers, signaler volé / révoquer, voir les vrais papiers.
- **Biométrie** : la vraie identité et les faux papiers réglés dans Eden ou par Zeus ne sont plus visibles des joueurs
  (gardés sur le serveur).

## 1.0.0 — 20/10/2026

Version majeure : tout passe par le téléphone ATAK natif (voir STEAM_CHANGELOG.txt).
