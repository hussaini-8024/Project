#!/usr/bin/env python3
"""Generate a ~60s animated tutorial: How to Install Microsoft Office."""

from __future__ import annotations

import math
import os
import subprocess
import tempfile
from pathlib import Path

from gtts import gTTS
from PIL import Image, ImageDraw, ImageFont

# --- Config ---
WIDTH, HEIGHT = 1280, 720
FPS = 24
DURATION = 60.0
TOTAL_FRAMES = int(DURATION * FPS)

OUT_DIR = Path(os.environ.get("OUT_DIR", "/opt/cursor/artifacts"))
WORK_DIR = Path(os.environ.get("WORK_DIR", "/tmp/office-tutorial"))
VIDEO_PATH = OUT_DIR / "ms-office-install-tutorial.mp4"
REPO_VIDEO_PATH = Path("/workspace/tutorials/ms-office-install-tutorial.mp4")

FONT_REG = "/usr/share/fonts/truetype/noto/NotoSansDisplay-Regular.ttf"
FONT_BOLD = "/usr/share/fonts/truetype/noto/NotoSansDisplay-Bold.ttf"

# Visual direction: deep teal / paper / coral accent (not purple AI defaults)
BG_TOP = (12, 42, 48)
BG_BOT = (232, 241, 238)
INK = (18, 32, 36)
MUTED = (70, 92, 98)
ACCENT = (0, 120, 140)
ACCENT_SOFT = (180, 220, 215)
PAPER = (248, 250, 249)
WHITE = (255, 255, 255)
WORD = (43, 87, 154)
EXCEL = (33, 115, 70)
PPT = (183, 71, 42)
OUTLOOK = (0, 114, 198)


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    path = FONT_BOLD if bold else FONT_REG
    return ImageFont.truetype(path, size)


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def ease_out_cubic(t: float) -> float:
    t = max(0.0, min(1.0, t))
    return 1 - (1 - t) ** 3


def ease_in_out(t: float) -> float:
    t = max(0.0, min(1.0, t))
    return 3 * t * t - 2 * t * t * t


def mix(c1, c2, t: float):
    t = max(0.0, min(1.0, t))
    return tuple(int(lerp(a, b, t)) for a, b in zip(c1, c2))


def vertical_gradient(draw: ImageDraw.ImageDraw, top, bot):
    for y in range(HEIGHT):
        t = y / (HEIGHT - 1)
        draw.line([(0, y), (WIDTH, y)], fill=mix(top, bot, t))


def rounded_rect(draw, xy, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline, width=width)


def text_size(draw, text, fnt):
    bbox = draw.textbbox((0, 0), text, font=fnt)
    return bbox[2] - bbox[0], bbox[3] - bbox[1]


def draw_centered_text(draw, y, text, fnt, fill, max_width=None):
    w, h = text_size(draw, text, fnt)
    x = (WIDTH - w) // 2
    draw.text((x, y), text, font=fnt, fill=fill)
    return h


def alpha_composite_rgba(base: Image.Image, overlay: Image.Image, xy=(0, 0)):
    layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
    layer.paste(overlay, xy, overlay)
    return Image.alpha_composite(base.convert("RGBA"), layer)


def scene_progress(t: float, start: float, end: float) -> float:
    if t <= start:
        return 0.0
    if t >= end:
        return 1.0
    return (t - start) / (end - start)


# Scene timing (seconds)
SCENES = [
    ("intro", 0.0, 6.0),
    ("step1", 6.0, 14.0),
    ("step2", 14.0, 22.0),
    ("step3", 22.0, 31.0),
    ("step4", 31.0, 40.0),
    ("step5", 40.0, 49.0),
    ("step6", 49.0, 56.0),
    ("outro", 56.0, 60.0),
]

