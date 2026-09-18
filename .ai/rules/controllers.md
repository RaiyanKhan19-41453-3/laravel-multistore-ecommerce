---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Use nested input for dotted validation rules
Validate nested input (store: {currency}) with dot rules, never flat dotted JSON keys. validated() uses data_get which drops literal "store.currency" keys, so nothing saves and no errors appear. Inertia useForm supports nested objects with setData('store.currency', v) paths. Tests must use putJson with nested arrays (PHP mangles dots to underscores in form-encoded bodies).
