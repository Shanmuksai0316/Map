package com.mapmars.hms

import android.app.Application
import androidx.core.content.res.ResourcesCompat
import com.facebook.react.PackageList
import com.facebook.react.ReactApplication
import com.facebook.react.ReactHost
import com.facebook.react.ReactNativeApplicationEntryPoint.loadReactNative
import com.facebook.react.common.assets.ReactFontManager
import com.facebook.react.defaults.DefaultReactHost.getDefaultReactHost
import com.mapmars.hms.BuildConfigPackage

class MainApplication : Application(), ReactApplication {

  override val reactHost: ReactHost by lazy {
    getDefaultReactHost(
      context = applicationContext,
      packageList =
        PackageList(this).packages.apply {
          // Packages that cannot be autolinked yet can be added manually here, for example:
          // add(MyReactNativePackage())
          add(BuildConfigPackage())
          add(SecureScreenPackage())
        },
    )
  }

  override fun onCreate() {
    super.onCreate()
    // Register Ethnocentric Rg so fontFamily "EthnocentricRg" works in JS
    ResourcesCompat.getFont(this, R.font.ethnocentric_rg)?.let { typeface ->
      ReactFontManager.getInstance().addCustomFont("EthnocentricRg", typeface)
    }
    loadReactNative(this)
  }
}
