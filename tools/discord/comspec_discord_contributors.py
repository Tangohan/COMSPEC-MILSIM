#!/usr/bin/env python3
"""
Rôles de contributeur sur le Discord COMSPEC (miroir de la page athena-comspec.eu/contribuer).

Le site propose cinq rôles de contributeur : Translator, Tester, Content creator, Dev, Support. Ce script :
  - crée les rôles manquants (Translator, Content creator ; Tester, Dev et Support existent déjà et sont
    réutilisés, anciens noms français compris), sans toucher à leurs permissions ni à leur position ;
  - crée le salon public « 🌍︱translation » dans la catégorie COMMUNITY (traduction du site et des mods,
    FR + EN, liens vers le site) ;
  - crée le salon privé « 🛠️︱contributors » dans la catégorie TEAM, visible des cinq rôles et du staff ;
  - ajoute à l'accueil du serveur (Onboarding, page « Salons et rôles ») une question où chacun choisit
    lui-même ses rôles : Translator, Tester, Content creator. Dev et Support restent donnés par le staff.
    Les questions déjà en place sont gardées telles quelles ;
  - rôles liés (comptes du site) : déclare à Discord les conditions envoyées par le site (compte Athena, membre
    d'une communauté, nombre de communautés, ancienneté), règle l'adresse de vérification de l'application et crée
    le rôle « Athena verified ». Il reste à cocher, dans Paramètres du serveur › Rôles › Athena verified › Liens,
    la condition « Compte Athena » de l'application ; puis à coller l'Application ID et le secret client dans
    Intégrations › Plateforme sur le site ;
  - avec --give « pseudo=Rôle » (répétable) : donne un rôle à un membre, d'après le pseudo Discord indiqué
    dans la demande sur le site (Administration du site › Contributeurs).

Ne touche JAMAIS la catégorie SUPPORT. Ne supprime rien. Ne poste aucun message.
Par défaut : simulation (affiche ce qui serait fait). --apply pour exécuter.

  py comspec_discord_contributors.py --guild 1559254947424641205
  py comspec_discord_contributors.py --guild 1559254947424641205 --apply
  py comspec_discord_contributors.py --guild 1559254947424641205 --apply --give "rickleo=Translator"

Jeton du bot : variable DISCORD_TOKEN, fichier jeton.txt à côté du script, ou saisie masquée. Jamais dans le chat.
"""
import argparse
import asyncio
import getpass
import os
import sys
from pathlib import Path

# Portail (ATHENA_SITE_URL pour un autre domaine ; l’ancien athena.ttrd.fr reste servi).
SITE = os.environ.get("ATHENA_SITE_URL", "https://athena-comspec.eu").rstrip("/")
# nom, couleur, anciens noms acceptés (réutilisés tels quels)
ROLES = [
    ("Translator", 0x2DD4BF, ("Translator", "Traducteur")),
    ("Tester", 0xF59E0B, ("Tester", "Testeur")),
    ("Content creator", 0xA78BFA, ("Content creator", "Créateur de contenu")),
    ("Dev", 0x60A5FA, ("Dev", "Développeur", "Developer")),
    ("Support", 0x34D399, ("Support",)),
]
STAFF_MATCH = ("admin", "staff", "modo", "moderator")
PUBLIC_CHANNEL = "🌍︱translation"
PUBLIC_MATCH = ("translation", "traduction")
PRIVATE_CHANNEL = "🛠️︱contributors"
PRIVATE_MATCH = ("contributors", "contributeurs")
COMMUNITY_MATCH = ("COMMUNITY", "COMMUNAUT")
TEAM_MATCH = ("TEAM", "ÉQUIPE", "EQUIPE", "STAFF")
PROTECTED_MATCH = ("SUPPORT",)

# Rôles que chacun choisit lui-même dans l'accueil du serveur (titre, emoji, description).
SELF_ROLES = [
    ("Translator", "🌍", "I translate the website or the mods into my language"),
    ("Tester", "🧪", "I test new versions in game and report bugs"),
    ("Content creator", "🎬", "I make videos, screenshots or guides about Athena"),
]
PROMPT_TITLE = "How do you want to help Athena? / Comment veux-tu aider Athena ?"

