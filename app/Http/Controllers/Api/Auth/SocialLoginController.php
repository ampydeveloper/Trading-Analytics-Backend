<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Exceptions\GeneralException;
use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;
use App\Models\Auth\User;
use App\Models\Auth\SocialAccount;
use Validator;
use Symfony\Component\HttpFoundation\Response as Response;

/**
 * Class SocialLoginController.
 */
class SocialLoginController extends Controller
{
    protected $auth;


    /**
     * SocialLoginController constructor.
     *
     * @param UserRepository  $userRepository
     * @param SocialiteHelper $socialiteHelper
     */
    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param Request $request
     * @param $provider
     *
     * @throws GeneralException
     *
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function login(Request $data, $provider)
    {
        $validator = Validator::make($data->all(), [
            'email' => 'required|string|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'id' => 'required|string',
            'avatar' => 'required|string',
            
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // User email may not provided.
            $user_email = $data->email ?: "{$data->id}@{$provider}.com";

            // Check to see if there is a user with this email first.
            $user = User::where('email', $user_email)->first();

            /*
         * If the user does not exist create them
         * The true flag indicate that it is a social account
         * Which triggers the script to use some default values in the create method
         */
            if (!$user) {
                // Registration is not enabled
                if (!config('access.registration')) {
                    return response()->json([
                        'error' => 'Login Not Allowed',
                    ], 400);
                }

                $user = User::create([
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'email' => $user_email,
                    'active' => true,
                    'confirmed' => true,
                    'password' => null,
                    'avatar_type' => $provider,
                    'avatar' => $data->avatar,
                ]);

                if ($user) {
                    // Add the default site role to the new user
                    $user->assignRole(config('access.users.default_role'));
                }
            }

            $token = md5(uniqid(mt_rand(), true) . '' . $data->id);
            // See if the user has logged in with this social account before
            if (!$user->hasProvider($provider)) {
                // Gather the provider data for saving and associate it with the user
                $user->providers()->save(new SocialAccount([
                    'provider' => $provider,
                    'provider_id' => $data->id,
                    'token' => $token,
                    'avatar' => $data->avatar,
                ]));
            } else {
                // Update the users information, token and avatar can be updated.
                $user->providers()->update([
                    'token' => $token,
                    'avatar' => $data->avatar,
                ]);

                $user->avatar_type = $provider;
                if($user->avatar == ''){
	                $user->avatar_location = $data->avatar;
                }
                $user->update();
            }

            // User has been successfully created or already exists
            auth()->login($user, true);
            $token = $this->auth->fromUser($user);
            if($user->two_factor_secret_activated == false){
                $status =  $this->generateGoogleAuthQR($user);
                if ($status['status'] == true) {
                    return response()->json(['id'=>$user->id,'auth' => $token, 'image' => $status['img']], 200);
                }
            }else{
                return response()->json(['id'=>$user->id,'auth' => $token, 'image' => null], 200);
            }
            
            auth()->logout();
            return response()->json([
                'error' => [
                    'message' => 'Google Auth Error',
                ],
            ], 500);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'error' => [
                    'message' => 'Internal Server error',
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ],
            ], 500);
        }
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
}
