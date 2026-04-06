<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Moderation\ContentModerationConfig;

class SystemSettingsController
{
    /** @var array<string, string> */
    private const PLATFORM_SETTING_LABELS = [
        'brief_member_access' => 'Accès au brief pour les membres',
        'brief_member_closed_message' => 'Message affiché lorsque le brief est fermé aux membres',
    ];

    public function __construct(
        private PlatformSettingsRepository $platformSettingsRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $appEnvRaw = (string) config('app.env', 'local');

        return Response::view('layout.main', [
            'content' => 'admin.system.settings',
            'title' => 'Paramètres système',
            'adminSettingsEnvRaw' => $appEnvRaw,
            'adminSettingsEnvLabel' => app_environment_label_fr($appEnvRaw),
            'adminSettingsSections' => $this->buildSections(),
            'adminSettingsPlatformRows' => $this->buildPlatformRows(),
        ]);
    }

    /**
     * @return list<array{title: string, description?: string, rows: list<array{label: string, value: string, note?: string}>}>
     */
    private function buildSections(): array
    {
        $sections = [];

        $sections[] = [
            'title' => 'Application',
            'description' => 'Identité du site, environnement d’exécution et adresse publique.',
            'rows' => [
                ['label' => 'Nom du site', 'value' => (string) config('app.name', '—')],
                ['label' => 'Environnement', 'value' => app_environment_label_fr((string) config('app.env', 'local'))],
                ['label' => 'Mode diagnostic (détail des erreurs)', 'value' => $this->yn((bool) config('app.debug', false))],
                ['label' => 'Fuseau horaire', 'value' => (string) config('app.timezone', '—')],
                ['label' => 'Langue par défaut', 'value' => (string) config('app.locale', '—')],
                ['label' => 'Adresse publique du site', 'value' => $this->dash((string) config('app.url', ''))],
                [
                    'label' => 'Sous-chemin d’installation',
                    'value' => $this->dash(trim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''))),
                    'note' => 'Laissez vide si le site est à la racine du domaine.',
                ],
            ],
        ];

        $maint = config('app.maintenance');
        $maint = is_array($maint) ? $maint : [];
        $maintOn = (bool) ($maint['enabled'] ?? false);
        $maintMsg = trim((string) ($maint['message'] ?? ''));
        $ips = $maint['allowed_ips'] ?? [];
        $ipList = is_array($ips) ? array_values(array_filter(array_map('strval', $ips))) : [];
        $sections[] = [
            'title' => 'Maintenance (variables d’environnement)',
            'description' => 'Ancien levier par fichier d’environnement. La maintenance réelle du site est gérée dans l’écran dédié (planification, messages, audiences).',
            'rows' => [
                ['label' => 'Bascule « maintenance » via environnement', 'value' => $this->yn($maintOn)],
                [
                    'label' => 'Message associé',
                    'value' => $maintMsg === '' ? '—' : $this->truncate($maintMsg, 160),
                ],
                [
                    'label' => 'Adresses IP autorisées pendant la maintenance',
                    'value' => $ipList === [] ? 'Aucune' : (string) count($ipList) . ' adresse(s)',
                ],
            ],
        ];

        $log = config('app.log');
        $log = is_array($log) ? $log : [];
        $sections[] = [
            'title' => 'Journalisation',
            'rows' => [
                ['label' => 'Canal', 'value' => $this->dash((string) ($log['channel'] ?? ''))],
                ['label' => 'Niveau minimal enregistré', 'value' => $this->dash((string) ($log['level'] ?? ''))],
                ['label' => 'Dossier des fichiers journaux', 'value' => $this->dash((string) ($log['path'] ?? ''))],
            ],
        ];

        $auth = config('auth');
        $auth = is_array($auth) ? $auth : [];
        $sections[] = [
            'title' => 'Sessions et sécurité de connexion',
            'rows' => [
                [
                    'label' => 'Durée de vie de session (minutes)',
                    'value' => (string) (int) ($auth['session_lifetime'] ?? 0),
                ],
                [
                    'label' => 'Cookie de session uniquement en HTTPS',
                    'value' => $this->yn((bool) ($auth['session_secure_cookie'] ?? false)),
                ],
                [
                    'label' => 'Tentatives de connexion avant temporisation',
                    'value' => (string) (int) ($auth['login_max_attempts'] ?? 0),
                ],
                [
                    'label' => 'Durée de la temporisation (minutes)',
                    'value' => (string) (int) ($auth['login_lockout_minutes'] ?? 0),
                ],
            ],
        ];

        $email = function_exists('email_config') ? email_config() : [];
        $smtp = is_array($email['smtp'] ?? null) ? $email['smtp'] : [];
        $userSmtp = trim((string) ($smtp['username'] ?? ''));
        $alerts = $email['security_alert_emails'] ?? [];
        $alertList = is_array($alerts) ? array_values(array_filter(array_map('strval', $alerts))) : [];
        $alertSummary = $alertList === []
            ? 'Aucune'
            : ((string) count($alertList) . ' adresse(s) : ' . $this->truncate(implode(', ', $alertList), 120));

        $sections[] = [
            'title' => 'Courriel transactionnel',
            'rows' => [
                ['label' => 'Mode d’envoi', 'value' => $this->mailerLabel((string) ($email['default_mailer'] ?? ''))],
                ['label' => 'Adresse d’expédition', 'value' => $this->dash((string) ($email['from_address'] ?? ''))],
                ['label' => 'Nom d’expédition', 'value' => $this->dash((string) ($email['from_name'] ?? ''))],
                [
                    'label' => 'Adresse de réponse (Reply-To)',
                    'value' => $this->dash(trim((string) ($email['reply_to'] ?? '')) ?: ''),
                ],
                ['label' => 'Serveur SMTP (hôte)', 'value' => $this->dash((string) ($smtp['host'] ?? ''))],
                ['label' => 'Port SMTP', 'value' => (string) (int) ($smtp['port'] ?? 0)],
                ['label' => 'Chiffrement', 'value' => $this->dash((string) ($smtp['encryption'] ?? ''))],
                ['label' => 'Délai d’attente (secondes)', 'value' => (string) (int) ($smtp['timeout'] ?? 0)],
                [
                    'label' => 'Vérification stricte du certificat TLS',
                    'value' => $this->yn((bool) ($smtp['ssl_verify_peer'] ?? true)),
                ],
                [
                    'label' => 'Identifiant de connexion SMTP',
                    'value' => $userSmtp === '' ? 'Non renseigné' : 'Renseigné (masqué)',
                ],
                [
                    'label' => 'Mise en file d’attente des envois',
                    'value' => $this->yn(filter_var((string) (function_exists('env') ? env('MAIL_QUEUE', '') : ''), FILTER_VALIDATE_BOOLEAN)),
                    'note' => 'Si activé, les messages sont traités par un worker séparé.',
                ],
                [
                    'label' => 'Analyse géographique des connexions (alertes)',
                    'value' => $this->yn((bool) ($email['geoip_enabled'] ?? false)),
                ],
                [
                    'label' => 'Seuil de tentatives (connexion, fenêtre courte)',
                    'value' => (string) (int) ($email['login_attempt_threshold'] ?? 0),
                ],
                [
                    'label' => 'Fenêtre du compteur (secondes)',
                    'value' => (string) (int) ($email['login_attempt_window_sec'] ?? 0),
                ],
                ['label' => 'Destinataires des alertes de sécurité', 'value' => $alertSummary],
            ],
        ];

        $db = config('database.connections.mysql');
        $db = is_array($db) ? $db : [];
        $dbUser = trim((string) ($db['username'] ?? ''));
        $sections[] = [
            'title' => 'Base de données',
            'description' => 'Paramètres de connexion MySQL (sans mot de passe ni identifiant en clair).',
            'rows' => [
                ['label' => 'Moteur', 'value' => $this->dash((string) ($db['driver'] ?? 'mysql'))],
                ['label' => 'Hôte', 'value' => $this->dash((string) ($db['host'] ?? ''))],
                ['label' => 'Port', 'value' => $this->dash((string) ($db['port'] ?? ''))],
                ['label' => 'Nom de la base', 'value' => $this->dash((string) ($db['database'] ?? ''))],
                ['label' => 'Jeu de caractères', 'value' => $this->dash((string) ($db['charset'] ?? ''))],
                ['label' => 'Collation', 'value' => $this->dash((string) ($db['collation'] ?? ''))],
                ['label' => 'Compte de connexion', 'value' => $dbUser === '' ? 'Non renseigné' : 'Renseigné (masqué)'],
            ],
        ];

        $forum = config('forum');
        $forum = is_array($forum) ? $forum : [];
        $sections[] = [
            'title' => 'Salle de brief (forum)',
            'rows' => [
                ['label' => 'Module activé', 'value' => $this->yn((bool) ($forum['enabled'] ?? true))],
                [
                    'label' => 'Longueur maximale d’un message',
                    'value' => (string) (int) ($forum['forum_max_post_length'] ?? 0) . ' caractères',
                ],
                [
                    'label' => 'Délai avant sortie vers un lien externe',
                    'value' => (string) (int) ($forum['leave_countdown_seconds'] ?? 0) . ' s',
                ],
            ],
        ];

        $mod = ContentModerationConfig::fromEnv();
        $sections[] = [
            'title' => 'Modération de contenu',
            'rows' => [
                ['label' => 'Analyse automatique activée', 'value' => $this->yn($mod->enabled)],
                ['label' => 'Version du jeu de règles', 'value' => $mod->rulesetVersion],
                ['label' => 'Seuil de vigilance bas', 'value' => (string) $mod->thresholdLow],
                ['label' => 'Seuil de vigilance haut', 'value' => (string) $mod->thresholdHigh],
                ['label' => 'Durée de rétention en quarantaine', 'value' => (string) $mod->quarantineTtlDays . ' jour(s)'],
                [
                    'label' => 'Binaire ClamAV',
                    'value' => $mod->clamavBin ?? '—',
                    'note' => 'Antivirus optionnel sur les pièces jointes.',
                ],
            ],
        ];

        $atak = trim((string) (function_exists('env') ? env('NODE_ATAK_URL', '') : ''));
        $pubSlugs = trim((string) (function_exists('env') ? env('PLATFORM_PUBLIC_TENANT_SLUGS', '') : ''));
        $pubPreview = $pubSlugs === '' ? '—' : $this->truncate(str_replace(',', ', ', $pubSlugs), 100);

        $sections[] = [
            'title' => 'Intégrations et options techniques',
            'rows' => [
                [
                    'label' => 'Paiement en ligne (Stripe)',
                    'value' => ((getenv('STRIPE_SECRET_KEY') ?: '') !== '') ? 'Configuré' : 'Non configuré',
                ],
                ['label' => 'URL du nœud ATAK', 'value' => $this->dash($atak)],
                [
                    'label' => 'Parcours de formation historiques',
                    'value' => function_exists('training_legacy_enabled') && training_legacy_enabled() ? 'Affichés' : 'Masqués',
                ],
                [
                    'label' => 'Communautés listées sur la vitrine publique',
                    'value' => $pubPreview,
                    'note' => $pubSlugs === '' ? null : 'Liste fournie par l’hébergeur.',
                ],
            ],
        ];

        $sections[] = [
            'title' => 'Mentions légales et hébergement (aperçu)',
            'description' => 'Champs utilisés sur la page « Mentions légales ». Les champs vides apparaissent comme tirets sur le site public.',
            'rows' => [
                ['label' => 'Dénomination de l’éditeur', 'value' => $this->legalLine('APP_PUBLISHER_NAME')],
                ['label' => 'Adresse de l’éditeur', 'value' => $this->legalLine('APP_PUBLISHER_ADDRESS')],
                ['label' => 'Forme juridique', 'value' => $this->legalLine('APP_PUBLISHER_LEGAL_FORM')],
                ['label' => 'Numéro de TVA', 'value' => $this->legalLine('APP_PUBLISHER_VAT_ID')],
                ['label' => 'Identifiant réglementaire', 'value' => $this->legalLine('APP_PUBLISHER_IDENTIFIER')],
                ['label' => 'RCS', 'value' => $this->legalLine('APP_PUBLISHER_RCS')],
                ['label' => 'Directeur de la publication', 'value' => $this->legalLine('APP_PUBLISHER_PUBLICATION_DIRECTOR')],
                ['label' => 'Contact éditorial', 'value' => $this->legalLine('APP_PUBLISHER_CONTACT_EMAIL')],
                ['label' => 'Hébergeur — dénomination', 'value' => $this->legalLine('APP_HOSTING_NAME')],
                ['label' => 'Hébergeur — adresse', 'value' => $this->legalLine('APP_HOSTING_ADDRESS')],
                ['label' => 'Hébergeur — téléphone', 'value' => $this->legalLine('APP_HOSTING_PHONE')],
            ],
        ];

        return $sections;
    }

    /**
     * @return list<array{label: string, value: string, note?: string}>
     */
    private function buildPlatformRows(): array
    {
        $all = $this->platformSettingsRepository->listAll();
        if ($all === []) {
            return [];
        }

        $rows = [];
        foreach ($all as $key => $value) {
            $label = self::PLATFORM_SETTING_LABELS[$key] ?? 'Autre réglage plateforme';
            $known = isset(self::PLATFORM_SETTING_LABELS[$key]);
            if ($key === 'brief_member_access') {
                $v = strtolower(trim($value));
                $display = in_array($v, ['1', 'true', 'yes', 'on'], true) ? 'Oui' : 'Non';
            } elseif ($key === 'brief_member_closed_message') {
                $t = trim($value);
                $display = $t === '' ? '—' : $this->truncate($t, 240);
            } else {
                $display = $value === '' ? '—' : $this->truncate($value, 240);
            }
            $row = ['label' => $label, 'value' => $display];
            if (!$known) {
                $row['note'] = 'Clé : ' . $key;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function yn(bool $b): string
    {
        return $b ? 'Oui' : 'Non';
    }

    private function dash(string $s): string
    {
        return trim($s) === '' ? '—' : trim($s);
    }

    private function truncate(string $s, int $max): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($s, 'UTF-8') <= $max) {
                return $s;
            }

            return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
        }
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max - 1) . '…';
    }

    private function mailerLabel(string $mailer): string
    {
        $m = strtolower(trim($mailer));

        return match ($m) {
            'file' => 'Fichier sur le serveur (aucun envoi Internet)',
            'smtp' => 'SMTP',
            'sendgrid' => 'SendGrid (SMTP relais)',
            'mailgun' => 'Mailgun (SMTP relais)',
            'ses' => 'Amazon SES (SMTP relais)',
            default => $m !== '' ? $mailer : '—',
        };
    }

    private function legalLine(string $envKey): string
    {
        if (!function_exists('env')) {
            return '—';
        }
        $v = trim((string) env($envKey, ''));

        return $v === '' ? 'Non renseigné' : $v;
    }
}
