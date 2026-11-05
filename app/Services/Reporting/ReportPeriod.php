<?php

namespace App\Services\Reporting;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The date window a report is read over.
 *
 * Both ends are inclusive: `from` is the first second of its day and `to` the
 * last, so a report never misses a recipient that moved late on the closing
 * date. A window is also the unit a scheduled report is mailed for, which is
 * why the previous week and previous month are part of the object.
 */
class ReportPeriod
{
    public Carbon $from;

    public Carbon $to;

    /**
     * `now()` hands back an immutable instance, so the window normalises both
     * ends to a mutable `Carbon` the rest of the reporting code can copy.
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to)
    {
        $this->from = Carbon::instance($from);
        $this->to = Carbon::instance($to);
    }

    /**
     * Read the window from the query string.
     *
     * An absent `to` means "up to now"; an absent `from` means the last
     * `$defaultDays` days ending on `to`. A reversed pair is swapped rather than
     * rejected, so a mistyped date still renders a report.
     */
    public static function fromRequest(Request $request, int $defaultDays = 1): self
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : $to->copy()->subDays(max($defaultDays, 1) - 1)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return new self($from, $to);
    }

    /**
     * Build a window from two dates, expanding them to whole days.
     */
    public static function between(CarbonInterface|string $from, CarbonInterface|string $to): self
    {
        return new self(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());
    }

    /**
     * The calendar week before the current one.
     */
    public static function previousWeek(): self
    {
        $start = now()->subWeek()->startOfWeek();

        return new self($start->copy()->startOfDay(), $start->copy()->endOfWeek()->endOfDay());
    }

    /**
     * The calendar month before the current one.
     */
    public static function previousMonth(): self
    {
        $start = now()->subMonthNoOverflow()->startOfMonth();

        return new self($start->copy()->startOfDay(), $start->copy()->endOfMonth()->endOfDay());
    }

    /**
     * The number of calendar days the window covers.
     */
    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    /**
     * The same length of time immediately before this window.
     */
    public function previous(): self
    {
        $end = $this->from->copy()->subSecond();

        return new self($end->copy()->subDays($this->days() - 1)->startOfDay(), $end->endOfDay());
    }

    /**
     * Describe the window the way a report heading reads it.
     */
    public function label(): string
    {
        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /**
     * The window as the query string carries it.
     *
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    /**
     * The window as the day boundaries an aggregate is keyed by.
     *
     * @return array{0: string, 1: string}
     */
    public function dateRange(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }
}
