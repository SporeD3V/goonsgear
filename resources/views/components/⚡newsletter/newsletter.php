<?php

use App\Models\IntegrationSetting;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public bool $subscribed = false;

    public string $errorMessage = '';

    public function subscribe(): void
    {
        $this->errorMessage = '';

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $existing = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing && $existing->unsubscribed_at === null) {
            $this->subscribed = true;

            return;
        }

        if ($existing) {
            $existing->update([
                'name' => $validated['name'],
                'unsubscribed_at' => null,
                'subscribed_at' => now(),
            ]);
            $subscriber = $existing;
        } else {
            $subscriber = NewsletterSubscriber::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subscribed_at' => now(),
            ]);
        }

        $this->syncToBrevo($subscriber);

        $this->subscribed = true;
        $this->name = '';
        $this->email = '';
    }

    public function render(): View
    {
        return view('components.⚡newsletter.newsletter');
    }

    private function syncToBrevo(NewsletterSubscriber $subscriber): void
    {
        /** @var string $apiKey */
        $apiKey = IntegrationSetting::value('brevo_api_key');

        if ($apiKey === '' || $apiKey === null) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.brevo.com/v3/contacts', [
                'email' => $subscriber->email,
                'attributes' => ['FIRSTNAME' => $subscriber->name],
                'updateEnabled' => true,
            ]);

            if ($response->successful()) {
                $contactId = $response->json('id');
                if ($contactId) {
                    $subscriber->update(['brevo_contact_id' => (string) $contactId]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Brevo newsletter sync failed', [
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
};
