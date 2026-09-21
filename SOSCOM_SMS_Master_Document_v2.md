# SOSCOM SMS — Master Project Document (v2)

## Part 0. Project Overview

**Company**
SOSCOM Technologies Company Limited — a Tanzanian software and technology company that builds custom software, digital solutions, and its own products.

**Product name**
Soscom SMS

**Domain**
sms.soscom.co.tz

**What it is**
A bulk SMS and messaging platform with two front doors on one shared core:

- A web dashboard where businesses, organizations, and institutions register, buy SMS credits, and send individual or bulk SMS.
- A developer API that lets other systems (websites, mobile apps, school systems, CRMs, ERPs) send SMS programmatically.

**What I want to achieve**
Take the product from its current state (a fresh Laravel + Livewire install with a UI template dropped in, no domain logic yet) to a production-ready SMS platform, built module by module in small, testable, committable tasks.

**Business model**
- SOSCOM buys SMS wholesale from Mobishastra at roughly TZS 14 per SMS.
- SOSCOM resells at a tiered price, configurable, not hardcoded:

| Volume | Price per SMS (TZS) |
|---|---|
| 500–1,000 | 30 |
| 1,000–2,500 | 28 |
| 2,500–10,000 | 25 |
| 10,000–50,000 | 22 |
| 50,000+ | 20 |

- Every send is priced by segment count, not message count.
- Customer price and provider cost are tracked separately so margin is always known.

---

## Part 1. Confirmed with Mobishastra (this changes/locks parts of the plan)

These points came directly from your conversation with Mobishastra and must be treated as fixed constraints, not assumptions:

