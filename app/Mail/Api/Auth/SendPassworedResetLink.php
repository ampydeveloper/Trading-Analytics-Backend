<?php

namespace App\Mail\Api\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendPassworemaildResetLink.
 */
class SendPassworedResetLink extends Mailable
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
        return $this->to($this->request->email, $this->request->name)
            ->view('api.mail.forget-password')
            ->subject(app_name().' - Password Reset')
            ->from(config('mail.from.address'), config('mail.from.name'));
    }
}
