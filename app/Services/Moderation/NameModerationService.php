<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Core\Session;
use App\Repositories\NameFilterRepository;

/**
 * Noms de communautés et d’unités : floutage public automatique, avertissement à la
 * personne qui enregistre le nom, alerte à l’administration du site.
 *
 * Ne bloque jamais l’enregistrement : en cas d’erreur (base pas prête…), la liste
 * livrée par défaut s’applique et l’alerte est simplement perdue.
 */
final class NameModerationService
{
    public const SUBJECT_TENANT = 'tenant';
    public const SUBJECT_UNIT = 'unit';

    private static ?NameFilter $filter = null;

    public function __construct(private NameFilterRepository $repo)
    {
    }

    public static function filter(): NameFilter
    {
        if (self::$filter !== null) {
            return self::$filter;
        }
        try {
            /** @var NameFilterRepository $repo */
            $repo = \App\Core\Container::get(NameFilterRepository::class);
            $words = array_map(static fn (array $w): array => ['word' => $w['word'], 'mode' => $w['mode']], $repo->listWords());
            self::$filter = new NameFilter($words, $repo->listAllowedNames());
        } catch (\Throwable) {
            self::$filter = new NameFilter(NameFilter::DEFAULT_WORDS);
        }

        return self::$filter;
    }

    /** À appeler après une modification de la liste ou des noms autorisés. */
    public static function resetCache(): void
    {
        self::$filter = null;
    }

    public static function maskText(string $name): string
    {
        return self::filter()->maskText($name);
    }

    public static function blurHtml(string $name): string
    {
        return self::filter()->blurHtml($name);
    }

    /**
     * Point d’appel des dépôts (communautés, unités) juste après l’enregistrement d’un nom.
     * Ne lève jamais d’exception.
     */
    public static function afterSave(string $subjectType, int $subjectId, ?int $tenantId, string $name, bool $isNew = false): void
    {
        try {
            if ($isNew && !self::filter()->isFlagged($name)) {
                return;
            }
            \App\Core\Container::get(self::class)->review($subjectType, $subjectId, $tenantId, $name);
        } catch (\Throwable) {
        }
    }

    /**
     * Vérifie un nom qui vient d’être enregistré. S’il contient un mot de la liste :
     * alerte pour l’administration du site, e-mail à l’exploitation, avertissement à l’auteur.
     *
     * @return list<string> mots trouvés (vide si le nom est propre)
     */
    public function review(string $subjectType, int $subjectId, ?int $tenantId, string $name): array
    {
        if ($subjectId < 1) {
            return [];
        }
        $words = self::filter()->matchedWords($name);
        try {
            if ($words === []) {
                $this->repo->closeAlertsForSubject($subjectType, $subjectId);

                return [];
            }
            $userId = (int) (Session::get('user_id') ?? 0) ?: null;
            $alertId = $this->repo->openAlert($subjectType, $subjectId, $tenantId, $name, $words, $userId);
            if ($alertId > 0) {
                $this->mailOperations($subjectType, $name, $words, $tenantId);
            }
        } catch (\Throwable) {
            // L’enregistrement du nom ne doit jamais échouer à cause du filtre.
        }
        $this->warnAuthor($name);

        return $words;
    }

    private function warnAuthor(string $name): void
    {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        // Texte en français : HtmlTranslator le traduit à l’affichage (lang/en/site_auto.php).
        Session::flash(
            'warning',
            '« ' . self::maskText($name) . ' » contient un terme inapproprié. Ce nom est flouté sur les pages publiques'
            . ' et l’administration du site a été prévenue. Choisissez un autre nom pour retirer le floutage.'
        );
    }

    /** @param list<string> $words */
    private function mailOperations(string $subjectType, string $name, array $words, ?int $tenantId): void
    {
        $to = function_exists('registration_alert_inbox_email') ? registration_alert_inbox_email() : null;
        if ($to === null) {
            return;
        }
        $kind = $subjectType === self::SUBJECT_TENANT ? 'communauté' : 'unité';
        $link = url('admin/system/noms-inappropries');
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $text = 'Nom de ' . $kind . ' signalé automatiquement : ' . $name . "\n"
            . 'Mots trouvés : ' . implode(', ', $words) . "\n"
            . 'Le nom est flouté sur les pages publiques. À traiter : ' . $link . "\n";
        $html = '<p>Nom de ' . $kind . ' signalé automatiquement : <strong>' . $esc($name) . '</strong></p>'
            . '<p>Mots trouvés : ' . $esc(implode(', ', $words)) . '</p>'
            . '<p>Le nom est flouté sur les pages publiques. <a href="' . $esc($link) . '">Traiter l’alerte</a></p>';
        try {
            \App\Core\Container::get(\App\Services\EmailService::class)->send(
                \App\Services\Email\EmailEvents::SECURITY_ALERT,
                $to,
                'Nom inapproprié signalé — ' . $kind,
                $html,
                $text,
                $tenantId,
                null,
                ['purpose' => 'name_filter_alert', 'subject_type' => $subjectType]
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Analyse tous les noms existants et ouvre les alertes manquantes (sans e-mail ni avertissement).
     *
     * @return int nombre d’alertes ouvertes
     */
    public function scanExisting(): int
    {
        $filter = self::filter();
        $opened = 0;
        foreach ($this->repo->listAllNames() as $row) {
            $words = $filter->matchedWords($row['name']);
            if ($words === []) {
                continue;
            }
            if ($this->repo->openAlert($row['type'], $row['id'], $row['tenant_id'], $row['name'], $words, null) > 0) {
                $opened++;
            }
        }

        return $opened;
    }
}
