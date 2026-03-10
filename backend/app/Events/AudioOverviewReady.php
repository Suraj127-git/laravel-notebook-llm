<?php

namespace App\Events;

use App\Models\AudioOverview;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AudioOverviewReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AudioOverview $overview) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("notebooks.{$this->overview->notebook_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'audio.overview.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'notebook_id'      => $this->overview->notebook_id,
            'status'           => $this->overview->status,
            'duration_seconds' => $this->overview->duration_seconds,
        ];
    }
}
