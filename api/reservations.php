<?php

require_once __DIR__ . '/../app/bootstrap.php';

(new App\Controllers\ReservationController())->handle(new App\Support\Request());
