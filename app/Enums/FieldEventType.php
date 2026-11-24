<?php

namespace App\Enums;

/** Lo que la app del capitán o chofer puede registrar con POST /api/v1/sync/events. */
enum FieldEventType: string
{
    case Stage = 'stage';               // llegó a una etapa del servicio
    case CheckIn = 'check_in';          // el pasajero abordó o fue recogido
    case CheckInUndo = 'check_in_undo'; // se marcó por error
    case NoShow = 'no_show';            // no se presentó
    case Fault = 'fault';               // falla en la embarcación o vehículo
}
