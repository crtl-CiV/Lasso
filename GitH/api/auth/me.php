<?php
require_once __DIR__ . '/../config/bootstrap.php';
if (empty($_SESSION['user'])) respondNotLoggedIn('Not logged in.');
$me = $_SESSION['user']; unset($me['session_token']);
respond(true, ['user' => $me]);
