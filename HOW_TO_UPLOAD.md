# How to upload AU-Kamra to your GitHub (faisal3537khan-max)

Do this on a **computer**, in **Chrome** or **Edge**. Sign in as **faisal3537khan-max**.

This chat cannot push to your account. You upload the files yourself. Follow **Method A** if you do not know Git.

## Demo login (must stay visible)

```
Username:  admin
Password:  admin123
```

Dashboard: `http://127.0.0.1:8443`

---

## What you are uploading (complete project)

Upload **only** these items. Do **not** upload `playbooks/`, `roles/`, `inventory/`, `ansible.cfg`, `src/`, or `aukamra/`.

```
README.md                 GitHub homepage — username and password shown here
PROJECT.md                Short project page
.gitignore                Tells Git to ignore junk files
rmm/                      The full program (see file list below)
```

### Files inside `rmm/`

| File | What it is |
|------|------------|
| `run_server.py` | Starts the admin dashboard |
| `run_agent.py` | Starts the agent on a PC |
| `Start-Server.bat` | Double-click on Windows to start the server |
| `requirements.txt` | Python libraries to install |
| `README.md` | Extra setup notes + login |
| `server/app.py` | Server API (login, agents, shell, live view) |
| `server/database.py` | SQLite database |
| `server/paths.py` | File locations |
| `server/agent_packages.py` | Builds/downloads agent installers |
| `server/remote_push.py` | Push-install to a Windows PC |
| `server/static/index.html` | Website UI (login shows **admin** / **admin123**) |
| `agent/agent.py` | Agent that runs on each PC |
| `agent/install_service.py` | Permanent install / uninstall |
| `agent/discovery.py` | LAN discovery |
| `shared/config.py` | Default port, token, admin password |
| `shared/protocol.py` | Server–agent messages |
| `build/build_windows.bat` | Build Windows `.exe` files |
| `build/server.spec` | PyInstaller config for the server |
| `build/agent.spec` | PyInstaller config for the agent |
| `build/build_unix_agent.sh` | Build macOS/Linux agent |

---

## Step 0 — Download the complete files

1. Open this link (the project branch):  
   https://github.com/hussaini-8024/Project/tree/cursor/faisal-rmm-project-c3e6
2. Green button **Code** → **Download ZIP**.
3. Extract the ZIP (right-click → **Extract All**).
4. Open the extracted folder. You will see `README.md`, `PROJECT.md`, `.gitignore`, and `rmm`.

Keep that window open. You will copy those four items.

---

## Step 1 — Open your empty GitHub repo

1. Sign in: https://github.com/login  
   Username: **faisal3537khan-max**
2. Open your repo:  
   https://github.com/faisal3537khan-max/-AU-Kamra-IT-Experts-Remote-Manager
3. Optional but recommended: **Settings** (repo menu) → **General** → rename to  
   `AU-Kamra-IT-Experts-Remote-Manager` (remove the `-` at the start) → **Rename**.

The page should say the repository is empty.

---

## Method A — GitHub website (no Git)

1. On the empty repo page, click **uploading an existing file**  
   (or **Add file** → **Upload files**).
2. Open the extracted folder from Step 0.
3. Drag these onto the GitHub page:
   - `README.md`
   - `PROJECT.md`
   - `.gitignore`
   - the whole **`rmm` folder**
4. Wait until GitHub finishes uploading (the `rmm` folder has many files).
5. At the bottom, commit message: `Add AU-Kamra IT Experts Remote Manager`
6. Click **Commit changes**.

### Check it worked

- Repo homepage must show **Username: admin** and **Password: admin123**.
- You should see a `rmm` folder. Open `rmm/server/static/index.html` — that is the dashboard page.

---

## Method B — GitHub Desktop (easy visual Git)

1. Install: https://desktop.github.com
2. Open GitHub Desktop → **File** → **Options** → **Accounts** → sign in as **faisal3537khan-max**.
3. **File** → **Clone repository** → pick  
   `faisal3537khan-max/-AU-Kamra-IT-Experts-Remote-Manager`  
   (or the renamed name). Choose a folder such as `Documents`. Clone.
4. GitHub Desktop shows a local folder. Click **Show in Explorer**.
5. Copy into that folder (not inside a subfolder):
   - `README.md`
   - `PROJECT.md`
   - `.gitignore`
   - `rmm` (entire folder)
6. Return to GitHub Desktop. You should see many new files.
7. Summary: `Add AU-Kamra IT Experts Remote Manager`
8. **Commit to main** → **Push origin**.

---

## Method C — Git Bash (if Git is installed)

Sign in as **faisal3537khan-max**. In Git Bash:

```bash
git clone --depth 1 --branch cursor/faisal-rmm-project-c3e6 https://github.com/hussaini-8024/Project.git src
mkdir aukamra && cd aukamra
git init -b main
cp ../src/README.md ../src/PROJECT.md ../src/.gitignore .
cp -r ../src/rmm .
git add .
git commit -m "Add AU-Kamra IT Experts Remote Manager"
git remote add origin https://github.com/faisal3537khan-max/-AU-Kamra-IT-Experts-Remote-Manager.git
git push -u origin main
```

If you renamed the repo, change the `origin` URL to the new name.

GitHub will ask you to sign in. Use **faisal3537khan-max**, not another account.

---

## How to run after upload (on your PC)

Python 3 must be installed. In a terminal:

```bash
cd rmm
pip install -r requirements.txt
python run_server.py
```

Browser: http://127.0.0.1:8443

```
Username:  admin
Password:  admin123
```

On Windows you can also double-click `rmm\Start-Server.bat`.
