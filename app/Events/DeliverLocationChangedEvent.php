<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class DeliverLocationChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lat;
    public $lng;
    public $order;
    // public $deliverId;
    // public $orderId;
    // public $customerId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($lat, $lng, Order $order)
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        \Log::debug($this->lat);
        \Log::debug($this->lng);
        return new PrivateChannel('location.{$this->order->user_id}');
    }
}
