# Flutter Mobile App Deployment Guide: Google Play & Apple App Store

This guide provides a comprehensive, step-by-step walkthrough for deploying your **LCMTV** Flutter application to both the Google Play Store and Apple App Store.

---

## 🛠️ General Prerequisites

Before you begin, ensure you have the following ready:

1.  **Developer Accounts**:
    *   **Google Play Console**: $25 one-time fee.
    *   **Apple Developer Program**: $99 annual fee.
2.  **App Assets**:
    *   **Icons**: High-resolution 1024x1024 icon.
    *   **Screenshots**: For various screen sizes (Android: 7-inch, 10-inch; iOS: 6.5-inch, 5.5-inch, iPad).
    *   **Descriptions**: Short and long descriptions, feature list.
    *   **Privacy Policy**: A hosted URL (e.g., `https://lcmtv.com/privacy-policy`).

---

## 🤖 Part 1: Google Play Store (Android)

### 1. App Signing
Android requires all apps to be digitally signed before they can be installed.

1.  **Generate a Keystore**:
    Open your terminal in the `mobile_app` directory and run:
    ```bash
    keytool -genkey -v -keystore ~/upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
    ```
    *Keep this file safe! If you lose it, you cannot update your app.*

2.  **Configure `key.properties`**:
    Create a file at `android/key.properties` with:
    ```properties
    storePassword=<your-password>
    keyPassword=<your-password>
    keyAlias=upload
    storeFile=<path-to-keystore-file>
    ```

3.  **Update `build.gradle`**:
    In `android/app/build.gradle`, update the `signingConfigs` and `buildTypes` to use your properties.

### 2. Build the App Bundle
Flutter recommends using **App Bundles (.aab)** as they optimize the download size for users.

1.  Clean the project: `flutter clean`
2.  Get dependencies: `flutter pub get`
3.  Build: `flutter build appbundle`
    *Output location: `build/app/outputs/bundle/release/app-release.aab`*

### 3. Google Play Console Setup
1.  **Create App**: Log in to Play Console and click "Create App".
2.  **Internal Testing/Production**: Go to "Production" > "Create new release".
3.  **Upload AAB**: Drag and drop the `.aab` file you built.
4.  **Store Presence**: Fill in "Main store listing" with your graphics and text.
5.  **App Content**: Complete all mandatory declarations (Ads, Age Rating, Data Safety).

---

## 🍎 Part 2: Apple App Store (iOS)

> [!IMPORTANT]
> You **MUST** use a Mac with Xcode installed for iOS deployment.

### 1. App Store Connect & Certificates
1.  **Register App ID**: Go to [Apple Developer Portal](https://developer.apple.com/) and create a new App ID with your bundle identifier (e.g., `com.lcmtv.mobile`).
2.  **Create Certificate**: Generate a "Distribution" certificate.
3.  **App Store Connect**: Create a "New App" in [App Store Connect](https://appstoreconnect.apple.com/).

### 2. Xcode Configuration
1.  Open `ios/Runner.xcworkspace` in Xcode.
2.  **Signing & Capabilities**: Select the "Runner" target, go to "Signing & Capabilities", and ensure "Automatically manage signing" is checked with your Team selected.
3.  **Version/Build**: Ensure the Version and Build numbers match your `pubspec.yaml`.

### 3. Archive and Upload
1.  Select **Runner > Any iOS Device (arm64)** as the target.
2.  Go to **Product > Archive**.
3.  Once the archive is created, click **Distribute App**.
4.  Choose **App Store Connect** > **Upload**.
5.  Wait for the processing to finish (you'll receive an email).

### 4. App Store Connect Setup
1.  Go to your app in App Store Connect.
2.  **Select Build**: Click the `+` icon in the "Build" section and select the one you uploaded from Xcode.
3.  **Metadata**: Fill in screenshots, descriptions, and keywords.
4.  **Review**: Submit for Review.

---

## 🚀 Part 3: Versioning Best Practices

Every time you update the app, you MUST increment the version in `pubspec.yaml`:
```yaml
version: 1.0.0+1
```
*   `1.0.0` is the **Version Name** (visible to users).
*   `1` is the **Build Number** (internal to stores). For every new upload, increment the build number (e.g., `1.0.0+2`).

---

## 🔍 Part 4: Common Pitfalls
*   **Permissions**: Ensure all permissions (Camera, Location) have descriptions in `Info.plist` (iOS) and `AndroidManifest.xml` (Android).
*   **Obfuscation**: Use `--obfuscate` and `--split-debug-info` during build to protect your code.
*   **Testing**: Always use **TestFlight** (iOS) and **Internal Testing** (Android) before going live.
