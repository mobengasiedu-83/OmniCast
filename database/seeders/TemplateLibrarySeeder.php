<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\MessageTemplate;
use App\Models\TemplateCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The platform template library.
 *
 * Ten categories and fifty SMS starting points mirror the published catalogue,
 * so a client never faces an empty library on its first campaign. Every entry
 * is a system template (`client_id` null): a tenant that edits one keeps the
 * catalogue intact because the editor copies it into the tenant instead.
 */
class TemplateLibrarySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The categories, in the order the library shows them.
     *
     * @var array<string, string>
     */
    protected array $categories = [
        'Marketing & Promotions' => 'Sales offers, discounts and product launches.',
        'Finance & Collections' => 'Payment reminders, receipts and overdue notices.',
        'Customer Service' => 'Service updates and complaint follow-ups.',
        'Appointments & Bookings' => 'Booking confirmations and reminders.',
        'Events & Invitations' => 'Invitations, reminders and event follow-ups.',
        'Education & Schools' => 'School notices, fees and examination reminders.',
        'Healthcare' => 'Appointment reminders and general wellness notices.',
        'Real Estate & Property' => 'Listings, viewings and rent reminders.',
        'Surveys & Feedback' => 'Feedback requests and satisfaction surveys.',
        'General Announcements' => 'Welcome messages, holidays and announcements.',
    ];

    /**
     * The catalogue, keyed by category. Each entry is a name and its copy.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    protected array $templates = [
        'Marketing & Promotions' => [
            ['Special Discount', 'Hi {{first_name}}, enjoy {{discount}}% off at {{company_name}}! Offer ends {{expiry_date}}. Shop: {{link}}'],
            ['New Product Launch', 'Hello {{first_name}}, exciting news! {{company_name}} has launched {{product_name}}. Discover more: {{link}}'],
            ['Flash Sale', 'FLASH SALE! Save up to {{discount}}% on selected items at {{company_name}}. Hurry, offer ends {{expiry_date}}. {{link}}'],
            ['Loyalty Reward', 'Thank you for being a valued customer, {{first_name}}! Enjoy your exclusive reward from {{company_name}}: {{reward_details}}.'],
            ['Seasonal Promotion', 'Celebrate {{occasion}} with {{company_name}}! Enjoy our special offers until {{expiry_date}}. Visit {{link}}.'],
        ],
        'Finance & Collections' => [
            ['Payment Due Reminder', 'Dear {{first_name}}, your payment of GHS {{amount}} is due on {{due_date}}. Please pay before the deadline. {{company_name}}.'],
            ['Overdue Payment', 'Dear {{first_name}}, our records show an outstanding balance of GHS {{amount}}. Kindly make payment or contact {{support_number}} if already paid.'],
            ['Payment Confirmation', 'Dear {{first_name}}, we have received your payment of GHS {{amount}} on {{payment_date}}. Reference: {{transaction_id}}. Thank you.'],
            ['Instalment Reminder', 'Hello {{first_name}}, your next instalment of GHS {{amount}} is due on {{due_date}}. Thank you for choosing {{company_name}}.'],
            ['Account Statement Available', 'Dear {{first_name}}, your latest account statement is ready. Sign in securely at {{portal_link}} to view it.'],
        ],
        'Customer Service' => [
            ['Service Interruption', 'Dear customer, {{company_name}} is experiencing a service interruption affecting {{service_name}}. We apologise and will provide updates.'],
            ['Service Restored', 'Good news! {{service_name}} has been restored. Thank you for your patience. {{company_name}}.'],
            ['Complaint Acknowledgement', 'Hello {{first_name}}, your complaint has been received. Ticket: {{ticket_number}}. Our team will contact you shortly.'],
            ['Complaint Resolution', 'Dear {{first_name}}, your support ticket {{ticket_number}} has been marked resolved. If you still need assistance, call {{support_number}}.'],
            ['Customer Satisfaction', 'Hi {{first_name}}, how was your recent experience with {{company_name}}? Share your feedback: {{survey_link}}.'],
        ],
        'Appointments & Bookings' => [
            ['Appointment Confirmation', 'Hello {{first_name}}, your appointment with {{company_name}} is confirmed for {{appointment_date}} at {{appointment_time}}.'],
            ['Appointment Reminder', 'Reminder: You have an appointment on {{appointment_date}} at {{appointment_time}}. Location: {{location}}. {{company_name}}.'],
            ['Appointment Rescheduled', 'Dear {{first_name}}, your appointment has been moved to {{new_date}} at {{new_time}}. We apologise for any inconvenience.'],
            ['Booking Cancellation', 'Hello {{first_name}}, your booking {{booking_reference}} has been cancelled. For assistance, contact {{support_number}}.'],
            ['Missed Appointment Follow-up', 'Hi {{first_name}}, we missed you at your appointment. Please contact {{support_number}} to arrange another suitable time.'],
        ],
        'Events & Invitations' => [
            ['Event Invitation', 'Hello {{first_name}}, you are invited to {{event_name}} on {{event_date}} at {{venue}}. Register: {{registration_link}}.'],
            ['Event Reminder', 'Reminder: {{event_name}} takes place on {{event_date}} at {{event_time}}. Venue: {{venue}}. See you there!'],
            ['Event Registration Confirmation', 'Thank you for registering for {{event_name}}. Your registration ID is {{registration_id}}. We look forward to seeing you.'],
            ['Event Postponement', 'Important: {{event_name}} has been postponed to {{new_date}}. We apologise for the inconvenience. {{company_name}}.'],
            ['Event Thank You', 'Thank you for attending {{event_name}}! We hope you enjoyed it. Share your feedback: {{feedback_link}}.'],
        ],
        'Education & Schools' => [
            ['School Fees Reminder', 'Dear Parent, school fees of GHS {{amount}} for {{student_name}} are due on {{due_date}}. Thank you. {{school_name}}.'],
            ['School Closure Notice', 'Dear Parent, {{school_name}} will be closed on {{closure_date}} due to {{reason}}. Classes resume on {{resumption_date}}.'],
            ['PTA Meeting Invitation', 'Dear Parent, you are invited to our PTA meeting on {{meeting_date}} at {{meeting_time}}. Venue: {{venue}}. {{school_name}}.'],
            ['Examination Reminder', 'Dear Parent, examinations at {{school_name}} begin on {{exam_date}}. Please ensure your child is prepared and punctual.'],
            ['School Reopening', 'Dear Parent, {{school_name}} reopens on {{reopening_date}}. We look forward to welcoming all pupils back.'],
        ],
        'Healthcare' => [
            ['Medical Appointment Reminder', 'Hello {{first_name}}, this is a reminder of your appointment at {{facility_name}} on {{appointment_date}} at {{appointment_time}}.'],
            ['Medical Appointment Confirmation', 'Dear {{first_name}}, your appointment at {{facility_name}} is confirmed for {{appointment_date}}. For enquiries, call {{contact_number}}.'],
            ['General Health Awareness', 'Your health matters! {{facility_name}} encourages regular health checks and healthy living. Contact us at {{contact_number}}.'],
            ['Clinic Schedule Update', 'Dear patient, {{facility_name}} has updated its clinic hours to {{opening_hours}}. For assistance, call {{contact_number}}.'],
            ['Patient Feedback', 'Thank you for visiting {{facility_name}}. We value your feedback. Please complete our short survey: {{survey_link}}.'],
        ],
        'Real Estate & Property' => [
            ['New Property Listing', 'Hello {{first_name}}, a new property is available at {{location}} for GHS {{price}}. View details: {{property_link}}.'],
            ['Property Viewing Confirmation', 'Dear {{first_name}}, your property viewing is confirmed for {{viewing_date}} at {{viewing_time}}. Location: {{property_address}}.'],
            ['Rent Payment Reminder', 'Dear {{first_name}}, your rent payment of GHS {{amount}} is due on {{due_date}}. Thank you. {{property_company}}.'],
            ['Property Inspection Notice', 'Dear {{first_name}}, a property inspection is scheduled for {{inspection_date}} at {{inspection_time}}. Contact {{contact_number}} for enquiries.'],
            ['Property Availability Update', 'Hello {{first_name}}, the property at {{location}} is now {{availability_status}}. Contact {{agent_number}} for further details.'],
        ],
        'Surveys & Feedback' => [
            ['Customer Satisfaction Survey', 'Hi {{first_name}}, thank you for choosing {{company_name}}. Please rate your experience: {{survey_link}}.'],
            ['Product Feedback', 'Hello {{first_name}}, we would love your feedback on {{product_name}}. Complete our short survey: {{survey_link}}.'],
            ['Service Rating', 'Dear {{first_name}}, how would you rate our recent service? Reply with a number from 1 (Poor) to 5 (Excellent).'],
            ['Event Feedback', 'Thank you for attending {{event_name}}. Tell us what you think: {{feedback_link}}.'],
            ['Survey Participation Reminder', 'Hello {{first_name}}, your feedback matters. Our survey closes on {{deadline}}. Participate here: {{survey_link}}.'],
        ],
        'General Announcements' => [
            ['Welcome Message', 'Welcome to {{company_name}}, {{first_name}}! We are delighted to have you with us. For assistance, contact {{support_number}}.'],
            ['Holiday Greetings', 'Happy {{holiday_name}} from everyone at {{company_name}}! Wishing you and your family a wonderful celebration.'],
            ['Business Hours Update', 'Dear customer, our business hours have changed to {{business_hours}} effective {{effective_date}}. Thank you. {{company_name}}.'],
            ['Important Announcement', 'Important notice from {{company_name}}: {{announcement_details}}. For enquiries, call {{contact_number}}.'],
            ['Account Activation', 'Hello {{first_name}}, your account with {{company_name}} is now active. Sign in securely at {{login_link}} to get started.'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sortOrder = 0;

        foreach ($this->categories as $name => $description) {
            $sortOrder++;

            $category = TemplateCategory::query()->firstOrCreate(
                ['client_id' => null, 'slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'sort_order' => $sortOrder],
            );

            foreach ($this->templates[$name] ?? [] as [$templateName, $body]) {
                MessageTemplate::query()->firstOrCreate(
                    [
                        'client_id' => null,
                        'channel' => Channel::Sms->value,
                        'name' => $templateName,
                    ],
                    [
                        'template_category_id' => $category->id,
                        'language' => 'en',
                        'description' => $description,
                        'body' => $body,
                        'status' => TemplateStatus::Active->value,
                    ],
                );
            }
        }
    }
}
