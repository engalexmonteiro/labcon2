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
            Response::json($this->state->all());
        }

        if ($request->method() === 'DELETE') {
            require_auth();
            $this->state->clear();
            Response::json(['success' => true]);
        }

        if ($request->method() === 'POST') {
            require_auth();
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
