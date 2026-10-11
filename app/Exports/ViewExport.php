<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Any Blade view's tables as one Excel sheet (Phase 3 items 7 and 11). */
class ViewExport implements FromView, WithTitle
{
    public function __construct(private string $view, private array $data, private string $title = 'Report')
    {
    }

    public function view(): View
    {
        return view($this->view, $this->data);
    }

    public function title(): string
    {
        // Excel sheet names: at most 31 characters, no []:*?/\
        return mb_substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $this->title), 0, 31);
    }
}
