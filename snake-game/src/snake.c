/*
 * Snake - a small native Windows desktop game (Win32 + GDI, no dependencies).
 *
 * Controls:
 *   Arrow keys / WASD  move
 *   Enter / Space      start, restart
 *   P                  pause / resume
 *   Esc                back to menu (or quit from menu)
 */
#define WIN32_LEAN_AND_MEAN
#ifndef UNICODE
#define UNICODE
#endif
#ifndef _UNICODE
#define _UNICODE
#endif
#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <time.h>
#include <math.h>

#include "resource.h"

/* ---------- layout (logical pixels at 96 DPI) ---------- */
#define COLS        28
#define ROWS        20
#define CELL        26
#define HUD_H       56
#define PAD         14
#define BOARD_W     (COLS * CELL)
#define BOARD_H     (ROWS * CELL)
#define CLIENT_W    (BOARD_W + PAD * 2)
#define CLIENT_H    (BOARD_H + HUD_H + PAD)

#define MAX_LEN     (COLS * ROWS)
#define TIMER_ID    1
#define FRAME_MS    16

#define START_SPEED_MS 130
#define MIN_SPEED_MS   55

/* ---------- colours ---------- */
#define C_BG        RGB(18, 22, 30)
#define C_BOARD_A   RGB(30, 37, 48)
#define C_BOARD_B   RGB(34, 42, 54)
#define C_BORDER    RGB(70, 85, 105)
#define C_TEXT      RGB(235, 240, 245)
#define C_MUTED     RGB(150, 160, 175)
#define C_ACCENT    RGB(120, 220, 130)
#define C_FOOD      RGB(235, 70, 80)
#define C_LEAF      RGB(90, 180, 90)
#define C_BONUS     RGB(255, 200, 40)

typedef enum { ST_MENU, ST_PLAYING, ST_PAUSED, ST_OVER } State;
typedef struct { int x, y; } Pt;

static HWND   g_hwnd;
static int    g_dpi = 96;
static State  g_state = ST_MENU;

static Pt     g_snake[MAX_LEN];
static int    g_len;
static Pt     g_dir;
static Pt     g_queue[3];          /* buffered turns so quick key presses aren't lost */
static int    g_qlen;
static Pt     g_food;
static Pt     g_bonus;
static int    g_bonusActive;
static DWORD  g_bonusUntil;
static int    g_score;
static int    g_best;
static int    g_newBest;
static int    g_speedMs;
static DWORD  g_lastStep;
static DWORD  g_frame;

static HFONT  g_fontBig, g_fontMid, g_fontSmall;

/* ---------- helpers ---------- */
static int S(int v) { return MulDiv(v, g_dpi, 96); }

static void scorePath(wchar_t *out, size_t n)
{
    wchar_t dir[MAX_PATH];
    DWORD len = GetEnvironmentVariableW(L"APPDATA", dir, MAX_PATH);
    if (len == 0 || len >= MAX_PATH) {
        GetModuleFileNameW(NULL, dir, MAX_PATH);
        wchar_t *slash = wcsrchr(dir, L'\\');
        if (slash) *slash = 0;
        _snwprintf(out, n, L"%s\\snake_highscore.txt", dir);
        return;
    }
    wcsncat(dir, L"\\SnakeGame", MAX_PATH - wcslen(dir) - 1);
    CreateDirectoryW(dir, NULL);
    _snwprintf(out, n, L"%s\\highscore.txt", dir);
}

static void loadBest(void)
{
    wchar_t path[MAX_PATH + 32];
    scorePath(path, MAX_PATH + 32);
    FILE *f = _wfopen(path, L"r");
    if (f) {
        if (fscanf(f, "%d", &g_best) != 1) g_best = 0;
        fclose(f);
    }
}

static void saveBest(void)
{
    wchar_t path[MAX_PATH + 32];
    scorePath(path, MAX_PATH + 32);
    FILE *f = _wfopen(path, L"w");
    if (f) {
        fprintf(f, "%d\n", g_best);
        fclose(f);
    }
}

static int onSnake(int x, int y, int skipTail)
{
    int n = skipTail ? g_len - 1 : g_len;
    for (int i = 0; i < n; i++)
        if (g_snake[i].x == x && g_snake[i].y == y) return 1;
    return 0;
}

