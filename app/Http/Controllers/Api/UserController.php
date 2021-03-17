<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use Exception;
use Illuminate\Http\Request;
use Storage;
use Validator;
use App\Repositories\Backend\Auth\UserRepository;

class UserController extends Controller
{
    public function profileUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required',
                // 'mobile' => 'required',
                // 'dob' => 'required',
                // 'address' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 500);
            }

            $user_id = auth()->user()->id;
            $user = User::where('id', $user_id)->first();
            if (!$user) {
                throw new Exception('User not found');
            }
            $name = explode(' ', $request->name);
            $data = [
                'first_name' => $name[0],
                'last_name' => (isset($name[1])) ? $name[1] : '',
                'email' => $request->email,
                'mobile' => $request->input('mobile', ''),
                'dob' => $request->input('dob', ''),
                'address' => $request->input('address', ''),
            ];
            $user->update($data);
            return response()->json(['status' => 200, 'data' => 'Profile Updated'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }


    public function notificationSettingsUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'slabNotification' => 'required',
                'myListingNotification' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 500);
            }

            $user_id = auth()->user()->id;
            $user = User::where('id', $user_id)->first();
            if (!$user) {
                throw new Exception('User not found');
            }
            $data = [
                'slab_notification' => $request->slabNotification,
                'my_listing_notification' => $request->myListingNotification,
            ];
            $user->update($data);
            return response()->json(['status' => 200, 'data' => 'Notification Updated'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSocialAccounts(Request $request)
    {
        try {
            $user_id = auth()->user()->id;
            $google = SocialAccount::where('user_id', $user_id)->where('provider', 'google')->select('id')->first();
            $facebook = SocialAccount::where('user_id', $user_id)->where('provider', 'facebook')->select('id')->first();
            return response()->json(['status' => 200, 'google' => $google, 'facebook' => $facebook], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function addSocialAccounts(Request $data, $provider)
    {
        $validator = Validator::make($data->all(), [
            'email' => 'required|string|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'id' => 'required|string',
            'avatar' => 'required|string',

        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        try {
            $user_id = auth()->user()->id;
            $user = User::where('id', $user_id)->first();
            $token = md5(uniqid(mt_rand(), true) . '' . $data->id);
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
                if ($user->avatar == '') {
                    $user->avatar_location = $data->avatar;
                }
                $user->update();
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function removeSocialAccounts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'password' => 'required|string',
            'provider' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        try {
            $user_id = auth()->user()->id;
            $u = User::where('id', $user_id)->update(['password' => $request->input('password')]);
            if (!$u) {
                throw new Exception('Unable to update password');
            }
            SocialAccount::where('id', $request->input('id'))->delete();
            return response()->json(['status' => 200, 'message' => 'Disconnected successfully'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function updateProfileImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $request->image)) {
                $data = substr($request->image, strpos($request->image, ',') + 1);

                $data = base64_decode($data);
                $user_id = auth()->user()->id;
                $name = 'user/' . $user_id . '/' . md5(microtime()) . $user_id . '-profile.png';
                $a = Storage::disk('local')->put('public/' . $name, $data);
                if (!$a) {
                    throw new Exception('Unable to upload image');
                }
                User::where('id', $user_id)->update(['avatar_type'=>'storage','avatar_location' => $name]);
                return response()->json(['status' => 200, 'message' => 'Image Updated'], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function generateImageUsingBase(Request $request)
    {
//        dump($request->all());
        $validator = Validator::make($request->all(), [
            'image' => 'required',
            'prefix' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $request->image)) {
                $data = substr($request->image, strpos($request->image, ',') + 1);

                $data = base64_decode($data);
                $user_id = rand(4, 7);
//                dump($user_id);
                $name = 'dash/'.$request->prefix . $user_id . '.png';
//                dd($name);
                $a = Storage::disk('local')->put('public/' . $name, $data);
                if (!$a) {
                    throw new Exception('Unable to upload image');
                }
//                dd(url('storage/' . $name));
                return response()->json(['status' => 200, 'url' => url('storage/' . $name)], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getAllUsersForAdmin(Request $request){
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        try{
            $page = $request->input('page', 1);
            $take = $request->input('take', 30);
            $search = $request->input('search', null);
            $skip = $take * $page;
            $skip = $skip - $take;

            return response()->json(['status' => 200, 'data' => User::with('roles', 'permissions', 'providers')->withTrashed()->skip($skip)->take($take)->get(), 'next' => ($page)], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveUserForAdmin(Request $request){
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:users,id',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'email' => 'required|email|unique:users,email,'.$request->get('id'),
            'mobile' => 'nullable|numeric',
            'dob' => 'nullable',
            'address' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages(), 201);
        }
        try{
            User::whereId($request->get('id'))->update($request->only('first_name', 'last_name', 'email', 'mobile', 'dob', 'address'));
            return response()->json(['status' => 200, 'data' => ['message' => 'User saved']], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateUserAttributeForAdmin(User $user, $action){
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        try{
            if($action == 'active'){
                if($user->isActive()){
                    $user->update(['active' => 0]);
                }else{
                    $user->update(['active' => 1]);
                }
            }elseif($action == 'delete'){
                if ($user->trashed()) {
                    $user->restore();
                } else {
                    $user->delete();
                }
            }
            return response()->json(['status' => 200, 'data' => ['message' => 'User status updated']], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }
}
