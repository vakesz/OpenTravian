<?php

namespace Core\Helper;

/**
 * Server-side SVG for the General statistics dashboard. The markup mirrors what the
 * reference's chart library renders (class names, layer groups, attribute set), so the
 * gpack stylesheets and the reference DOM line up without shipping the React bundle.
 */
class RechartsMarkup
{
    private const LAYERS_BEFORE_PLOT = [-100, -50];
    private const LAYERS_AFTER_PLOT = [200, 300, 400, 500, 600, 1000, 1100, 1200];

    /** Format a coordinate like the reference: at most 4 decimals, no trailing zeros. */
    public static function num(float $value, int $decimals = 4): string
    {
        $text = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $text === '-0' || $text === '' ? '0' : $text;
    }

    /**
     * @param list<array{value:float,fill:string}> $slices
     * @param (callable(int,array<string, mixed>,float,float):string)|null $labelRenderer called with index, slice, mid-angle (deg, clockwise from top) and total share
     */
    public static function pie(int $surfaceNo, int $reactId, array $slices, float $innerRadius = 0.0, ?callable $labelRenderer = null): string
    {
        $cx = 160.0;
        $cy = 135.0;
        $outer = 78.0;
        $inner = $innerRadius;
        $total = 0.0;
        foreach ($slices as $slice) {
            $total += max(0.0, (float)$slice['value']);
        }
        $html = self::surfaceOpen(320, 270) . self::emptyLayers(self::LAYERS_BEFORE_PLOT);
        $html .= '<defs><clipPath id="recharts' . $surfaceNo . '-clip"><rect x="5" y="5" height="260" width="310"></rect></clipPath></defs>';
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_100"><g class="recharts-layer recharts-pie" tabindex="0"><g class="recharts-layer">';
        $labels = '';
        $angle = 0.0;
        foreach ($slices as $index => $slice) {
            $share = $total > 0 ? max(0.0, (float)$slice['value']) / $total : 0.0;
            $sweep = min(359.99999, $share * 360.0);
            $start = $angle;
            $end = $angle + $sweep;
            $angle += $share * 360.0;
            $large = $sweep > 180 ? 1 : 0;
            [$sx, $sy] = self::point($cx, $cy, $outer, $start);
            [$ex, $ey] = self::point($cx, $cy, $outer, $end);
            $d = 'M ' . self::num($sx) . ',' . self::num($sy) . "\n    A " . self::num($outer) . ',' . self::num($outer) . ",0,\n    " . $large . ',1,'
                . "\n    " . self::num($ex) . ',' . self::num($ey) . "\n  ";
            if ($inner > 0) {
                [$ix, $iy] = self::point($cx, $cy, $inner, $end);
                [$jx, $jy] = self::point($cx, $cy, $inner, $start);
                $d .= 'L ' . self::num($ix) . ',' . self::num($iy) . "\n            A " . self::num($inner) . ',' . self::num($inner) . ",0,\n            "
                    . $large . ',0,' . "\n            " . self::num($jx) . ',' . self::num($jy) . ' Z';
            } else {
                $d .= 'L ' . self::num($cx) . ',' . self::num($cy) . ' Z';
            }
            $fill = htmlspecialchars((string)$slice['fill'], ENT_QUOTES, 'UTF-8');
            $html .= '<g class="recharts-layer recharts-pie-sector" tabindex="-1"><g class="recharts-layer recharts-shape">'
                . '<path cx="160" cy="135" fill="' . $fill . '" stroke="#fff" name="' . $index . '" color="' . $fill . '" tabindex="-1" data-recharts-item-index="' . $index
                . '" data-recharts-item-id="recharts-pie-_r_' . $reactId . '_" class="recharts-sector" d="' . $d . '"></path></g></g>';
            if ($labelRenderer !== null && $share > 0) {
                $labels .= $labelRenderer($index, $slice, $start + $sweep / 2, $share);
            }
        }
        $html .= '</g>' . ($labelRenderer !== null ? '<g class="recharts-layer recharts-pie-labels"></g>' : '') . '</g></g>';
        $html .= self::emptyLayers(self::LAYERS_AFTER_PLOT);
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_2000">' . $labels . '</g></svg>';

        return $html;
    }

    /**
     * Anchor of a pie label: the centre of the label box, 97.5 from the pie centre.
     *
     * @return array{0: float, 1: float}
     */
    public static function labelCenter(float $midAngle): array
    {
        [$x, $y] = self::point(160.0, 135.0, 97.5, $midAngle);

        return [$x, $y];
    }

