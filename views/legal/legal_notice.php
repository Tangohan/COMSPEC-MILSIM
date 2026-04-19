<?php
declare(strict_types=1);
$pubName = trim((string) env('APP_PUBLISHER_NAME', ''));
$pubAddr = trim((string) env('APP_PUBLISHER_ADDRESS', ''));
$pubForm = trim((string) env('APP_PUBLISHER_LEGAL_FORM', ''));
$pubVat = trim((string) env('APP_PUBLISHER_VAT_ID', ''));
$pubId = trim((string) env('APP_PUBLISHER_IDENTIFIER', ''));
$pubRcs = trim((string) env('APP_PUBLISHER_RCS', ''));
$pubDirector = trim((string) env('APP_PUBLISHER_PUBLICATION_DIRECTOR', ''));
$hostName = trim((string) env('APP_HOSTING_NAME', ''));
$hostAddr = trim((string) env('APP_HOSTING_ADDRESS', ''));
$hostPhone = trim((string) env('APP_HOSTING_PHONE', ''));
$updatedAt = '19 avril 2026';
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Mentions légales</h1>
    <p class="text-sm text-slate-500 mb-2">Informations réglementaires sur l’éditeur du site, l’hébergement et les modalités de contact.</p>
    <p class="text-xs text-slate-400 mb-10">Dernière mise à jour : <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>

    <div class="space-y-8 text-sm text-slate-600 leading-relaxed">
        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Éditeur du site</h2>
            <p><span class="text-slate-800 font-semibold">Dénomination :</span> <?= $pubName !== '' ? htmlspecialchars($pubName, ENT_QUOTES, 'UTF-8') : '—' ?></p>
            <?php if ($pubForm !== ''): ?>
                <p><span class="text-slate-800 font-semibold">Forme juridique :</span> <?= htmlspecialchars($pubForm, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p><span class="text-slate-800 font-semibold">Adresse :</span><br>
                <?= $pubAddr !== '' ? nl2br(htmlspecialchars($pubAddr, ENT_QUOTES, 'UTF-8')) : '—' ?></p>
            <?php if ($pubId !== ''): ?>
                <p><span class="text-slate-800 font-semibold">Identifiant d’entreprise :</span> <?= htmlspecialchars($pubId, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($pubRcs !== ''): ?>
                <p><span class="text-slate-800 font-semibold">Immatriculation (RCS/RM) :</span> <?= htmlspecialchars($pubRcs, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($pubVat !== ''): ?>
                <p><span class="text-slate-800 font-semibold">TVA intracommunautaire :</span> <?= htmlspecialchars($pubVat, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Direction de la publication</h2>
            <p><?= $pubDirector !== '' ? htmlspecialchars($pubDirector, ENT_QUOTES, 'UTF-8') : '—' ?></p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Contact et support</h2>
            <?php $contact = legal_public_contact_email(); ?>
            <?php if ($contact !== null): ?>
                <p>Pour toute question (fonctionnement du site, signalement, protection des données, exercice des droits) : <a href="mailto:<?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:underline"><?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?></a></p>
            <?php else: ?>
                <p>—</p>
            <?php endif; ?>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Hébergement</h2>
            <p><span class="text-slate-800 font-semibold">Prestataire :</span> <?= $hostName !== '' ? htmlspecialchars($hostName, ENT_QUOTES, 'UTF-8') : '—' ?></p>
            <p><span class="text-slate-800 font-semibold">Adresse :</span><br>
                <?= $hostAddr !== '' ? nl2br(htmlspecialchars($hostAddr, ENT_QUOTES, 'UTF-8')) : '—' ?></p>
            <?php if ($hostPhone !== ''): ?>
                <p><span class="text-slate-800 font-semibold">Téléphone :</span> <?= htmlspecialchars($hostPhone, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Statut de l’intermédiaire technique</h2>
            <p>Le portail peut héberger des contenus publiés par les utilisateurs et les communautés. L’éditeur agit en qualité d’hébergeur pour ces contenus au sens de la réglementation applicable et peut retirer ou rendre inaccessible tout contenu manifestement illicite après signalement ou détection.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Propriété intellectuelle</h2>
            <p>La structure du portail, ses interfaces, ses marques, textes, éléments graphiques, logos, icônes et bases de données (le cas échéant) sont protégés par les droits de propriété intellectuelle.</p>
            <p>Toute reproduction, extraction, réutilisation, représentation ou adaptation non autorisée est interdite, hors exceptions légales ou autorisation écrite préalable.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Protection des données personnelles</h2>
            <p>Les modalités de traitement des données personnelles sont décrites dans la page <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="font-semibold text-emerald-700 hover:underline">Données personnelles</a> et la page <a href="<?= htmlspecialchars(url('cookies')) ?>" class="font-semibold text-emerald-700 hover:underline">Cookies</a>.</p>
            <p>Vous pouvez exercer vos droits via le formulaire <a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="font-semibold text-emerald-700 hover:underline">Exercer vos droits</a>.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Responsabilité</h2>
            <p>L’éditeur met en œuvre des moyens raisonnables pour assurer la disponibilité, la sécurité et la fiabilité des informations publiées, sans garantir l’absence totale d’erreurs, d’indisponibilités ou d’altération liées à Internet.</p>
            <p>Vous restez responsable de l’usage que vous faites des informations et fonctionnalités proposées sur le portail.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Droit applicable</h2>
            <p>Sauf règles impératives contraires, le présent site et ses mentions légales sont soumis au droit français. En cas de litige, les juridictions compétentes sont déterminées selon les règles de procédure applicables.</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
