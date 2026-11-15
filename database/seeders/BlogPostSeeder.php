<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'slug' => 'complete-guide-to-bulk-sms-in-ghana',
                'title' => 'Bulk SMS in Ghana: a practical guide for businesses',
                'category' => 'SMS marketing',
                'excerpt' => 'How to plan useful text campaigns, reach customers across Ghana’s mobile networks, and build consent and compliance into the way you send.',
                'meta_title' => 'Bulk SMS in Ghana: The Complete Guide for Businesses | Omnicast',
                'meta_description' => 'A practical guide to bulk SMS in Ghana: campaign planning, promotional and transactional messages, consent, sender names, APIs and FAQs.',
                'body' => <<<'MARKDOWN'
Social feeds move quickly. Emails get buried. But a well-timed text still gives a business a direct, simple way to reach a customer, whether that customer is in Accra, Kumasi, Tamale or somewhere with patchy data coverage.

Bulk SMS lets you send one useful update to many people at once. It can support a shop announcing a restock, a school reminding parents about a meeting, or an app sending a one-time passcode. The key is to send messages people expect and actually need.

## Why SMS still earns a place in your communication mix

SMS is direct and familiar. It works on basic phones as well as smartphones, and a recipient does not need to install an app or use mobile data to read a standard text. It can be useful for time-sensitive updates and automated service messages. Reach alone does not guarantee attention, though: relevance, timing and trust matter.

## Promotional or transactional? Start with the purpose

A promotional message is designed to encourage interest or a purchase. A transactional message helps someone complete or understand an action they have already taken, such as receiving a login code or payment receipt. Keep the two purposes clear, and check the rules that apply to your provider and network route.

| Promotional | Transactional |
| --- | --- |
| A sale, new product or event invitation | A login code, receipt or order update |
| Sent to people who agreed to hear from you | Usually triggered by a customer’s action |
| Needs careful audience and timing choices | Should be prompt, clear and limited to the service |

## Where businesses put SMS to work

- **Retail and e-commerce:** back-in-stock alerts, order updates and relevant offers.
- **Financial services:** one-time passcodes, transaction notices and repayment reminders.
- **Schools:** meeting dates, fee reminders and urgent closure notices for parents.
- **Churches and community groups:** service times, event details and important announcements.
- **Clinics and service providers:** appointment confirmations and reminders.

## Build trust into every campaign

Collect numbers fairly, explain what people are signing up for, and keep a record of their preferences. Do not buy lists or add people simply because you have their number. Identify your organisation, make it easy to stop marketing messages, and honour opt-out requests promptly. Check current NCA guidance and your SMS provider’s requirements for message timing, sender names and network rules; they can differ by message type and route.

A branded sender name can help recipients recognise a message, but approval processes and supported names vary. Ask your provider what the networks currently accept before planning a launch around one.

## When it is time to automate, connect an SMS API

A web dashboard is a good place to start. If your team already uses a website, app, point-of-sale system or CRM, an SMS API can send the right update when something happens:

- Welcome someone after account creation.
- Deliver a one-time code during sign-in or checkout.
- Send a receipt when a payment is confirmed.
- Track delivery outcomes through reports or webhooks.

Before choosing an API, review its documentation, test tools, delivery reporting, support and handling of failed messages.

## A simple way to get started

1. Choose one clear goal for your first campaign.
2. Build a small, permission-based audience for that purpose.
3. Write a short message with your name and a useful next step.
4. Check current provider and network requirements before scheduling.
5. Review delivery results and use what you learn to improve the next message.