static Pt freeCell(void)
{
    Pt p;
    int free = MAX_LEN - g_len - 1;
    if (free <= 0) { p.x = -1; p.y = -1; return p; }
    do {
        p.x = rand() % COLS;
        p.y = rand() % ROWS;
    } while (onSnake(p.x, p.y, 0) ||
             (p.x == g_food.x && p.y == g_food.y) ||
             (g_bonusActive && p.x == g_bonus.x && p.y == g_bonus.y));
    return p;
}

static void newGame(void)
{
    g_len = 4;
    for (int i = 0; i < g_len; i++) {
        g_snake[i].x = COLS / 4 - i + 3;
        g_snake[i].y = ROWS / 2;
    }
    g_dir.x = 1; g_dir.y = 0;
    g_qlen = 0;
    g_score = 0;
    g_newBest = 0;
    g_speedMs = START_SPEED_MS;
    g_bonusActive = 0;
    g_food.x = -1; g_food.y = -1;
    g_food = freeCell();
    g_lastStep = GetTickCount();
    g_state = ST_PLAYING;
}

static void gameOver(void)
{
    g_state = ST_OVER;
    if (g_score > g_best) {
        g_best = g_score;
        g_newBest = 1;
        saveBest();
    }
    MessageBeep(MB_ICONHAND);
}

static void queueTurn(int dx, int dy)
{
    Pt last = g_qlen ? g_queue[g_qlen - 1] : g_dir;
    if ((last.x == dx && last.y == dy) || (last.x == -dx && last.y == -dy)) return;
    if (g_qlen < 3) {
        g_queue[g_qlen].x = dx;
        g_queue[g_qlen].y = dy;
        g_qlen++;
    }
}

static void step(void)
{
    if (g_qlen) {
        g_dir = g_queue[0];
        memmove(g_queue, g_queue + 1, sizeof(Pt) * (g_qlen - 1));
        g_qlen--;
    }

    Pt head = { g_snake[0].x + g_dir.x, g_snake[0].y + g_dir.y };

    if (head.x < 0 || head.y < 0 || head.x >= COLS || head.y >= ROWS) { gameOver(); return; }

    int eat   = (head.x == g_food.x && head.y == g_food.y);
    int bonus = g_bonusActive && head.x == g_bonus.x && head.y == g_bonus.y;
    int grow  = eat || bonus;

    /* moving into the tail cell is fine when the tail moves away this step */
    if (onSnake(head.x, head.y, !grow)) { gameOver(); return; }

    if (grow && g_len < MAX_LEN) g_len++;
    memmove(g_snake + 1, g_snake, sizeof(Pt) * (g_len - 1));
    g_snake[0] = head;

    if (eat) {
        g_score += 1;
        if (g_speedMs > MIN_SPEED_MS) g_speedMs -= 3;
        g_food = freeCell();
        if (g_food.x < 0) { gameOver(); return; }          /* board full: you win */
        if (!g_bonusActive && g_score % 5 == 0) {
            g_bonus = freeCell();
            if (g_bonus.x >= 0) {
                g_bonusActive = 1;
                g_bonusUntil = GetTickCount() + 6000;
            }
        }
    }
    if (bonus) {
        g_score += 5;
        g_bonusActive = 0;
        MessageBeep(MB_OK);
    }
}

/* ---------- drawing ---------- */
static void fillRect(HDC dc, int x, int y, int w, int h, COLORREF c)
{
    RECT r = { x, y, x + w, y + h };
    HBRUSH b = CreateSolidBrush(c);
    FillRect(dc, &r, b);
    DeleteObject(b);
}

static void roundRect(HDC dc, int x, int y, int w, int h, int rad, COLORREF c)
{
    HBRUSH b = CreateSolidBrush(c);
    HPEN p = CreatePen(PS_SOLID, 1, c);
    HGDIOBJ ob = SelectObject(dc, b), op = SelectObject(dc, p);
    RoundRect(dc, x, y, x + w, y + h, rad, rad);
    SelectObject(dc, ob); SelectObject(dc, op);
    DeleteObject(b); DeleteObject(p);
}

