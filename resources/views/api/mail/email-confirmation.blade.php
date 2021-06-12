<div class="slabstox-mail-box" style="font-family: Helvetica, Arial, sans-serif;font-size: 16px;line-height: 19px;">
    <div class="header-email" style="background: #fff;text-align: center;padding: 25px 0 20px 0;">
        <img src="<?php echo url('img/dashboard-sidebar-middel-logo.png'); ?>" alt="Slabstox Pro logo" style="width: 300px;">
    </div>
    <div class="content-mail" style="width: 100%;background: #000;padding: 50px 0;color: #fff;">
        <div class="content-mail-inner" style="padding: 40px 18px;max-width: 600px!important;margin: 0 auto;">
            
            <p style="margin-top: 0;margin-bottom: 15px;"><h4 style="margin-top: 0;margin-bottom: 0px;font-size: 20px;display: inline-block;">Congratulations!</h4> You've officially created an account to use the 100%-free SlabStoxPro platform. Now it's time to start your journey on SlabStoxPro by tracking the hottest Slabs, adding Slabs to your portfolio, and getting updated pricing information on the Slabs you want to follow. You can even request for us to add a Slab to our database!</p>

            <p style="margin-top: 0;margin-bottom: 15px;">SlabStoxPro is evolving daily, so be sure to check back to find out what's new and how you can increase your knowledge in the sports card industry. Thank you for joining the SlabStoxPro team!</p>

            <a class="btn-click" href="{{$request->email_confirmation_link}}" style="margin-bottom: 15px;display: inline-block;color: #1ce783;text-decoration: none;font-weight: 700;">Please click here to confirm your email account.</a>

            <p class="regards-before" style="margin-top: 20px;margin-bottom: 15px;">If you did not request a signup, no further action is required.</p>
            <p class="regards-text" style="margin-top: 0;margin-bottom: 15px;margin: 0;">Regards,<br>
                {{ app_name() }} Team</p>
        </div>
    </div>
    <div class="footer-mail" style="width: 100%;background: #15202b;margin: 0;padding: 20px 0;color: #e6e4e4;font-size: 13px;">
        <div class="footer-mail-inner" style="width: 80%;margin: 0 auto;padding: 10px 0px;text-align: center;">
            <p style="margin: 0;">If you have trouble clicking the "Email Confirmation" button, copy and paste the URL below into your web browser: <a href="{{$request->email_confirmation_link}}" style="color: #fff;">{{$request->email_confirmation_link}}</a></p>
        </div>
    </div>
</div>