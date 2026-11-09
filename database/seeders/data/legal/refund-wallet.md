# Refund, Cancellation & Wallet Policy

_SysUp360 OmniCast | Prepaid GHS wallet and campaign billing_

DRAFT — requires review and approval by Ghana-qualified legal counsel before publication.

## Document control

Version 1.0 | Draft for legal and commercial review | Effective date: [INSERT DATE] | Operator: [INSERT REGISTERED LEGAL ENTITY], [REGISTERED ADDRESS], Ghana | Privacy/contact email: [INSERT EMAIL].

## Interpretation and scope

This policy applies to the OmniCast multi-tenant messaging platform offering SMS, outbound voice and email campaigns, associated templates, contact management, analytics, payment and wallet services. It supplements the OmniCast Terms and Conditions. Where mandatory law prevails, that law controls.

## 1. Wallet structure

OmniCast uses a single GHS-denominated prepaid wallet shared across SMS, voice and email. Launch (GHS 51.25), Boost (GHS 243.75), Scale (GHS 2,153.75) and Max (GHS 3,990.00) are top-up denominations, not subscriptions or service-specific bundles. Buying a smaller package later does not downgrade the account.

## 2. What package cards mean

Displayed SMS, voice and email quantities are indicative alternative capacities calculated using current published rates, not three separate guaranteed allotments. Actual purchasing power varies by destination, SMS encoding/segments, voice billing duration, email pricing and applicable taxes or fees. The checkout must show the exact amount credited and any separately charged fees.

## 3. Successful top-ups

Credit the wallet only after authoritative payment-provider verification and idempotent server-side reconciliation. A redirect, screenshot or client-side success message alone does not establish payment. Each payment must generate a unique ledger credit and receipt.

## 4. Failed, pending or duplicate payments

Pending payments do not become spendable until confirmed. Failed charges do not generate credit. Duplicate provider callbacks must not create duplicate credits. If money is debited but credit is missing, the client should submit a payment reference for reconciliation; verified funds are credited or refunded as appropriate.

## 5. Wallet spending and reservations

Before dispatch, OmniCast may reserve an estimated campaign amount to prevent overspending. Final charges settle against billable usage, and unused reservations are released. Concurrent campaigns must be limited by available unreserved funds. Wallet history should distinguish available, reserved, debited and refunded amounts.

## 6. SMS charges

SMS charges may depend on destination, route, character encoding and segment count after personalisation. Submitted or accepted messages may incur charges even if final handset delivery fails, depending on upstream provider billing. Publish precise charge triggers and dispute treatment in the rate schedule.

## 7. Voice charges

Voice billing must follow the active published rate card and provider charging model. For per-minute billing, answered calls may be rounded up to the next whole minute per contact: for example, 61 seconds may count as two minutes. Unanswered calls ordinarily should not incur connected-minute charges unless a separately disclosed fee applies. Campaign estimates are not final charges.

## 8. Email charges

Email charges depend on the disclosed billable event, such as accepted message submission, and any premium delivery or validation services. Bounces and blocks may not automatically reverse charges where upstream resources have already been consumed; the exact rule must be disclosed before purchase.

## 9. Refund eligibility

Proposed policy for legal approval: unused, uncommitted wallet funds may be requested for refund to the original payment method, subject to verification, lawful fees, chargeback prevention and applicable mandatory rights. Promotional or bonus credits are not cash-refundable. Already consumed campaign charges are generally non-refundable unless billing error, service failure attributable to OmniCast or law requires otherwise.

## 10. Refund processing

Requests should include account ID, payment reference, requested amount and reason. Finance verifies ownership, available unreserved balance, ledger history and fraud risk. Approved refunds create a negative wallet ledger entry linked to the original payment and are processed through the supported provider. Set a published target processing period after confirming payment-provider capabilities.

## 11. Cancellation and campaign changes

Draft campaigns can be cancelled without usage charges. Submitted campaigns may be withdrawn before approval or dispatch, releasing any unused reservation. Once sending begins, cancellation stops future attempts where technically feasible but cannot recall already dispatched SMS, calls or email. Charges for completed billable actions remain payable.

## 12. Chargebacks and reversals

A payment reversal or chargeback may result in a corresponding wallet debit, temporary hold or suspension, subject to review and law. The system must not silently create negative spendable balances; reconcile exceptions and notify the client.

## 13. Expiry and inactivity

Recommended default: purchased wallet funds do not expire solely because the customer buys another top-up. Any inactivity, expiry, dormancy or escheat treatment must be separately disclosed, legally reviewed and implemented consistently with applicable Ghanaian law. Bonus credits may have separately disclosed expiry.

## 14. Taxes and price changes

Show whether quoted GHS amounts include applicable taxes and payment fees. Rate changes apply prospectively after notice as required; existing monetary wallet balances remain monetary balances, but estimated message capacity may change. Keep historical rate snapshots for each billed event.

## 15. Disputes and records

Provide searchable transaction history, downloadable receipts, usage records and dispute references. Clients should raise discrepancies promptly; mandatory statutory rights are unaffected by any contractual review window. Preserve auditable adjustment approvals and immutable transaction records.

## 16. Account closure

On closure, stop new campaigns, reconcile unsettled reservations and payments, and determine treatment of unused purchased funds under this policy and applicable law. Do not automatically confiscate funds due solely to account termination. Fraud, legal holds and unresolved chargebacks may affect timing.

## Changes and notices

We may revise this policy for legal, security, operational or product reasons. Material changes will be notified by appropriate in-product or email notice and will take effect on the stated date, subject to applicable law. Historical versions and acceptance records will be retained where appropriate.

## Contact and complaints

Questions and complaints should be sent to [INSERT SUPPORT EMAIL] or [INSERT POSTAL ADDRESS]. We will acknowledge and investigate complaints within commercially reasonable periods and meet applicable statutory deadlines. Users may contact competent regulators where legally entitled.
