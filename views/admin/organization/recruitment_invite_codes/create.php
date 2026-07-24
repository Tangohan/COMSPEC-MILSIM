<?php
declare(strict_types=1);

$recruitmentOpenings = $recruitmentOpenings ?? [];
$csrfToken = \App\Core\Csrf::token();
?>

<div class="space-y-8">
    <!-- En-tête -->
    <div>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation'), ENT_QUOTES, 'UTF-8') ?>" 
           class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Retour aux codes
        </a>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">Créer un code d'invitation</h1>
        <p class="mt-2 text-sm text-slate-600">
            Générez un code unique pour faciliter la migration de vos membres.
        </p>
    </div>

    <!-- Formulaire -->
    <form method="POST" action="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation/creer'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-8">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Carte principale -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
            <!-- Libellé -->
            <div>
                <label for="label" class="block text-sm font-semibold text-slate-900">
                    Libellé <span class="text-rose-600">*</span>
                </label>
                <p class="mt-1 text-sm text-slate-600">
                    Un nom pour identifier ce code dans votre interface (non visible par les candidats).
                </p>
                <input type="text" 
                       name="label" 
                       id="label" 
                       required
                       maxlength="255"
                       placeholder="Ex: Migration communauté partenaire"
                       class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>

            <!-- Code personnalisé (optionnel) -->
            <div>
                <label for="code" class="block text-sm font-semibold text-slate-900">
                    Code personnalisé (optionnel)
                </label>
                <p class="mt-1 text-sm text-slate-600">
                    Laissez vide pour générer automatiquement un code aléatoire sécurisé.
                </p>
                <input type="text" 
                       name="code" 
                       id="code" 
                       maxlength="64"
                       placeholder="MIGRATION2026"
                       pattern="[A-Z0-9\-_]{3,64}"
                       class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-4 py-3 font-mono text-slate-900 shadow-sm transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                <p class="mt-2 text-xs text-slate-500">
                    Uniquement lettres majuscules, chiffres, tirets et underscores (3-64 caractères).
                </p>
            </div>

            <hr class="border-slate-200">

            <!-- Validation automatique -->
            <div class="flex items-start gap-3">
                <input type="checkbox" 
                       name="auto_accept" 
                       id="auto_accept" 
                       value="1"
                       checked
                       class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-2 focus:ring-sky-500/20">
                <div class="flex-1">
                    <label for="auto_accept" class="block text-sm font-semibold text-slate-900">
                        Validation automatique
                    </label>
                    <p class="mt-1 text-sm text-slate-600">
                        Les candidatures avec ce code seront acceptées immédiatement, sans passer par la file d'instruction.
                    </p>
                </div>
            </div>

            <hr class="border-slate-200">

            <!-- Limite d'utilisations -->
            <div>
                <label for="max_uses" class="block text-sm font-semibold text-slate-900">
                    Nombre maximum d'utilisations
                </label>
                <p class="mt-1 text-sm text-slate-600">
                    Laissez vide pour un nombre illimité d'utilisations.
                </p>
                <input type="number" 
                       name="max_uses" 
                       id="max_uses" 
                       min="1"
                       max="10000"
                       placeholder="Illimité"
                       class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>

            <!-- Date d'expiration -->
            <div>
                <label for="expires_at" class="block text-sm font-semibold text-slate-900">
                    Date d'expiration
                </label>
                <p class="mt-1 text-sm text-slate-600">
                    Laissez vide pour aucune expiration.
                </p>
                <input type="datetime-local" 
                       name="expires_at" 
                       id="expires_at" 
                       class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
        </div>

        <!-- Paramètres avancés -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
            <h2 class="text-lg font-semibold text-slate-900">Paramètres avancés (optionnels)</h2>

            <!-- Affectation automatique à une offre -->
            <?php if (!empty($recruitmentOpenings)): ?>
                <div>
                    <label for="assign_to_opening_id" class="block text-sm font-semibold text-slate-900">
                        Lier automatiquement à une offre de recrutement
                    </label>
                    <p class="mt-1 text-sm text-slate-600">
                        Les candidatures avec ce code seront automatiquement associées à l'offre sélectionnée.
                    </p>
                    <select name="assign_to_opening_id" 
                            id="assign_to_opening_id" 
                            class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                        <option value="">Aucune (manuel)</option>
                        <?php foreach ($recruitmentOpenings as $opening): ?>
                            <option value="<?= htmlspecialchars((string) ($opening['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($opening['title'] ?? 'Sans titre'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Spécialité par défaut -->
            <div>
                <label for="default_specialty" class="block text-sm font-semibold text-slate-900">
                    Spécialité par défaut
                </label>
                <p class="mt-1 text-sm text-slate-600">
                    Sera utilisée si le candidat ne spécifie pas de spécialité.
                </p>
                <input type="text" 
                       name="default_specialty" 
                       id="default_specialty" 
                       maxlength="255"
                       placeholder="Ex: Infanterie"
                       class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-4">
            <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation'), ENT_QUOTES, 'UTF-8') ?>" 
               class="text-sm font-medium text-slate-600 hover:text-slate-900 transition">
                Annuler
            </a>
            <button type="submit" 
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Créer le code
            </button>
        </div>
    </form>
</div>
