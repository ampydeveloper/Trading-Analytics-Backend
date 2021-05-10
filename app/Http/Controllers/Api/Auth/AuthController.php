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

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
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

            return response()->json([
                        'message' => 'Register successfully!',
                        'alert' => 'Email is sent please verify your email'
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
    
    public function emailConfirmation($token) {
        $user = User::where('confirmation_code', $token)->first();
        if($user != null) {
            if ($user->confirmed == false) {
                User::whereId($user->id)->update(['confirmed' => 1]);
                return response()->json([
                            'message' => 'Email confirmed successfully!',
                                ], 200);
            } else {
                return response()->json([
                            'message' => 'Email is already confirmed.',
                                ], 200);
            }
        } else {
            return response()->json([
                        'message' => 'Invalid Token',
                            ], 200);
        }
    }

    public function login(Request $request)
    {
        try {
            if(User::where('email', $request->email)->where('confirmed', false)->exists()) {
                return response()->json([
                            'error' => [
                                'message' => 'Please confirm your email.',
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
                return response()->json(['error' => 'Unauthorized', 'token' => $token], Response::HTTP_UNAUTHORIZED);
            }
            auth('api')->setToken($token);
            $user = auth('api')->authenticate();
            if($user->two_factor_secret_activated == false){
                $status =  $this->generateGoogleAuthQR($user);
                if ($status['status'] == true) {
                    return response()->json(['id'=>$user->id,'auth' => $token, 'image' => $status['img']], Response::HTTP_OK);
                }
            }else{
                return response()->json(['id'=>$user->id,'auth' => $token, 'image' => null], Response::HTTP_OK);
            }
            
            auth()->logout();
            return response()->json([
                'error' => [
                    'message' => 'Google Auth Error',
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

    public function getUser(Request $request)
    {
        try {
            $user = $request->user();
            $user = User::with('roles')->where('id',$user->id)->first();
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

    public function logout()
    {
        auth()->logout();
        return response()->json(['message'=>'logged out successfully'], Response::HTTP_OK);
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

//        dd($request->all());
        $user = User::where('email', $request->input('email'))->first();
        if ($user) {
            try {
                $token = md5(uniqid(mt_rand(), true));
                if ($user->update(['confirmation_code' => $token])) {
                    $request->reset_link = url('/') . '/auth/password-reset/' . $token;
                    $request->name = $user->first_name . ' ' . $user->last_name;
                    \Mail::send(new SendPassworedResetLink($request));
                    return response()->json(['message' => 'Email sent to you'], 200);
                }
            } catch (\Expection $e) {
                \Log::error($e);
            }
            return response()->json(['error' => 'Somthing went wrong'], 200);
        }
        return response()->json(['error' => 'This email not registered with us'], 200);
    }

    public function passwordResetRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string',
            'confirmPassword' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('confirmation_code', $request->input('token'))->first();
        if ($user) {
            try {
                if ($user->update(['password' => $request->input('password')])) {
                    return response()->json(['message' => 'Password reset successfully'], 200);
                }
            } catch (\Expection $e) {
                \Log::error($e);
                //return response()->json(['error'=>$e->getMessage()],200);        
            }
            return response()->json(['error' => 'Somthing went wrong'], 200);
        }
        return response()->json(['error' => 'Invalid Token'], 200);
    }

    public function generateGoogleAuthQR($user)
    {
        try {
            $g = new \Google\Authenticator\GoogleAuthenticator();
            $secret = $g->generateSecret();
            $u = User::where('id',$user->id)->first();
            $u->update(['two_factor_secret'=>$secret]);
            $url =  $g->getURL($user->full_name, 'slabstox.com', $secret);
            return ['status' => true, 'img' => $url];
        } catch (\Expection $e) {
            \Log::error($e);
        }

        return ['status' => false];
    }

    public function checkGoogleAuthCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'code' => 'required|string',
            'token' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $u = User::where('id',$request->id)->first();
            $g = new \Google\Authenticator\GoogleAuthenticator();
            $secret = $u->two_factor_secret;
            $check_this_code = $request->code;
            if ($g->checkCode($secret, $check_this_code)) {
                $u->update(['two_factor_secret_activated'=>1]);
                return response()->json(['token' => $request->token], 200);
            } else {
                return response()->json(['error' => 'Invalid Code'], 200);
            }
        } catch (\Expection $e) {
            \Log::error($e);
            return response()->json(['not able to verify'], 500);
        }
    }

    public function redirect(Request $request)
    {
        return redirect(env('CLIENT_BASE_URL'));
    }
}