1. **SOSCOM is confirmed as a reseller/aggregator.** Multiple independent SOSCOM customers can send SMS through SOSCOM's single Mobishastra account. This confirms the multi-tenant architecture in Part 5 is sound — no redesign needed here.
2. **Sender ID submission to Mobishastra is manual, not API-driven.** When a SOSCOM customer requests a sender ID, SOSCOM staff submit that request to Mobishastra manually (email/portal/account manager — however Mobishastra's process works on their side). There is no confirmed automatic sender-ID-submission API. The sender ID workflow in Phase 4 is therefore locked to: customer requests → admin reviews → **admin manually submits to Mobishastra outside the system** → admin records Mobishastra's approval/rejection back into SOSCOM → sender ID becomes active or rejected-with-reason. Do not build any automatic provider-side submission for sender IDs; just build a clean admin screen to track the manual step (status, notes, date submitted, date resolved).
3. **A static IP is required.** Mobishastra will only accept SMS-sending requests from SOSCOM's whitelisted static IP. This applies only to the SOSCOM server itself — individual SOSCOM customers or developers integrating with the SOSCOM API never need their own IP whitelisted by Mobishastra, since all their traffic passes through SOSCOM's server first. Action item for you: get a static IP for the production server before Phase 6 (Provider Integration) goes live, and send it to Mobishastra to whitelist.
4. **Developers integrating with SOSCOM never touch Mobishastra directly.** A third-party developer (e.g., "ABC School's" own system) authenticates only against SOSCOM's own API using a SOSCOM-issued API key. SOSCOM's backend is the only thing that ever talks to Mobishastra. This confirms the 3-level structure below.
5. **Sender ID ownership must be enforced at the SOSCOM layer**, since Mobishastra just sees "SOSCOM sending from IP X" — Mobishastra has no concept of your individual customers. If Customer A owns sender ID `ABC SCHOOL` and Customer B owns `LINKADI`, SOSCOM's own database — not Mobishastra — is what stops Customer A from sending as `LINKADI`.

### The 3-level structure (now confirmed, not just proposed)

```
Level 1 — SOSCOM Web Platform (sms.soscom.co.tz)
   Customers: buy SMS, contacts, campaigns, sender ID requests, send, schedule, reports, manage API keys

Level 2 — SOSCOM Public API (sms.soscom.co.tz/api/v1)
   Developers: API keys, send SMS, bulk SMS, balance, message status, webhooks, sender ID info

Level 3 — Mobishastra (completely hidden from every SOSCOM customer and developer)
   Only SOSCOM's backend calls this, from the whitelisted static IP
```

Mobishastra credentials, the static IP, and the account relationship all live only inside SOSCOM's own provider config (admin-only, per the `providers` table already in the plan). No customer or developer, however technical, ever sees a Mobishastra endpoint, username, or password.

---

## Part 2. Mobishastra API — Confirmed Technical Spec (from their integration document, v1.3)

This is the real spec the `MobishastraGateway` adapter (Phase 6) will implement. One flag before using it as-is: the document's examples use a UAE domain and phone format (`mshastra.com`, `+971...`) and also references `valuesms.ae` in places — this looks like a shared/generic template Mobishastra reuses across country deployments. **Before writing the adapter, confirm with your Mobishastra account manager the exact base URL, profile ID, and whether `CountryCode=ALL` (or a Tanzania-specific code) is correct for your account**, since the sample domain shown may not be the one your Tanzania account actually uses.

**Authentication**
No token/OAuth — every request carries `user` (an 8-character numeric profile ID Mobishastra assigns you) and `pwd` (your account password) as query parameters. Store both in the `providers` table, never in code or `.env` committed to git — use `.env` + config, as already planned.

**Sending SMS — PUSH API (GET request), multiple recipients**
```
GET https://<mobishastra-base>/sendurlcomma.aspx
    ?user=<profile_id>
    &pwd=<password>
    &senderid=<approved_sender_id>
    &mobileno=<comma,separated,numbers>
    &msgtext=<url-encoded message>
    &priority=High
    &CountryCode=ALL
```
Optional: `&scheduledDate=mm/dd/year hh:min am` for provider-side scheduling (Phase 8 can decide whether to use Mobishastra's own scheduling or SOSCOM's queue-based scheduling — recommend using SOSCOM's own queue so all campaign logic/state stays in one place, and only using this parameter if a specific need for provider-side scheduling comes up).

**Sending SMS — single recipient** uses `sendurl.aspx` with the same parameters (endpoint name differs, everything else the same).

**Error-code variant** — append `&ShowError=C` to get numeric response codes instead of text:
| Code | Meaning |
|---|---|
| 000 | Send Successful |
| 001 | Invalid Receiver |
| 003 | Invalid Message |
| 005 | Authorization failed |
| 006 | DND Number |
| 007 | Cannot Extract Country Code |
| 008 | Empty Receiver |
| 009 | Profile Blocked |
| 010 | Invalid Profile ID |
| 011 | Profile ID expired |
| 012 | Sender ID more than 13 characters |
| 013 | Server Error |

Use this error-code variant in the adapter rather than parsing free text like `"Send Successful"` — numeric codes are far more reliable to branch on, and map each one to Soscom's own normalized status/error at the adapter boundary (per the "normalize provider status" rule already in the architecture).

**Balance check**
```
GET https://<mobishastra-base>/balance.aspx?user=<profile_id>&pwd=<password>
```
Wire this to the Phase 6 "provider balance" task and to a low-balance admin alert (Phase 14).

**XML API (alternative to query-string GET, for bulk in one POST)**
`POST https://<mobishastra-base>/sendsms_api_xml.aspx` with an XML body containing `<user>`, `<pwd>`, and one `<message>` block per recipient (`<number>`, `<msg>`, `<sender>`, `<language>`). Response is XML with one `<message>` block per recipient containing `<msg_id>`, `<number>`, `<str_response>`.

**JSON API (alternative, closer to what a Laravel HTTP client wants)**
`POST` a JSON array, one object per recipient: `{"user":..., "pwd":..., "number":..., "msg":..., "sender":..., "language":...}`. Response is a JSON array of `{"msg_id":..., "number":..., "response":...}`. **Recommend using this JSON variant for `MobishastraGateway::sendBulk()`** — it maps far more naturally onto a `campaign_recipients` batch than building comma-joined query strings, and gives back a `msg_id` per recipient that should be stored on `deliveries.provider_message_id` for later status matching.

**Unicode / Swahili with special characters**
Set `language=Unicode` and pass `msgtext`/`msg` as hexadecimal-encoded text (not raw UTF-8). This must be handled inside the segmentation service (Phase 5), since it also affects segment counting below.

**Segment counting (confirms and refines the business rule already in Part 3 of the original roadmap)**
- English/GSM encoding: 160 characters = 1 SMS segment. If the message needs more than one segment, each additional segment holds 153 characters (not 160), because part of each segment is used for concatenation headers.
- Unicode encoding: 70 characters = 1 SMS segment; each additional segment holds 63 characters.
The Phase 5 segmentation calculator must implement exactly these thresholds (160/153 for GSM, 70/63 for Unicode) and must detect whether a message needs Unicode encoding (any non-GSM character, which will be common for Swahili names/place names with special characters) before choosing which threshold set to apply.

**Inbound SMS / PULL API (not needed for MVP, noted for the future)**
Mobishastra also supports inbound SMS: the client hosts a callback page (`response.aspx`-style endpoint) that Mobishastra calls with `shortcode`, `mobileno`, `keyword`, `message` parameters whenever someone texts a shortcode. This is unrelated to sending and is not part of the MVP — flag it as a **future** feature only if SOSCOM later wants two-way SMS (e.g., keyword-based surveys or opt-outs).

---

## Part 3. Database Architecture

Text ERD:

```
users ──< customers ──< contacts >── contact_groups
                │
                ├──< sender_id_requests >── sender_ids
                │
                ├──< credit_transactions   (the ledger)
                │
                ├──< campaigns ──< campaign_recipients ──> messages
                │                                   │
                │                                   └──< deliveries
                │
                ├──< api_keys ──< api_logs
                │
                ├──< webhooks ──< webhook_deliveries
                │
                └──< payments

providers ──< provider_logs
pricing_tiers (standalone, referenced at time of use)
audit_logs (polymorphic)
```

Notes worth restating given Part 1 and 2 above:
- `sender_id_requests` needs a `submitted_to_provider_at` and `provider_reference`/`notes` field, since submission is a manual admin action, not an API call — there is no automatic status webhook from Mobishastra for this step.
- `providers` (admin-only) stores `profile_id`, `password`, `base_url`, and the confirmed static IP for reference — never exposed past the admin layer.
- `deliveries.provider_message_id` stores Mobishastra's `msg_id` per recipient (from the JSON API response) so any later status check can be matched back to the right row.
- Everything else from the original database plan (customers, contacts, campaigns, messages, api_keys, webhooks, payments, audit_logs) is unchanged by this update.

---

## Part 4. Module Map & Dependency Order

```
0. Project Audit
1. Auth & Customer Profile
2. Credit Ledger & Pricing
3. Contacts & Groups
4. Sender IDs (manual Mobishastra submission, confirmed)
5. Messaging Core (Message/Campaign/Recipient/Segmentation — 160/153 GSM, 70/63 Unicode, confirmed)
6. Provider Integration (Mobishastra JSON API adapter, static IP required, confirmed)
7. Delivery Reports
8. Scheduling & Queues
9. Public API (3-level structure, confirmed — developers never see Mobishastra)
10. Webhooks
11. Customer Billing
12. Admin Dashboard
13. Security & Abuse Prevention
14. Production Readiness (static IP provisioning + whitelisting happens here or before Phase 6 go-live)
15. Testing
```

Dependency order is unchanged from the original plan; the sender ID and provider phases are now more precisely specified rather than restructured.

---

## Part 5. Phases and Step-by-Step Tasks

Phase 0 and Phase 1 remain fully expanded from the original document with Cursor-ready prompts (unchanged by this update — see previous version if needed). What changes here is the detail available for Phase 4 and Phase 6, now that Mobishastra's terms and API are confirmed.

### PHASE 4 — Sender IDs (updated)
4.1 `sender_ids` + `sender_id_requests` tables, with `submitted_to_provider_at`, `provider_reference`, `rejection_reason` fields.
4.2 Seed generic sender IDs (MWALIKO, TAARIFA, HUDUMA, etc. — confirm with Mobishastra which, if any, are already pre-approved for SOSCOM's account).
4.3 Customer request flow (customer submits a desired sender ID, sees status: pending, under review, submitted to provider, approved, rejected).
4.4 Admin screen for the manual submission step: admin marks a request "submitted to Mobishastra" (with a date), then later records the outcome ("approved" or "rejected" + reason) once Mobishastra replies outside the system. No API call happens here — this is a tracked manual workflow, confirmed.
4.5 Enforce active-only sender IDs at send time, and enforce ownership (a customer can only send using a sender ID their own account owns) at both the dashboard and API layer.

### PHASE 6 — Provider Integration (updated)
6.1 `SmsGatewayInterface` contract: `send()`, `sendBulk()`, `checkBalance()`, `checkDeliveryStatus()` (no `submitSenderId()` method — that's manual, per Part 1).
6.2 `MobishastraGateway` implementation using the **JSON API** variant from Part 2 for `sendBulk()`, with the `ShowError=C` numeric-code style response handling for the single-send path if the GET-based endpoint is used anywhere.
6.3 `providers` table populated with Mobishastra's profile ID, password, base URL, and the SOSCOM server's static IP (for reference/documentation — actual whitelisting happens on Mobishastra's side once you send them the IP).
6.4 Wire campaign sending through the interface with idempotency protection against duplicate sends on timeout; store each `msg_id` per recipient into `deliveries.provider_message_id`.
6.5 Provider balance check wired to `balance.aspx`, surfaced on the admin dashboard with a low-balance warning threshold.
6.6 **Action item before this phase can go live in production:** confirm the exact base URL/domain for your Tanzania Mobishastra account (don't assume `mshastra.com` from the sample doc), and provision + send Mobishastra your production server's static IP for whitelisting.

All other phases (2, 3, 5, 7–15) are unchanged from the original roadmap.

---

## Part 6. Critical Workflows (unchanged, restated for completeness)

1. Registration → complete profile → dashboard unlocked.
2. Buying SMS → tiered pricing → payment recorded → ledger entry → balance updates.
3. Sending SMS → active + owned sender ID → cost/balance preview → confirm → send.
4. Bulk SMS → contact group → chunked queued campaign.
5. Sender ID request → customer submits → admin reviews → **admin manually submits to Mobishastra** → outcome recorded → active or rejected-with-reason.
6. Sender ID approval → admin action moves the lifecycle; rejection requires a reason.
7. Provider sending → gateway interface call → credits deducted only on confirmed acceptance.
8. Delivery reporting → provider status mapped to Soscom's normalized enum.
9. API sending → SOSCOM API key validated → same core send service as dashboard → Mobishastra never seen by the caller.
10. Webhook delivery → signed payload → retried on failure.
11. Failed SMS → recorded distinctly; credits not deducted or refunded.
12. Refund/credit reversal → reversing ledger entry, never edits the original.
13. Customer suspension → blocks sending and API access at the middleware/gate level.

---

## Part 7. Security Roadmap, Deployment Checklist, MVP Definition

Unchanged from the original document, with one addition to the deployment checklist: **static IP provisioned and confirmed whitelisted by Mobishastra** must be checked off before Phase 6 goes live with real customer traffic — this is now a hard external dependency, not an assumption.

---

## Part 8. How We Work From Here

Say "Start Task X.X" and I will confirm the plan for that task, give you the final Cursor prompt, acceptance criteria, and a manual testing checklist — then wait for you to confirm it's tested before we move on.

Since you're about to upload the Mobishastra PDF into the Cursor project too: it's fine for Cursor to read it as reference documentation, but the `MobishastraGateway` class itself should only be written once we reach Phase 6 — don't let Cursor jump ahead and build the provider adapter while we're still on Phase 0/1 foundation work.
