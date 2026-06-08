<?php

namespace App\Controllers;

use App\Services\ReservationService;
use App\Support\Request;
use App\Support\Response;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class ReservationController
{
    private ReservationService $reservations;

    public function __construct(?ReservationService $reservations = null)
    {
        $this->reservations = $reservations ?? new ReservationService();
    }

    public function handle(Request $request): void
    {
        require_auth();

        try {
            if ($request->method() === 'GET') {
                Response::json($this->reservations->all());
            }

            if ($request->method() === 'POST') {
                $body = $request->body();
                $items = isset($body['items']) ? $body['items'] : [$body];
                $saved = $this->reservations->saveMany($items);

                if (count($saved) === 1) {
                    Response::json(['success' => true, 'item' => $saved[0], 'items' => $saved]);
                }

                Response::json(['success' => true, 'items' => $saved]);
            }

            if ($request->method() === 'PUT') {
                Response::json(['success' => true, 'item' => $this->reservations->saveOne($request->body())]);
            }

            if ($request->method() === 'DELETE') {
                $this->reservations->delete((string) $request->query('id', ''));
                Response::json(['success' => true]);
            }
        } catch (InvalidArgumentException | RuntimeException $e) {
            Response::error($e->getMessage());
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }

        Response::error('Método não permitido.', 405);
    }
}
