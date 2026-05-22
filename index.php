<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>it.noorgee.com - Your Suite of Online Tools</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;family=Cookie&amp;display=swap" rel="stylesheet" />
    
    <style type="text/css">
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; overflow: hidden; }
        
        /* Ensure HTML and Body use full viewport height */
        html, body { height: 100%; }
        
        /* Set main container height to fill the viewport */
        #app-container {
            display: flex;
            flex-direction: column;
            height: 100vh; 
        }
        
        /* Container for the 3-column layout */
        #main-sections {
            flex-grow: 1; 
            display: flex;
            overflow: hidden; 
            position: relative;
        }

        /* Base styles for fixed layout parts */
        #ads-left, #content-area {
            height: 100%; 
            flex-shrink: 0; 
            overflow: hidden; 
        }

        /* SIDEBAR OVERLAY LOGIC */
        #sidebar {
            position: absolute;
            left: 8%; /* Default Desktop */
            top: 0;
            height: 100%;
            z-index: 20;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s;
            box-sizing: border-box;
            overflow-x: hidden;
            overflow-y: auto;
            border-right: 2px solid #e5e7eb;
            background-color: rgba(243, 244, 246, 0.95);
            backdrop-filter: blur(4px);
        }
        
        /* IFRAME ZOOM REDUCTION (90% Zoom = 0.9 Scale) */
        #tool-iframe {
            display: block; 
            width: 111.11%; 
            height: 111.11%; 
            border: none;
            transform: scale(0.9);
            transform-origin: 0 0;
        }

        .vertical-text {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
        }

        .tool-label { white-space: nowrap; }
        .tool-link { justify-content: start; }
        .sidebar-collapsed-text { display: block; }

        /* Landing Page Grid Styles */
        #landing-page {
            width: 100%;
            height: 100%;
            overflow-y: auto;
            padding: 2rem;
            background: radial-gradient(circle at top right, #f8fafc, #f1f5f9);
        }

        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* NGO Ad Specific Constraints */
        #bottom-ad-bar {
            height: 170px; /* Default Desktop Height */
        }
        .ngo-ad-container {
            width: 700px; 
            max-width: 100%; 
            height: 100%;
            background: linear-gradient(to right, #0f172a, #1e293b);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .bmc-vertical-wrapper {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 160px;
        }
        .bmc-vertical-wrapper a {
            transform: rotate(-90deg) scale(0.85);
            transform-origin: center;
            white-space: nowrap;
            display: block !important;
        }

        /* --- RESPONSIVE ADJUSTMENTS START HERE --- */

        /* 1. Compact Desktop / Tablet (Width < 1024px) */
        @media (max-width: 1024px) {
            /* Reduce Left Ad Width */
            #ads-left {
                width: 60px !important;
                padding: 10px 2px !important;
            }
            
            /* Align sidebar to new ad width */
            #sidebar {
                left: 60px !important; 
            }

            /* Expand content area to fill space */
            #content-area {
                width: calc(100% - 60px) !important;
            }

            /* === SINGLE LINE BOTTOM AD FIX === */
            #bottom-ad-bar {
                height: 45px !important; /* Forced 1 line height */
                min-height: 45px !important;
                padding: 0 !important;
                background: #0f172a !important;
            }

            /* Hide the container box effects to fit the strip */
            .ngo-ad-container {
                width: 100% !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
                border: none !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            /* Hide Columns 1, 2, 3 completely */
            .ngo-col:nth-child(1),
            .ngo-col:nth-child(2),
            .ngo-col:nth-child(3) {
                display: none !important;
            }
            
            /* Style Column 4 (CTA) as horizontal banner */
            .ngo-col:nth-child(4) {
                flex: 1 !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: center !important;
                background: transparent !important;
                padding: 0 !important;
                gap: 15px !important;
                height: 100% !important;
            }

            /* Adjust CTA Text */
            .ngo-col:nth-child(4) p.text-blue-300 {
                margin: 0 !important;
                font-size: 11px !important;
                white-space: nowrap !important;
                display: inline-block !important;
            }
            
            /* Adjust CTA Button */
            .ngo-col:nth-child(4) a {
                width: auto !important;
                padding: 4px 16px !important;
                font-size: 10px !important;
                margin: 0 !important;
                display: inline-block !important;
            }

            /* Hide the small text below button */
            .ngo-col:nth-child(4) p.text-slate-500 {
                display: none !important;
            }
        }

        /* 2. Mobile (Width < 768px) */
        @media (max-width: 768px) {
            #main-sections { flex-direction: column; }
            
            #ads-left { 
                width: 100% !important; 
                height: 70px !important; 
                border-right: none;
                border-bottom: 2px solid #e5e7eb;
                padding: 0.5rem;
                flex-direction: row;
                justify-content: space-around;
            }
            #ads-left .vertical-text { display: none; }
            .bmc-vertical-wrapper { height: auto; width: auto; }
            .bmc-vertical-wrapper a { transform: none; scale: 0.9; }

            #sidebar {
                position: relative;
                left: 0 !important;
                width: 100% !important; 
                height: auto;
                max-height: 40vh; 
                overflow-y: auto;
                border-right: none;
                border-bottom: 2px solid #e5e7eb;
            }

            #content-area { 
                width: 100% !important; 
                height: 45vh; 
            }

            #tool-iframe {
                width: 100%;
                height: 100%;
                transform: none;
            }

            /* Bottom Ad - Keep 1-line style from 1024px query by inheriting, or override if column stack preferred.
               Given "reduce those both area" request, keeping it 1-line on mobile is safer for screen real estate. */
            #bottom-ad-bar {
                height: 50px !important;
                padding: 0.5rem !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

