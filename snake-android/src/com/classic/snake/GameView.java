package com.classic.snake;

import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Canvas;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.RectF;
import android.graphics.Shader;
import android.graphics.Typeface;
import android.media.AudioManager;
import android.media.ToneGenerator;
import android.os.Handler;
import android.os.SystemClock;
import android.view.KeyEvent;
import android.view.MotionEvent;
import android.view.View;

import java.util.Random;

/** Classic monochrome-LCD snake with a 12-key phone keypad. */
public class GameView extends View {
    static final int COLS = 20, ROWS = 24, CELLS = COLS * ROWS;
    static final int READY = 0, RUNNING = 1, PAUSED = 2, DYING = 3, OVER = 4;
    static final int UP = 0, RIGHT = 1, DOWN = 2, LEFT = 3;
    static final int[] DX = {0, 1, 0, -1};
    static final int[] DY = {-1, 0, 1, 0};

    static final int BODY = 0xFF17222E;
    static final int BODY_LIGHT = 0xFF2A3B4E;
    static final int BEZEL = 0xFF0C131A;
    static final int LCD_BG = 0xFFC7F0D8;
    static final int LCD_FG = 0xFF43523D;
    static final int LCD_SHADOW = 0x2643523D;
    static final int KEY = 0xFF34485E;
    static final int KEY_DOWN = 0xFF5A7896;
    static final int KEY_TEXT = 0xFFEFF4F8;
    static final int KEY_SUB = 0xFF8FA6BD;
    static final int KEY_ACCENT = 0xFF8EE0B4;

    // Keypad layout: index -> label. 2/4/6/8 steer, 5 starts/pauses, * sound, # level.
    static final String[] KEY_LABELS = {"1", "2", "3", "4", "5", "6", "7", "8", "9", "*", "0", "#"};
    static final String[] KEY_SUBS = {"", "", "def", "", "ok", "", "pqrs", "", "wxyz", "sound", "", "level"};

    static final String[][] CRITTERS = {
            {".#.##.#.", "#.####.#", ".######.", "#.#..#.#"},
            {".##.....", "#######.", ".#######", "..#..#.#"},
            {"...###.#", ".######.", "#.######", "...###.#"},
            {"#......#", ".######.", "##.##.##", ".#.##.#."},
    };

    // Snake as a ring buffer of cell indices, tail first.
    private final int[] ring = new int[CELLS];
    private int tailIdx, len;
    private final boolean[] occ = new boolean[CELLS];
    private final boolean[] fat = new boolean[CELLS];

    private int dir;
    private final int[] queue = new int[3];
    private int qn;
    private int food = -1;
    private int bonus = -1, bonusTicks, bonusMax, bonusKind;
    private int score, best, level, eaten;
    private int state = READY;
    private int blink;
    private boolean visible = true;
    private boolean sound;
    private boolean newRecord;
    private long overAt;

    private final Random rnd = new Random();
    private final Handler handler = new Handler();
    private final Runnable loop = new Runnable() {
        @Override
        public void run() {
            tick();
        }
    };
    private final SharedPreferences prefs;
    private ToneGenerator tone;

    // Layout
    private final RectF panel = new RectF(), lcd = new RectF(), field = new RectF();
    private final RectF[] keys = new RectF[12];
    private final long[] keyFlash = new long[12];
    private float cell, border, headerBase, headerSize;
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint text = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final RectF tmp = new RectF();
    private final Path path = new Path();
    private Shader bodyShader;

    // Touch
    private final int[] pointerKey = new int[16];
    private int swipeId = -1;
    private float sx, sy;
    private boolean swiped;

    public GameView(Context ctx) {
        super(ctx);
        prefs = ctx.getSharedPreferences("snake", Context.MODE_PRIVATE);
        best = prefs.getInt("best", 0);
        level = prefs.getInt("level", 5);
        sound = prefs.getBoolean("sound", true);
        for (int i = 0; i < 12; i++) keys[i] = new RectF();
        for (int i = 0; i < pointerKey.length; i++) pointerKey[i] = -1;
        text.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        setFocusable(true);
        setFocusableInTouchMode(true);
        newGame();
    }

