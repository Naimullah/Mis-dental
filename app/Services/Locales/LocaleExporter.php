<?php

namespace App\Services\Locales;

use App\Models\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class LocaleExporter
{
    /** @return array{xlsx: bool, pdf: bool} */
    public static function available(): array
    {
        return ['xlsx' => class_exists(Spreadsheet::class), 'pdf' => class_exists(Mpdf::class)];
    }

    /** @param Builder<Locale> $query */
    public function xlsx(Builder $query): Response
    {
        abort_unless(self::available()['xlsx'], 501, __('The Excel export package is not installed.'));
        $rows = $this->rows($query);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Languages');
        $sheet->setRightToLeft($this->pageDirection() === 'rtl');

        foreach ($this->headings() as $index => $heading) {
            $sheet->setCellValueExplicit([$index + 1, 1], $heading, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        foreach ($rows as $offset => $locale) {
            $row = $offset + 2;
            $sheet->setCellValueExplicit([1, $row], $locale->code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $row], $locale->english_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([3, $row], $locale->text_direction, DataType::TYPE_STRING);
        }

        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param Builder<Locale> $query */
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
            'directionality' => $this->pageDirection(),
        ]);

        $mpdf->WriteHTML(view('exports.locales', [
            'rows' => $rows,
            'headings' => $this->headings(),
            'direction' => $this->pageDirection(),
            'locale' => app()->getLocale(),
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
     * @param  Builder<Locale>  $query
     * @return Collection<int, Locale>
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
        return [__('Code'), __('English name'), __('Text direction')];
    }

    private function pageDirection(): string
    {
        return config('locales.supported.'.app()->getLocale().'.dir', 'ltr');
    }

    private function filename(string $extension): string
    {
        return 'languages-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}
