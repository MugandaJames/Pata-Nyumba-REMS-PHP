<?php
ob_start(); // start buffering

require_once('/usr/share/php/tcpdf/tcpdf.php');

function generateContractPDF($request, $property, $customer)
{
    $pdf = new \TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    $pdf->Write(0, 'Contract Agreement');

    // Clean buffer before output
    ob_clean();

    $pdf->Output('contract.pdf', 'I');
}
