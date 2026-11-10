# PRD: Voice NG Urdu Diacritics, Tags, Voice Defaults, and Portrait Video Repair

## 1. Summary

Voice NG needs a safer Urdu pronunciation workflow before audio generation. This release adds Gemini-assisted diacritization with a human review step, clickable vocal tags, editor font controls, Fish Audio defaults, and a reliable portrait caption video recorder that preserves timing and audio.

The original script remains unchanged until the user approves the AI result. Approved word corrections remain in browser storage and are sent as relevant examples in later diacritization requests.

## 2. Contacts

| Name | Role | Comment |
|---|---|---|
| Nooruddin | Product owner and Urdu media workflow reviewer | Reviews diacritics, voice quality, tags, and exported video. |
| Development agent | Frontend and PHP developer | Maintains the browser UI and protected server proxy. |
| cPanel deployment | Hosting system | Deploys `main-it` into `/home/noorgeec/it.noorgee.com`. |

## 3. Background

Urdu TTS can read the same unmarked word in different ways. Missing or incorrect zabar, zer, pesh, jazm, tashdeed, madd, or tanween can change the intended pronunciation. The existing post-generation correction panel helps after an audition, but users need an earlier AI-assisted draft that they can review before generating audio.

The current page also displays vocal tags as text only. Fish Audio is available but is not the default engine, and the Fish reference field is empty. The portrait video recorder can finish too early or produce a video without an audio track when browser timing or audio capture is not ready.

## 4. Objective

Improve first-pass Urdu pronunciation while keeping the user in control. Make common speech tags faster to insert. Reduce setup steps for Fish Audio. Export portrait videos at real-time audio duration with a confirmed audio track.

### Key results

- User can request Gemini diacritics from the main script editor.
- AI output appears in an editable review box before it is used for TTS.
- Original script remains unchanged.
- Previously approved corrections are reused as relevant prompt examples.
- User can insert all five supported vocal tags at the cursor.
- User can switch the editor between five requested font families.
- Fish Audio is selected by default with the requested editable reference ID.
- Portrait video duration follows the decoded audio buffer duration.
- Portrait video recorder waits for audio playback completion and includes an enabled audio track.
- Gemini and Fish secrets remain server-side or in user-supplied fields; no secret is committed.

## 5. Market Segment(s)

The feature serves Urdu news workers, presenters, journalists, voiceover producers, and social-video creators who need readable, reviewable pronunciation before recording.

Their main job is to type Urdu, correct likely readings quickly, audition audio, and export a usable portrait clip without desktop editing software.

## 6. Value Proposition(s)

Users get an Urdu-first correction loop: type, request diacritics, review, accept, generate, and export. The workflow reduces repeated manual vowel marking without hiding the AI result or changing the source script automatically.

Users also gain one-click insertion for pauses and emotions, familiar editor fonts, faster access to a configured Fish voice, and a portrait video export that keeps narration attached.

## 7. Solution

### 7.1 UX and user flow

1. User writes or loads Urdu text.
2. User clicks `Gemini سے اعراب لگائیں`.
3. The protected PHP proxy sends the text and matching approved correction examples to Gemini.
4. The page shows editable diacritized text in a review box.
5. User edits the result, then clicks `یہ متن وائس کے لیے استعمال کریں`.
6. User inserts `<short pause>`, `<long pause>`, `<breath>`, `<sigh>`, or `<laugh>` from buttons at the cursor.
7. User chooses an editor font.
8. Fish Audio is available as the default engine with an editable reference ID.
9. User generates audio and can still use the existing post-generation correction panel.
10. User renders a portrait video. The recorder uses wall-clock timing, the decoded audio duration, an enabled audio track, and a stop callback tied to audio completion.

### 7.2 Key features

- Gemini diacritization endpoint behind `VO/gemini_tts_server_proxy.php`.
- Low-temperature JSON response request with a strict Urdu preservation prompt.
- Relevant local correction memory passed as bounded examples.
- Editable AI preview with accept and dismiss controls.
- Cursor-aware clickable vocal tag insertion.
- Font switcher for Nastaliq, Naskh Asia Type, Nafees Naskh, Tahoma, and Calibri fallbacks.
- Fish Audio selected by default.
- Editable default Fish reference ID `1d0fe21c26b4474191e5b6cfacc1e339`.
- Real-time portrait video capture based on `combinedAudioBuffer.duration`.
- Audio track enabled before `MediaRecorder.start()`.
- Recorder stop on source end with a short flush delay.

### 7.3 Technology

- Existing single-page HTML and JavaScript UI.
- Existing `pronunciation_dictionary.js` local correction storage.
- Existing PHP Gemini proxy with isolated environment loading.
- Gemini text generation through the server proxy; model can be overridden with `GEMINI_DIACRITIC_MODEL` in the private environment.
- Browser Selection, localStorage, Fetch, AudioContext, MediaStreamAudioDestinationNode, Canvas capture, and MediaRecorder APIs.

### 7.4 Assumptions

- Gemini API access and the configured text model are enabled on the cPanel server.
- User reviews AI diacritics before accepting them.
- Browser has the requested fonts installed, or uses the declared fallback stack.
- Browser supports audio capture through MediaRecorder.
- Fish Audio reference ID remains valid or user replaces it.
- Portrait output remains WebM in the current browser-only implementation; MP4 conversion is outside this release.

## 8. Release

### First version

Release the editor controls, secure diacritization endpoint, bounded correction-memory examples, Fish defaults, and repaired real-time portrait recorder. Keep the existing post-generation pronunciation panel for backward compatibility during review.

### Future versions

- Optional server-side correction profiles keyed by an explicit user ID.
- Correction history with undo and phrase-level context.
- Server-side rate limiting and structured privacy-safe request logs.
- Browser acceptance tests across Chrome, Edge, Safari, and mobile browsers.
- Server-side MP4 export with explicit codec support.
- Automatic audio/video duration diagnostics shown to the user.

### Relative delivery estimate

This is one focused implementation cycle followed by one cPanel smoke-test cycle. Gemini quota, Fish credentials, and browser MediaRecorder support remain external dependencies.
