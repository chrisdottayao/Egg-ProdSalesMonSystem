<?php

namespace App\Support;

/**
 * Phase 3 item 11: server-side PNG charts for the PDF report (dompdf cannot
 * run Chart.js). Plain GD: title, axis titles, y-axis ticks, legend.
 * Text uses the DejaVu Sans font shipped with dompdf when GD has FreeType
 * (the Docker image does), otherwise GD's built-in font.
 */
class ChartImage
{
    private const W = 900;
    private const H = 360;
    private const PAD_L = 90;
    private const PAD_R = 20;
    private const PAD_T = 60;
    private const PAD_B = 70;

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, values: list<float|int|null>, color: array{int,int,int}, type: string}>  $series  type 'line' or 'bar'
     * @return string|null  data:image/png;base64,… or null when GD is unavailable / nothing to plot
     */
    public static function render(string $title, string $xTitle, string $yTitle, array $labels, array $series, string $yPrefix = ''): ?string
    {
        if (! function_exists('imagecreatetruecolor') || $labels === []) {
            return null;
        }

        $all = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null);
        if ($all->isEmpty()) {
            return null;
        }
        $max = (float) $all->max();
        $min = min(0.0, (float) $all->min());
        [$lo, $hi, $step] = self::niceScale($min, $max);

        $img   = imagecreatetruecolor(self::W, self::H);
        $white = imagecolorallocate($img, 255, 255, 255);
        $ink   = imagecolorallocate($img, 55, 65, 81);
        $muted = imagecolorallocate($img, 107, 114, 128);
        $grid  = imagecolorallocate($img, 229, 231, 235);
        imagefill($img, 0, 0, $white);

        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;
        $y     = fn (float $v) => (int) round(self::PAD_T + $plotH - ($v - $lo) / max($hi - $lo, 1e-9) * $plotH);
        $n     = count($labels);
        $slot  = $plotW / $n;
        $x     = fn (int $i) => (int) round(self::PAD_L + $slot * ($i + 0.5));

        // Title, axis titles.
        self::text($img, $title, self::W / 2, 18, 13, $ink, 'center', true);
        self::text($img, $xTitle, self::PAD_L + $plotW / 2, self::H - 16, 10, $muted, 'center');
        self::text($img, $yTitle, 16, self::PAD_T + $plotH / 2, 10, $muted, 'vertical');

        // Grid + y ticks.
        for ($v = $lo; $v <= $hi + $step / 2; $v += $step) {
            $py = $y($v);
            imageline($img, self::PAD_L, $py, self::PAD_L + $plotW, $py, $grid);
            self::text($img, $yPrefix . number_format($v), self::PAD_L - 8, $py, 9, $muted, 'right');
        }
        imageline($img, self::PAD_L, self::PAD_T, self::PAD_L, self::PAD_T + $plotH, $ink);
        imageline($img, self::PAD_L, self::PAD_T + $plotH, self::PAD_L + $plotW, self::PAD_T + $plotH, $ink);

        // X labels (at most ~12 shown).
        $every = max(1, (int) ceil($n / 12));
        foreach ($labels as $i => $label) {
            if ($i % $every === 0) {
                self::text($img, $label, $x($i), self::PAD_T + $plotH + 14, 9, $muted, 'center');
            }
        }

        // Series.
        $bars = array_values(array_filter($series, fn ($s) => $s['type'] === 'bar'));
        $barW = max(2, (int) ($slot * 0.7 / max(count($bars), 1)));
        foreach ($series as $s) {
            $c = imagecolorallocate($img, ...$s['color']);
            if ($s['type'] === 'bar') {
                $k = array_search($s, $bars, true);
                foreach ($s['values'] as $i => $v) {
                    if ($v === null) {
                        continue;
                    }
                    $x0 = (int) ($x($i) - $barW * count($bars) / 2 + $k * $barW);
                    imagefilledrectangle($img, $x0, $y((float) $v), $x0 + $barW - 1, $y(max(0.0, $lo)), $c);
                }
            } else {
                imagesetthickness($img, 2);
                $prev = null;
                foreach ($s['values'] as $i => $v) {
                    if ($v === null) {
                        $prev = null;
                        continue;
                    }
                    $pt = [$x($i), $y((float) $v)];
                    if ($prev) {
                        imageline($img, $prev[0], $prev[1], $pt[0], $pt[1], $c);
                    }
                    imagefilledellipse($img, $pt[0], $pt[1], 6, 6, $c);
                    $prev = $pt;
                }
                imagesetthickness($img, 1);
            }
        }

        // Legend (top, under the title).
        $lx = self::PAD_L;
        foreach ($series as $s) {
            $c = imagecolorallocate($img, ...$s['color']);
            imagefilledrectangle($img, $lx, 36, $lx + 14, 46, $c);
            self::text($img, $s['label'], $lx + 20, 41, 10, $ink, 'left');
            $lx += 40 + 8 * mb_strlen($s['label']);
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    /** @return array{float, float, float} rounded axis bounds and step */
    private static function niceScale(float $min, float $max): array
    {
        if ($max <= $min) {
            $max = $min + 1;
        }
        $raw   = ($max - $min) / 5;
        $mag   = 10 ** floor(log10($raw));
        $step  = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s >= $raw);

        return [floor($min / $step) * $step, ceil($max / $step) * $step, $step];
    }

    /** $align: left | right | center | vertical (rotated, centered on y). */
    private static function text($img, string $s, float $x, float $y, int $size, int $color, string $align, bool $bold = false): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/' . ($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
        if (function_exists('imagettftext') && is_file($font)) {
            $angle = $align === 'vertical' ? 90 : 0;
            $box   = imagettfbbox($size, 0, $font, $s);
            $w     = abs($box[2] - $box[0]);
            $h     = abs($box[7] - $box[1]);
            [$tx, $ty] = match ($align) {
                'center'   => [$x - $w / 2, $y + $h / 2],
                'right'    => [$x - $w, $y + $h / 2],
                'vertical' => [$x + $h / 2, $y + $w / 2],
                default    => [$x, $y + $h / 2],
            };
            imagettftext($img, $size, $angle, (int) $tx, (int) $ty, $color, $font, $s);

            return;
        }

        // Built-in font (ASCII only): ₱ and similar become "P".
        $s  = str_replace('₱', 'P', $s);
        $f  = $size >= 12 ? 5 : ($size >= 10 ? 3 : 2);
        $cw = imagefontwidth($f) * strlen($s);
        $ch = imagefontheight($f);
        if ($align === 'vertical') {
            imagestringup($img, $f, (int) ($x - $ch / 2), (int) ($y + $cw / 2), $s, $color);

            return;
        }
        $tx = match ($align) { 'center' => $x - $cw / 2, 'right' => $x - $cw, default => $x };
        imagestring($img, $f, (int) $tx, (int) ($y - $ch / 2), $s, $color);
    }
}
