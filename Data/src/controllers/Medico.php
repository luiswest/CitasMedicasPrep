<?php

namespace App\controllers;

use App\Models\Medico as MedicoModel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class Medico {
    private ContainerInterface $container;

    public function __construct(ContainerInterface $c) {
        $this->container = $c;
    }

    public function read(Request $request, Response $response, array $args): Response {
        $this->container->get('eloquent');

        if (isset($args['id'])) {
            $medico = MedicoModel::query()
                ->select(['medicos.id', 'especialidad_id', 'especialidades.nombre as especialidad_nombre', 'nombre_completo', 'licencia', 'telefono'])
                ->join('especialidades', 'medicos.especialidad_id', '=', 'especialidades.id')
                ->find($args['id']);

            if ($medico === null) {
                return $this->json($response, ['error' => 'Médico no encontrado.'], 404);
            }

            return $this->json($response, ['data' => $medico->toArray()], 200);
        }

        $medicos = MedicoModel::query()
            ->select(['id', 'especialidad_id', 'nombre_completo', 'licencia', 'telefono'])
            ->get()
            ->toArray();
        return $this->json($response, ['data' => $medicos], 200);
    }

    public function create(Request $request, Response $response, array $args): Response {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $errors = [];
        $specialtyId = filter_var($data['especialidad_id'] ?? null, FILTER_VALIDATE_INT);
        $fullName = trim((string) ($data['nombre_completo'] ?? ''));
        $license = trim((string) ($data['licencia'] ?? ''));
        $phone = isset($data['telefono']) ? trim((string) $data['telefono']) : null;
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($specialtyId === false || $specialtyId === null || $specialtyId < 1) {
            $errors['especialidad_id'] = 'Debe ser un entero positivo.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors['nombre_completo'] = 'Es obligatorio y debe tener máximo 150 caracteres.';
        }
        if ($license === '' || mb_strlen($license) > 50) {
            $errors['licencia'] = 'Es obligatoria y debe tener máximo 50 caracteres.';
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
            $medico = $eloquent->connection()->transaction(
                static function () use ($specialtyId, $fullName, $license, $phone, $username, $password): MedicoModel {
                    $roleId = User::query()
                        ->from('roles')
                        ->where('nombre', 'Médico')
                        ->value('id');

                    if ($roleId === null) {
                        throw new RuntimeException('El rol Médico no está configurado.');
                    }

                    $user = User::create([
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'rol_id' => $roleId,
                        'activo' => true,
                    ]);

                    return MedicoModel::create([
                        'usuario_id' => $user->id,
                        'especialidad_id' => $specialtyId,
                        'nombre_completo' => $fullName,
                        'licencia' => $license,
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

                return $this->json($response, ['error' => 'La licencia ya está registrada.'], 409);
            }

            throw $exception;
        }

        return $this->json($response, ['data' => $medico->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response {
        // Implementation for updating a Medico record would go here.
        $eloquent = $this->container->get('eloquent');
        if (isset($args['id'])) {
            $medico = MedicoModel::find($args['id']);

            if ($medico === null) {
                return $this->json($response, ['error' => 'Médico no encontrado.'], 404);
            }

            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];

            // Validate and update fields as necessary
            // For example:
            if (isset($data['nombre_completo'])) {
                $fullName = trim((string) $data['nombre_completo']);
                if ($fullName === '' || mb_strlen($fullName) > 150) {
                    return $this->json($response, ['error' => 'Nombre completo inválido.'], 422);
                }
                $medico->nombre_completo = $fullName;
            }

            // Update other fields similarly...

            $medico->save();

            return $this->json($response, ['data' => $medico->toArray()], 200);
        }
    }
    public function delete(Request $request, Response $response, array $args): Response {
        
        $eloquent = $this->container->get('eloquent');
        if (isset($args['id'])) {
            $medico = MedicoModel::find($args['id']);

            if ($medico === null) {
                return $this->json($response, ['error' => 'Médico no encontrado.'], 404);
            }

            // Verificar si el médico tiene citas asignadas
            $citasCount = $eloquent->table('citas')
                ->where('medico_id', $medico->id)
                ->count();

            if ($citasCount > 0) {
                return $this->json($response, ['error' => 'No se puede eliminar el médico porque tiene citas asignadas.'], 409);
            }

            $eloquent->connection()->transaction(static function () use ($medico): void {
                // Obtener el ID del usuario asociado antes de eliminar el médico
                $usuarioId = $medico->usuario_id;
                
                // Eliminar el médico
                $medico->delete();
                
                // Eliminar el usuario relacionado
                if ($usuarioId !== null) {
                    $usuario = User::find($usuarioId);
                    if ($usuario !== null) {
                        $usuario->delete();
                    }
                }
            });

            return $this->json($response, ['message' => 'Médico y usuario eliminados exitosamente.'], 200);
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