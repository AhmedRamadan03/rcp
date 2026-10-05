<?php

namespace App\Services\RecoveryReconciliation;

use Carbon\Carbon;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/**
 * Reads the two workbook shapes this feature deals with:
 *
 *  - the source recovery backlog, whose headers are outside our control
 *    (note the trailing space and misspelling in "Settlment Type ")
 *  - the scenario files produced by recovery:split, whose headers are ours
 *
 * Amounts are returned as integer halalas so comparisons never touch floats.
 */
class RecoveryWorkbookReader
{
    /** Source workbook: normalised header => key in the returned row. */
    private const SOURCE_COLUMNS = [
        'claim no.' => 'claim_number',
        'policy no.' => 'policy_number',
        'recovery collected' => 'amount',
        'recovery date' => 'date',
        'settlment type' => 'settlement',
    ];

    /** Scenario file: normalised header => key in the returned row. */
    private const SCENARIO_COLUMNS = [
        'scenario' => 'scenario',
        'source row' => 'source_row',
        'claim no.' => 'claim_number',
        'claim id' => 'claim_id',
        'payment id' => 'payment_id',
        'policy no.' => 'policy_number',
        'matched by' => 'matched_by',
        'recovery collected' => 'amount',
        'recovery date' => 'date',
        'settlment type' => 'settlement',
        'order' => 'order',
        'claim amount' => 'claim_amount',
        'payment amount' => 'payment_amount',
        'payment paid amount' => 'payment_paid_amount',
        'claim status' => 'claim_status',
        'claim total collected' => 'claim_total',
        'collector id' => 'collector_id',
        'collector name' => 'collector_name',
    ];

    /**
     * Read the source backlog, grouped by claim number.
     *
     * Rows are never deduplicated: identical (amount, date) pairs within one
     * claim are legitimate same-day installments.
     *
     * @return array{rows: int, claims: array<string, list<array<string, mixed>>>, unparsable: list<array<string, mixed>>}
     */
    public function readSource(string $path): array
    {
        $rowCount = 0;
        $claims = [];
        $unparsable = [];

        $this->eachRow($path, self::SOURCE_COLUMNS, function (int $rowNumber, array $raw) use (&$rowCount, &$claims, &$unparsable) {
            $rowCount++;

            $claimNumber = trim((string) ($raw['claim_number'] ?? ''));
            $date = $this->parseDate($raw['date'] ?? null);
            $amount = $this->parseAmount($raw['amount'] ?? null);
            $settlement = strtolower(trim((string) ($raw['settlement'] ?? '')));

            // Held as a Y-m-d string, not a Carbon: 32,600 Carbon instances cost
            // ~30 MB and nothing here needs more than an ISO date, which also
            // sorts correctly as a string.
            $row = [
                'source_row' => $rowNumber,
                'claim_number' => $claimNumber,
                'policy_number' => trim((string) ($raw['policy_number'] ?? '')),
                'amount' => $amount,
                'date' => $date?->toDateString(),
                'settlement' => $settlement,
            ];

            if ($claimNumber === '' || $date === null || $amount === null || ! in_array($settlement, ['partial', 'complete'], true)) {
                $unparsable[] = $row;

                return;
            }

            $claims[$claimNumber][] = $row;
        });

        return ['rows' => $rowCount, 'claims' => $claims, 'unparsable' => $unparsable];
    }

