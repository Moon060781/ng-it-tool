-- Populating ai_vault_keys with placeholders for official API keys
-- The user should replace these placeholders with actual keys manually in the database.

INSERT INTO ai_vault_keys (key_name, platform_name, model_target, api_key, custom_notes, is_active) VALUES
('Google Gemini Official', 'Google Gemini', 'gemini-1.5-flash', 'YOUR_GOOGLE_GEMINI_KEY', 'Official Google AI Studio Key - Project 992127088254', 1),
('Groq Official', 'Groq Cloud', 'llama3-8b-8192', 'YOUR_GROQ_KEY', 'Official Groq Cloud Key for fast text processing', 1),
('OpenRouter Official', 'OpenRouter', 'google/gemini-2.0-flash-exp:free', 'YOUR_OPENROUTER_TOKEN', 'Official OpenRouter Token for multi-model access', 1),
('Together AI Official', 'Together AI', 'stabilityai/stable-diffusion-xl-base-1.0', 'YOUR_TOGETHER_AI_KEY', 'Official Together AI Key for Image Generation', 1),
('Hugging Face Official', 'Hugging Face', 'runwayml/stable-diffusion-v1-5', 'YOUR_HUGGING_FACE_TOKEN', 'Official Hugging Face Read Token for Open Source Models', 1),
('Livepeer Official', 'Livepeer Studio', 'video-transcoding', 'YOUR_LIVEPEER_KEY', 'Official Livepeer Studio Key for Video Workflows', 1);
