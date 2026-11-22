<?php

namespace App\Services\TaxCodes;

use App\Models\TaxCode;
use App\Services\CatalogTranslations\TypeTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/** Rates are written as text so spreadsheets never round them through a float. */
class TaxCodeExporter
{
    public function __construct(private readonly TypeTranslations $translations) {}

    /** @return array{xlsx: bool, pdf: bool} */
    public static function available(): array
    {
        return ['xlsx' => class_exists(Spreadsheet::class), 'pdf' => class_exists(Mpdf::class)];
    }

    /** @param Builder<TaxCode> $query */
    public function xlsx(Builder $query): Response
    {
        abort_unless(self::available()['xlsx'], 501, __('The Excel export package is not installed.'));
        $rows = $this->rows($query);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tax codes');
        $sheet->setRightToLeft($this->pageDirection() === 'rtl');

        foreach ($this->headings() as $index => $heading) {
            $sheet->setCellValueExplicit([$index + 1, 1], $heading, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        foreach ($rows as $offset => $row) {
            foreach (array_values($row) as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $offset + 2], (string) $value, DataType::TYPE_STRING);
            }
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param Builder<TaxCode> $query */
    public function pdf(Builder $query): Response
    {
        abort_unless(self::available()['pdf'], 501, __('The PDF export package is not installed.'));
        $rows = $this->rows($query);

        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'directionality' => $this->pageDirection(),
        ]);

        $mpdf->WriteHTML(view('exports.tax-codes', [
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
     * @param  Builder<TaxCode>  $query
     * @return Collection<int, array<string, string>>
     */
    private function rows(Builder $query): Collection
    {
        $max = (int) config('master_data.export_max_rows', 5000);
        abort_if((clone $query)->count() > $max, 422, __('Too many rows to export. Narrow the filters and try again.'));
        $today = now()->toDateString();
        $taxCodes = $query->get();
        // Name in the current interface language: exact locale, then base language; blank if none.
        $localized = $this->translations->forPage(new TaxCode, array_values($taxCodes->pluck('id')->all()), app()->getLocale())['localized'];

        return $taxCodes->map(fn (TaxCode $taxCode): array => [
            'country' => $taxCode->country_code,
            'code' => $taxCode->code,
            'description' => $taxCode->description,
            'translated' => $localized[$taxCode->id]['display_name'] ?? '',
            'rate_percent' => $taxCode->rate_percent,
            'effective_from' => $taxCode->effective_from,
            'effective_to' => $taxCode->effective_to ?? __('Open-ended'),
            'status' => $this->statusLabel($taxCode->periodStatus($today)),
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'future' => __('Upcoming'),
            'ended' => __('Ended'),
            default => __('In effect'),
        };
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [__('Country'), __('Code'), __('Description'), __('Translated name'), __('Rate (%)'), __('Effective from'), __('Effective to'), __('Status')];
    }

    private function pageDirection(): string
    {
        return config('locales.supported.'.app()->getLocale().'.dir', 'ltr');
    }

    private function filename(string $extension): string
    {
        return 'tax-codes-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}