    // ---------------------------------------------------------------- lifecycle

    void onHostPause() {
        handler.removeCallbacks(loop);
        if (state == RUNNING) state = PAUSED;
        if (state == DYING) finishDying();
        if (tone != null) {
            tone.release();
            tone = null;
        }
    }

    void onHostResume() {
        try {
            tone = new ToneGenerator(AudioManager.STREAM_MUSIC, 70);
        } catch (RuntimeException e) {
            tone = null;
        }
        invalidate();
    }

    /** @return true when the back press was consumed. */
    boolean onBack() {
        if (state == RUNNING) {
            pause();
            return true;
        }
        if (state == PAUSED || state == OVER || state == DYING) {
            handler.removeCallbacks(loop);
            newGame();
            state = READY;
            invalidate();
            return true;
        }
        return false;
    }

    // ---------------------------------------------------------------- game logic

    private void newGame() {
        java.util.Arrays.fill(occ, false);
        java.util.Arrays.fill(fat, false);
        tailIdx = 0;
        len = 0;
        int y = ROWS / 2;
        for (int x = 2; x < 7; x++) push(y * COLS + x);
        dir = RIGHT;
        qn = 0;
        score = 0;
        eaten = 0;
        bonus = -1;
        newRecord = false;
        visible = true;
        placeFood();
    }

    private void push(int c) {
        ring[(tailIdx + len) % CELLS] = c;
        len++;
        occ[c] = true;
    }

    private void popTail() {
        int c = ring[tailIdx];
        tailIdx = (tailIdx + 1) % CELLS;
        len--;
        occ[c] = false;
        fat[c] = false;
    }

    private int seg(int i) {
        return ring[(tailIdx + i) % CELLS];
    }

    private int headCell() {
        return seg(len - 1);
    }

    private int nextCell(int c, int d) {
        int x = (c % COLS + DX[d] + COLS) % COLS;
        int y = (c / COLS + DY[d] + ROWS) % ROWS;
        return y * COLS + x;
    }

    private int bonusTwin() {
        return bonus < 0 ? -1 : (bonus / COLS) * COLS + (bonus % COLS + 1) % COLS;
    }

    private boolean free(int c) {
        return !occ[c] && c != food && c != bonus && c != bonusTwin();
    }

    private void placeFood() {
        food = -1;
        int c = randomFree(false);
        food = c;
    }

    private int randomFree(boolean pair) {
        for (int i = 0; i < 200; i++) {
            int c = rnd.nextInt(CELLS);
            if (free(c) && (!pair || free(twinOf(c)))) return c;
        }
        int start = rnd.nextInt(CELLS);
        for (int i = 0; i < CELLS; i++) {
            int c = (start + i) % CELLS;
            if (free(c) && (!pair || free(twinOf(c)))) return c;
        }
        return -1;
    }

    private int twinOf(int c) {
        return (c / COLS) * COLS + (c % COLS + 1) % COLS;
    }

    private int interval() {
        return 300 - level * 26;
    }

    private void spawnBonus() {
        int c = randomFree(true);
        if (c < 0) return;
        bonus = c;
        bonusKind = rnd.nextInt(CRITTERS.length);
        bonusMax = 7000 / interval();
        bonusTicks = bonusMax;
    }

    private void start() {
        state = RUNNING;
        schedule(interval());
        invalidate();
    }

    private void pause() {
        state = PAUSED;
        handler.removeCallbacks(loop);
        invalidate();
    }

    private void schedule(long ms) {
        handler.removeCallbacks(loop);
        handler.postDelayed(loop, ms);
    }

