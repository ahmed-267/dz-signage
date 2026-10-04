# Fire TV Player APK (PWABuilder / Bubblewrap)

The Fire TV “app” is the **existing** hosted Player at `/player`, packaged as a Trusted Web Activity. It is not a second frontend, not Capacitor, and not a Kotlin/Java player.

```text
RMSignage (/player PWA)
        ↓
PWABuilder / Bubblewrap
        ↓
Android APK
        ↓
Fire TV Stick (sideload)
```

`/app` and `/admin` stay normal web UIs. Pairing, manifests, playback, Schedules, and Widgets stay on the Laravel + React Player.

## Production prerequisites

1. Public **HTTPS** origin. Set `APP_URL` to that origin (QR/PIN claim links use `url()`, so a localhost `APP_URL` in production makes pairing unusable from a phone).
2. Deployed `/player`, `/player.webmanifest`, `/player-sw.js`, `/icons/pwa-192.png`, `/icons/pwa-512.png`.
3. Manifest `start_url` `/player`, `display` `fullscreen`, PNG 192 + 512 icons (already in `public/player.webmanifest`).
4. After you have a signing certificate, set:

```bash
PLAYER_ANDROID_PACKAGE=com.rmsignage.player
PLAYER_ANDROID_SHA256_FINGERPRINTS=AA:BB:CC:...
```

`GET /.well-known/assetlinks.json` stays `[]` until fingerprints are configured.

## Package with PWABuilder (UI)

1. Open [PWABuilder](https://www.pwabuilder.com/).
2. Enter `https://YOUR_DOMAIN/player` (or the site origin and select the Player manifest).
3. Confirm the Player manifest (not the customer `/manifest.webmanifest`).
4. Package **Android**. Enable Android TV / leanback if the UI offers it (`touchscreen` not required).
5. Download the package. Prefer **Bubblewrap** locally if you need a TV banner / leanback activity.

## Package with Bubblewrap (CLI)

From a machine with JDK 17+ and Android SDK:

```bash
npm install -g @bubblewrap/cli
bubblewrap init --manifest https://YOUR_DOMAIN/player.webmanifest
```

Use `tools/pwabuilder/twa-manifest.json` as the starting values: `startUrl` `/player`, `display` `fullscreen`, `orientation` `any` (Fire TV is landscape in practice). Then:

```bash
bubblewrap build
```

Signing: create or reuse a keystore. Never commit the keystore or passwords.

After the first signed APK, copy the SHA-256 fingerprint into `PLAYER_ANDROID_SHA256_FINGERPRINTS` and redeploy so Digital Asset Links verify.

## Sideload onto a Fire TV Stick

1. On the Stick: Settings → My Fire TV → Developer options → **ADB** and **Apps from Unknown Sources** on.
2. Settings → About → Network → note the IP.
3. From your computer:

```bash
adb connect FIRE_TV_IP:5555
adb install -r app-release-signed.apk
```

4. Launch **RMSignage TV** from the Fire TV apps row.
5. Pair: the Player shows a PIN (`DZ-XXXX`) and QR. In RMSignage go to **Paired TVs → Pair a TV**. Claim with PIN or by scanning the QR on a phone.
6. Restart the Stick (or force-stop the app) and confirm it **resumes playback without pairing again**.
7. Unpair the TV in RMSignage and confirm the Player returns to the pairing PIN.

## What this APK does not do

- It does not embed `/app` or `/admin`.
- It does not reimplement Screens, Playlists, or Schedules natively.
- It is not listed on the Amazon Appstore unless you separately submit it.
- Offline first-time pairing is not supported (same as the browser Player). A previously paired device can keep playing from the offline package.

## Checklist before a customer Stick

- [ ] `APP_URL` is the public HTTPS origin
- [ ] `/player` loads on the Stick (Silk or the APK)
- [ ] Pairing PIN + QR claim a TV in the correct Business
- [ ] Token survives APK restart (IndexedDB + cookie + localStorage)
- [ ] Published Screen / Playlist plays
- [ ] Schedule window still wins over Deployment (same Player rules)
- [ ] Heartbeat shows the TV Online in Paired TVs
- [ ] Revoke/unpair returns to pairing
