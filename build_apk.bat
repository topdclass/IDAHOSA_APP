@echo off
echo ==================================================
echo RosmonSMS Mobile App Builder
echo ==================================================
echo.
echo Step 1: Navigating to mobile project...
cd rosmon_mobile

echo.
echo Step 2: Generating platform files (Android/iOS)...
flutter create . --platforms android,ios

echo.
echo Step 3: Getting dependencies...
flutter pub get

echo.
echo Step 4: Building Release APK...
flutter build apk --release

echo.
echo ==================================================
echo Build Process Finished.
echo If successful, your APK is located at:
echo rosmon_mobile\build\app\outputs\flutter-apk\app-release.apk
echo ==================================================
pause
