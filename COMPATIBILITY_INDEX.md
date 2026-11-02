# Compatibility index

Last synchronized from confirmed workspace evidence: 2 November 2026.

Current release candidate: `3.0.2`. The operator accepted its local Mautic 6.0.10 sandbox UI on 2 November 2026; publication and downstream installation are in progress. Declared support: Mautic 6.x, 7.x; PHP 8.3 is documented but not declared in Composer.

| Mautic | Status | Confirmed scope |
| --- | --- | --- |
| 5.2.10 | — | Outside declared range |
| 6.0.9 | ? | `2.0.5` fresh container/forms and live catalog inspection; `3.0.2` downstream installation pending |
| 6.0.10 | UI | `3.0.2` local sandbox: standard integration layout, profile create/save, quick-start selector, and persisted Off/On runtime suppression accepted by operator, 2 Nov 2026; provider call unavailable at configured `localhost:4000` |
| 7.1.3 | ? | `2.0.5` fresh container/forms, 6 Sep 2026; changed `3.0.2` configuration/runtime behavior not rerun |
| 7.2.0 | ? | `2.0.5` fresh container/forms, 6 Sep 2026; changed `3.0.2` configuration/runtime behavior not rerun |

The `3.0.2` provider/Mac Mini runtime path was not accepted by this UI check because the sandbox profile points to an unavailable `localhost:4000`; no model-quality or small-model Mautic 6/7 claim is made. Update this file and the workspace `../../COMPATIBILITY_INDEX.md` row with every plugin-related change or review.
