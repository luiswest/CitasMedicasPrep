<?php
namespace App\Controllers;
use App\Services\DataService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Medico {
    public function __construct(private ContainerInterface $container) {  }

    public function read(Request $request, Response $response, array $args): Response {
        try {
            $dataService = $this->container->get(DataService::class);
            $path = isset($args['id']) ? 'medicos/' . rawurlencode($args['id']) : 'medicos';

            $upstream = $dataService->get($path);

            $response->getBody()->write((string) $upstream->getBody());
            return $response
                ->withHeader(
                    'Content-Type',
                    $upstream->getHeaderLine('Content-Type') ?: 'application/json; charset=utf-8'
                )
                ->withStatus($upstream->getStatusCode());
        } catch (ConnectException) {
            return $this->json($response, ['error' => 'El servicio de datos no está disponible.'], 502);
        } catch (RequestException) {
            return $this->json($response, ['error' => 'No se pudo consultar el servicio de datos.'], 502);
        }
    }
     
    public function filter(Request $request, Response $response, array $args): Response {
        try {
            $dataService = $this->container->get(DataService::class);
            $path = 'medicos/filter/' . rawurlencode($args['pag']) . '/' . rawurlencode($args['lim']);
            $upstream = $dataService->get($path, $request->getQueryParams());

            return $this->json($response, json_decode((string) $upstream->getBody(), true), $upstream->getStatusCode());
        } catch (ConnectException) {
            return $this->json($response, ['error' => 'El servicio de datos no está disponible.'], 502);
        } catch (RequestException) {
            return $this->json($response, ['error' => 'No se pudo consultar el servicio de datos.'], 502);
        }
    }

    public function create(Request $request, Response $response, array $args): Response {
        try {
            $dataService = $this->container->get(DataService::class);
            // $body = (string) $request->getBody();
            $upstream = $dataService->post('medicos', (string) $request->getBody());
            return $this->json($response, json_decode((string) $upstream->getBody(), true), $upstream->getStatusCode());

    
        } catch (ConnectException) {
            return $this->json($response, ['error' => 'El servicio de datos no está disponible.'], 502);
        } catch (RequestException) {
            return $this->json($response, ['error' => 'No se pudo consultar el servicio de datos.'], 502);
        }
    }
    public function update(Request $request, Response $response, array $args): Response {
        try {
            $dataService = $this->container->get(DataService::class);
            $body = (string) $request->getBody();
            $upstream = $dataService->put('medicos/' . rawurlencode($args['id']), $body);
            return $this->json($response, json_decode((string) $upstream->getBody(), true), $upstream->getStatusCode());
        } catch (ConnectException) {
            return $this->json($response, ['error' => 'El servicio de datos no está disponible.'], 502);
        } catch (RequestException) {
            return $this->json($response, ['error' => 'No se pudo consultar el servicio de datos.'], 502);
        }
    }
    private function json(Response $response, array $payload, int $status): Response {
        $response->getBody()->write(json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}