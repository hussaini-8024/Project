#!/usr/bin/env python3
"""
Build long-form storyboard animatics (~20 minutes) for each episode.

Still-frame timing reels with Urdu-Hindi dialogue cards — not full animation.
"""

from __future__ import annotations

import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "site" / "assets"
OUT = ROOT / "site" / "videos" / "long"
ART = Path("/opt/cursor/artifacts/gali-gang/videos/long")


def cards_for(ep: int) -> list[tuple[str, str, str]]:
    """Return (image, title, line) beats; duration assigned later."""
    intros = [
        ("billu-billi.png", "Billu Billi", "Assalamualaikum / Namaste. Main Billu Billi - aapki bakwas guide."),
        ("lalten-gali-hero.png", "Lalten Gali", "Yahan har plan seedha drama ban jata hai."),
    ]
    outros = [
        ("billu-billi.png", "Billu", "Hasaye to share karo. Nahi hasaye to bhi share karo - Begum dekh rahi hai."),
        ("lalten-gali-hero.png", "GALI GANG", "Full Bakwas - Episode complete. Season 1 rocking."),
    ]
    bodies = {
        1: [
            ("chacha-jalebi.png", "Chacha", "Pehle jalebi khao phir socho. Aaj history banegi."),
            ("bunty.png", "Bunty", "History baad mein. 5 minute aur so ne do."),
            ("motu-mama.png", "Motu Mama", "Bhook lagi hai - emergency accept."),
            ("pinky.png", "Pinky", "Wait wait - content ban raha hai. Hashtag JalebiJam."),
            ("sassy-sana.png", "Sana", "Logic suno - machine overheat. Drama mat karo."),
            ("inspector-bakra.png", "Inspector Bakra", "Suspect number one - jalebi. Case almost open."),
            ("professor-pappu.png", "Pappu", "Anti-sticky spray. Science hai yeh - thodi si pagal."),
            ("nano.png", "Nano", "Unstick-o-matic ready - thoda explode ho sakta hai."),
            ("rocky-rickshaw.png", "Rocky", "Rickshaw bol raha hai - chal pad phir chipak pad."),
            ("dj-dumroo.png", "DJ Dumroo", "Beat drop - syrup shake."),
            ("begum-bubble.png", "Begum Bubble", "Mujhe sab pata hai chai ke saath - gold jalebi rumour."),
            ("chacha-jalebi.png", "Act 2", "Sticky storm. Gali wallpaper ban gayi."),
            ("bunty.png", "Twist", "Bunty ne so te so te cool switch dhoond liya."),
            ("lalten-gali-hero.png", "Ending", "Meethas share hui dosti soft chipak gayi."),
        ],
        2: [
            ("pinky.png", "Pinky", "24-hour live challenge. No phone no life - teen phones se."),
            ("inspector-bakra.png", "Bakra", "Filter ne mujhe bakra bana diya. Supernatural case."),
            ("rocky-rickshaw.png", "Rocky", "Ugly filter mein drive nahi. Horn of dignity."),
            ("begum-bubble.png", "Begum", "Rumour app offline. Yeh toh blackout hai."),
            ("professor-pappu.png", "Pappu", "Signal booster balloon - ab sab ek group call mein."),
            ("bunty.png", "Meme Star", "Sleeping Superstar trend - Bunty ab bhi so raha hai."),
            ("sassy-sana.png", "Sana", "Digital detox posters - log unpe selfie le rahe hain."),
            ("lalten-gali-hero.png", "No Filter Mela", "Net gaya masti aayi. Likes temporary gali permanent."),
            ("pinky.png", "Pinky Soft", "Best moment offline kindness thi. Post later."),
            ("billu-billi.png", "Billu", "Caption strong. Battery weak."),
        ],
        3: [
            ("inspector-bakra.png", "Case", "Trophy bat gayab. Yeh bat nahi izzat hai."),
            ("chacha-jalebi.png", "Evidence", "Sticky fingerprints - delicious but useless."),
            ("dj-dumroo.png", "Arrest", "Dumroo weapon-shaped. Music is innocent."),
            ("motu-mama.png", "Suspect", "Main snacks borrow karta hoon bats nahi."),
            ("sassy-sana.png", "Sana", "Real evidence board. Koi padh kyun nahi raha."),
            ("nano.png", "Nano", "Fingerprint powder. Oops - Holi colour."),
            ("professor-pappu.png", "Serum", "Truth serum - actually lemonade. Secrets spilling."),
            ("begum-bubble.png", "Witness", "Maine shadow dekha - ya apna pallu."),
            ("billu-billi.png", "Twist", "Thank-you note mere bowl ke neeche thi."),
            ("lalten-gali-hero.png", "Match", "Neechi Gali ke saath friendly match. Case almost closed."),
        ],
        4: [
            ("nano.png", "Nano", "Rickshaw Rocket for science week."),
            ("rocky-rickshaw.png", "Rocky", "Mat chhed - okay thoda chhed."),
            ("professor-pappu.png", "Fuel", "Chili soda fuel - spicy thrust."),
            ("bunty.png", "Mission Control", "Main baitha reh sakta hoon. Sleeping counts."),
            ("motu-mama.png", "Launch", "Aachhoo. Button pressed. Oh no."),
            ("begum-bubble.png", "Parachute", "Meri sarees. Ab fashion space mein hai."),
            ("inspector-bakra.png", "Chase", "Training wheels bicycle chase - dignity maybe intact."),
            ("sassy-sana.png", "Math", "Landing in 40 seconds - Chacha shop."),
            ("chacha-jalebi.png", "Runway", "Jalebi syrup landing strip ready."),
            ("rocky-rickshaw.png", "Captain Horn", "Auto ka naya naam - Captain Horn."),
        ],
        5: [
            ("begum-bubble.png", "Panic", "Gossip diary gayab. Chai bhi tension mein."),
            ("motu-mama.png", "Too Nice", "Food share - khaye baghair. Unnatural."),
            ("pinky.png", "Pinky", "Main rivals ko compliment de rahi hoon. Help."),
            ("inspector-bakra.png", "Bakra", "Traffic cone se maafi mang li. Professionalism."),
            ("professor-pappu.png", "Sniffer", "Secret sniffer perfume follow karta hai - Begum."),
            ("nano.png", "Decoys", "Fake diaries failayi - galat secrets sahi comedy."),
            ("sassy-sana.png", "Reveal", "Diary mein help reminders hain mean gossip nahi."),
            ("billu-billi.png", "Billu", "Diary mere neeche thi. Warm seat tax."),
            ("lalten-gali-hero.png", "Chai Night", "Thank You Chai Night - guardian Begum."),
            ("dj-dumroo.png", "Soft Beat", "Volume down. Feelings up."),
        ],
        6: [
            ("motu-mama.png", "Promise", "Free langar. Kitchen chhota dil bada."),
            ("chacha-jalebi.png", "Donate", "Sweets donated. Sugar patriotism."),
            ("rocky-rickshaw.png", "Delivery", "Pot tower rickshaw - balance is faith."),
            ("pinky.png", "Brand", "FoodFluencer Fest trending - maybe."),
            ("professor-pappu.png", "Fog", "Flavour multiplier equals onion fog monster."),
            ("dj-dumroo.png", "Blues", "Rone ki awaaz se blues beat."),
            ("nano.png", "Fan-bot", "Fog rival gali mein - accidental peace tears."),
            ("begum-bubble.png", "Look", "Mascara modern art. Main own karti hoon."),
            ("sassy-sana.png", "Plan", "Mint steam plus simple khichdi. Logic wins."),
            ("motu-mama.png", "Lesson", "Feeding people better than finishing plate alone."),
        ],
        7: [
            ("dj-dumroo.png", "Dream", "Beat drop - gali shake. Concert tonight."),
            ("inspector-bakra.png", "Rules", "Silent hours. Whistle is the law."),
            ("begum-bubble.png", "Petition", "Petition for AND against. Fair and confusing."),
            ("pinky.png", "Merch", "Glitter earplugs selling fast."),
            ("rocky-rickshaw.png", "Stage", "Mobile stage on rickshaw - touring already."),
            ("sassy-sana.png", "Deal", "Acoustic plus kids choir plus soft ending."),
            ("professor-pappu.png", "Power Cut", "Glow sticks plus genius panic lighting."),
            ("nano.png", "Generator", "Pedal generator go brrr."),
            ("bunty.png", "Snore Drop", "Mera snore bassline ban gaya. Okay cool."),
            ("lalten-gali-hero.png", "Harmony", "Community circle bigger than ego stage."),
        ],
        8: [
            ("professor-pappu.png", "Clone", "Clone ready - chores finish fast."),
            ("professor-pappu.png", "Clone 2", "Main bhi pagal - official."),
            ("pinky.png", "Content Crisis", "Kaun sa Pappu camera ke liye best hai."),
            ("bunty.png", "Sleep Clone", "Usne mujhse better so liya. Insult."),
            ("sassy-sana.png", "Glitch", "Clones kehte hain thodi si sagal. Fake detected."),
            ("inspector-bakra.png", "Parade", "ID parade fail - mannequin arrested."),
            ("nano.png", "Meter", "Original-o-meter - chai preference test."),
            ("begum-bubble.png", "Rumour", "Pappu ke 12 beta aa gaye - allegedly."),
            ("professor-pappu.png", "Heart", "Invent to help friends not impress world."),
            ("billu-billi.png", "Count", "One Pappu enough. Trust me. I counted."),
        ],
        9: [
            ("rocky-rickshaw.png", "Gridlock", "Rickshaw bol raha hai - chal pad."),
            ("lalten-gali-hero.png", "Traffic", "Wedding baraat stuck in Lalten Gali."),
            ("motu-mama.png", "Wrong Lane", "Snack cart follow - wrong universe lane."),
            ("pinky.png", "Live Map", "Arrows confuse everyone including me."),
            ("inspector-bakra.png", "Signals", "17 hand signals equals traffic ballet."),
            ("chacha-jalebi.png", "Business", "Traffic jalebis for stuck drivers."),
            ("nano.png", "Wall Lights", "Arrow projections on walls - follow the glow."),
            ("dj-dumroo.png", "Horn Beat", "Horns on tempo - cars move with music."),
            ("bunty.png", "Speed Breaker", "Sleeping speed breaker. People slow carefully."),
            ("begum-bubble.png", "Peace", "Two angry uncles one umbrella instant calm."),
            ("rocky-rickshaw.png", "Saved", "Baraat on time. Tip nahi dance yes."),
        ],
        10: [
            ("billu-billi.png", "Finale", "Season final. Tissue rakho - hasi ke liye."),
            ("pinky.png", "Spotlight", "Solo stage ya gang stage. Conflict."),
            ("dj-dumroo.png", "Sound", "Mera sound pehle - ya sabka saath."),
            ("nano.png", "Sparkles", "Safe fireworks this time. Promise 80 percent."),
            ("professor-pappu.png", "Robots", "Festival robots that only mildly explode."),
            ("sassy-sana.png", "Schedule", "Papers ud gaye. Chaos alphabetized."),
            ("bunty.png", "Opening Act", "Main opening. Panic nap required."),
            ("begum-bubble.png", "Mix-up", "Gang khatam ka matlab theme khatam tha."),
            ("inspector-bakra.png", "Useful", "Finally useful clue. Proud mustache moment."),
            ("chacha-jalebi.png", "Food War", "Jalebi alliance vs Samosa Sultan - then friendship."),
            ("lalten-gali-hero.png", "One Show", "Combined act - snore-rap sparkles rickshaw dance."),
            ("dj-dumroo.png", "Together", "Rival gali joins finale. Bakwas better together."),
            ("billu-billi.png", "Bye", "Season 2. Shayad. Abhi fish. Full Bakwas."),
        ],
    }
    bumpers = [
        ("billu-billi.png", "Break", "No ads - only chai thoughts."),
        ("lalten-gali-hero.png", "Gali Tip", "Hansi sehat ke liye zaroori hai."),
        ("chacha-jalebi.png", "Snack Beat", "Jalebi crunch ASMR - cartoon edition."),
        ("bunty.png", "Nap Break", "Audience bhi blink kar lo. Main so raha hoon."),
        ("pinky.png", "Like Reminder", "Like button imaginary hai - hasi real rakho."),
        ("sassy-sana.png", "Fact", "20 minute episode equals friendship plus 40 gags."),
        ("inspector-bakra.png", "Public Service", "Do not arrest jalebis at home."),
        ("dj-dumroo.png", "Sting", "Dumroo sting. Moving on."),
    ]
    story = intros + bodies[ep] + outros
    # Pad with bumpers so we have enough unique-ish cards for ~20 min
    while len(story) < 40:
        b = bumpers[len(story) % len(bumpers)]
        story.append((b[0], f"{b[1]} Ep {ep:02d}", f"{b[2]} Phir se hasao."))
    return story[:40]


