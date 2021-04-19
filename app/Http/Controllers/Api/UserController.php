<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\SocialAccount;
use App\Models\Auth\User;
use App\Models\Auth\Role;
use App\Models\AppSettings;
use Exception;
use Illuminate\Http\Request;
use Storage;
use Validator;
use App\Repositories\Backend\Auth\UserRepository;
use \Spatie\Activitylog\Models\Activity;

class UserController extends Controller {

    public function profileUpdate(Request $request) {
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

    public function notificationSettingsUpdate(Request $request) {
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

    public function getSocialAccounts(Request $request) {
        try {
            $user_id = auth()->user()->id;
            $google = SocialAccount::where('user_id', $user_id)->where('provider', 'google')->select('id')->first();
            $facebook = SocialAccount::where('user_id', $user_id)->where('provider', 'facebook')->select('id')->first();
            return response()->json(['status' => 200, 'google' => $google, 'facebook' => $facebook], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function addSocialAccounts(Request $data, $provider) {
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

    public function removeSocialAccounts(Request $request) {
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
            $u = (User::where('id', $user_id)->first())->update(['password' => $request->input('password')]);
            if (!$u) {
                throw new Exception('Unable to update password');
            }
            SocialAccount::where('id', $request->input('id'))->delete();
            return response()->json(['status' => 200, 'message' => 'Disconnected successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function updateProfileImage(Request $request) {
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
                (User::where('id', $user_id)->first())->update(['avatar_type' => 'storage', 'avatar_location' => $name]);
                return response()->json(['status' => 200, 'message' => 'Image Updated'], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function generateImageUsingBase(Request $request) {
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
                $name = 'dash/' . $request->prefix . $user_id . '.png';
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

    public function getAllUsersForAdmin(Request $request) {
        if (!auth()->user()->isAdmin() && !auth()->user()->isModerator()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        try {
            $page = $request->input('page', 1);
            $take = $request->input('take', 30);
            $search = $request->input('search', null);
            $skip = $take * $page;
            $skip = $skip - $take;

            $users = User::where(function ($q) use ($request) {
                        if ($request->has('search') && $request->get('search') != '' && $request->get('search') != null) {
                            $searchTerm = strtolower($request->get('search'));
                            $q->orWhere('first_name', 'like', '%' . $searchTerm . '%');
                            $q->orWhere('last_name', 'like', '%' . $searchTerm . '%');
                            $q->orWhere('email', 'like', '%' . $searchTerm . '%');
                            $q->orWhere('id', 'like', '%' . $searchTerm . '%');
                        }
                    })->with('roles', 'permissions', 'providers')->withTrashed()->skip($skip)->take($take)->get();

//            $users = User::with('roles', 'permissions', 'providers')->withTrashed()->skip($skip)->take($take)->get();
            return response()->json(['status' => 200, 'data' => $users, 'next' => ($page)], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveUserForAdmin(Request $request) {
        if (!auth()->user()->isAdmin() && !auth()->user()->isModerator()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:users,id',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'email' => 'required|email|unique:users,email,' . $request->get('id'),
            'mobile' => 'nullable|numeric',
            'dob' => 'nullable',
            'address' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages(), 201);
        }
        try {
//           User::whereId($request->get('id'))->update($request->only('first_name', 'last_name', 'email', 'mobile', 'dob', 'address'));
//            Role::where('model_id', $request->get('id'))->update(['role_id'=>$request->get('user_roles')]);
//           $user = User::whereId($request->input('id'))->get();
//            $user->roles()->attach($request->input('user_roles'));
//            return response()->json(['status' => 200, 'data' => ['message' => $user]], 200);

            $user = User::where('id', $request->input('id'))->withTrashed()->first();
            $data = [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'mobile' => $request->input('mobile', ''),
                'dob' => $request->input('dob', ''),
                'address' => $request->input('address', ''),
            ];
            $user->update($data);

            if($request->has('user_roles')){
                $role = Role::whereId($request->get('user_roles'))->first();
                if($role){
                    $user->roles()->detach();
                    $user->forgetCachedPermissions();
                    $user->assignRole($role->name);                    
                }
            }


//            return response()->json(['status' => 200, 'data' => ['message' => $user]], 200);
            return response()->json(['status' => 200, 'data' => ['message' => 'User saved successfully.']], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateUserAttributeForAdmin(User $user, $action) {
        if (!auth()->user()->isAdmin() && !auth()->user()->isModerator()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        try {
            if ($action == 'active') {
                if ($user->isActive()) {
                    $user->update(['active' => 0]);
                } else {
                    $user->update(['active' => 1]);
                }
            } elseif ($action == 'delete') {
                if ($user->trashed()) {
                    $user->restore();
                } else {
                    $user->delete();
                }
            }
            return response()->json(['status' => 200, 'data' => ['message' => 'User status updated.']], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function changeUSerPasswordForAdmin(User $user, Request $request) {
        if (!auth()->user()->isAdmin() && !auth()->user()->isModerator()) {
            return response()->json(['error' => 'Unauthorised'], 301);
        }
        try {
            $user->update(['password' => $request->get('password')]);
            return response()->json(['status' => 200, 'data' => ['message' => 'User password updated.']], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function createUser(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string',
                'last_name' => 'nullable|string',
                'email' => 'required|email|unique:users',
                'mobile' => 'nullable|numeric',
                'dob' => 'nullable',
                'address' => 'nullable|string'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 500);
            }

            $user = User::create([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'mobile' => $request->input('mobile', ''),
                'dob' => $request->input('dob', ''),
                'address' => $request->input('address', ''),
            ]);

            if ($request->has('user_roles')) {
                $role = Role::whereId($request->get('user_roles'))->first();
                if ($role) {
                    $user->roles()->detach();
                    $user->forgetCachedPermissions();
                    $user->assignRole($role->name);
                }
            }

            return response()->json(['status' => 200, 'data' => 'User created successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function settings(Request $request){
        try{
            if (!auth()->user()->isAdmin()){
                return response()->json(['error' => 'Unauthorised'], 301);
            }
            if($request->method() == 'GET'){
                $settings = AppSettings::first();
                return response()->json(['status' => 200, 'data' => $settings], 200);
            }
            $data = $request->all();
            if($request->file('slab_image')){
                $filename = 'Default-Slab-'.$request->slab_image->getClientOriginalName();
                Storage::disk('public')->put($filename, file_get_contents($request->slab_image->getRealPath()));
                $data['slab_image'] = $filename;
            }
            if($request->file('listing_image')){
                $filename = 'Default-Listing-'.$request->slab_image->getClientOriginalName();
                Storage::disk('public')->put($filename, file_get_contents($request->listing_image->getRealPath()));
                $data['listing_image'] = $filename;
            }
            AppSettings::updateOrCreate(['id' => 1],$data);
            return response()->json(['status' => 200, 'message' => 'Settings saved successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getUsersForActivityLogs(){
        try {
            if (!auth()->user()->isAdmin()) {
                return response()->json(['error' => 'Unauthorised'], 301);
            }
            $users = User::role([config('access.users.moderator_role'), config('access.users.data_entry_role')])->get()->map(function($us){
                return ['id' => $us->id, 'name' => $us->full_name];
            });
            $models = Activity::select('subject_type')->distinct()->pluck('subject_type')->map(function($model){
                $orig = $model;
                $model = explode('\\', $model);
                $model = $model[count($model) - 1];
                if (strtolower($model) == 'ebayitems') {
                    $model = 'Listings';
                }
                return ['id' => $orig, 'name' => $model];
            });
            return response()->json(['status' => 200, 'data' => ['users' => $users, 'models' => $models]], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getActivityLogs(User $user, Request $request){
        try {
            if (!auth()->user()->isAdmin()) {
                return response()->json(['error' => 'Unauthorised'], 301);
            }
            
            $logs = Activity::where('causer_id', $user->id);
            if($request->has('model') && strlen(trim($request->query('model'))) > 0 && $request->query('model') != 'null'){
                $logs = $logs->where('subject_type', $request->query('model'));
            }
            if ($request->has('sts') && strlen(trim($request->query('sts'))) > 0 && $request->query('sts') != 'null' && strpos($request->query('model'), 'RequestListing') !== false) {
                $logs = $logs->where('properties->attributes->approved', $request->query('sts'));
            }
            $logs = $logs->paginate(20);
            $items = Collect($logs->items())->map(function($log){
                $model = explode('\\', $log->subject_type);
                $log->subject_type = $model[count($model) - 1];
                if(strtolower($log->subject_type) == 'ebayitems'){ $log->subject_type = 'Listings'; } 
                return $log;
            });
            
            return response()->json(['status' => 200, 'data' => $logs], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

}
