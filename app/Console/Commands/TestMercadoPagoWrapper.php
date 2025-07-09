<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Facades\MercadoPago;
use App\Models\Order;
use App\Models\Product;

class TestMercadoPagoWrapper extends Command
{
    protected $signature = 'mercadopago:test-wrapper {--debug}';
    protected $description = 'Test the new MercadoPago wrapper functionality';

    public function handle()
    {
        $this->info('🧪 Testing MercadoPago Wrapper...');
        
        // 1. Test configuration
        $this->info('📋 Testing configuration...');
        if (!MercadoPago::isConfigured()) {
            $this->error('❌ MercadoPago is not properly configured');
            $this->info('Please check your .env file for:');
            $this->info('- MERCADOPAGO_ACCESS_TOKEN');
            $this->info('- MERCADOPAGO_PUBLIC_KEY');
            return 1;
        }
        $this->info('✅ Configuration is valid');
        
        if ($this->option('debug')) {
            $config = MercadoPago::getConfig();
            $this->info('Configuration details:');
            $this->info('- Sandbox mode: ' . ($config['sandbox'] ? 'enabled' : 'disabled'));
            $this->info('- Currency: ' . $config['currency']);
            $this->info('- Country: ' . $config['country']);
        }
        
        // 2. Test simple preference creation
        $this->info('🔗 Testing simple preference creation...');
        try {
            $result = MercadoPago::begin(function($mp) {
                $mp->addItem([
                    'id' => 'test-item-1',
                    'title' => 'Test Product',
                    'quantity' => 1,
                    'price' => 100.00,
                    'currency' => 'PEN',
                ]);
            });
            
            $this->info('✅ Simple preference created successfully');
            $this->info('- Preference ID: ' . $result['id']);
            $this->info('- Init Point: ' . $result['init_point']);
            
        } catch (\Exception $e) {
            $this->error('❌ Error creating simple preference: ' . $e->getMessage());
            return 1;
        }
        
        // 3. Test multiple items
        $this->info('📦 Testing multiple items...');
        try {
            $result = MercadoPago::begin(function($mp) {
                $mp->addItem([
                    'id' => 'test-item-1',
                    'title' => 'Test Product 1',
                    'quantity' => 2,
                    'price' => 50.00,
                    'currency' => 'PEN',
                ]);
                $mp->addItem([
                    'id' => 'test-item-2',
                    'title' => 'Test Product 2',
                    'quantity' => 1,
                    'price' => 75.00,
                    'currency' => 'PEN',
                ]);
            });
            
            $this->info('✅ Multiple items preference created successfully');
            $this->info('- Total items: ' . count($result['items']));
            
        } catch (\Exception $e) {
            $this->error('❌ Error creating multiple items preference: ' . $e->getMessage());
            return 1;
        }
        
        // 4. Test with real order (if available)
        $this->info('🛒 Testing with real order...');
        $order = Order::with('orderDetails.product')->where('status', 'pendiente_pago')->first();
        
        if ($order) {
            try {
                $result = MercadoPago::setOrder($order)->begin(function($mp) use ($order) {
                    foreach ($order->orderDetails as $detail) {
                        $mp->addItem([
                            'id' => (string) $detail->product->id,
                            'title' => $detail->product->name,
                            'quantity' => $detail->quantity,
                            'price' => $detail->unit_price,
                            'currency' => 'PEN',
                        ]);
                    }
                });
                
                $this->info('✅ Real order preference created successfully');
                $this->info('- Order ID: ' . $order->id);
                $this->info('- Preference ID: ' . $result['id']);
                
            } catch (\Exception $e) {
                $this->error('❌ Error creating real order preference: ' . $e->getMessage());
            }
        } else {
            $this->warn('⚠️ No pending payment orders found for testing');
        }
        
        // 5. Test payment retrieval (if we have an external_id)
        $this->info('💳 Testing payment retrieval...');
        $payment = \App\Models\Payment::whereNotNull('external_id')->first();
        
        if ($payment && $payment->external_id) {
            try {
                $paymentData = MercadoPago::getPayment($payment->external_id);
                
                if ($paymentData) {
                    $this->info('✅ Payment retrieved successfully');
                    $this->info('- Payment ID: ' . $paymentData['id']);
                    $this->info('- Status: ' . $paymentData['status']);
                    $this->info('- Amount: ' . $paymentData['transaction_amount']);
                } else {
                    $this->warn('⚠️ Payment data not found');
                }
                
            } catch (\Exception $e) {
                $this->error('❌ Error retrieving payment: ' . $e->getMessage());
            }
        } else {
            $this->warn('⚠️ No payments with external_id found for testing');
        }
        
        $this->info('🎉 MercadoPago Wrapper test completed!');
        $this->info('The wrapper is ready to use. You can now use it with:');
        $this->info('MercadoPago::begin(function($mp) { ... });');
        
        return 0;
    }
}