# Rôles liés : mêmes clés que App\Services\Integrations\DiscordLinkedRoles::METADATA (le site envoie ces valeurs).
VERIFIED_ROLE = ("Athena verified", 0x38BDF8)
VERIFICATION_URL = SITE + "/discord/roles-lies"
LINKED_ROLES_METADATA = [
    {"key": "athena_account", "type": 7, "name": "Athena account", "description": "Has an Athena account linked",
     "name_localizations": {"fr": "Compte Athena"}, "description_localizations": {"fr": "A relié son compte Athena"}},
    {"key": "community_member", "type": 7, "name": "Community member", "description": "Member of an Athena community",
     "name_localizations": {"fr": "Membre d’une communauté"}, "description_localizations": {"fr": "Membre d’une communauté Athena"}},
    {"key": "communities", "type": 2, "name": "Communities", "description": "Number of Athena communities",
     "name_localizations": {"fr": "Communautés"}, "description_localizations": {"fr": "Nombre de communautés Athena"}},
    {"key": "member_since", "type": 5, "name": "Days on Athena", "description": "Days since the Athena account was created",
     "name_localizations": {"fr": "Jours sur Athena"}, "description_localizations": {"fr": "Jours depuis la création du compte Athena"}},
]

PUBLIC_TOPIC = (
    "🇬🇧 Translate Athena into your language: website " + SITE + "/traduire-site · ATAK phone and SSE mod "
    + SITE + "/traduire-atak · Translator role: " + SITE + "/contribuer\n"
    "🇫🇷 Traduire Athena dans votre langue : site " + SITE + "/traduire-site · téléphone ATAK et mod SSE "
    + SITE + "/traduire-atak · rôle Traducteur : " + SITE + "/contribuer"
)
PRIVATE_TOPIC = (
    "🇬🇧 Contributors (translators, testers, content creators, devs, support): coordination with the team.\n"
    "🇫🇷 Contributeurs (traducteurs, testeurs, créateurs de contenu, devs, support) : coordination avec l'équipe."
)


def find_category(guild, match):
    for cat in guild.categories:
        up = cat.name.upper()
        if any(p in up for p in PROTECTED_MATCH):
            continue
        if any(m in up for m in match):
            return cat
    return None


def find_text_channel(guild, match):
    for ch in guild.text_channels:
        if ch.category and any(p in ch.category.name.upper() for p in PROTECTED_MATCH):
            continue
        if any(m in ch.name.lower() for m in match):
            return ch
    return None


def snowflake(offset=0):
    import time
    return str(((int(time.time() * 1000) - 1420070400000) << 22) + offset)


def prompt_for_put(prompt):
    """Question renvoyée par GET /onboarding, remise au format attendu par PUT (emoji à plat)."""
    options = []
    for opt in prompt.get("options", []):
        emoji = opt.get("emoji") or {}
        item = {"id": opt["id"], "title": opt["title"], "description": opt.get("description"),
                "role_ids": opt.get("role_ids", []), "channel_ids": opt.get("channel_ids", [])}
        if emoji.get("id"):
            item["emoji_id"] = emoji["id"]
            item["emoji_animated"] = bool(emoji.get("animated"))
        if emoji.get("name"):
            item["emoji_name"] = emoji["name"]
        options.append(item)
    return {k: prompt[k] for k in ("id", "type", "title", "single_select", "required", "in_onboarding")} | {"options": options}