Omnicast brings campaigns, contact segments and delivery insights together, with tools for teams getting started and developers building SMS into their products. [Explore pricing](/pricing) or [create an account](https://omnicast.sysup360.com/register-client).

## Frequently asked questions

### How much does bulk SMS cost in Ghana?

Pricing depends on the provider, route and message volume. Check current rates before planning a campaign. Long messages or Unicode characters may use multiple SMS segments.

### How long does sender name approval take?

Timelines vary by provider and network. Ask which documents are needed and how long the current review is expected to take.

### Can one platform send to MTN, Telecel and AT?

Many platforms support Ghana’s major mobile networks. Confirm route availability with your provider and review delivery reports after sending.

### Does someone need internet or a smartphone to receive an SMS?

No. Standard SMS is delivered over the mobile network, so recipients do not need mobile data or a smartphone.
MARKDOWN,
            ],
            [
                'slug' => 'sms-api-integration-ghana-otp-automated-alerts',
                'title' => 'Integrate an SMS API in Ghana for OTPs and useful alerts',
                'category' => 'For developers',
                'excerpt' => 'A practical path from API credentials to reliable OTPs, payment notifications and delivery callbacks.',
                'meta_title' => 'SMS API Integration in Ghana: OTPs & Automated Alerts | Omnicast',
                'meta_description' => 'Learn how to plan an SMS API integration for OTP verification, payment notifications and delivery reports in Ghana.',
                'body' => <<<'MARKDOWN'
An OTP that arrives late can stop a customer at sign-in. A payment receipt that never comes can leave them wondering whether checkout worked. Connecting an SMS API to your product can make these moments clearer, provided the integration is designed for failure as well as success.

This guide walks through the decisions to make before wiring an SMS gateway into your app. Check the [Omnicast API documentation](/api-docs) for current endpoints and authentication details; the examples below are provider-neutral.

## Start with the event, not the endpoint

List the events that should send a text: account verification, passwordless sign-in, payment confirmation, appointment reminders or delivery updates. Define what should happen if sending fails, and avoid sending a second message every time a background job retries.

## Protect credentials and customer data

Keep API keys in server-side environment configuration or a secrets manager. Do not put them in browser code, mobile apps or public repositories. Limit access, rotate keys when needed, and send only the personal data required to render the message.

## Design OTPs with sensible limits

Generate short-lived, single-use codes using a cryptographically secure random source. Store a verifier rather than a plain code where practical, expire codes quickly, limit attempts and requests, and avoid logging the code itself. Bind each code to the user and action it was created for.

## Queue messages and handle outcomes

For non-interactive notifications, a queue helps keep a slow provider response from holding up a customer request. Set timeouts and bounded retries. Use an idempotency key or equivalent application record so retries do not create duplicate sends. Treat an API acceptance response as “queued” unless the provider confirms final delivery separately.

Delivery reports and webhooks can update your message record later. Verify webhook signatures when offered, reject unexpected payloads, and make event processing idempotent because callbacks can be retried.

## Test before launch

Use a test environment or a small internal recipient group first. Check Ghanaian phone-number normalisation, message encoding and segment counts, sender-name status, provider errors, callback handling and opt-out rules for promotional traffic. Keep transactional alerts separate from marketing campaigns.

## A launch checklist

- Store credentials outside source control and client applications.
- Set a clear timeout and retry policy.
- Rate-limit OTP requests and verify codes only once.
- Record message identifiers and delivery states without storing unnecessary secrets.
- Validate and authenticate delivery callbacks.
- Confirm current route coverage, sender-name requirements and pricing with your provider.

The API is one piece of the experience. Clear copy, consent, monitoring and a recovery path when delivery fails matter just as much.
MARKDOWN,
            ],
            [
                'slug' => 'how-to-register-branded-sms-sender-id-ghana',
                'title' => 'Branded SMS sender names in Ghana: what to check first',
                'category' => 'SMS delivery',
                'excerpt' => 'How a recognisable sender name helps, what information providers may ask for, and how to plan around review times.',
                'meta_title' => 'Branded SMS Sender ID in Ghana: A Practical Guide | Omnicast',
                'meta_description' => 'Learn how to prepare for a branded SMS sender name in Ghana and what to confirm with your messaging provider.',
                'body' => <<<'MARKDOWN'
A text is easier to trust when the recipient can tell who sent it. A branded sender name can help with that, but the process and supported formats depend on the messaging provider and mobile network.

## What is a sender name?

It is the label displayed as the sender of an SMS, often a business or organisation name. Networks may limit its length or characters, and some routes require names to be reviewed or registered before use. Do not assume that approval on one network automatically covers another.

## Prepare before you submit

Ask your provider for its current requirements. You may be asked for proof that your organisation owns or represents the name, business registration details, a letter of authorisation, sample message content or a description of the use case. Requirements can change, so use the provider’s latest checklist rather than relying on an old online list.

Choose a name recipients will recognise and that clearly relates to your organisation. Avoid names that could be confused with a bank, public agency or another brand. Keep your messages consistent with the stated purpose.

## Plan the timeline carefully

Review time can vary with the provider, network and submitted documents. Submit early, ask for an expected timeline, and keep a fallback plan for a launch date. Do not promise a fixed approval window until your provider confirms it for your submission.

## Use the name responsibly

A sender name is not a substitute for consent. Keep marketing lists permission-based, explain what subscribers will receive and provide a clear opt-out method. For transactional messages, keep the content tied to the action that triggered it. Follow current NCA guidance, applicable privacy requirements and the rules your provider shares.

## Before your first send

1. Confirm supported sender-name formats and network coverage.
2. Gather the latest documents requested by your provider.
3. Submit the name and sample messages for review.
4. Test delivery on the networks and devices relevant to your audience.
5. Monitor reports and respond quickly if a route rejects or rewrites the name.

If you use Omnicast, start with the [Sender IDs area in your dashboard](/sender-ids) or [contact the team](/contact) for current registration steps.
MARKDOWN,
            ],
            [
                'slug' => 'bulk-sms-vs-whatsapp-business-ghana',
                'title' => 'Bulk SMS or WhatsApp Business? Choosing the right channel',
                'category' => 'Messaging strategy',
                'excerpt' => 'Compare the strengths of SMS and WhatsApp for alerts, customer service and marketing, then use them together where it makes sense.',
                'meta_title' => 'Bulk SMS vs WhatsApp Business in Ghana | Omnicast',
                'meta_description' => 'Compare SMS and WhatsApp for customer communication in Ghana, including reach, rich conversations, service alerts and consent.',
                'body' => <<<'MARKDOWN'
SMS and WhatsApp can both help a business stay in touch with customers. The better choice depends on what the message needs to do, what your audience uses and how much interaction you expect.

## When SMS is a good fit

SMS is useful for concise, time-sensitive information: a verification code, appointment reminder, payment receipt or short service update. It works without mobile data and does not require the recipient to install an app. Messages are short, and longer or Unicode text may be split into multiple billable segments.

## When WhatsApp is a good fit

WhatsApp can support richer, two-way conversations with images, documents, product details and interactive replies. Business Platform messaging may involve templates, conversation rules and charges that can change, so check Meta’s current policies and pricing before estimating costs. The free WhatsApp Business app has different capabilities from the Platform/API.

## Compare the job each channel does

| Need | SMS | WhatsApp |
| --- | --- | --- |
| Reach without mobile data | A good fit for standard text | Requires an internet connection |
| One-time codes and short alerts | Commonly used | Possible, depending on setup |
| Images, catalogues and documents | Not suited to standard SMS | Supports richer media |
| Two-way customer support | Replies depend on the route | Designed for chat-style interaction |
| Cost planning | Check route, volume and segment pricing | Check current product rules and pricing |

Avoid treating a published “open rate” as a guarantee. Actual delivery and response depend on audience, content, timing, device and provider.

## A thoughtful way to combine them

You might send a concise SMS with an order update and offer a WhatsApp link for questions. Or use WhatsApp for a customer conversation and SMS for a short fallback alert. Keep consent and opt-out preferences clear for each channel, and do not send a promotional message simply because someone contacted you for service.

## Make the choice

Start with the customer’s need. If the update must reach a basic phone without data, SMS may be the practical option. If the customer needs to browse images or have a conversation, WhatsApp may fit better. For many teams, using each channel for its strengths works best.
MARKDOWN,
            ],
            [
                'slug' => 'bulk-sms-for-churches-schools-nonprofits-ghana',
                'title' => 'Bulk SMS for schools, churches and nonprofits in Ghana',
                'category' => 'For institutions',
                'excerpt' => 'Practical ways to keep parents, congregations, volunteers and community members informed, with ready-to-adapt message examples.',
                'meta_title' => 'Bulk SMS for Churches, Schools & Nonprofits in Ghana | Omnicast',
                'meta_description' => 'Ideas and message templates for schools, churches and nonprofits using SMS to share reminders and community updates in Ghana.',
                'body' => <<<'MARKDOWN'
When a school meeting changes venue, a church programme is coming up, or a community health event needs volunteers, people need the right information in time. Flyers and group chats can help, but messages may be missed or recipients may not have data when they need them.

Bulk SMS gives institutions a straightforward way to send concise updates to people who have agreed to receive them. The most useful messages are specific, timely and easy to act on.

## For schools: make parent communication easier

Schools can use SMS for PTA reminders, term dates, fee due dates, exam information and urgent closure notices. Keep financial messages private and share only the details a recipient needs. Never ask a parent to disclose a mobile-money PIN or password by text.

**Fee reminder:** “Hello [Parent Name], a reminder that [Student Name]’s [Term] fees are due on [Date]. For payment options, contact [School Office] on [Phone]. — [School Name]”

**PTA meeting:** “You’re invited to [School Name]’s PTA meeting on [Date] at [Time] at [Venue]. We look forward to seeing you.”

## For churches: keep the community connected

Share service times, programme details and practical updates with members who have opted in. For sensitive welfare matters, contact people privately rather than broadcasting personal details to a whole list.

**Programme reminder:** “Reminder: [Church Name]’s [Programme] is on [Date] at [Time], [Venue]. For directions, call [Number]. We hope to see you.”

**Visitor follow-up:** “Hello [First Name], thank you for visiting [Church Name]. We hope you felt welcome. If you have a question, call [Number].”

## For nonprofits: coordinate people and services

SMS can help share outreach dates, volunteer schedules, clinic information or changes to a distribution plan. State whether a service is free, where to go and who to contact. Do not include private beneficiary details in a group message.

**Community notice:** “[Organisation]: [Service/Event] takes place on [Date] from [Start] to [End] at [Location]. For information, call [Number]. Please share with neighbours who may need it.”

## Keep messages useful and respectful

Collect numbers with a clear explanation of how they will be used. Separate groups by class, branch or programme so each person receives relevant notices. Identify your organisation, protect contact lists, provide a way to opt out of promotional messages, and follow current provider requirements and applicable privacy rules.

Character encoding affects message length: standard GSM text can fit more characters than Unicode text, and long messages can be split into segments. Preview the final message and cost before sending.

## A simple starting plan

1. Choose the audience and the one action you want them to take.
2. Check that recipients agreed to these messages.
3. Include the organisation name, date, time, place and contact details that matter.
4. Send a test to your team and check the message on a phone.
5. Review delivery reports and keep contact information up to date.

With clear groups and a few reusable templates, an institution can spend less time repeating notices and more time supporting its community.
MARKDOWN,
            ],
        ];

        foreach ($posts as $index => $post) {
            BlogPost::query()->firstOrCreate(['slug' => $post['slug']], [
                ...$post,
                'status' => 'published',
                'is_featured' => $index === 0,
                'sort_order' => $index,
                'published_at' => now()->subDays(count($posts) - $index),
            ]);
        }
    }
}
