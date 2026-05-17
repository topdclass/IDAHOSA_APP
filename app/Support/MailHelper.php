<?php

namespace app\Support;

class MailHelper {
    /**
     * Send a professional HTML email
     */
    public static function send($to, $subject, $message, $fromName = 'Rosmon SMS', $fromEmail = 'noreply@rosmon.edu') {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $fromEmail = str_replace(' ', '', strtolower($fromName)) . "@rosmon.edu"; // Dynamic but safe
        $headers .= "From: " . $fromName . " <" . $fromEmail . ">" . "\r\n";
        $headers .= "Reply-To: " . $fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Professional HTML Wrapper
        $htmlMessage = "
        <html>
        <head>
            <style>
                body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #334155; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
                .header { background: #1e293b; color: #ffffff; padding: 30px; text-align: center; }
                .content { padding: 40px; background: #ffffff; }
                .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; }
                .btn { display: inline-block; padding: 12px 24px; background: #6366f1; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>$fromName</h2>
                </div>
                <div class='content'>
                    $message
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " $fromName. Powered by Rosmon SMS.
                </div>
            </div>
        </body>
        </html>";

        return @mail($to, $subject, $htmlMessage, $headers);
    }
}
