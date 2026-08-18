#!/usr/bin/env python3
"""Build a 60-second CCNA course advertisement with voice, music, and motion."""

from __future__ import annotations

import asyncio
import json
import math
import os
import shutil
import subprocess
import wave
from pathlib import Path

import edge_tts
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parent
SCENES = ROOT / "scenes"
AUDIO = ROOT / "audio"
BUILD = ROOT / "build"
OVERLAYS = BUILD / "overlays"
CLIPS = BUILD / "clips"
OUT = ROOT / "CCNA-Level-From-Beginner-to-Professional-60s.mp4"

W, H, FPS = 1920, 1080, 30
DURATION = 60.0
GOLD = (224, 160, 69, 255)
GOLD_SOFT = (240, 208, 142, 255)
WHITE = (247, 243, 234, 255)
TEAL = (13, 59, 69, 255)
MUTED = (214, 226, 228, 230)

FONT_BOLD = "/usr/share/fonts/truetype/macos/Inter-Bold.ttf"
FONT_SEMI = "/usr/share/fonts/truetype/macos/Inter-SemiBold.ttf"
FONT_MED = "/usr/share/fonts/truetype/macos/Inter-Medium.ttf"
FONT_MONO = "/usr/share/fonts/truetype/jetbrains-mono/JetBrainsMono-Bold.ttf"

SCENE_FILES = [
    SCENES / "scene01-hook.png",
    SCENES / "scene02-title.png",
    SCENES / "scene03-osi.png",
    SCENES / "scene04-skills.png",
    SCENES / "scene05-lab.png",
    SCENES / "scene06-career.png",
    SCENES / "scene07-sale.png",
    SCENES / "scene08-cta.png",
]


