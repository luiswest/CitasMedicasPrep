<?php
namespace App\controllers;
use App\Models\Paciente as PacienteModel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class Paciente {
    private ContainerInterface $container;

    public function __construct(ContainerInterface $c) {
        $this->container = $c;
    }

    public function read(Request $request, Response $response, array $args): Response {
        $this->container->get('eloquent');
        if (isset($args['id'])) {
            $paciente = PacienteModel::query()
                ->select(['id', 'cedula', 'nombre_completo', 'fecha_nacimiento', 'telefono'])
                ->find($args['id']);
            if ($paciente === null) {
                return $this->json($response, ['error' => 'Paciente no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $paciente->toArray()], 200);
        }

        $pacientes = PacienteModel::query()
            ->select(['id', 'cedula', 'nombre_completo', 'fecha_nacimiento', 'telefono'])
            ->get()
            ->toArray();
        return $this->json($response, ['data' => $pacientes], 200);
    }

    public function filter(Request $request, Response $response, array $args): Response {
        $this->container->get('eloquent');
        //?? coalescing operator to provide default values if offset or limit are not provided
        $offset = filter_var($args['offset'] ?? 0, FILTER_VALIDATE_INT);
        $limit = filter_var($args['limit'] ?? 20, FILTER_VALIDATE_INT);
        if ($offset === false || $offset < 0 || $limit === false || $limit < 1 || $limit > 100) {
            return $this->json($response, [
                'error' => 'El offset debe ser mayor o igual a 0 y el límite debe estar entre 1 y 100.',
            ], 422);
        }

        $params = $request->getQueryParams();
        //Operador de coalescencia nula (??) para proporcionar valores predeterminados si los parámetros no están presentes
        $cedula = trim((string) ($params['cedula'] ?? ''));
        $name = trim((string) ($params['nombre'] ?? $params['nombre_completo'] ?? ''));

        $query = PacienteModel::query()
            ->select([
                'pacientes.id',
                'pacientes.cedula',
                'pacientes.nombre_completo',
                'pacientes.fecha_nacimiento',
                'pacientes.telefono',
            ]);
        if ($name !== '') {
            $query->where('pacientes.nombre_completo', 'like', "%{$name}%");
        }
        if ($cedula !== '') {
            $query->where('pacientes.cedula', 'like', "%{$cedula}%");
        }

        $total = (clone $query)->count('pacientes.id');
        $pacientes = $query
            ->orderBy('pacientes.nombre_completo')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return $this->json($response, [
            'data' => $pacientes,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
            ],
        ], 200);
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $errors = [];
        $cedula  = trim((string) ($data['cedula'] ?? ''));
        $fullName = trim((string) ($data['nombre_completo'] ?? ''));
        $birth_date = trim((string) ($data['fecha_nacimiento'] ?? ''));
        $phone = isset($data['telefono']) ? trim((string) $data['telefono']) : null;
        
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        

        if ($cedula === '' || mb_strlen($cedula) > 20) {
            $errors['cedula'] = 'Es obligatoria y debe tener máximo 20 caracteres.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors['nombre_completo'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
        }
        if ($birth_date === '' || !strtotime($birth_date)) {
            $errors['fecha_nacimiento'] = 'Es obligatoria y debe ser una fecha válida.';
        }

        if ($username === '' || mb_strlen($username) > 50) {
            $errors['username'] = 'Es obligatorio y debe tener máximo 50 caracteres.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Debe tener al menos 8 caracteres.';
        }
        if ($phone !== null && $phone === '') {
            $phone = null;
        } elseif ($phone !== null && mb_strlen($phone) > 20) {
            $errors['telefono'] = 'Debe tener máximo 20 caracteres.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }
        try {
            $eloquent = $this->container->get('eloquent');
            $paciente = $eloquent->connection()->transaction(
                static function () use ($cedula, $fullName, $birth_date, $phone, $username, $password): PacienteModel {
                    $roleId = User::query()
                        ->from('roles')
                        ->where('nombre', 'Paciente')
                        ->value('id');

                    if ($roleId === null) {
                        throw new RuntimeException('El rol Paciente no está configurado.');
                    }
                    $user = User::create([
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'rol_id' => $roleId,
                        'activo' => true,
                    ]);
                    return PacienteModel::create([
                        'usuario_id' => $user->id,
                        'cedula' => $cedula,
                        'nombre_completo' => $fullName,
                        'fecha_nacimiento' => $birth_date,
                        'telefono' => $phone,
                    ]);
                }
            );
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $message = (string) ($exception->errorInfo[2] ?? '');

                if (str_contains($message, 'username')) {
                    return $this->json($response, ['error' => 'El nombre de usuario ya está registrado.'], 409);
                }
                return $this->json($response, ['error' => 'La cédula ya está registrada.'], 409);
            }
            throw $exception;
        }
        return $this->json($response, ['data' => $paciente->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        // Implementation for updating a Paciente record would go here.
        $eloquent = $this->container->get('eloquent');
        if (isset($args['id'])) {
            $paciente = PacienteModel::find($args['id']);

            if ($paciente === null) {
                return $this->json($response, ['error' => 'Paciente no encontrado.'], 404);
            }

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            if (isset($data['nombre_completo'])) {
                $fullName = trim((string) $data['nombre_completo']);
                if ($fullName === '' || mb_strlen($fullName) > 150) {
                    return $this->json($response, ['error' => 'Nombre completo inválido.'], 422);
                }
                $paciente->nombre_completo = $fullName;
            }

            $paciente->save();

            return $this->json($response, ['data' => $paciente->toArray()], 200);
        }
    }
    public function delete(Request $request, Response $response, array $args): Response {
        
        $eloquent = $this->container->get('eloquent');
        if (isset($args['id'])) {
            $paciente = PacienteModel::find($args['id']);

            if ($paciente === null) {
                return $this->json($response, ['error' => 'Paciente no encontrado.'], 404);
            }

            // Verificar si el paciente tiene citas asignadas
            $citasCount = $eloquent->table('citas')
                ->where('paciente_id', $paciente->id)
                ->count();

            if ($citasCount > 0) {
                return $this->json($response, ['error' => 'No se puede eliminar el paciente porque tiene citas asignadas.'], 409);
            }

            $eloquent->connection()->transaction(static function () use ($paciente): void {
                // Obtener el ID del usuario asociado antes de eliminar el paciente
                $usuarioId = $paciente->usuario_id;
                
                // Eliminar el paciente
                $paciente->delete();
                
                // Eliminar el usuario relacionado
                if ($usuarioId !== null) {
                    $usuario = User::find($usuarioId);
                    if ($usuario !== null) {
                        $usuario->delete();
                    }
                }
            });

            return $this->json($response, ['message' => 'Paciente y usuario eliminados exitosamente.'], 200);
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