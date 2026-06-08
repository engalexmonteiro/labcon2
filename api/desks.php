<?php

require_once __DIR__ . '/../app/bootstrap.php';

(new App\Controllers\DeskController())->handle(new App\Support\Request());
