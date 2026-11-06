# PRD: Voice NG Urdu Voiceover Studio

## 1. Summary

Voice NG is a browser-based Urdu voiceover studio at `it.noorgee.com/VO/`. It converts Urdu scripts into WAV and MP3 audio, supports multi-chunk text, and offers Gemini voice replication and Fish Audio voice generation.

This document defines the working product scope, technical requirements, security rules, and release plan for the repaired tool page.

## 2. Contacts

| Name | Role | Comment |
|---|---|---|
| Nooruddin | Product owner and media technology lead | Approves UX, Urdu wording, and release decisions. |
| Development agent | Developer and QA support | Maintains frontend, PHP proxies, and deployment-ready files. |
| Hosting provider | cPanel operations | Serves `/home/noorgeec/it.noorgee.com` and runs GitHub-based deployment. |

## 3. Background

The page UI is deployed and visible, but its main generation flow was not working. The frontend called `gemini_tts.php`, while the repository contained `gemini_tts_server_proxy.php`. The live server returned HTTP 404 for the filename used by the browser.

The Gemini proxy also forwarded an older `generateContent` payload directly to Gemini. Current Gemini 3.8 TTS uses the Interactions API for speech generation. The repaired proxy now translates the existing frontend request into the current API format and translates the audio response back into the frontend format.

The Fish proxy was incomplete and contained placeholder source fragments. It now has complete request validation, environment loading, TTS forwarding, and voice-model creation handling.

## 4. Objective

Make the Voice NG page usable for real Urdu voiceover production without exposing server secrets or requiring users to understand API transport details.

### Key results

- Browser calls reach the deployed Gemini proxy with no filename mismatch.
- Gemini TTS requests use supported 3.8 TTS models and return playable WAV audio.
- Fish Audio TTS uses the configured server-side model and reference ID.
- Missing keys, invalid input, upstream failures, and cross-origin requests return clear JSON errors.
- No API secret is committed to GitHub.
- The existing Urdu UI, multi-chunk flow, WAV assembly, MP3 export, and portrait-video flow remain compatible.

## 5. Market Segments

Voice NG serves people who need quick Urdu narration without desktop audio software:

- News workers preparing Urdu bulletins and voiceovers.
- Journalists and content creators producing short social videos.
- Media teams testing multiple delivery styles and speaking speeds.
- Urdu educators, presenters, and independent producers.

The main job is: enter Urdu text, choose a voice and style, generate audio, and download a usable file.

## 6. Value Propositions

Voice NG combines Urdu-first controls, browser-based audio assembly, and multiple voice engines in one lightweight page.

Users gain:

- Urdu RTL writing and readable Nastaliq presentation.
- Multi-chunk handling for long scripts.
- WAV and MP3 downloads.
- Optional Fish Audio reference voices.
- Optional Gemini replicated voices with consent recording.
- Local diagnostic simulation for UI and audio pipeline testing.

Users avoid installing desktop TTS software, manually joining audio chunks, and exposing API keys in frontend source files.

## 7. Solution

### 7.1 UX and user flow

1. Open `/VO/`.
2. Select Urdu or English UI.
3. Choose server fallback or enter an optional Gemini key.
4. Enter or load Urdu script.
5. Select Gemini or Fish Audio.
6. Select model, voice, style, pace, and accent.
7. Generate a short audition or full voiceover.
8. Review the audio player and duration.
9. Download WAV, encode MP3, share the file, or render the portrait caption video.
10. Optionally create a Gemini or Fish voice model using consent-controlled recordings.

### 7.2 Key features

- RTL Urdu editor with character and chunk statistics.
- Gemini 3.8 Flash TTS and Flash-Lite TTS support.
- Server proxy filename is `gemini_tts_server_proxy.php`.
- Proxy compatibility layer for the existing frontend request shape.
- Current Gemini Interactions API request translation.
- Compatibility response containing base64 audio for existing browser assembly logic.
- Gemini voice creation and deletion through `/v1beta/voices`.
- Fish Audio TTS through `/v1/tts`.
- Fish Audio model creation through `/v1/model`.
- Secure environment loading from `/home/noorgeec/cred/it-VO.env`.
- No credentials stored in repository files.
- Clear HTTP and JSON error handling.
- WAV, MP3, sharing, and caption-video export.
- Simulated offline diagnostics.

### 7.3 Technology

- Single-page HTML, CSS, and JavaScript frontend.
- PHP 8-compatible server proxies.
- cURL for upstream API requests.
- Browser AudioContext and OfflineAudioContext for audio normalization.
- LameJS for client-side MP3 encoding.
- GitHub `main-it` branch connected to cPanel deployment.

### 7.4 Assumptions

- cPanel deploys all files under `VO/`, including PHP files.
- `/home/noorgeec/cred/it-VO.env` exists and has active `GEMINI_API_KEY` and `FISH_API_KEY` values.
- PHP cURL is enabled on hosting.
- The configured Fish Audio account permits the selected model and voice operations.
- Browser supports MediaRecorder, AudioContext, and the File/Share APIs used by the page.
- Voice replication is used only with the speaker's own voice or documented consent.

## 8. Release

### First release

- Deploy the corrected `VO/index.html`.
- Deploy `VO/gemini_tts_server_proxy.php`.
- Deploy complete `VO/fish_tts.php`.
- Confirm `.env` and credential files remain outside GitHub.
- Test proxy availability, Gemini generation, Fish generation, and local diagnostics.
- Confirm generated WAV plays and MP3 export remains functional.

### Future releases

- Add server-side rate limiting per IP or session.
- Add structured request logging without storing script text or audio.
- Add an admin health check for environment keys and upstream status.
- Add retry handling for temporary 429 and 5xx responses.
- Add upload-size and recording-duration checks before voice creation.
- Add automated browser acceptance checks for desktop and mobile layouts.

### Relative delivery estimate

- Proxy and frontend repair: one focused development cycle.
- cPanel deployment and smoke testing: one deployment cycle.
- Hardening, rate limiting, and automated browser tests: a later improvement cycle.
