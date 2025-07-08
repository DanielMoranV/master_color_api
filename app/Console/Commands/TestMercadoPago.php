<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentService;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class TestMercadoPago extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mercadopago:test {--debug : Show debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MercadoPago connection and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing MercadoPago Configuration...');
        $this->newLine();

        // 1. Test configuration
        $this->testConfiguration();

        // 2. Test API connection
        $this->testApiConnection();

        // 3. Test preference creation
        $this->testPreferenceCreation();

        $this->newLine();
        $this->info('✅ MercadoPago test completed!');
    }

    private function testConfiguration()
    {
        $this->info('1️⃣ Testing Configuration...');

        $accessToken = config('mercadopago.access_token');
        $publicKey = config('mercadopago.public_key');
        $sandbox = config('mercadopago.sandbox');

        if (!$accessToken) {
            $this->error('❌ MERCADOPAGO_ACCESS_TOKEN not configured');
            return;
        }

        if (!$publicKey) {
            $this->error('❌ MERCADOPAGO_PUBLIC_KEY not configured');
            return;
        }

        $this->info("✅ Access Token: " . substr($accessToken, 0, 20) . "...");
        $this->info("✅ Public Key: " . substr($publicKey, 0, 20) . "...");
        $this->info("✅ Sandbox Mode: " . ($sandbox ? 'Yes' : 'No'));
        $this->info("✅ Country: " . config('mercadopago.country'));
        $this->info("✅ Currency: " . config('mercadopago.currency'));
    }

    private function testApiConnection()
    {
        $this->newLine();
        $this->info('2️⃣ Testing API Connection...');

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.mercadopago.com/users/me',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . config('mercadopago.access_token'),
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $this->error("❌ CURL Error: " . $curlError);
                return;
            }

            if ($httpCode === 200) {
                $userData = json_decode($response, true);
                $this->info("✅ API Connection successful");
                $this->info("✅ User ID: " . $userData['id']);
                $this->info("✅ Email: " . $userData['email']);
                $this->info("✅ Country: " . $userData['country_id']);
                
                if ($this->option('debug')) {
                    $this->newLine();
                    $this->info('🔍 Full API Response:');
                    $this->line(json_encode($userData, JSON_PRETTY_PRINT));
                }
            } else {
                $this->error("❌ API Error - HTTP $httpCode");
                $this->error("Response: " . $response);
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
        }
    }

    private function testPreferenceCreation()
    {
        $this->newLine();
        $this->info('3️⃣ Testing Preference Creation with CURL...');

        try {
            // Crear preferencia de prueba con datos similares a la orden real
            $preferenceData = [
                'items' => [
                    [
                        'id' => '2',
                        'title' => 'Canon c350',
                        'description' => 'Canon c350',
                        'quantity' => 1,
                        'unit_price' => 100.0,
                        'currency_id' => 'PEN'
                    ],
                    [
                        'id' => '5',
                        'title' => 'Konica Minolta bizbuh c3350i',
                        'description' => 'Konica Minolta bizbuh c3350i',
                        'quantity' => 1,
                        'unit_price' => 150.0,
                        'currency_id' => 'PEN'
                    ]
                ],
                'payer' => [
                    'name' => 'Pedro Picapiedra Rocadura',
                    'surname' => '',
                    'email' => 'pedrotuterror@gmail.com'
                ],
                'external_reference' => 'test-' . time(),
                'expires' => false,
                'back_urls' => [
                    'success' => 'http://localhost:5173/payment/success?order=test',
                    'failure' => 'http://localhost:5173/payment/failure?order=test',
                    'pending' => 'http://localhost:5173/payment/pending?order=test'
                ]
            ];

            // No agregar statement_descriptor en localhost para evitar el error
            if (!str_contains(env('APP_URL', 'localhost'), 'localhost')) {
                $preferenceData['statement_descriptor'] = 'MasterColor';
            }

            if ($this->option('debug')) {
                $this->newLine();
                $this->info('🔍 Preference Data:');
                $this->line(json_encode($preferenceData, JSON_PRETTY_PRINT));
                $this->newLine();
            }

            // Usar CURL directo
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . config('mercadopago.access_token'),
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($preferenceData)
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                throw new \Exception('CURL Error: ' . $curlError);
            }

            if ($httpCode !== 201) {
                throw new \Exception('MercadoPago API Error - HTTP ' . $httpCode . ': ' . $response);
            }

            $preference = json_decode($response, true);

            if (!$preference || !isset($preference['id'])) {
                throw new \Exception('Invalid response from MercadoPago API');
            }

            $this->info("✅ Preference created successfully with CURL");
            $this->info("✅ Preference ID: " . $preference['id']);
            $this->info("✅ Init Point: " . $preference['init_point']);
            $this->info("✅ Sandbox Init Point: " . ($preference['sandbox_init_point'] ?? 'N/A'));

            if ($this->option('debug')) {
                $this->newLine();
                $this->info('🔍 Full API Response:');
                $this->line(json_encode($preference, JSON_PRETTY_PRINT));
            }

        } catch (\Exception $e) {
            $this->error("❌ Preference Creation Failed: " . $e->getMessage());
            
            if ($this->option('debug')) {
                $this->newLine();
                $this->error('🔍 Exception Details:');
                $this->error('File: ' . $e->getFile());
                $this->error('Line: ' . $e->getLine());
            }
        }
    }
}
