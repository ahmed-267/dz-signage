# Player Soak Test (manual)

Do **not** run multi-hour soak in CI.

## Goal

Confirm a paired Player remains stable under continuous Playlist playback.

## Setup

1. Pair a Screen on Chromium (or target TV browser).
2. Publish a Playlist with short item durations (e.g. 5–10s) for accelerated cycles.
3. Open DevTools Performance / Memory where available.

## Observe (2–8 hours)

- Heartbeat continues at configured interval
- No duplicate heartbeat timers (single interval)
- Playlist advances without accumulating delay beyond a few seconds/hour
- Memory roughly stable (no unbounded growth)
- Manifest polling does not storm
- Offline: disconnect → playback continues → reconnect reconciles
- Analytics receives content_started / deployment_applied without flooding

## Failure signals

- Blank crash loops
- Rapidly rising JS heap
- Heartbeat 429 storms
- Service Worker trapping old broken assets after deploy

Record notes in the launch checklist; fix reliability issues before launch.
