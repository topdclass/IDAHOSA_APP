<?php

namespace App\Domain;

class EmailService {

    public function sendEmail($to, $subject, $message) {
        // PHP mail() or modern mailer like PHPMailer / SwiftMailer setup goes here
        // mail($to, $subject, $message);
        return true;
    }

    /**
     * Sends the exact parent onboarding welcome email requested by the user.
     * Uses template string formatting to fill in dynamic parent credentials.
     */
    public function sendParentOnboardingWelcome($parentEmail, $parentName, $username) {
        $subject = "Welcome to Christland School: Parent Onboarding";
        $password = "parent"; // Default as requested

        $body = "Dear {$parentName},\n\n";
        $body .= "Welcome to Christland School!\n\n";
        $body .= "We are delighted to have you and your family join our community.\n\n";
        $body .= "Through our School Management System (RosmonSMS), you can:\n";
        $body .= "• Track your child’s academic progress\n";
        $body .= "• Stay updated on school events and announcements\n";
        $body .= "• Access important resources anytime\n\n";
        $body .= "Your login details:\n\n";
        $body .= "Username: {$username}\n\n";
        $body .= "Password: {$password}\n\n";
        $body .= "(For security reasons, kindly change your password after your first login.)\n\n";
        $body .= "We look forward to a wonderful school year together.\n\n";
        $body .= "Warm regards,\n";
        $body .= "Christland School Administration";

        return $this->sendEmail($parentEmail, $subject, $body);
    }
}
