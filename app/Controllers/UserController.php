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
        $caller = require_auth();

        try {
            if ($request->method() === 'GET') {
                Response::json($this->users->all());
            }

            if ($request->method() === 'POST' || $request->method() === 'PUT') {
                $body      = $request->body();
                $targetId  = $body['id'] ?? '';
                $callerRole = $caller['role'] ?? '';

                // Não-admins só podem editar o próprio cadastro e não podem alterar role
                if ($callerRole !== 'administrador') {
                    if ($targetId !== ($caller['id'] ?? '')) {
                        Response::error('Sem permissão para alterar outro usuário.', 403);
                    }
                    unset($body['role']);
                }

                Response::json(['success' => true, 'item' => $this->users->save($body)]);
            }

            if ($request->method() === 'DELETE') {
                if (($caller['role'] ?? '') !== 'administrador') {
                    Response::error('Apenas o administrador pode excluir usuários.', 403);
                }
                $this->users->delete((string) $request->query('id', ''));
                Response::json(['success' => true]);
            }
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }

        Response::error('Método não permitido.', 405);
    }
}