    /**
     * Area chart with a left y axis and a bottom x axis (population rank history).
     *
     * @param list<array{day:int,rank:int}> $points
     */
    public static function area(int $surfaceNo, int $reactId, array $points, int $xMax): string
    {
        $left = 65.0;
        $top = 5.0;
        $width = 272.0;
        $height = 188.0;
        $bottom = $top + $height;
        $right = $left + $width;

        $ranks = array_column($points, 'rank');
        $yMax = $ranks ? max($ranks) : 2;
        $yMin = 1;
        if ($yMax <= $yMin) {
            $yMax = $yMin + 1;
        }
        $step = max(1, (int)(ceil((($yMax - $yMin) / 4) / 50) * 50));
        $yTicks = [];
        for ($v = $yMin; $v < $yMax; $v += $step) {
            $yTicks[] = $v;
        }
        $yTicks[] = $yMax;
        $yOf = static fn (float $v): float => $top + ($yMax - $v) / ($yMax - $yMin) * $height;
        $xStep = $xMax / 4;
        $xTicks = [];
        for ($i = 0; $i <= 4; $i++) {
            $xTicks[] = $xStep * $i;
        }
        $xOf = static fn (float $v): float => $left + $v / $xMax * $width;

        $html = self::surfaceOpen(342, 228, false);
        $grid = '';
        foreach ($yTicks as $tick) {
            $y = self::num($yOf($tick), 10);
            $grid .= '<line stroke-dasharray="3 3" x="65" y="5" width="272" height="188" stroke="#ccc" x1="65" y1="' . $y . '" x2="337" y2="' . $y . '" fill="none"></line>';
        }
        $vertical = '';
        foreach ($xTicks as $tick) {
            $x = self::num($xOf($tick), 10);
            $vertical .= '<line stroke-dasharray="3 3" x="65" y="5" width="272" height="188" stroke="#ccc" x1="' . $x . '" y1="5" x2="' . $x . '" y2="193" fill="none"></line>';
        }
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_-100"><g class="recharts-cartesian-grid"><g class="recharts-cartesian-grid-horizontal">' . $grid
            . '</g><g class="recharts-cartesian-grid-vertical">' . $vertical . '</g></g></g>';
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_-50"></g>';
        $html .= '<defs><clipPath id="recharts' . $surfaceNo . '-clip"><rect x="65" y="5" height="188" width="272"></rect></clipPath></defs>';
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_100">';
        if ($points) {
            $coords = [];
            foreach ($points as $point) {
                $coords[] = [$xOf((float)$point['day']), $yOf((float)$point['rank'])];
            }
            $curve = '';
            foreach ($coords as $i => [$x, $y]) {
                $curve .= ($i === 0 ? 'M' : 'L') . self::num($x, 3) . ',' . self::num($y, 3);
            }
            $areaPath = $curve;
            $lastX = $coords[count($coords) - 1][0];
            $areaPath .= 'L' . self::num($lastX, 3) . ',' . self::num($bottom, 3);
            for ($i = count($coords) - 2; $i >= 0; $i--) {
                $areaPath .= 'L' . self::num($coords[$i][0], 3) . ',' . self::num($bottom, 3);
            }
            $areaPath .= 'L' . self::num($coords[0][0], 3) . ',' . self::num($bottom, 3) . 'Z';
            $html .= '<g class="recharts-layer recharts-area"><g class="recharts-layer"><g class="recharts-layer recharts-shape">'
                . '<path stroke-width="1" fill="#EA866E" fill-opacity="1" height="188" width="272" id="recharts-area-_r_' . $reactId . '_" stroke="none" class="recharts-curve recharts-area-area" d="' . $areaPath . '"></path>'
                . '<path stroke-width="1" fill="none" fill-opacity="1" height="188" width="272" class="recharts-curve recharts-area-curve" stroke="#bf6060" d="' . $curve . '"></path>'
                . '</g></g></g>';
        }
        $html .= '</g>';
        $html .= self::emptyLayers([200, 300, 400]);

        $yTickLines = '';
        foreach ($yTicks as $tick) {
            $y = self::num($yOf($tick), 10);
            $yTickLines .= '<g class="recharts-layer recharts-cartesian-axis-tick"><line angle="0" orientation="left" width="60" x="5" y="5" height="188" class="recharts-cartesian-axis-tick-line" stroke="#666" fill="none" x1="59" y1="' . $y . '" x2="65" y2="' . $y . '"></line></g>';
        }
        $xTickLines = '';
        foreach ($xTicks as $tick) {
            $x = self::num($xOf($tick), 10);
            $xTickLines .= '<g class="recharts-layer recharts-cartesian-axis-tick"><line angle="0" height="30" orientation="bottom" x="65" y="193" width="272" class="recharts-cartesian-axis-tick-line" stroke="#666" fill="none" x1="' . $x . '" y1="199" x2="' . $x . '" y2="193"></line></g>';
        }
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_500">'
            . '<g class="recharts-layer recharts-cartesian-axis recharts-yAxis yAxis"><line angle="0" orientation="left" width="60" x="5" y="5" height="188" class="recharts-cartesian-axis-line" stroke="#666" fill="none" x1="65" y1="5" x2="65" y2="193"></line>'
            . '<g class="recharts-cartesian-axis-ticks recharts-yAxis-ticks"><g class="recharts-cartesian-axis-tick-lines recharts-yAxis-tick-lines">' . $yTickLines . '</g></g></g>'
            . '<g class="recharts-layer recharts-cartesian-axis recharts-xAxis xAxis"><line angle="0" height="30" orientation="bottom" x="65" y="193" width="272" class="recharts-cartesian-axis-line" stroke="#666" fill="none" x1="65" y1="193" x2="337" y2="193"></line>'
            . '<g class="recharts-cartesian-axis-ticks recharts-xAxis-ticks"><g class="recharts-cartesian-axis-tick-lines recharts-xAxis-tick-lines">' . $xTickLines . '</g></g></g></g>';
        $html .= self::emptyLayers([600, 1000, 1100, 1200]);

        $yLabels = '';
        foreach ($yTicks as $tick) {
            $y = $yOf($tick);
            $labelY = self::num(max($y, 8.0), 10);
            $yLabels .= '<g class="recharts-layer recharts-cartesian-axis-tick-label"><text orientation="left" width="60" height="188" stroke="none" x="57" y="' . $labelY
                . '" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="end" fill="#666"><tspan x="57" dy="0.355em">' . $tick . '</tspan></text></g>';
        }
        $xLabels = '';
        foreach ($xTicks as $i => $tick) {
            $x = $xOf($tick);
            $text = self::num((float)$tick, 2);
            if ($i === count($xTicks) - 1) {
                $x = min($x, 342 - 3.617 * strlen($text) - 0.0);
            }
            $xs = self::num($x, 6);
            $xLabels .= '<g class="recharts-layer recharts-cartesian-axis-tick-label"><text height="30" orientation="bottom" width="272" stroke="none" x="' . $xs
                . '" y="201" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="middle" fill="#666"><tspan x="' . $xs . '" dy="0.71em">' . $text . '</tspan></text></g>';
        }
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_2000"><g class="recharts-cartesian-axis-tick-labels recharts-yAxis-tick-labels">' . $yLabels
            . '</g><g class="recharts-cartesian-axis-tick-labels recharts-xAxis-tick-labels">' . $xLabels . '</g></g></svg>';

        return $html;
    }

