<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        $workspace = $this->invitation->workspace;

        return new Envelope(
            subject: 'You are invited to join '.$workspace->name.' on DZ Signage',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody(),
        );
    }

    private function htmlBody(): string
    {
        $workspace = $this->invitation->workspace->name;
        $role = $this->invitation->role->label();
        $url = url('/invitations/'.$this->plainToken);
        $expires = $this->invitation->expires_at->toDayDateTimeString();

        return <<<HTML
            <p>You have been invited to join <strong>{$workspace}</strong> on DZ Signage as <strong>{$role}</strong>.</p>
            <p><a href="{$url}">Accept invitation</a></p>
            <p>This invitation expires on {$expires}.</p>
            HTML;
    }
}
