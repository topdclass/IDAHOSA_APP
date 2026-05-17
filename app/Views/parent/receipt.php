<?php
/**
 * Parent-authorized wrapper for Student Receipt
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
$invId = $_GET['id'] ?? 0;
$parentId = $_SESSION['user_id'];

// Authorization Check: Parent can only view invoices mapped to their family_id
$stmt = $pdo->prepare("SELECT i.id FROM student_invoices i
                      JOIN institute_students s ON i.student_id = s.student_id
                      JOIN institute_parents p ON s.family_id = p.family_id
                      WHERE i.id = ? AND p.parent_id = ?");
$stmt->execute([$invId, $parentId]);

if (!$stmt->fetch()) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif; color:#991B1B;'>
            <h2>Access Denied</h2>
            <p>You do not have permission to view this receipt. It may belong to another family.</p>
         </div>");
}

// Display receipt
require __DIR__ . '/../student/receipt.php';
