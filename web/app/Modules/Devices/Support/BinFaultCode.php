<?php

namespace App\Modules\Devices\Support;

/**
 * The faults a bin can report in telemetry. Database_Schema.md §5,
 * API_Design.md P42.
 *
 * The device decides these and the server only stores them. A bin's current
 * fault is the one on its latest reading; NULL means no fault. The bin stops
 * taking deposits on its own while it has one (System_Plan.md §8.2).
 */
enum BinFaultCode: string
{
    case ActuatorJam = 'ACTUATOR_JAM';
    case ActuatorTimeout = 'ACTUATOR_TIMEOUT';
    case ActuatorRetractFailed = 'ACTUATOR_RETRACT_FAILED';
    case StationUnreachable = 'STATION_UNREACHABLE';
    case CameraUnavailable = 'CAMERA_UNAVAILABLE';
    case ScannerUnavailable = 'SCANNER_UNAVAILABLE';
}
