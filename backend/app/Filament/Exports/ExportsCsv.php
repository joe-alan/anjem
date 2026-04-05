<?php

namespace App\Filament\Exports;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    protected function getExportCsvAction(): Action
    {
        return Action::make('export_csv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (): StreamedResponse {
                $resource = static::getResource();
                $columns = $resource::getExportColumns();
                $query = $this->getFilteredTableQuery();
                $filename = class_basename($resource::getModel()) . '_' . now()->format('Y-m-d_His') . '.csv';

                return Response::streamDownload(function () use ($columns, $query) {
                    $handle = fopen('php://output', 'w');

                    // UTF-8 BOM for Excel compatibility
                    fwrite($handle, "\xEF\xBB\xBF");

                    // Header row
                    fputcsv($handle, array_values($columns));

                    // Data rows — chunked at 500
                    $query->chunk(500, function ($records) use ($handle, $columns) {
                        foreach ($records as $record) {
                            $row = [];
                            foreach (array_keys($columns) as $key) {
                                $row[] = data_get($record, $key) ?? '';
                            }
                            fputcsv($handle, $row);
                        }
                    });

                    fclose($handle);
                }, $filename, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);
            });
    }
}
