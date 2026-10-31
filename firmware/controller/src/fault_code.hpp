#pragma once

/**
 * The bin's current fault. Database_Schema.md §5 (`bin_telemetry.fault_code`)
 * and System_Plan.md §8.2.
 *
 * The device decides these and the server only stores them, sent as
 * `fault_code` in telemetry (API_Design.md §5.3, P42). Shared by the actuator,
 * the API and the sequence in main.cpp, so it has its own file.
 *
 * Actuator faults latch until the service panel has been opened and closed
 * again; bin station faults clear when the bin station answers normally.
 */
enum class FaultCode {
  None,
  ActuatorJam,
  ActuatorTimeout,
  ActuatorRetractFailed,
  StationUnreachable,
  CameraUnavailable,
  ModelUnavailable,
  ScannerUnavailable,
};

/** The name on the wire, or nullptr for None, which is sent as null. A switch
 *  rather than a table, so -Wall flags a fault added without a name. */
inline const char* faultCodeName(FaultCode fault) {
  switch (fault) {
    case FaultCode::None:
      return nullptr;
    case FaultCode::ActuatorJam:
      return "ACTUATOR_JAM";
    case FaultCode::ActuatorTimeout:
      return "ACTUATOR_TIMEOUT";
    case FaultCode::ActuatorRetractFailed:
      return "ACTUATOR_RETRACT_FAILED";
    case FaultCode::StationUnreachable:
      return "STATION_UNREACHABLE";
    case FaultCode::CameraUnavailable:
      return "CAMERA_UNAVAILABLE";
    case FaultCode::ModelUnavailable:
      return "MODEL_UNAVAILABLE";
    case FaultCode::ScannerUnavailable:
      return "SCANNER_UNAVAILABLE";
  }
  return nullptr;
}

/** True for the faults that latch until the service panel is cycled. */
inline bool faultLatches(FaultCode fault) {
  return fault == FaultCode::ActuatorJam ||
         fault == FaultCode::ActuatorTimeout ||
         fault == FaultCode::ActuatorRetractFailed;
}
