<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Helpers\Translate;

class SendFeedbackReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $msg;

    public function __construct($msg)
    {
        $this->msg = $msg;
    }

    /**
     * Build the message.s
     *
     * @return $this
     */
    public function build()
    {
        $subject = Translate::msg("Dagensmenu support reply");
        return $this->markdown('Emails.SendFeedbackReplyMail')
                ->with([
                    'message' => $this->msg
                ])->subject($subject)->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
    }
}
