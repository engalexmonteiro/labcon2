<?php

require_once __DIR__ . '/../app/bootstrap.php';

(new App\Controllers\SmtpSettingsController())->handle(new App\Support\Request());
