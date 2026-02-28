<?php
require_once '../includes/db.php';
require_once '../vendor/fpdf/fpdf.php'; // You'll need to download FPDF

if(isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // Get student details
    $query = "SELECT s.*, c.class_name 
              FROM students s 
              LEFT JOIN classes c ON s.class_id = c.id 
              WHERE s.id = $student_id";
    
    $result = mysqli_query($conn, $query);
    $student = mysqli_fetch_assoc($result);
    
    if($student) {
        // Create PDF
        $pdf = new FPDF('L', 'mm', array(86, 54)); // Credit card size
        $pdf->AddPage();
        
        // Background color
        $pdf->SetFillColor(42, 82, 152); // Dark blue
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
        $pdf->Cell(0, 3, 'Student Identity Card', 0, 1, 'C');
        
        // Photo placeholder
        $pdf->Image('https://ui-avatars.com/api/?name='.urlencode($student['first_name'].'+'.$student['last_name']).'&size=50&background=2a5298&color=fff', 5, 15, 20, 20);
        
        // Student details
        $pdf->SetXY(30, 15);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 4, $student['first_name'] . ' ' . $student['last_name'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Student ID:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $student['student_id'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Class:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $student['class_name'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Roll No:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $student['roll_number'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Batch:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $student['batch_no'], 0, 1);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(25, 4, 'Phone:', 0, 0);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(0, 4, $student['phone'], 0, 1);
        
        // Footer
        $pdf->SetY(42);
        $pdf->SetFont('Arial', '', 5);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 3, 'Valid till: December 2025', 0, 1, 'C');
        
        // Output PDF
        $pdf->Output('I', 'ID_Card_'.$student['student_id'].'.pdf');
    }
}
?>