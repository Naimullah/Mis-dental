<?php

namespace App\Services\Currencies;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class CurrencyExporter
{
    /** @return array{xlsx: bool, pdf: bool} */
    public static function available(): array
    {
        return [
            'xlsx' => class_exists(Spreadsheet::class),
            'pdf' => class_exists(Mpdf::class),
        ];
    }

    public function xlsx(Builder $query): Response
    {
        abort_unless(self::available()['xlsx'], 501, __('The Excel export package is not installed.'));
        $rows = $this->rows($query);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Currencies');
        $sheet->setRightToLeft($this->direction() === 'rtl');

        foreach ($this->headings() as $index => $heading) {
            $sheet->setCellValueExplicit([$index + 1, 1], $heading, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        foreach ($rows as $offset => $currency) {
            $row = $offset + 2;
            $sheet->setCellValueExplicit([1, $row], $currency->code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $row], $currency->english_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([3, $row], (string) $currency->symbol, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([4, $row], $currency->minor_units, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit([5, $row], $this->status($currency), DataType::TYPE_STRING);
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(Builder $query): Response
    {
        abort_unless(self::available()['pdf'], 501, __('The PDF export package is not installed.'));
        $rows = $this->rows($query);

        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'directionality' => $this->direction(),
        ]);

        $mpdf->WriteHTML(view('exports.currencies', [
            'rows' => $rows,
            'headings' => $this->headings(),
            'direction' => $this->direction(),
            'locale' => app()->getLocale(),
            'status' => fn (Currency $currency): string => $this->status($currency),
            'footer' => __('Exported by :name on :date', [
                'name' => auth()->user()?->name ?? '—',
                'date' => now()->format('Y-m-d H:i'),
            ]),
        ])->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename('pdf').'"',
        ]);
    }

    /** @return Collection<int, Currency> */
    private function rows(Builder $query): Collection
    {
        $max = (int) config('master_data.export_max_rows', 5000);

        abort_if((clone $query)->count() > $max, 422, __('Too many rows to export. Narrow the filters and try again.'));

        return $query->get();
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [__('Code'), __('English name'), __('Symbol'), __('Minor units'), __('Status')];
    }

    private function status(Currency $currency): string
    {
        return $currency->is_active ? __('Active') : __('Inactive');
    }

    private function direction(): string
    {
        return config('locales.supported.'.app()->getLocale().'.dir', 'ltr');
    }

    private function filename(string $extension): string
    {
        return 'currencies-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}
