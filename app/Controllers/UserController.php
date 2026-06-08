<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Support\Request;
use App\Support\Response;
use InvalidArgumentException;

class UserController
{
    private UserService $users;

    public function __construct(?UserService $users = null)
    {
        $this->users = $users ?? new UserService();
    }

    public function handle(Request $request): void
    {
        require_auth();

        try {
            if ($request->method() === 'GET') {
                Response::json($this->users->all());
            }

            if ($request->method() === 'POST' || $request->method() === 'PUT') {
                Response::json(['success' => true, 'item' => $this->users->save($request->body())]);
            }

            if ($request->method() === 'DELETE') {
                $this->users->delete((string) $request->query('id', ''));
                Response::json(['success' => true]);
            }
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }

        Response::error('Método não permitido.', 405);
    }
}
