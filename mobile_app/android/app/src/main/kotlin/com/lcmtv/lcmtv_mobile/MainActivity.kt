package com.lcmtv.app

import io.flutter.embedding.android.FlutterActivity
import android.os.Build

class MainActivity : FlutterActivity() {
    override fun onUserLeaveHint() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            enterPictureInPictureMode()
        }
        super.onUserLeaveHint()
    }
}
