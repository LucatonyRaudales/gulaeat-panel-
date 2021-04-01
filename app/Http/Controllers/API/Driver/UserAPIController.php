<?php
/**
 * File name: UserAPIController.php
 * Last modified: 2020.05.21 at 17:25:21
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 */

namespace App\Http\Controllers\API\Driver;

use App\Events\UserRoleChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\CustomFieldValue;
use App\Models\Driver;
use App\Models\Media;
use App\Models\User;
use App\Repositories\CustomFieldRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Prettus\Validator\Exceptions\ValidatorException;
use Illuminate\Support\Facades\DB;

class UserAPIController extends Controller
{
    private $userRepository;
    private $uploadRepository;
    private $roleRepository;
    private $customFieldRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepository, UploadRepository $uploadRepository, RoleRepository $roleRepository, CustomFieldRepository $customFieldRepo)
    {
        $this->userRepository = $userRepository;
        $this->uploadRepository = $uploadRepository;
        $this->roleRepository = $roleRepository;
        $this->customFieldRepository = $customFieldRepo;
    }

    public function listInactive(Request $request)
    {
        $driver = DB::select("SELECT users.id,drivers.user_id,drivers.active,drivers.lat,drivers.lng,users.name FROM `drivers` join `users` on users.id=drivers.user_id ");
        $rel = DB::select('SELECT * FROM `driver_restaurants`');
        $relResult = [];
        foreach ($rel as $attribute => $value) {
            $value = (array)$value;
            $relResult[$value['user_id']] = $value;
        }

        if (empty($driver)) {
            return $this->sendError('Driver not find');
        }
        $result = [];
        foreach ($driver as $attribute => $value) {
            $value = (array)$value;
            if(!array_key_exists($value['user_id'] , $relResult)){
                $value['id'] = $value['user_id'];
            }
            else{
                $value['res'] = $relResult[$value['user_id']]['restaurant_id'];
            }
            unset($value['user_id']);
            $result[] = $value ;
                
        }
        return $this->sendResponse($result, 'Driver retrieved successfully');
    }

    function login(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required',
            ]);
            if (auth()->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
                // Authentication passed...
                $user = auth()->user();
                if (!$user->hasRole('driver')) {
                    $this->sendError('User not driver', 401);
                }
                $user->device_token = $request->input('device_token', '');
                $user->save();

                $driver = Driver::where('user_id', $user->id)->get(['lat', 'lng'])->each(function($row){
                    $row->setHidden(['custom_fields']);
                });
                $adriver = Driver::where('user_id', $user->id)->get(['active'])->each(function($row){
                    $row->setHidden(['custom_fields']);
                });

                $user['active'] = $adriver[0]['active'];
                $user['location'] = $driver;

                return $this->sendResponse($user, 'User retrieved successfully');
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 401);
        }

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return
     */
    function register(Request $request)
    {
        try {

            $this->validate($request, [
                'name' => 'required',
                'email' => 'required|unique:users|email',
                'password' => 'required',
            ]);

            $user = new User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->device_token = $request->input('device_token', '');
            $user->password = Hash::make($request->input('password'));
            $user->api_token = str_random(60);
            $user->save();

            $user->assignRole('driver');

            event(new UserRoleChangedEvent($user));

            if (isset($request->media))
                foreach (json_decode($request->media) as $item) {

                    $ext = pathinfo($item->url, PATHINFO_EXTENSION);
                    $media = new Media;
                    $media->model_type = "App\Models\User";
                    $media->model_id = $user->id;
                    $media->collection_name = "image";
                    $media->name = $item->name;
                    $media->file_name = $item->name.$ext;
                    $media->mime_type = "image/{$ext}";
                    $media->disk = "public";
                    $media->size = $item->formated_size;
                    $media->custom_properties = isset($item->custom_properties) ? $item->custom_properties : "";
                    $media->save();
                }

            $driver = new Driver;
            $driver->user_id = $user->id;
            $driver->delivery_fee = 0;
            $driver->total_orders = 0;
            $driver->earning = 0;
            $driver->available = 0;
            $driver->active = 0;
            $driver->lat = $request->lat;
            $driver->lng = $request->lng;
            $driver->save();


            $fields = [4 => $request->phone ,5 => $request->bio ,6 => $request->address ,7 => $request->transport];
            foreach ($fields as $key => $item){
                if (isset($item)){
                    $custom_fields = new CustomFieldValue;
                    $custom_fields->value = $item;
                    $custom_fields->view = $item;
                    $custom_fields->custom_field_id = $key;
                    $custom_fields->customizable_type = "App\\Models\\User";
                    $custom_fields->customizable_id = $user->id;
                    $custom_fields->save();
                }
            }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 401);
        }


        return $this->sendResponse(['user' => $user, 'driver' => $driver], 'User retrieved successfully');
    }

    function logout(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();
        if (!$user) {
            return $this->sendError('User not found', 401);
        }
        try {
            auth()->logout();
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 401);
        }
        return $this->sendResponse($user['name'], 'User logout successfully');

    }

    function user(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();

        if (!$user) {
            return $this->sendError('User not found', 401);
        }

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    function settings(Request $request)
    {
        $settings = setting()->all();
        $settings = array_intersect_key($settings,
            [
                'default_tax' => '',
                'default_currency' => '',
                'default_currency_decimal_digits' => '',
                'app_name' => '',
                'currency_right' => '',
                'enable_paypal' => '',
                'enable_stripe' => '',
                'enable_razorpay' => '',
                'main_color' => '',
                'main_dark_color' => '',
                'second_color' => '',
                'second_dark_color' => '',
                'accent_color' => '',
                'accent_dark_color' => '',
                'scaffold_dark_color' => '',
                'scaffold_color' => '',
                'google_maps_key' => '',
                'mobile_language' => '',
                'app_version' => '',
                'enable_version' => '',
                'distance_unit' => '',
            ]
        );

        if (!$settings) {
            return $this->sendError('Settings not found', 401);
        }

        return $this->sendResponse($settings, 'Settings retrieved successfully');
    }

    /**
     * Update the specified User in storage.
     *
     * @param int $id
     * @param Request $request
     *
     */
    public function update($id, Request $request)
    {
        $user = $this->userRepository->findWithoutFail($id);

        if (empty($user)) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        $input = $request->except(['password', 'api_token']);
        try {
            if ($request->has('device_token')) {
                $user = $this->userRepository->update($request->only('device_token'), $id);
            } else {
                $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->userRepository->model());
                $user = $this->userRepository->update($input, $id);

                foreach (getCustomFieldsValues($customFields, $request) as $value) {
                    $user->customFieldsValues()
                        ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
                }
            }
        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage(), 401);
        }

        return $this->sendResponse($user, __('lang.updated_successfully', ['operator' => __('lang.user')]));
    }

    function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);

        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($response == Password::RESET_LINK_SENT) {
            return $this->sendResponse(true, 'Reset link was sent successfully');
        } else {
            return $this->sendError([
                'error' => 'Reset link not sent',
                'code' => 401,
            ], 'Reset link not sent');
        }

    }
}
