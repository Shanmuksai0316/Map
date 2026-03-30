#pragma once

// ============================================================================
// React Native Concept Fix for C++17 Compatibility
// Disables C++20 concepts when compiling with C++17
// Must be included BEFORE any React Native headers
// ============================================================================

#ifdef __ANDROID__

// Disable C++20 concepts feature detection
#undef __cpp_concepts
#define __cpp_concepts 0L

#undef __cpp_lib_concepts
#define __cpp_lib_concepts 0L

// Disable concepts in Folly
#ifndef FOLLY_HAS_CONCEPTS
#define FOLLY_HAS_CONCEPTS 0
#endif

#ifndef FOLLY_USE_CONCEPTS
#define FOLLY_USE_CONCEPTS 0
#endif

#ifndef FOLLY_NO_CONCEPTS
#define FOLLY_NO_CONCEPTS 1
#endif

// Disable concepts in fmt
#ifndef FMT_USE_CONCEPTS
#define FMT_USE_CONCEPTS 0
#endif

#ifndef FMT_HAS_CONCEPTS
#define FMT_HAS_CONCEPTS 0
#endif

// Note: We cannot use macros to replace 'concept' keyword because:
// 1. 'concept' is a reserved keyword in C++20 (but not in C++17)
// 2. Concept syntax (concept Name = ...;) cannot be transformed to valid C++17
// 3. The prefab headers must be patched after extraction (see patch_prefab_concepts.ps1)

#endif // __ANDROID__
