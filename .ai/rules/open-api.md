---
paths:
  - 'src/OpenApi/**'
---

# Open Api

## Generate a public sibling for internal endpoints
Mark internal operations with #[SpecEndpoint(internal: true)]. The normal OpenAPI document remains complete. A file-path generation containing internal operations also writes a .public.json sibling with those operations removed; mixed paths keep public methods, fully internal paths disappear, stdout stays complete, and no internal-only document is generated.