    private void tick() {
        if (state == RUNNING) {
            step();
            if (state == RUNNING) schedule(interval());
        } else if (state == DYING) {
            blink++;
            visible = !visible;
            if (blink >= 8) finishDying();
            else schedule(170);
        }
        invalidate();
    }

    private void step() {
        if (qn > 0) {
            dir = queue[0];
            System.arraycopy(queue, 1, queue, 0, qn - 1);
            qn--;
        }
        int n = nextCell(headCell(), dir);
        boolean grow = n == food;
        if (occ[n] && !(n == seg(0) && !grow)) {
            die();
            return;
        }
        if (!grow) popTail();
        push(n);
        if (grow) {
            score += level;
            eaten++;
            fat[n] = true;
            beep(ToneGenerator.TONE_PROP_BEEP, 40);
            placeFood();
            if (food < 0) { // board full: you win
                die();
                return;
            }
            if (eaten % 5 == 0 && bonus < 0) spawnBonus();
        }
        if (bonus >= 0) {
            if (n == bonus || n == bonusTwin()) {
                score += level + (bonusTicks * 4 * level) / bonusMax;
                bonus = -1;
                beep(ToneGenerator.TONE_PROP_BEEP2, 120);
            } else if (--bonusTicks <= 0) {
                bonus = -1;
            }
        }
    }

    private void die() {
        state = DYING;
        blink = 0;
        visible = true;
        beep(ToneGenerator.TONE_SUP_ERROR, 350);
        if (score > best) {
            best = score;
            newRecord = true;
            prefs.edit().putInt("best", best).commit();
        }
        schedule(170);
    }

    private void finishDying() {
        handler.removeCallbacks(loop);
        visible = true;
        state = OVER;
        overAt = SystemClock.uptimeMillis();
    }

    private void beep(int type, int ms) {
        if (!sound || tone == null) return;
        try {
            tone.startTone(type, ms);
        } catch (RuntimeException ignored) {
        }
    }

    // ---------------------------------------------------------------- input

    private void steer(int d) {
        switch (state) {
            case READY:
                newGame();
                if (d != LEFT) enqueue(d);
                start();
                return;
            case PAUSED:
                enqueue(d);
                start();
                return;
            case RUNNING:
                enqueue(d);
                return;
            default:
        }
    }

    private void enqueue(int d) {
        int last = qn > 0 ? queue[qn - 1] : dir;
        if (d == last || d == (last + 2) % 4) return;
        if (qn < queue.length) queue[qn++] = d;
    }

    private void okPressed() {
        switch (state) {
            case READY:
            case PAUSED:
                start();
                break;
            case RUNNING:
                pause();
                break;
            case OVER:
                if (SystemClock.uptimeMillis() - overAt > 600) {
                    newGame();
                    state = READY;
                }
                break;
            default:
        }
        invalidate();
    }

    private void keyPressed(int k) {
        keyFlash[k] = SystemClock.uptimeMillis();
        postInvalidateDelayed(130);
        String l = KEY_LABELS[k];
        if (l.equals("2")) steer(UP);
        else if (l.equals("8")) steer(DOWN);
        else if (l.equals("4")) steer(LEFT);
        else if (l.equals("6")) steer(RIGHT);
        else if (l.equals("5")) okPressed();
        else if (l.equals("*")) {
            sound = !sound;
            prefs.edit().putBoolean("sound", sound).commit();
            beep(ToneGenerator.TONE_PROP_BEEP, 40);
        } else if (l.equals("#")) {
            if (state == READY || state == OVER) {
                level = level % 9 + 1;
                prefs.edit().putInt("level", level).commit();
            }
        }
        invalidate();
    }

    private int keyAt(float x, float y) {
        for (int i = 0; i < 12; i++) if (keys[i].contains(x, y)) return i;
        return -1;
    }

