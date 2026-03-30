# Copyright (c) Meta Platforms, Inc. and affiliates.
#
# This source code is licensed under the MIT license found in the
# LICENSE file in the root directory of this source tree.

cmake_minimum_required(VERSION 3.22.1)

# Set the library name
project(appmodules)

# Force C++20 standard BEFORE including React Native toolchain
set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)

# Ensure C++20 is used via compile options as well
add_compile_options(
  $<$<COMPILE_LANGUAGE:CXX>:-std=c++20>
)

# This file includes all the necessary to let you build your application with the New Architecture.
include(${REACT_ANDROID_DIR}/cmake-utils/ReactNative-application.cmake)

# Force C++20 again AFTER React Native toolchain inclusion
set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Override any target-level settings
set_property(GLOBAL PROPERTY CMAKE_CXX_STANDARD 20)
