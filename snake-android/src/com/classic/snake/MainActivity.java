package com.classic.snake;

import android.app.Activity;
import android.graphics.drawable.ColorDrawable;
import android.os.Bundle;
import android.view.Window;
import android.view.WindowManager;

public class MainActivity extends Activity {
    private GameView game;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        Window w = getWindow();
        w.setFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN, WindowManager.LayoutParams.FLAG_FULLSCREEN);
        w.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        w.setBackgroundDrawable(new ColorDrawable(GameView.BODY));
        game = new GameView(this);
        setContentView(game);
        game.requestFocus();
        hideSystemUi();
    }

    private void hideSystemUi() {
        // HIDE_NAVIGATION | FULLSCREEN | IMMERSIVE_STICKY | LAYOUT_STABLE | LAYOUT_HIDE_NAVIGATION | LAYOUT_FULLSCREEN
        getWindow().getDecorView().setSystemUiVisibility(0x0002 | 0x0004 | 0x1000 | 0x0100 | 0x0200 | 0x0400);
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) hideSystemUi();
    }

    @Override
    protected void onPause() {
        super.onPause();
        game.onHostPause();
    }

    @Override
    protected void onResume() {
        super.onResume();
        game.onHostResume();
    }

    @Override
    public void onBackPressed() {
        if (!game.onBack()) super.onBackPressed();
    }
}