    /**
     * Area chart for an increasing metric such as culture-point production.
     * The wider surface is the 535 × 220 culture-point progression graph.
     *
     * @param list<array{day:int,value:int}> $points
     */
    public static function areaValues(int $surfaceNo, int $reactId, array $points, int $xMax): string
    {
        $left = 65.0;
        $top = 5.0;
        $width = 465.0;
        $height = 180.0;
        $right = $left + $width;
        $bottom = $top + $height;
        $maximum = max(1, ...array_map(static fn (array $point): int => max(0, (int)$point['value']), $points ?: [['value' => 1]]));
        $increment = max(1, (int)(floor($maximum / 3 / 5) * 5));
        $yTicks = [0];
        for ($index = 1; $index <= 2; $index++) {
            $value = $increment * $index;
            if ($value < $maximum) {
                $yTicks[] = $value;
            }
        }
        $yTicks[] = $maximum;
        $yTicks = array_values(array_unique($yTicks));
        $xMax = max(4, $xMax);
        $xTicks = [];
        for ($index = 0; $index <= 4; $index++) {
            $xTicks[] = $xMax / 4 * $index;
        }
        $xOf = static fn (float $day): float => $left + $day / $xMax * $width;
        $yOf = static fn (float $value): float => $bottom - max(0.0, min((float)$maximum, $value)) / $maximum * $height;

        $horizontal = '';
        $vertical = '';
        foreach ($yTicks as $tick) {
            $y = self::num($yOf((float)$tick), 10);
            $horizontal .= '<line stroke-dasharray="3 3" x="65" y="5" width="465" height="180" stroke="#ccc" x1="65" y1="' . $y . '" x2="530" y2="' . $y . '" fill="none"></line>';
        }
        foreach ($xTicks as $tick) {
            $x = self::num($xOf($tick), 10);
            $vertical .= '<line stroke-dasharray="3 3" x="65" y="5" width="465" height="180" stroke="#ccc" x1="' . $x . '" y1="5" x2="' . $x . '" y2="185" fill="none"></line>';
        }

        $html = self::surfaceOpen(535, 220, false)
            . '<g tabindex="-1" class="recharts-zIndex-layer_-100"><g class="recharts-cartesian-grid"><g class="recharts-cartesian-grid-horizontal">' . $horizontal
            . '</g><g class="recharts-cartesian-grid-vertical">' . $vertical . '</g></g></g>'
            . '<g tabindex="-1" class="recharts-zIndex-layer_-50"></g>'
            . '<defs><clipPath id="recharts' . $surfaceNo . '-clip"><rect x="65" y="5" height="180" width="465"></rect></clipPath></defs>'
            . '<g tabindex="-1" class="recharts-zIndex-layer_100">';
        if ($points !== []) {
            $coords = array_map(static fn (array $point): array => [$xOf((float)$point['day']), $yOf((float)$point['value'])], $points);
            $curve = '';
            foreach ($coords as $index => [$x, $y]) {
                $curve .= ($index === 0 ? 'M' : 'L') . self::num($x, 3) . ',' . self::num($y, 3);
            }
            $area = $curve . 'L' . self::num($coords[count($coords) - 1][0], 3) . ',' . self::num($bottom, 3);
            for ($index = count($coords) - 2; $index >= 0; $index--) {
                $area .= 'L' . self::num($coords[$index][0], 3) . ',' . self::num($bottom, 3);
            }
            $area .= 'L' . self::num($coords[0][0], 3) . ',' . self::num($bottom, 3) . 'Z';
            $html .= '<g class="recharts-layer recharts-area"><g class="recharts-layer"><g class="recharts-layer recharts-shape">'
                . '<path stroke-width="1" fill="#EA866E" fill-opacity="1" height="180" width="465" id="recharts-area-_r_' . $reactId . '_" stroke="none" class="recharts-curve recharts-area-area" d="' . $area . '"></path>'
                . '<path stroke-width="1" fill="none" fill-opacity="1" height="180" width="465" class="recharts-curve recharts-area-curve" stroke="#bf6060" d="' . $curve . '"></path>'
                . '</g></g></g>';
        }
        $html .= '</g>' . self::emptyLayers([200, 300, 400]);

        $yLines = $xLines = $yLabels = $xLabels = '';
        foreach ($yTicks as $tick) {
            $y = self::num($yOf((float)$tick), 10);
            $labelY = self::num(max((float)$y, 8.0), 10);
            $yLines .= '<g class="recharts-layer recharts-cartesian-axis-tick"><line angle="0" orientation="left" width="60" x="5" y="5" height="180" class="recharts-cartesian-axis-tick-line" stroke="#666" fill="none" x1="59" y1="' . $y . '" x2="65" y2="' . $y . '"></line></g>';
            $yLabels .= '<g class="recharts-layer recharts-cartesian-axis-tick-label"><text orientation="left" width="60" height="180" stroke="none" x="57" y="' . $labelY . '" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="end" fill="#666"><tspan x="57" dy="0.355em">' . $tick . '</tspan></text></g>';
        }
        foreach ($xTicks as $index => $tick) {
            $x = self::num($xOf($tick), 10);
            $label = self::num($tick, 2);
            $xLines .= '<g class="recharts-layer recharts-cartesian-axis-tick"><line angle="0" height="30" orientation="bottom" x="65" y="185" width="465" class="recharts-cartesian-axis-tick-line" stroke="#666" fill="none" x1="' . $x . '" y1="191" x2="' . $x . '" y2="185"></line></g>';
            $labelX = $index === count($xTicks) - 1 ? self::num(min((float)$x, 530 - 3.617 * strlen($label)), 6) : $x;
            $xLabels .= '<g class="recharts-layer recharts-cartesian-axis-tick-label"><text height="30" orientation="bottom" width="465" stroke="none" x="' . $labelX . '" y="193" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="middle" fill="#666"><tspan x="' . $labelX . '" dy="0.71em">' . $label . '</tspan></text></g>';
        }
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_500">'
            . '<g class="recharts-layer recharts-cartesian-axis recharts-yAxis yAxis"><line angle="0" orientation="left" width="60" x="5" y="5" height="180" class="recharts-cartesian-axis-line" stroke="#666" fill="none" x1="65" y1="5" x2="65" y2="185"></line><g class="recharts-cartesian-axis-ticks recharts-yAxis-ticks"><g class="recharts-cartesian-axis-tick-lines recharts-yAxis-tick-lines">' . $yLines . '</g></g></g>'
            . '<g class="recharts-layer recharts-cartesian-axis recharts-xAxis xAxis"><line angle="0" height="30" orientation="bottom" x="65" y="185" width="465" class="recharts-cartesian-axis-line" stroke="#666" fill="none" x1="65" y1="185" x2="530" y2="185"></line><g class="recharts-cartesian-axis-ticks recharts-xAxis-ticks"><g class="recharts-cartesian-axis-tick-lines recharts-xAxis-tick-lines">' . $xLines . '</g></g></g></g>'
            . self::emptyLayers([600, 1000, 1100, 1200])
            . '<g tabindex="-1" class="recharts-zIndex-layer_2000"><g class="recharts-cartesian-axis-tick-labels recharts-yAxis-tick-labels">' . $yLabels . '</g><g class="recharts-cartesian-axis-tick-labels recharts-xAxis-tick-labels">' . $xLabels . '</g></g></svg>';

        return $html;
    }

