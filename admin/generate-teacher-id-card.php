<?php
require_once '../includes/db.php';
require_once '../vendor/fpdf/fpdf.php';

if(isset($_GET['teacher_id'])) {
    $teacher_id = $_GET['teacher_id'];
    
    // Get teacher details
    $query = "SELECT * FROM teachers WHERE id = $teacher_id";
    $result = mysqli_query($conn, $query);
    $teacher = mysqli_fetch_assoc($result);
    
    if($teacher) {
        // Create PDF
        $pdf = new FPDF('L', 'mm', array(86, 54));
        $pdf->AddPage();
        
        // Background
        $pdf->SetFillColor(42, 82, 152);
        $pdf->Rect(0, 0, 86, 54, 'F');
        
        // White overlay
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(2, 2, 82, 50, 'F');
        
        // Logo
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(42, 82, 152);
        $pdf->Cell(0, 8, 'CoachingPro', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 6);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 3, 'Teacher Identity Card', 0, 1, 'C');
        
        // Photo
        $pdf->Image('https://ui-avatars.com/api/?name='.urlencode($teacher['first_name'].'+'.$teacher['last_name']).'&size=50&background=2a5298&color=fff', 5, 15, 20, 20);
        
        // Teacher details
        $pdf->SetXY(30, 15);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 4, $teacher['first_name'] . ' ' . $teacher['last_name'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Teacher ID:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $teacher['teacher_id'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Qualification:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, substr($teacher['qualification'], 0, 15), 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Phone:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $teacher['phone'], 0, 1);
        
        // Footer
        $pdf->SetY(42);
        $pdf->SetFont('Arial', '', 5);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 3, 'Valid till: December 2025', 0, 1, 'C');
        
        // Output PDF
        $pdf->Output('I', 'Teacher_ID_'.$teacher['teacher_id'].'.pdf');
    }
}
?>