NARRATION = {
    "intro": "Welcome. In this one-minute tutorial, you'll learn how to install Microsoft Office on your computer.",
    "step1": "Step one. Open your web browser and go to office.com.",
    "step2": "Step two. Sign in with your Microsoft account email and password.",
    "step3": "Step three. Click Install apps, then choose Microsoft Three Sixty five apps.",
    "step4": "Step four. Open the downloaded Setup file and allow it to run.",
    "step5": "Step five. Wait while Office downloads and installs. Keep your internet connected.",
    "step6": "Step six. Open Word or Excel and sign in again to activate your apps.",
    "outro": "That's it. Microsoft Office is ready. Thanks for watching.",
}


def make_narration(work: Path) -> Path:
    audio_dir = work / "audio"
    audio_dir.mkdir(parents=True, exist_ok=True)
    parts = []
    # Build one continuous narration with scene-aligned silence via ffmpeg later
    # Generate per-scene clips first
    for name, start, end in SCENES:
        text = NARRATION[name]
        mp3 = audio_dir / f"{name}.mp3"
        if not mp3.exists():
            gTTS(text=text, lang="en").save(str(mp3))
        parts.append((name, start, end, mp3))

    # Create a silent base and overlay each clip at scene start
    concat_list = audio_dir / "mix.txt"
    # Convert each to wav with known duration padding
    wavs = []
    for name, start, end, mp3 in parts:
        wav = audio_dir / f"{name}.wav"
        scene_dur = end - start
        # Generate speech, then pad/truncate to scene duration
        cmd = [
            "ffmpeg", "-y", "-i", str(mp3),
            "-af", f"apad=pad_dur={scene_dur},atrim=0:{scene_dur}",
            "-ar", "44100", "-ac", "1", str(wav),
        ]
        subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        wavs.append(wav)

    # Concatenate scene audio in order
    with concat_list.open("w") as f:
        for wav in wavs:
            f.write(f"file '{wav}'\n")

    full_wav = audio_dir / "narration.wav"
    subprocess.run(
        [
            "ffmpeg", "-y", "-f", "concat", "-safe", "0",
            "-i", str(concat_list), "-c", "copy", str(full_wav),
        ],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    return full_wav


def draw_step_badge(draw, step_num: int, alpha_t: float):
    r = int(28 + 4 * ease_out_cubic(alpha_t))
    cx, cy = 96, 96
    color = mix(ACCENT_SOFT, ACCENT, ease_out_cubic(alpha_t))
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=color)
    fnt = font(28, bold=True)
    label = str(step_num)
    w, h = text_size(draw, label, fnt)
    draw.text((cx - w // 2, cy - h // 2 - 2), label, font=fnt, fill=WHITE)


def draw_browser_chrome(draw, url: str, y_off: float = 0):
    x0, y0 = 160, 140 + int(y_off)
    x1, y1 = WIDTH - 160, HEIGHT - 90
    rounded_rect(draw, [x0, y0, x1, y1], 18, PAPER, outline=(210, 220, 218), width=2)
    # title bar
    rounded_rect(draw, [x0, y0, x1, y0 + 48], 18, (236, 242, 240))
    draw.rectangle([x0, y0 + 30, x1, y0 + 48], fill=(236, 242, 240))
    for i, c in enumerate([(255, 95, 86), (255, 189, 46), (39, 201, 63)]):
        draw.ellipse([x0 + 18 + i * 22, y0 + 16, x0 + 32 + i * 22, y0 + 30], fill=c)
    # address bar
    ax0, ay0 = x0 + 100, y0 + 12
    ax1, ay1 = x1 - 24, y0 + 36
    rounded_rect(draw, [ax0, ay0, ax1, ay1], 10, WHITE, outline=(200, 210, 208), width=1)
    draw.text((ax0 + 12, ay0 + 3), url, font=font(14), fill=MUTED)
    return x0, y0 + 48, x1, y1


def draw_office_icons(draw, cx, cy, scale=1.0, glow=0.0):
    apps = [
        ("W", WORD, -90),
        ("X", EXCEL, -30),
        ("P", PPT, 30),
        ("O", OUTLOOK, 90),
    ]
    size = int(54 * scale)
    for letter, color, dx in apps:
        x = cx + int(dx * scale)
        y = cy
        if glow > 0:
            g = int(20 * glow)
            draw.ellipse([x - size // 2 - g, y - size // 2 - g, x + size // 2 + g, y + size // 2 + g], fill=mix(color, WHITE, 0.7))
        rounded_rect(
            draw,
            [x - size // 2, y - size // 2, x + size // 2, y + size // 2],
            10,
            color,
        )
        fnt = font(int(26 * scale), bold=True)
        w, h = text_size(draw, letter, fnt)
        draw.text((x - w // 2, y - h // 2 - 1), letter, font=fnt, fill=WHITE)


def render_intro(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, BG_TOP, (28, 78, 82))

    # subtle moving rings
    for i in range(4):
        r = int(80 + i * 70 + 20 * math.sin(t_local * 2 + i))
        alpha = 40 + i * 10
        color = (ACCENT[0], ACCENT[1], ACCENT[2])
        # approximate soft rings
        bbox = [WIDTH // 2 - r, HEIGHT // 2 - r + 40, WIDTH // 2 + r, HEIGHT // 2 + r + 40]
        draw.ellipse(bbox, outline=mix(BG_TOP, ACCENT_SOFT, 0.35), width=2)

    appear = ease_out_cubic(min(1.0, t_local / 1.2))
    y_shift = int((1 - appear) * 40)

    draw_office_icons(draw, WIDTH // 2, 210 + y_shift, scale=1.05 + 0.05 * math.sin(t_local * 2), glow=0.5)

    title = "How to Install Microsoft Office"
    subtitle = "A clear 1-minute walkthrough"
    title_y = 300 + y_shift
    draw_centered_text(draw, title_y, title, font(42, bold=True), mix(BG_TOP, WHITE, appear))
    draw_centered_text(draw, title_y + 60, subtitle, font(22), mix(BG_TOP, ACCENT_SOFT, appear))

    # bottom progress hint
    bar_w = int(280 * ease_in_out(min(1.0, t_local / duration)))
    bx = (WIDTH - 280) // 2
    by = HEIGHT - 70
    rounded_rect(draw, [bx, by, bx + 280, by + 8], 4, (40, 70, 74))
    rounded_rect(draw, [bx, by, bx + max(8, bar_w), by + 8], 4, ACCENT_SOFT)
    return img


def render_step1(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, (236, 245, 243), (210, 230, 226))
    draw_step_badge(draw, 1, min(1.0, t_local / 0.6))

    draw.text((150, 72), "Open office.com", font=font(34, bold=True), fill=INK)
    draw.text((150, 118), "Launch your browser and visit the Office website.", font=font(18), fill=MUTED)

    y_off = (1 - ease_out_cubic(min(1.0, t_local / 0.8))) * 30
    x0, y0, x1, y1 = draw_browser_chrome(draw, "https://www.office.com", y_off=y_off)

    # page content
    draw.text((x0 + 40, y0 + 36), "Microsoft 365", font=font(28, bold=True), fill=INK)
    draw.text((x0 + 40, y0 + 80), "Your productivity apps in one place", font=font(16), fill=MUTED)
    draw_office_icons(draw, (x0 + x1) // 2, y0 + 200, scale=1.0)

    # typing cursor animation in address bar already static; animate a CTA
    cta_t = ease_out_cubic(scene_progress(t_local, 1.5, 2.5))
    cta = mix(PAPER, ACCENT, cta_t)
    rounded_rect(draw, [x0 + 40, y0 + 300, x0 + 220, y0 + 348], 10, cta)
    draw.text((x0 + 68, y0 + 312), "Get started", font=font(16, bold=True), fill=WHITE if cta_t > 0.4 else INK)

    # cursor click
    if t_local > 2.8:
        cx, cy = x0 + 130, y0 + 324
        pulse = 0.5 + 0.5 * math.sin((t_local - 2.8) * 6)
        r = int(10 + 6 * pulse)
        draw.ellipse([cx - r, cy - r, cx + r, cy + r], outline=ACCENT, width=2)
    return img


def render_step2(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, (236, 245, 243), (210, 230, 226))
    draw_step_badge(draw, 2, min(1.0, t_local / 0.6))
    draw.text((150, 72), "Sign in to your Microsoft account", font=font(32, bold=True), fill=INK)
    draw.text((150, 118), "Use the email and password for your Office subscription.", font=font(18), fill=MUTED)

    # sign-in card (interaction container — card allowed)
    cx = WIDTH // 2
    card_w, card_h = 420, 360
    x0 = cx - card_w // 2
    y0 = 170 + int((1 - ease_out_cubic(min(1, t_local / 0.7))) * 40)
    rounded_rect(draw, [x0, y0, x0 + card_w, y0 + card_h], 16, PAPER, outline=(200, 214, 210), width=1)
    draw.text((x0 + 36, y0 + 28), "Sign in", font=font(26, bold=True), fill=INK)
    draw.text((x0 + 36, y0 + 68), "Microsoft account", font=font(14), fill=MUTED)

    # email field with typing
    email_full = "you@example.com"
    typed = int(len(email_full) * ease_in_out(scene_progress(t_local, 1.0, 3.2)))
    email = email_full[:typed]
    rounded_rect(draw, [x0 + 36, y0 + 110, x0 + card_w - 36, y0 + 158], 8, WHITE, outline=(190, 205, 200), width=1)
    draw.text((x0 + 50, y0 + 124), email or "Email", font=font(16), fill=INK if email else (160, 170, 168))

    # password field
    pwd_t = scene_progress(t_local, 3.4, 5.0)
    dots = "•" * int(10 * ease_in_out(pwd_t))
    rounded_rect(draw, [x0 + 36, y0 + 178, x0 + card_w - 36, y0 + 226], 8, WHITE, outline=(190, 205, 200), width=1)
    draw.text((x0 + 50, y0 + 192), dots or "Password", font=font(16), fill=INK if dots else (160, 170, 168))

    btn_t = ease_out_cubic(scene_progress(t_local, 5.2, 6.0))
    rounded_rect(draw, [x0 + 36, y0 + 260, x0 + card_w - 36, y0 + 312], 8, mix((180, 200, 198), ACCENT, btn_t))
    draw.text((x0 + card_w // 2 - 28, y0 + 274), "Next", font=font(18, bold=True), fill=WHITE)
    return img


def render_step3(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, (236, 245, 243), (210, 230, 226))
    draw_step_badge(draw, 3, min(1.0, t_local / 0.6))
    draw.text((150, 72), "Download Microsoft 365 apps", font=font(32, bold=True), fill=INK)
    draw.text((150, 118), "Click Install apps, then choose Microsoft 365 apps.", font=font(18), fill=MUTED)

    x0, y0, x1, y1 = draw_browser_chrome(draw, "https://www.office.com", y_off=(1 - ease_out_cubic(min(1, t_local / 0.6))) * 20)
    draw.text((x0 + 40, y0 + 30), "Hi there — ready to install?", font=font(22, bold=True), fill=INK)

    # Install button
    highlight = 0.5 + 0.5 * math.sin(t_local * 4) if t_local > 1.5 else 0.2
    btn_color = mix(ACCENT, (0, 150, 165), highlight)
    rounded_rect(draw, [x0 + 40, y0 + 90, x0 + 250, y0 + 142], 10, btn_color)
    draw.text((x0 + 78, y0 + 104), "Install apps", font=font(18, bold=True), fill=WHITE)

    # dropdown appearing
    drop_t = ease_out_cubic(scene_progress(t_local, 2.5, 3.5))
    if drop_t > 0:
        dh = int(120 * drop_t)
        rounded_rect(draw, [x0 + 40, y0 + 150, x0 + 320, y0 + 150 + dh], 10, WHITE, outline=(200, 214, 210), width=1)
        if drop_t > 0.5:
            draw.text((x0 + 58, y0 + 168), "Microsoft 365 apps", font=font(16, bold=True), fill=INK)
            draw.text((x0 + 58, y0 + 200), "Office for the web", font=font(16), fill=MUTED)
            # selection highlight
            sel = ease_out_cubic(scene_progress(t_local, 4.0, 5.0))
            if sel > 0:
                rounded_rect(draw, [x0 + 48, y0 + 160, x0 + 312, y0 + 196], 6, mix(WHITE, ACCENT_SOFT, sel * 0.7))
                draw.text((x0 + 58, y0 + 168), "Microsoft 365 apps", font=font(16, bold=True), fill=INK)

    # download arrow
    if t_local > 5.5:
        ay = y1 - 80 + int(8 * math.sin(t_local * 8))
        draw.polygon([(WIDTH // 2, ay + 40), (WIDTH // 2 - 20, ay), (WIDTH // 2 + 20, ay)], fill=ACCENT)
        draw.text((WIDTH // 2 - 70, ay + 50), "Downloading…", font=font(16, bold=True), fill=ACCENT)
    return img


def render_step4(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, (236, 245, 243), (210, 230, 226))
    draw_step_badge(draw, 4, min(1.0, t_local / 0.6))
    draw.text((150, 72), "Run the Setup installer", font=font(32, bold=True), fill=INK)
    draw.text((150, 118), "Open the file from your Downloads folder and allow it to run.", font=font(18), fill=MUTED)

    # desktop mock
    desk_y = 170
    rounded_rect(draw, [120, desk_y, WIDTH - 120, HEIGHT - 70], 16, (40, 62, 66))

    # file icon
    fx, fy = 220, 260
    bounce = int(abs(math.sin(min(t_local, 2) * math.pi)) * 12) if t_local < 2 else 0
    rounded_rect(draw, [fx, fy - bounce, fx + 90, fy + 110 - bounce], 10, PAPER)
    draw.rectangle([fx + 10, fy + 20 - bounce, fx + 80, fy + 70 - bounce], fill=ACCENT)
    draw.text((fx + 18, fy + 78 - bounce), "Setup", font=font(14, bold=True), fill=INK)

    # UAC / permission dialog
    dlg_t = ease_out_cubic(scene_progress(t_local, 2.2, 3.2))
    if dlg_t > 0:
        dw, dh = 460, 240
        dx = (WIDTH - dw) // 2
        dy = 250 + int((1 - dlg_t) * 30)
        rounded_rect(draw, [dx, dy, dx + dw, dy + dh], 14, PAPER, outline=(190, 205, 200), width=1)
        draw.text((dx + 28, dy + 24), "Do you want to allow this app?", font=font(20, bold=True), fill=INK)
        draw.text((dx + 28, dy + 64), "Publisher: Microsoft Corporation", font=font(15), fill=MUTED)
        draw.text((dx + 28, dy + 94), "File: OfficeSetup.exe", font=font(15), fill=MUTED)

        yes_t = ease_out_cubic(scene_progress(t_local, 4.5, 5.5))
        rounded_rect(draw, [dx + 28, dy + 150, dx + 160, dy + 198], 8, mix((200, 210, 208), ACCENT, yes_t))
        draw.text((dx + 70, dy + 164), "Yes", font=font(16, bold=True), fill=WHITE if yes_t > 0.3 else INK)
        rounded_rect(draw, [dx + 180, dy + 150, dx + 312, dy + 198], 8, (230, 236, 234))
        draw.text((dx + 222, dy + 164), "No", font=font(16, bold=True), fill=MUTED)
    return img


def render_step5(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, (236, 245, 243), (210, 230, 226))
    draw_step_badge(draw, 5, min(1.0, t_local / 0.6))
    draw.text((150, 72), "Wait for Office to install", font=font(32, bold=True), fill=INK)
    draw.text((150, 118), "Stay online — the installer downloads and sets up your apps.", font=font(18), fill=MUTED)

    # installer window
    x0, y0 = 260, 200
    x1, y1 = WIDTH - 260, HEIGHT - 100
    rounded_rect(draw, [x0, y0, x1, y1], 16, PAPER, outline=(200, 214, 210), width=1)
    draw_office_icons(draw, (x0 + x1) // 2, y0 + 90, scale=0.85, glow=0.3 + 0.2 * math.sin(t_local * 3))

    draw.text(((x0 + x1) // 2 - 120, y0 + 150), "Installing Office…", font=font(22, bold=True), fill=INK)

    progress = ease_in_out(scene_progress(t_local, 0.5, duration - 0.5))
    bx0, by0 = x0 + 60, y0 + 210
    bx1, by1 = x1 - 60, y0 + 230
    rounded_rect(draw, [bx0, by0, bx1, by1], 8, (220, 230, 228))
    fill_x = int(lerp(bx0, bx1, progress))
    if fill_x > bx0 + 8:
        rounded_rect(draw, [bx0, by0, fill_x, by1], 8, ACCENT)

    pct = int(progress * 100)
    draw.text(((x0 + x1) // 2 - 20, y0 + 250), f"{pct}%", font=font(18, bold=True), fill=ACCENT)
    tip = "Tip: Leave this window open until it finishes."
    draw.text(((x0 + x1) // 2 - 180, y0 + 290), tip, font=font(15), fill=MUTED)
    return img


def render_step6(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, (236, 245, 243), (210, 230, 226))
    draw_step_badge(draw, 6, min(1.0, t_local / 0.6))
    draw.text((150, 72), "Activate by signing in", font=font(32, bold=True), fill=INK)
    draw.text((150, 118), "Open Word or Excel and sign in to unlock your license.", font=font(18), fill=MUTED)

    # app window Word-like
    x0, y0 = 200, 180
    x1, y1 = WIDTH - 200, HEIGHT - 80
    rounded_rect(draw, [x0, y0, x1, y1], 14, PAPER, outline=(200, 214, 210), width=1)
    # ribbon
    draw.rectangle([x0, y0, x1, y0 + 56], fill=WORD)
    draw.text((x0 + 24, y0 + 14), "Word", font=font(22, bold=True), fill=WHITE)

    # activation panel
    panel_t = ease_out_cubic(min(1.0, t_local / 0.8))
    px = x0 + 40
    py = y0 + 90 + int((1 - panel_t) * 20)
    rounded_rect(draw, [px, py, x1 - 40, py + 220], 12, (245, 248, 247), outline=(210, 220, 218), width=1)
    draw.text((px + 28, py + 24), "Sign in to activate Office", font=font(22, bold=True), fill=INK)
    draw.text((px + 28, py + 64), "Use the same Microsoft account from earlier.", font=font(15), fill=MUTED)

    btn_t = ease_out_cubic(scene_progress(t_local, 2.0, 3.2))
    rounded_rect(draw, [px + 28, py + 120, px + 220, py + 168], 8, mix((180, 200, 198), WORD, btn_t))
    draw.text((px + 70, py + 134), "Sign in", font=font(17, bold=True), fill=WHITE)

    if t_local > 4.0:
        ok_t = ease_out_cubic(scene_progress(t_local, 4.0, 5.0))
        check = mix((245, 248, 247), (33, 115, 70), ok_t)
        draw.ellipse([x1 - 120, py + 120, x1 - 70, py + 170], fill=check)
        draw.text((x1 - 108, py + 132), "✓", font=font(22, bold=True), fill=WHITE)
        draw.text((px + 250, py + 134), "Activated", font=font(17, bold=True), fill=mix(MUTED, (33, 115, 70), ok_t))
    return img


def render_outro(t_local: float, duration: float) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(img)
    vertical_gradient(draw, BG_TOP, (28, 78, 82))

    appear = ease_out_cubic(min(1.0, t_local / 0.9))
    y = int((1 - appear) * 30)
    draw_office_icons(draw, WIDTH // 2, 220 + y, scale=1.1, glow=0.6)
    draw_centered_text(draw, 310 + y, "You're all set!", font(44, bold=True), mix(BG_TOP, WHITE, appear))
    draw_centered_text(
        draw,
        375 + y,
        "Word, Excel, PowerPoint & more are ready to use.",
        font(20),
        mix(BG_TOP, ACCENT_SOFT, appear),
    )
    draw_centered_text(draw, 480 + y, "Thanks for watching", font(18), mix(BG_TOP, (160, 190, 188), appear))
    return img


RENDERERS = {
    "intro": render_intro,
    "step1": render_step1,
    "step2": render_step2,
    "step3": render_step3,
    "step4": render_step4,
    "step5": render_step5,
    "step6": render_step6,
    "outro": render_outro,
}


def scene_at(t: float):
    for name, start, end in SCENES:
        if start <= t < end or (name == SCENES[-1][0] and t >= start):
            return name, start, end
    return SCENES[-1]


def render_frame(t: float) -> Image.Image:
    name, start, end = scene_at(t)
    local = t - start
    duration = end - start
    frame = RENDERERS[name](local, duration)

    # crossfade near scene boundaries
    fade = 0.35
    draw = None
    if local < fade and name != "intro":
        # blend with previous scene end
        prev_idx = [s[0] for s in SCENES].index(name) - 1
        if prev_idx >= 0:
            pname, pstart, pend = SCENES[prev_idx]
            prev = RENDERERS[pname](pend - pstart - 0.001, pend - pstart)
            alpha = local / fade
            frame = Image.blend(prev, frame, alpha)
    remaining = duration - local
    if remaining < fade and name != "outro":
        pass  # next scene handles blend from its side

    # global top progress bar
    img = frame.convert("RGB")
    draw = ImageDraw.Draw(img)
    prog = t / DURATION
    draw.rectangle([0, 0, WIDTH, 4], fill=(30, 50, 54))
    draw.rectangle([0, 0, int(WIDTH * prog), 4], fill=ACCENT_SOFT)
    return img


def encode_video(frames_dir: Path, audio_path: Path, out_path: Path):
    out_path.parent.mkdir(parents=True, exist_ok=True)
    cmd = [
        "ffmpeg", "-y",
        "-framerate", str(FPS),
        "-i", str(frames_dir / "frame_%05d.png"),
        "-i", str(audio_path),
        "-c:v", "libx264",
        "-pix_fmt", "yuv420p",
        "-c:a", "aac",
        "-b:a", "128k",
        "-shortest",
        "-movflags", "+faststart",
        str(out_path),
    ]
    subprocess.run(cmd, check=True)


def main():
    WORK_DIR.mkdir(parents=True, exist_ok=True)
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    frames_dir = WORK_DIR / "frames"
    frames_dir.mkdir(parents=True, exist_ok=True)

    print("Generating narration…")
    audio_path = make_narration(WORK_DIR)
    print(f"Narration ready: {audio_path}")

    skip_existing = os.environ.get("SKIP_EXISTING_FRAMES", "0") == "1"
    print(f"Rendering {TOTAL_FRAMES} frames ({DURATION}s @ {FPS}fps)…")
    for i in range(TOTAL_FRAMES):
        out_frame = frames_dir / f"frame_{i:05d}.png"
        if skip_existing and out_frame.exists():
            continue
        t = i / FPS
        frame = render_frame(t)
        frame.save(out_frame, optimize=True)
        if i % 48 == 0:
            print(f"  frame {i}/{TOTAL_FRAMES} ({t:.1f}s)")

    print("Encoding video…")
    encode_video(frames_dir, audio_path, VIDEO_PATH)
    # also copy into repo tutorials folder
    encode_video(frames_dir, audio_path, REPO_VIDEO_PATH)

    size_mb = VIDEO_PATH.stat().st_size / (1024 * 1024)
    print(f"Done: {VIDEO_PATH} ({size_mb:.1f} MB)")
    print(f"Also saved: {REPO_VIDEO_PATH}")


if __name__ == "__main__":
    main()
