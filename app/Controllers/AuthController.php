<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class AuthController extends Controller
{
    public function loginForm(Request $request): Response
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return $this->render('auth/login', [], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $username = trim((string) $request->input('username'));
        $password = (string) $request->input('password');

        if ($username === '' || $password === '') {
            return $this->redirectWithMessage('/login', 'Usuario y contraseña son obligatorios.', 'error');
        }

        if (!Auth::attempt($username, $password)) {
            return $this->redirectWithMessage('/login', 'Credenciales inválidas o usuario inactivo.', 'error');
        }

        Session::flash('success', 'Sesión iniciada correctamente.');
        return redirect('/');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        Session::destroy();
        Session::start();
        Session::flash('success', 'Sesion cerrada correctamente.');

        return redirect('/login');
    }
}
