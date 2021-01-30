<h1>Hello!</h1>
<p>You are receiving this email because we received a password reset request for your account.</p>

<a href="{{$request->reset_link}}">Reset Password</a>

<p>If you did not request a password reset, no further action is required.</p>
<p>Regards,</p>
<p>{{ app_name() }}</p>

<hr/>

<p>If you’re having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser: <a href="{{$request->reset_link}}">{{$request->reset_link}}</a> </p>