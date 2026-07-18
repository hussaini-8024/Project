# Microsoft Office Install Tutorial (Animated Video)

A **60-second** animated walkthrough with voice narration: how to install Microsoft Office / Microsoft 365.

## Watch

- Artifact: `/opt/cursor/artifacts/ms-office-install-tutorial.mp4`
- Repo copy: [`ms-office-install-tutorial.mp4`](./ms-office-install-tutorial.mp4)

## Steps covered

1. Open **office.com** in a browser  
2. Sign in with your Microsoft account  
3. Click **Install apps** → Microsoft 365 apps  
4. Run the downloaded **Setup** installer  
5. Wait for Office to download and install  
6. Open Word/Excel and sign in to activate  

## Regenerate

```bash
pip install pillow gTTS
python3 tutorials/generate_office_install_tutorial.py
```

Requires `ffmpeg` on `PATH`.
