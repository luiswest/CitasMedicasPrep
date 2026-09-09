<?php
namespace App\controllers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\Routing\RouteCollectorProxy;

$app->get('/', function (Request $request, Response $response, array $args) {
    
    $response->getBody()->write("Hola Slim");
    return $response;
});
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

$app->group('/api', function(RouteCollectorProxy $api) {
    $api->group('/cita', function(RouteCollectorProxy $endpoint) {
        $endpoint->get('/paciente/{pacienteId}', Cita::class . ':read');
        $endpoint->get('', Cita::class . ':read');
        $endpoint->post('', Cita::class . ':create');
    });
    $api->group('/medico', function(RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', Medico::class . ':filter');
        $endpoint->get('/filter', Medico::class . ':filter');
        $endpoint->get('[/{id}]', Medico::class . ':read');
        $endpoint->post('', Medico::class . ':create');
        $endpoint->put('/{id}', Medico::class . ':update');
        $endpoint->delete('/{id}', Medico::class . ':delete');
    });
    $api->group('/paciente', function(RouteCollectorProxy $endpoint) {
        $endpoint->get('/filter/{offset}/{limit}', Paciente::class . ':filter');
        $endpoint->get('/filter', Paciente::class . ':filter');
        $endpoint->get('[/{id}]', Paciente::class . ':read');
        $endpoint->post('', Paciente::class . ':create');
        $endpoint->put('/{id}', Paciente::class . ':update');
        $endpoint->delete('/{id}', Paciente::class . ':delete');
    });    
});