static void ellipse(HDC dc, int x, int y, int w, int h, COLORREF c)
{
    HBRUSH b = CreateSolidBrush(c);
    HPEN p = CreatePen(PS_SOLID, 1, c);
    HGDIOBJ ob = SelectObject(dc, b), op = SelectObject(dc, p);
    Ellipse(dc, x, y, x + w, y + h);
    SelectObject(dc, ob); SelectObject(dc, op);
    DeleteObject(b); DeleteObject(p);
}

static void text(HDC dc, HFONT f, COLORREF c, int x, int y, int w, int h, UINT fmt, const wchar_t *s)
{
    RECT r = { x, y, x + w, y + h };
    SelectObject(dc, f);
    SetTextColor(dc, c);
    DrawTextW(dc, s, -1, &r, fmt | DT_NOPREFIX);
}

static COLORREF lerp(COLORREF a, COLORREF b, int t, int max)
{
    if (max <= 0) return a;
    int r = GetRValue(a) + (GetRValue(b) - GetRValue(a)) * t / max;
    int g = GetGValue(a) + (GetGValue(b) - GetGValue(a)) * t / max;
    int bl = GetBValue(a) + (GetBValue(b) - GetBValue(a)) * t / max;
    return RGB(r, g, bl);
}

static void drawBoard(HDC dc, int ox, int oy)
{
    fillRect(dc, ox - S(3), oy - S(3), S(BOARD_W) + S(6), S(BOARD_H) + S(6), C_BORDER);
    for (int y = 0; y < ROWS; y++)
        for (int x = 0; x < COLS; x++)
            fillRect(dc, ox + S(x * CELL), oy + S(y * CELL),
                     S((x + 1) * CELL) - S(x * CELL), S((y + 1) * CELL) - S(y * CELL),
                     ((x + y) & 1) ? C_BOARD_A : C_BOARD_B);
}

static void drawFood(HDC dc, int ox, int oy)
{
    int cx = ox + S(g_food.x * CELL), cy = oy + S(g_food.y * CELL);
    int pulse = (int)((g_frame / 4) % 8);
    int inset = S(4) - (pulse < 4 ? pulse : 8 - pulse) / 2;
    ellipse(dc, cx + inset, cy + inset + S(2), S(CELL) - inset * 2, S(CELL) - inset * 2 - S(1), C_FOOD);
    ellipse(dc, cx + S(CELL) / 2, cy + S(2), S(7), S(5), C_LEAF);
    ellipse(dc, cx + inset + S(4), cy + inset + S(5), S(4), S(4), RGB(255, 170, 170));

    if (g_bonusActive) {
        DWORD left = g_bonusUntil - GetTickCount();
        if (left > 2000 || (g_frame / 6) % 2) {
            int bx = ox + S(g_bonus.x * CELL), by = oy + S(g_bonus.y * CELL);
            POINT star[10];
            int c = S(CELL) / 2;
            for (int i = 0; i < 10; i++) {
                double a = -3.14159265 / 2 + i * 3.14159265 / 5;
                double r = (i % 2) ? c * 0.42 : c * 0.95;
                star[i].x = bx + c + (int)(r * cos(a));
                star[i].y = by + c + (int)(r * sin(a));
            }
            HBRUSH b = CreateSolidBrush(C_BONUS);
            HPEN p = CreatePen(PS_SOLID, 1, RGB(255, 235, 150));
            HGDIOBJ ob = SelectObject(dc, b), op = SelectObject(dc, p);
            Polygon(dc, star, 10);
            SelectObject(dc, ob); SelectObject(dc, op);
            DeleteObject(b); DeleteObject(p);
        }
    }
}

