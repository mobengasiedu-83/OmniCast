<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report file requested by a user or scheduled by the platform.
 *
 * The row is the contract between the screen and the worker: the request writes
 * it as `pending`, `GenerateReportExport` fills in the file and flips it to
 * `completed`, and the download route serves the file only in that state.
 *
 * @property int $id
 * @property int|null $client_id
 * @property string $scope
 * @property int|null $requested_by
 * @property ReportType $report
 * @property string $format
 * @property ReportExportStatus $status
 * @property array<string, mixed>|null $filters
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string $source
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $rows
 * @property int|null $size_bytes
 * @property string|null $error_message
 * @property Carbon|null $completed_at
 * @property Carbon|null $emailed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'scope', 'requested_by', 'report', 'format', 'status', 'filters',
    'period_start', 'period_end', 'source', 'file_path', 'file_name', 'rows',
    'size_bytes', 'error_message', 'completed_at', 'emailed_at',
])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report' => ReportType::class,
            'status' => ReportExportStatus::class,
            'filters' => 'array',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'rows' => 'integer',
            'size_bytes' => 'integer',
            'completed_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Whether the generated file can be served.
     */
    public function isReady(): bool
    {
        return $this->status->isDownloadable() && $this->file_path !== null;
    }

    /**
     * Whether the export reads across every client.
     */
    public function isPlatformScope(): bool
    {
        return str_starts_with($this->scope, 'platform');
    }

    /**
     * Describe the window the file covers, for the screen and the email.
     */
    public function periodLabel(): string
    {
        if ($this->period_start === null || $this->period_end === null) {
            return 'As requested';
        }

        return $this->period_start->format('j M Y').' – '.$this->period_end->format('j M Y');
    }
}
