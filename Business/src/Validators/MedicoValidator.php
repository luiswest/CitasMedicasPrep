<?php

declare(strict_types=1);

namespace App\Validators;

use Respect\Validation\Validator;
use Respect\Validation\Exceptions\ValidationException;
   /*  usuario_id INT NOT NULL, -- Vinculado para login
    especialidad_id INT NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    licencia VARCHAR(50) NOT NULL UNIQUE,
    telefono VARCHAR(20), */

final class MedicoValidator
{
    public function validate(array $data): array
    {
        $validator = Validator::key('especialidad_id', Validator::intVal()->positive())
            ->key(
                'nombre_completo',
                Validator::stringType()
                    ->notEmpty()
                    ->length(1, 150) //?: grupo no capturante (aplicar cuantificador al conjunto)
                    ->regex('/^[A-Za-zÑñÁÉÍÓÚáéíóú]{2,}(?: [A-Za-zÑñÁÉÍÓÚáéíóú]{2,}){1,3}$/u')
                    //'([A-Za-zÑñáéíóú]+)( ([A-Za-zÑñáéíóú]+)){1,3}'
            )
            ->key('licencia', Validator::stringType()->notEmpty()->length(1, 50))
            ->key('username', Validator::stringType()->notEmpty()->length(1, 50))
            ->key(
                'password',
                Validator::stringType()
                    ->regex('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[#@$*])[\s\S]{8,16}$/')
                    //'[2-9]?[0-9]{3}\-[0-9]{4}')],
                   // /^([2-9]?[0-9]{3}\-[0-9]{4})$/';
                  // '/^([2-9][0-9]{3}\-[0-9]{4})$/'
            )
            
            ->key('telefono', Validator::optional(Validator::stringType()
                ->regex('/^([2-9][0-9]{3}\-[0-9]{4})$/')
                ->length(1, 20)));

        try {
            $validator->assert($data);
        } catch (ValidationException $exception) {
            return $exception->getMessages();
        }

        return [];
    }
}