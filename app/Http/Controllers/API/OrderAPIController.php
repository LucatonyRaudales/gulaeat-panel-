<?php
/**
 * File name: OrderAPIController.php
 * Last modified: 2020.06.11 at 16:10:52
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 */

namespace App\Http\Controllers\API;


use App\Criteria\Orders\OrdersOfStatusesCriteria;
use App\Criteria\Orders\OrdersOfUserCriteria;
use App\Events\OrderChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAddress;
use App\Models\Restaurant;
use App\Models\FoodOrder;
use App\Models\Order;
use App\Notifications\AssignedOrder;
use App\Notifications\NewOrder;
use App\Notifications\StatusChangedOrder;
use App\Repositories\CartRepository;
use App\Repositories\FoodOrderRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use Flash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;
use Stripe\Token;

/**
 * Class OrderController
 * @package App\Http\Controllers\API
 */
class OrderAPIController extends Controller
{
    /** @var  OrderRepository */
    private $orderRepository;
    /** @var  FoodOrderRepository */
    private $foodOrderRepository;
    /** @var  CartRepository */
    private $cartRepository;
    /** @var  UserRepository */
    private $userRepository;
    /** @var  PaymentRepository */
    private $paymentRepository;
    /** @var  NotificationRepository */
    private $notificationRepository;

    /**
     * OrderAPIController constructor.
     * @param OrderRepository $orderRepo
     * @param FoodOrderRepository $foodOrderRepository
     * @param CartRepository $cartRepo
     * @param PaymentRepository $paymentRepo
     * @param NotificationRepository $notificationRepo
     * @param UserRepository $userRepository
     */
    public function __construct(OrderRepository $orderRepo, FoodOrderRepository $foodOrderRepository, CartRepository $cartRepo, PaymentRepository $paymentRepo, NotificationRepository $notificationRepo, UserRepository $userRepository)
    {
        $this->orderRepository = $orderRepo;
        $this->foodOrderRepository = $foodOrderRepository;
        $this->cartRepository = $cartRepo;
        $this->userRepository = $userRepository;
        $this->paymentRepository = $paymentRepo;
        $this->notificationRepository = $notificationRepo;
    }

    /**
     * Display a listing of the Order.
     * GET|HEAD /orders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $this->orderRepository->pushCriteria(new RequestCriteria($request));
            $this->orderRepository->pushCriteria(new LimitOffsetCriteria($request));
            $this->orderRepository->pushCriteria(new OrdersOfStatusesCriteria($request));
            $this->orderRepository->pushCriteria(new OrdersOfUserCriteria(auth()->id()));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }

        $orders = $this->orderRepository->all();
        /*$orders = $this->orderRepository->with([
            'foodOrders' => function($q) {
                $q
                    ->leftjoin('foods', 'food_orders.food_id', '=', 'foods.id')
                    ->leftjoin('restaurants', 'foods.restaurant_id', '=', 'restaurants.id')
                    ->select('food_orders.*','restaurants.id as restaurant_id', 'restaurants.latitude as lat', 'restaurants.longitude as lng', 'restaurants.active as active');
            }
        ])->get();*/

