<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Claim;
use OpenSpout\Reader\XLSX\Reader;

class UpdateDataCommand extends Command
{
    protected $signature = 'app:update-data-command
        {filename : Name of the CSV or XLSX file in public/}
        {column? : Column to update (optional)}';

    protected $description = 'Update claims data from CSV or XLSX using header columns with transformations';

    public function handle()
    {
        $filename = $this->argument('filename');
        $column   = $this->argument('column');

        $filePath = public_path($filename);

        if (!file_exists($filePath)) {
            $this->error("File not found: $filename");
            return 1;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Read headers
        $headers = $this->getHeaders($filePath, $ext);

        if (empty($headers)) {
            $this->error('Could not read file headers.');
            return 1;
        }

        // Ask user to select column if not provided
        if (!$column) {
            $column = $this->choice(
                'Select column to update (or choose ALL)',
                array_merge(['all'], $headers),
                0
            );
        }

        $column = strtolower($column);
        $mapping = $this->getColumnMapping();

        if ($column !== 'all' && (!isset($mapping[$column]) || !$mapping[$column])) {
            $this->error("Column '$column' is not allowed or not mapped.");
            return 1;
        }

        $updated = 0;
        $notFound = [];
        $duplicates = [];

        if ($ext === 'csv') {
            $this->processCsv($filePath, $updated, $notFound, $duplicates, $column, $mapping);
        } elseif ($ext === 'xlsx') {
            $this->processXlsx($filePath, $updated, $notFound, $duplicates, $column, $mapping);
        } else {
            $this->error('Unsupported file type.');
            return 1;
        }

        // Summary
        $this->info("✅ Done");
        $this->info("Updated: $updated");
        $this->info("Not found: " . count($notFound));
        $this->info("Duplicates: " . count($duplicates));

        return 0;
    }

    private function processCsv($filePath, &$updated, &$notFound, &$duplicates, $column, $mapping)
    {
        $handle = fopen($filePath, 'r');
        $headers = $this->normalizeHeaders(fgetcsv($handle));

        $rowIndex = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            if (count($headers) !== count($row)) continue;

            $rowData = array_combine($headers, $row);
            $this->processRow($rowData, $rowIndex, $updated, $notFound, $duplicates, $column, $mapping);
        }

        fclose($handle);
    }

