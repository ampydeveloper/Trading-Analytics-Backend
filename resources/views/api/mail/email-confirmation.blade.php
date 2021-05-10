<h1>Hello!</h1>
<p>You are receiving this email because we received a signup request from this email.</p>

<a href="{{$request->email_confirmation_link}}">Email Confirmation</a>

<p>If you did not request a signup, no further action is required.</p>
<p>Regards,</p>
<p>{{ app_name() }}</p>

<hr/>

<p>If you’re having trouble clicking the "Email Confirmation" button, copy and paste the URL below into your web browser: <a href="{{$request->email_confirmation_link}}">{{$request->email_confirmation_link}}</a> </p>