// This dictionary maps native sounds to phonetic symbols
// You can expand this based on your specific language needs
const phoneticMap = {
    "sh": "ʃ",
    "ch": "tʃ",
    "th": "θ",
    "aa": "ɑː",
    // Add your native language specific mappings here
};

const inputArea = document.getElementById('nativeInput');
const outputArea = document.getElementById('phoneticOutput');

inputArea.addEventListener('input', () => {
    let text = inputArea.value.toLowerCase();
    
    // Apply mappings
    Object.keys(phoneticMap).forEach(key => {
        const regex = new RegExp(key, 'g');
        text = text.replace(regex, phoneticMap[key]);
    });
    
    outputArea.value = text;
});

function copyToClipboard() {
    outputArea.select();
    document.execCommand('copy');
    alert("Phonetic text copied!");
}