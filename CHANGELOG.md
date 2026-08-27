# Changelog

## 2.0.3 - 2026-08-27

- Interpret requests about opening, visiting, viewing, browsing, or accessing a
  site as page-hit behavior and keep the live URL filters in the model catalog.
- Prevent the segment assistant from substituting a company website profile
  field for a requested page visit.
- Normalize an AND-only request into one Mautic filter group when a small model
  incorrectly returns separate OR groups, while preserving explicit OR logic.

## 2.0.2 - 2026-08-27

- Keep the new-segment assistant available after Mautic replaces admin content
  during internal navigation instead of requiring a full page reload.
- Load the segment runtime across the administration UI while retaining its
  strict new-segment-only rendering guard.
- Refresh plugin asset versions so browsers no longer reuse the 2.0.0 scripts
  after a plugin update.

## 2.0.1 - 2026-08-27

- Rank the live segment-field catalog against the operator request before it is
  sent to the model, reducing local-model latency and unrelated field guesses.
- Keep final validation against the complete live Mautic catalog, so catalog
  reduction cannot permit unsupported fields or operators.

## 2.0.0 - 2026-08-27

- Add an AI assistant to the new-segment form that converts natural-language
  audience descriptions into native Mautic filter controls.
- Limit segment assistance to creation: it never appears on edit pages and
  never saves, publishes, rebuilds, or executes a segment.
- Build the model catalog from the exact fields and operators rendered by the
  current Mautic, including filters contributed by installed plugins.
- Validate every generated object, field, and operator against that live
  catalog before filling the form, and roll back the inserted controls if a
  choice or lookup value cannot be resolved.
- Use Gemini's structured JSON response mode for segment plans and retry one
  rejected response without changing normal email-section generation.
- Wait for Mautic's native asynchronous filter controls before applying values,
  preventing a late core response from overwriting AI-prepared conditions.
- Express Mautic logic as OR groups containing AND conditions and document
  relative date semantics for requests such as "within the last 10 days".
- Rename the displayed integration to Sales Snap AI Assistant while retaining
  the existing integration identity and all email-section functionality.

## 1.1.6 - 2026-08-27

- Adapt the configuration form immediately when the provider receives focus or
  changes, without disabling or clearing hidden settings.
- Hide the manual endpoint for provider presets while keeping it available for
  OpenAI-compatible and local models.
- Hide temperature for Anthropic because its native client does not send that
  parameter, without clearing any stored values when providers are switched.
- Set Alexander Zlobin as the maintainer under the Sales Snap brand while
  retaining the original Bloomidea attribution in the package metadata.

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
