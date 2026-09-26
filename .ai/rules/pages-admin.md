---
paths:
  - 'resources/js/pages/admin/**'
---

# Pages Admin

## Spoof PUT for file upload forms
Forms with file inputs must submit via post() with transform() adding _method PUT, never put(). PHP discards multipart bodies on real PUT/PATCH so uploads silently never arrive (also bit profile photo and store logo forms).
