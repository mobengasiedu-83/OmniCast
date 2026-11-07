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
 * Ten categories and fifty starting points per channel mirror the published
 * catalogue, so a client never faces an empty library on its first campaign.
 * The SMS staging is the short form; the email staging adds a subject line and
 * the greeting, call to action, sign-off and unsubscribe footer a broadcast
 * email needs. Every entry is a system template (`client_id` null): a tenant
 * that edits one keeps the catalogue intact because the editor copies it into
 * the tenant instead.
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
     * The email staging of the same catalogue, keyed by category.
     *
     * Each entry is the template name, its subject line, the body paragraphs,
     * the call-to-action label and — when the message sells or surveys — the
     * flag that adds the postal address and unsubscribe footer that marketing
     * mail requires.
     *
     * @var array<string, list<array{0: string, 1: string, 2: list<string>, 3: string, 4?: bool}>>
     */
    protected array $emailTemplates = [
        'Marketing & Promotions' => [
            ['Special Discount', 'Your exclusive {{discount}}% offer from {{company_name}}', [
                'We have a special offer for you: enjoy {{discount}}% off {{product_or_service}} until {{expiry_date}}.',
                'Explore the offer at {{link}} before it ends.',
            ], 'Shop the offer', true],
            ['New Product Launch', 'Introducing {{product_name}}', [
                'We are pleased to introduce {{product_name}}, now available from {{company_name}}.',
                'Discover the features, benefits and availability at {{link}}.',
            ], 'Explore the product', true],
            ['Flash Sale', 'Limited-time offer ends {{expiry_date}}', [
                'Our flash sale is live, with savings of up to {{discount}}% on selected items.',
                'Browse eligible products and offer terms at {{link}}.',
            ], 'View the sale', true],
            ['Loyalty Reward', 'A thank-you reward for you, {{first_name}}', [
                'Thank you for being a valued customer of {{company_name}}. Your loyalty reward is {{reward_details}}.',
                'Redeem your reward by {{expiry_date}} using {{link}}.',
            ], 'Claim reward', true],
            ['Seasonal Promotion', 'Celebrate {{occasion}} with us', [
                'To mark {{occasion}}, we are offering {{offer_details}} to eligible customers.',
                'The promotion runs until {{expiry_date}}. Full details are available at {{link}}.',
            ], 'See the offer', true],
        ],
        'Finance & Collections' => [
            ['Payment Due Reminder', 'Payment reminder: GHS {{amount}} due {{due_date}}', [
                'This is a friendly reminder that your payment of GHS {{amount}} is due on {{due_date}}.',
                'Please use your usual verified payment channel. If you have already paid, kindly disregard this reminder.',
            ], 'View account'],
            ['Overdue Payment', 'Account payment follow-up', [
                'Our records indicate an outstanding amount of GHS {{amount}} on your account.',
                'Please review your account at {{portal_link}} or contact {{support_number}} if you need clarification or have already paid.',
            ], 'Review balance'],
            ['Payment Confirmation', 'Payment received - {{transaction_id}}', [
                'We confirm receipt of your payment of GHS {{amount}} on {{payment_date}}.',
                'Your transaction reference is {{transaction_id}}. Keep this message for your records.',
            ], 'View receipt'],
            ['Instalment Reminder', 'Upcoming instalment due {{due_date}}', [
                'Your next scheduled instalment of GHS {{amount}} falls due on {{due_date}}.',
                'Please check your repayment schedule at {{portal_link}} and contact us if you have any questions.',
            ], 'View schedule'],
            ['Statement Available', 'Your account statement is ready', [
                'Your latest account statement for {{statement_period}} is available in your secure account.',
                'For your privacy, we have not attached financial details to this email. Sign in using your usual trusted portal.',
            ], 'View statement'],
        ],
        'Customer Service' => [
            ['Service Interruption', 'Service update: {{service_name}}', [
                'We are currently experiencing an interruption affecting {{service_name}} in {{affected_area}}.',
                'Our team is investigating. We will share an update by {{update_time}} where possible. We apologise for the disruption.',
            ], 'View status'],
            ['Service Restored', '{{service_name}} has been restored', [
                'Service for {{service_name}} has now been restored following the earlier interruption.',
                'If you are still experiencing difficulties, contact {{support_number}} and quote {{incident_reference}}.',
            ], 'Get support'],
            ['Complaint Acknowledgement', 'We received your request - {{ticket_number}}', [
                'Thank you for contacting {{company_name}}. We have logged your request under ticket {{ticket_number}}.',
                'Our team will review it and provide an update through your preferred contact channel.',
            ], 'Track request'],
            ['Complaint Resolution', 'Update on ticket {{ticket_number}}', [
                'Your support ticket {{ticket_number}} has been marked resolved following our review.',
                'If the issue persists, please reply to this email or contact {{support_number}} so we can reopen the case.',
            ], 'View ticket'],
            ['Customer Satisfaction', 'How did we do, {{first_name}}?', [
                'Thank you for your recent interaction with {{company_name}}.',
                'We would appreciate your feedback through our short survey at {{survey_link}}.',
            ], 'Share feedback'],
        ],
        'Appointments & Bookings' => [
            ['Appointment Confirmation', 'Appointment confirmed for {{appointment_date}}', [
                'Your appointment with {{company_name}} is confirmed for {{appointment_date}} at {{appointment_time}}.',
                'Location or joining details: {{location_or_link}}. Please keep your reference {{booking_reference}}.',
            ], 'View booking'],
            ['Appointment Reminder', 'Reminder: your appointment is coming up', [
                'This is a reminder of your appointment on {{appointment_date}} at {{appointment_time}}.',
                'If you need to reschedule, use {{manage_booking_link}} or contact {{support_number}}.',
            ], 'Manage appointment'],
            ['Appointment Rescheduled', 'Your appointment has been rescheduled', [
                'Your appointment has been moved to {{new_date}} at {{new_time}}.',
                'We apologise for any inconvenience. The updated details are available at {{manage_booking_link}}.',
            ], 'View updated booking'],
            ['Booking Cancellation', 'Booking {{booking_reference}} cancelled', [
                'Your booking {{booking_reference}} has been cancelled as requested or notified.',
                'If this was unexpected, please contact {{support_number}} for assistance.',
            ], 'Contact support'],
            ['Missed Appointment Follow-up', 'Would you like to reschedule?', [
                'We noticed that you could not attend your appointment on {{appointment_date}}.',
                'If you would still like to meet, select a new time at {{manage_booking_link}}.',
            ], 'Book another time'],
        ],
        'Events & Invitations' => [
            ['Event Invitation', 'You are invited: {{event_name}}', [
                'We would be delighted to welcome you to {{event_name}} on {{event_date}} at {{venue}}.',
                'Find the programme and registration details at {{registration_link}}.',
            ], 'Register now'],
            ['Event Reminder', 'Reminder: {{event_name}} on {{event_date}}', [
                'Your event {{event_name}} is coming up on {{event_date}} at {{event_time}}.',
                'Venue or access details: {{venue_or_link}}. We look forward to seeing you.',
            ], 'View event details'],
            ['Registration Confirmation', 'Registration confirmed for {{event_name}}', [
                'Thank you for registering for {{event_name}}. Your registration ID is {{registration_id}}.',
                'Please keep this confirmation and review the event details at {{event_link}}.',
            ], 'View registration'],
            ['Event Postponement', 'Schedule change: {{event_name}}', [
                'We regret to inform you that {{event_name}} has been postponed.',
                'The new date is {{new_date}}. Please check {{event_link}} for updates and contact {{support_number}} with questions.',
            ], 'See update'],
            ['Event Thank You', 'Thank you for joining {{event_name}}', [
                'Thank you for attending {{event_name}}. We hope you found the event valuable.',
                'We would appreciate your feedback at {{feedback_link}} and hope to see you again.',
            ], 'Give feedback'],
        ],
        'Education & Schools' => [
            ['School Fees Reminder', 'School fee reminder for {{student_name}}', [
                'The outstanding school fees for {{student_name}} are GHS {{amount}}, due on {{due_date}}.',
                'Please use the approved school payment channel or contact {{school_contact}} with questions.',
            ], 'View payment details'],
            ['School Closure Notice', 'Important school notice: {{closure_date}}', [
                '{{school_name}} will be closed on {{closure_date}} due to {{reason}}.',
                'Classes are expected to resume on {{resumption_date}}. We will communicate any changes.',
            ], 'View school notice'],
            ['PTA Meeting Invitation', 'Invitation to PTA meeting - {{meeting_date}}', [
                'You are invited to the {{school_name}} PTA meeting on {{meeting_date}} at {{meeting_time}}.',
                'Venue: {{venue}}. Your participation is appreciated.',
            ], 'View meeting details'],
            ['Examination Reminder', 'Upcoming examinations begin {{exam_date}}', [
                'Examinations at {{school_name}} begin on {{exam_date}}.',
                'Please review the timetable at {{timetable_link}} and help your child prepare and arrive on time.',
            ], 'View timetable'],
            ['School Reopening', 'Welcome back: school reopens {{reopening_date}}', [
                'We look forward to welcoming learners back to {{school_name}} on {{reopening_date}}.',
                'Please review the term information and required items at {{school_link}}.',
            ], 'View term details'],
        ],
        'Healthcare' => [
            ['Medical Appointment Reminder', 'Appointment reminder - {{appointment_date}}', [
                'This is a reminder of your appointment at {{facility_name}} on {{appointment_date}} at {{appointment_time}}.',
                'For directions or changes, please contact {{contact_number}}. No medical details are included for your privacy.',
            ], 'Manage appointment'],
            ['Medical Appointment Confirmation', 'Your appointment is confirmed', [
                'Your booking at {{facility_name}} is confirmed for {{appointment_date}} at {{appointment_time}}.',
                'Please refer to booking {{booking_reference}} when contacting us.',
            ], 'View booking'],
            ['General Health Awareness', 'A friendly health reminder from {{facility_name}}', [
                'Taking time for preventive care and healthy daily habits can support wellbeing.',
                'For general information about our services, visit {{facility_link}}. This message is not individual medical advice.',
            ], 'Learn more'],
            ['Clinic Schedule Update', 'Updated clinic hours', [
                'Please note that {{facility_name}} will operate on the following schedule from {{effective_date}}: {{opening_hours}}.',
                'For assistance, contact {{contact_number}} before travelling.',
            ], 'View opening hours'],
            ['Patient Feedback', 'Tell us about your visit', [
                'Thank you for visiting {{facility_name}}. We would value feedback on your service experience.',
                'Please avoid including medical details in the survey unless the secure form specifically requests them. {{survey_link}}',
            ], 'Share feedback'],
        ],
        'Real Estate & Property' => [
            ['New Property Listing', 'New listing in {{location}}', [
                'A new property is available in {{location}} with an advertised price of GHS {{price}}.',
                'View photos, specifications and current availability at {{property_link}}.',
            ], 'View property'],
            ['Viewing Confirmation', 'Property viewing confirmed', [
                'Your viewing at {{property_address}} is scheduled for {{viewing_date}} at {{viewing_time}}.',
                'Your contact person is {{agent_name}} at {{agent_number}}.',
            ], 'View appointment'],
            ['Rent Reminder', 'Rent payment reminder - {{due_date}}', [
                'This is a reminder that rent of GHS {{amount}} is due on {{due_date}} for your tenancy.',
                'Please use the agreed payment channel and contact {{property_company}} if your records differ.',
            ], 'View tenancy'],
            ['Inspection Notice', 'Property inspection notice', [
                'An inspection is planned for {{inspection_date}} at {{inspection_time}} at {{property_address}}.',
                'Please contact {{contact_number}} if you need to discuss access or the schedule.',
            ], 'Contact management'],
            ['Availability Update', 'Update on property at {{location}}', [
                'The listing at {{location}} is currently marked {{availability_status}}.',
                'For the latest details or alternative properties, contact {{agent_number}}.',
            ], 'Browse listings'],
        ],
        'Surveys & Feedback' => [
            ['Customer Satisfaction Survey', 'Tell us about your experience', [
                'Thank you for choosing {{company_name}}. Your feedback helps us improve our service.',
                'Please complete our brief survey at {{survey_link}}. Participation is voluntary.',
            ], 'Take survey', true],
            ['Product Feedback', 'How is {{product_name}} working for you?', [
                'We would like to hear your thoughts about {{product_name}}.',
                'Share your experience at {{survey_link}} to help guide future improvements.',
            ], 'Share feedback', true],
            ['Service Rating', 'Rate your recent service', [
                'How would you rate your recent experience with {{company_name}}?',
                'Use {{rating_link}} to give a rating from 1 (poor) to 5 (excellent).',
            ], 'Rate service', true],
            ['Event Feedback', 'Your feedback on {{event_name}}', [
                'Thank you for participating in {{event_name}}.',
                'Tell us what worked well and what we can improve using {{feedback_link}}.',
            ], 'Give feedback', true],
            ['Survey Reminder', 'Reminder: survey closes {{deadline}}', [
                'Our feedback survey closes on {{deadline}}, and we would welcome your views.',
                'If you have already completed it, thank you. Otherwise, visit {{survey_link}}.',
            ], 'Complete survey', true],
        ],
        'General Announcements' => [
            ['Welcome Message', 'Welcome to {{company_name}}, {{first_name}}', [
                'We are delighted to welcome you to {{company_name}}.',
                'You can find useful account information and support resources at {{welcome_link}}.',
            ], 'Get started'],
            ['Holiday Greetings', 'Warm wishes for {{holiday_name}}', [
                'Everyone at {{company_name}} wishes you a wonderful {{holiday_name}}.',
                'Thank you for being part of our community. We look forward to serving you.',
            ], 'Visit our website'],
            ['Business Hours Update', 'Our business hours are changing', [
                'From {{effective_date}}, our opening hours will be {{business_hours}}.',
                'Please plan visits or calls accordingly. For assistance, contact {{contact_number}}.',
            ], 'View hours'],
            ['Important Announcement', 'Important update from {{company_name}}', [
                'We would like to share the following update: {{announcement_details}}.',
                'For full information and any next steps, visit {{announcement_link}} or contact {{contact_number}}.',
            ], 'Read announcement'],
            ['Account Activation', 'Your {{company_name}} account is active', [
                'Your account is now active and ready to use.',
                'For security, sign in through your usual trusted portal at {{login_link}}. Never share your password or verification codes.',
            ], 'Sign in'],
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
                $template = MessageTemplate::query()->firstOrCreate(
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

                $this->rememberVariables($template);
            }

            foreach ($this->emailTemplates[$name] ?? [] as $spec) {
                [$templateName, $subject, $paragraphs, $ctaLabel] = $spec;

                $template = MessageTemplate::query()->firstOrCreate(
                    [
                        'client_id' => null,
                        'channel' => Channel::Email->value,
                        'name' => $templateName,
                    ],
                    [
                        'template_category_id' => $category->id,
                        'language' => 'en',
                        'description' => $description,
                        'subject' => $subject,
                        'body' => $this->emailBody($paragraphs, $ctaLabel, (bool) ($spec[4] ?? false)),
                        'status' => TemplateStatus::Active->value,
                    ],
                );

                $this->rememberVariables($template);
            }
        }
    }

    /**
     * Record the placeholders a template expects so the editor can offer them
     * as chips. The model fills this in whenever it saves, but this seeder runs
     * without model events, and a catalogue entry published before the list
     * existed is filled in here rather than left blank.
     */
    protected function rememberVariables(MessageTemplate $template): void
    {
        if ($template->variables !== null) {
            return;
        }

        $template->forceFill(['variables' => $template->detectVariables()])->saveQuietly();
    }

    /**
     * Wrap the catalogue copy in the markup a broadcast email needs: the
     * greeting, the body paragraphs, the call to action and the sign-off. A
     * marketing or survey message also carries the postal address and
     * unsubscribe line its footer requires.
     *
     * @param  list<string>  $paragraphs
     */
    protected function emailBody(array $paragraphs, string $ctaLabel, bool $marketing): string
    {
        $html = ['<p>Dear {{first_name}},</p>'];

        foreach ($paragraphs as $paragraph) {
            $html[] = '<p>'.$paragraph.'</p>';
        }

        $html[] = '<p><a href="{{cta_link}}">'.$ctaLabel.'</a></p>';
        $html[] = '<p>Kind regards,<br>{{company_name}}<br>{{support_email}} | {{support_number}}</p>';

        if ($marketing) {
            $html[] = '<p style="font-size:12px;color:#6b7280;">{{company_address}} | <a href="{{unsubscribe_link}}">Unsubscribe</a></p>';
        }

        return implode("\n", $html);
    }
}