    @Override
    public boolean onTouchEvent(MotionEvent e) {
        int action = e.getActionMasked();
        int idx = e.getActionIndex();
        switch (action) {
            case MotionEvent.ACTION_DOWN:
            case MotionEvent.ACTION_POINTER_DOWN: {
                int id = e.getPointerId(idx);
                float x = e.getX(idx), y = e.getY(idx);
                int k = keyAt(x, y);
                if (k >= 0) {
                    if (id < pointerKey.length) pointerKey[id] = k;
                    keyPressed(k);
                } else if (swipeId < 0) {
                    swipeId = id;
                    sx = x;
                    sy = y;
                    swiped = false;
                }
                return true;
            }
            case MotionEvent.ACTION_MOVE: {
                if (swipeId < 0) return true;
                int i = e.findPointerIndex(swipeId);
                if (i < 0) return true;
                float dx = e.getX(i) - sx, dy = e.getY(i) - sy;
                float thr = Math.max(cell * 1.1f, 24 * getResources().getDisplayMetrics().density);
                if (Math.abs(dx) > thr || Math.abs(dy) > thr) {
                    if (Math.abs(dx) > Math.abs(dy)) steer(dx > 0 ? RIGHT : LEFT);
                    else steer(dy > 0 ? DOWN : UP);
                    sx = e.getX(i);
                    sy = e.getY(i);
                    swiped = true;
                    invalidate();
                }
                return true;
            }
            case MotionEvent.ACTION_UP:
            case MotionEvent.ACTION_POINTER_UP: {
                int id = e.getPointerId(idx);
                if (id < pointerKey.length) pointerKey[id] = -1;
                if (id == swipeId) {
                    if (!swiped && state != RUNNING) okPressed();
                    swipeId = -1;
                }
                return true;
            }
            case MotionEvent.ACTION_CANCEL:
                swipeId = -1;
                for (int i = 0; i < pointerKey.length; i++) pointerKey[i] = -1;
                return true;
            default:
                return true;
        }
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        switch (keyCode) {
            case KeyEvent.KEYCODE_DPAD_UP:
            case KeyEvent.KEYCODE_W:
            case KeyEvent.KEYCODE_2:
                steer(UP);
                break;
            case KeyEvent.KEYCODE_DPAD_DOWN:
            case KeyEvent.KEYCODE_S:
            case KeyEvent.KEYCODE_8:
                steer(DOWN);
                break;
            case KeyEvent.KEYCODE_DPAD_LEFT:
            case KeyEvent.KEYCODE_A:
            case KeyEvent.KEYCODE_4:
                steer(LEFT);
                break;
            case KeyEvent.KEYCODE_DPAD_RIGHT:
            case KeyEvent.KEYCODE_D:
            case KeyEvent.KEYCODE_6:
                steer(RIGHT);
                break;
            case KeyEvent.KEYCODE_DPAD_CENTER:
            case KeyEvent.KEYCODE_ENTER:
            case KeyEvent.KEYCODE_SPACE:
            case KeyEvent.KEYCODE_5:
                okPressed();
                break;
            default:
                return super.onKeyDown(keyCode, event);
        }
        invalidate();
        return true;
    }

    // ---------------------------------------------------------------- layout

