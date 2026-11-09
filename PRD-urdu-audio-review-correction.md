# PRD: Urdu Audio Review Pronunciation Correction

## 1. Summary

Voice NG needs a small correction loop for Urdu words that sound wrong after TTS generation. The user should listen to the first audio, select one wrong word from the generated text, add correct Urdu diacritics, and generate audio again.

The feature keeps the original script unchanged. It creates a separate temporary TTS text and stores correction records in a portable JSON format.

## 2. Contacts

| Name | Role | Comment |
|---|---|---|
| Nooruddin | Product owner and media worker | Reviews Urdu pronunciation and approves corrections. |
| Development agent | Frontend developer | Maintains the Voice NG browser workflow. |

## 3. Background

Urdu TTS can pronounce a word differently from the intended reading. Examples include `اردو` being read without the intended `پیش`, or `بنا` being read with the wrong vowel.

A full dictionary editor adds too much work before the first audio test. The better workflow is post-generation review: hear the result first, correct only the words that sound wrong, and regenerate.

## 4. Objective

Reduce repeated manual editing during Urdu voiceover production. Let users correct one word at the exact place where it sounds wrong, without changing the visible source script.

### Key results

- User can select one word in generated TTS text.
- User can add or paste corrected Urdu spelling with diacritics.
- User can save correction and regenerate audio using corrected text.
- Original editor text remains unchanged.
- Corrections can be exported and imported as JSON.
- API keys, audio files, and full scripts never enter correction JSON.

## 5. Market Segment(s)

The feature serves Urdu journalists, news presenters, voiceover producers, and content creators who review generated audio by ear.

Their main job is to fix only the few words that sound wrong, not to build a full pronunciation dictionary before production.

## 6. Value Proposition(s)

The feature gives users a short audio-first workflow:

1. Generate.
2. Listen.
3. Select wrong word.
4. Add diacritics.
5. Generate again.

Users avoid rebuilding the whole script, editing hidden API payloads, or maintaining a large dictionary. JSON export keeps corrections portable for another AI, device, or future Voice NG version.

## 7. Solution

### 7.1 UX and user flow

After successful audio generation, Voice NG shows the exact TTS text in a selectable review box. The user selects one word, clicks `منتخب لفظ درست کریں`, and enters corrected text in a small popup.

The popup includes quick buttons for `زبر`, `زیر`, `پیش`, `جزم`, and `تشدید`. The user saves the correction, then presses the existing Generate button again.

The correction panel also provides JSON export, JSON import, and reset controls.

### 7.2 Key features

- Generated TTS text review box.
- Single-word selection validation.
- Correction popup with Urdu input.
- Diacritic quick buttons.
- Replacement of only the selected occurrence.
- Separate original script and corrected TTS text.
- Local browser storage for the correction list.
- JSON export with version and correction records.
- JSON import with validation.
- Existing generation button reused for regeneration.

Example record:

```json
{
  "original": "اردو",
  "corrected": "اُردو",
  "createdAt": "2026-11-09T00:00:00.000Z"
}
```

### 7.3 Technology

The feature uses browser JavaScript, the Selection and Range APIs, localStorage, FileReader, Blob download, and the existing Voice NG generation pipeline.

The correction module lives in `VO/pronunciation_dictionary.js`. The existing page remains the main UI and keeps its dark Urdu studio design.

### 7.4 Assumptions

- The user listens to audio and identifies the wrong word.
- The review text contains plain selectable text.
- One selected word is enough for the first release.
- The browser supports Selection, localStorage, FileReader, and Blob APIs.
- Automatic pronunciation detection is not part of the first release.
- Corrected TTS text can contain diacritics without changing captions.

## 8. Release

### First version

Release the review panel, single-word selection, diacritic input, correction save, regeneration flow, localStorage, JSON export, JSON import, and reset.

### Future versions

Add correction history with undo, phrase-level correction, voice-specific correction profiles, `Copy for AI`, correction search, and optional synchronized captions that show the corrected reading.

### Relative delivery estimate

This is a focused frontend extension suitable for one development cycle followed by one browser smoke-test cycle.
