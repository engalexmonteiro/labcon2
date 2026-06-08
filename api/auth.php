<?php

require_once __DIR__ . '/../app/bootstrap.php';

(new App\Controllers\AuthController())->handle(new App\Support\Request());
