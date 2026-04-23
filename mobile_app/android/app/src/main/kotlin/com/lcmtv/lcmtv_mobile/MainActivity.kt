package com.lcmtv.app

import com.ryanheise.audioservice.AudioServiceActivity
import android.os.Build

class MainActivity : AudioServiceActivity() {
    override fun onUserLeaveHint() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            enterPictureInPictureMode()
        }
        super.onUserLeaveHint()
    }
}
