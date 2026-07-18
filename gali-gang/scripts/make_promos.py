#!/usr/bin/env python3
"""Generate short cartoon promo MP4s for each Gali Gang episode."""

from __future__ import annotations

import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "site" / "assets"
OUT = ROOT / "site" / "videos"
ART = Path("/opt/cursor/artifacts/gali-gang/videos")

EPISODES = [
    ("ep01-jalebi-jam", "EP 01", "Jalebi Jam", "Meethas faili, gali chipak gayi!", ["chacha-jalebi.png", "bunty.png", "motu-mama.png", "pinky.png", "billu-billi.png"]),
    ("ep02-selfie-se-satyanash", "EP 02", "Selfie Se Satyanash", "Live challenge = full drama!", ["pinky.png", "inspector-bakra.png", "rocky-rickshaw.png", "begum-bubble.png"]),
    ("ep03-inspector-ki-investigation", "EP 03", "Inspector Ki Investigation", "Bat gayab, bakwas present!", ["inspector-bakra.png", "sassy-sana.png", "dj-dumroo.png", "billu-billi.png"]),
    ("ep04-nano-ka-rocket", "EP 04", "Nano Ka Rocket", "Rickshaw ready for takeoff!", ["nano.png", "rocky-rickshaw.png", "professor-pappu.png", "motu-mama.png"]),
    ("ep05-begum-bubble-ka-secret", "EP 05", "Begum Bubble Ka Secret", "Diary gayab, chai tension!", ["begum-bubble.png", "billu-billi.png", "motu-mama.png", "lalten-gali-hero.png"]),
    ("ep06-motu-mama-ka-langar", "EP 06", "Motu Mama Ka Langar", "Bhook emergency for everyone!", ["motu-mama.png", "chacha-jalebi.png", "professor-pappu.png", "dj-dumroo.png"]),
    ("ep07-dj-dumroo-night", "EP 07", "DJ Dumroo Night", "Beat drop, gali shake!", ["dj-dumroo.png", "bunty.png", "inspector-bakra.png", "lalten-gali-hero.png"]),
    ("ep08-professor-pappu-ka-clone", "EP 08", "Professor Pappu Ka Clone", "One Pappu, too many Pappus!", ["professor-pappu.png", "pinky.png", "nano.png", "bunty.png"]),
    ("ep09-rocky-vs-traffic", "EP 09", "Rocky vs Traffic", "Horn orchestra vs wedding!", ["rocky-rickshaw.png", "inspector-bakra.png", "begum-bubble.png", "lalten-gali-hero.png"]),
    ("ep10-lalten-gali-utsav", "EP 10", "Lalten Gali Utsav", "Season finale: full bakwas!", ["lalten-gali-hero.png", "billu-billi.png", "pinky.png", "dj-dumroo.png", "bunty.png"]),
]


def escape_drawtext(text: str) -> str:
    return (
        text.replace("\\", "\\\\")
        .replace(":", "\\:")
        .replace("'", "\\'")
        .replace("%", "\\%")
    )


def make_episode(slug: str, ep_label: str, title: str, tagline: str, images: list[str]) -> Path:
    OUT.mkdir(parents=True, exist_ok=True)
    ART.mkdir(parents=True, exist_ok=True)
    out = OUT / f"{slug}.mp4"

    img_paths = [ASSETS / name for name in images if (ASSETS / name).exists()]
    if not img_paths:
        raise FileNotFoundError(f"No images for {slug}")

    title_hold = 3.0
    end_hold = 3.0
    scene_hold = 3.5
    duration = title_hold + end_hold + scene_hold * len(img_paths)

    inputs: list[str] = [
        "-f", "lavfi", "-i", f"color=c=0x0B1D36:s=1280x720:d={title_hold}",
        "-f", "lavfi", "-i", f"color=c=0x0B1D36:s=1280x720:d={end_hold}",
    ]
    for p in img_paths:
        inputs += ["-loop", "1", "-t", str(scene_hold), "-i", str(p)]

    title_text = escape_drawtext(f"GALI GANG  |  {ep_label}")
    title_sub = escape_drawtext(title)
    title_tag = escape_drawtext(tagline)
    end_text = escape_drawtext("GALI GANG")
    end_sub = escape_drawtext("Full Bakwas  •  Urdu-Hindi Comedy")

    filters = [
        (
            f"[0:v]drawtext=text='{title_text}':fontcolor=0xFFB703:fontsize=40:"
            f"x=(w-text_w)/2:y=h/2-90,"
            f"drawtext=text='{title_sub}':fontcolor=white:fontsize=52:"
            f"x=(w-text_w)/2:y=h/2-10,"
            f"drawtext=text='{title_tag}':fontcolor=0x1A9B8A:fontsize=30:"
            f"x=(w-text_w)/2:y=h/2+60,"
            f"fade=t=in:st=0:d=0.4,fade=t=out:st={title_hold-0.5}:d=0.45[v0]"
        ),
        (
            f"[1:v]drawtext=text='{end_text}':fontcolor=0xFFB703:fontsize=60:"
            f"x=(w-text_w)/2:y=h/2-40,"
            f"drawtext=text='{end_sub}':fontcolor=white:fontsize=28:"
            f"x=(w-text_w)/2:y=h/2+40,"
            f"fade=t=in:st=0:d=0.4,fade=t=out:st={end_hold-0.5}:d=0.4[vend]"
        ),
    ]

    labels = ["[v0]"]
    for i in range(len(img_paths)):
        inp = i + 2
        lab = f"v{i+1}"
        filters.append(
            f"[{inp}:v]scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720,"
            f"zoompan=z='min(zoom+0.0012,1.1)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':"
            f"d=88:s=1280x720:fps=25,"
            f"fade=t=in:st=0:d=0.3,fade=t=out:st={scene_hold-0.4}:d=0.35[{lab}]"
        )
        labels.append(f"[{lab}]")
    labels.append("[vend]")

    audio_idx = 2 + len(img_paths)
    filters.append("".join(labels) + f"concat=n={len(labels)}:v=1:a=0[vout]")
    filters.append(
        f"[{audio_idx}:a]afade=t=in:st=0:d=0.5,"
        f"afade=t=out:st={max(0.5, duration - 0.8)}:d=0.7,volume=0.07[aout]"
    )

    cmd = [
        "ffmpeg", "-y",
        *inputs,
        "-f", "lavfi", "-i", f"sine=frequency=392:sample_rate=44100:duration={duration}",
        "-filter_complex", ";".join(filters),
        "-map", "[vout]", "-map", "[aout]",
        "-c:v", "libx264", "-pix_fmt", "yuv420p", "-r", "25",
        "-c:a", "aac", "-b:a", "96k",
        "-t", str(duration),
        str(out),
    ]

    print("Rendering", out.name, "...")
    proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if proc.returncode != 0:
        print(proc.stderr[-2000:])
        raise RuntimeError(f"ffmpeg failed for {slug}")
    art_out = ART / out.name
    art_out.write_bytes(out.read_bytes())
    print("OK", out.name, f"({out.stat().st_size // 1024} KB)")
    return out


def main() -> None:
    for row in EPISODES:
        make_episode(*row)
    print("All 10 promos done.")


if __name__ == "__main__":
    main()
