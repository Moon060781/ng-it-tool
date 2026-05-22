<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Us</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .input-error { border-color: #ef4444 !important; }
        .input-success { border-color: #22c55e !important; }
    </style>
</head>
<body class="bg-white p-4">
    <form id="messageForm" class="space-y-4">
        <input type="hidden" name="site_source" value="it.noorgee.com/FU">
        
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Full Name *</label>
            <input type="text" name="name" required placeholder="Enter your full name" 
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-blue-500 outline-none transition-colors">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address *</label>
            <input type="email" name="email" required placeholder="Enter your email" 
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-blue-500 outline-none transition-colors">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Contact Number (Optional)</label>
            <input type="text" name="contact" placeholder="Enter your phone number" 
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-blue-500 outline-none transition-colors">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Subject *</label>
            <input type="text" name="subject" required placeholder="What is this about?" 
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-blue-500 outline-none transition-colors">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Message *</label>
            <textarea name="message" required placeholder="Write your message here..." rows="4"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-blue-500 outline-none transition-colors resize-none"></textarea>
        </div>

        <div id="responseMsg" class="hidden text-sm font-medium p-3 rounded-lg"></div>

        <button type="submit" id="submitBtn" 
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition shadow-lg shadow-blue-100 flex items-center justify-center">
            <span id="btnText">Send Message</span>
        </button>
    </form>

    <script>
        const form = document.getElementById('messageForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const responseMsg = document.getElementById('responseMsg');

        // Real-time validation
        form.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('input', () => {
                if (input.hasAttribute('required') && !input.value.trim()) {
                    input.classList.add('input-error');
                    input.classList.remove('input-success');
                } else if (input.type === 'email' && input.value.trim()) {
                    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (re.test(input.value)) {
                        input.classList.remove('input-error');
                        input.classList.add('input-success');
                    } else {
                        input.classList.add('input-error');
                        input.classList.remove('input-success');
                    }
                } else if (input.value.trim()) {
                    input.classList.remove('input-error');
                    input.classList.add('input-success');
                } else {
                    input.classList.remove('input-error', 'input-success');
                }
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // UI Loading State
            submitBtn.disabled = true;
            btnText.innerHTML = '<span class="loading-spinner"></span> Sending...';
            responseMsg.classList.add('hidden');

            const formData = new FormData(form);
            
            try {
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                responseMsg.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
                
                if (result.status === 'success') {
                    responseMsg.innerText = result.message;
                    responseMsg.classList.add('bg-green-100', 'text-green-700');
                    form.reset();
                    form.querySelectorAll('input, textarea').forEach(i => i.classList.remove('input-success', 'input-error'));
                } else {
                    responseMsg.innerText = result.message;
                    responseMsg.classList.add('bg-red-100', 'text-red-700');
                }
            } catch (error) {
                responseMsg.classList.remove('hidden');
                responseMsg.innerText = 'An error occurred. Please try again later.';
                responseMsg.classList.add('bg-red-100', 'text-red-700');
            } finally {
                submitBtn.disabled = false;
                btnText.innerText = 'Send Message';
            }
        });
    </script>
</body>
</html>
