package com.mapmars.hms

import android.view.WindowManager
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.Promise
import com.facebook.react.modules.core.DeviceEventManagerModule

class SecureScreenModule(private val reactAppContext: ReactApplicationContext) :
    ReactContextBaseJavaModule(reactAppContext) {

    override fun getName(): String {
        return "SecureScreenModule"
    }

    @ReactMethod
    fun setSecureFlag(enable: Boolean) {
        val activity = reactAppContext.currentActivity ?: return
        activity.runOnUiThread {
            if (enable) {
                activity.window.setFlags(
                    WindowManager.LayoutParams.FLAG_SECURE,
                    WindowManager.LayoutParams.FLAG_SECURE
                )
            } else {
                activity.window.clearFlags(WindowManager.LayoutParams.FLAG_SECURE)
            }
        }
    }

    @ReactMethod
    fun enableSecureScreen() {
        setSecureFlag(true)
    }

    @ReactMethod
    fun disableSecureScreen() {
        setSecureFlag(false)
    }
}

