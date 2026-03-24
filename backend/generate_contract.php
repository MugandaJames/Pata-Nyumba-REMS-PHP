<?php
// backend/generate_contract.php

// 1. Security: Preventing direct URL access to this script
if (count(get_included_files()) <= 1) {
    http_response_code(403);
    die(json_encode(['error' => 'Direct access forbidden']));
}


require_once __DIR__ . '/libs/tcpdf.php';

function generateContractPDF($request, $property, $customer)
{
    // Cleaning any accidental whitespace/buffer to prevent PDF corruption
    if (ob_get_length()) {
        ob_end_clean();
    }

    // 2. Initializing TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Setting Document Metadata
    $pdf->SetCreator('Pata-Nyumba System');
    $pdf->SetAuthor('Pata-Nyumba Admin');
    $pdf->SetTitle('Property Agreement - ' . $property['id']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->AddPage();

    // 3. Preparing Secure Content
    $customerName = htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8');
    $propTitle = htmlspecialchars($property['title'], ENT_QUOTES, 'UTF-8');
    $location = htmlspecialchars($property['location'], ENT_QUOTES, 'UTF-8');
    $price = number_format($property['price'], 2);
    $type = strtoupper($request['request_type']); // RENT or PURCHASE
    $date = date('F j, Y');

    // Contract content
    $html = "
    <h1 style='color: #2c3e50; text-align: center;'>$type AGREEMENT</h1>
    <hr>
    <p>This document serves as a formal agreement generated on <b>$date</b>.</p>

    <h3>1. THE PARTIES</h3>
    <p><b>Client Name:</b> $customerName<br>
       <b>Client Email:</b> {$customer['email']}</p>

    <h3>2. THE PROPERTY</h3>
    <p><b>Title:</b> $propTitle<br>
       <b>Location:</b> $location<br>
       <b>Agreed Price:</b> KES $price</p>

    <h3>3. TERMS OF AGREEMENT</h3>
    <p>By approving this request, the Agent/Admin confirms the availability and transfer of rights for the specified property type ($type) to the client. This digital contract acts as a primary record of the transaction within the Pata-Nyumba ecosystem.</p>
    
    <br><br>
    <table border=\"0\" cellspacing=\"0\" cellpadding=\"5\">
        <tr>
            <td style=\"border-bottom: 1px solid #000;\"></td>
            <td></td>
            <td style=\"border-bottom: 1px solid #000;\"></td>
        </tr>
        <tr>
            <td><b>Authorized Agent Signature</b></td>
            <td></td>
            <td><b>Customer Signature</b></td>
        </tr>
    </table>
    
    <p style='font-size: 10px; color: #7f8c8d; text-align: center; margin-top: 50px;'>
        Document Reference (UUID): {$request['uuid']}
    </p>
    ";

    $pdf->writeHTML($html, true, false, true, false, '');

    // 4. Secure Storage Logic
    // Using the 'agreements' folder in the root
    $dir = __DIR__ . '/../agreements/';
    if (!file_exists($dir)) {
        mkdir($dir, 0775, true);
    }

    // Using the UUID for the filename to prevent ID enumeration attacks
    $fileName = 'contract_' . $request['uuid'] . '.pdf';
    $filePath = $dir . $fileName;

    // Saving to server
    $pdf->Output($filePath, 'F');

    // Return the path for the 'agreements' table in DB
    return 'agreements/' . $fileName;
}
