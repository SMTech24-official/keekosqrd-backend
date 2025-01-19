<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $winner;
    public $product;
    public $vote;

    /**
     * Create a new message instance.
     */
    public function __construct($winner, $product, $vote)
    {
        $this->winner = $winner;
        $this->product = $product;
        $this->vote = $vote;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! You have won the Sneakers!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.send_winner_mail',
            with: [
                'winnerName' => $this->winner->name,
                'sneakerDetails' => [
                    'model' => $this->product->model,
                    'size' => $this->product->size,
                ],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