async def add_onboarding_prompt(client, guild, roles, apply, log):
    from discord.http import Route

    current = await client.http.request(Route("GET", "/guilds/{guild_id}/onboarding", guild_id=guild.id))
    prompts = current.get("prompts", [])
    picks = [(roles.get(name), emoji, desc, name) for name, emoji, desc in SELF_ROLES]
    existing = next((p for p in prompts if p.get("title") == PROMPT_TITLE), None)
    if existing is not None:
        have = {rid for o in existing.get("options", []) for rid in o.get("role_ids", [])}
        if all(r is not None and str(r.id) in have for r, *_ in picks):
            log("Accueil du serveur : la question de choix des rôles est déjà en place.")
            return
    log("Accueil du serveur : question « " + PROMPT_TITLE + " » ajoutée (choix libre, plusieurs rôles possibles : "
        + ", ".join(n for _, _, _, n in picks) + ").")
    if not current.get("enabled"):
        log("  L'accueil (Onboarding) est désactivé : la question sera visible dans « Salons et rôles » une fois activé.")
    if not apply:
        return
    options = [{"id": snowflake(i + 1), "title": name, "description": desc, "emoji_name": emoji,
                "role_ids": [str(role.id)], "channel_ids": []}
               for i, (role, emoji, desc, name) in enumerate(picks) if role is not None]
    new_prompt = {"id": existing["id"] if existing else snowflake(), "type": 0, "title": PROMPT_TITLE,
                  "single_select": False, "required": False, "in_onboarding": True, "options": options}
    body = {
        "prompts": [prompt_for_put(p) for p in prompts if p is not existing] + [new_prompt],
        "default_channel_ids": current.get("default_channel_ids", []),
        "enabled": current.get("enabled", False),
        "mode": current.get("mode", 0),
    }
    await client.http.request(Route("PUT", "/guilds/{guild_id}/onboarding", guild_id=guild.id), json=body,
                              reason="Rôles de contributeur Athena")


async def setup_linked_roles(client, guild, apply, log):
    import discord
    from discord.http import Route

    app_id = client.application_id
    log(f"Rôles liés : application {app_id} (Application ID à coller dans Intégrations › Plateforme sur le site).")
    log("Rôles liés : conditions déclarées à Discord : " + ", ".join(m["key"] for m in LINKED_ROLES_METADATA) + ".")
    log(f"Rôles liés : adresse de vérification de l'application = {VERIFICATION_URL}")
    role = next((r for r in guild.roles if r.name == VERIFIED_ROLE[0]), None)
    log(("Rôle existant réutilisé : " if role else "Rôle créé : ") + VERIFIED_ROLE[0])
    if not apply:
        return
    await client.http.request(Route("PUT", "/applications/{application_id}/role-connections/metadata",
                                    application_id=app_id), json=LINKED_ROLES_METADATA)
    await client.http.request(Route("PATCH", "/applications/@me"),
                              json={"role_connections_verification_url": VERIFICATION_URL})
    if role is None:
        await guild.create_role(name=VERIFIED_ROLE[0], colour=discord.Colour(VERIFIED_ROLE[1]), mentionable=False,
                                reason="Rôles liés Athena (comptes du site)")


