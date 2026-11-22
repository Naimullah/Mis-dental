<?php

namespace App\Services\LicenseTypes;

use App\Enums\LicenseAppliesTo;
use App\Models\LicenseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class LicenseTypeExporter
{
    /** @return array{xlsx: bool, pdf: bool} */
    public static function available(): array
    {
        return ['xlsx' => class_exists(Spreadsheet::class), 'pdf' => class_exists(Mpdf::class)];
    }

    /** @param Builder<LicenseType> $query */
    public function xlsx(Builder $query): Response
    {
        abort_unless(self::available()['xlsx'], 501, __('The Excel export package is not installed.'));
        $rows = $this->rows($query);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('License types');
        $sheet->setRightToLeft($this->pageDirection() === 'rtl');

        foreach ($this->headings() as $index => $heading) {
            $sheet->setCellValueExplicit([$index + 1, 1], $heading, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        foreach ($rows as $offset => $row) {
            foreach (array_values($row) as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $offset + 2], (string) $value, DataType::TYPE_STRING);
            }
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

    /** @param Builder<LicenseType> $query */
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

        $mpdf->WriteHTML(view('exports.license-types', [
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
     * @param  Builder<LicenseType>  $query
     * @return Collection<int, array<string, string>>
     */
    private function rows(Builder $query): Collection
    {
        $max = (int) config('master_data.export_max_rows', 5000);
        abort_if((clone $query)->count() > $max, 422, __('Too many rows to export. Narrow the filters and try again.'));

        return $query->get()->map(fn (LicenseType $type): array => [
            'country' => $type->country_code,
            'code' => $type->code,
            'name' => $type->name,
            'applies_to' => LicenseAppliesTo::from($type->applies_to)->label(),
            'status' => $type->is_active ? __('Active') : __('Inactive'),
        ]);
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [__('Country'), __('Code'), __('Name'), __('Applies to'), __('Status')];
    }

    private function pageDirection(): string
    {
        return config('locales.supported.'.app()->getLocale().'.dir', 'ltr');
    }

    private function filename(string $extension): string
    {
        return 'license-types-'.now()->format('Ymd-Hi').'.'.$extension;
    }
}
