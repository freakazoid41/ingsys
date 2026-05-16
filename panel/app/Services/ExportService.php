<?php

namespace App\Services;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export the provided data as an Excel file.
     *
     * @param array $headers
     * @param array $data
     * @param string $filename
     * @param callable|null $valueMapper
     * @return StreamedResponse
     */
    public function exportExcel(array $headers, array $data, string $filename = 'export.xlsx', ?callable $valueMapper = null): StreamedResponse
    {
        if (empty($filename)) {
            $filename = 'export.xlsx';
        }

        if (strtolower(substr($filename, -5)) !== '.xlsx') {
            $filename .= '.xlsx';
        }

        $spreadsheet = new Spreadsheet();
        $worksheet = new Worksheet($spreadsheet, 'Export');
        $spreadsheet->addSheet($worksheet, 0);

        $headerKeys = [];
        $headerLabels = [];

        foreach ($headers as $key => $label) {
            if (is_int($key)) {
                $headerKeys[] = $label;
                $headerLabels[] = $label;
                continue;
            }

            $headerKeys[] = $key;
            $headerLabels[] = $label;
        }

        foreach ($headerLabels as $columnIndex => $label) {
            $coordinate = sprintf('%s%d', Coordinate::stringFromColumnIndex((int) $columnIndex + 1), 1);
            $worksheet->setCellValue($coordinate, $label);
        }

        foreach ($data as $rowIndex => $row) {
            $rowNumber = (int) $rowIndex + 2;

            foreach ($headerKeys as $columnIndex => $key) {
                $columnNumber = (int) $columnIndex + 1;
                $value = null;

                if ($valueMapper !== null) {
                    $value = $valueMapper($row, $key, $headerLabels[$columnIndex], $rowIndex, $columnIndex);
                } else {
                    if (is_array($row) && array_key_exists($key, $row)) {
                        $value = $row[$key];
                    } elseif (is_object($row) && isset($row->{$key})) {
                        $value = $row->{$key};
                    } elseif (is_array($row) && array_key_exists($columnIndex, $row)) {
                        $value = $row[$columnIndex];
                    }
                }

                $coordinate = sprintf('%s%d', Coordinate::stringFromColumnIndex($columnNumber), $rowNumber);
                $worksheet->setCellValue($coordinate, $this->normalizeValue($value));
            }
        }

        foreach ($worksheet->getColumnIterator() as $column) {
            $worksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Normalize values for Excel export.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalizeValue($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_null($value)) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
