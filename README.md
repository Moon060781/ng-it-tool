---

# 📂 BRAND DOCUMENTATION: NOORGEE DIGITAL NETWORK

## 1. 🌐 MAIN TOOL HUB (it.noorgee.com)

* **Core Idea:** یہ مرکزی پورٹل (Main Hub) ہے جہاں تمام سب-پیجز (ٹولز) کو **iFrame** کے ذریعے ایک ہی جگہ ڈسپلے اور کنٹرول کیا جاتا ہے۔
* **Root Structure:** ہوم پیج پر index.php اور مرکزی deploy.php موجود ہے جو پورے نیٹ ورک کی گٹ ہب سرگرمیوں کو سنک کرتی ہے۔

### 🛠️ SUB-PAGES ARCHITECTURE (ٹولز کی تفصیل اور ڈائریکٹریز)

| Sub-page URL | Tool Name & Description | Core Files & Assets |
| --- | --- | --- |
| **[it.noorgee.com/FU]()** | **Fuel Calculator:** آن لائن رائیڈرز اور فیول کاؤنٹنگ کے لیے ڈیٹا بیس ٹول۔ | admin.php, api.php, auth.php, deploy.php, schema.sql, tasks/ |
| **[it.noorgee.com/FX]()** | **Screen Hand Gestures:** ہینڈ گیسچر کنٹرول اور اسکرین انٹرایکشن۔ | index.html, main.js |
| **[it.noorgee.com/IN]()** | **InPage Online Editor:** ویب پر مبنی ان پیج اردو سافٹ ویئر ایڈیٹر۔ | index.html |
| **[it.noorgee.com/LW]()** | **Legal Case Evaluator:** قانونی کیسز کی جانچ اور ایویلویشن کا خودکار نظام۔ | index.html |
| **[it.noorgee.com/NM]()** | **Name Generator:** معنی اور تفصیل کے ساتھ نام تجویز کرنے والا ٹول۔ | index.php |
| **[it.noorgee.com/NW]()** | **News Ticker Writer:** جیو نیوز فارمیٹ پر مبنی خودکار نیوز ٹکر رائٹر۔ | index.php, config.js, deploy.php, reporters.json, ticker_inst.md |
| **[it.noorgee.com/PA]()** | **IPA Phonetic Tool:** آرٹیفیشل انٹیلیجنس (AI) وائس اوور کے لیے بالکل درست ٹیکسٹ لکھنے کا صوتیاتی ٹول۔ | index.html, script.js, style.css |
| **[it.noorgee.com/PM]()** | **AI Prompt Maker:** تمام انواع (Genres) کے لیے ایڈوانسڈ AI پرامپٹ رائٹر۔ | index.html, README.md |
| **[it.noorgee.com/TW]()** | **Task Wheeler Selector:** کاموں کی تقسیم اور اسائنمنٹس کے لیے وہیل سلیکٹر۔ | task_wheel.php, load_assignments.php, index.php |
| **[it.noorgee.com/UR]()** | **Online Urdu Keyboard:** ٹیکسٹ ایڈیٹر کے ساتھ مکمل آن لائن اردو کی بورڈ۔ | index.php, test_db.php |

---

## 2. 🚀 DEPLOYMENT & GIT HUB INTEGRATION

* **Repository Link:** [https://github.com/grapheart247/test-pm.git]()
* **Local Root Path:** /home/noorgeec/[it.noorgee.com/PM]() (cPanel Git™ Version Control کے ساتھ منسلک)
* **The deploy.php Mechanism:** روٹ ڈائریکٹری اور سب-فولڈرز (FU/, NW/) میں موجود deploy.php فائلوں کا بنیادی مقصد گٹ ہب پر ہونے والی تبدیلیوں (Commits) کو لسٹ اپ کرنا اور لائیو سرور پر کوڈ کو اپ ٹو ڈیٹ (Deploy) کرنا ہے۔

### 🔒 CREDENTIALS SECURITY & CONFIG:

حساس ڈیٹا اور ڈیٹا بیس کی معلومات کو محفوظ رکھنے کے لیے مرکزی کنفیگریشن فائل روٹ سے باہر cred/config.php میں رکھی گئی ہے، جبکہ لوکل فولڈرز میں گٹ ہب سے پروٹیکشن کے لیے .env اور .gitignore کا اصول لاگو ہے۔
