<?php

require_once __DIR__ . '/../app/bootstrap.php';

(new App\Controllers\UserController())->handle(new App\Support\Request());
