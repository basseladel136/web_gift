<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order #{$this->order->id} Update — {$this->statusLabel()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.status_updated',
        );
    }

    public function statusLabel(): string
    {
        return match($this->order->status) {
            'confirmed'  => 'Order Confirmed',
            'processing' => 'Being Processed',
            'shipped'    => 'On The Way',
            'delivered'  => 'Delivered',
            'cancelled'  => 'Cancelled',
            default      => 'Status Updated',
        };
    }
}