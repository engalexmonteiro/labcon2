<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\Request;
use App\Support\Response;
use InvalidArgumentException;
use RuntimeException;

class AuthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function handle(Request $request): void
    {
        if ($request->method() === 'GET') {
            $user = get_session_user();
            Response::json(['authenticated' => $user !== null, 'user' => $user]);
        }

        if ($request->method() !== 'POST') {
            Response::error('Método não permitido.', 405);
        }

        $body = $request->body();
        $action = $body['action'] ?? '';

        try {
            if ($action === 'login') {
                $user = $this->auth->login($body['email'] ?? '', $body['password'] ?? '');
                Response::json(['success' => true, 'user' => $user]);
            }

            if ($action === 'register') {
                $user = $this->auth->register($body);
                Response::json(['success' => true, 'user' => $user]);
            }

            if ($action === 'logout') {
                $this->auth->logout();
                Response::json(['success' => true]);
            }
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }

        Response::error('Ação inválida.');
    }
}