    /**
     * Render the auction-detail graph in the Recharts shape below.
     *
     * The live component draws two 24-hour Area series in a 299 × 160
     * ResponsiveContainer. Keeping that native DOM/CSS contract here prevents
     * the legacy auction page from falling back to an unrelated line chart.
     *
     * @param list<int|float> $priceHistory
     * @param list<int|float> $salesHistory
     * @param array{empty:string,now:string,hours:string,averagePrice:string,sales:string} $labels
     */
    public static function auctionPriceHistory(
        array $priceHistory,
        array $salesHistory,
        string $rarity,
        array $labels,
        bool $rtl = false
    ): string {
        $hasData = $priceHistory !== [] && $salesHistory !== [];
        $prices = [];
        $sales = [];
        for ($index = 0; $index < 24; ++$index) {
            $prices[] = $hasData ? max(0.0, (float)($priceHistory[$index] ?? 0)) : 0.0;
            $sales[] = $hasData ? max(0.0, (float)($salesHistory[$index] ?? 0)) : 0.0;
        }

        $width = 299.0;
        $height = 160.0;
        $left = 40.0;
        $right = 259.0;
        $top = 0.0;
        // The 219px plot ends at y=121; the
        // remaining 39px is the x-axis labels and legend, not plot height.
        $bottom = 121.0;
        $plotWidth = $right - $left;
        $plotHeight = $bottom - $top;
        $salesMaximum = self::auctionAxisMaximum(max($sales), 10.0);
        $priceMaximum = self::auctionAxisMaximum(max($prices), 50.0);
        $xOf = static fn (int $index): float => $left + $plotWidth * $index / 23;
        $yOf = static fn (float $value, float $maximum): float => $bottom - $plotHeight * $value / max(1.0, $maximum);
        $xTicks = [0, 11, 23];
        $salesTicks = [0.0, $salesMaximum / 2, $salesMaximum];
        $priceTicks = [0.0, $priceMaximum / 2, $priceMaximum];

        $html = '<div class="recharts-responsive-container" style="width: 100%; height: 160px; min-width: 0px;">'
            . '<div style="width:0px;overflow-x:visible">'
            . '<div class="recharts-wrapper" style="position: relative; cursor: default; width: 299px; height: 160px;">'
            . '<svg role="application" tabindex="0" class="recharts-surface" width="299" height="160" viewBox="0 0 299 160" style="width: 100%; height: 100%; display: block;"><title></title><desc></desc>';

        if ($hasData) {
            $horizontal = '';
            foreach ($priceTicks as $tick) {
                $y = self::num($yOf($tick, $priceMaximum));
                $horizontal .= '<line stroke-dasharray="3 3" x="' . self::num($left) . '" y="' . self::num($top) . '" width="' . self::num($plotWidth) . '" height="' . self::num($plotHeight) . '" stroke="#939393" x1="' . self::num($left) . '" y1="' . $y . '" x2="' . self::num($right) . '" y2="' . $y . '" fill="none"></line>';
            }
            $vertical = '';
            foreach ($xTicks as $tick) {
                $x = self::num($xOf($tick));
                $vertical .= '<line stroke-dasharray="3 3" x="' . self::num($left) . '" y="' . self::num($top) . '" width="' . self::num($plotWidth) . '" height="' . self::num($plotHeight) . '" stroke="#939393" x1="' . $x . '" y1="' . self::num($top) . '" x2="' . $x . '" y2="' . self::num($bottom) . '" fill="none"></line>';
            }
            $html .= '<g tabindex="-1" class="recharts-zIndex-layer_-100"><g class="recharts-cartesian-grid"><g class="recharts-cartesian-grid-horizontal">' . $horizontal . '</g><g class="recharts-cartesian-grid-vertical">' . $vertical . '</g></g></g>';
        } else {
            $html .= '<g tabindex="-1" class="recharts-zIndex-layer_-100"></g>';
        }
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_-50"></g>'
            . '<defs><clipPath id="recharts-auction-price-history-clip"><rect x="' . self::num($left) . '" y="' . self::num($top) . '" height="' . self::num($plotHeight) . '" width="' . self::num($plotWidth) . '"></rect></clipPath></defs>'
            . '<g tabindex="-1" class="recharts-zIndex-layer_100">';

        if ($hasData) {
            $html .= self::auctionArea($sales, $salesMaximum, $xOf, $yOf, $bottom, '#D9A90B', 'sales')
                . self::auctionArea($prices, $priceMaximum, $xOf, $yOf, $bottom, '#B43B35', 'averagePrice');
        } else {
            $html .= '<text text-anchor="middle" x="50%" y="65" fill="#939393" font-style="italic">'
                . self::auctionEscape($labels['empty']) . '</text>';
        }
        $html .= '</g>' . self::emptyLayers([200, 300, 400]);

        $priceAxis = self::auctionYAxis(
            'left',
            $priceTicks,
            $priceMaximum,
            $left,
            $top,
            $plotHeight,
            '#B43B35',
            $rtl ? 'start' : 'end',
            -10.0,
            $yOf
        );
        $salesAxis = self::auctionYAxis(
            'right',
            $salesTicks,
            $salesMaximum,
            $right,
            $top,
            $plotHeight,
            '#D9A90B',
            $rtl ? 'end' : 'start',
            10.0,
            $yOf
        );
        $xAxis = self::auctionXAxis($xTicks, $xOf, $left, $bottom, $plotWidth, $labels);
        $html .= '<g tabindex="-1" class="recharts-zIndex-layer_500">' . $priceAxis . $salesAxis . $xAxis . '</g>'
            . self::emptyLayers([600, 1000, 1100, 1200])
            . '</svg>'
            . self::auctionLegend($rarity, $labels)
            . '</div></div></div>';

        return $html;
    }