static void drawSnake(HDC dc, int ox, int oy)
{
    int c = S(CELL);
    for (int i = g_len - 1; i >= 0; i--) {
        int x = ox + S(g_snake[i].x * CELL), y = oy + S(g_snake[i].y * CELL);
        COLORREF col = lerp(RGB(140, 235, 120), RGB(40, 130, 90), i, g_len);
        if (g_state == ST_OVER) col = lerp(col, RGB(110, 110, 110), 2, 3);
        int in = (i == 0) ? S(1) : S(3);
        roundRect(dc, x + in, y + in, c - in * 2, c - in * 2, S(10), col);

        /* connect segments so the body looks continuous */
        if (i > 0) {
            Pt a = g_snake[i], b = g_snake[i - 1];
            int mx = ox + S(((a.x + b.x) * CELL) / 2), my = oy + S(((a.y + b.y) * CELL) / 2);
            fillRect(dc, mx + S(4), my + S(4), c - S(8), c - S(8), col);
        }
    }

    /* eyes */
    int hx = ox + S(g_snake[0].x * CELL), hy = oy + S(g_snake[0].y * CELL);
    int e = S(6), p = S(3);
    int ex1, ey1, ex2, ey2;
    if (g_dir.x == 1)      { ex1 = c - S(11); ey1 = S(5);  ex2 = c - S(11); ey2 = c - S(11); }
    else if (g_dir.x == -1){ ex1 = S(5);  ey1 = S(5);  ex2 = S(5);  ey2 = c - S(11); }
    else if (g_dir.y == -1){ ex1 = S(5);  ey1 = S(5);  ex2 = c - S(11); ey2 = S(5); }
    else                   { ex1 = S(5);  ey1 = c - S(11); ex2 = c - S(11); ey2 = c - S(11); }
    ellipse(dc, hx + ex1, hy + ey1, e, e, RGB(255, 255, 255));
    ellipse(dc, hx + ex2, hy + ey2, e, e, RGB(255, 255, 255));
    int px = g_dir.x * S(1), py = g_dir.y * S(1);
    ellipse(dc, hx + ex1 + (e - p) / 2 + px, hy + ey1 + (e - p) / 2 + py, p, p, RGB(20, 20, 20));
    ellipse(dc, hx + ex2 + (e - p) / 2 + px, hy + ey2 + (e - p) / 2 + py, p, p, RGB(20, 20, 20));
}

static void drawOverlay(HDC dc, int ox, int oy, const wchar_t *title, COLORREF tc,
                        const wchar_t *l1, const wchar_t *l2, const wchar_t *l3)
{
    int w = S(BOARD_W), h = S(BOARD_H);
    /* dim the board: blend a dark rectangle with AlphaBlend-free trick (pattern of
       solid fill at low alpha isn't available in GDI, so draw a panel instead) */
    int pw = S(420), ph = S(230);
    int px = ox + (w - pw) / 2, py = oy + (h - ph) / 2;
    roundRect(dc, px - S(2), py - S(2), pw + S(4), ph + S(4), S(24), C_BORDER);
    roundRect(dc, px, py, pw, ph, S(22), RGB(22, 27, 36));

    text(dc, g_fontBig, tc, px, py + S(26), pw, S(60), DT_CENTER | DT_SINGLELINE, title);
    if (l1) text(dc, g_fontMid,   C_TEXT,  px, py + S(98),  pw, S(30), DT_CENTER | DT_SINGLELINE, l1);
    if (l2) text(dc, g_fontSmall, C_MUTED, px, py + S(140), pw, S(26), DT_CENTER | DT_SINGLELINE, l2);
    if (l3) text(dc, g_fontSmall, C_MUTED, px, py + S(168), pw, S(26), DT_CENTER | DT_SINGLELINE, l3);
}

static void paint(HDC dc, int cw, int ch)
{
    fillRect(dc, 0, 0, cw, ch, C_BG);
    SetBkMode(dc, TRANSPARENT);

    /* HUD */
    wchar_t buf[128];
    text(dc, g_fontMid, C_ACCENT, S(PAD), S(12), S(200), S(36), DT_LEFT | DT_SINGLELINE | DT_VCENTER, L"\u25CF SNAKE");
    _snwprintf(buf, 128, L"Score  %d", g_score);
    text(dc, g_fontMid, C_TEXT, 0, S(12), cw, S(36), DT_CENTER | DT_SINGLELINE | DT_VCENTER, buf);
    _snwprintf(buf, 128, L"Best  %d", g_best);
    text(dc, g_fontMid, C_BONUS, 0, S(12), cw - S(PAD), S(36), DT_RIGHT | DT_SINGLELINE | DT_VCENTER, buf);

    int ox = S(PAD), oy = S(HUD_H);
    drawBoard(dc, ox, oy);

    if (g_state != ST_MENU) {
        drawFood(dc, ox, oy);
        drawSnake(dc, ox, oy);
    }

    switch (g_state) {
    case ST_MENU:
        drawOverlay(dc, ox, oy, L"SNAKE", C_ACCENT,
                    L"Press ENTER to start",
                    L"Arrows / WASD to move  \u2022  P to pause",
                    L"Catch golden stars for +5 bonus");
        break;
    case ST_PAUSED:
        drawOverlay(dc, ox, oy, L"PAUSED", C_BONUS,
                    L"Press P to resume", L"Esc for menu", NULL);
        break;
    case ST_OVER:
        _snwprintf(buf, 128, g_newBest ? L"New best score: %d!" : L"Score: %d", g_score);
        drawOverlay(dc, ox, oy, L"GAME OVER", C_FOOD, buf,
                    L"Press ENTER to play again", L"Esc for menu");
        break;
    default:
        break;
    }
}