    @Override
    protected void onSizeChanged(int w, int h, int ow, int oh) {
        float pad = w * 0.05f;
        float kpH = h * 0.36f;
        float availW = w - 2 * pad;
        float availH = h - kpH - 2 * pad;
        float inner = pad * 0.55f;
        // Solve for cell size: header takes ~1.8 cells, border ~0.15 cells.
        float cw = (availW - 2 * inner) / (COLS + 0.4f);
        float ch = (availH - 2 * inner) / (ROWS + 2.2f);
        cell = (float) Math.floor(Math.min(cw, ch));
        border = Math.max(2f, cell * 0.16f);
        float fw = COLS * cell + 2 * border, fh = ROWS * cell + 2 * border;
        float header = cell * 1.8f;
        float lw = fw + 2 * inner, lh = fh + header + 2 * inner;
        float left = (w - lw) / 2f;
        float top = pad + (availH - lh) / 2f;
        lcd.set(left, top, left + lw, top + lh);
        float bz = pad * 0.45f;
        panel.set(lcd.left - bz, lcd.top - bz, lcd.right + bz, lcd.bottom + bz);
        field.set(left + inner, top + inner + header, left + inner + fw, top + inner + header + fh);
        headerSize = cell * 1.3f;
        headerBase = top + inner + header * 0.72f;

        // Keypad: 4 rows x 3 columns under the screen.
        float kTop = panel.bottom + pad * 0.9f, kBottom = h - pad;
        float gap = w * 0.035f;
        float kw = (w - 2 * pad - 2 * gap) / 3f;
        float kh = Math.min((kBottom - kTop - 3 * gap) / 4f, kw * 0.62f);
        float total = 4 * kh + 3 * gap;
        float y0 = kTop + (kBottom - kTop - total) / 2f;
        for (int i = 0; i < 12; i++) {
            int r = i / 3, c = i % 3;
            float x = pad + c * (kw + gap), y = y0 + r * (kh + gap);
            keys[i].set(x, y, x + kw, y + kh);
        }
        bodyShader = new LinearGradient(0, 0, 0, h, BODY_LIGHT, BODY, Shader.TileMode.CLAMP);
    }

    // ---------------------------------------------------------------- drawing

    @Override
    protected void onDraw(Canvas c) {
        if (cell <= 0) return;
        paint.setShader(bodyShader);
        c.drawRect(0, 0, getWidth(), getHeight(), paint);
        paint.setShader(null);

        float r = panel.width() * 0.05f;
        paint.setColor(BEZEL);
        c.drawRoundRect(panel, r, r, paint);
        paint.setColor(LCD_BG);
        c.drawRoundRect(lcd, r * 0.4f, r * 0.4f, paint);

        drawHeader(c);
        drawField(c);
        if (state == READY) drawReady(c);
        else if (state == PAUSED) drawBox(c, new String[]{"PAUSED", "", "5 / Tap", "= Continue"}, 0);
        else if (state == OVER) drawOver(c);
        drawKeypad(c);
    }

    private static String pad4(int v) {
        String s = String.valueOf(v);
        while (s.length() < 4) s = "0" + s;
        return s;
    }

    private void drawHeader(Canvas c) {
        text.setColor(LCD_FG);
        text.setTextSize(headerSize);
        text.setTextAlign(Paint.Align.LEFT);
        c.drawText(pad4(score), field.left, headerBase, text);
        text.setTextAlign(Paint.Align.RIGHT);
        if (bonus >= 0 && state != READY) {
            int left = (bonusTicks * 20 + bonusMax - 1) / bonusMax;
            String s = String.valueOf(left);
            c.drawText(s, field.right, headerBase, text);
            float tw = text.measureText(s + " ");
            float s4 = headerSize * 0.2f;
            drawCritter(c, field.right - tw - 8 * s4, headerBase - 4 * s4, s4, bonusKind);
        } else {
            text.setTextSize(headerSize * 0.75f);
            c.drawText("HI " + pad4(best), field.right, headerBase, text);
        }
    }

    private void px(Canvas c, float l, float t, float r, float b) {
        float o = cell * 0.09f;
        paint.setColor(LCD_SHADOW);
        c.drawRect(l + o, t + o, r + o, b + o, paint);
        paint.setColor(LCD_FG);
        c.drawRect(l, t, r, b, paint);
    }