    /** Rarity indicator SVG for the auction legend and table. */
    public static function auctionRarityIndicator(string $rarity): string
    {
        $rarity = in_array($rarity, ['common', 'uncommon', 'rare', 'epic', 'legendary'], true) ? $rarity : 'common';
        $shape = match ($rarity) {
            'uncommon' => '<path class="uncommon" d="M6.7 1.33c1.02-1.77 3.58-1.77 4.61 0l6.34 10.97c1.02 1.77-.25 3.98-2.3 3.98H2.66c-2.05 0-3.32-2.21-2.3-3.98L6.7 1.33Z"></path>',
            'rare' => '<path class="rare" d="M7.09.79a2.71 2.71 0 0 1 3.82 0l6.3 6.3a2.71 2.71 0 0 1 0 3.82l-6.3 6.3a2.71 2.71 0 0 1-3.82 0l-6.3-6.3a2.71 2.71 0 0 1 0-3.82l6.3-6.3Z"></path>',
            'epic' => '<path class="epic" d="M7.45.82a2.64 2.64 0 0 1 3.12 0l6.34 4.61c.93.68 1.32 1.87.97 2.96l-2.42 7.47a2.654 2.654 0 0 1-2.52 1.83H5.1c-1.15 0-2.17-.73-2.52-1.83L.13 8.4c-.36-1.09.03-2.3.97-2.96L7.45.82Z"></path>',
            'legendary' => '<path class="legendary" d="M10.06 17.47a1.325 1.325 0 0 1-2.12 0L0 6.86l3.76-4.84c.25-.32.64-.51 1.04-.51h8.41c.4 0 .79.19 1.04.51l3.76 4.84-7.95 10.61h.01Z"></path>',
            default => '<circle class="common" cx="9" cy="9" r="9"></circle>',
        };

        return '<div class="rarityQuality"><svg xmlns="http://www.w3.org/2000/svg" class="rarityIndicator ' . $rarity . '" viewBox="0 0 18 18">' . $shape . '</svg></div>';
    }

