<?php

$_secret = null; // It is highly recommended to set up your own secret to prevent unauthorized calls to the endpoint (e.g. by guessing the name)

if (!is_null($_secret) && (!isset($_REQUEST['_secret']) || $_REQUEST['_secret'] !== $_secret)) {
    header("HTTP/1.1 404 Not Found", 404, true);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['message'])) {
    throw new Exception('Response does not comply expected form!');
}

// Now do whatever you need with $data['message']
