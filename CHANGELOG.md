# Changelog

## 1.1.5 - 2026-08-26

- Use `gemini-3.6-flash` as the Google Gemini default because Google no longer
  makes the previous `gemini-2.5-flash` model available to new API users.
- Preserve explicitly configured model names for existing installations.

## 1.1.4 - 2026-08-26

- Release the PHP session lock before the long-running AI provider request so
  an interrupted generation cannot block the user's following Mautic requests.

## 1.1.3 - 2026-08-26

- Keep only the manual archive installation procedure in the operator guide.

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
