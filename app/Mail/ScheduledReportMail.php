<?php

namespace App\Mail;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a scheduled report with its CSV attached.
 *
 * The mailable sends synchronously on purpose: the scheduled command is the
 * queue for this mail, and a synchronous send lets the command mark the export
 * as emailed only once the transport accepted it.
 */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReportExport $export) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your SysUp360 OmniCast {$this->export->report->label()} report",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.scheduled-report',
            with: [
                'title' => $this->export->report->label(),
                'period' => $this->export->periodLabel(),
                'clientName' => $this->export->client?->name,
                'rows' => $this->export->rows,
                'reportsUrl' => $this->export->isPlatformScope()
                    ? route('admin.reports.index')
                    : route('reports.index'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->export->isReady()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(
                (string) config('reports.exports.disk', 'local'),
                (string) $this->export->file_path,
            )
                ->as($this->export->file_name ?? 'omnicast-report.csv')
                ->withMime('text/csv'),
        ];
    }
}