        return $this->sendResponse($orders->toArray(), 'Orders retrieved successfully');
    }

    /**
     * Display the specified Order.
     * GET|HEAD /orders/{id}
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        /** @var Order $order */
        if (!empty($this->orderRepository)) {
            try {
                $this->orderRepository->pushCriteria(new RequestCriteria($request));
                $this->orderRepository->pushCriteria(new LimitOffsetCriteria($request));
            } catch (RepositoryException $e) {
                return $this->sendError($e->getMessage());
            }
            $order = $this->orderRepository->findWithoutFail($id);
        }

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $order = $order->toArray();

        $rest = FoodOrder::where('order_id', $order['id'])->join('foods', 'food_orders.food_id', '=' ,'foods.id')
            ->join('restaurants', 'foods.restaurant_id', '=', 'restaurants.id')
            ->select('restaurants.id', 'restaurants.latitude as lat', 'restaurants.longitude as lng')
            ->groupBy('restaurants.id')
            ->get()->each(function($row){
                $row->setHidden(['custom_fields']);
            });

        $order["restaurants"] = $rest->toArray();

        return $this->sendResponse($order, 'Order retrieved successfully');
    }
    
    
    public function joinDriver( $id )
    {
        /** @var Order $order */
        $order = $this->orderRepository->findWithoutFail($id);
        
        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $order = $order->toArray();

        $rest = order::where('orders.id', $order['id'])
            ->join('drivers', 'orders.driver_id', '=', 'drivers.user_id')
            ->get()->each(function($row){
                $row->setHidden(['custom_fields']);
            });

        $order["driver"] = $rest->toArray();

        return $this->sendResponse($order, 'Order retrieved successfully');
    }

    /**
     * Store a newly created Order in storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $payment = $request->only('payment');
        if (isset($payment['payment']) && $payment['payment']['method']) {
            if ($payment['payment']['method'] == "Credit Card (Stripe Gateway)") {
                return $this->stripPayment($request);
            } else {
                return $this->cashPayment($request);

            }
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    private function stripPayment(Request $request)
    {
        $input = $request->all();
        $amount = 0;
        try {
            $user = $this->userRepository->findWithoutFail($input['user_id']);
            if (empty($user)) {
                return $this->sendError('User not found');
            }
            $stripeToken = Token::create(array(
                "card" => array(
                    "number" => $input['stripe_number'],
                    "exp_month" => $input['stripe_exp_month'],
                    "exp_year" => $input['stripe_exp_year'],
                    "cvc" => $input['stripe_cvc'],
                    "name" => $user->name,
                )
            ));
            if ($stripeToken->created > 0) {
                if (empty($input['delivery_address_id'])) {
                    $order = $this->orderRepository->create(
                        $request->only('user_id', 'order_status_id', 'tax', 'hint')
                    );
                } else {
                    $order = $this->orderRepository->create(
                        $request->only('user_id', 'order_status_id', 'tax', 'delivery_address_id', 'delivery_fee', 'hint')
                    );
                }
                foreach ($input['foods'] as $foodOrder) {
                    $foodOrder['order_id'] = $order->id;
                    $amount += $foodOrder['price'] * $foodOrder['quantity'];
                    $this->foodOrderRepository->create($foodOrder);
                }
                $amount += $order->delivery_fee;
                $amountWithTax = $amount + ($amount * $order->tax / 100);
                $charge = $user->charge((int)($amountWithTax * 100), ['source' => $stripeToken]);
                $payment = $this->paymentRepository->create([
                    "user_id" => $input['user_id'],
                    "description" => trans("lang.payment_order_done"),
                    "price" => $amountWithTax,
                    "status" => $charge->status, // $charge->status
                    "method" => $input['payment']['method'],
                ]);
                $this->orderRepository->update(['payment_id' => $payment->id], $order->id);
                
                $order->restaurant_id = $order->getRestaurantId($order->id);
                $this->updateDistance($order);

                $this->cartRepository->deleteWhere(['user_id' => $order->user_id]);

                Notification::send($order->foodOrders[0]->food->restaurant->users, new NewOrder($order));
            }
        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    private function cashPayment(Request $request)
    {
        $input = $request->all();
        $amount = 0;
        try {
            $order = $this->orderRepository->create(
                $request->only('user_id', 'order_status_id', 'tax', 'delivery_address_id', 'delivery_fee', 'hint')
            );
            foreach ($input['foods'] as $foodOrder) {
                $foodOrder['order_id'] = $order->id;
                $amount += $foodOrder['price'] * $foodOrder['quantity'];
                $this->foodOrderRepository->create($foodOrder);
            }
            $amount += $order->delivery_fee;
            $amountWithTax = $amount + ($amount * $order->tax / 100);
            $payment = $this->paymentRepository->create([
                "user_id" => $input['user_id'],
                "description" => trans("lang.payment_order_waiting"),
                "price" => $amountWithTax,
                "status" => 'Waiting for Client',
                "method" => $input['payment']['method'],
            ]);

            $this->orderRepository->update(['payment_id' => $payment->id], $order->id);
            
            $order->restaurant_id = $order->getRestaurantId($order->id);
                $this->updateDistance($order);

            $this->cartRepository->deleteWhere(['user_id' => $order->user_id]);

            Notification::send($order->foodOrders[0]->food->restaurant->users, new NewOrder($order));

        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }

    /**
     * Update the specified Order in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $oldOrder = $this->orderRepository->findWithoutFail($id);
        if (empty($oldOrder)) {
            return $this->sendError('Order not found');
        }

        $oldStatus = isset($oldOrder->payment->status) ? $oldOrder->payment->status : 'Status Not Found';
        $input = $request->all();

        try {
            $order = $this->orderRepository->update($input, $id);
            if (isset($input['order_status_id']) && $input['order_status_id'] == 5 && !empty($order)) {
                $this->paymentRepository->update(['status' => 'Paid'], $order['payment_id']);
            }
            event(new OrderChangedEvent($oldStatus, $order));

            if (setting('enable_notifications', false)) {
                if (isset($input['order_status_id']) && $input['order_status_id'] != $oldOrder->order_status_id) {
                    Notification::send([$order->user], new StatusChangedOrder($order));
                }

                if (isset($input['driver_id']) && ($input['driver_id'] != $oldOrder['driver_id'])) {
                    $driver = $this->userRepository->findWithoutFail($input['driver_id']);
                    if (!empty($driver)) {
                        Notification::send([$driver], new AssignedOrder($order));
                    }
                }
            }

        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }
    
    
    /**
     * Update all orders while arrive is not default and have data location
     */
    public function updateAllArrives($order_id = null)
    {
        try {
            $res=[];
            if (isset($order_id)){
                $data = Order::findOrFail($order_id);
                $data['restaurant_id'] = $data->getRestaurantId($order_id);

                $this->updateDistance($data);

            } else {
                $data = Order::whereRaw("delivery_address_id is not null AND driver_id IS NOT NULL AND arrive_time <=20 OR arrive_time is null")
                    ->select('orders.id','driver_id', 'delivery_address_id', 'arrive_time', 'foods.restaurant_id')
                    ->join('food_orders', 'orders.id', '=', 'food_orders.order_id')
                    ->join('foods', 'food_orders.food_id', '=', 'foods.id')
                    ->get();

                foreach ($data as $key => $order){
                    $res[] = $this->updateDistance($order);

                    if ($order->arrive_time == 20)
                        unset($data[$key]);
                }
            }

            return ['data' => $data, 'rejected' => array_diff($res, array("",0,null))];

        } catch (\Exception $e){
            return $this->sendError($e->getMessage());
        }
    }

    public function updateDistance($order)
    {
        $restaurant = Restaurant::where('id', $order->restaurant_id)->select('latitude as lat', 'longitude as lng')->first();
        $client = DeliveryAddress::where('id', $order->delivery_address_id)->select('latitude as lat', 'longitude as lng')->first();

        if(isset($restaurant) && isset($client))
        {
            $calc = calculateDistance($restaurant->lat, $restaurant->lng, $client->lat, $client->lng);
            $order->arrive_time = $calc;
            unset($order->restaurant_id);
            $order->save();
        }

        if ($order->arrive_time == 20)
            return $order;
    }

}
