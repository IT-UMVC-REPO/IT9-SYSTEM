<?php

namespace App\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait BuildsDailyChartSeries
{
    /**
     * @template TRecord of object
     *
     * @param  Collection<int, TRecord>  $records
     * @param  callable(TRecord): ?CarbonInterface  $dateResolver
     * @param  callable(TRecord): float|int  $valueResolver
     * @return array{labels: array<int, string>, series: array<int, float>}
     */
    protected function buildDailySeries(CarbonInterface $start, CarbonInterface $end, Collection $records, callable $dateResolver, callable $valueResolver): array
    {
        $days = collect();
        $cursor = Carbon::instance($start);
        $endDate = Carbon::instance($end);

        while ($cursor->lte($endDate)) {
            $days->push($cursor->toDateString());
            $cursor->addDay();
        }

        /** @var array<string, float> $totals */
        $totals = $records->reduce(function (array $carry, object $record) use ($dateResolver, $valueResolver): array {
            $date = $dateResolver($record);

            if ($date === null) {
                return $carry;
            }

            $day = $date->toDateString();
            $carry[$day] = round(($carry[$day] ?? 0) + (float) $valueResolver($record), 2);

            return $carry;
        }, []);

        return [
            'labels' => $days->map(fn (string $day): string => Carbon::parse($day)->format('M j'))->all(),
            'series' => $days->map(fn (string $day): float => round((float) ($totals[$day] ?? 0), 2))->all(),
        ];
    }
}