    private static function auctionAxisMaximum(float $maximum, float $step): float
    {
        return (floor(max(0.0, $maximum) / $step) + 1) * $step;
    }

    /**
     * @param list<float> $values
     * @param callable(int):float $xOf
     * @param callable(float,float):float $yOf
     */
    private static function auctionArea(array $values, float $maximum, callable $xOf, callable $yOf, float $bottom, string $color, string $id): string
    {
        $line = '';
        foreach ($values as $index => $value) {
            $line .= ($index === 0 ? 'M' : 'L') . self::num($xOf($index)) . ',' . self::num($yOf((float)$value, $maximum));
        }
        $firstX = self::num($xOf(0));
        $lastX = self::num($xOf(count($values) - 1));
        $area = $line . 'L' . $lastX . ',' . self::num($bottom) . 'L' . $firstX . ',' . self::num($bottom) . 'Z';

        return '<g class="recharts-layer recharts-area"><g class="recharts-layer"><g class="recharts-layer recharts-shape">'
            . '<path stroke-width="1" fill="' . $color . '" fill-opacity=".3" height="121" width="219" id="recharts-area-_r_auction-' . $id . '_" stroke="none" class="recharts-curve recharts-area-area" d="' . $area . '"></path>'
            . '<path stroke-width="1" fill="none" fill-opacity=".3" height="121" width="219" class="recharts-curve recharts-area-curve" stroke="' . $color . '" d="' . $line . '"></path>'
            . '</g></g></g>';
    }

