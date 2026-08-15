<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Lead field strings — English (§6.1, §10.6)
|--------------------------------------------------------------------------
*/

return [
    'optional' => '(optional)',
    'placeholder' => 'Your email or mobile number',
    'dock_cta' => 'Contact us',
    'submit' => 'Contact me',
    'submitting' => 'Sending…',
    'add_message' => 'Add a message (optional)',
    'message_label' => 'Your message',
    'message_placeholder' => 'One line…',

    'contact_required' => 'Enter your email or mobile number so we can reach you.',
    'contact_invalid' => 'We could not read that. Enter an email such as name@company.sa, or a mobile number such as 05xxxxxxxx.',
    'phone_invalid' => 'That number is not valid for the chosen country. Check its length and prefix.',
    'email_invalid' => 'That email address is not valid. For example: name@company.sa',
    'message_too_long' => 'That message is too long. Keep it to a single line.',

    /*
     * The thank-you after a successful submit — the DEFAULT wording only.
     * The client's own text lives in Settings → Confirmation messages and
     * replaces these; see the Arabic file for why this one falls back and the
     * confirmation email does not.
     */
    'success_title' => "We've received your request",
    'success_body' => "We'll be in touch shortly.",
    'error_title' => 'It did not send',
    'error_body' => 'Your request did not reach us. Check your connection and try again — what you typed is still here.',
    'error' => 'We could not send that just now. Please try again in a moment.',
    'too_many' => 'Too many attempts from this device. Wait a minute and try again.',
];
