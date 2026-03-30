#pragma once

// Include C++20 compatibility shim FIRST (before any other headers)
// This ensures std::regular, std::identity, and Hashable are available
#include "../cpp/cpp20_shim.h"

#include <cstring>
#include <sstream>
#include <string>
#include <utility>

#if __has_include(<format>) && defined(__cpp_lib_format)
#include <format>
#else
namespace std {
namespace __format_detail {
inline void stream_literal(std::ostringstream& oss, const char* start, const char* end) {
  oss.write(start, static_cast<std::streamsize>(end - start));
}

inline void format_impl(std::ostringstream& oss, const char* fmt) {
  oss << fmt;
}

template <typename T, typename... Args>
inline void format_impl(std::ostringstream& oss, const char* fmt, T&& value, Args&&... rest) {
  const char* placeholder = std::strstr(fmt, "{}");
  if (!placeholder) {
    oss << fmt;
    return;
  }

  stream_literal(oss, fmt, placeholder);
  oss << std::forward<T>(value);
  format_impl(oss, placeholder + 2, std::forward<Args>(rest)...);
}
} // namespace __format_detail

template <typename... Args>
inline std::string format(const char* fmt, Args&&... args) {
  std::ostringstream oss;
  __format_detail::format_impl(oss, fmt, std::forward<Args>(args)...);
  return oss.str();
}

template <typename... Args>
inline std::string format(const std::string& fmt, Args&&... args) {
  return format(fmt.c_str(), std::forward<Args>(args)...);
}
} // namespace std
#endif