    /** @param list<float> $ticks @param callable(float,float):float $yOf */
    private static function auctionYAxis(string $orientation, array $ticks, float $maximum, float $x, float $top, float $height, string $color, string $anchor, float $dx, callable $yOf): string
    {
        $labels = '';
        $markers = '';
        foreach ($ticks as $tick) {
            $y = self::num($yOf($tick, $maximum));
            $direction = $orientation === 'left' ? -1 : 1;
            $markerEnd = self::num($x + $direction * 4);
            $textX = self::num($x + $dx);
            $markers .= '<g class="recharts-layer recharts-cartesian-axis-tick"><line angle="0" orientation="' . $orientation . '" width="40" x="' . self::num($x) . '" y="' . self::num($top) . '" height="' . self::num($height) . '" class="recharts-cartesian-axis-tick-line" stroke="#5E463A" fill="none" x1="' . self::num($x) . '" y1="' . $y . '" x2="' . $markerEnd . '" y2="' . $y . '"></line></g>';
            $labels .= '<g class="recharts-layer recharts-cartesian-axis-tick-label"><text orientation="' . $orientation . '" width="40" height="' . self::num($height) . '" stroke="none" x="' . $textX . '" y="' . $y . '" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="' . $anchor . '" fill="' . $color . '" font-size="10"><tspan x="' . $textX . '" dy="0.355em">' . self::num($tick) . '</tspan></text></g>';
        }

        return '<g class="recharts-layer recharts-cartesian-axis recharts-yAxis yAxis"><line angle="0" orientation="' . $orientation . '" width="40" x="' . self::num($x) . '" y="' . self::num($top) . '" height="' . self::num($height) . '" class="recharts-cartesian-axis-line" stroke="#5E463A" stroke-width="2" fill="none" x1="' . self::num($x) . '" y1="' . self::num($top) . '" x2="' . self::num($x) . '" y2="' . self::num($top + $height) . '"></line><g class="recharts-cartesian-axis-ticks recharts-yAxis-ticks"><g class="recharts-cartesian-axis-tick-lines recharts-yAxis-tick-lines">' . $markers . '</g></g></g>'
            . '<g tabindex="-1" class="recharts-zIndex-layer_2000"><g class="recharts-cartesian-axis-tick-labels recharts-yAxis-tick-labels">' . $labels . '</g></g>';
    }

