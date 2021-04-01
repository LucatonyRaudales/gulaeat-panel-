<?php

namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Repositories\DeliveryAddressRepository;
use Flash;
use Illuminate\Http\Request;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class DeliveryAddressController
 * @package App\Http\Controllers\API
 */
class DeliveryAddressAPIController extends Controller
{
    /** @var  DeliveryAddressRepository */
    private $deliveryAddressRepository;

    public function __construct(DeliveryAddressRepository $deliveryAddressRepo)
    {
        $this->deliveryAddressRepository = $deliveryAddressRepo;
    }

    /**
     * Display a listing of the DeliveryAddress.
     * GET|HEAD /deliveryAddresses
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $this->deliveryAddressRepository->pushCriteria(new RequestCriteria($request));
            $this->deliveryAddressRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $deliveryAddresses = $this->deliveryAddressRepository->where('status', 1)->get();

        return $this->sendResponse($deliveryAddresses->toArray(), 'Delivery Addresses retrieved successfully');
    }

    /**
     * Display the specified DeliveryAddress.
     * GET|HEAD /deliveryAddresses/{id}
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        /** @var DeliveryAddress $deliveryAddress */
        if (!empty($this->deliveryAddressRepository)) {
            $deliveryAddress = $this->deliveryAddressRepository->findWithoutFail($id);

            $deliveryAddress['lat'] = $deliveryAddress['latitude'];
            $deliveryAddress['lng'] = $deliveryAddress['longitude'];

            unset($deliveryAddress['latitude']);
            unset($deliveryAddress['longitude']);
        }

        if (empty($deliveryAddress)) {
            return $this->sendError('Dirección no encontrada');
        }

        return $this->sendResponse($deliveryAddress->toArray(), 'Dirección mostrada correctamente');
    }

    /**
     * Store a newly created DeliveryAddress in storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $uniqueInput = $request->only("address");
        $otherInput = $request->except("address");

        $otherInput['latitude'] = $otherInput['latitude'];
        $otherInput['longitude'] = $otherInput['longitude'];
        $otherInput['status'] = 1;

        try {
            $deliveryAddress = $this->deliveryAddressRepository->updateOrCreate($uniqueInput, $otherInput);

        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }
        
        // unset($otherInput['latitude']);
        // unset($otherInput['longitude']);

        return $this->sendResponse($deliveryAddress->toArray(), __('lang.saved_successfully', ['operator' => __('lang.delivery_address')]));
    }

    /**
     * Update the specified DeliveryAddress in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $deliveryAddress = $this->deliveryAddressRepository->findWithoutFail($id);

        if (empty($deliveryAddress)) {
            return $this->sendError('Delivery Address not found');
        }

        $input = $request->all();

        $input['latitude'] = $input['latitude'];
        $input['longitude'] = $input['longitude'];
        unset($input['lat']);
        unset($input['lng']);

        if ($input['is_default'] == true){
            $this->deliveryAddressRepository->initIsDefault($id);
            $deliveryAddress = $this->deliveryAddressRepository->create($input);

            return $this->sendResponse($deliveryAddress->toArray(), __('lang.updated_successfully', ['operator' => __('lang.delivery_address')]));
        }

        try {
            $deliveryAddress = $this->deliveryAddressRepository->update($input, $id);
        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($deliveryAddress->toArray(), __('lang.updated_successfully', ['operator' => __('lang.delivery_address')]));

    }

    /**
     * Remove the specified Address from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $address = $this->deliveryAddressRepository->findWithoutFail($id);

        if (empty($address)) {
            return $this->sendError('Dirección no encontrada');
        }

        /** Verify if exist orders with status diferent to 'send' from this address and alert user about that */
        $orders = Order::where('delivery_address_id', $id)->pluck('order_status_id')->toArray();

        if (!empty(array_diff($orders, [5]))){
            $addresses = DeliveryAddress::with('user')->where('status', 1)->get()->toArray();
            // return $this->sendError('Esta dirección esta en uso');
        } else {
            $this->deliveryAddressRepository->delete($id);
        }

    
        return $this->sendResponse($addresses, __('lang.deleted_successfully',['operator' => __('lang.delivery_address')]));
    }

    /** Return a list of all delivery addresses without status filter */
    public function all(){
        try {

            $deliveryAddresses = $this->deliveryAddressRepository->all();

            foreach ($deliveryAddresses as $res){
                $res['lat'] = $res['latitude'];
                $res['lng'] = $res['longitude'];

                unset($res['latitude']);
                unset($res['longitude']);
            }

            return $this->sendResponse($deliveryAddresses->toArray(), 'Direcciones mostradas correctamente');

        } catch (\Exception $e){
            return $this->sendError('Delivery Address not found');
        }
    }
}