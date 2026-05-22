// Security: Separate credentials file
const API_CONFIG = {
    // اپنی نئی API Key یہاں ڈالیں جو آپ نے ابھی بنائی ہے
    GEMINI_API_KEY: "AIzaSyAUL5xzmq-cKV5x5QWSqAo_JFrizc1Vu4w",

    // درست اور مستحکم API URL (Gemini 1.5 Flash سب سے تیز ہے)
    API_URL: "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent",

    // Database Reference (For your PHP scripts)
    DATABASE: {
        name: "noorgeec_it",
        user: "noorgeec_nw",
        pass: "Tl_Nw@02-01",
        table: "ur_ai_brain" // ہم نے ٹیبل کا نام ur_ai_brain رکھا تھا سیکھنے کے لیے
    }
};
