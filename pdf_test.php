<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();

require_once('/usr/share/php/tcpdf/tcpdf.php');

$pdf = new \TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);
$pdf->Write(0, 'TCPDF WORKING');

ob_end_clean();

$pdf->Output('test.pdf', 'I');
