"""Station API error types. API_Design.md section 11.8 and section 3.5.

``retryable`` is false for every station error in this mock. Section 11.8 lists
the codes and status values but not ``retryable``; the controller fails closed on
any station error, so false is the safe mock value until the docs owner fixes it.
"""

from __future__ import annotations


class StationError(Exception):
    """Base class for errors returned in the section 3.5 error shape."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.http_status = http_status
        self.retryable = False


class ValidationError(StationError):
    """422 VALIDATION_ERROR for malformed bodies and invalid screen values."""

    def __init__(self, message: str) -> None:
        super().__init__("VALIDATION_ERROR", message, 422)


class NoReference(StationError):
    """409 NO_REFERENCE when verify is called before a reference exists."""

    def __init__(self, message: str = "No reference image is stored.") -> None:
        super().__init__("NO_REFERENCE", message, 409)


class CameraUnavailable(StationError):
    """503 CAMERA_UNAVAILABLE; never treated as a pass."""

    def __init__(self, message: str = "The camera is unavailable.") -> None:
        super().__init__("CAMERA_UNAVAILABLE", message, 503)


class ModelUnavailable(StationError):
    """503 MODEL_UNAVAILABLE; never treated as a pass."""

    def __init__(self, message: str = "The e-waste model is unavailable.") -> None:
        super().__init__("MODEL_UNAVAILABLE", message, 503)


class ScannerUnavailable(StationError):
    """503 SCANNER_UNAVAILABLE when arming without a usable scanner."""

    def __init__(self, message: str = "The scanner is unavailable.") -> None:
        super().__init__("SCANNER_UNAVAILABLE", message, 503)
