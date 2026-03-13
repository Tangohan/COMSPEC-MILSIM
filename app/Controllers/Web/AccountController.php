<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Services\Auth\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\UserProfileRepository;

class AccountController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        return Response::view('layout.main', [
            'content' => 'account.index',
            'title' => 'Mon compte',
        ]);
    }

    public function preferences(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $profile = $this->userProfileRepository->getByUserId((int) $user['id']);
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/preferences'));
            }
            $v = new Validator($request->all(), [
                'display_name' => 'max:100',
                'callsign' => 'max:50',
                'timezone' => 'max:50',
                'language' => 'max:10',
            ]);
            if ($v->validate()) {
                $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], [
                    'display_name' => trim((string) $request->input('display_name')),
                    'callsign' => trim((string) $request->input('callsign')),
                ]);
                $this->userProfileRepository->upsert((int) $user['id'], [
                    'timezone' => trim((string) $request->input('timezone')),
                    'language' => trim((string) $request->input('language')),
                    'first_name' => trim((string) $request->input('first_name')),
                    'last_name' => trim((string) $request->input('last_name')),
                    'phone' => trim((string) $request->input('phone')),
                ]);
                Session::set('display_name', trim((string) $request->input('display_name')));
                Session::set('callsign', trim((string) $request->input('callsign')));
                Session::flash('success', 'Préférences enregistrées.');
                return Response::redirect(url('account/preferences'));
            }
            $errors = $v->errors();
        }

        return Response::view('layout.main', [
            'content' => 'account.preferences',
            'title' => 'Préférences',
            'user' => $user,
            'profile' => $profile,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public function mail(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/mail'));
            }
            $email = trim((string) $request->input('email'));
            $email_confirmation = trim((string) $request->input('email_confirmation'));
            $password = $request->input('password');

            $v = new Validator([
                'email' => $email,
                'email_confirmation' => $email_confirmation,
                'password' => $password,
            ], [
                'email' => 'required|email',
                'email_confirmation' => 'required',
                'password' => 'required',
            ]);
            if (!$v->validate()) {
                $errors = $v->errors();
            } elseif ($email !== $email_confirmation) {
                $errors['email_confirmation'] = ['Les deux adresses doivent être identiques.'];
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errors['password'] = ['Mot de passe actuel incorrect.'];
            } elseif ($this->userRepository->emailExistsInTenant((int) $user['tenant_id'], $email, (int) $user['id'])) {
                $errors['email'] = ['Cette adresse est déjà utilisée par un autre compte.'];
            } else {
                $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], ['email' => $email]);
                Session::set('email', $email);
                Session::flash('success', 'Adresse email mise à jour.');
                return Response::redirect(url('account/mail'));
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.mail',
            'title' => 'Adresse email',
            'user' => $user,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public function image(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/image'));
            }
            $file = $_FILES['avatar'] ?? null;
            if (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                $errors['avatar'] = ['Veuillez sélectionner une image (JPG, PNG ou WebP, max 2 Mo).'];
            } else {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed, true) || $file['size'] > 2 * 1024 * 1024) {
                    $errors['avatar'] = ['Format non autorisé ou fichier trop volumineux (max 2 Mo).'];
                } else {
                    $dir = base_path('public/uploads/avatars');
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg',
                    };
                    $name = $user['id'] . '_' . time() . '.' . $ext;
                    $path = $dir . DIRECTORY_SEPARATOR . $name;
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $urlPath = 'uploads/avatars/' . $name;
                        $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], ['avatar_url' => $urlPath]);
                        Session::flash('success', 'Photo de profil mise à jour.');
                        return Response::redirect(url('account/image'));
                    }
                    $errors['avatar'] = ['Impossible d\'enregistrer le fichier.'];
                }
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.image',
            'title' => 'Photo de profil',
            'user' => $user,
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public function password(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $errors = [];
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');
                return Response::redirect(url('account/password'));
            }
            $current = $request->input('current_password');
            $new = $request->input('new_password');
            $confirm = $request->input('new_password_confirmation');

            $v = new Validator([
                'current_password' => $current,
                'new_password' => $new,
                'new_password_confirmation' => $confirm,
            ], [
                'current_password' => 'required',
                'new_password' => 'required|min:8',
                'new_password_confirmation' => 'required',
            ]);
            if (!$v->validate()) {
                $errors = $v->errors();
            } elseif (!password_verify((string) $current, $user['password_hash'])) {
                $errors['current_password'] = ['Mot de passe actuel incorrect.'];
            } elseif ($new !== $confirm) {
                $errors['new_password_confirmation'] = ['Les deux mots de passe doivent être identiques.'];
            } else {
                $hash = password_hash((string) $new, PASSWORD_ARGON2ID);
                $this->userRepository->update((int) $user['id'], (int) $user['tenant_id'], ['password_hash' => $hash]);
                Session::flash('success', 'Mot de passe modifié.');
                return Response::redirect(url('account/password'));
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.password',
            'title' => 'Mot de passe',
            'errors' => $errors,
            'success' => $success,
            'error' => $error,
        ]);
    }
}
