<?php

namespace App\Http\Controllers;

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

class TestEmailController extends Controller
{
    /**
     * Send a test email to codrut_befu@yahoo.com
     */
    public function sendTestEmail()
    {
        try {
            $email = 'codrut_befu@yahoo.com';
            $message = 'This is a test email from Master Spa Middleware application.';

            Mail::to($email)->send(new TestEmail($message));

            return response()->json([
                'success' => true,
                'message' => "Test email sent successfully to {$email}",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send test email to a custom email address
     */
    public function sendTestEmailToCustom(string $email)
    {
        try {
            $message = 'This is a test email from Master Spa Middleware application.';

            Mail::to($email)->send(new TestEmail($message));

            return response()->json([
                'success' => true,
                'message' => "Test email sent successfully to {$email}",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
