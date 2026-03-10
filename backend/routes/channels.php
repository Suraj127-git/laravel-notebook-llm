<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel: subscribe to all document/audio events for a notebook the user owns
Broadcast::channel('notebooks.{notebookId}', function ($user, $notebookId) {
    return $user->notebooks()->where('id', $notebookId)->exists();
});
