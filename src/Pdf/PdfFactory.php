<?php

declare(strict_types=1);

namespace PdfGenerator\Pdf;

use setasign\Fpdi\Tcpdf\Fpdi;

final class PdfFactory
{
    /** Creates an FPDI/TCPDF instance prepared for exact overlay positioning. */
    public function create(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);      // no default TCPDF header
        $pdf->setPrintFooter(false);      // no default TCPDF footer
        $pdf->SetMargins(0, 0, 0, true);  // zero margins
        $pdf->SetAutoPageBreak(false, 0); // no automatic page breaks
        $pdf->SetCellPadding(0);          // exact x/y placement for Cell()
        return $pdf;
    }
}
