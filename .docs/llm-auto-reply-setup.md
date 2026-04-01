# LLM Auto-Reply Setup Guide

Super Speedy Chat can automatically reply to visitors when no admin responds in time. It does this by sending the visitor's question to an LLM (OpenAI or Anthropic) along with all your canned responses. The LLM picks the best matching canned response and sends it as a bot reply. If no canned response is a good match, nothing is sent.

This is a classifier, not a conversational AI. The LLM never generates free-form answers -- it only selects from your pre-written canned responses. You stay in full control of what visitors see.

---

## Prerequisites

- **Canned responses saved** in the Canned Responses tab (see the Canned Responses section in the plugin settings)
- **An API key** from [OpenAI](https://platform.openai.com/api-keys) or [Anthropic](https://console.anthropic.com/settings/keys)

---

## Step 1: Configure the LLM Provider

Go to **Super Speedy Chat settings > LLM Auto-Reply** tab.

1. **LLM Provider** -- Select either OpenAI or Anthropic.

2. **API Key** -- Enter your API key. The key is stored in the WordPress database, so use a key with minimal permissions (e.g. an OpenAI key restricted to chat completions only).

3. **Model** -- Leave this blank to use the defaults:
   - OpenAI: `gpt-4o-mini`
   - Anthropic: `claude-haiku-4-5`

   These are the cheapest and fastest models from each provider. Since the task is simple classification (picking a number from a list), expensive models are unnecessary. Each classification costs fractions of a cent.

4. **System Prompt** -- The default prompt works well for most setups. It instructs the LLM to pick the best matching canned response number, or respond with 0 if none match. Only change this if you have specific needs.

---

## Step 2: Set the Timeout Action

Go to **Super Speedy Chat settings > Behaviour** tab.

1. **Timeout Action** -- Choose one of the LLM options:

   | Option | What happens |
   |--------|-------------|
   | **Auto-reply with canned response (LLM)** | Sends the best-matching canned response as a bot reply. Nothing else happens. |
   | **Auto-reply with LLM, then show email prompt** | Sends the best-matching canned response, then also shows the email collection form so the visitor can leave their contact details. |

2. **Admin Reply Timeout** -- How many seconds to wait for an admin reply before triggering the auto-reply. Default is 30 seconds. Adjust based on how quickly your team typically responds.

---

## How It Works in Practice

1. A visitor sends a message in the chat widget.
2. The configured timeout period passes (default 30 seconds) with no admin reply.
3. The visitor's question is sent to the LLM along with all your canned responses.
4. The LLM picks the best matching canned response (or determines none are a good match).
5. If a match is found, the canned response appears in the chat as a bot message.
6. The visitor sees the reply on the next poll cycle.

If no canned response matches the visitor's question, no auto-reply is sent. The timeout action (e.g. showing the email prompt) may still proceed depending on your configuration.

---

## Cost Expectations

Both `gpt-4o-mini` and `claude-haiku-4-5` are budget-tier models designed for lightweight tasks like classification. A single auto-reply classification costs well under 1 cent -- typically a fraction of a cent depending on how many canned responses you have.

Even with hundreds of conversations per day, monthly LLM costs would be minimal (likely under a few dollars). The cost scales linearly with the number of canned responses and conversations, but remains low because the prompts are short and the response is a single number.

---

## Troubleshooting

- **Auto-reply never fires** -- Check that Timeout Action is set to one of the LLM options in the Behaviour tab, and that your API key and provider are configured in the LLM Auto-Reply tab.
- **Auto-reply always returns nothing** -- Review your canned responses. If the questions in your canned responses don't relate to what visitors are asking, the LLM will correctly determine there is no match. Add canned responses that cover your most common visitor questions.
- **API errors** -- Verify your API key is valid and has sufficient credits/quota. Check your provider's dashboard for usage and error logs.
