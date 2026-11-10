/* NG Voice: lightweight post-generation pronunciation review. */
(function () {
  'use strict';

  const STORAGE_KEY = 'ng_voice_pronunciation_review_v1';
  const DIACRITICS = ['َ', 'ِ', 'ُ', 'ْ', 'ّ', 'ٰ', 'ً', 'ٍ', 'ٌ'];
  let selection = null;

  function readStore() {
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(value) ? value : [];
    } catch (_) {
      return [];
    }
  }

  function getCorrectionMemory(sourceText) {
    const text = String(sourceText || '');
    return readStore()
      .filter(item => item && item.original && item.corrected)
      .filter(item => !text || text.includes(String(item.original)))
      .slice(-40)
      .map(item => ({ original: String(item.original), corrected: String(item.corrected) }));
  }

  function rememberApprovedText(originalText, correctedText) {
    const originalWords = String(originalText || '').split(/(\s+)/);
    const correctedWords = String(correctedText || '').split(/(\s+)/);
    const items = readStore();
    const limit = Math.min(originalWords.length, correctedWords.length);
    for (let index = 0; index < limit; index += 1) {
      const original = originalWords[index];
      const corrected = correctedWords[index];
      if (original && corrected && !/^\s+$/.test(original) && original !== corrected && original.length <= 120 && corrected.length <= 120) {
        items.push({ original, corrected, createdAt: new Date().toISOString() });
      }
    }
    writeStore(items.slice(-200));
  }

  function writeStore(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }

  function getReviewText() {
    return String(window.STATE && window.STATE.ttsText || '');
  }

  function setReviewText(value) {
    if (window.STATE) {
      window.STATE.ttsText = value;
      window.STATE.reviewDirty = true;
    }
    const box = document.getElementById('pronunciationReviewText');
    if (box) box.textContent = value;
  }

  function showMessage(text, type) {
    const box = document.getElementById('pronunciationReviewMessage');
    if (!box) return;
    box.textContent = text;
    box.className = 'text-xs mt-2 ' + (type === 'error' ? 'text-red-300' : 'text-emerald-300');
  }

  function closePopup() {
    const popup = document.getElementById('pronunciationPopup');
    if (popup) popup.classList.add('hidden');
    selection = null;
  }

  function openPopup() {
    const review = document.getElementById('pronunciationReviewText');
    if (!review) return;
    const current = window.getSelection();
    if (!current || current.rangeCount === 0 || current.isCollapsed || !review.contains(current.anchorNode)) {
      showMessage('پہلے آڈیو متن میں ایک لفظ select کریں۔', 'error');
      return;
    }

    const range = current.getRangeAt(0);
    const selectedText = current.toString().trim();
    if (!selectedText || /\s/.test(selectedText)) {
      showMessage('صرف ایک لفظ select کریں۔', 'error');
      return;
    }

    const walker = document.createTreeWalker(review, NodeFilter.SHOW_TEXT);
    let node;
    let start = 0;
    let foundStart = false;
    while ((node = walker.nextNode())) {
      if (node === range.startContainer) {
        start += range.startOffset;
        foundStart = true;
        break;
      }
      start += node.nodeValue.length;
    }
    if (!foundStart) {
      showMessage('منتخب لفظ دوبارہ select کریں۔', 'error');
      return;
    }

    selection = { start, end: start + current.toString().length, original: selectedText };
    const input = document.getElementById('pronunciationCorrectionInput');
    if (input) {
      input.value = selectedText;
      input.focus();
      input.select();
    }
    const popup = document.getElementById('pronunciationPopup');
    if (popup) popup.classList.remove('hidden');
    showMessage('', 'ok');
  }

  function saveCorrection() {
    if (!selection) {
      showMessage('پہلے ایک لفظ select کریں۔', 'error');
      return;
    }
    const input = document.getElementById('pronunciationCorrectionInput');
    const corrected = String(input && input.value || '').trim();
    if (!corrected) {
      showMessage('درست تلفظ لکھیں۔', 'error');
      return;
    }

    const text = getReviewText();
    const before = text.slice(0, selection.start);
    const after = text.slice(selection.end);
    setReviewText(before + corrected + after);

    const items = readStore();
    items.push({
      original: selection.original,
      corrected,
      createdAt: new Date().toISOString()
    });
    writeStore(items.slice(-200));
    closePopup();
    showMessage('تلفظ محفوظ ہو گیا۔ دوبارہ Generate دبائیں۔', 'ok');
  }

  function insertDiacritic(mark) {
    const input = document.getElementById('pronunciationCorrectionInput');
    if (!input) return;
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? start;
    input.value = input.value.slice(0, start) + mark + input.value.slice(end);
    input.focus();
    input.setSelectionRange(start + mark.length, start + mark.length);
  }

  function exportJSON() {
    const payload = {
      version: 1,
      format: 'ng-voice-pronunciation-review',
      exportedAt: new Date().toISOString(),
      corrections: readStore()
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ng-voice-pronunciation-corrections.json';
    a.click();
    URL.revokeObjectURL(url);
  }

  function importJSON(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function () {
      try {
        const payload = JSON.parse(String(reader.result || '{}'));
        const corrections = Array.isArray(payload) ? payload : payload.corrections;
        if (!Array.isArray(corrections)) throw new Error('Invalid corrections');
        const valid = corrections.filter(item => item && item.original && item.corrected)
          .map(item => ({ original: String(item.original), corrected: String(item.corrected), createdAt: item.createdAt || new Date().toISOString() }));
        writeStore(valid.slice(-200));
        showMessage(`${valid.length} corrections import ہو گئیں۔`, 'ok');
      } catch (_) {
        showMessage('JSON فائل درست نہیں ہے۔', 'error');
      }
    };
    reader.readAsText(file);
  }

  function init() {
    const review = document.getElementById('pronunciationReviewText');
    if (review) review.addEventListener('mouseup', openPopup);
    document.querySelectorAll('[data-diacritic]').forEach(button => {
      button.addEventListener('click', () => insertDiacritic(button.dataset.diacritic));
    });
    const file = document.getElementById('pronunciationImportFile');
    if (file) file.addEventListener('change', () => importJSON(file.files[0]));
  }

  window.NGPronunciation = {
    init,
    render: setReviewText,
    getCorrectionMemory,
    rememberApprovedText,
    openPopup,
    closePopup,
    saveCorrection,
    exportJSON,
    getText: getReviewText,
    reset: () => { writeStore([]); showMessage('Correction data صاف ہو گیا۔', 'ok'); }
  };
})();