static void makeFonts(void)
{
    if (g_fontBig) { DeleteObject(g_fontBig); DeleteObject(g_fontMid); DeleteObject(g_fontSmall); }
    g_fontBig   = CreateFontW(-S(48), 0, 0, 0, FW_HEAVY, 0, 0, 0, DEFAULT_CHARSET, 0, 0, CLEARTYPE_QUALITY, 0, L"Segoe UI");
    g_fontMid   = CreateFontW(-S(22), 0, 0, 0, FW_BOLD,  0, 0, 0, DEFAULT_CHARSET, 0, 0, CLEARTYPE_QUALITY, 0, L"Segoe UI");
    g_fontSmall = CreateFontW(-S(16), 0, 0, 0, FW_NORMAL,0, 0, 0, DEFAULT_CHARSET, 0, 0, CLEARTYPE_QUALITY, 0, L"Segoe UI");
}

static void resizeForDpi(HWND hwnd)
{
    RECT r = { 0, 0, S(CLIENT_W), S(CLIENT_H) };
    DWORD style = (DWORD)GetWindowLongPtrW(hwnd, GWL_STYLE);
    AdjustWindowRect(&r, style, FALSE);
    SetWindowPos(hwnd, NULL, 0, 0, r.right - r.left, r.bottom - r.top, SWP_NOMOVE | SWP_NOZORDER);
}

/* ---------- window proc ---------- */
static LRESULT CALLBACK WndProc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp)
{
    switch (msg) {
    case WM_TIMER: {
        g_frame++;
        DWORD now = GetTickCount();
        if (g_state == ST_PLAYING) {
            if (g_bonusActive && (LONG)(now - g_bonusUntil) >= 0) g_bonusActive = 0;
            if (now - g_lastStep >= (DWORD)g_speedMs) {
                g_lastStep = now;
                step();
            }
        }
        InvalidateRect(hwnd, NULL, FALSE);
        return 0;
    }
    case WM_KEYDOWN:
        switch (wp) {
        case VK_UP:    case 'W': if (g_state == ST_PLAYING) queueTurn(0, -1); break;
        case VK_DOWN:  case 'S': if (g_state == ST_PLAYING) queueTurn(0, 1);  break;
        case VK_LEFT:  case 'A': if (g_state == ST_PLAYING) queueTurn(-1, 0); break;
        case VK_RIGHT: case 'D': if (g_state == ST_PLAYING) queueTurn(1, 0);  break;
        case VK_RETURN: case VK_SPACE:
            if (g_state == ST_MENU || g_state == ST_OVER) newGame();
            else if (g_state == ST_PAUSED && wp == VK_SPACE) { g_state = ST_PLAYING; g_lastStep = GetTickCount(); }
            break;
        case 'P':
            if (g_state == ST_PLAYING) g_state = ST_PAUSED;
            else if (g_state == ST_PAUSED) {
                g_state = ST_PLAYING;
                g_lastStep = GetTickCount();
            }
            break;
        case VK_ESCAPE:
            if (g_state == ST_MENU) DestroyWindow(hwnd);
            else g_state = ST_MENU;
            break;
        }
        return 0;
    case WM_KILLFOCUS:
        if (g_state == ST_PLAYING) g_state = ST_PAUSED;
        return 0;
    case WM_ERASEBKGND:
        return 1;
    case WM_PAINT: {
        PAINTSTRUCT ps;
        HDC dc = BeginPaint(hwnd, &ps);
        RECT rc;
        GetClientRect(hwnd, &rc);
        HDC mem = CreateCompatibleDC(dc);
        HBITMAP bmp = CreateCompatibleBitmap(dc, rc.right, rc.bottom);
        HGDIOBJ old = SelectObject(mem, bmp);
        paint(mem, rc.right, rc.bottom);
        BitBlt(dc, 0, 0, rc.right, rc.bottom, mem, 0, 0, SRCCOPY);
        SelectObject(mem, old);
        DeleteObject(bmp);
        DeleteDC(mem);
        EndPaint(hwnd, &ps);
        return 0;
    }
    case 0x02E0: /* WM_DPICHANGED */
        g_dpi = HIWORD(wp);
        makeFonts();
        {
            RECT *r = (RECT *)lp;
            SetWindowPos(hwnd, NULL, r->left, r->top, 0, 0, SWP_NOSIZE | SWP_NOZORDER);
        }
        resizeForDpi(hwnd);
        return 0;
    case WM_DESTROY:
        KillTimer(hwnd, TIMER_ID);
        PostQuitMessage(0);
        return 0;
    }
    return DefWindowProcW(hwnd, msg, wp, lp);
}

