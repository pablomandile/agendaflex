<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El horario pedido ya no está disponible (conflicto de agenda,
 * recursos agotados o cupo grupal completo).
 */
class SlotUnavailableException extends RuntimeException {}