    private void drawField(Canvas c) {
        paint.setColor(LCD_FG);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(border);
        c.drawRect(field.left + border / 2, field.top + border / 2,
                field.right - border / 2, field.bottom - border / 2, paint);
        paint.setStyle(Paint.Style.FILL);

        float ox = field.left + border, oy = field.top + border;

        // Food: four-pixel diamond.
        if (food >= 0 && state != READY) {
            float fx = ox + (food % COLS) * cell, fy = oy + (food / COLS) * cell;
            float t = cell / 3f;
            px(c, fx + t, fy, fx + 2 * t, fy + t);
            px(c, fx, fy + t, fx + t, fy + 2 * t);
            px(c, fx + 2 * t, fy + t, fx + 3 * t, fy + 2 * t);
            px(c, fx + t, fy + 2 * t, fx + 2 * t, fy + 3 * t);
        }
        if (bonus >= 0 && state != READY) {
            float bx = ox + (bonus % COLS) * cell, by = oy + (bonus / COLS) * cell;
            if (bonus % COLS == COLS - 1) {
                // Wraps around the edge: draw each half on its own side.
                c.save();
                c.clipRect(ox, oy, ox + COLS * cell, oy + ROWS * cell);
                drawCritter(c, bx, by, cell / 4f, bonusKind);
                drawCritter(c, ox - cell, by, cell / 4f, bonusKind);
                c.restore();
            } else {
                drawCritter(c, bx, by, cell / 4f, bonusKind);
            }
        }

        if (!visible) return;
        float in = cell * 0.16f;
        int head = headCell();
        for (int i = 0; i < len; i++) {
            int s = seg(i);
            float x = ox + (s % COLS) * cell, y = oy + (s / COLS) * cell;
            float inset = fat[s] && i != len - 1 ? 0 : in;
            px(c, x + inset, y + inset, x + cell - inset, y + cell - inset);
            if (i < len - 1) {
                int n = seg(i + 1);
                int dx = n % COLS - s % COLS, dy = n / COLS - s / COLS;
                if (Math.abs(dx) + Math.abs(dy) == 1) {
                    float cx = x + cell / 2 + dx * cell / 2, cy = y + cell / 2 + dy * cell / 2;
                    float hw = cell / 2 - in;
                    px(c, cx - (dx == 0 ? hw : in), cy - (dy == 0 ? hw : in),
                            cx + (dx == 0 ? hw : in), cy + (dy == 0 ? hw : in));
                }
            }
        }
        // Eye and open mouth on the head.
        float hx = ox + (head % COLS) * cell, hy = oy + (head / COLS) * cell;
        float s4 = cell / 4f;
        int fd = dir;
        float ex = hx + cell / 2 + DX[fd] * s4 * 0.6f - DY[fd] * s4 * 0.9f;
        float ey = hy + cell / 2 + DY[fd] * s4 * 0.6f + DX[fd] * s4 * 0.9f;
        paint.setColor(LCD_BG);
        c.drawRect(ex - s4 * 0.45f, ey - s4 * 0.45f, ex + s4 * 0.45f, ey + s4 * 0.45f, paint);
        if (food >= 0 && nextCell(head, fd) == food && state == RUNNING) {
            float mx = hx + cell / 2 + DX[fd] * cell / 2, my = hy + cell / 2 + DY[fd] * cell / 2;
            float a = s4 * 1.3f, b = s4 * 0.5f;
            path.reset();
            path.moveTo(mx + DY[fd] * a, my + DX[fd] * a);
            path.lineTo(mx - DY[fd] * a, my - DX[fd] * a);
            path.lineTo(mx - DX[fd] * (a + b), my - DY[fd] * (a + b));
            path.close();
            c.drawPath(path, paint);
        }
    }

    private void drawCritter(Canvas c, float x, float y, float s, int kind) {
        String[] rows = CRITTERS[kind];
        for (int ry = 0; ry < rows.length; ry++) {
            for (int rx = 0; rx < rows[ry].length(); rx++) {
                if (rows[ry].charAt(rx) == '#') {
                    px(c, x + rx * s, y + ry * s, x + (rx + 1) * s, y + (ry + 1) * s);
                }
            }
        }
    }

