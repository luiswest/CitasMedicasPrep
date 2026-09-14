<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\Routing\RouteCollectorProxy;

$app->get('/openapi.yaml', function (Request $request, Response $response): Response {
    $openApiFile = dirname(__DIR__, 2) . '/docs/openapi.yaml';

    if (!is_readable($openApiFile)) {
        $response->getBody()->write('OpenAPI documentation has not been generated.');
        return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    $response->getBody()->write((string) file_get_contents($openApiFile));
    return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
});

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
    $api->group('/medicos', function(RouteCollectorProxy $endpoint) {
        $endpoint->get('[/{id}]', Medico::class . ':read');
        $endpoint->post('', Medico::class . ':create');
        $endpoint->put('/{id}', Medico::class . ':update');
        $endpoint->delete('/{id}', Medico::class . ':delete');
        $endpoint->get('/filter/{pag}/{lim}', Medico::class . ':filter');
    });
});
