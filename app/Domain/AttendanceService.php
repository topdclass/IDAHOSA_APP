<?php

namespace App\Domain;

require_once __DIR__ . '/../../../config/tenant_manager.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/../Support/MailHelper.php';

use TenantManager;
use PDO;

class AttendanceService {

    private $db;
    private $emailService;

    public function __construct($school_id = null) {
        $this->db = \TenantManager::getTenantConnection($school_id);
        $this->emailService = new EmailService();
    }

    /**
     * Mark student as present (Check-in)
     * Triggers Parent Notification dynamically
     */
    public function markCheckIn($studentId, $time = null) {
        if (!$time) $time = date('H:i:s');
        $date = date('Y-m-d');

        // 1. Fetch Student and Parent Info
        $query = "
            SELECT u.full_name as student_name, p_u.full_name as parent_name, p_u.email as parent_email, p_u.phone as parent_phone
            FROM users u
            JOIN institute_students s ON u.id = s.student_id
            JOIN institute_parents p ON s.family_id = p.family_id
            JOIN users p_u ON p.parent_id = p_u.id
            WHERE u.id = ? AND s.is_deleted = 0
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studentId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($recipients)) {
            // Fallback: Just get student name if no parent found
            $sStmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
            $sStmt->execute([$studentId]);
            $student = $sStmt->fetch(PDO::FETCH_ASSOC);
            $student_name = $student['full_name'] ?? 'Student';
        } else {
            $student_name = $recipients[0]['student_name'];
        }

        // 2. Record attendance logic
        $ins = $this->db->prepare("
            INSERT INTO student_attendance_logs (student_id, attendance_date, clock_in, status) 
            VALUES (?, ?, ?, 'Present')
            ON DUPLICATE KEY UPDATE clock_in = IF(clock_in IS NULL OR clock_in = '00:00:00', VALUES(clock_in), clock_in)
        ");
        $ins->execute([$studentId, $date, $time]);

        // 3. Notify Parents
        foreach ($recipients as $parent) {
            $this->sendNotification($parent, $student_name, $time, 'Check-In');
        }

        return true;
    }

    /**
     * Mark student as checked out (Check-out)
     * Triggers Parent Notification dynamically
     */
    public function markCheckOut($studentId, $time = null) {
        if (!$time) $time = date('H:i:s');
        $date = date('Y-m-d');

        // 1. Fetch Student and Parent Info
        $query = "
            SELECT u.full_name as student_name, p_u.full_name as parent_name, p_u.email as parent_email, p_u.phone as parent_phone
            FROM users u
            JOIN institute_students s ON u.id = s.student_id
            JOIN institute_parents p ON s.family_id = p.family_id
            JOIN users p_u ON p.parent_id = p_u.id
            WHERE u.id = ? AND s.is_deleted = 0
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studentId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($recipients)) {
            $sStmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
            $sStmt->execute([$studentId]);
            $student = $sStmt->fetch(PDO::FETCH_ASSOC);
            $student_name = $student['full_name'] ?? 'Student';
        } else {
            $student_name = $recipients[0]['student_name'];
        }

        // 2. Record check-out logic
        $upd = $this->db->prepare("
            UPDATE student_attendance_logs 
            SET clock_out = ? 
            WHERE student_id = ? AND attendance_date = ?
        ");
        $upd->execute([$time, $studentId, $date]);

        // 3. Notify Parents
        foreach ($recipients as $parent) {
            $this->sendNotification($parent, $student_name, $time, 'Check-Out');
        }

        return true;
    }

    private function logNotification($recipient, $message, $channel) {
        // Log to notification_logs table
        $stmt = $this->db->prepare("INSERT INTO notification_logs (channel, recipient_group, subject, message_body, status, sent_by) VALUES (?, 'parent', 'Attendance Notification', ?, 'sent', 0)");
        $stmt->execute([$channel, $message]);
    }

    private function getTemplate($templateName, $channel) {
        $stmt = $this->db->prepare("SELECT subject, body FROM notification_templates WHERE template_name = ? AND channel = ? LIMIT 1");
        $stmt->execute([$templateName, $channel]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function processTemplate($templateBody, $placeholders) {
        foreach($placeholders as $key => $val) {
            $templateBody = str_replace("{".$key."}", $val, $templateBody);
        }
        return $templateBody;
    }

    private function sendNotification($parent, $student_name, $time, $type) {
        $placeholders = [
             'parent_name' => $parent['parent_name'],
             'student_name' => $student_name,
             'time' => $time,
             'date' => date('Y-m-d')
        ];
        
        $templateName = "Attendance " . $type;
        
        // SMS
        if (!empty($parent['parent_phone'])) {
            $smsTpl = $this->getTemplate($templateName, 'sms');
            if ($smsTpl) {
                $smsMsg = $this->processTemplate($smsTpl['body'], $placeholders);
            } else {
                $statusMsg = $type === 'Check-In' ? 'arrived at school' : 'checked out from school';
                $smsMsg = "Rosmon SMS: Hello {$parent['parent_name']}, your child {$student_name} has {$statusMsg} at {$time}.";
            }
            $this->logNotification($parent['parent_phone'], $smsMsg, 'sms');
        }
        
        // Email
        if (!empty($parent['parent_email'])) {
            $emailTpl = $this->getTemplate($templateName, 'email');
            if ($emailTpl) {
                $subject = $this->processTemplate($emailTpl['subject'] ?? "School Attendance: {$type}", $placeholders);
                $body = $this->processTemplate($emailTpl['body'], $placeholders);
            } else {
                $subject = "School Attendance: {$type}";
                $statusMsg = $type === 'Check-In' ? 'arrived at school' : 'checked out from school';
                $body = "<h2>Hello {$parent['parent_name']},</h2><p>This is to notify you that your child <strong>{$student_name}</strong> has {$statusMsg} at <strong>{$time}</strong>.</p><p>Thank you.</p>";
            }
            \app\Support\MailHelper::send($parent['parent_email'], $subject, $body);
        }
    }
}
