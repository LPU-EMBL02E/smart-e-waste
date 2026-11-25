// Temporary ESP32-S3 to bin-station API connectivity test.
// This is not the production controller: no GPIO, sensors, actuators, secrets,
// or deposit logic. It only joins Wi-Fi and polls station /health.

#include <Arduino.h>
#include <HTTPClient.h>
#include <WiFi.h>
#include <WiFiManager.h>

namespace {

const char* kStationHealthUrl = "http://192.168.1.34:8080/station/v1/health";
const char* kWifiApName = "ESP32 Station Link Test";
const unsigned long kPollIntervalMs = 5000;

void printConnectionInfo() {
  Serial.print("Wi-Fi status: ");
  Serial.println(WiFi.status() == WL_CONNECTED ? "connected" : "not connected");
  Serial.print("Wi-Fi status code: ");
  Serial.println(WiFi.status());
  Serial.print("ESP32 IP address: ");
  Serial.println(WiFi.localIP());
  Serial.print("Raspberry Pi API URL: ");
  Serial.println(kStationHealthUrl);
}

void requestHealth() {
  Serial.println("--- station health request ---");

  HTTPClient http;
  http.begin(kStationHealthUrl);
  const int httpCode = http.GET();

  if (httpCode > 0) {
    Serial.print("HTTP response status: ");
    Serial.println(httpCode);
    Serial.print("JSON response body: ");
    Serial.println(http.getString());
  } else {
    Serial.print("HTTP connection error: ");
    Serial.println(HTTPClient::errorToString(httpCode));
    Serial.print("HTTP status code: ");
    Serial.println(httpCode);
  }

  http.end();
}

}  // namespace

void setup() {
  Serial.begin(115200);
  Serial.println("ESP32-S3 station link test");

  WiFi.mode(WIFI_STA);
  WiFiManager wifiManager;
  wifiManager.setConfigPortalTimeout(120);

  if (!wifiManager.autoConnect(kWifiApName)) {
    Serial.println("Wi-Fi connection failed; halting.");
    while (true) {
      delay(1000);
    }
  }

  printConnectionInfo();
  requestHealth();
}

void loop() {
  static unsigned long lastPollMs = millis();
  if (millis() - lastPollMs < kPollIntervalMs) {
    delay(10);
    return;
  }
  lastPollMs = millis();
  requestHealth();
}
