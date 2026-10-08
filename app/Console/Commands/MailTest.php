<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Proves the configured mailer can actually deliver, which is the one part of
 * the mail setup that cannot be checked without the mailbox password. Run it
 * on the server right after filling MAIL_PASSWORD in .env:
 *
 *     php artisan ecenter:mail-test someone@arucad.edu.tr
 *
 * Prints the transport settings it used (never the password) and, on failure,
 * the SMTP server's own words -- which is what distinguishes an application
 * misconfiguration from a Microsoft 365 tenant policy.
 */
#[Signature('ecenter:mail-test {recipient : Address to send the test message to}')]
#[Description('Send a test email through the configured mailer and report exactly what the SMTP server said')]
class MailTest extends Command
{
    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        $mailer = config('mail.default');

        $this->line('Mailer      : '.$mailer);
        if ($mailer === 'smtp') {
            $this->line('Host        : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
            $this->line('Scheme      : '.(config('mail.mailers.smtp.scheme') ?: '(auto — STARTTLS on 587)'));
            $this->line('Username    : '.(config('mail.mailers.smtp.username') ?: '(none)'));
            $this->line('Password    : '.(config('mail.mailers.smtp.password') ? '(set)' : '(NOT SET)'));
        }
        $this->line('From        : '.config('mail.from.address').' ("'.config('mail.from.name').'")');
        $this->line('Recipient   : '.$recipient);
        $this->newLine();

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — nothing will be delivered; the message only goes to storage/logs.');
        }

        try {
            Mail::raw(
                "eCenter test message.\n\nSent ".now()->format('d.m.Y H:i T').
                " to confirm outgoing mail works.\nIf you received this, notification email is operational.",
                fn ($message) => $message->to($recipient)->subject('eCenter — mail delivery test')
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('DELIVERY FAILED');
            $this->line(get_class($e));
            $this->line($e->getMessage());
            $this->newLine();
            $this->line('If the message above mentions SmtpClientAuthentication or 535 5.7.139, SMTP AUTH');
            $this->line('is disabled for this mailbox in Microsoft 365. A tenant administrator must run:');
            $this->line('  Set-CASMailbox -Identity '.(config('mail.mailers.smtp.username') ?: '<mailbox>').' -SmtpClientAuthenticationDisabled $false');
            $this->line('and ensure SMTP AUTH is not disabled tenant-wide in the Exchange admin center.');

            return self::FAILURE;
        }

        $this->info('Accepted by the mail transport. Check the recipient inbox (and junk folder).');

        return self::SUCCESS;
    }
}