    private void drawBox(Canvas c, String[] lines, int bigLines) {
        float ts = cell * 0.95f, big = cell * 2.2f, lh = ts * 1.35f;
        float h = lines.length * lh + (bigLines > 0 ? big * 1.2f - lh : 0) + cell * 1.6f;
        float w = COLS * cell * 0.86f;
        float cx = field.centerX(), cy = field.centerY();
        tmp.set(cx - w / 2, cy - h / 2, cx + w / 2, cy + h / 2);
        paint.setColor(LCD_BG);
        c.drawRect(tmp, paint);
        paint.setColor(LCD_FG);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(border);
        c.drawRect(tmp, paint);
        paint.setStyle(Paint.Style.FILL);
        text.setColor(LCD_FG);
        text.setTextAlign(Paint.Align.CENTER);
        float y = tmp.top + cell * 0.8f;
        for (int i = 0; i < lines.length; i++) {
            boolean isBig = i < bigLines;
            text.setTextSize(isBig ? big : ts);
            float step = isBig ? big * 1.2f : lh;
            y += step;
            c.drawText(lines[i], cx, y - step * 0.25f, text);
        }
    }

    private void drawReady(Canvas c) {
        drawBox(c, new String[]{"SNAKE", "", "Level " + level, "Best " + pad4(best), "",
                "5 / Tap = Start", "2 4 6 8 / Swipe", "# = Level",
                "* = Sound " + (sound ? "on" : "off")}, 1);
    }

    private void drawOver(Canvas c) {
        drawBox(c, new String[]{"Game over!", "", "Your score:", pad4(score),
                newRecord ? "New record!" : "Best " + pad4(best), "", "5 / Tap = Again"}, 0);
    }

    private void drawKeypad(Canvas c) {
        long now = SystemClock.uptimeMillis();
        for (int i = 0; i < 12; i++) {
            RectF k = keys[i];
            boolean down = now - keyFlash[i] < 120;
            for (int p = 0; p < pointerKey.length && !down; p++) down = pointerKey[p] == i;
            float r = k.height() * 0.42f;
            paint.setColor(BEZEL);
            tmp.set(k.left, k.top + k.height() * 0.06f, k.right, k.bottom + k.height() * 0.06f);
            c.drawRoundRect(tmp, r, r, paint);
            paint.setColor(down ? KEY_DOWN : KEY);
            c.drawRoundRect(k, r, r, paint);

            String l = KEY_LABELS[i];
            int arrow = l.equals("2") ? UP : l.equals("8") ? DOWN : l.equals("4") ? LEFT : l.equals("6") ? RIGHT : -1;
            text.setTextAlign(Paint.Align.CENTER);
            text.setColor(KEY_TEXT);
            text.setTextSize(k.height() * 0.42f);
            float cx = k.centerX();
            if (arrow >= 0) {
                float ax = cx + k.width() * 0.2f, ay = k.centerY(), a = k.height() * 0.17f;
                c.drawText(l, cx - k.width() * 0.12f, k.centerY() + k.height() * 0.15f, text);
                paint.setColor(KEY_ACCENT);
                path.reset();
                path.moveTo(ax + DX[arrow] * a, ay + DY[arrow] * a);
                path.lineTo(ax - DX[arrow] * a * 0.6f + DY[arrow] * a, ay - DY[arrow] * a * 0.6f + DX[arrow] * a);
                path.lineTo(ax - DX[arrow] * a * 0.6f - DY[arrow] * a, ay - DY[arrow] * a * 0.6f - DX[arrow] * a);
                path.close();
                c.drawPath(path, paint);
            } else {
                String sub = KEY_SUBS[i];
                if (l.equals("#")) sub = "level " + level;
                if (l.equals("*")) sub = sound ? "sound" : "mute";
                c.drawText(l, cx, k.centerY() + k.height() * (sub.length() > 0 ? 0.06f : 0.15f), text);
                if (sub.length() > 0) {
                    text.setColor(l.equals("5") ? KEY_ACCENT : KEY_SUB);
                    text.setTextSize(k.height() * 0.2f);
                    c.drawText(sub, cx, k.centerY() + k.height() * 0.34f, text);
                }
            }
        }
    }
}
