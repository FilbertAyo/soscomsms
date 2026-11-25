<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use RuntimeException;

class SpreadsheetReader
{
    /**
     * Read the first worksheet. CSV and TXT stay comma-separated.
     * Numeric cells are plain digits so phone numbers are not scientific notation.
     *
     * @return list<list<string>>
     */
    public function rows(string $path, string $extension): array
    {
        $reader = IOFactory::createReader($this->readerType($extension));
        $reader->setReadDataOnly(true);

        if ($reader instanceof Csv) {
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
        }

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            $rows = [];

            for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
                $row = [];
                for ($column = 1; $column <= $highestColumn; $column++) {
                    $row[] = $this->cellText($sheet->getCell(Coordinate::stringFromColumnIndex($column).$rowNumber));
                }
                $rows[] = $row;
            }

            return $rows;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function readerType(string $extension): string
    {
        return match (strtolower($extension)) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            'csv', 'txt' => 'Csv',
            default => throw new RuntimeException('Upload a CSV or Excel file.'),
        };
    }

    private function cellText(Cell $cell): string
    {
        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

        if ($value instanceof RichText) {
            return trim($value->getPlainText());
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                return '';
            }

            return sprintf('%.0f', $value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value)) {
            return trim($value);
        }

        return '';
    }
}