    /**
     * @param list<int> $ticks
     * @param callable(int):float $xOf
     * @param array{now:string,hours:string} $labels
     */
    private static function auctionXAxis(array $ticks, callable $xOf, float $left, float $bottom, float $width, array $labels): string
    {
        $markers = '';
        $labelsHtml = '';
        foreach ($ticks as $tick) {
            $x = self::num($xOf($tick));
            $label = $tick === 23
                ? $labels['now']
                : str_replace('[hours]', (string)(23 - $tick), $labels['hours']);
            $markers .= '<g class="recharts-layer recharts-cartesian-axis-tick"><line angle="0" height="20" orientation="bottom" x="' . self::num($left) . '" y="' . self::num($bottom) . '" width="' . self::num($width) . '" class="recharts-cartesian-axis-tick-line" stroke="#5E463A" fill="none" x1="' . $x . '" y1="' . self::num($bottom + 4) . '" x2="' . $x . '" y2="' . self::num($bottom) . '"></line></g>';
            $labelsHtml .= '<g class="recharts-layer recharts-cartesian-axis-tick-label"><text height="20" orientation="bottom" width="' . self::num($width) . '" stroke="none" x="' . $x . '" y="' . self::num($bottom + 4) . '" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="middle" fill="#5E463A" font-size="10"><tspan x="' . $x . '" dy="1em">' . self::auctionEscape($label) . '</tspan></text></g>';
        }

        return '<g class="recharts-layer recharts-cartesian-axis recharts-xAxis xAxis"><line angle="0" height="20" orientation="bottom" x="' . self::num($left) . '" y="' . self::num($bottom) . '" width="' . self::num($width) . '" class="recharts-cartesian-axis-line" stroke="#5E463A" stroke-width="2" fill="none" x1="' . self::num($left) . '" y1="' . self::num($bottom) . '" x2="' . self::num($left + $width) . '" y2="' . self::num($bottom) . '"></line><g class="recharts-cartesian-axis-ticks recharts-xAxis-ticks"><g class="recharts-cartesian-axis-tick-lines recharts-xAxis-tick-lines">' . $markers . '</g></g></g>'
            . '<g tabindex="-1" class="recharts-zIndex-layer_2000"><g class="recharts-cartesian-axis-tick-labels recharts-xAxis-tick-labels">' . $labelsHtml . '</g></g>';
    }

    /** @param array{averagePrice:string,sales:string} $labels */
    private static function auctionLegend(string $rarity, array $labels): string
    {
        $item = static function (int $index, string $color, string $label) use ($rarity): string {
            return '<li class="recharts-legend-item legend-item-' . $index . '" style="display: inline-block; margin-right: 10px;">'
                . '<svg class="recharts-surface recharts-legend-icon" width="14" height="14" viewBox="0 0 32 32"><line stroke-width="4" fill="none" stroke="' . $color . '" x1="0" y1="16" x2="32" y2="16" class="recharts-legend-icon"></line></svg>'
                . '<span class="recharts-legend-item-text" style="color: ' . $color . ';"><div class="labelRarity ' . self::auctionEscape($rarity) . '">' . self::auctionRarityIndicator($rarity) . self::auctionEscape($label) . '</div></span></li>';
        };

        return '<div class="recharts-legend-wrapper" style="position: absolute; width: 299px; left: 0px; bottom: 0px;"><ul class="recharts-default-legend" style="padding: 0px; margin: 0px; text-align: left;">'
            . $item(0, '#B43B35', $labels['averagePrice'])
            . $item(1, '#D9A90B', $labels['sales'])
            . '</ul></div>';
    }

    private static function auctionEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** @return array{0:float,1:float} */
    private static function point(float $cx, float $cy, float $radius, float $angleDeg): array
    {
        $rad = deg2rad($angleDeg);

        return [round($cx + $radius * sin($rad), 4), round($cy - $radius * cos($rad), 4)];
    }

    private static function surfaceOpen(int $width, int $height, bool $center = true): string
    {
        return '<svg ' . ($center ? 'cx="50%" cy="50%" ' : '') . 'role="application" tabindex="0" class="recharts-surface" width="' . $width . '" height="' . $height
            . '" viewBox="0 0 ' . $width . ' ' . $height . '" style="width: 100%; height: 100%; display: block;"><title></title><desc></desc>';
    }

    /** @param list<int> $layers */
    private static function emptyLayers(array $layers): string
    {
        $html = '';
        foreach ($layers as $layer) {
            $html .= '<g tabindex="-1" class="recharts-zIndex-layer_' . $layer . '"></g>';
        }

        return $html;
    }
}
