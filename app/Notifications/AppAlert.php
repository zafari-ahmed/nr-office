<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Generic in-app alert shown in the bell panel.
 */
class AppAlert extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tone = 'blue',
        public string $icon = 'bell',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tone' => $this->tone,
            'icon' => $this->icon,
        ];
    }
}
