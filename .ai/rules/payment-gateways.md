---
paths:
  - 'app/Services/PaymentGateways/*.php'
---

# Payment Gateways

## Webhook verification fails closed
verifyPayment must fail closed: server-side gateway lookup failure returns false, never trust the webhook payload. Stripe and Tabby once had payload-trust fallbacks that let forged paid webhooks confirm orders — covered by WebhookSecurityTest.
