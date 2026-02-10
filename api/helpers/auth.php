<?php
require "jwt.php";

$h = getallheaders();

if (!isset($h['Authorization'])) {
  http_response_code(401);
  exit(json_encode(["error"=>"Unauthorized"]));
}

$user = jwt_decode(str_replace("Bearer ","",$h['Authorization']));

if (!$user || !isset($user['exp']) || $user['exp'] < time()) {
    http_response_code(401);
    exit(json_encode(["error"=>"Invalid token"]));
} 