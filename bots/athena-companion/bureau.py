"""Bureau des effectifs : mise en forme des réponses du site et plan des rôles. Aucune connexion à Discord ici.

Les données arrivent déjà filtrées par Athena (droits vérifiés sur le site, communauté déduite du serveur lié) : on ne
fait que les présenter. Aucune donnée d'état civil n'est jamais demandée ni affichée.
"""
from i18n import tr

ERROR_KEYS = {
    'not_linked': 'e_not_linked', 'link_required': 'e_link_required', 'not_member': 'e_not_member',
    'forbidden': 'e_forbidden', 'rate_limited': 'e_rate_limited',
}
STATUS_KEYS = {
    'to_start', 'in_progress', 'waiting_member', 'waiting_staff', 'blocked',
    'submitted', 'interview_scheduled', 'on_hold', 'acceptance_onboarding',
}
RSVP_KEYS = {'yes': 'c_rsvp_yes', 'no': 'c_rsvp_no', 'maybe': 'c_rsvp_maybe'}
# Un rôle qui porte une de ces permissions ne peut pas être géré par le bot : jamais d'élévation de privilèges via Discord.
DANGEROUS = ('administrator', 'manage_guild', 'manage_roles', 'manage_channels', 'manage_webhooks', 'ban_members',
             'kick_members', 'moderate_members', 'manage_messages', 'mention_everyone', 'manage_nicknames',
             'manage_expressions', 'view_audit_log', 'manage_threads')


def error_text(code, locale, link=''):
    key = ERROR_KEYS.get(code)
    return tr(key, locale, link=link) if key else tr('e_unavailable', locale)


def num(value, locale):
    return tr('c_na', locale) if value is None else str(int(value))


def status_label(status, locale):
    return tr('s_' + status, locale) if status in STATUS_KEYS else str(status)[:32]


def format_effectifs(data, locale):
    fields = [
        (tr('c_active', locale), num(data.get('active'), locale)),
        (tr('c_pending', locale), num(data.get('pending'), locale)),
        (tr('c_absent', locale), num(data.get('absent'), locale)),
        (tr('c_applications', locale), num(data.get('applications'), locale)),
        (tr('c_integrations', locale), num(data.get('integrations'), locale)),
    ]
    return {'title': tr('c_effectifs', locale), 'description': '', 'fields': fields, 'url': data.get('link', ''), 'buttons': []}


def format_membre(data, locale):
    members = data.get('members') or []
    if not members:
        return {'title': tr('c_member', locale), 'description': tr('c_no_member', locale), 'fields': [], 'url': '', 'buttons': []}
    fields = []
    for m in members[:5]:
        bits = []
        if m.get('grade'):
            bits.append(tr('c_grade', locale) + ' : ' + str(m['grade'])[:60])
        bits.append(tr('c_since', locale) + ' ' + str(m.get('since', ''))[:10])
        if m.get('absent'):
            bits.append(tr('c_absent_now', locale))
        fields.append((str(m.get('name', ''))[:60] or '-', ' · '.join(bits)))
    return {'title': tr('c_member', locale), 'description': '', 'fields': fields, 'url': data.get('link', ''), 'buttons': []}


def format_candidatures(data, locale):
    items = data.get('items') or []
    if not items:
        return {'title': tr('c_applications_title', locale), 'description': tr('c_no_applications', locale), 'fields': [], 'url': data.get('link', ''), 'buttons': []}
    fields, buttons = [], []
    for it in items[:10]:
        label = '#%d %s' % (int(it['id']), str(it.get('callsign') or '')[:40])
        fields.append((label.strip(), status_label(str(it.get('status', '')), locale) + ' · ' + str(it.get('date', ''))))
        if len(buttons) < 5 and it.get('link'):
            buttons.append((tr('c_decide', locale) + ' #%d' % int(it['id']), str(it['link'])))
    description = '%s : %s\n%s' % (tr('c_applications', locale), num(data.get('total'), locale), tr('c_decide_note', locale))
    return {'title': tr('c_applications_title', locale), 'description': description, 'fields': fields, 'url': data.get('link', ''), 'buttons': buttons}


def format_evenements(data, locale):
    items = data.get('items') or []
    if not items:
        return {'title': tr('c_events', locale), 'description': tr('c_no_events', locale), 'fields': [], 'url': data.get('link', ''), 'buttons': []}
    fields = []
    for it in items[:5]:
        rsvp = tr(RSVP_KEYS[it['rsvp']], locale) if it.get('rsvp') in RSVP_KEYS else tr('c_rsvp_none', locale)
        fields.append((str(it.get('title', ''))[:100], '%s · %s' % (str(it.get('starts_at', ''))[:16], rsvp)))
    return {'title': tr('c_events', locale), 'description': '', 'fields': fields, 'url': data.get('link', ''), 'buttons': []}


