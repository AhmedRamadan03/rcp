<?php

namespace Tests\Unit\Services;

use App\Services\RecoveryReconciliation\RecoveryWorkbookReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the workbook parsing and scenario-file validation.
 *
 * These run without a database on purpose: the classifier and the writer both
 * need real tables, and this project's test connection cannot currently run the
 * migrations (several of them emit PostgreSQL-only DDL).
 */
class RecoveryBacklogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/recovery-backlog-'.uniqid();
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->directory);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- reader

    #[Test]
    public function it_reads_the_source_workbook_despite_awkward_headers(): void
    {
        // The real file misspells "Settlement" and leaves a trailing space on it,
        // and every Recovery Date carries a leading space.
        $path = $this->workbook(
            ['Claim NO.', 'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Policy No.'],
            [['C1-A/2024-1', 1358, ' 01/08/2024', 'Complete', 'P1-A']],
        );

        $result = (new RecoveryWorkbookReader)->readSource($path);

        $this->assertSame(1, $result['rows']);
        $this->assertSame([], $result['unparsable']);

        $row = $result['claims']['C1-A/2024-1'][0];

        $this->assertSame(2, $row['source_row']);
        $this->assertSame(135800, $row['amount']);
        $this->assertSame('2024-08-01', $row['date']);
        $this->assertSame('complete', $row['settlement']);
        $this->assertSame('P1-A', $row['policy_number']);
    }

    #[Test]
    public function it_ignores_column_order_and_extra_columns(): void
    {
        $path = $this->workbook(
            ['Junk', 'Settlment Type', 'Policy No.', 'Claim NO.', 'Recovery Date', 'Recovery Collected'],
            [['x', 'Partial', 'P9', 'C9/2024-9', '15/03/2026', 250.5]],
        );

        $row = (new RecoveryWorkbookReader)->readSource($path)['claims']['C9/2024-9'][0];

        $this->assertSame(25050, $row['amount']);
        $this->assertSame('2026-03-15', $row['date']);
        $this->assertSame('partial', $row['settlement']);
    }

    #[Test]
    public function it_keeps_identical_rows_within_one_claim(): void
    {
        // 217 such pairs exist in the real file; they are same-day installments,
        // not duplicates, and collapsing them would lose money.
        $path = $this->workbook(
            ['Claim NO.', 'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Policy No.'],
            [
                ['C2/2024-2', 500, ' 05/05/2024', 'Partial', 'P2'],
                ['C2/2024-2', 500, ' 05/05/2024', 'Partial', 'P2'],
            ],
        );

        $rows = (new RecoveryWorkbookReader)->readSource($path)['claims']['C2/2024-2'];

        $this->assertCount(2, $rows);
        $this->assertSame([50000, 50000], array_column($rows, 'amount'));
    }

    #[Test]
    public function it_rounds_amounts_to_whole_halalas(): void
    {
        // Excel hands us values like 23578.799999999999.
        $path = $this->workbook(
            ['Claim NO.', 'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Policy No.'],
            [['C3/2024-3', 23578.799999999999, ' 01/01/2024', 'Complete', 'P3']],
        );

        $row = (new RecoveryWorkbookReader)->readSource($path)['claims']['C3/2024-3'][0];

        $this->assertSame(2357880, $row['amount']);
    }

    #[Test]
    public function it_sets_bad_rows_aside_instead_of_failing(): void
    {
        $path = $this->workbook(
            ['Claim NO.', 'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Policy No.'],
            [
                ['C4/2024-4', 100, ' 01/01/2024', 'Complete', 'P4'],   // good
                ['', 100, ' 01/01/2024', 'Complete', 'P4'],            // no claim number
                ['C4/2024-4', 100, '31/02/2024', 'Complete', 'P4'],    // impossible date
                ['C4/2024-4', 0, ' 01/01/2024', 'Complete', 'P4'],     // zero amount
                ['C4/2024-4', 100, ' 01/01/2024', 'Settled', 'P4'],    // unknown settlement
            ],
        );

        $result = (new RecoveryWorkbookReader)->readSource($path);

        $this->assertSame(5, $result['rows']);
        $this->assertCount(1, $result['claims']['C4/2024-4']);
        $this->assertCount(4, $result['unparsable']);
    }

    #[Test]
    public function it_fails_when_a_required_column_is_missing(): void
    {
        $path = $this->workbook(
            ['Claim NO.', 'Recovery Collected', 'Recovery Date', 'Settlment Type '],
            [['C5/2024-5', 100, ' 01/01/2024', 'Complete']],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing required column\(s\): policy_number/');

        (new RecoveryWorkbookReader)->readSource($path);
    }

    #[Test]
    public function it_fails_on_a_duplicated_column(): void
    {
        $path = $this->workbook(
            ['Claim NO.', 'Claim NO.', 'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Policy No.'],
            [['C6/2024-6', 'C6/2024-6', 100, ' 01/01/2024', 'Complete', 'P6']],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate column/');

        (new RecoveryWorkbookReader)->readSource($path);
    }

    #[Test]
    public function it_reads_a_scenario_file_back(): void
    {
        $path = $this->scenarioFile([
            $this->scenarioRow(order: 1, sourceRow: 2, amount: 600),
            $this->scenarioRow(order: 2, sourceRow: 9, amount: 400),
        ]);

        $claims = (new RecoveryWorkbookReader)->readScenarioFile($path);

        $this->assertSame([77], array_keys($claims));
        $this->assertCount(2, $claims[77]);

        [$first, $second] = $claims[77];

        $this->assertSame('full', $first['scenario']);
        $this->assertSame(2, $first['source_row']);
        $this->assertSame(77, $first['claim_id']);
        $this->assertSame(88, $first['payment_id']);
        $this->assertSame(60000, $first['amount']);
        $this->assertSame('2024-08-01', $first['date']->toDateString());
        $this->assertSame(1, $first['order']);
        $this->assertSame(0, $first['payment_paid_amount']);
        $this->assertSame(5, $first['collector_id']);
        $this->assertSame(40000, $second['amount']);
    }

    // ------------------------------------------------- scenario file guards

    #[Test]
    public function it_refuses_a_file_that_mixes_scenarios(): void
    {
        $path = $this->scenarioFile([
            $this->scenarioRow(order: 1, sourceRow: 2, amount: 1000),
            $this->scenarioRow(order: 1, sourceRow: 3, amount: 1000, claimId: 78, scenario: 'discount'),
        ]);

        $this->artisan('recovery:apply', ['file' => $path])
            ->expectsOutputToContain('File mixes scenarios')
            ->assertFailed();
    }

    #[Test]
    public function it_refuses_a_scenario_that_is_not_applicable(): void
    {
        $path = $this->scenarioFile([
            $this->scenarioRow(order: 1, sourceRow: 2, amount: 1000, scenario: 'still-collecting'),
        ]);

        $this->artisan('recovery:apply', ['file' => $path])
            ->expectsOutputToContain('is not applicable')
            ->assertFailed();
    }

    #[Test]
    public function it_refuses_a_claim_whose_order_is_not_contiguous(): void
    {
        $path = $this->scenarioFile([
            $this->scenarioRow(order: 1, sourceRow: 2, amount: 500),
            $this->scenarioRow(order: 3, sourceRow: 3, amount: 500),
        ]);

        $this->artisan('recovery:apply', ['file' => $path])
            ->expectsOutputToContain('Order must be 1..2')
            ->assertFailed();
    }

    #[Test]
    public function it_refuses_a_file_whose_rows_disagree_on_the_payment(): void
    {
        $path = $this->scenarioFile([
            $this->scenarioRow(order: 1, sourceRow: 2, amount: 500),
            $this->scenarioRow(order: 2, sourceRow: 3, amount: 500, paymentId: 999),
        ]);

        $this->artisan('recovery:apply', ['file' => $path])
            ->expectsOutputToContain('disagree on Payment ID')
            ->assertFailed();
    }

    #[Test]
    public function a_dry_run_reports_what_it_would_write_and_writes_nothing(): void
    {
        $path = $this->scenarioFile([
            $this->scenarioRow(order: 1, sourceRow: 2, amount: 600),
            $this->scenarioRow(order: 2, sourceRow: 9, amount: 400),
        ]);

        // --output keeps the run's reports out of the application's storage.
        $this->artisan('recovery:apply', ['file' => $path, '--output' => $this->directory])
            ->expectsOutputToContain('Dry run: 1 claims, 2 transactions, SAR 1,000.00')
            ->expectsOutputToContain('Dry run — nothing was written')
            ->assertSuccessful();

        $this->assertFileExists($this->directory.'/planned.xlsx');
        $this->assertFileExists($this->directory.'/manifest.json');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function workbook(array $headers, array $rows): string
    {
        $path = $this->directory.'/source-'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function scenarioFile(array $rows): string
    {
        return $this->workbook([
            'Scenario', 'Source Row', 'Claim NO.', 'Claim ID', 'Payment ID', 'Policy No.', 'Matched By',
            'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Order',
            'Claim Amount', 'Payment Amount', 'Payment Paid Amount', 'Claim Status',
            'Claim Total Collected', 'Collector ID', 'Collector Name',
        ], $rows);
    }

    /**
     * @return list<mixed>
     */
    private function scenarioRow(
        int $order,
        int $sourceRow,
        float $amount,
        int $claimId = 77,
        int $paymentId = 88,
        string $scenario = 'full',
    ): array {
        return [
            $scenario, $sourceRow, 'C7/2024-7', $claimId, $paymentId, 'P7', 'claim_number',
            $amount, '2024-08-01', 'complete', $order,
            1000, 1000, 0, 'approved',
            1000, 5, 'Collector',
        ];
    }
}