    private function processXlsx($filePath, &$updated, &$notFound, &$duplicates, $column, $mapping)
    {
        $reader = new Reader();
        $reader->open($filePath);

        $headers = [];
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                $cells = array_map(fn($cell) => $cell->getValue(), $row->getCells());

                if ($rowIndex === 1) {
                    $headers = $this->normalizeHeaders($cells);
                    continue;
                }

                if (count($headers) !== count($cells)) continue;

                $rowData = array_combine($headers, $cells);
                $this->processRow($rowData, $rowIndex, $updated, $notFound, $duplicates, $column, $mapping);
            }
            break; // first sheet only
        }

        $reader->close();
    }

    private function processRow($row, $rowIndex, &$updated, &$notFound, &$duplicates, $column, $mapping)
    {
        $claimNumber = trim((string) ($row['claim_number'] ?? ''));
        $debtorId    = trim((string) ($row['debtor_id'] ?? $row['debtor_nid'] ?? ''));
        $amount      = (float) ($row['claim_amount'] ?? $row['amount'] ?? 0);

        if (!$claimNumber || !$debtorId || !$amount) return;

        $claims = Claim::where('claim_number', $claimNumber)
            ->where('debtor_nid', $debtorId)
            ->whereBetween('amount', [$amount - 0.01, $amount + 0.01])
            ->get();

        if ($claims->count() === 1) {
            $claim = $claims->first();
            $updateData = [];

            if ($column === 'all') {
                // Loop all mapping and apply transformations
                foreach ($mapping as $excelCol => $dbCol) {
                    if (!$dbCol) continue;

                    $value = $row[$excelCol] ?? null;
                    if ($value === null || $value === '') continue;

                    // Apply transformations
                    switch ($dbCol) {
                        case 'debtor_name':
                            $debtorName = trim($row['debtor_name'] ?? '');
                            if (!$debtorName) {
                                $debtorName = trim($row['driver_name'] ?? '');
                            }
                            $updateData['debtor_name'] = $debtorName ?: null;
                            break;
                        case 'debtor_type':
                            $debtorType = trim($row['debtor_type'] ?? '');
                            $updateData['debtor_type'] = $debtorType ? $this->getDebtorType($debtorType) : null;
                            break;
                        case 'city_id':
                            $city = trim($row['city'] ?? '');
                            $updateData['city_id'] = $city ? $this->getCityId($city) : null;
                            break;
                        case 'user_id':
                            $emailOrName = trim($row['employee_email'] ?? '') ?: trim($row['employee_name'] ?? '');
                            $updateData[$dbCol] = $this->getUserId($emailOrName);
                            break;
                        case 'car_type':
                            $make  = trim($row['vehicle_make'] ?? '');
                            $model = trim($row['vehicle_model'] ?? '');
                            $updateData['car_type'] = $make || $model ? trim($make . ' - ' . $model, ' -') : null;
                            break;
//                        case 'return_reason':
//                            $returnReason = trim($row['recovery_reason'] ?? '');
//                            $updateData['return_reason'] = $returnReason ? $this->mapReturnReason($returnReason) : null;
//                            break;
                        case 'accident_date':
                            $accidentDate = $row['accident_date'] ?? null;

                            if ($accidentDate instanceof \DateTimeInterface) {
                                $updateData['accident_date'] = $accidentDate->format('Y-m-d');
                            } elseif (is_string($accidentDate) && trim($accidentDate) !== '') {
                                $updateData['accident_date'] = \Carbon\Carbon::parse(trim($accidentDate))->format('Y-m-d');
                            } else {
                                $updateData['accident_date'] = null;
                            }
                            break;
                        case 'due_date':
                            $dueDate = $row['due_date'] ?? null;

                            if ($dueDate instanceof \DateTimeInterface) {
                                $updateData['due_date'] = $dueDate->format('Y-m-d');
                            } elseif (is_string($dueDate) && trim($dueDate) !== '') {
                                $updateData['due_date'] = \Carbon\Carbon::parse(trim($dueDate))->format('Y-m-d');
                            } else {
                                $updateData['due_date'] = null;
                            }
                            break;
                        default:
                            $updateData[$dbCol] = $value;
                            break;
                    }
                }
            } else {
                $dbCol = $mapping[$column];
                $value = $row[$column] ?? null;
                if ($value === null || $value === '') return;

                // Apply transformations for single column
                switch ($dbCol) {
                    case 'debtor_name':
                        $updateData[$dbCol] = $row['debtor_name'] ?? $row['driver_name'] ?? '';
                        break;
                    case 'debtor_type':
                        $updateData[$dbCol] = $this->getDebtorType($value);
                        break;
                    case 'city_id':
                        $updateData[$dbCol] = $this->getCityId($value);
                        break;
                    case 'user_id':
                        $updateData[$dbCol] = $this->getUserId($row['employee_email'] ?? $row['employee_name'] ?? '');
                        break;
                    case 'car_type':
                        $updateData[$dbCol] = trim(($row['vehicle_make'] ?? '') . ' - ' . ($row['vehicle_model'] ?? ''));
                        break;
//                    case 'return_reason':
//                        $updateData[$dbCol] = $this->mapReturnReason($value);
//                        break;

                    default:
                        $updateData[$dbCol] = $value;
                        break;
                }
            }

            if (!empty($updateData)) {
                $claim->update($updateData);
                $updated++;
            }

        } elseif ($claims->count() > 1) {
            $duplicates[] = [
                'row' => $rowIndex,
                'claim_number' => $claimNumber,
                'debtor_id' => $debtorId,
                'count' => $claims->count(),
            ];
        } else {
            $notFound[] = [
                'row' => $rowIndex,
                'claim_number' => $claimNumber,
                'debtor_id' => $debtorId,
            ];
        }
    }

    private function getColumnMapping()
    {
        return [
            'claim_number'          => 'claim_number',
            'policy_number'         => 'policy_number',
            'sub_claim_number'      => 'sub_claim_number',
            'claim_amount'          => 'amount',
            'claim_type'            => 'type',
//            'recovery_reason'       => 'return_reason',
            'debtor_id'             => 'debtor_nid',
            'debtor_phone_number'   => 'debtor_phone',
            'accident_date'         => 'accident_date',
            'debtor_name'           => 'debtor_name', // can also take driver_name
            'driver_name'           => 'debtor_name', // can also take debtor_name
            'debtor_type'           => 'debtor_type', // needs getDebtorType()
            'city'                  => 'city_id',     // needs getCityId()
            'employee_email'        => 'user_id',     // needs getUserId()
            'employee_name'         => 'user_id',     // needs getUserId()
            'accident_number'       => 'accident_number',
            'vehicle_make'          => 'car_type',    // combined with vehicle_model
            'vehicle_model'         => 'car_type',    // combined with vehicle_make
            'plate_number'          => 'plate_number',
            'remarks'               => 'remark',
            'intimation_date'       => 'due_date',
            'mistake_percentage'    => 'mistake_percentage',
        ];
    }

    private function normalizeHeaders($headers)
    {
        return array_map(function ($h) {
            return strtolower(trim(str_replace([' ', '-', '/', '\\'], '_', $h)));
        }, $headers);
    }

    private function getHeaders($filePath, $ext)
    {
        if ($ext === 'csv') {
            $handle = fopen($filePath, 'r');
            if (!$handle) return [];

            $headers = fgetcsv($handle);
            fclose($handle);
            return $this->normalizeHeaders($headers);
        }

        if ($ext === 'xlsx') {
            $reader = new Reader();
            $reader->open($filePath);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn($cell) => $cell->getValue(), $row->getCells());
                    $reader->close();
                    return $this->normalizeHeaders($cells);
                }
            }
            $reader->close();
        }

        return [];
    }

    /**
     * ==================== HELPERS ====================
     * Implement these based on your DB/models
     */
    private function getDebtorType($type)
    {
        // map Excel debtor type to DB enum
        return strtolower($type);
    }

    private function getCityId($cityName)
    {
        // lookup city by name
        return $cityName ? \App\Models\City::where('name', $cityName)->value('id') : null;
    }

    private function getUserId($emailOrName)
    {
        // lookup user by email or name
        if (!$emailOrName) return null;
        return \App\Models\User::where('email', $emailOrName)
            ->orWhere('name', $emailOrName)
            ->value('id');
    }

//    private function mapReturnReason($value)
//    {
//        $map = [
//            'أنتهاء تاريخ رخصة القيادة' => 2,
//            'استخدام خارج الوثيقة' => 1,
//            'حادث' => 3,
//            // add all possible strings here
//        ];
//
//        return $map[$value] ?? null; // default null if unknown
//    }
}
