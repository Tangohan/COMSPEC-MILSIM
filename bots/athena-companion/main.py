"""ATHENA Companion — official multi-server bot. No privileged Discord intents."""
import asyncio
import hashlib
import json
import logging
import os
import time
import aiohttp
import discord
from discord import app_commands
from discord.ext import tasks
from api import AthenaAPI, ApiError
from bureau import FORMATTERS, dangerous_role, error_text, format_reminder, plan_roles
from config import Config
from health import Health
from i18n import tr
from state import State

EMOJIS = {'yes': '✅', 'no': '❌', 'maybe': '❓'}
log = logging.getLogger('athena-companion')

class Companion(discord.Client):
    def __init__(self, config):
        intents = discord.Intents.none()
        intents.guilds = True
        intents.guild_reactions = True
        intents.members = bool(config.members_intent)  # privilégié : seulement pour l'accueil des nouveaux arrivants (documenté)
        super().__init__(intents=intents, allowed_mentions=discord.AllowedMentions.none())
        self.config = config
        self.tree = app_commands.CommandTree(self)
        self.state = State(config.state_path)
        self.jobs = {}
        self.snapshot_at = {}
        self.pending = {}
        self.guild_sync_at = 0
        self.last_command = {}
        self.welcome_cache = {}
        self.poll_ok_at = 0.0
        self.health = None
        self.group = app_commands.Group(name='athena', description='ATHENA Companion · événements / events')
        self.group.add_command(app_commands.Command(name='connect', description='Relier Athena avec un code temporaire / Connect with a temporary code', callback=self.connect))
        self.group.add_command(app_commands.Command(name='help', description='Aide ATHENA Companion / ATHENA Companion help', callback=self.help_command))
        self.tree.add_command(self.group)
        for name, description, callback in (
            ('effectifs', 'Résumé des effectifs / Staffing summary', self.cmd_effectifs),
            ('membre', 'Fiche courte d’un membre / Short member record', self.cmd_membre),
            ('candidatures', 'Candidatures ouvertes / Open applications', self.cmd_candidatures),
            ('evenements', 'Prochains événements / Upcoming events', self.cmd_evenements),
            ('integration', 'État de l’intégration / Onboarding status', self.cmd_integration),
        ):
            self.tree.add_command(app_commands.Command(name=name, description=description, callback=callback))
        self.tree.on_error = self.command_error

    async def setup_hook(self):
        self.http_session = aiohttp.ClientSession()
        self.api = AthenaAPI(self.config, self.http_session)
        await self.tree.sync()
        self.poll.start()
        self.remind.start()
        self.sync_roles.start()
        self.beat.start()
        if self.config.health_port:
            self.health = Health(self, self.config.health_port)
            await self.health.start()

    async def close(self):
        self.poll.cancel()
        self.remind.cancel()
        self.sync_roles.cancel()
        self.beat.cancel()
        if self.health is not None:
            await self.health.stop()
        for task in self.pending.values():
            task.cancel()
        if hasattr(self, 'http_session'):
            await self.http_session.close()
        self.state.db.close()
        await super().close()

    def channels(self, guild):
        return {str(c.id): c.name for c in guild.text_channels if c.permissions_for(guild.me).view_channel}

    def safe_roles(self, guild):
        """Rôles que la communauté peut désigner : ni @everyone, ni rôle d'intégration, ni rôle qui gère le serveur."""
        return {str(r.id): r.name for r in guild.roles if not r.is_default() and not r.managed and not dangerous_role(r.permissions)}

    async def report_guild(self, guild):
        await self.api.call('channels', guild_id=str(guild.id), guild_name=guild.name, channels=self.channels(guild), roles=self.safe_roles(guild))

    @app_commands.guild_only()
    @app_commands.default_permissions(manage_guild=True)
    @app_commands.checks.has_permissions(manage_guild=True)
    async def connect(self, interaction: discord.Interaction, code: str, channel: discord.TextChannel):
        await interaction.response.defer(ephemeral=True)
        if interaction.guild is None or channel.guild.id != interaction.guild.id:
            return await interaction.followup.send(tr('guild_only', interaction.locale), ephemeral=True)
        permissions = channel.permissions_for(interaction.guild.me)
        if not all((permissions.view_channel, permissions.send_messages, permissions.embed_links, permissions.read_message_history, permissions.add_reactions)):
            return await interaction.followup.send(tr('permissions', interaction.locale), ephemeral=True)
        try:
            await self.api.call('pair', code=code, guild_id=str(interaction.guild.id), guild_name=interaction.guild.name,
                                channel_id=str(channel.id), channels=self.channels(interaction.guild))
            await interaction.followup.send(tr('linked', interaction.locale), ephemeral=True)
        except ApiError as exc:
            key = {'companion.invalid_code':'invalid','companion.already_bound':'bound'}.get(str(exc),'failed')
            await interaction.followup.send(tr(key, interaction.locale), ephemeral=True)

    async def help_command(self, interaction: discord.Interaction):
        await interaction.response.send_message(tr('help', interaction.locale) + '\n' + self.config.site_url + '/discord/roles-lies', ephemeral=True)

    async def command_error(self, interaction, error):
        key = 'admin_only' if isinstance(error, app_commands.MissingPermissions) else 'failed'
        send = interaction.followup.send if interaction.response.is_done() else interaction.response.send_message
        await send(tr(key, interaction.locale), ephemeral=True)
        log.warning('Command failed: %s', type(error).__name__)

    def embed(self, job):
        locale = job['locale']
        cancelled = bool(job.get('cancelled_at'))
        disabled = not int(job['enabled'])
        title = tr('cancelled', locale) if cancelled else tr('disabled', locale) if disabled else job['title']
        embed = discord.Embed(title=str(title)[:256], description=str(job.get('description') or '')[:1800],
                              color=0xB42318 if cancelled else 0x0B8A5C,
                              url=self.config.site_url + '/evenements#event-' + str(job['event_id']))
        embed.add_field(name=tr('start', locale), value=str(job['starts_at']), inline=True)
        if job.get('location'):
            embed.add_field(name=tr('location', locale), value=str(job['location'])[:1024], inline=True)
        if int(job['rsvp_enabled']) and not cancelled and not disabled:
            embed.add_field(name=tr('vote', locale).split('\n')[0], value=tr('vote', locale).split('\n',1)[1], inline=False)
            embed.add_field(name=tr('link', locale), value=self.config.site_url + '/discord/roles-lies', inline=False)
        embed.set_footer(text='ATHENA:' + str(job['event_id']))
        return embed

    async def publish(self, job):
        event = int(job['event_id'])
        guild = self.get_guild(int(job['guild_id']))
        if guild is None:
            return
        saved = self.state.get(event)
        if saved and saved['guild_id'] != str(job['guild_id']):
            saved = None
        message_id = job.get('message_id') or (saved or {}).get('message_id')
        channel_id = job.get('channel_id') or (saved or {}).get('channel_id') or job['default_channel_id']
        channel = guild.get_channel(int(channel_id))
        if not isinstance(channel, discord.TextChannel):
            return
        if not message_id and (job.get('cancelled_at') or not int(job['enabled'])):
            return
        embed = self.embed(job)
        digest = hashlib.sha256(json.dumps(embed.to_dict(), sort_keys=True).encode()).hexdigest()
        if message_id:
            message = await channel.fetch_message(int(message_id))
            if message.author.id != self.user.id:
                raise ValueError('Message does not belong to Companion')
            if not saved or saved['digest'] != digest:
                await message.edit(embed=embed)
        else:
            # Recover an acknowledgement lost after sending, without reposting the announcement.
            message = None
            async for candidate in channel.history(limit=100):
                if candidate.author.id == self.user.id and any(e.footer.text == 'ATHENA:' + str(event) for e in candidate.embeds):
                    message = candidate
                    break
            if message is None:
                message = await channel.send(embed=embed)
        self.state.save(event, guild.id, channel.id, message.id, digest)
        await self.api.call('published', event_id=event, guild_id=str(guild.id), channel_id=str(channel.id), message_id=str(message.id))
        if int(job['rsvp_enabled']) and int(job['enabled']) and not job.get('cancelled_at'):
            for emoji in EMOJIS.values():
                if not any(str(r.emoji) == emoji and r.me for r in message.reactions):
                    await message.add_reaction(emoji)
            if time.monotonic() - self.snapshot_at.get(event, 0) > 300:
                await self.snapshot(event, message)

    async def snapshot(self, event, message=None):
        saved = self.state.get(event)
        job = self.jobs.get(event)
        if not saved or not job or not int(job['enabled']) or not int(job['rsvp_enabled']) or job.get('cancelled_at'):
            return
        guild = self.get_guild(int(saved['guild_id']))
        if guild is None:
            return
        channel = guild.get_channel(int(saved['channel_id']))
        if channel is None:
            return
        message = await channel.fetch_message(int(saved['message_id']))
        reactions = {status: [] for status in EMOJIS}
        for reaction in message.reactions:
            status = next((s for s,e in EMOJIS.items() if str(reaction.emoji) == e), None)
            if status is None:
                continue
            if reaction.count > 10000:
                raise ValueError('Reaction snapshot exceeds limit')
            seen = 0
            async for user in reaction.users(limit=10001):
                seen += 1
                if seen > 10000:
                    raise ValueError('Reaction snapshot exceeds limit')
                if not user.bot:
                    reactions[status].append(str(user.id))
                if len(reactions[status]) > 10000:
                    raise ValueError('Reaction snapshot exceeds limit')
        await self.api.call('reactions', event_id=event, guild_id=saved['guild_id'], channel_id=saved['channel_id'], message_id=saved['message_id'], reactions=reactions)
        self.snapshot_at[event] = time.monotonic()

    async def reaction_changed(self, payload):
        if str(payload.emoji) not in EMOJIS.values() or payload.user_id == self.user.id:
            return
        event = self.state.event_for_message(payload.message_id)
        if event is None:
            return
        task = self.pending.get(event)
        if task and not task.done():
            task.cancel()
        async def sync():
            try:
                await asyncio.sleep(1)
                await self.snapshot(event)
            except asyncio.CancelledError:
                pass
            except Exception as exc:
                log.warning('Reaction sync failed for event %s: %s', event, type(exc).__name__)
            finally:
                if self.pending.get(event) is asyncio.current_task():
                    self.pending.pop(event, None)
        self.pending[event] = asyncio.create_task(sync())

    async def on_guild_channel_update(self, before, after):
        if before.name != after.name:
            try:
                await self.report_guild(after.guild)
            except ApiError:
                log.warning('Channel name sync failed')

    async def on_raw_reaction_add(self, payload):
        await self.reaction_changed(payload)

    async def on_raw_reaction_remove(self, payload):
        await self.reaction_changed(payload)

    # ---- Bureau des effectifs : commandes -------------------------------------------------------------------------

    @app_commands.guild_only()
    async def cmd_effectifs(self, interaction: discord.Interaction):
        await self.bureau(interaction, 'effectifs')

    @app_commands.guild_only()
    async def cmd_membre(self, interaction: discord.Interaction, nom: str):
        await self.bureau(interaction, 'membre', nom[:40])

    @app_commands.guild_only()
    async def cmd_candidatures(self, interaction: discord.Interaction):
        await self.bureau(interaction, 'candidatures')

    @app_commands.guild_only()
    async def cmd_evenements(self, interaction: discord.Interaction):
        await self.bureau(interaction, 'evenements')

    @app_commands.guild_only()
    async def cmd_integration(self, interaction: discord.Interaction):
        await self.bureau(interaction, 'integration')

    def result_embed(self, result):
        embed = discord.Embed(title=result['title'][:256], description=result['description'][:1800], color=0x0B8A5C)
        if result['url']:
            embed.url = result['url']
        for name, value in result['fields'][:10]:
            embed.add_field(name=str(name)[:256], value=str(value)[:1024] or '-', inline=False)
        return embed

    def result_view(self, result):
        if not result['buttons']:
            return None
        view = discord.ui.View(timeout=None)
        for label, url in result['buttons'][:5]:
            view.add_item(discord.ui.Button(style=discord.ButtonStyle.link, label=label[:80], url=url))
        return view

    async def bureau(self, interaction, op, query=''):
        """Une commande du bureau des effectifs : le site vérifie le compte lié et le droit, le bot présente la réponse en privé."""
        locale = interaction.locale
        now = time.monotonic()
        if now - self.last_command.get(interaction.user.id, 0) < 3:
            return await interaction.response.send_message(tr('e_cooldown', locale), ephemeral=True)
        if len(self.last_command) > 5000:
            self.last_command.clear()
        self.last_command[interaction.user.id] = now
        await interaction.response.defer(ephemeral=True)
        try:
            data = await self.api.call('cmd', guild_id=str(interaction.guild.id), user_id=str(interaction.user.id), command=op, query=query)
        except ApiError:
            return await interaction.followup.send(tr('e_unavailable', locale), ephemeral=True)
        if not data.get('ok'):
            return await interaction.followup.send(error_text(data.get('error', ''), locale, self.config.site_url + '/discord/roles-lies'), ephemeral=True)
        result = FORMATTERS[op](data.get('data') or {}, locale)
        view = self.result_view(result)
        embed = self.result_embed(result)
        if view is None:
            await interaction.followup.send(embed=embed, ephemeral=True)
        else:
            await interaction.followup.send(embed=embed, view=view, ephemeral=True)

    # ---- Rappels automatiques ---------------------------------------------------------------------------------------

    async def deliver(self, guild, item, formatted):
        """Envoie un rappel dans un salon ou en message privé. Vrai si le rappel est réglé (envoyé ou impossible à jamais)."""
        title, body, link = formatted
        embed = discord.Embed(title=title[:256], description=body[:1800], color=0x0B8A5C)
        if link:
            embed.url = link
        if item.get('target') == 'dm':
            try:
                user = await self.fetch_user(int(item['to']))
                await user.send(embed=embed)
            except (discord.Forbidden, discord.NotFound):
                return True  # messages privés fermés : inutile de réessayer toutes les cinq minutes
            return True
        channel = guild.get_channel(int(item['to']))
        if not isinstance(channel, discord.TextChannel) or not channel.permissions_for(guild.me).send_messages:
            return False
        await channel.send(embed=embed)
        return True

    async def remind_guild(self, guild):
        data = await self.api.call('reminders', guild_id=str(guild.id))
        locale = str(guild.preferred_locale)
        for item in (data.get('reminders') or [])[:25]:
            formatted = format_reminder(item, locale)
            if formatted is None:
                continue
            try:
                done = await self.deliver(guild, item, formatted)
                if done:
                    await self.api.call('reminder_ack', guild_id=str(guild.id), kind=item['kind'], ref=item['ref'])
            except (discord.HTTPException, ApiError) as exc:
                log.warning('Reminder %s failed: %s', item.get('kind'), type(exc).__name__)
            await asyncio.sleep(1.5)  # reste très en dessous des limites de débit de Discord

    @tasks.loop(minutes=5)
    async def remind(self):
        for guild in list(self.guilds):
            try:
                await self.remind_guild(guild)
            except Exception as exc:
                log.warning('Reminders failed: %s', type(exc).__name__)

    @remind.before_loop
    async def before_remind(self):
        await self.wait_until_ready()

    # ---- Rôles : copie du niveau d'accès du site, jamais l'inverse ------------------------------------------------------

    def usable_role(self, guild, role_id):
        role = guild.get_role(int(role_id)) if role_id else None
        if role is None or role.is_default() or role.managed or role >= guild.me.top_role or dangerous_role(role.permissions):
            return None
        return role

    async def sync_roles_guild(self, guild):
        if not guild.me.guild_permissions.manage_roles:
            return
        plan = await self.api.call('roles_plan', guild_id=str(guild.id))
        if not plan.get('enabled'):
            return
        member_role = self.usable_role(guild, plan.get('member_role_id'))
        manager_role = self.usable_role(guild, plan.get('manager_role_id'))
        if member_role is None and manager_role is None:
            return
        member_id = str(member_role.id) if member_role else ''
        manager_id = str(manager_role.id) if manager_role else ''
        for entry in (plan.get('users') or [])[:200]:
            try:
                member = guild.get_member(int(entry['discord_user_id'])) or await guild.fetch_member(int(entry['discord_user_id']))
            except (discord.NotFound, discord.HTTPException):
                continue
            add, remove = plan_roles(entry.get('level'), [r.id for r in member.roles], member_id, manager_id)
            try:
                if add:
                    await member.add_roles(*[discord.Object(int(i)) for i in add], reason='Athena : niveau d’accès du site')
                if remove:
                    await member.remove_roles(*[discord.Object(int(i)) for i in remove], reason='Athena : niveau d’accès du site')
            except discord.HTTPException as exc:
                log.warning('Role sync failed: %s', type(exc).__name__)
            if add or remove:
                await asyncio.sleep(1)

    @tasks.loop(minutes=15)
    async def sync_roles(self):
        for guild in list(self.guilds):
            try:
                await self.sync_roles_guild(guild)
            except Exception as exc:
                log.warning('Role sync failed: %s', type(exc).__name__)

    @sync_roles.before_loop
    async def before_sync_roles(self):
        await self.wait_until_ready()

    # ---- Présence, accueil, battement de cœur --------------------------------------------------------------------------

    async def on_guild_join(self, guild):
        try:
            await self.report_guild(guild)
        except ApiError:
            log.warning('Guild report failed')

    async def on_guild_remove(self, guild):
        try:
            await self.api.call('guild_removed', guild_id=str(guild.id))
        except ApiError:
            log.warning('Guild removal report failed')

    async def on_member_join(self, member):
        """Accueil : seulement si l'intent « Membres » est activé par l'opérateur et le salon d'annonces choisi par la communauté."""
        if member.bot:
            return
        guild = member.guild
        cached = self.welcome_cache.get(guild.id)
        if not cached or time.monotonic() - cached[0] > 300:
            try:
                cached = (time.monotonic(), await self.api.call('welcome', guild_id=str(guild.id)))
            except ApiError:
                return
            self.welcome_cache[guild.id] = cached
        info = cached[1]
        channel = guild.get_channel(int(info['channel_id'])) if info.get('enabled') and info.get('channel_id') else None
        if isinstance(channel, discord.TextChannel) and channel.permissions_for(guild.me).send_messages:
            await channel.send(tr('welcome', str(guild.preferred_locale), name=member.display_name, link=info.get('link', '')))

    @tasks.loop(seconds=30)
    async def beat(self):
        if not self.config.heartbeat_path:
            return
        tmp = self.config.heartbeat_path + '.tmp'
        with open(tmp, 'w', encoding='utf-8') as handle:
            handle.write(json.dumps({'at': int(time.time()), 'guilds': len(self.guilds), 'poll_ok_at': int(self.poll_ok_at)}))
        os.replace(tmp, self.config.heartbeat_path)

    @tasks.loop(seconds=60)
    async def poll(self):
        try:
            data = await self.api.call('jobs')
            self.jobs = {int(j['event_id']):j for j in data['jobs']}
            for job in self.jobs.values():
                try:
                    await self.publish(job)
                except Exception as exc:
                    log.warning('Event %s failed: %s', job['event_id'], type(exc).__name__)
                    await self.api.call('failed', event_id=int(job['event_id']), guild_id=str(job['guild_id']))
            if time.monotonic() - self.guild_sync_at > 600:
                for guild in self.guilds:
                    await self.report_guild(guild)
                self.guild_sync_at = time.monotonic()
            self.poll_ok_at = time.time()
        except Exception as exc:
            log.warning('Companion poll failed: %s', type(exc).__name__)

    @poll.before_loop
    async def before_poll(self):
        await self.wait_until_ready()

if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO)
    configuration = Config.load()
    Companion(configuration).run(configuration.token, log_level=logging.WARNING)
