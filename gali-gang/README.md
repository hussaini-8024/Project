# GALI GANG — Full Bakwas (Season 1)

Funny **Urdu–Hindi** neighbourhood cartoon series package.

## What’s included

- **12 original character designs** (illustration art)
- **1 series setting key art** (Lalten Gali)
- **10 episode teleplays** written for ~**20:00** runtime each
- **10 promo videos** (MP4 trailers)
- **Showcase website** with character gallery + storyboard player
- Scripts to generate **~20 minute storyboard animatics** (still-frame timing reels)

> Fully frame-by-frame animated 20-minute cartoons require a studio animation pipeline (layout, animation, cleanup, color, lip-sync, mix). This repo delivers the creative series bible, characters, scripts, promos, and animatic generator.

## Quick start

```bash
# Open the series site
cd gali-gang/site
python3 -m http.server 8080
# visit http://localhost:8080
```

## Generate videos

```bash
# Short promos for all 10 episodes
python3 gali-gang/scripts/make_promos.py

# Long ~20 minute storyboard animatics
python3 gali-gang/scripts/make_long_animatics.py
# or one episode:
python3 gali-gang/scripts/make_long_animatics.py --ep 1
```

## Characters (12)

Bunty, Pinky, Chacha Jalebi, Inspector Bakra, Motu Mama, Sassy Sana, Rocky Rickshaw, Nano, DJ Dumroo, Begum Bubble, Professor Pappu, Billu Billi.

## Episodes (10 × ~20 min)

1. Jalebi Jam  
2. Selfie Se Satyanash  
3. Inspector Ki Investigation  
4. Nano Ka Rocket  
5. Begum Bubble Ka Secret  
6. Motu Mama Ka Langar  
7. DJ Dumroo Night  
8. Professor Pappu Ka Clone  
9. Rocky vs Traffic  
10. Lalten Gali Utsav  

Scripts live in `gali-gang/episodes/`.
