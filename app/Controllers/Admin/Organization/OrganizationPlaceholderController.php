<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;

class OrganizationPlaceholderController
{
    private function placeholder(string $title, string $label): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.organization.placeholder',
            'title' => $title,
            'label' => $label,
        ]);
    }

    public function users(Request $request, array $params = []): Response
    {
        return $this->placeholder('Utilisateurs', 'La gestion des utilisateurs sera disponible prochainement.');
    }

    public function roles(Request $request, array $params = []): Response
    {
        return $this->placeholder('Rôles', 'La gestion des rôles métier sera disponible prochainement.');
    }

    public function categories(Request $request, array $params = []): Response
    {
        return $this->placeholder('Catégories', 'La gestion des catégories sera disponible prochainement.');
    }

    public function groups(Request $request, array $params = []): Response
    {
        return $this->placeholder('Groupes', 'La gestion des groupes sera disponible prochainement.');
    }

    public function teams(Request $request, array $params = []): Response
    {
        return $this->placeholder('Équipes', 'La gestion des équipes sera disponible prochainement.');
    }
}
