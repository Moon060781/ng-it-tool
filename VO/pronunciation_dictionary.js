/* NG Voice: server-backed Urdu pronunciation review with local fallback. */
(function () {
  'use strict';

  const STORAGE_KEY = 'ng_voice_pronunciation_review_v1';
  const PENDING_KEY = 'ng_voice_pronunciation_pending_v1';
  const MIGRATION_KEY = 'ng_voice_pronunciation_migrated_v1';
  const API_URL = 'corrections_api.php';
  const DIACRITIC_PATTERN = /[\u064B-\u065F\u0670\u06D6-\u06ED]/gu;
  let selection = null;
  let corrections = readLocalStore();
  let pending = readPending();
  let remoteReady = false;
  let csrfToken = '';
  let readyPromise = Promise.resolve();

  function safeRead(key) {
    try {
      const value = JSON.parse(localStorage.getItem(key) || '[]');
      return Array.isArray(value) ? value : [];
    } catch (_) {
      return [];
    }
  }

  function normalizeItem(item) {
    if (!item || typeof item !== 'object') return null;
    const original = String(item.original || '').trim();
    const corrected = String(item.corrected || '').trim();
    if (!original || !corrected || original.length > 120 || corrected.length > 120) return null;
    return {
      ...(Number.isInteger(Number(item.id)) && Number(item.id) > 0 ? { id: Number(item.id) } : {}),
      original,
      corrected,
      context: String(item.context || '').slice(0, 500),
      source: String(item.source || 'manual_review').slice(0, 40),
      createdAt: String(item.createdAt || new Date().toISOString()),
      ...(item.updatedAt ? { updatedAt: String(item.updatedAt) } : {}),
      ...(Number.isFinite(Number(item.useCount)) ? { useCount: Number(item.useCount) } : {})
    };
  }

  function normalizeList(items, limit) {
    return (Array.isArray(items) ? items : [])
      .map(normalizeItem)
      .filter(Boolean)
      .slice(-limit);
  }

  function pairKey(item) {
    return `${item.original}\u0000${item.corrected}`;
  }

  function mergeLists(...lists) {
    const merged = new Map();
    lists.flat().forEach((item) => {
      const normalized = normalizeItem(item);
      if (normalized) merged.set(pairKey(normalized), normalized);
    });
    return Array.from(merged.values()).slice(-500);
  }

  function readLocalStore() {
    return normalizeList(safeRead(STORAGE_KEY), 500);
  }

  function readPending() {
    return normalizeList(safeRead(PENDING_KEY), 200);
  }

  function writeLocalStore(items) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(normalizeList(items, 500)));
    } catch (_) {
      // Local fallback is optional when browser storage is unavailable.
    }
  }

  function writePending(items) {
    pending = normalizeList(items, 200);
    try {
      localStorage.setItem(PENDING_KEY, JSON.stringify(pending));
    } catch (_) {
      // Pending copy is best effort only.
    }
  }

  function rememberPending(items) {
    writePending(mergeLists(pending, items));
  }

  function samePairsExist(items, serverItems) {
    const serverPairs = new Set(serverItems.map(pairKey));
    return items.every((item) => serverPairs.has(pairKey(item)));
  }

  async function request(method, payload) {
    const headers = { Accept: 'application/json' };
    const options = { method, credentials: 'same-origin', cache: 'no-store', headers };
    if (payload) {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = csrfToken;
      options.body = JSON.stringify(payload);
    }

    const response = await fetch(API_URL, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(String(data.error || `HTTP ${response.status}`));
    }
    return data;
  }

  async function importToServer(items, source) {
    const valid = normalizeList(items, 200).map((item) => ({
      original: item.original,
      corrected: item.corrected,
      context: item.context || '',
      source: item.source || source
    }));
    if (valid.length === 0) return corrections;
    const data = await request('POST', { action: 'import', corrections: valid });
    const received = normalizeList(data.corrections, 500);
    if (!samePairsExist(valid, received)) {
      throw new Error('Server did not confirm all imported corrections.');
    }
    corrections = received;
    writeLocalStore(corrections);
    return corrections;
  }

  async function importMany(items, source) {
    let serverItems = corrections;
    const valid = normalizeList(items, 500);
    for (let index = 0; index < valid.length; index += 200) {
      serverItems = await importToServer(valid.slice(index, index + 200), source);
    }
    return serverItems;
  }

  async function bootstrap() {
    corrections = readLocalStore();
    pending = readPending();
    try {
      const data = await request('GET');
      csrfToken = String(data.csrfToken || '');
      if (!csrfToken) throw new Error('Correction security token is missing.');
      remoteReady = true;
      let serverItems = normalizeList(data.corrections, 500);
      const localItems = readLocalStore();
      let migrationDone = false;
      try { migrationDone = localStorage.getItem(MIGRATION_KEY) === '1'; } catch (_) { /* migrate again if storage is blocked */ }

      if (!migrationDone) {
        if (localItems.length > 0) {
          serverItems = await importMany(
            localItems.map((item) => ({ ...item, source: item.source || 'legacy_import' })),
            'legacy_import'
          );
        }
        try { localStorage.setItem(MIGRATION_KEY, '1'); } catch (_) { /* retry migration next visit */ }
      }

      const unsynced = pending.filter((item) => !serverItems.some((saved) => pairKey(saved) === pairKey(item)));
      if (unsynced.length > 0) {
        serverItems = await importToServer(unsynced, 'offline_fallback');
      }
      corrections = serverItems;
      pending = [];
      writePending([]);
      writeLocalStore(corrections);
      showMessage('تصحیحات ویب سائٹ کے database سے منسلک ہیں۔', 'ok');
    } catch (_) {
      remoteReady = false;
      corrections = mergeLists(readLocalStore(), pending);
      showMessage('Database دستیاب نہیں۔ نئی corrections صرف اس browser میں عارضی طور پر محفوظ ہوں گی۔', 'error');
    }
  }

  function normalizeForMatch(value) {
    return String(value || '').replace(DIACRITIC_PATTERN, '');
  }

  function getCorrectionMemory(sourceText) {
    const text = normalizeForMatch(String(sourceText || ''));
    return corrections
      .filter((item) => item && item.original && item.corrected)
      .filter((item) => !text || text.includes(normalizeForMatch(item.original)))
      .sort((left, right) => Date.parse(right.updatedAt || right.createdAt) - Date.parse(left.updatedAt || left.createdAt))
      .slice(0, 40)
      .map((item) => ({ original: String(item.original), corrected: String(item.corrected) }));
  }

  function rememberApprovedText(originalText, correctedText) {
    const originalWords = String(originalText || '').split(/(\s+)/);
    const correctedWords = String(correctedText || '').split(/(\s+)/);
    if (originalWords.length !== correctedWords.length) return;

    const approved = [];
    for (let index = 0; index < originalWords.length; index += 1) {
      const original = originalWords[index];
      const corrected = correctedWords[index];
      if (!original || /^\s+$/.test(original) || original === corrected) continue;
      if (original.length > 120 || corrected.length > 120) continue;
      if (normalizeForMatch(original) !== normalizeForMatch(corrected)) continue;
      approved.push({ original, corrected, source: 'accepted_diacritics' });
    }
    if (approved.length > 0) void persistBatch(approved, 'accepted_diacritics');
  }

  async function persistBatch(items, source) {
    await readyPromise;
    const valid = normalizeList(items, 200).map((item) => ({ ...item, source: item.source || source }));
    if (valid.length === 0) return;
    if (remoteReady) {
      try {
        const synced = await importToServer(valid, source);
        corrections = synced;
        pending = pending.filter((pendingItem) => !valid.some((item) => pairKey(item) === pairKey(pendingItem)));
        writePending(pending);
        writeLocalStore(corrections);
        showMessage('منظور شدہ تلفظ database میں محفوظ ہو گئے۔', 'ok');
        return;
      } catch (_) {
        remoteReady = false;
      }
    }
    corrections = mergeLists(corrections, valid);
    writeLocalStore(corrections);
    rememberPending(valid);
    showMessage('تصحیح browser میں محفوظ ہے؛ database sync ابھی باقی ہے۔', 'error');
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

  async function saveCorrection() {
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
    if (selection.original.length > 120 || corrected.length > 120) {
      showMessage('لفظ 120 حروف سے بڑا نہیں ہو سکتا۔', 'error');
      return;
    }

    const record = {
      original: selection.original,
      corrected,
      context: '',
      source: 'manual_review',
      createdAt: new Date().toISOString()
    };
    const text = getReviewText();
    const before = text.slice(0, selection.start);
    const after = text.slice(selection.end);
    setReviewText(before + corrected + after);
    closePopup();

    await readyPromise;
    if (remoteReady) {
      try {
        const data = await request('POST', { action: 'save', ...record });
        corrections = normalizeList(data.corrections, 500);
        pending = pending.filter((item) => pairKey(item) !== pairKey(record));
        writePending(pending);
        writeLocalStore(corrections);
        showMessage('تلفظ سائٹ کے database میں محفوظ ہو گیا۔ دوبارہ Generate دبائیں۔', 'ok');
        return;
      } catch (_) {
        remoteReady = false;
      }
    }
    corrections = mergeLists(corrections, [record]);
    writeLocalStore(corrections);
    rememberPending([record]);
    showMessage('تلفظ اس browser میں محفوظ ہے؛ database sync باقی ہے۔ دوبارہ Generate دبائیں۔', 'error');
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

  async function exportJSON() {
    await readyPromise;
    const payload = {
      version: 1,
      format: 'ng-voice-pronunciation-review',
      exportedAt: new Date().toISOString(),
      corrections
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'ng-voice-pronunciation-corrections.json';
    anchor.click();
    URL.revokeObjectURL(url);
  }

  function importJSON(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function () {
      try {
        const payload = JSON.parse(String(reader.result || '{}'));
        const imported = Array.isArray(payload) ? payload : payload.corrections;
        if (!Array.isArray(imported)) throw new Error('Invalid corrections');
        const valid = normalizeList(imported, 200).map((item) => ({ ...item, source: 'json_import' }));
        if (valid.length === 0) {
          showMessage('درست corrections نہیں ملیں۔', 'error');
          return;
        }
        void persistBatch(valid, 'json_import');
      } catch (_) {
        showMessage('JSON فائل درست نہیں ہے۔', 'error');
      }
    };
    reader.readAsText(file);
  }

  async function deleteCorrection(id) {
    await readyPromise;
    if (!remoteReady) {
      showMessage('Database دستیاب نہیں؛ correction حذف نہیں ہوئی۔', 'error');
      return;
    }
    try {
      const data = await request('DELETE', { action: 'delete', id: Number(id) });
      corrections = normalizeList(data.corrections, 500);
      writeLocalStore(corrections);
      showMessage('Correction database سے حذف ہو گئی۔', 'ok');
    } catch (_) {
      showMessage('Correction حذف نہیں ہوئی۔ دوبارہ کوشش کریں۔', 'error');
    }
  }

  async function reset() {
    if (!window.confirm('کیا آپ اس owner کی تمام saved corrections database سے مستقل حذف کرنا چاہتے ہیں؟')) return;
    await readyPromise;
    if (!remoteReady) {
      showMessage('Database دستیاب نہیں؛ server corrections حذف نہیں ہوئیں۔', 'error');
      return;
    }
    try {
      await request('DELETE', { action: 'clear' });
      corrections = [];
      pending = [];
      writeLocalStore([]);
      writePending([]);
      try { localStorage.setItem(MIGRATION_KEY, '1'); } catch (_) { /* optional */ }
      showMessage('تمام corrections database سے حذف ہو گئیں۔', 'ok');
    } catch (_) {
      showMessage('Corrections حذف نہیں ہوئیں۔ دوبارہ کوشش کریں۔', 'error');
    }
  }

  function init() {
    const review = document.getElementById('pronunciationReviewText');
    if (review) review.addEventListener('mouseup', openPopup);
    document.querySelectorAll('[data-diacritic]').forEach((button) => {
      button.addEventListener('click', () => insertDiacritic(button.dataset.diacritic));
    });
    const file = document.getElementById('pronunciationImportFile');
    if (file) file.addEventListener('change', () => importJSON(file.files[0]));
    readyPromise = bootstrap();
    return readyPromise;
  }

  window.NGPronunciation = {
    init,
    ready: () => readyPromise,
    render: setReviewText,
    getCorrectionMemory,
    rememberApprovedText,
    openPopup,
    closePopup,
    saveCorrection,
    deleteCorrection,
    exportJSON,
    getText: getReviewText,
    reset
  };
})();
