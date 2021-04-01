<?php

namespace App\Events;

use App\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangedLocation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $lat;
    public $lng;
    public $rotation;
    public $accuracy;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($order, $lat, $lng, $rotation, $accuracy)
    {
        $this->order = $order;
        $this->lat = $lat;
        $this->lng =$lng;
        $this->rotation = $rotation;
        $this->accuracy = $accuracy;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel("changed.location.{$this->order}");
    }
}
