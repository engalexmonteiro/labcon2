<?php

require_once __DIR__ . '/../app/bootstrap.php';

(new App\Controllers\LabController())->handle(new App\Support\Request());
