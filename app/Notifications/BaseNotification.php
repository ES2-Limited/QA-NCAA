<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    /**
     * The setting event key (e.g. 'audit_assigned', 'ncr_raised').
     */
    abstract public function eventKey(): string;

    /**
     * Placeholder data for subject and body substitution.
     */
    abstract public function placeholderData(object $notifiable): array;

    /**
     * Default configuration for this event.
     */
    abstract public function defaultTemplate(): array;

    /**
     * Target URL for user action.
     */
    abstract public function targetUrl(object $notifiable): string;

    /**
     * Determine the notification's delivery channels based on admin settings.
     */
    public function via(object $notifiable): array
    {
        $config = setting("notifications.events.{$this->eventKey()}", $this->defaultTemplate());

        $channels = [];
        if (! empty($config['in_app'])) {
            $channels[] = 'database';
        }
        if (! empty($config['email'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Render a string replacing {placeholder} tags with actual data.
     */
    protected function interpolate(string $template, array $data): string
    {
        foreach ($data as $key => $val) {
            $template = str_replace('{'.$key.'}', (string) $val, $template);
        }

        return $template;
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $config = setting("notifications.events.{$this->eventKey()}", $this->defaultTemplate());
        $data = $this->placeholderData($notifiable);

        $subject = $this->interpolate($config['subject'] ?? 'NCAA QA Notification', $data);
        $body = $this->interpolate($config['body'] ?? '', $data);

        $mail = (new MailMessage)->subject($subject);

        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $mail->line(trim($line));
        }

        return $mail->action('Open in System', $this->targetUrl($notifiable));
    }

    /**
     * Build the array/database representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $config = setting("notifications.events.{$this->eventKey()}", $this->defaultTemplate());
        $data = $this->placeholderData($notifiable);

        $subject = $this->interpolate($config['subject'] ?? 'NCAA QA Notification', $data);
        $body = $this->interpolate($config['body'] ?? '', $data);

        return [
            'event' => $this->eventKey(),
            'title' => $subject,
            'message' => $body,
            'url' => $this->targetUrl($notifiable),
            'metadata' => $data,
        ];
    }
}
