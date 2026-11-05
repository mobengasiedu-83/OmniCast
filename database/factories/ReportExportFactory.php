<?php

namespace Database\Factories;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ReportExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'scope' => 'client:0',
            'requested_by' => null,
            'report' => ReportType::Overview,
            'format' => 'csv',
            'status' => ReportExportStatus::Pending,
            'filters' => null,
            'period_start' => null,
            'period_end' => null,
            'source' => 'manual',
            'file_path' => null,
            'file_name' => null,
            'rows' => null,
            'size_bytes' => null,
            'error_message' => null,
            'completed_at' => null,
            'emailed_at' => null,
        ];
    }

    /**
     * Point the scope key at the client the export belongs to.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ReportExport $export): void {
            if ($export->client_id !== null && str_starts_with((string) $export->scope, 'client:')) {
                $export->scope = 'client:'.$export->client_id;
            }
        });
    }

    /**
     * A platform-wide export that reads across every client.
     */
    public function platform(): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => null,
            'scope' => 'platform:all',
        ]);
    }

    /**
     * An export whose file has been written.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportExportStatus::Completed,
            'file_path' => 'reports/omnicast-overview.csv',
            'file_name' => 'omnicast-overview.csv',
            'rows' => 1,
            'size_bytes' => 42,
            'completed_at' => now(),
        ]);
    }
}
