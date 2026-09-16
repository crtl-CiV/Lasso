<?php
require_once __DIR__ . '/../config/bootstrap.php';
if (empty($_SESSION['user'])) respond(false, 'Not logged in.', 401);
respond(true, ['user' => $_SESSION['user']]);