static void enableDpiAwareness(void)
{
    HMODULE user = GetModuleHandleW(L"user32.dll");
    typedef BOOL (WINAPI *SetCtx)(HANDLE);
    SetCtx setCtx = user ? (SetCtx)(void *)GetProcAddress(user, "SetProcessDpiAwarenessContext") : NULL;
    if (setCtx && setCtx((HANDLE)-4 /* PER_MONITOR_AWARE_V2 */)) return;
    SetProcessDPIAware();
}

static int windowDpi(HWND hwnd)
{
    HMODULE user = GetModuleHandleW(L"user32.dll");
    typedef UINT (WINAPI *GetDpi)(HWND);
    GetDpi getDpi = user ? (GetDpi)(void *)GetProcAddress(user, "GetDpiForWindow") : NULL;
    if (getDpi) return (int)getDpi(hwnd);
    HDC dc = GetDC(NULL);
    int d = GetDeviceCaps(dc, LOGPIXELSX);
    ReleaseDC(NULL, dc);
    return d;
}

int WINAPI wWinMain(HINSTANCE hInst, HINSTANCE prev, PWSTR cmd, int show)
{
    (void)prev; (void)cmd;
    srand((unsigned)time(NULL) ^ GetTickCount());
    enableDpiAwareness();
    loadBest();

    WNDCLASSEXW wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.cbSize        = sizeof(wc);
    wc.lpfnWndProc   = WndProc;
    wc.hInstance     = hInst;
    wc.hCursor       = LoadCursor(NULL, IDC_ARROW);
    wc.hIcon         = LoadIconW(hInst, MAKEINTRESOURCEW(IDI_APPICON));
    wc.hIconSm       = (HICON)LoadImageW(hInst, MAKEINTRESOURCEW(IDI_APPICON), IMAGE_ICON,
                                         GetSystemMetrics(SM_CXSMICON), GetSystemMetrics(SM_CYSMICON), 0);
    wc.lpszClassName = L"SnakeGameWindow";
    RegisterClassExW(&wc);

    DWORD style = WS_OVERLAPPED | WS_CAPTION | WS_SYSMENU | WS_MINIMIZEBOX;
    g_hwnd = CreateWindowExW(0, wc.lpszClassName, L"Snake", style,
                             CW_USEDEFAULT, CW_USEDEFAULT, 100, 100, NULL, NULL, hInst, NULL);
    if (!g_hwnd) return 1;

    g_dpi = windowDpi(g_hwnd);
    makeFonts();
    resizeForDpi(g_hwnd);

    /* centre on the primary monitor's work area */
    RECT wr, work;
    GetWindowRect(g_hwnd, &wr);
    SystemParametersInfoW(SPI_GETWORKAREA, 0, &work, 0);
    SetWindowPos(g_hwnd, NULL,
                 work.left + ((work.right - work.left) - (wr.right - wr.left)) / 2,
                 work.top + ((work.bottom - work.top) - (wr.bottom - wr.top)) / 2,
                 0, 0, SWP_NOSIZE | SWP_NOZORDER);

    ShowWindow(g_hwnd, show);
    UpdateWindow(g_hwnd);
    SetTimer(g_hwnd, TIMER_ID, FRAME_MS, NULL);

    MSG m;
    while (GetMessageW(&m, NULL, 0, 0) > 0) {
        TranslateMessage(&m);
        DispatchMessageW(&m);
    }
    return 0;
}
