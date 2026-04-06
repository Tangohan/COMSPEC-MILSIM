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
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight uppercase mb-2">Mentions légales</h1>
    <p class="text-sm text-slate-500 mb-10">Informations réglementaires sur l’éditeur du site, l’hébergement et le contact.</p>

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
                <p><span class="text-slate-800 font-semibold">Immatriculation :</span> <?= htmlspecialchars($pubRcs, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($pubVat !== ''): ?>
                <p><span class="text-slate-800 font-semibold">Numéro de TVA intracommunautaire :</span> <?= htmlspecialchars($pubVat, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Directeur de la publication</h2>
            <p><?= $pubDirector !== '' ? htmlspecialchars($pubDirector, ENT_QUOTES, 'UTF-8') : '—' ?></p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Contact</h2>
            <?php $contact = legal_public_contact_email(); ?>
            <?php if ($contact !== null): ?>
                <p>Pour toute question relative au site ou au service : <a href="mailto:<?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 hover:underline"><?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?></a></p>
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
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Propriété intellectuelle</h2>
            <p>L’ensemble des éléments du portail (structure, textes, logotypes, interfaces, bases de données le cas échéant) sont protégés. Toute reproduction ou représentation non autorisée est interdite sous réserve des exceptions prévues par la loi.</p>
        </section>

        <section class="space-y-3">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Limitation de responsabilité</h2>
            <p>L’éditeur s’efforce d’assurer l’exactitude et la mise à jour des informations publiées mais ne saurait garantir l’absence d’erreurs ou d’interruptions. L’usage du service se fait sous votre responsabilité, dans le respect des présentes conditions et de la réglementation applicable.</p>
        </section>
    </div>

    <?php require base_path('views/partials/legal_crosslinks.php'); ?>
</div>
