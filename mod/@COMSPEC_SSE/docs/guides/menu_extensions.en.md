# « SSE ROLEPLAY · Extensions » menu

French version: [menu_extensions.md](menu_extensions.md). Guides index: [index.en.md](index.en.md). Creating an extension: [extensions_documents.en.md](extensions_documents.en.md).

## What it is for

See the **document extensions** loaded on the server (third-party addons that add document types: press card, pass…) and **enable or disable** each one for the session. A disabled extension no longer offers its types in « Choisir un document » (Choose a document), the Eden Papiers (Papers) attribute, the SSE editor and the Area AI page; documents already handed out remain readable.

## Where to find it

| Context | Path |
|---|---|
| In game | Esc › **SSE ROLEPLAY · Extensions** (window) |
| CBA settings | Esc › Options › Addon Options › category **SSE ROLEPLAY · Extensions**: one switch per extension (server side, synchronised to clients) |
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

Status line at the bottom of the window (« Extension désactivée : ses 2 modèles ne sont plus proposés » (Extension disabled: its 2 templates are no longer offered)); no Arma hint. **Fermer** (Close) button, Esc closes.

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
