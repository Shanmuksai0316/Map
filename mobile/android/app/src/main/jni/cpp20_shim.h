#pragma once

// C++20 compatibility shim for React Native 0.82
// Fixes missing std::regular, std::identity, and Hashable concept issues

#include <concepts>
#include <functional>
#include <type_traits>

#if !defined(__cpp_lib_concepts) || __cpp_lib_concepts < 202002L
// If concepts are not fully supported, provide fallbacks
namespace std {
    // std::regular concept fallback
    template<typename T>
    concept regular = std::copyable<T> && std::default_initializable<T> && 
                      std::equality_comparable<T>;
    
    // std::identity fallback (C++20 feature)
    struct identity {
        template<class T>
        constexpr T&& operator()(T&& t) const noexcept {
            return std::forward<T>(t);
        }
        
        using is_transparent = void;
    };
}
#endif

// Hashable concept for React Native's hash_combine
namespace facebook::react {
    template<typename T>
    concept Hashable = requires(T a) {
        { std::hash<T>{}(a) } -> std::convertible_to<std::size_t>;
    };
    
    // hash_combine implementation with Hashable concept
    template<Hashable T, Hashable... Rest>
    void hash_combine(std::size_t& seed, const T& v, const Rest&... rest) {
        seed ^= std::hash<T>{}(v) + 0x9e3779b9 + (seed << 6) + (seed >> 2);
        if constexpr (sizeof...(rest) > 0) {
            hash_combine(seed, rest...);
        }
    }
    
    template<Hashable T, Hashable... Args>
    std::size_t hash_combine(const T& v, const Args&... args) {
        std::size_t seed = std::hash<T>{}(v);
        if constexpr (sizeof...(args) > 0) {
            hash_combine(seed, args...);
        }
        return seed;
    }
}
