<?php

namespace App\Controllers;

use App\Services\StateService;
use App\Support\Request;
use App\Support\Response;

class StateController
{
    private StateService $state;

    public function __construct(?StateService $state = null)
    {
        $this->state = $state ?? new StateService();
    }

    public function handle(Request $request): void
    {
        if ($request->method() === 'GET') {
            $caller = get_session_user();

            if ($caller) {
                // Usuário autenticado: retorna estado completo
                Response::json($this->state->all());
            }

            // Público: retorna dados mínimos sem PII (apenas nome e papel dos usuários)
            $full = $this->state->all();
            $full['users'] = array_map(
                fn(array $u): array => ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']],
                $full['users']
            );
            Response::json($full);
        }

        if ($request->method() === 'DELETE') {
            require_role('administrador');
            $this->state->clear();
            Response::json(['success' => true]);
        }

        if ($request->method() === 'POST') {
            require_role('administrador');
            $body = $request->body();

            if (($body['action'] ?? '') === 'seed') {
                $this->state->seed();
                Response::json(['success' => true]);
            }

            Response::error('Ação inválida.');
        }

        Response::error('Método não permitido.', 405);
    }
}
