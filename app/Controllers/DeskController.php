<?php

namespace App\Controllers;

use App\Services\DeskService;
use App\Support\Request;
use App\Support\Response;
use InvalidArgumentException;

class DeskController
{
    private DeskService $desks;

    public function __construct(?DeskService $desks = null)
    {
        $this->desks = $desks ?? new DeskService();
    }

    public function handle(Request $request): void
    {
        require_auth();

        try {
            if ($request->method() === 'GET') {
                Response::json($this->desks->all());
            }

            if ($request->method() === 'POST' || $request->method() === 'PUT') {
                Response::json(['success' => true, 'item' => $this->desks->save($request->body())]);
            }

            if ($request->method() === 'DELETE') {
                $this->desks->delete((string) $request->query('id', ''));
                Response::json(['success' => true]);
            }
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }

        Response::error('Método não permitido.', 405);
    }
}
