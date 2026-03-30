#pragma once

// ============================================================================
// Android C++17 Compatibility Header for React Native 0.82
// Provides shims ONLY for missing C++20 library features (bit_cast, bit_width)
// Does NOT provide std:: type trait shims - NDK provides these
// Must be force-included BEFORE any other headers via -include flag
// ============================================================================

#ifdef __ANDROID__

// Include concept fix first
#include "rn_concept_fix.h"

#include <type_traits>
#include <cstddef>
#include <cstring>

namespace std {

// ============================================================================
// std::bit_cast implementation (C++20 feature, missing in NDK 25.x)
// ============================================================================
#if !defined(__cpp_lib_bit_cast) || __cpp_lib_bit_cast < 201806L
template <typename To, typename From>
inline To bit_cast(const From& src) noexcept {
    static_assert(sizeof(To) == sizeof(From), "bit_cast requires same size types");
    static_assert(std::is_trivially_copyable_v<To>, "bit_cast requires trivially copyable To");
    static_assert(std::is_trivially_copyable_v<From>, "bit_cast requires trivially copyable From");
    To dst;
    std::memcpy(&dst, &src, sizeof(To));
    return dst;
}
#endif

// ============================================================================
// std::bit_width implementation (C++20 feature, missing in NDK 25.x)
// ============================================================================
#if !defined(__cpp_lib_bitops) || __cpp_lib_bitops < 201907L
inline constexpr int bit_width(unsigned int x) noexcept {
    return x == 0 ? 0 : (sizeof(unsigned int) * 8 - __builtin_clz(x));
}

inline constexpr int bit_width(unsigned long x) noexcept {
    return x == 0 ? 0 : (sizeof(unsigned long) * 8 - __builtin_clzl(x));
}

inline constexpr int bit_width(unsigned long long x) noexcept {
    return x == 0 ? 0 : (sizeof(unsigned long long) * 8 - __builtin_clzll(x));
}
#endif

// ============================================================================
// std::identity functor (C++20 feature)
// ============================================================================
struct identity {
    template <typename T>
    constexpr T&& operator()(T&& t) const noexcept {
        return static_cast<T&&>(t);
    }
    using is_transparent = void;
};

} // namespace std

#endif // __ANDROID__