<div id="app-container">
    
    <!-- HEADER -->
    <header class="bg-white shadow-md p-3 flex items-center justify-between z-30">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-bold text-blue-600 truncate cursor-pointer" onclick="location.reload()">it.noorgee.com</h1>
            <button id="preview-tool-btn" class="flex items-center space-x-1 px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded shadow transition transform active:scal[...]
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <span>Preview</span>
            </button>
        </div>
        <div class="hidden md:block text-base font-medium text-gray-700 bg-gray-100 px-4 py-1 rounded-full border border-gray-300 shadow-inner" id="current-tool-display">
            Select a Tool to Begin
        </div>
        <nav class="hidden md:flex space-x-4 text-sm font-medium">
            <a class="text-gray-600 hover:text-blue-600 transition" href="/">Home</a> 
            <a class="text-gray-600 hover:text-blue-600 transition" href="#">About</a> 
            <a class="text-gray-600 hover:text-blue-600 transition" href="#">Contact</a> 
            <a class="text-blue-600 hover:text-blue-700 transition font-semibold" href="https://noorgee.com" target="_blank">Partner Site</a>
        </nav>
        <button class="md:hidden p-2 text-gray-600 rounded-lg hover:bg-gray-100 transition" id="menu-toggle">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" stroke-linejoin=[...]
            </svg>
        </button>
    </header>

    <!-- MOBILE MENU -->
    <nav class="hidden md:hidden bg-white shadow-lg border-t border-gray-200 p-4 space-y-2 z-30" id="mobile-menu">
        <a class="block text-gray-600 hover:text-blue-600 transition" href="/">Home</a> 
        <a class="block text-gray-600 hover:text-blue-600 transition" href="#">About</a> 
        <a class="block text-gray-600 hover:text-blue-600 transition" href="#">Contact</a> 
    </nav>

    <!-- MAIN SECTIONS -->
    <div class="flex flex-grow overflow-hidden" id="main-sections">
        
        <!-- LEFT SPONSORED SIDEBAR -->
        <div class="bg-gray-200 border-r border-gray-300 flex flex-col items-center justify-between py-6 px-1 overflow-hidden" id="ads-left" style="width: 8%;">
            
            <!-- UPPER: ADS -->
            <div class="flex flex-col items-center w-full space-y-4">
                <a href="https://buymeacoffee.com/grapheart365" target="_blank" class="flex flex-col items-center bg-[#FFDD00] rounded-lg p-2 shadow-sm border border-black/10 hover:scale-105 tran[...]
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="mb-1"><path d="M17 8H19C20.1046 8 21 8.89543 21 10V12C21 13.1046 20.1046 [...]
                    <div class="text-[10px] font-black text-black uppercase text-center leading-none" style="font-family: 'Cookie', cursive;">NG Coffee</div>
                </a>

                <a href="https://www.patreon.com/posts/write-right-via-146098926?source=storefront" target="_blank" class="flex flex-col items-center bg-blue-600 rounded-lg p-2 shadow-sm border b[...]
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg" class="mb-1"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6z" fill="w[...]
                    <div class="text-[9px] font-bold text-white uppercase text-center leading-none">NG Shop</div>
                </a>
            </div>

            <!-- MIDDLE: FAN TEXT -->
            <div class="flex-grow flex flex-col items-center justify-center space-y-8 my-4">
                <span class="text-[9px] font-bold text-blue-600 vertical-text uppercase tracking-widest bg-blue-50 py-2 rounded-full px-1">To my amazing fans:</span>
                <span class="text-[10px] font-medium text-gray-600 vertical-text text-center italic leading-relaxed">"Your support keeps the code running and the tools growing."</span>
                <span class="text-[9px] font-bold text-gray-400 vertical-text uppercase tracking-tighter">Stay Inspired</span>
            </div>

            <!-- LOWER: PATREON -->
            <div class="flex flex-col items-center w-full pb-4">
                <a href="https://www.patreon.com/c/NoorGee" target="_blank" class="flex flex-col items-center bg-[#f96854] rounded-lg p-2 shadow-sm border border-black/10 hover:scale-105 transiti[...]
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg" class="mb-1"><circle cx="14.5" cy="9.5" r="5.5" fill="white"/><rect x="4" y="4"[...]
                    <div class="text-[9px] font-black text-white uppercase text-center leading-none">Patreon</div>
                </a>
                <div class="mt-2">
                    <span class="text-[8px] font-bold text-gray-500 vertical-text uppercase tracking-widest">NG Community</span>
                </div>
            </div>
        </div>

        <!-- OVERLAY SIDEBAR -->
        <aside class="p-4" id="sidebar">
            <h2 class="text-xs font-bold mb-4 text-gray-500 uppercase tracking-widest sidebar-collapsed-text">Tools Suite</h2>
            <div class="space-y-2">
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    🤖<span class="tool-label ml-2 sidebar-collapsed-text">Prompt Writer</span>
                </button>
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    📰<span class="tool-label ml-2 sidebar-collapsed-text">News Format Writer</span>
                </button>
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    ⌨️<span class="tool-label ml-2 sidebar-collapsed-text">Urdu Keyboard</span>
                </button>
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    ✨<span class="tool-label ml-2 sidebar-collapsed-text">Control FX</span>
                </button>
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    🆔<span class="tool-label ml-2 sidebar-collapsed-text">Name Gen Tool</span>
                </button>
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    🎡<span class="tool-label ml-2 sidebar-collapsed-text">Task Assigner</span>
                </button>
                <button class="tool-link w-full text-left p-3 rounded-lg font-medium bg-white hover:bg-blue-50 transition text-gray-700 data-[active='true']:bg-blue-600 data-[active='true']:text-[...]
                    📂<span class="tool-label ml-2 sidebar-collapsed-text">Fuel Consumption</span>
                </button>
            </div>
        </aside>

        <!-- CONTENT AREA -->
        <main class="p-0 bg-white overflow-hidden relative" id="content-area" style="width: 92%;">
            <div id="landing-page" class="z-10">
                <div class="max-w-4xl mx-auto">
                    <div class="text-center mb-10">
                        <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">Online Tools Directory</h2>
                        <p class="mt-4 text-lg text-gray-600">Select a tool below to get started. All tools are free to use.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/PM')">
                            <div class="text-4xl mb-4 text-blue-600">🤖</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Prompt Writer</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Optimize your AI prompts for Gemini and ChatGPT.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/NW')">
                            <div class="text-4xl mb-4 text-blue-600">📰</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">News Writer</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Convert raw notes into professional news formats.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/UR')">
                            <div class="text-4xl mb-4 text-blue-600">⌨️</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Urdu Keyboard</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Type in Urdu using phonetic English mapping.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/FX')">
                            <div class="text-4xl mb-4 text-blue-600">✨</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Control FX</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Hand-tracking controls for experimental visual effects.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/NM')">
                            <div class="text-4xl mb-4 text-blue-600">🆔</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Name Gen</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Generate creative business and project names.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/TW')">
                            <div class="text-4xl mb-4 text-blue-600">🎡</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Task Assigner</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Fairly distribute tasks among team members.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/FU')">
                            <div class="text-4xl mb-4 text-blue-600">📂</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Measure Fuel Expense </h3>
                            <p class="text-sm text-gray-500 leading-relaxed">For Calculate fuel consumption as online ride partner (like bykea, yango etc.).</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/IN')">
                            <div class="text-4xl mb-4 text-blue-600">🔧</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Online InPage Software</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Web-based page editing and design tool for professional layouts.</p>
                        </div>
                        <div class="tool-card bg-white p-6 rounded-xl border border-gray-200 cursor-pointer transition-all duration-200 hover:border-blue-500" onclick="loadTool('/AI')">
                            <div class="text-4xl mb-4 text-blue-600">🎨</div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">AI Generator</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">Generate AI images, videos, and text content instantly.</p>
                        </div>
                    </div>
                </div>
            </div>
            <iframe frameborder="0" id="tool-iframe" class="hidden" sandbox="allow-forms allow-scripts allow-same-origin allow-popups" title="Online Tool Frame"></iframe>
        </main>
    </div>

    <!-- BOTTOM NGO AD BAR -->
    <div id="bottom-ad-bar" class="w-full bg-slate-950 border-t border-slate-700 z-10 text-white overflow-hidden flex items-center justify-center py-2">
        <div class="ngo-ad-container flex flex-row items-stretch overflow-hidden border border-slate-700/50">
            <!-- Col 1 -->
            <div class="flex-[1.2] flex flex-col items-center justify-center px-4 border-r border-slate-800/60 ngo-col text-center">
                <div class="h-16 w-16 mb-2 bg-white rounded-lg p-1.5 shadow-lg flex items-center justify-center">
                    <img src="https://kwa.com.pk/images/KWA_logo_final_--removebg-preview.png" alt="KWA Logo" class="h-full w-full object-contain">
                </div>
                <div class="flex flex-col">
                    <h3 class="text-blue-400 font-bold text-[10px] uppercase tracking-widest leading-none">Karsaziyan</h3>
                    <p class="text-[14px] font-extrabold text-white leading-tight">Welfare Association</p>
                    <p class="text-[9px] text-slate-500 mt-1">www.kwa.com.pk</p>
                </div>
            </div>
            <!-- Col 2 -->
            <div class="flex-[2] flex flex-col justify-center px-6 border-r border-slate-800/60 bg-slate-900/50 ngo-col">
                <div class="flex items-center space-x-2 mb-2">
                    <span class="bg-emerald-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold text-white shadow-sm">Education</span>
                    <span class="bg-blue-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold text-white shadow-sm">Health</span>
                </div>
                <h4 class="text-slate-100 font-bold text-[13px] mb-1">Empowering Humanity</h4>
                <p class="text-[11px] text-slate-400 leading-relaxed">Providing quality education grants and accessible healthcare.</p>
            </div>
            <!-- Col 3 -->
            <div class="flex-1 flex flex-col justify-center px-5 border-r border-slate-800/60 ngo-col">
                <h5 class="text-blue-500 font-bold text-[10px] uppercase tracking-widest mb-3">Learn More</h5>
                <div class="flex flex-col space-y-2">
                    <a href="https://kwa.com.pk/about.html" target="_blank" class="text-[11px] text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center group"><span cl[...]
                    <a href="https://kwa.com.pk/activities.html" target="_blank" class="text-[11px] text-slate-300 hover:text-white hover:translate-x-1 transition-all flex items-center group"><sp[...]
                </div>
            </div>
            <!-- Col 4 -->
            <div class="flex-1 flex flex-col items-center justify-center px-4 bg-blue-900/30 ngo-col text-center">
                <p class="text-[11px] font-bold text-blue-300 mb-3 italic">Join Our Cause</p>
                <a href="https://kwa.com.pk/donation.html" target="_blank" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-[12px] font-black py-3 rounded shadow-xl border border-white[...]
                <p class="text-[9px] text-slate-500 mt-3 leading-tight font-medium uppercase tracking-tighter">Small Acts, Big Impact</p>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="bg-white border-t border-gray-200 text-gray-500 p-2 text-center text-[10px] uppercase tracking-tighter z-10">
        it.noorgee.com &copy; <?php echo date('Y'); ?>. Professional Tools Infrastructure. updated: 22-May-2026
    </footer>
