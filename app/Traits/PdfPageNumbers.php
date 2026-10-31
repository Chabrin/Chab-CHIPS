<?php

namespace App\Traits;

use Dompdf\Dompdf;

trait PdfPageNumbers
{
    /**
     * Write "Page x of y" in the bottom-right corner of every page of an already rendered PDF.
     */
    protected function addPdfPageNumbers(Dompdf $dompdf) {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 18, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 6.5, [0.54, 0.58, 0.64]);
    }
}
