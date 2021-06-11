<?php

namespace App\Mail\Api\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendPassworemaildResetLink.
 */
class EmailConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Request
     */
    public $request;

    /**
     * SendPassworemaildResetLink constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->to($this->request->email, $this->request->first_name)
            ->view('api.mail.email-confirmation')
            ->subject('Welcome to '.app_name())
            ->from(config('mail.from.address'), config('mail.from.name'));
    }
}
