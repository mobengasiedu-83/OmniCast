<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpSmsSender
{
    public function __construct(private readonly PlatformConfiguration $configuration) {}

    public function send(string $phone, string $code, ?int $signupId = null): bool
    {
        $driver = $this->configuration->resolve('providers', 'sms.driver', config('services.sms.driver', 'txtconnect'));
        $endpoint = $this->configuration->resolve(
            'providers',
            'sms.endpoint',
            $driver === 'txtconnect' ? config('services.sms.endpoint') : null,
        );
        $username = $this->configuration->resolve('providers', 'sms.username');
        $password = $this->configuration->resolve('providers', 'sms.password');
        $token = $this->configuration->resolve('providers', 'sms.token', config('services.sms.token'));
        $sender = $this->configuration->resolve('providers', 'sms.sender_id', config('services.sms.sender_id', 'OmniCast'));

        if (! is_string($endpoint) || $endpoint === '') {
            Log::channel('sms')->error('Client signup SMS was not sent because the provider endpoint is not configured.', [
                'signup_id' => $signupId,
                'driver' => $driver,
            ]);

            return false;
        }

        $message = 'Your GWO SEVO OmniCast verification code is '.$code.'. It expires in 10 minutes.';
        $request = Http::connectTimeout(3)->timeout(8);

        try {
            if ($driver === 'txtconnect') {
                if (! is_string($token) || $token === '') {
                    Log::channel('sms')->error('Client signup SMS was not sent because the TxtConnect token is not configured.', [
                        'signup_id' => $signupId,
                        'driver' => $driver,
                    ]);

                    return false;
                }

                $response = $request->withToken($token)->acceptJson()->post($endpoint, [
                    'to' => ltrim($phone, '+'),
                    'from' => $sender,
                    'unicode' => '1',
                    'sms' => $message,
                ]);
            } elseif ($driver === 'generic_json') {
                if (is_string($username) && $username !== '' && is_string($password) && $password !== '') {
                    $request = $request->withBasicAuth($username, $password);
                }
                $response = $request->acceptJson()->post($endpoint, [
                    'to' => $phone,
                    'from' => $sender,
                    'message' => $message,
                ]);
            } else {
                $response = $request->asForm()->post($endpoint, [
                    'username' => $username,
                    'password' => $password,
                    'to' => $phone,
                    'from' => $sender,
                    'text' => $message,
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::channel('sms')->error('Client signup SMS provider connection failed.', [
                'signup_id' => $signupId,
                'driver' => $driver,
                'exception' => $exception::class,
            ]);

            return false;
        }

        $accepted = $response->successful() && match ($driver) {
            'txtconnect' => $response->json('data.status_code') === '000'
                && $response->json('data.in_error') === false,
            'generic_json' => ($response->json('success') ?? true) === true,
            default => preg_match('/^0\s*:/', trim($response->body())) === 1,
        };

        if (! $accepted) {
            Log::channel('sms')->warning('Client signup SMS provider rejected the OTP message.', [
                'signup_id' => $signupId,
                'driver' => $driver,
                'status' => $response->status(),
                'provider_code' => $driver === 'txtconnect' ? $response->json('data.status_code') : null,
            ]);
        } else {
            Log::channel('sms')->info('Client signup SMS was accepted by the provider.', [
                'signup_id' => $signupId,
                'driver' => $driver,
                'status' => $response->status(),
                'provider_code' => $driver === 'txtconnect' ? $response->json('data.status_code') : null,
            ]);
        }

        return $accepted;
    }
}
