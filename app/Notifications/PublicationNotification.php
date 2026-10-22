<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $titre,
        private readonly string $message,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titre' => $this->titre,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->titre)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message)
            ->action('Consulter mon espace', url($this->url))
            ->line('Université Internationale des Sciences et Technologie');
    }
}
