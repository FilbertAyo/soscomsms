# Snippe payments for Soscom SMS

Reference for integrating [Snippe](https://snippe.sh/) into this app. Source of truth is the [2026-01-25 API docs](https://docs.snippe.sh/docs/2026-01-25). Read this before writing payment code.

Soscom today sells SMS credits with a manual Lipa namba flow: the customer requests a quote, an admin checks the money, then `PaymentService` credits the wallet. Snippe replaces that confirmation step. The quote still comes from our pricing tiers. Snippe only collects the shillings and tells us when the customer has paid.

## What you need from Snippe

Log in at [https://snippe.sh/login](https://snippe.sh/login) (dashboard: [https://snippe.sh/dashboard](https://snippe.sh/dashboard)).

You already paid the TZS 100,000 setup fee and verified the business. Verification does not create an API key. You still have to create the key and copy the webhook secret yourself. Both values are shown in the dashboard. Do not paste either one into chat, email, or git. When implementation starts, they go only in server environment variables.

| What | Where in Snippe | What to select or copy | Env var we will use |
| --- | --- | --- | --- |
| API key | **Settings → API Keys → Create API Key** | Scopes `collection:read` and `collection:create` only. The key starts with `snp_` and is shown **once**. | `SNIPPE_API_KEY` |
| Webhook signing secret | **Settings → Webhook Secret** | Copy the existing secret. It is created automatically and starts with `whsec_`. Do not click regenerate unless the old secret is compromised. | `SNIPPE_WEBHOOK_SECRET` |
| Public HTTPS webhook URL | Not registered in the dashboard | We send it on every payment as `webhook_url`. Production target: `https://sms.soscom.co.tz/api/webhooks/snippe` | Derived from `APP_URL` |
| Payment profile (optional) | **Settings → Payment Profiles → Create Profile** | Only if we later use hosted checkout. Branding, logo, color, locale `en` or `sw`. Copy the profile id (`prof_...`). | `SNIPPE_PROFILE_ID` |

Create **two keys** if you have a staging site and production: one for each, same two scopes. Never use the production key on a laptop.

Do **not** enable `disbursement:read` or `disbursement:create`. Those scopes send money out of the Snippe balance. Buying SMS credits only collects money in.

There is no sandbox. The WooCommerce guide states this directly: test with a real payment of at least **TZS 500**, then turn the flow on for customers. Our smallest package is 100 SMS at TZS 25, so TZS 2,500, which is above that minimum.

## How a wallet top-up should work

Use the **Payments API** (USSD push), not payouts and not a public payment link.

1. The customer picks a quantity. The server prices it with the existing pricing tiers. The browser never sends the amount we charge.
2. The customer confirms the Tanzanian mobile number that will receive the PIN prompt. Their account phone can pre-fill this, but the money may sit on a different line.
3. Our server calls `POST https://api.snippe.sh/v1/payments` with that amount, that phone, and our payment id in `metadata`.
4. Snippe pushes a USSD prompt to Airtel Money, M-Pesa, Mixx by Yas, or Halotel. The customer enters their PIN on their own phone. We never see the PIN.
5. Snippe `POST`s `payment.completed` to our webhook. We verify the signature, check the amount matches the row we stored, then credit SMS inside the same locked transaction we already use for admin confirmation.
6. The buy screen also polls our own status endpoint, which asks Snippe `GET /v1/payments/{reference}` if the webhook is late. The redirect or the browser is never proof of payment.

Hosted checkout (`POST /api/v1/sessions`) is a later option. It sends the customer to `snippe.me` to type their number. It is useful if USSD push from our form is awkward. It is not required for the first version. Payouts are out of scope: they move Soscom's Snippe balance to a phone or bank, which is a withdrawal, not a credit purchase.

## Security rules for this integration

These are the rules that make the feature safe. Implementation has to follow all of them.

- The API key and webhook secret stay on the server. They never go to React, logs, or the repository. `.env` is already gitignored.
- Credit the wallet only when a webhook signature verifies **and** `data.amount.value` equals the `amount_tzs` we stored **and** `data.amount.currency` is `TZS` **and** status is `completed`. Then lock the payment row before writing credits, the same way admin confirm already does.
- Verify `X-Webhook-Signature` as hex HMAC-SHA256 of `{X-Webhook-Timestamp}.{raw body}` using the webhook secret. Compare with `hash_equals`. Reject timestamps older than 5 minutes. Use the raw body bytes. Re-encoding JSON breaks the signature.
- The same event can arrive more than once (Snippe retries). Deduplicate on the event `id`. If that payment is already paid, return HTTP 200 and do not credit again.
- Return 2xx within 30 seconds. Snippe retries on anything else, up to 5 attempts (immediate, then 3, 6, 12, and 24 minutes), then abandons the webhook.
- The webhook route is public and must skip CSRF, because Snippe cannot send a Laravel token. The signature is the authentication.
- Store the Snippe `reference` when we create the payment. Trust that stored reference, not an amount or user id posted by the browser.
- `Idempotency-Key` is required on create, must be **30 characters or fewer**, and is cached for 24 hours. A longer key comes back as HTTP 500 `PAY_001`, which looks like a processor failure. Same key plus a different body returns HTTP 422.
- Phone numbers must be Tanzanian mobiles. Snippe accepts `2557XXXXXXXX` and `+2557XXXXXXXX`. We should normalise `07...` before sending.
- Direct mobile payments require `customer.firstname`, `customer.lastname`, and `customer.email`. Our users have a name and email. Split the name; if there is no last name, send a single placeholder last name rather than omitting the field.
- Minimum charge is TZS 500. Only `TZS` works. Amounts are integers, not decimals.
- A direct payment expires after **4 hours** if the customer does not approve it (`expires_at` on the create response). Our current Lipa namba hold is 72 hours. Snippe payments must use the shorter window.
- Snippe's fee is taken from the settlement, not added on top unless we choose to. The public price is [2.5% per transaction, no monthly fee](https://snippe.sh/). The API returns the real fee on `settlement.fees` and the net on `settlement.net`. Credit the SMS quantity the customer bought. Record the fee for finance. Do not hardcode 2.5% as the amount check.
- Rate limit is 60 requests per minute. Back off on HTTP 429 using `X-Ratelimit-Reset`.
- Log the event type, our payment id, and the Snippe reference. Do not log the API key, webhook secret, or full customer phone in application logs if it can be avoided.

## API contract we will use

Base URL: `https://api.snippe.sh`

Auth on every merchant call:

```http
Authorization: Bearer snp_...
Content-Type: application/json
Idempotency-Key: pay-123
```

Success envelope:

```json
{ "status": "success", "code": 200, "data": {} }
```

Error envelope:

```json
{ "status": "error", "code": 400, "error_code": "validation_error", "message": "amount is required" }
```

### Create a mobile-money collection

`POST /v1/payments` — scope `collection:create`

```json
{
  "payment_type": "mobile",
  "details": { "amount": 2500, "currency": "TZS" },
  "phone_number": "2557XXXXXXXX",
  "customer": {
    "firstname": "Amina",
    "lastname": "Juma",
    "email": "amina@example.com"
  },
  "webhook_url": "https://sms.soscom.co.tz/api/webhooks/snippe",
  "metadata": { "payment_id": "123" }
}
```

Create response (`201`) includes `reference` (UUID), `status: "pending"`, `expires_at`, and `api_version: "2026-01-25"`. The request `amount` is a number. Later responses and webhooks use `{ "value": 2500, "currency": "TZS" }`.

Networks: Airtel Money, M-Pesa, Mixx by Yas, Halotel. Snippe detects the network from the phone.

### Read status and balance

| Call | Scope | Use |
| --- | --- | --- |
| `GET /v1/payments/{reference}` | `collection:read` | Confirm status if the webhook is late |
| `POST /v1/payments/{reference}/push` | `collection:create` | Send the USSD prompt again |
| `GET /v1/payments?limit=20&offset=0` | `collection:read` | Reconciliation. `limit` max 100 |
| `GET /v1/payments/search?reference=` | `collection:read` | Lookup by reference |
| `GET /v1/payments/balance` | `collection:read` | Soscom's Snippe balance, not a customer's SMS balance |

Payment statuses: `pending`, `completed`, `failed`, `voided`, `expired`.

### Webhook

Snippe does not have a separate "register webhook URL" screen for this API. The URL travels on the payment.

Headers: `Content-Type: application/json`, `User-Agent: Snippe-Webhook/1.0`, `X-Webhook-Event`, `X-Webhook-Timestamp`, `X-Webhook-Signature`.

Events we must handle: `payment.completed`, `payment.failed`, `payment.voided`, `payment.expired`.

`payment.completed` body (API version `2026-01-25`):

```json
{
  "id": "evt_...",
  "type": "payment.completed",
  "api_version": "2026-01-25",
  "created_at": "2026-01-24T10:30:00Z",
  "data": {
    "reference": "pi_or_uuid",
    "external_reference": "SEL...",
    "status": "completed",
    "amount": { "value": 2500, "currency": "TZS" },
    "settlement": {
      "gross": { "value": 2500, "currency": "TZS" },
      "fees": { "value": 63, "currency": "TZS" },
      "net": { "value": 2437, "currency": "TZS" }
    },
    "channel": { "type": "mobile_money", "provider": "mpesa" },
    "customer": { "phone": "+2557...", "name": "Amina Juma", "email": "amina@example.com" },
    "metadata": { "payment_id": "123" },
    "completed_at": "2026-01-24T10:30:00Z"
  }
}
```

`payment.failed` adds `failure_reason` and `failed_at`. Failed, voided, and expired payments must not credit SMS.

Signature:

```text
hex(HMAC-SHA256(webhook_secret, "{timestamp}.{raw_json_body}"))
```

PHP check, using Laravel's raw body:

```php
$timestamp = $request->header('X-Webhook-Timestamp', '');
$signature = $request->header('X-Webhook-Signature', '');
$payload = $request->getContent();

if (abs(time() - (int) $timestamp) > 300) {
    abort(400);
}

$expected = hash_hmac('sha256', $timestamp.'.'.$payload, config('services.snippe.webhook_secret'));

if (! hash_equals($expected, $signature)) {
    abort(400);
}
```

Official PHP SDK: `composer require snippe/snippe-php` ([docs](https://docs.snippe.sh/docs/2026-01-25/sdks/php), [GitHub](https://github.com/Neurotech-HQ/snippe-php-sdk)). PHP 8.1+, `ext-curl`, `ext-json`. `Webhook::capture()` reads `php://input` itself. Inside a Laravel controller, prefer the raw-body check above so the framework and the SDK do not both consume the body.

### Errors worth handling

| HTTP | `error_code` | Meaning |
| --- | --- | --- |
| 400 | `validation_error` | Bad amount, phone, or missing customer fields |
| 401 | `unauthorized` | Missing, wrong, or wrong-environment API key |
| 403 | `insufficient_scope` | Key lacks `collection:create` or `collection:read` |
| 404 | `not_found` | Unknown payment reference |
| 422 | `validation_error` | Idempotency key reused with a different body |
| 429 | `rate_limit_exceeded` | Over 60 requests/minute |
| 500 | `PAY_001` | Processor down **or** idempotency key longer than 30 characters |
| 503 | | Upstream mobile-money provider down |

## Hosted checkout (not the first build)

`POST /api/v1/sessions` returns `checkout_url` (`https://snippe.me/checkout/...`) and `payment_link_url` (`https://snippe.me/p/...`). Minimum TZS 500. `expires_in` defaults to 3600 seconds and can be 60–86400. Customer pays on Snippe's page. We still confirm only by webhook. `redirect_url` is a browser convenience.

Payment profiles live at **Settings → Payment Profiles** and cannot be created through the API. Branding comes only from the profile. `webhook_url` on the request overrides the profile.

`?meta=` on a payment link is base64, not encryption. Anyone can decode it. Do not put secrets in it. For our app, server-side `metadata.payment_id` plus the stored Snippe reference is the safe match.

## Payouts (do not build for SMS purchases)

`POST /v1/payouts/send` sends Snippe balance to a wallet or bank and deducts amount plus fee immediately. Minimum payout is TZS 5,000. Failed payouts return to the balance. This needs `disbursement:create`. Leave it off until Soscom deliberately builds withdrawals.

Mobile payout networks documented: Airtel, Mixx by Yas (code `Tigo`), HaloPesa. M-Pesa is documented for **collections**, not in that payout list. Bank codes include CRDB, NMB, NBC, ABSA, and the rest of the [bank list](https://docs.snippe.sh/docs/2026-01-25/disbursements/bank-transfer).

## Doc conflicts to remember

- Direct payments expire in 4 hours. The webhook catalogue also says `payment.expired` defaults to 1 hour. Sessions default to 1 hour. Trust `expires_at` on the object we created, and still handle the `payment.expired` event.
- The sessions page says merchant auth is a JWT. The payments page says an API key (`snp_...`). Use the API key from **Settings → API Keys** for ` /v1/payments`.
- The auth page mentions a test key failing in production. The WooCommerce page says there is no sandbox. If the dashboard offers both a test and a live key, keep them in different env vars and never mix them. Test the real flow with TZS 500 or one real SMS package.

## What changes in this codebase later

Not done yet. When the two secrets are in the server environment:

- Add `snippe` beside `lipa_namba` on `PaymentMethod`.
- Store Snippe `reference`, `external_reference`, provider, fee, net, and event id on the payment. Keep the existing credit lock in `PaymentService`.
- Add a public `POST /api/webhooks/snippe` that verifies the signature before any credit.
- Change the buy screen so the customer enters the paying number, waits for the USSD prompt, and sees paid / failed / expired without an admin click.
- Admin payments stay a read-only list. New Snippe payments are credited by the webhook, not by an admin.
- Cover signature failure, amount mismatch, replay, duplicate webhook, and expired payment in tests. Hit Snippe for real only after the secrets exist, with one small live payment.

## Doc map

- [Overview](https://docs.snippe.sh/docs/2026-01-25)
- [Authentication](https://docs.snippe.sh/docs/2026-01-25/authentication)
- [Payments](https://docs.snippe.sh/docs/2026-01-25/payments)
- [Mobile money](https://docs.snippe.sh/docs/2026-01-25/payments/mobile-money)
- [Checkout sessions](https://docs.snippe.sh/docs/2026-01-25/sessions)
- [Payment profiles](https://docs.snippe.sh/docs/2026-01-25/sessions/profiles)
- [Payment links](https://docs.snippe.sh/docs/2026-01-25/sessions/payment-links)
- [Webhooks](https://docs.snippe.sh/docs/2026-01-25/webhooks)
- [Error handling](https://docs.snippe.sh/docs/2026-01-25/error-handling)
- [PHP SDK](https://docs.snippe.sh/docs/2026-01-25/sdks/php)
- [Disbursements](https://docs.snippe.sh/docs/2026-01-25/disbursements)
