package com.mapmars.hms

import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.Promise
import com.facebook.react.module.annotations.ReactModule

@ReactModule(name = BuildConfigModule.NAME)
class BuildConfigModule(reactContext: ReactApplicationContext) : ReactContextBaseJavaModule(reactContext) {

  companion object {
    const val NAME = "BuildConfig"
  }

  override fun getName(): String = NAME

  override fun getConstants(): MutableMap<String, Any> {
    val constants = HashMap<String, Any>()
    constants["BUILD_VARIANT"] = com.mapmars.hms.BuildConfig.BUILD_VARIANT
    constants["BUILD_ENV"] = com.mapmars.hms.BuildConfig.BUILD_ENV
    constants["APPLICATION_ID"] = com.mapmars.hms.BuildConfig.APPLICATION_ID
    constants["VERSION_NAME"] = com.mapmars.hms.BuildConfig.VERSION_NAME
    constants["VERSION_CODE"] = com.mapmars.hms.BuildConfig.VERSION_CODE
    constants["DEBUG"] = com.mapmars.hms.BuildConfig.DEBUG
    return constants
  }

  @ReactMethod
  fun getConstantsAsync(promise: Promise) {
    try {
      promise.resolve(getConstants())
    } catch (e: Exception) {
      promise.reject("BUILD_CONFIG_ERROR", e)
    }
  }
}
