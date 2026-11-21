<?php

namespace App\Services\Countries;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class CountryExporter
{
    /** @return array{xlsx: bool, pdf: bool} */
    public static function available(): array
    {
        return [
            'xlsx' => class_exists(Spreadsheet::class),
            'pdf' => class_exists(Mpdf::class),
        ];
    }

    /** @param Builder<Country> $query */
    public function xlsx(Builder $query): Response
    {
        abort_unless(self::available()['xlsx'], 501, __('The Excel export package is not installed.'));
        $rows = $this->rows($query);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Countries');
        $sheet->setRightToLeft($this->direction() === 'rtl');

        foreach ($this->headings() as $index => $heading) {
            $sheet->setCellValueExplicit([$index + 1, 1], $heading, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach ($rows as $offset => $country) {
            $row = $offset + 2;
            $sheet->setCellValueExplicit([1, $row], $country->iso2, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $row], (string) $country->iso3, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([3, $row], $country->english_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([4, $row], $this->status($country), DataType::TYPE_STRING);
        }

        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param Builder<Country> $query */
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

        $mpdf->WriteHTML(view('exports.countries', [
            'rows' => $rows,
            'headings' => $this->headings(),
            'direction' => $this->direction(),
            'locale' => app()->getLocale(),
            'status' => fn (Country $country): string => $this->status($country),
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

    /**
     * @param  Builder<Country>  $query
     * @return Collection<int, Country>
     */
    private function rows(Builder $query): Collection
    {
        $max = (int) config('master_data.export_max_rows', 5000);

        abort_if((clone $query)->count() > $max, 422, __('Too many rows to export. Narrow the filters and try again.'));

        return $query->get();
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [__('ISO2'), __('ISO3'), __('English name'), __('Status')];
    }

    private function status(Country $country): string
    {
        return $country->is_active ? __('Active') : __('Inactive');
    }

    private function direction(): string
    {
        return config('locales.supported.'.app()->getLocale().'.dir', 'ltr');
    }

    private function filename(string $extension): string
    {
        return 'countries-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}
