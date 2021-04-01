<?php

namespace App\Listeners;

use App\Events\DeliverLocationChanged;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;


class DeliverLocationNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(DeliverLocationChangedEvent $e)
    {
        broadcast(new DeliverLocationChangedEvent("{$e->lat}", "{$e->lng}"));
    }
}
