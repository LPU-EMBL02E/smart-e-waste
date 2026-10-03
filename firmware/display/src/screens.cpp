#include "../include/screens.h"

#include <string.h>

namespace {

struct Entry {
  Screen screen;
  const char* name;
};

// The names on the wire. These must match the controller's feedback module.
constexpr Entry kScreens[] = {
    {Screen::Idle, "IDLE"},
    {Screen::Welcome, "WELCOME"},
    {Screen::Weighing, "WEIGHING"},
    {Screen::Accepted, "ACCEPTED"},
    {Screen::Done, "DONE"},
    {Screen::PointsPending, "PENDING"},
    {Screen::Problem, "PROBLEM"},
    {Screen::BinFull, "BIN_FULL"},
    {Screen::Offline, "OFFLINE"},
};

}  // namespace

Screen screenFromName(const char* name) {
  for (const Entry& entry : kScreens) {
    if (strcmp(entry.name, name) == 0) {
      return entry.screen;
    }
  }
  return Screen::Unknown;
}

const char* screenName(Screen screen) {
  for (const Entry& entry : kScreens) {
    if (entry.screen == screen) {
      return entry.name;
    }
  }
  return "UNKNOWN";
}
