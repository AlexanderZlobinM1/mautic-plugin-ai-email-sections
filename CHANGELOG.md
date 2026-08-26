# Changelog

## 1.1.2 - 2026-08-26

- Make the MCC plugin catalog the primary installation path and remove the
  unsupported Composer installation procedure from the operator guide.

## 1.1.1 - 2026-08-26

- Declare and verify compatibility with Mautic 6 in addition to Mautic 7.
- Keep the same integration, builder, provider and security behavior on both
  supported Mautic majors.

## 1.1.0 - 2026-08-26

- Add provider presets for OpenAI, Google Gemini, DeepSeek, Qwen, Groq and
  OpenRouter while preserving existing OpenAI-compatible configurations.
- Add a native Google Gemini `generateContent` client with server-side API-key
  handling.
- Add complete Russian (`ru`) and Serbian (`sr_RS`) translations for plugin
  settings and the email builder UI.
- Document provider defaults and region-specific Qwen endpoints.
