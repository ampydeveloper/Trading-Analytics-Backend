<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use Illuminate\Http\Request;
use App\Models\Auth\User;
use Symfony\Component\HttpFoundation\Response as Response;
use Validator;
use App\Mail\Api\Auth\SendPassworedResetLink;
use App\Mail\Api\Auth\EmailConfirmation;

class AuthController extends Controller {

    public function register(RegisterRequest $request) {
        $messages = [
            'password.regex' => 'Password must contain uppercase, lowercase, at least one digit, non-alphanumeric and unicode characters.',
        ];
        $validator = Validator::make($request->all(), [
                    'password' => 'required|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%]).*$/',
                        ], $messages);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } 

        try {
            $user = User::create([
                        'first_name' => $request->input('first_name'),
                        'email' => $request->input('email'),
                        'confirmation_code' => md5(uniqid(mt_rand(), true)),
                        'active' => true,
                        'password' => $request->input('password'),
                        'avatar_type' => 'storage',
                        'avatar_location' => 'dummy.jpg',
                            // If users require approval or needs to confirm email
                            //                'confirmed' => !(config('access.users.requires_approval') || config('access.users.confirm_email')),
            ]);

            if ($user) {
                // Add the default site role to the new user
                $user->assignRole(config('access.users.default_role'));
            }

            $request->email_confirmation_link = url('/') . '/api/auth/email-confirmation/' . $user->confirmation_code;
            \Mail::send(new EmailConfirmation($request));

            //register wp user
            $res = $this->__registerToWordpress($request);
            return response()->json([
                        'message' => 'You have registered successfully. Email has been sent to you. Please verify it to login.'
                            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                        'error' => [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                        ],
                            ], 500);
        }
    }

    public function __registerToWordpress($request) {
        // Create Wordpress site user
        $endpoint = "https://slabstox.com/wp-admin/admin-ajax.php";
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $endpoint);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "POST");
        // curl_setopt($curl_handle, CURLOPT_POSTREDIR, 3);
        // curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "action=other_signup_ajax_handler&name=$request->first_name&email=$request->email&password=$request->password");
        curl_exec($curl_handle);
        // if (curl_errno($curl_handle)) {
        //     $error_msg = curl_error($curl_handle);
        // }
        // if (isset($error_msg)) {
        //     echo $error_msg;
        // }
        curl_close($curl_handle);
        // return;
    }

    public function emailConfirmation($token) {
        $user = User::where('confirmation_code', $token)->first();
        if ($user != null) {
            if ($user->confirmed == false) {
                User::whereId($user->id)->update(['confirmed' => 1]);
                $message = 'Email has been confirmed successfully. You can login now.';
                return view('frontend.email-confirmation', compact('message'));
            } else {
                $message = 'Email is already confirmed. You can login.';
                return view('frontend.email-confirmation', compact('message'));
            }
        } else {
            $message = 'Your email verification token is invalid. Try forgot password option to proceed further.';
            return view('frontend.email-confirmation', compact('message'));
        }
    }

    public function login(Request $request) {
        try {
            if (User::where('email', $request->email)->where('confirmed', false)->exists()) {
                return response()->json([
                            'error' => [
                                'message' => 'Your account has not been confirmed. Please check your email and verify it to login.',
                            ],
                                ], 500);
            }

            //attem login with token 
            if ($request->has('token')) {
                auth('api')->setToken($request->input('token'));
                $user = auth('api')->authenticate();

                if ($user) {
                    return response()->json(['token' => $request->input('token')], Response::HTTP_OK);
                }
            }

            // Validate input data
            $validator = Validator::make($request->all(), [
                        'email' => 'required|string|email',
                        'password' => 'required|string',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            $credentials = request(['email', 'password']);
            $credentials['active'] = 1;
            $credentials['deleted_at'] = null;

            // Check the combination of email and password, also check for activation status
            if (!$token = auth('api')->attempt($credentials)) {
                //check for sync users
                $credentials_sync = request(['email']);
                $credentials_sync['password'] = '123456';
                $credentials_sync['active'] = 1;
                $credentials_sync['deleted_at'] = null;
                if (!$token = auth('api')->attempt($credentials_sync)) {
                    return response()->json(['error' => [ 'message' => 'Invalid Email or Password. Try again.'], 'token' => $token], Response::HTTP_UNAUTHORIZED);
                }
            }
            auth('api')->setToken($token);
            $user = auth('api')->authenticate();

            if ($user->two_factor_secret_activated == false) {
                $status = $this->generateGoogleAuthQR($user);
                if ($status['status'] == true) {
                    return response()->json(['id' => $user->id, 'auth' => $token, 'image' => $status['img']], Response::HTTP_OK);
                }
            } else {
                return response()->json(['id' => $user->id, 'auth' => $token, 'image' => null], Response::HTTP_OK);
            }

            auth()->logout();
            return response()->json([
                        'error' => [
                            'message' => 'Google authentication is not working. Please try again.',
                        ],
                            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                        'error' => [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                        ],
                            ], 500);
        }
    }

    public function getUser(Request $request) {
        try {
            $user = $request->user();
            $user = User::with('roles')->where('id', $user->id)->first();
            return response()->json(['user' => $user], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                        'error' => [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                        ],
                            ], 400);
        }
    }

    public function logout() {
        auth()->logout();
        return response()->json(['message' => 'logged out successfully'], Response::HTTP_OK);
    }

    public function sendResetLinkEmail(Request $request) {
        $validator = Validator::make($request->all(), [
                    'email' => 'required|string|email',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('email', $request->input('email'))->first();
        if ($user) {
            try {
                $token = md5(uniqid(mt_rand(), true));
                if ($user->update(['confirmation_code' => $token])) {
                    $request->reset_link = env('VUE_URL') . 'reset-password?token=' . $token;
                    $request->name = $user->first_name . ' ' . $user->last_name;
                    \Mail::send(new SendPassworedResetLink($request));
                    return response()->json(['message' => 'An email has been sent to you successfully.'], 200);
                }
            } catch (\Expection $e) {
                \Log::error($e);
            }
            return response()->json(['error' => 'There has been an error.Please try again.'], 200);
        }
        return response()->json(['error' => 'No account with this email is registered with us. Check your email address and try again.'], 200);
    }

    public function passwordResetRequest(Request $request) {
        $validator = Validator::make($request->all(), [
                    'token' => 'required|string',
                    'password' => 'min:6|required|same:confirmPassword',
                    'confirmPassword' => 'min:6|required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('confirmation_code', $request->input('token'))->first();
        if ($user) {
            try {
                // $a = $this->__passwordResetToWordpress($request,$user->email);

                if ($user->update(['password' => $request->input('password')])) {
                    //reset password on wordpress
                    $this->__passwordResetToWordpress($request, $user->email);
                    return response()->json(['message' => 'Your account password has been reset successfully. You can login now.'], 200);
                }
            } catch (\Expection $e) {
                \Log::error($e);
                //return response()->json(['error'=>$e->getMessage()],200);        
            }
            return response()->json(['error' => 'There has been an error. Please try again.'], 200);
        }
        return response()->json(['error' => 'Your passsword verification token is invalid. Try forgot password option again to proceed further.'], 200);
    }

    public function __passwordResetToWordpress($request, $email) {
        // Reset password Wordpress site user
        $endpoint = "https://slabstox.com/wp-admin/admin-ajax.php";
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $endpoint);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "action=auto_password_reset_ajax_handler&email=$email&password=$request->password");
        curl_exec($curl_handle);
        curl_close($curl_handle);
    }

    public function autoShiftUsers() {
        $users = User::select("email", "first_name")->get()->toArray();
        // $j = json_encode($users);
        // save all users to Wordpress site user
        foreach ($users as $key => $user) {
            $first_name = $user['first_name'];
            $email = $user['email'];
            $endpoint = "https://slabstox.com/wp-admin/admin-ajax.php";
            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, $endpoint);
            curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "action=auto_save_users_ajax_handler&name=$first_name&email=$email");
            // curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "action=auto_save_users_ajax_handler&data=$j");
            $c = curl_exec($curl_handle);
            curl_close($curl_handle);
        }
        die;
    }

    //save users from wordpress
    public function autoShiftUsersFromWordpress() {
        $endpoint = "https://slabstox.com/wp-admin/admin-ajax.php";
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $endpoint);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, "true");
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "action=get_users_ajax_handler");
        $c = curl_exec($curl_handle);

        curl_close($curl_handle);
        $json = json_decode($c, true);

        foreach ($json["data"] as $data) {

            if (!User::where("email", $data['user_email'])->exists()) {
                $user = User::create([
                            'first_name' => $data['user_login'],
                            'email' => $data['user_email'],
                            'confirmation_code' => md5(uniqid(mt_rand(), true)),
                            'active' => true,
                            'password' => "123456",
                            'avatar_type' => 'storage',
                            'avatar_location' => 'dummy.jpg',
                ]);

                if ($user) {
                    // Add the default site role to the new user
                    $user->assignRole(config('access.users.default_role'));
                }
            }
        }
        die;
    }

    public function generateGoogleAuthQR($user) {
        try {
            $g = new \Google\Authenticator\GoogleAuthenticator();
            $secret = $g->generateSecret();
            $u = User::where('id', $user->id)->first();
            $u->update(['two_factor_secret' => $secret]);
            $url = $g->getURL($user->full_name, 'slabstox.com', $secret);
            return ['status' => true, 'img' => $url];
        } catch (\Expection $e) {
            \Log::error($e);
        }

        return ['status' => false];
    }

    public function checkGoogleAuthCode(Request $request) {
        $validator = Validator::make($request->all(), [
                    'id' => 'required',
                    'code' => 'required|string',
                    'token' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $u = User::where('id', $request->id)->first();
            $g = new \Google\Authenticator\GoogleAuthenticator();
            $secret = $u->two_factor_secret;
            $check_this_code = $request->code;
            if ($g->checkCode($secret, $check_this_code)) {
                $u->update(['two_factor_secret_activated' => 1]);
                return response()->json(['token' => $request->token], 200);
            } else {
                return response()->json(['error' => 'Google authentication code is invalid. Please try again.'], 200);
            }
        } catch (\Expection $e) {
            \Log::error($e);
            return response()->json(['Google authentication is not working. Please try again.'], 500);
        }
    }

    public function redirect(Request $request) {
        return redirect(env('CLIENT_BASE_URL'));
    }

}