def font(path: str, size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(path, size)


def run(cmd: list[str]) -> None:
    print("+", " ".join(cmd[:8]), "..." if len(cmd) > 8 else "")
    subprocess.run(cmd, check=True)


def probe_duration(path: Path) -> float:
    out = subprocess.check_output(
        [
            "ffprobe",
            "-v",
            "error",
            "-show_entries",
            "format=duration",
            "-of",
            "default=nw=1:nk=1",
            str(path),
        ],
        text=True,
    ).strip()
    return float(out)


async def make_voice() -> tuple[Path, list[dict]]:
    AUDIO.mkdir(parents=True, exist_ok=True)
    text = (ROOT / "voiceover.txt").read_text().strip()
    voice = "en-US-AndrewMultilingualNeural"
    mp3 = AUDIO / "voiceover.mp3"
    sentences: list[dict] = []
    communicate = edge_tts.Communicate(text, voice, rate="-10%", pitch="-3Hz")
    with mp3.open("wb") as fh:
        async for chunk in communicate.stream():
            if chunk["type"] == "audio":
                fh.write(chunk["data"])
            elif chunk["type"] == "SentenceBoundary":
                sentences.append(
                    {
                        "text": chunk["text"],
                        "start": chunk["offset"] / 10_000_000,
                        "duration": chunk["duration"] / 10_000_000,
                    }
                )
    (AUDIO / "sentences.json").write_text(json.dumps(sentences, indent=2))
    print(f"voice {probe_duration(mp3):.2f}s sentences={len(sentences)}")
    return mp3, sentences


def make_music(path: Path, seconds: float) -> None:
    sr = 44100
    n = int(sr * seconds)
    t = np.arange(n) / sr
    # Low cinematic drone
    drone = (
        0.18 * np.sin(2 * np.pi * 55.0 * t)
        + 0.12 * np.sin(2 * np.pi * 82.5 * t)
        + 0.07 * np.sin(2 * np.pi * 110.0 * t)
    )
    # Soft shimmer
    shimmer = 0.035 * np.sin(2 * np.pi * 659.25 * t) * (0.5 + 0.5 * np.sin(2 * np.pi * 0.12 * t))
    # Gold pulse every 2 seconds
    pulse_env = np.exp(-((t % 2.0) / 0.28) ** 2)
    pulse = 0.06 * np.sin(2 * np.pi * 220.0 * t) * pulse_env
    # Final rise
    rise = 0.08 * np.sin(2 * np.pi * 330.0 * t) * np.clip((t - 50) / 10.0, 0, 1)
    mix = drone + shimmer + pulse + rise
    # Gentle fade in/out
    fade = np.ones_like(t)
    fade[: int(sr * 1.2)] = np.linspace(0, 1, int(sr * 1.2))
    fade[-int(sr * 1.6) :] = np.linspace(1, 0, int(sr * 1.6))
    mix *= fade * 0.55
    # Stereo
    left = mix
    right = mix * 0.92 + 0.03 * np.sin(2 * np.pi * 164.8 * t)
    stereo = np.stack([left, right], axis=1)
    peak = np.max(np.abs(stereo)) or 1.0
    pcm = np.int16(np.clip(stereo / peak * 0.85, -1, 1) * 32767)
    with wave.open(str(path), "w") as wf:
        wf.setnchannels(2)
        wf.setsampwidth(2)
        wf.setframerate(sr)
        wf.writeframes(pcm.tobytes())


def rounded_rect(draw: ImageDraw.ImageDraw, box, radius, fill):
    draw.rounded_rectangle(box, radius=radius, fill=fill)


def draw_text_shadow(draw, xy, text, font_, fill, shadow=(0, 0, 0, 180), offset=3):
    x, y = xy
    draw.text((x + offset, y + offset), text, font=font_, fill=shadow)
    draw.text((x, y), text, font=font_, fill=fill)


def measure(draw, text, font_):
    b = draw.textbbox((0, 0), text, font=font_)
    return b[2] - b[0], b[3] - b[1]


def make_overlay(path: Path, eyebrow: str, title: str, subtitle: str, chips: list[str] | None = None, cta: bool = False):
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Top and bottom cinematic bars
    for i in range(170):
        alpha = int(200 * (1 - i / 170))
        draw.rectangle((0, i, W, i + 1), fill=(6, 22, 28, alpha))
        draw.rectangle((0, H - 1 - i, W, H - i), fill=(6, 22, 28, int(230 * (1 - i / 170))))

    # Brand chip
    brand_font = font(FONT_SEMI, 28)
    brand = "GIGA CLASS MARKET"
    bw, bh = measure(draw, brand, brand_font)
    rounded_rect(draw, (56, 42, 56 + bw + 44, 42 + bh + 22), 10, (13, 59, 69, 210))
    draw.rectangle((56, 42, 62, 42 + bh + 22), fill=GOLD)
    draw.text((78, 50), brand, font=brand_font, fill=GOLD_SOFT)

    y = 690 if not cta else 430
    if eyebrow:
        eye_font = font(FONT_MONO, 26)
        draw_text_shadow(draw, (70, y), eyebrow.upper(), eye_font, GOLD)
        y += 46

    title_font = font(FONT_BOLD, 78 if not cta else 64)
    # Wrap title
    words = title.split()
    lines, cur = [], ""
    max_w = 1720
    for word in words:
        trial = (cur + " " + word).strip()
        tw, _ = measure(draw, trial, title_font)
        if tw > max_w and cur:
            lines.append(cur)
            cur = word
        else:
            cur = trial
    if cur:
        lines.append(cur)
    for line in lines:
        draw_text_shadow(draw, (70, y), line, title_font, WHITE, offset=4)
        y += 88 if not cta else 74

    if subtitle:
        sub_font = font(FONT_MED, 36)
        draw_text_shadow(draw, (70, y + 6), subtitle, sub_font, MUTED, offset=2)
        y += 58

    if chips:
        chip_font = font(FONT_SEMI, 26)
        x = 70
        cy = y + 18
        for chip in chips:
            cw, ch = measure(draw, chip, chip_font)
            box = (x, cy, x + cw + 36, cy + ch + 22)
            rounded_rect(draw, box, 22, (13, 59, 69, 205))
            draw.rounded_rectangle(box, radius=22, outline=GOLD, width=2)
            draw.text((x + 18, cy + 8), chip, font=chip_font, fill=GOLD_SOFT)
            x += cw + 50
            if x > 1650:
                x = 70
                cy += ch + 36

    if cta:
        btn_font = font(FONT_BOLD, 40)
        label = "BUY NOW"
        lw, lh = measure(draw, label, btn_font)
        bx, by = 70, 860
        rounded_rect(draw, (bx, by, bx + lw + 80, by + lh + 36), 18, GOLD)
        draw.text((bx + 40, by + 12), label, font=btn_font, fill=TEAL)
        url_font = font(FONT_MED, 28)
        draw.text(
            (bx + lw + 110, by + 18),
            "gigaclassmarket.com/courses/ccna-level-from-beginner-to-professional",
            font=url_font,
            fill=WHITE,
        )

    img = img.filter(ImageFilter.SMOOTH)
    img.save(path)


def prepare_overlays():
    OVERLAYS.mkdir(parents=True, exist_ok=True)
    specs = [
        ("01.png", "Networking", "Zero experience?", "Perfect. This is where professionals start.", ["Beginner friendly", "No prior networking needed"]),
        ("02.png", "Giga Class Market", "CCNA Level — From Beginner to Professional", "3 months  ·  Mr. Manzoor Ahmad  ·  ★ 4.8", ["Premium learning"]),
        ("03.png", "Fundamentals first", "See how networks really work", "OSI & TCP/IP models, packets, and communication", ["OSI", "TCP/IP", "Packets"]),
        ("04.png", "What you'll master", "From subnetting to troubleshooting", "Build CCNA-level skills, step by step", ["Subnetting", "VLANs", "OSPF", "ACLs", "IPv6"]),
        ("05.png", "Hands-on labs", "No expensive hardware", "Practice every lab in Packet Tracer", ["Real topologies", "Switch & router labs"]),
        ("06.png", "Who it's for", "Beginners to future engineers", "Start at zero. Finish professional.", ["Students", "IT learners", "Aspiring engineers"]),
        ("07.png", "Azadi Sale", "PKR 1,500", "Was PKR 20,000  ·  3-month CCNA-level course", ["Limited Azadi Sale"]),
        ("08.png", "Enroll now", "Start as a beginner. Finish as a network professional.", "Giga Class Market  ·  Premium Learning", None),
    ]
    for name, eyebrow, title, subtitle, chips in specs:
        make_overlay(OVERLAYS / name, eyebrow, title, subtitle, chips, cta=(name == "08.png"))


def scene_durations(voice_dur: float) -> list[float]:
    # Hold CTA so the full video is exactly 60s.
    weights = [7.2, 8.0, 7.0, 8.4, 7.6, 6.6, 7.2, 8.0]
    s = sum(weights)
    durs = [w / s * min(voice_dur + 1.4, 58.5) for w in weights]
    durs[-1] += DURATION - sum(durs)
    return durs


def make_clip(idx: int, src: Path, overlay: Path, duration: float, zoom_dir: int) -> Path:
    CLIPS.mkdir(parents=True, exist_ok=True)
    frames = max(int(duration * FPS), 2)
    out = CLIPS / f"clip{idx:02d}.mp4"
    # Ken Burns: start slightly zoomed, pan opposite directions per scene
    z_end = 1.12 if zoom_dir > 0 else 1.08
    z_expr = f"1+({z_end}-1)*on/{frames}"
    x_expr = "iw/2-(iw/zoom/2)+0.12*on" if zoom_dir > 0 else "iw/2-(iw/zoom/2)-0.10*on"
    y_expr = "ih/2-(ih/zoom/2)-0.06*on" if zoom_dir > 0 else "ih/2-(ih/zoom/2)+0.05*on"
    fade = min(0.45, duration / 5)
    vf = (
        f"scale=2200:1238:force_original_aspect_ratio=increase,"
        f"crop=2200:1238,"
        f"zoompan=z='{z_expr}':x='{x_expr}':y='{y_expr}':d={frames}:s={W}x{H}:fps={FPS},"
        f"format=rgba[v];"
        f"[1:v]scale={W}:{H},format=rgba[ov];"
        f"[v][ov]overlay=0:0:format=auto,format=yuv420p,"
        f"fade=t=in:st=0:d={fade},fade=t=out:st={max(duration-fade,0):.3f}:d={fade}"
    )
    run(
        [
            "ffmpeg",
            "-y",
            "-loop",
            "1",
            "-i",
            str(src),
            "-i",
            str(overlay),
            "-filter_complex",
            vf,
            "-t",
            f"{duration:.3f}",
            "-r",
            str(FPS),
            "-an",
            "-c:v",
            "libx264",
            "-pix_fmt",
            "yuv420p",
            "-preset",
            "medium",
            "-crf",
            "18",
            str(out),
        ]
    )
    return out


def concat_clips(clips: list[Path], path: Path) -> None:
    lst = BUILD / "concat.txt"
    lst.write_text("".join(f"file '{c}'\n" for c in clips))
    run(
        [
            "ffmpeg",
            "-y",
            "-f",
            "concat",
            "-safe",
            "0",
            "-i",
            str(lst),
            "-c:v",
            "libx264",
            "-pix_fmt",
            "yuv420p",
            "-r",
            str(FPS),
            "-an",
            str(path),
        ]
    )


def mix_audio(video: Path, voice: Path, music: Path, out: Path, voice_dur: float) -> None:
    # Pad/trim to exactly 60s, duck music under voice, add light limiter.
    pad = max(DURATION - voice_dur, 0.15)
    filter_complex = (
        f"[1:a]aformat=sample_fmts=fltp:sample_rates=44100:channel_layouts=stereo,"
        f"adelay=200|200,volume=2.1,apad=pad_dur={pad:.3f},atrim=0:{DURATION},asetpts=PTS-STARTPTS,asplit=2[vo][vo2];"
        f"[2:a]aformat=sample_fmts=fltp:sample_rates=44100:channel_layouts=stereo,"
        f"volume=0.18,atrim=0:{DURATION},asetpts=PTS-STARTPTS[mus];"
        f"[mus][vo]sidechaincompress=threshold=0.04:ratio=7:attack=15:release=350:makeup=2[ducked];"
        f"[vo2][ducked]amix=inputs=2:duration=first:dropout_transition=0:normalize=0:weights=1 0.85,"
        f"alimiter=limit=0.94,loudnorm=I=-14:TP=-1.5:LRA=11,atrim=0:{DURATION}[a]"
    )
    run(
        [
            "ffmpeg",
            "-y",
            "-i",
            str(video),
            "-i",
            str(voice),
            "-i",
            str(music),
            "-filter_complex",
            filter_complex,
            "-map",
            "0:v",
            "-map",
            "[a]",
            "-c:v",
            "libx264",
            "-pix_fmt",
            "yuv420p",
            "-preset",
            "medium",
            "-crf",
            "17",
            "-c:a",
            "aac",
            "-b:a",
            "192k",
            "-shortest",
            "-movflags",
            "+faststart",
            "-t",
            str(DURATION),
            str(out),
        ]
    )


def add_progress(src: Path, dst: Path) -> None:
    # Gold progress bar + safe end hold
    vf = (
        f"drawbox=x=0:y=ih-8:w=iw:h=8:color=0x0D3B45@0.85:t=fill,"
        f"drawbox=x=0:y=ih-8:w='iw*t/{DURATION}':h=8:color=0xE0A045@1:t=fill"
    )
    run(
        [
            "ffmpeg",
            "-y",
            "-i",
            str(src),
            "-vf",
            vf,
            "-c:v",
            "libx264",
            "-pix_fmt",
            "yuv420p",
            "-preset",
            "medium",
            "-crf",
            "17",
            "-c:a",
            "copy",
            "-movflags",
            "+faststart",
            "-t",
            str(DURATION),
            str(dst),
        ]
    )


def main() -> None:
    if BUILD.exists():
        shutil.rmtree(BUILD)
    BUILD.mkdir(parents=True, exist_ok=True)
    AUDIO.mkdir(parents=True, exist_ok=True)

    voice, sentences = asyncio.run(make_voice())
    voice_dur = probe_duration(voice)
    music = AUDIO / "music.wav"
    make_music(music, DURATION + 1)
    prepare_overlays()

    durs = scene_durations(voice_dur)
    print("scene durations", [round(d, 2) for d in durs], "sum", sum(durs))
    overlays = sorted(OVERLAYS.glob("*.png"))
    clips = []
    for i, (src, ov, dur) in enumerate(zip(SCENE_FILES, overlays, durs), start=1):
        clips.append(make_clip(i, src, ov, dur, zoom_dir=1 if i % 2 else -1))

    silent = BUILD / "video_silent.mp4"
    mixed = BUILD / "video_mixed.mp4"
    concat_clips(clips, silent)
    mix_audio(silent, voice, music, mixed, voice_dur)
    add_progress(mixed, OUT)
    print("OUTPUT", OUT, "duration", probe_duration(OUT))


if __name__ == "__main__":
    main()
