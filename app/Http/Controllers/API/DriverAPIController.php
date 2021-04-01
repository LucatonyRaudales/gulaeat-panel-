<?php

namespace App\Http\Controllers\API;

use App\Events\ChangedLocation;
use App\Models\CustomFieldValue;
use App\Models\Driver;
use App\Repositories\DriverRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Illuminate\Support\Facades\Response;
use Prettus\Repository\Exceptions\RepositoryException;
use Flash;

/**
 * Class DriverController
 * @package App\Http\Controllers\API
 */

class DriverAPIController extends Controller
{
    /** @var  DriverRepository */
    private $driverRepository;

    public function __construct(DriverRepository $driverRepo)
    {
        $this->driverRepository = $driverRepo;
    }

    /**
     * Display a listing of the Driver.
     * GET|HEAD /drivers
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try{
            $this->driverRepository->pushCriteria(new RequestCriteria($request));
            $this->driverRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $drivers = $this->driverRepository->all();

        return $this->sendResponse($drivers->toArray(), 'Drivers retrieved successfully');
    }

    /**
     * Display the specified Driver.
     * GET|HEAD /drivers/{id}
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $driver = Driver::where('user_id',$id);
        $driver = ($request->with && $request->with == "user") ? $driver->with('user')->first() : $driver->first();

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        $driver = $this->addVehicleValue($driver->user_id, $driver);

        return $this->sendResponse($driver, 'Driver retrieved successfully');
    }
    
    // public function listInactive(Request $request)
    // {
    //     $drivers = Driver::where('active','1')->all();

    //     if (empty($drivers)) {
    //         return $this->sendError('Driver not find');
    //     }

    //     return $this->sendResponse($drivers, 'Driver retrieved successfully');
    // }

     /**
     * Update the specified Driver in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $driver = Driver::where('user_id', $id)->first();

        if (empty($driver)) {
            return $this->sendError('Driver not found');
        }

        $validated = $request->validate([
            'user_id' => 'integer',
            'delivery_fee' => 'numeric',
            'total_orders' => 'integer',
            'earning' => 'numeric',
            'available' => 'boolean',
            'active' => 'boolean',
            'lat' => 'numeric',
            'lng' => 'numeric',
        ]);

        $driver->update($request->all());

        $act_fields = CustomFieldValue::where('customizable_id', $driver->user_id)->get();

        $new_fields = [4 => $request->phone ,5 => $request->bio ,6 => $request->address ,7 => $request->transport];
        foreach ($act_fields as $key => $item){
            foreach ($new_fields as $k => $v){
                if ($item->custom_field_id == $k){
                    $item->value = isset($v) ? $v : $item->value;
                    $item->view = isset($v) ? $v : $item->value;
                    $item->save();
                }
            }
        }

        $driver = $this->addVehicleValue($driver->user_id, $driver);

        /*try {
            $driver = $this->driverRepository->update($input, $id);
            // if (isset($input['order_status_id']) && $input['order_status_id'] == 5 && !empty($driver)) {
            //     $this->paymentRepository->update(['status' => 'Paid'], $driver['payment_id']);
            // }
            // event(new OrderChangedEvent($oldAvailable, $driver));

            // if (setting('enable_notifications', false)) {
            //     if (isset($input['order_status_id']) && $input['order_status_id'] != $oldDriver->order_status_id) {
            //         Notification::send([$driver->user], new StatusChangedOrder($driver));
            //     }

            //     if (isset($input['driver_id']) && ($input['driver_id'] != $oldDriver['driver_id'])) {
            //         $oldDriver = $this->userRepository->findWithoutFail($input['driver_id']);
            //         if (!empty($oldDriver)) {
            //             Notification::send([$oldDriver], new AssignedOrder($driver));
            //         }
            //     }
            //}

        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }*/

        return $this->sendResponse($driver, __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }

    public function changedLocation(Request $request, $order)
    {
       broadcast(new ChangedLocation($order, $request->lat, $request->lng, $request->rotation, $request->accuracy));
       return "Order {$order} lat {$request->lat} lng {$request->lng} rotation {$request->rotation} accuracy {$request->accuracy}";
    }

    public function locate($driver){

        try {
            $driver = Driver::where('user_id', $driver)->first();
            $driver = $this->addVehicleValue($driver->user_id, $driver);

        } catch (\Exception $e){
            return $this->sendError('Driver not found');
        }

        return $this->sendResponse($driver, 'Driver retrieved successfully');
    }

    public function location($driver, Request $request){
        try {
            $driver = Driver::where('user_id', $driver)->first();

            $active = isset($request->active) ? $request->active : $driver->active;

            $driver->lat = isset($request->lat) ? $request->lat : $driver->lat;
            $driver->lng = isset($request->lng) ? $request->lng : $driver->lng;
            $driver->active = $active == 'true' ? 1 : 0;
            $driver->save();

            $driver = $this->addVehicleValue($driver->user_id, $driver);

            return $this->sendResponse($driver, 'Driver updated successfully');

        } catch (\Exception $e){
            return $this->sendError('Driver could not be updated');
        }
    }

    /** HELPERS */
    public function addVehicleValue($user_id, $driver)
    {
        $vehicle = CustomFieldValue::where('custom_field_id', 7)->where('customizable_id', $user_id)->first();
        $driver = $driver->toArray();
        unset($driver['custom_fields']);
        $driver['vehicle'] = isset($vehicle) ? $vehicle->value : "";

        return $driver;
    }
}