    /**
     * Read a scenario file produced by recovery:split, grouped by claim id.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function readScenarioFile(string $path): array
    {
        $claims = [];

        $this->eachRow($path, self::SCENARIO_COLUMNS, function (int $rowNumber, array $raw) use (&$claims) {
            $date = $this->parseDate($raw['date'] ?? null);
            $amount = $this->parseAmount($raw['amount'] ?? null);

            if ($date === null) {
                throw new RuntimeException("Row {$rowNumber}: Recovery Date could not be parsed.");
            }

            if ($amount === null) {
                throw new RuntimeException("Row {$rowNumber}: Recovery Collected must be a positive amount.");
            }

            $claimId = (int) $raw['claim_id'];

            $claims[$claimId][] = [
                'file_row' => $rowNumber,
                'scenario' => trim((string) $raw['scenario']),
                'source_row' => (int) $raw['source_row'],
                'claim_number' => trim((string) $raw['claim_number']),
                'claim_id' => $claimId,
                'payment_id' => (int) $raw['payment_id'],
                'matched_by' => trim((string) ($raw['matched_by'] ?? '')),
                'amount' => $amount,
                'date' => $date,
                'settlement' => strtolower(trim((string) $raw['settlement'])),
                'order' => (int) $raw['order'],
                'claim_amount' => $this->parseAmount($raw['claim_amount'] ?? null),
                'payment_paid_amount' => $this->parseAmount($raw['payment_paid_amount'] ?? null, allowZero: true),
                'claim_total' => $this->parseAmount($raw['claim_total'] ?? null),
                'collector_id' => ($raw['collector_id'] ?? null) ? (int) $raw['collector_id'] : null,
            ];
        });

        return $claims;
    }

    /**
     * Stream the first sheet, handing each row's mapped values to $handler.
     *
     * OpenSpout's RowIterator opens its own XMLReader on the zip stream in
     * rewind() and only closes it once valid() sees end-of-file — Reader::close()
     * does not reach it. Breaking or throwing out of the loop therefore leaves
     * the workbook locked. So a failure is captured, the remaining rows are
     * drained cheaply, and the exception is rethrown once the file is closed.
     *
     * @param  array<string, string>  $columns
     * @param  callable(int, array<string, mixed>): void  $handler
     */
    private function eachRow(string $path, array $columns, callable $handler): void
    {
        $reader = new Reader;
        $reader->open($path);
        $failure = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $indexes = null;
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    if ($failure !== null) {
                        continue; // draining; see above
                    }

                    $rowNumber++;
                    $cells = $row->toArray();

                    try {
                        if ($indexes === null) {
                            $indexes = $this->mapHeaders($cells, $columns);

                            continue;
                        }

                        $mapped = [];
                        foreach ($indexes as $key => $index) {
                            $mapped[$key] = $cells[$index] ?? null;
                        }

                        $handler($rowNumber, $mapped);
                    } catch (RuntimeException $exception) {
                        $failure = $exception;
                    }
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Resolve each required header to its column index.
     *
     * @param  array<string, string>  $columns
     * @return array<string, int>
     */
    private function mapHeaders(array $headerCells, array $columns): array
    {
        $seen = [];

        foreach ($headerCells as $index => $cell) {
            $header = strtolower(preg_replace('/\s+/', ' ', trim((string) $cell)));

            if (! isset($columns[$header])) {
                continue; // unnamed and irrelevant columns are ignored
            }

            $key = $columns[$header];

            if (isset($seen[$key])) {
                throw new RuntimeException("Duplicate column for \"{$header}\".");
            }

            $seen[$key] = $index;
        }

        $missing = array_diff(array_values($columns), array_keys($seen));

        if ($missing !== []) {
            throw new RuntimeException('Missing required column(s): '.implode(', ', $missing).'.');
        }

        return $seen;
    }

    /**
     * Amounts arrive as numbers; convert to integer halalas.
     */
    private function parseAmount(mixed $value, bool $allowZero = false): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $halalas = (int) round(((float) $value) * 100);

        if ($halalas < 0 || ($halalas === 0 && ! $allowZero)) {
            return null;
        }

        return $halalas;
    }

    /**
     * Every Recovery Date in the source is a d/m/Y string carrying a leading
     * space. Scenario files we write use Y-m-d. Date-styled cells are tolerated
     * so a hand-edited scenario file still reads.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $parts)) {
            [, $day, $month, $year] = array_map('intval', $parts);
        } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $value, $parts)) {
            [, $year, $month, $day] = array_map('intval', $parts);
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day) : null;
    }
}
