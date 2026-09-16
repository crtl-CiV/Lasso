<?php
require_once __DIR__ . '/../config/bootstrap.php';
$_SESSION = [];
session_destroy();
respond(true, ['message' => 'Logged out.']);
