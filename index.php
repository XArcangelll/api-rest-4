<?php


require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$alumnos = new Classes\Alumnos;
Flight::route('GET /alumnos', [$alumnos,'selectAll']);
Flight::route('GET /alumnos/@id', [$alumnos,'selectOne']);
Flight::route('GET /alumnosp(/@page)', [$alumnos,'selectAllPage']);
Flight::route('POST /auth',[$alumnos,"auth"]);
Flight::route('POST /alumnos', [$alumnos,'insert']);
Flight::route('PUT /alumnos', [$alumnos,'update']);
Flight::route('DELETE /alumnos', [$alumnos,'delete']);


Flight::start();