def run(guild_id: int, token: str, apply: bool, gives: list) -> int:
    import discord

    intents = discord.Intents.default()
    client = discord.Client(intents=intents)
    result = {"code": 0}

    def log(msg):
        print(("" if apply else "[simulation] ") + msg, flush=True)

    async def build(guild):
        roles = {}
        for name, color, aliases in ROLES:
            role = next((r for r in guild.roles if r.name in aliases), None)
            if role is None:
                log(f"Rôle créé : {name}")
                if apply:
                    role = await guild.create_role(name=name, colour=discord.Colour(color), mentionable=False,
                                                   reason="Rôles de contributeur Athena")
            else:
                log(f"Rôle existant réutilisé : {role.name}")
            roles[name] = role

        community = find_category(guild, COMMUNITY_MATCH)
        public = find_text_channel(guild, PUBLIC_MATCH)
        if public is None:
            log(f"Salon public « {PUBLIC_CHANNEL} » créé dans {community.name if community else 'aucune catégorie'}.")
            if apply:
                await guild.create_text_channel(PUBLIC_CHANNEL, category=community, topic=PUBLIC_TOPIC[:1024],
                                                reason="Traduction communautaire Athena")
        else:
            log(f"Salon public existant réutilisé : « {public.name} » (sujet mis à jour).")
            if apply:
                await public.edit(topic=PUBLIC_TOPIC[:1024], reason="Traduction communautaire Athena")

        team = find_category(guild, TEAM_MATCH)
        private = find_text_channel(guild, PRIVATE_MATCH)
        if private is None:
            log(f"Salon privé « {PRIVATE_CHANNEL} » créé dans {team.name if team else 'aucune catégorie'} "
                "(visible des rôles de contributeur et du staff).")
            if apply:
                overwrites = {guild.default_role: discord.PermissionOverwrite(view_channel=False)}
                for role in roles.values():
                    if role is not None:
                        overwrites[role] = discord.PermissionOverwrite(view_channel=True, send_messages=True)
                for role in guild.roles:
                    if role.permissions.administrator or any(m in role.name.lower() for m in STAFF_MATCH):
                        overwrites[role] = discord.PermissionOverwrite(view_channel=True, send_messages=True)
                await guild.create_text_channel(PRIVATE_CHANNEL, category=team, topic=PRIVATE_TOPIC[:1024],
                                                overwrites=overwrites, reason="Rôles de contributeur Athena")
        else:
            log(f"Salon privé existant réutilisé : « {private.name} » (permissions inchangées).")

        try:
            await add_onboarding_prompt(client, guild, roles, apply, log)
        except Exception as exc:  # noqa: BLE001
            log(f"Accueil du serveur non modifié ({exc}). Le reste continue.")

        try:
            await setup_linked_roles(client, guild, apply, log)
        except Exception as exc:  # noqa: BLE001
            log(f"Rôles liés non déclarés ({exc}). Le reste continue.")

        for give in gives:
            if "=" not in give:
                log(f"--give ignoré (format pseudo=Rôle) : {give}")
                continue
            handle, role_name = (p.strip() for p in give.split("=", 1))
            role = roles.get(role_name) or next((r for n, r in roles.items() if n.lower() == role_name.lower()), None)
            if role is None and not apply:
                log(f"{handle} recevrait le rôle {role_name} (créé par ce lancement).")
                continue
            if role is None:
                log(f"Rôle inconnu : {role_name} (rôles : {', '.join(roles)})")
                continue
            members = await guild.query_members(query=handle.lstrip("@").split("#")[0], limit=5)
            member = next((m for m in members if handle.lstrip("@").lower() in (m.name.lower(), (m.global_name or "").lower(), m.display_name.lower())), None)
            if member is None:
                log(f"Membre introuvable sur le serveur : {handle}")
                continue
            if role in member.roles:
                log(f"{member} a déjà le rôle {role.name}.")
                continue
            log(f"Rôle {role.name} donné à {member}.")
            if apply:
                await member.add_roles(role, reason="Rôle de contributeur validé sur le site Athena")
        log("Rien n'a été posté. Terminé.")

    @client.event
    async def on_ready():
        try:
            guild = client.get_guild(guild_id)
            if guild is None:
                print("Serveur introuvable : le bot est-il invité sur ce serveur ?")
                result["code"] = 2
            else:
                await build(guild)
        except Exception as exc:  # noqa: BLE001
            print(f"Erreur : {exc}")
            result["code"] = 1
        finally:
            await client.close()

    asyncio.run(client.start(token))
    return result["code"]


def main() -> int:
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8", errors="replace")
        except (AttributeError, ValueError):
            pass
    ap = argparse.ArgumentParser(description="Rôles de contributeur sur le Discord COMSPEC")
    ap.add_argument("--guild", type=int, required=True, help="ID du serveur Discord")
    ap.add_argument("--apply", action="store_true", help="applique (sinon simulation : rien n'est modifié)")
    ap.add_argument("--give", action="append", default=[], help="pseudo=Rôle : donne un rôle à un membre (répétable)")
    args = ap.parse_args()
    try:
        import discord  # noqa: F401
    except ImportError:
        print('discord.py manquant : py -m pip install "discord.py>=2.4"')
        return 2
    token = os.environ.get("DISCORD_TOKEN", "").strip()
    token_file = Path(__file__).resolve().parent / "jeton.txt"
    if not token and token_file.exists():
        token = token_file.read_text(encoding="utf-8-sig").strip()
    if not token:
        token = getpass.getpass("Jeton du bot (rien ne s'affiche) : ").strip()
    if not token:
        print("Aucun jeton fourni.")
        return 2
    return run(args.guild, token, args.apply, args.give)


if __name__ == "__main__":
    sys.exit(main())
