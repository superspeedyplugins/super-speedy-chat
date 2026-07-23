# Sonetel + WhatsApp rollout (Dave's personal setup)

Status: **not started** - written 22 Jul 2026 so this can be picked up cold in a
later session. Update the checkboxes as steps complete.

This is Dave's own rollout of the WhatsApp integration on live
superspeedyplugins.com, using a **Sonetel virtual number** as the WhatsApp
business number. It is separate from the generic user-facing guide
(`whatsapp-integration-setup.md`) - follow that doc for the Meta/plugin steps;
this doc adds the Sonetel specifics, Dave's decisions, and the affiliate
follow-up.

## Context / decisions already made

- WhatsApp integration was **built in v1.11.0** (`includes/class-ssc-whatsapp.php`),
  both modes: visitors can chat via WhatsApp, AND every chat message is
  forwarded to Dave's personal WhatsApp (tagged `[#id name]`, quote-reply to
  answer). Motivation: Discord phone notifications lag ~10 minutes; Dave will
  reply through whichever channel notifies first. Discord stays enabled -
  the channels mirror each other.
- Dave's personal number goes ONLY in "Your WhatsApp Number" (forward target).
  The business number must be a separate number not registered on regular
  WhatsApp - hence Sonetel (~$2/month, no SIM needed).
- Provider choice: **Sonetel**, chosen because Dave will use it personally,
  test it works, then recommend it with an **affiliate link** (their programme:
  20% first-year commission, 30-day cookie). Twilio was ruled out for
  recommendation purposes (no self-serve affiliate programme).

## Blockers

- [ ] **v1.11.0 is uncommitted** in the super-speedy-chat repo (as of 22 Jul).
      Commit, push, and update the live site to 1.11.0 BEFORE any of the
      below - the webhook must point at live (Meta cannot reach local dev).

## Phase 1 - Sonetel number (the risky bit, do first)

The whole plan depends on a Sonetel number receiving **one verification
SMS (or voice call) from Meta**. VoIP numbers are exactly where this gets
flaky, so prove it before writing anything public.

- [ ] Sign up at sonetel.com and buy a number. Requirements:
      - Must be **SMS-capable** (Sonetel offers both; pick a number/country
        combo that explicitly lists inbound SMS support - a US +1 local
        number is the safe default and reads fine internationally).
      - Do NOT pick a country needing local-address paperwork.
- [ ] Confirm inbound SMS shows up (Sonetel app/dashboard) - send it a text
      from a phone.
- [ ] Run Meta verification against it (Phase 2 step 2). If the SMS never
      arrives: use Meta's **voice call** option and take the code from
      Sonetel's call handling (answer in the Sonetel app, or set the number
      to record/voicemail and play it back).
- [ ] VERDICT: record here whether Sonetel survived Meta verification cleanly.
      This decides whether the affiliate recommendation goes in the KB.

## Phase 2 - Meta setup

Follow `whatsapp-integration-setup.md` Part 1 in full. Summary:

- [ ] developers.facebook.com -> Create App (type Business) -> add WhatsApp product.
- [ ] WhatsApp -> API Setup -> add the Sonetel number, verify (SMS first,
      voice fallback).
- [ ] Create permanent access token via Business Settings -> System Users
      (whatsapp_business_messaging + whatsapp_business_management).
      The API Setup page's temporary token dies after 24h - fine for a first
      test, not for leaving configured.
- [ ] Copy: Phone Number ID, permanent token, App Secret (App Settings -> Basic).
- [ ] Optional but recommended: create a re-engagement template in WhatsApp
      Manager -> Message Templates ("You have a new reply - message us back to
      continue") and note its name + language code. `hello_world` / `en_US`
      works while testing.

## Phase 3 - Plugin config on live

wp-admin -> Super Speedy Chat -> Settings -> WhatsApp tab
(`whatsapp-integration-setup.md` Parts 2-4):

- [ ] Access Token, Phone Number ID, App Secret, Business Phone Number
      (the Sonetel number, +1... format). Enable. Save. **Test Connection**.
- [ ] Webhook: copy Callback URL + Verify Token from the tab into Meta's
      WhatsApp -> Configuration -> Webhook; verify; subscribe to **messages**.
- [ ] Forward to Your Phone: tick, enter Dave's personal number.
- [ ] From Dave's phone, WhatsApp any message TO the Sonetel number (opens
      the 24h forwarding window; replying to forwards keeps it open).
- [ ] Re-engagement template name + language into the plugin settings.
- [ ] Click to Chat: tick to show the bubble's "Chat on WhatsApp" link.

## Phase 4 - End-to-end test matrix

- [ ] Website bubble message -> arrives on Dave's WhatsApp as `[#id Name]`
      AND in the Discord thread. Compare notification speed (the 10-min
      Discord lag was the whole point - note the difference).
- [ ] Quote-reply from phone -> appears in website bubble + mirrored to Discord.
- [ ] Reply from wp-admin -> copied to Dave's WhatsApp + Discord.
- [ ] Reply from Discord -> reaches the visitor + copied to Dave's WhatsApp.
- [ ] From a second phone (NOT Dave's - the admin number is always treated as
      admin), message the Sonetel number -> WhatsApp-badged conversation in
      dashboard; reply from wp-admin arrives back on that phone.
- [ ] Non-quoted reply from Dave's phone -> plugin sends back the
      "quote-reply so I can route it" helper message.
- [ ] Let a test conversation go >24h quiet, reply from wp-admin -> template
      sent instead, warning shown under reply box.

## Phase 5 - Affiliate + marketing follow-up (only after Phase 1 verdict is a pass)

- [ ] Join Sonetel's affiliate programme, get the link.
- [ ] Add a "Where to get a number" section to `whatsapp-integration-setup.md`
      recommending Sonetel (affiliate link), with the honest caveat about
      voice-fallback if SMS verification is slow, and Twilio as the
      known-name alternative.
- [ ] KB article for the WhatsApp integration (kb-publishing skill) including
      the Sonetel walkthrough + affiliate link.
- [ ] Mention WhatsApp support on the Super Speedy Chat sales page / changelog
      marketing (readme changelog entry for 1.11.0 already written).
- [ ] Consider a dev-diary entry: building a WhatsApp channel on the add-on
      API, and the 24h-window design.

## Gotchas to remember (from the build session)

- Local dev cannot receive webhooks; all inbound testing needs live. The
  code itself was smoke-tested locally (17 checks, all passing) via
  `wp --skip-plugins=scalability-pro eval-file` - test script pattern is in
  the session scratchpad, easy to recreate: simulate a Cloud API payload
  against `SSC_Whatsapp::handle_incoming_webhook()`.
- Webhook GET handshake echoes `hub.challenge` as a JSON int (verified
  working via curl); PHP mangles `hub.mode` query keys to `hub_mode`.
- Inbound is rejected unless App Secret is set (signature verification is
  mandatory, not optional).
- Meta test numbers can only message 5 verified recipients - fine for
  Phase 4, which is why the real Sonetel number comes first.
