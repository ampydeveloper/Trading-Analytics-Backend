<div class="slabstox-mail-box" style="font-family: Helvetica, Arial, sans-serif;font-size: 16px;line-height: 19px;">
    <div class="header-email" style="background: #fff;text-align: center;padding: 25px 0 20px 0;">
        <img src="<?php echo url('img/dashboard-sidebar-middel-logo.png'); ?>" alt="Slabstox Pro logo" style="width: 300px;">
    </div>
    <div class="content-mail" style="width: 100%;background: #000;padding: 50px 0;color: #fff;">
        <div class="content-mail-inner" style="padding: 40px 18px;max-width: 600px!important;margin: 0 auto;">
            <p style="margin-top: 0;margin-bottom: 15px;"><h4 style="margin-top: 0;margin-bottom: 0px;font-size: 20px;display: inline-block;">Hello!</h4> You are receiving this email because we received a password reset request for your account.</p>

            <a class="btn-click" href="{{$request->reset_link}}" style="margin-bottom: 15px;display: inline-block;color: #1ce783;text-decoration: none;font-weight: 700;">Please click here to reset your password.</a>

            <p class="regards-before" style="margin-top: 20px;margin-bottom: 15px;">If you did not request a password reset, no further action is required.</p>
            <p class="regards-text" style="margin-top: 0;margin-bottom: 15px;margin: 0;">Regards,<br>
                {{ app_name() }} Team</p>
        </div>
    </div>
    <div class="footer-mail" style="width: 100%;background: #15202b;margin: 0;padding: 20px 0;color: #e6e4e4;font-size: 13px;">
        <div class="footer-mail-inner" style="width: 80%;margin: 0 auto;padding: 10px 0px;text-align: center;">
            <p style="margin: 0;">If you have trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser: <a href="{{$request->reset_link}}" style="color: #fff;">{{$request->reset_link}}</a> </p>
        </div>
    </div>
</div>