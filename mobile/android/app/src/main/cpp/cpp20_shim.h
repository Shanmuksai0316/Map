#pragma once

#if __cplusplus < 202002L
#error "C++20 required"
#endif

#include <type_traits>
#include <functional>
#include <utility>

namespace std {

// std::identity (C++20)
struct identity {
  template <class T>
  constexpr T&& operator()(T&& t) const noexcept {
    return static_cast<T&&>(t);
  }
  using is_transparent = void;
};

// std::regular (C++20 concept) - using type traits for compatibility
// NDK 25.2.9519653's libc++ may not have all C++20 concepts, so we use SFINAE
template <typename T>
struct is_regular_helper {
private:
  template <typename U>
  static auto test_equality(int) -> decltype(
    std::declval<const U&>() == std::declval<const U&>(),
    std::true_type{}
  );
  template <typename>
  static std::false_type test_equality(...);
  
  template <typename U>
  static auto test_inequality(int) -> decltype(
    std::declval<const U&>() != std::declval<const U&>(),
    std::true_type{}
  );
  template <typename>
  static std::false_type test_inequality(...);

public:
  static constexpr bool value = 
    std::is_default_constructible_v<T> &&
    std::is_copy_constructible_v<T> &&
    std::is_copy_assignable_v<T> &&
    std::is_destructible_v<T> &&
    decltype(test_equality<T>(0))::value &&
    decltype(test_inequality<T>(0))::value;
};

// Define regular as a concept if concepts are supported, otherwise as a constexpr bool
#if defined(__cpp_concepts) && __cpp_concepts >= 201907L
template <typename T>
concept regular = is_regular_helper<T>::value;
#else
// Fallback: define as a constexpr variable template for compatibility
template <typename T>
inline constexpr bool regular = is_regular_helper<T>::value;
#endif

} // namespace std

// Hashable concept for React Native (used in hash_combine.h)
// Must be defined before React Native headers are included
namespace facebook::react {
  template <typename T>
  struct is_hashable_helper {
  private:
    template <typename U>
    static auto test(int) -> decltype(
      std::hash<U>{}(std::declval<U>()),
      std::true_type{}
    );
    template <typename>
    static std::false_type test(...);
    
  public:
    static constexpr bool value = decltype(test<T>(0))::value;
  };

#if defined(__cpp_concepts) && __cpp_concepts >= 201907L
  template <typename T>
  concept Hashable = is_hashable_helper<T>::value;
#else
  // Fallback: define as a constexpr variable template
  template <typename T>
  inline constexpr bool Hashable = is_hashable_helper<T>::value;
#endif
}
