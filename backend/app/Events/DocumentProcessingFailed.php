<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentProcessingFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Document $document) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("notebooks.{$this->document->notebook_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document.processing.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'               => $this->document->id,
            'title'            => $this->document->title,
            'extraction_error' => $this->document->extraction_error ?? null,
        ];
    }
}
