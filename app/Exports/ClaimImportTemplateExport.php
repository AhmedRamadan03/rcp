<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ClaimImportTemplateExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    /**
     * @return array
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Policy Number',           
            'Claim Number',            
            'Sub Claim Number',        
            'Liability',               
            'OD Vehicle Vehicle Make', 
            'OD Vehicle Vehicle Model',
            'Date of loss',            
            'OD Plate number',         
            'Plate no.',               
            'ID number',               
            'Driver name',             
            'Accident number',         
            'OD Mobile no.',           
            'Area',                    
            'Claim Registration Date', 
            'Outstanding Recovery',    
            'Recovered Amount',        
            'Policy holder',           
            'Risk Type',               
            'Type',                    
            'Employee name',           
            'Claim Type',              
            'Intimation Date',         
            'Debtor Type',             
            'Recovery Reason',         
            'Remarks'                  
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Claims Import Template';
    }
}