<?php

namespace App\Exceptions;

use RuntimeException;

// Se lanza al intentar modificar o eliminar un registro de auditoria
class RegistroAuditoriaInmutable extends RuntimeException
{
}
