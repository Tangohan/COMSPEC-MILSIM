# « SSE ROLEPLAY · Extensions » menu

French version: [menu_extensions.md](menu_extensions.md). Guides index: [index.en.md](index.en.md). Creating an extension: [extensions_documents.en.md](extensions_documents.en.md).

## What it is for

See the **document extensions** loaded on the server (third-party addons that add document types: press card, pass…) and **enable or disable** each one for the session. A disabled extension no longer offers its types in « Choisir un document » (Choose a document), the Eden Papiers (Papers) attribute, the SSE editor and the Area AI page; documents already handed out remain readable.

## Where to find it

| Context | Path |
|---|---|
| In game | Esc › **SSE ROLEPLAY · Extensions** (window) |
| CBA settings | Esc › Options › Addon Options › category **SSE ROLEPLAY · Extensions**: extensions served (yes / no), list of extensions switched off at start, Zeus announcement (server side, synchronised to clients) |
| Zeus | ZEN › COMSPEC · Identité (COMSPEC · Identity) › **Extensions** |
| Eden | Menu bar › COMSPEC SSE › **Extensions** |

## The window

List of detected extensions (`CfgComspecDocumentExtensions`); for each one:

| Column | Example |
|---|---|
| **Nom** (Name) | Documents d'exemple (Example documents) |
| **Auteur** (Author) | COMSPEC |
| **Version** (Version) | 1.0.0 |
| **Modèles** (Templates) | 2 (press card, pass) |
| **Aperçu** (Preview) | the extension's `preview` texture |
| **Switch** Activée / Désactivée (Enabled / Disabled) | server state, applied to all clients |

Status line at the bottom of the window (two lines at most, smaller font if needed); no Arma hint. **Enable / Disable** and **Close** buttons, Esc closes. The list cuts a long name with « … » (full name in the tooltip); the card (description, templates) scrolls when long.

**Documents d'exemple** (press card, pass) ships with the SSE and is **off by default**: enable it to try it, disable it afterwards if needed (choice kept on the server).

**No extension detected**: instead of the two panels, the window explains that the built-in papers stay available, what an extension is and how to load one, with an **Open the tutorial** button.

## Built-in tutorial « How it works »

**Help** button in the header (any time; the same button, renamed **Extensions**, goes back to the list). Steps on the left, scrolling reading panel on the right:

1. What is an extension? 2. Enable or disable. 3. The shipped example. 4. Make your addon (`$PBOPREFIX$`, `requiredAddons` with `comspec_sse_identity`). 5. The minimal config.cpp (commented « Firearms permit » type, **Copy the example** button: clipboard, confirmation on the status line). 6. Fields of a type. 7. Textures (PAA, powers of two, `_co` / `_ca`). 8. Pack and load (`-mod=`). 9. Test. 10. Common problems.

The same content is in the SSE manual (chapter **Document extensions**, « ? » button).

## Step by step: disabling the press card for a military mission

1. Esc › **SSE ROLEPLAY · Extensions**.
2. Row « Documents d'exemple »: switch to **Désactivée** (you must be Zeus or an administrator; otherwise the window is read-only).
3. Status line: the extension is disabled; all clients see the change.
4. Zeus › **Donner des papiers** (Give papers): the press card no longer appears in the type list.

## Common pitfalls

- The switch acts **server side**: a client who toggles it without rights changes nothing (read-only).
- An extension missing on the server but present on a client does not appear: both must load the same addon.
- Disabling an extension **does not remove** documents already handed out: they remain readable and checkable.
- The **templates** count counts the types (`documents[]`), not the copies (`_001`…).
- A badly declared extension (without `CfgComspecDocumentExtensions`) has its types offered without an extension name and does not appear in the list: add the class.

## Athena

No direct link: Athena recognises the types by their class when a check or a record reaches it. The enabled / disabled state is not sent.