def safe_text(text: str) -> str:
    for a, b in {
        "\\": "",
        ":": " -",
        "'": "",
        '"': "",
        "%": " pct",
        "[": "(",
        "]": ")",
        ",": " ",
        ";": " -",
        "—": " - ",
        "–": " - ",
        "…": "...",
        "/": " ",
    }.items():
        text = text.replace(a, b)
    return text


def render_episode(ep: int, seconds: int = 1200) -> Path:
    OUT.mkdir(parents=True, exist_ok=True)
    ART.mkdir(parents=True, exist_ok=True)
    cards = cards_for(ep)
    hold = seconds / len(cards)
    slug = f"ep{ep:02d}-animatic-20min.mp4"
    out = OUT / slug

    with tempfile.TemporaryDirectory(prefix=f"gali_ep{ep:02d}_") as tmp:
        tmp_path = Path(tmp)
        segments: list[Path] = []
        for idx, (img, title, line) in enumerate(cards):
            src = ASSETS / img
            if not src.exists():
                src = ASSETS / "lalten-gali-hero.png"
            seg = tmp_path / f"s{idx:04d}.mp4"
            title_e = safe_text(title)[:55]
            line_e = safe_text(line)[:85]
            cmd = [
                "ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
                "-framerate", "2", "-loop", "1", "-t", f"{hold:.3f}", "-i", str(src),
                "-f", "lavfi", "-i", "anullsrc=r=44100:cl=mono",
                "-filter_complex",
                (
                    f"[0:v]scale=854:480:force_original_aspect_ratio=increase,crop=854:480,"
                    f"drawbox=x=0:y=h-120:w=iw:h=120:color=0x0B1D36@0.72:t=fill,"
                    f"drawtext=text='{title_e}':fontcolor=0xFFB703:fontsize=28:x=24:y=h-105,"
                    f"drawtext=text='{line_e}':fontcolor=white:fontsize=20:x=24:y=h-68[v];"
                    f"[1:a]atrim=0:{hold:.3f},asetpts=PTS-STARTPTS,volume=0.0[a]"
                ),
                "-map", "[v]", "-map", "[a]",
                "-c:v", "libx264", "-pix_fmt", "yuv420p", "-r", "2",
                "-preset", "ultrafast", "-crf", "32",
                "-c:a", "aac", "-b:a", "64k",
                "-t", f"{hold:.3f}",
                str(seg),
            ]
            proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if proc.returncode != 0:
                raise RuntimeError(proc.stderr[-2000:] or proc.stdout[-2000:] or "ffmpeg failed")
            segments.append(seg)
            if idx % 8 == 0:
                print(f"  ep{ep:02d} {idx}/{len(cards)}", flush=True)

        lst = tmp_path / "list.txt"
        lst.write_text("".join(f"file '{s.name}'\n" for s in segments))
        cmd = [
            "ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
            "-f", "concat", "-safe", "0", "-i", str(lst),
            "-c", "copy", str(out),
        ]
        proc = subprocess.run(cmd, cwd=str(tmp_path), stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        if proc.returncode != 0:
            cmd = [
                "ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
                "-f", "concat", "-safe", "0", "-i", str(lst),
                "-c:v", "libx264", "-pix_fmt", "yuv420p", "-preset", "ultrafast",
                "-c:a", "aac", str(out),
            ]
            proc = subprocess.run(cmd, cwd=str(tmp_path), stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if proc.returncode != 0:
                raise RuntimeError(proc.stderr[-2000:])

    art = ART / slug
    art.write_bytes(out.read_bytes())
    print(f"OK {slug} ({out.stat().st_size / (1024 * 1024):.1f} MB)", flush=True)
    return out


def main() -> None:
    import argparse

    p = argparse.ArgumentParser()
    p.add_argument("--ep", type=int, nargs="*", default=list(range(1, 11)))
    p.add_argument("--seconds", type=int, default=1200, help="Target runtime seconds (default 1200 = 20 min)")
    args = p.parse_args()
    for ep in args.ep:
        print("Rendering long animatic for episode", ep, flush=True)
        render_episode(ep, seconds=args.seconds)
    print("Long animatics complete.", flush=True)


if __name__ == "__main__":
    main()