def format_integration(data, locale):
    mine = data.get('mine')
    fields = []
    if mine:
        fields.append((tr('c_progress', locale), '%d %% · %s' % (int(mine.get('percent', 0)), status_label(str(mine.get('status', '')), locale))))
        if mine.get('next_appointment'):
            fields.append((tr('c_next', locale), str(mine['next_appointment'])[:16]))
        if int(mine.get('overdue', 0)):
            fields.append((tr('c_overdue', locale), str(int(mine['overdue']))))
        description = ''
    else:
        description = tr('c_no_integration', locale)
    if data.get('staff_waiting') is not None:
        fields.append((tr('c_staff_waiting', locale), num(data.get('staff_waiting'), locale)))
    return {'title': tr('c_integration', locale), 'description': description, 'fields': fields, 'url': data.get('link', ''), 'buttons': []}


FORMATTERS = {
    'effectifs': format_effectifs, 'membre': format_membre, 'candidatures': format_candidatures,
    'evenements': format_evenements, 'integration': format_integration,
}


def format_reminder(item, locale):
    """(titre, texte, lien) d'un rappel renvoyé par le site ; None si le genre est inconnu."""
    kind, data = item.get('kind'), item.get('data') or {}
    if kind == 'campaign':
        slot = data.get('slot') if data.get('slot') in ('j14', 'j3', 'j0') else 'j14'
        return tr('r_campaign', locale), tr('r_campaign_' + slot, locale, title=data.get('title', ''), due_on=data.get('due_on', '')), data.get('link', '')
    if kind == 'applications_new':
        return tr('r_applications_new', locale), tr('r_applications_new_body', locale, id=int(data.get('id', 0)), callsign=str(data.get('callsign') or '-')[:40]), data.get('link', '')
    if kind in ('integration_validate', 'retro30', 'qualification_staff'):
        return tr('r_' + kind, locale), tr('r_%s_body' % kind, locale, count=int(data.get('count', 0))), data.get('link', '')
    if kind == 'qualification_due':
        return tr('r_qualification_due', locale), tr('r_qualification_due_body', locale, name=str(data.get('name', ''))[:80], expires_at=data.get('expires_at', '')), ''
    if kind == 'test':
        return tr('r_test', locale), tr('r_test_body', locale), ''
    if kind == 'remontee':
        # Remontée du renseignement : référence, type, urgence, émetteur. Jamais le texte (le site ne l'envoie pas).
        event = data.get('event') if data.get('event') in ('new', 'urgent', 'ack', 'forward', 'late', 'manual') else 'manual'
        urgency = data.get('urgency') if data.get('urgency') in ('critical', 'high', 'medium', 'low') else 'low'
        lines = [tr('r_remontee_ref', locale, ref=str(data.get('ref') or '-')[:60], type=str(data.get('type') or '-')[:80]),
                 tr('r_remontee_urgency', locale, urgency=tr('r_remontee_u_' + urgency, locale))]
        if data.get('emitter'):
            lines.append(tr('r_remontee_emitter', locale, emitter=str(data['emitter'])[:40]))
        if data.get('title'):
            lines.append(str(data['title'])[:120])
        return tr('r_remontee_' + event, locale), chr(10).join(lines), data.get('link', '')
    return None


def reminder_mention(item):
    """Rôle à mentionner avec un rappel (remontées seulement) : identifiant numérique, sinon rien."""
    role = str((item.get('data') or {}).get('role') or '')
    return role if item.get('kind') == 'remontee' and role.isdigit() and 5 <= len(role) <= 24 else ''


def dangerous_role(permissions):
    """Vrai si le rôle donne un pouvoir de gestion : le bot refuse de le distribuer."""
    return any(getattr(permissions, name, False) for name in DANGEROUS)


def plan_roles(level, current_ids, member_role, manager_role):
    """(à ajouter, à retirer) : seulement les deux rôles désignés par la communauté ; jamais un autre rôle.

    level vient du site (manager, member, none) ; un rôle Discord ne donne jamais de droit sur le site.
    """
    managed = {r for r in (member_role, manager_role) if r}
    wanted = set()
    if level == 'manager':
        wanted = managed
    elif level == 'member' and member_role:
        wanted = {member_role}
    current = {str(i) for i in current_ids}
    return sorted(wanted - current), sorted((managed - wanted) & current)
