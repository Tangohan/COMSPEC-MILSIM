# Translating COMSPEC ATAK into your language

*Version française plus bas.*

**Easiest way: [athena-comspec.eu/traduire-atak](https://athena-comspec.eu/traduire-atak).** Translate in your browser, with
automatic checks, then download the finished mod (PBO, mod.cpp, Workshop preview and description) as a zip. No
account, no tools, nothing sent to the server. The steps below are the manual way.

COMSPEC ATAK Native ships in French and English. Any other language can be added by the community as a
small separate mod, published on the Steam Workshop by its translators. The phone picks it up by itself:
the new language appears in **Settings > Language** on the phone and in **Esc > Options > Addon Options >
COMSPEC > Langue / Language**. No change to COMSPEC is needed and you don't have to wait for our releases.

What a translation mod covers: every screen of the ATAK phone, its notifications and messages, the
COMSPEC addon options, the screens of the SSE mod and of the Overwatch connection, the few texts written in
English in the code, and the names of items (arsenal) and of Eden and Zeus modules of the ATAK, the SSE mod and
the Overwatch connection. Item and module names follow the **game language**: they show in your language when
Arma 3 itself runs in it (the mod ships a `stringtable.xml` with your language column, see step 2).

## 1. Translate the file

1. Copy [`template/xx.txt`](template/xx.txt) and name it after your language code, for example `es.txt`.
2. Each line is one sentence of the phone:

   ```
   ["Batterie faible", "", "Low battery"],
   ```

   The first text is the text as written in the code, usually French (the key: **never change it**), the
   second is your translation (empty for now), the third is the English version, for reference only. Fill in
   the second text:

   ```
   ["Batterie faible", "Batería baja", "Low battery"],
   ```

3. You don't need to translate everything at once. A sentence left empty is shown in English.
4. Rules:
   - Keep `%1`, `%2`… : they are replaced by values (a distance, a name, a time). You can move them
     inside the sentence. Keep `%%` as is (it shows a `%` sign).
   - Inside a text, a double quote is written twice: `""`.
   - Keep the commas between lines, the `[` at the top and the `]` at the bottom of the file.
   - Save the file as UTF-8.

## 2. Build the mod

1. Copy the [`example/comspec_atak_lang_es`](example/comspec_atak_lang_es) folder and rename it with
   your language code (`comspec_atak_lang_de`, `comspec_atak_lang_it`…).
2. In that folder, replace `es`, `Español` and `Spanish` in `config.cpp` and `$PBOPREFIX$` with your
   language (code, name in your language, and the Arma game language name: German, Italian, Polish,
   Portuguese, Russian, Czech, Turkish…). Put your translated file next to `config.cpp`.
3. Item and Eden/Zeus module names: run
   `python3 mod/COMSPEC_ATAK_Native/tools/i18n.py community path/to/es.txt --game Spanish` (the Arma game
   language name). It writes a `stringtable.xml` next to your file; put it next to `config.cpp` too. The site
   adds it by itself to the zip when the game language is set. Without it, these names stay in English.
4. Pack the folder into a PBO with Arma 3 Tools > Addon Builder (or any PBO packer), inside a mod folder:

   ```
   @COMSPEC_ATAK_Espanol/
       addons/comspec_atak_lang_es.pbo
       mod.cpp            (optional: name and picture in the launcher)
   ```

5. Sign the PBO with your own key (DSUtils in Arma 3 Tools) and ship the `.bikey` in a `keys` folder:
   servers that check signatures only accept mods whose key they have. Ask server admins to add it.
6. Publish with Arma 3 Tools > Publisher and set **COMSPEC ATAK Native** as a required item.

To test: load COMSPEC ATAK Native and your mod, open the phone, Settings > Language, pick your language.

## 3. Keep it up to date

Every COMSPEC version may add new sentences. They are shown in English until you translate them. To
update your file (needs Python 3 and a copy of the COMSPEC repository):

```
python3 mod/COMSPEC_ATAK_Native/tools/i18n.py community path/to/es.txt
```

It keeps your translations, adds the new sentences with an empty translation, removes the ones that no
longer exist, refreshes the English column and prints how many sentences are translated. It also warns
when a translation lost a `%1`.

Without Python: take the new `template/xx.txt` and copy your translations over, sentence by sentence.

---

# Traduire COMSPEC ATAK dans une autre langue

**Le plus simple : [athena-comspec.eu/traduire-atak](https://athena-comspec.eu/traduire-atak).** Traduction dans le
navigateur avec vérification automatique, puis mod complet (PBO, mod.cpp, aperçu et description Workshop) téléchargé
en zip. Sans compte ni outils, rien n'est envoyé au serveur. Les étapes ci-dessous sont la méthode manuelle.

Le mod est livré en français et en anglais. Toute autre langue s'ajoute par un petit mod séparé que ses
traducteurs publient sur le Workshop. Le téléphone le détecte tout seul : la langue apparaît dans
**Réglages > Langue** du téléphone et dans **Échap > Options > Options des addons > COMSPEC > Langue /
Language**, sans modifier COMSPEC.

Couvert : tous les écrans du téléphone, notifications et messages, les options COMSPEC, les écrans SSE et
de connexion Overwatch, les quelques textes écrits en anglais dans le code, et les noms d'objets (arsenal) et de
modules Eden/Zeus de l'ATAK, du SSE et de la connexion Overwatch. Ces noms suivent la **langue du jeu** : ils
s'affichent dans la langue du mod quand Arma 3 tourne dans cette langue (`stringtable.xml` livrée avec le mod).

1. Copier `template/xx.txt` sous le code de la langue (`es.txt`) et remplir la 2e colonne de chaque ligne
   `["français", "traduction", "anglais"]`. Ne jamais toucher la 1re colonne. Une traduction vide
   s'affiche en anglais. Garder `%1`, `%2`, `%%`, doubler les guillemets (`""`), enregistrer en UTF-8.
2. Copier `example/comspec_atak_lang_es`, adapter le code, le nom et la langue du jeu dans `config.cpp`
   et `$PBOPREFIX$`, poser le fichier traduit à côté (et la `stringtable.xml` écrite par
   `tools/i18n.py community es.txt --game Spanish` pour les noms d'objets et de modules), empaqueter en PBO (Addon Builder), signer
   (DSUtils, `.bikey` dans `keys/`), publier avec Publisher en dépendance de COMSPEC ATAK Native.
3. Mise à jour à chaque version : `python3 mod/COMSPEC_ATAK_Native/tools/i18n.py community es.txt`.

Pour l'équipe COMSPEC : après chaque ajout au dictionnaire anglais (`tools/i18n.py merge`), relancer
`python3 tools/i18n.py community` (sans fichier) pour régénérer `template/xx.txt`, `template/categories.json` et
`template/stringtable.json` (clés des stringtables de l'ATAK, du SSE et d'Overwatch connect, lues par le site). Pour
trouver les textes français affichés sans traduction : `python3 tools/i18n.py extract` (et `--sse`, `--connect`).
