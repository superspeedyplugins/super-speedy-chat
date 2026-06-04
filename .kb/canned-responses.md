# Canned Responses

## What are canned responses?

Canned responses are pre-saved question-and-answer pairs stored in your Super Speedy Chat plugin. When admins are away, the LLM classifier can automatically match incoming visitor questions against your canned responses and send the best-fitting reply. This lets your chat handle common queries around the clock without a live agent.

## Saving a canned response from a conversation

While reviewing a conversation in wp-admin, you can save any admin message as a canned response directly from the chat log.

1. Find the admin message you want to save and click the **star icon** next to it.
2. An inline form appears with three fields:
   - **Question** -- Auto-populated from the preceding visitor message. Edit this to better summarize what the visitor was asking.
   - **Response** -- Auto-populated from the admin message you clicked. Adjust if needed.
   - **Category** (optional) -- Assign a category for organization, such as "billing", "setup", or "general".
3. Click **Save** to store the canned response.

The saved response is immediately available for the LLM classifier to use.

## Managing canned responses

The plugin settings page includes a **Canned Responses** tab where you can view and manage all saved responses. The tab displays a searchable table with the following columns:

- **Question** -- The visitor question or summary.
- **Response** -- The saved admin reply.
- **Category** -- The assigned category, if any.
- **Usage Count** -- How many times the LLM classifier has selected this response. This increments automatically each time the response is used in an auto-reply.

Each row provides **Edit** and **Delete** actions. Use the search bar at the top to filter responses by keyword.

## How canned responses are used

Canned responses power the **LLM Auto-Reply** feature. When a visitor sends a message and no admin is available, the LLM classifier evaluates the visitor's question against all stored canned responses and selects the best match. If a strong match is found, the corresponding response is sent automatically.

The quality and coverage of your canned response library directly affects how well auto-reply performs. A larger set of well-written responses means more visitor questions can be handled without admin intervention.

For details on enabling and configuring automatic responses, see the [LLM Auto-Reply Setup](llm-auto-reply-setup.md) guide.

## Tips

- **Write clear question summaries.** When saving a canned response, edit the question field to reflect how visitors actually phrase their questions. Generic or vague summaries make it harder for the classifier to find a good match.
- **Build your library over time.** Save good responses as you chat with visitors. After a few weeks of regular use, you will have solid coverage for your most common questions.
- **Use categories.** Categories make it easier to find and manage responses as your library grows. Settle on a small set of consistent category names (e.g. "billing", "setup", "general", "technical") and stick with them.
- **Review usage counts.** Responses with high usage counts are clearly valuable. Responses with zero usage may need their question field rewritten, or may cover topics visitors rarely ask about.
- **Keep responses concise.** Short, direct answers work best for auto-reply. If a topic requires a lengthy explanation, consider linking to a help page or documentation article in your response.
