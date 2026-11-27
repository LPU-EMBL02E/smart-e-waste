#pragma once

/**
 * Every GPIO on the pin header of the controller board: what the board has,
 * not what the bin wires to it. System_Plan.md §2.
 *
 * The board is VCC-GND Studio's YD-ESP32-S3 (silkscreen "YD-ESP32-23",
 * "2022-V1.3") with an ESP32-S3-WROOM-1-N8R2 module. It copies Espressif's
 * ESP32-S3-DevKitC-1: the same header, pin for pin, with the RGB LED on
 * GPIO48 as on the DevKitC-1 v1.0.
 *
 * Names follow the pin's purpose. A pin the board or the chip uses for
 * something (USB, the serial log, the RGB LED, boot strapping) is named after
 * that use; every other pin is named GPIOn. The bin's signals are assigned to
 * these names in pins.hpp, which checks them against this file at build time.
 * Modules include pins.hpp, never this file.
 *
 * Checked against the YD-ESP32-S3 README (header tables P1 and P2, RGB LED),
 * Espressif's ESP32-S3-DevKitC-1 user guide (the same header as J1 and J3),
 * the ESP32-S3 Series Datasheet v2.2 (§2.3.4, §2.3.5, §3, Table 2-8) and the
 * ESP32-S3-WROOM-1 datasheet (module variants). The full table, with every
 * pin's functions, is in firmware/docs/ESP32-S3.md.
 *
 * Header order, pin 1 at the antenna end:
 *   P1: 3V3, 3V3, RST, GPIO4, GPIO5, GPIO6, GPIO7, GPIO15, GPIO16, GPIO17,
 *       GPIO18, GPIO8, STRAP_JTAG (3), STRAP_BOOT_LOG (46), GPIO9, GPIO10,
 *       GPIO11, GPIO12, GPIO13, GPIO14, 5V, G
 *   P2: G, UART0_TX (43), UART0_RX (44), GPIO1, GPIO2, GPIO42, GPIO41,
 *       GPIO40, GPIO39, GPIO38, GPIO37, GPIO36, GPIO35, BOOT (0),
 *       STRAP_VDD_SPI (45), RGB_LED (48), GPIO47, GPIO21, USB_D_PLUS (20),
 *       USB_D_MINUS (19), G, G
 *
 * GPIO 22 to 25 don't exist on the ESP32-S3. GPIO 26 to 32 go to the module's
 * flash and PSRAM, and 33 and 34 are not on the header.
 */

// ------------------------------------------------------------- free GPIOs
// ADC1 (GPIO1 to GPIO10) is the only ADC that can be read while Wi-Fi is on.
constexpr int GPIO1 = 1;    // ADC1_CH0, TOUCH1
constexpr int GPIO2 = 2;    // ADC1_CH1, TOUCH2
constexpr int GPIO4 = 4;    // ADC1_CH3, TOUCH4
constexpr int GPIO5 = 5;    // ADC1_CH4, TOUCH5
constexpr int GPIO6 = 6;    // ADC1_CH5, TOUCH6
constexpr int GPIO7 = 7;    // ADC1_CH6, TOUCH7
constexpr int GPIO8 = 8;    // ADC1_CH7, TOUCH8
constexpr int GPIO9 = 9;    // ADC1_CH8, TOUCH9
constexpr int GPIO10 = 10;  // ADC1_CH9, TOUCH10
constexpr int GPIO11 = 11;  // ADC2_CH0, TOUCH11
constexpr int GPIO12 = 12;  // ADC2_CH1, TOUCH12
constexpr int GPIO13 = 13;  // ADC2_CH2, TOUCH13
constexpr int GPIO14 = 14;  // ADC2_CH3, TOUCH14
constexpr int GPIO15 = 15;  // ADC2_CH4, XTAL_32K_P (no crystal fitted)
constexpr int GPIO16 = 16;  // ADC2_CH5, XTAL_32K_N (no crystal fitted)
// GPIO17 and GPIO18 drive 10 mA by default; every other free pin 20 mA.
constexpr int GPIO17 = 17;  // ADC2_CH6
constexpr int GPIO18 = 18;  // ADC2_CH7
constexpr int GPIO21 = 21;
// Free on the N8R2: only modules with octal flash or PSRAM use GPIO35 to
// GPIO37.
constexpr int GPIO35 = 35;
constexpr int GPIO36 = 36;
constexpr int GPIO37 = 37;
// The RGB LED's pin on the DevKitC-1 v1.1, but a plain GPIO on this board.
constexpr int GPIO38 = 38;
// The JTAG pins (MTCK, MTDO, MTDI, MTMS). Free: the board debugs over USB
// instead.
constexpr int GPIO39 = 39;
constexpr int GPIO40 = 40;
constexpr int GPIO41 = 41;
constexpr int GPIO42 = 42;
constexpr int GPIO47 = 47;

// ------------------------------------------------------ boot strapping
// Sampled at reset (datasheet §3). Something that pulls one of these the
// wrong way at power-up can stop the board from booting.
constexpr int BOOT = 0;             // boot mode; also the BOOT button
constexpr int STRAP_JTAG = 3;       // JTAG signal source
constexpr int STRAP_VDD_SPI = 45;   // flash voltage
constexpr int STRAP_BOOT_LOG = 46;  // boot mode and ROM message printing

// ---------------------------------------------------------- serial log
// UART0, wired to the USB-to-UART port (a CH343P): Serial, and flashing. The
// board's TX and RX LEDs sit on these lines. Named TX and RX on the board, but
// the Arduino core already uses those names.
constexpr int UART0_TX = 43;
constexpr int UART0_RX = 44;

// ------------------------------------------------------------------ USB
// The ESP32-S3 USB port.
constexpr int USB_D_MINUS = 19;
constexpr int USB_D_PLUS = 20;

// -------------------------------------------------------------- RGB LED
// The board's addressable RGB LED, a WS2812.
constexpr int RGB_LED = 48;

/** A GPIO that is on the pin header. */
inline constexpr bool boardIsHeaderGpio(int pin) {
  return (pin >= BOOT && pin <= GPIO21) || (pin >= GPIO35 && pin <= RGB_LED);
}

/** Taken by the board: strapping, the serial log, USB or the RGB LED. */
inline constexpr bool boardIsReserved(int pin) {
  return pin == BOOT || pin == STRAP_JTAG || pin == STRAP_VDD_SPI ||
         pin == STRAP_BOOT_LOG || pin == UART0_TX || pin == UART0_RX ||
         pin == USB_D_MINUS || pin == USB_D_PLUS || pin == RGB_LED;
}

/** On the header and not taken by the board: free for the bin to wire. */
inline constexpr bool boardIsUsable(int pin) {
  return boardIsHeaderGpio(pin) && !boardIsReserved(pin);
}

/** An ADC1 pin, so it can be read while Wi-Fi is on. */
inline constexpr bool boardIsAdc1(int pin) {
  return pin >= GPIO1 && pin <= GPIO10;
}
