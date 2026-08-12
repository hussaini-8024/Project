# Install Flutter on Windows (laptop)

## Option A — Official installer (recommended)

1. Download the Flutter SDK zip from https://docs.flutter.dev/get-started/install/windows
2. Extract to `C:\src\flutter` (avoid paths with spaces).
3. Add `C:\src\flutter\bin` to your user **PATH**.
4. Install [Android Studio](https://developer.android.com/studio), then open **SDK Manager** and install:
   - Android SDK Platform 35
   - Android SDK Build-Tools
   - Android SDK Command-line Tools
5. In a new terminal:

```bat
flutter doctor
flutter doctor --android-licenses
flutter create my_app
cd my_app
flutter run
```

## Option B — WSL2 (Ubuntu)

From WSL:

```bash
cd /path/to/this/repo
bash scripts/install-flutter-linux.sh
source ~/.bashrc
flutter doctor
```

For physical device testing from WSL, use USB/ip forwarding or develop on native Windows with Android Studio.