</div>

<script>
    const iframe = document.getElementById('tool-iframe');
    const landingPage = document.getElementById('landing-page');
    const previewBtn = document.getElementById('preview-tool-btn');
    const links = document.querySelectorAll('.tool-link');
    const currentToolDisplay = document.getElementById('current-tool-display');
    const mobileMenuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    function loadTool(url) {
        landingPage.classList.add('hidden');
        iframe.classList.remove('hidden');
        previewBtn.classList.remove('hidden');
        iframe.src = url;
        setActiveTool(url);
    }

    function setActiveTool(url) {
        links.forEach(link => {
            const linkUrl = link.getAttribute('data-tool-url');
            const isActive = linkUrl === url;
            link.setAttribute('data-active', isActive);
            if (isActive) {
                const label = link.querySelector('.tool-label').textContent;
                if(currentToolDisplay) currentToolDisplay.textContent = `Active Tool: ${label}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarLabels = document.querySelectorAll('.sidebar-collapsed-text');
        const sidebarTitle = document.querySelector('#sidebar h2');
        const W_COLLAPSED = 5;  
        const W_EXPANDED = 18;  

        function setSidebarWidth(sidebarW) {
            if (window.innerWidth < 768) return; 
            sidebar.style.width = `${sidebarW}%`;
            const isExpanded = sidebarW > W_COLLAPSED;
            sidebarLabels.forEach(el => { el.style.display = isExpanded ? 'inline' : 'none'; });
            if(sidebarTitle) sidebarTitle.style.display = isExpanded ? 'block' : 'none';
            sidebar.style.padding = isExpanded ? '1rem' : '0.5rem';
        }
        
        sidebar.addEventListener('mouseenter', () => setSidebarWidth(W_EXPANDED));
        sidebar.addEventListener('mouseleave', () => setSidebarWidth(W_COLLAPSED));

        if (window.innerWidth >= 768) setSidebarWidth(W_COLLAPSED);

        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                loadTool(link.getAttribute('data-tool-url'));
            });
        });

        if (previewBtn) {
            previewBtn.addEventListener('click', () => {
                if (iframe.src) window.open(iframe.src, '_blank');
            });
        }
        
        if(mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    });

    window.loadTool = loadTool;
</script>

<!-- Buy Me A Coffee Widget -->
<script data-name="BMC-Widget" data-cfasync="false" src="https://cdnjs.buymeacoffee.com/1.0.0/widget.prod.min.js" data-id="grapheart365" data-description="Support me on Buy me a coffee!" data-mes[...]

</body>
</html>
