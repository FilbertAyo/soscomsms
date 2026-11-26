<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignRecipientStatus;
use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DeliveryReportController extends Controller
{
    use ResolvesCustomer;

    private const RECENT_DAYS = 30;

    private const ARCHIVE_ROW_LIMIT = 50000;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $reports = $this->filtered($customer, $request)
            ->paginate(PaginationMeta::perPage($request, 10, 500));

        return response()->json([
            'reports' => $reports->getCollection()->map->toReportArray()->values(),
            'meta' => PaginationMeta::from($reports),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->streamDownload(function () use ($customer, $request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Recipient',
                'Campaign',
                'Sender ID',
                'Message',
                'Status',
                'Sent at',
            ]);

            $exported = 0;
            $this->filtered($customer, $request)
                ->lazyById(200)
                ->each(function (Delivery $row) use ($handle, &$exported): mixed {
                    if ($exported >= 10000) {
                        return false;
                    }

                    $report = $row->toReportArray();
                    fputcsv($handle, [
                        $report['phone'],
                        $report['campaignName'],
                        $report['senderId'],
                        $report['message'],
                        $this->reportStatus($report['status']),
                        $this->formatReportTime($row->sent_at),
                    ]);
                    $exported++;

                    return null;
                });

            fclose($handle);
        }, 'delivery-reports.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function archive(Request $request): StreamedResponse|JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $query = $this->filtered($customer, $request, false)
            ->whereBetween('sent_at', [$from, $to]);
        $count = (clone $query)->count();

        if ($count === 0) {
            return response()->json([
                'message' => 'No delivery reports in that date range.',
            ], 422);
        }

        if ($count > self::ARCHIVE_ROW_LIMIT) {
            return response()->json([
                'message' => 'That range has '.number_format($count).' messages. Download at most '.number_format(self::ARCHIVE_ROW_LIMIT).' at a time by choosing a shorter range.',
            ], 422);
        }

        $filename = 'delivery-reports-'.$from->toDateString().'-to-'.$to->toDateString().'.zip';

        return response()->streamDownload(function () use ($query): void {
            $this->streamArchiveZip($query);
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @return Builder<Delivery>
     */
    private function filtered(Customer $customer, Request $request, bool $recentOnly = true): Builder
    {
        return Delivery::query()
            ->with(['campaignRecipient.campaign', 'campaignRecipient.message'])
            ->whereHas('campaignRecipient.campaign', function (Builder $query) use ($customer): void {
                $query->where('customer_id', $customer->id);
            })
            ->when($request->filled('campaign_id'), function (Builder $query) use ($request): void {
                $query->whereHas('campaignRecipient', function (Builder $inner) use ($request): void {
                    $inner->where('campaign_id', $request->integer('campaign_id'));
                });
            })
            ->when($request->filled('sender_id'), function (Builder $query) use ($request): void {
                $query->whereHas('campaignRecipient.campaign', function (Builder $inner) use ($request): void {
                    $inner->where('sender_name', $request->string('sender_id')->toString());
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $status = $request->string('status')->toString();
                $statuses = $status === CampaignRecipientStatus::Sent->value
                    ? [CampaignRecipientStatus::Sent->value, CampaignRecipientStatus::Unknown->value]
                    : [$status];

                $query->whereHas('campaignRecipient', function (Builder $inner) use ($statuses): void {
                    $inner->whereIn('status', $statuses);
                });
            })
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim($request->string('q')->toString());
                $digits = preg_replace('/\D+/', '', $term) ?? '';
                if (strlen($term) < 3 && strlen($digits) < 3) {
                    return;
                }

                $tail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
                $query->whereHas('campaignRecipient', function (Builder $inner) use ($term, $tail, $digits): void {
                    $inner->where(function (Builder $phones) use ($term, $tail, $digits): void {
                        if (strlen($digits) >= 3) {
                            $phones->where('phone', 'like', '%'.$tail);
                        } else {
                            $phones->where('phone', 'like', $term.'%');
                        }
                    });
                });
            })
            ->when($recentOnly, function (Builder $query) use ($request): void {
                $query->where('sent_at', '>=', now()->subDays(self::RECENT_DAYS)->startOfDay());

                if ($request->filled('date')) {
                    $query->whereDate('sent_at', $request->date('date'));
                }
            })
            ->latest('id');
    }

    /**
     * @param  Builder<Delivery>  $query
     */
    private function streamArchiveZip(Builder $query): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Delivery reports');
        $sheet->fromArray([
            'Recipient',
            'Campaign',
            'Sender ID',
            'Message',
            'Status',
            'Sent at',
        ], null, 'A1');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ]);
        $sheet->freezePane('A2');

        foreach (['A' => 20, 'B' => 28, 'C' => 16, 'D' => 64, 'E' => 14, 'F' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $rowNumber = 2;
        $query->lazyById(200)->each(function (Delivery $row) use ($sheet, &$rowNumber): void {
            $report = $row->toReportArray();
            $values = [
                'A' => $report['phone'],
                'B' => $report['campaignName'],
                'C' => $report['senderId'],
                'D' => $report['message'],
                'E' => $this->reportStatus($report['status']),
                'F' => $this->formatReportTime($row->sent_at),
            ];
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit(
                    $column.$rowNumber,
                    is_scalar($value) ? (string) $value : '',
                    DataType::TYPE_STRING
                );
            }
            $rowNumber++;
        });

        $lastRow = max(1, $rowNumber - 1);
        $sheet->setAutoFilter('A1:F'.$lastRow);

        $xlsxPath = tempnam(sys_get_temp_dir(), 'soscom-dlr-xlsx-');
        $zipPath = tempnam(sys_get_temp_dir(), 'soscom-dlr-zip-');
        if ($xlsxPath === false || $zipPath === false) {
            $spreadsheet->disconnectWorksheets();

            return;
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($xlsxPath);
            $spreadsheet->disconnectWorksheets();

            if (is_file($zipPath)) {
                unlink($zipPath);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return;
            }

            $zip->addFile($xlsxPath, 'delivery-reports.xlsx');
            $zip->close();

            $handle = fopen($zipPath, 'rb');
            if ($handle === false) {
                return;
            }

            fpassthru($handle);
            fclose($handle);
        } finally {
            if (is_file($xlsxPath)) {
                unlink($xlsxPath);
            }
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    private function reportStatus(mixed $status): string
    {
        $value = is_string($status) ? $status : '';

        return $value === CampaignRecipientStatus::Unknown->value
            ? CampaignRecipientStatus::Sent->value
            : $value;
    }

    private function formatReportTime(?Carbon $at): string
    {
        if ($at === null) {
            return '';
        }

        return $at->copy()
            ->timezone((string) config('app.timezone'))
            ->format('j M Y, H:i');
    }
}
