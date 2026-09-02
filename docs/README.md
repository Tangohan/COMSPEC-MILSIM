ATHENA C2
DOCUMENTATION SYSTEM

Document: ATHENA C2 Documentation Portal
Reference: IDX-A3-01
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | IDX-A3-01      |
| Revision        | 1.0            |
| Status          | CONTROLLED     |
| Owner           | COMSPEC        |
| System          | ATHENA C2      |
| Last Review     | 2026-09-02     |
| Source of Truth | Git repository |

## Revision History

| Revision | Date       | Author  | Changes                        |
| -------- | ---------- | ------- | ------------------------------ |
| 1.0      | 2026-09-02 | COMSPEC | Initial controlled publication |

---

# ATHENA C2 DOCUMENTATION SYSTEM

Official references replace historical Markdown filenames. The executed code in this repository is the source of truth. Capability status is only authoritative in REG-A3-01.

```text
FM ATHENA C2
Doctrine d'emploi
        │
        ├── SOP ATHENA C2
        │   Procédures opérationnelles
        │
        ├── ATAK Operator Manual
        ├── ATHENA Administrator Manual
        │
        └── Technical Publications
             ├── System Architecture
             ├── COMSPEC Overwatch Technical Manual
             ├── Interface Control Document
             ├── Security Architecture
             ├── Deployment & Release
             └── Capability Registry
```

## Official publications

| Reference | Document | Audience |
| --------- | -------- | -------- |
| FM-A3-01  | [ATHENA C2 Field Manual](doctrine/FM-ATHENA-C2.md) | Tous |
| SOP-A3-01 | [ATHENA C2 SOP](sop/SOP-ATHENA-C2.md) | Opérations |
| ATP-A3-01 | [System Architecture](technical/ATP-ATHENA-SYSTEM-ARCHITECTURE.md) | Technique |
| TM-A3-11  | [COMSPEC Overwatch Technical Manual](technical/TM-COMSPEC-OVERWATCH.md) | Développeurs |
| ICD-A3-01 | [ATHENA–ATAK ICD](technical/ICD-ATHENA-ATAK.md) | Développeurs / intégrateurs |
| SEC-A3-01 | [Security Architecture](technical/SEC-ATHENA-C2.md) | Technique / sécurité |
| ATP-A3-11 | [Deployment & Release](technical/ATP-ATHENA-DEPLOYMENT.md) | Administrateurs système |
| TM-A3-21  | [ATAK Operator Manual](manuals/TM-ATAK-OPERATOR.md) | Opérateurs |
| TM-A3-31  | [ATHENA Administrator Manual](manuals/TM-ATHENA-ADMIN.md) | Administrateurs |
| REG-A3-01 | [Capability Registry](registry/REG-ATHENA-CAPABILITIES.md) | Responsables produit / techniques |

## How to read

| Need | Start here |
| ---- | ---------- |
| What the system is for | FM-A3-01 |
| Run a serial | SOP-A3-01 then TM-A3-21 |
| Configure a community | TM-A3-31 |
| Understand Arma ↔ API ↔ Tacmap | ATP-A3-01 then ICD-A3-01 |
| SQF / extension / CBA | TM-A3-11 |
| Is a feature actually fielded? | REG-A3-01 |

## Related corpora (not ATHENA C2 pubs)

| Corpus | Location | Notes |
| ------ | -------- | ----- |
| Portal user guide | [docs/utilisateur/](utilisateur/README.md) | Forum, RH, LMS, courrier… |
| Portal technical notes | [docs/technique/](technique/README.md) | PHP app, still valid as companions |
| Overwatch pages served on the website | `docs/technique/overwatch-mod/` | `/documentation/references` |
| SSE | `docs/sse/`, `mod/docs/` | Adjacent intelligence product |
| Public bulletins | `docs/dev/` | SPOTREP / TECHREP |
| Bug memory | `docs/bugs/` | Keep; not doctrine |
| Release journal | `CHANGELOG-ATAK.md` | Product changelog |
| Migration of old C2 notes | [_migration/DOCUMENT-MAPPING.md](_migration/DOCUMENT-MAPPING.md) | |
| Archived C2 drafts | [archive/legacy-atak/](archive/legacy-atak/) | Absorbed, not authoritative |

## Rule

Do not cite archived filenames as authority. Cite FM-A3-01, SOP-A3-01, ATP-A3-01, TM-A3-11, ICD-A3-01, SEC-A3-01, ATP-A3-11, TM-A3-21, TM-A3-31, REG-A3-01.
