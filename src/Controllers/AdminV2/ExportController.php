<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\AdminV2;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Helpers\ShiftExportHelper;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Psr\Log\LoggerInterface;

class ExportController extends BaseController
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly LoggerInterface $log,
        private readonly ShiftExportHelper $exportHelper
    ) {
//        parent::__construct();
    }

    public function showExportPage(): Response
    {
        if (!$this->auth->can('admin_export') && !$this->auth->can('admin_user')) {
            throw new HttpForbidden();
        }

        return response()->withView(
            'adminv2/export/export.twig',
            [
                'title' => __('Import/Export Data'),
            ]
        );
    }

    public function export(Request $request): Response
    {
//        if (!$this->auth->can('admin_export')) {
//            throw new HttpForbidden();
//        }

        $format = $request->get('format', 'xlsx');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add headers
        $headers = ['Shift Type ID', 'Shift Type Name', 'Shift ID', 'Shift Name', 'Shift Start', 'Shift End', 'Users'];
        foreach ($headers as $col => $header) {
//            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        // Export data
        $this->exportHelper->exportToSpreadsheet($spreadsheet);

        // Create the writer
        $writer = $format === 'csv'
            ? new Csv($spreadsheet)
            : new Xlsx($spreadsheet);

        $filename = 'shifts-export-' . date('Y-m-d') . '.' . $format;

        return response()->download(
            function () use ($writer): void {
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type' => $format === 'csv'
                    ? 'text/csv'
                    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function import(Request $request): Response
    {
        if (!$this->auth->can('admin_export')) {
            throw new HttpForbidden();
        }

        $file = $request->getUploadedFiles()['file'] ?? null;
        if (!$file) {
            return response()->redirectWithError(
                'adminv2/export',
                __('No file uploaded')
            );
        }

        try {
            // TODO: Implement import logic
            // 1. Read the uploaded file
            // 2. Validate the structure
            // 3. Create/update locations if needed
            // 4. Create/update shift types if needed
            // 5. Create/update shifts
            // 6. Associate users with shifts
            $tempPath = tempnam(sys_get_temp_dir(), 'shift_import_');
            $file->moveTo($tempPath);

            $stats = $this->exportHelper->importFromFile($tempPath);
            unlink($tempPath);

//            $message = sprintf(
//                __('Import completed: %d new locations, %d new shift types, %d new shifts, %d updated shifts'),
//                $stats['locations_created'],
//                $stats['angel_types_created'],
//                $stats['shifts_created'],
//                $stats['shifts_updated']
//            );

            $this->log->info('Shifts imported successfully', $stats);

            return response()->redirectWithMessage(
                'adminv2/export',
                __('Import completed successfully')
            );
        } catch (\Exception $e) {
            $this->log->error('Import failed: ' . $e->getMessage());

            return response()->redirectWithError(
                'adminv2/export',
                __('Import failed: ') . $e->getMessage()
            );
        }
    }
}
