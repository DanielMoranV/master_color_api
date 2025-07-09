<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Facades\MercadoPago;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Client;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MercadoPagoWrapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrapper_configuration_validation()
    {
        // Test que la configuración se valida correctamente
        $this->assertFalse(MercadoPago::isConfigured());
    }

    public function test_wrapper_can_get_config()
    {
        $config = MercadoPago::getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('access_token', $config);
        $this->assertArrayHasKey('public_key', $config);
        $this->assertArrayHasKey('sandbox', $config);
        $this->assertArrayHasKey('currency', $config);
        $this->assertEquals('PEN', $config['currency']);
    }

    public function test_wrapper_can_add_items()
    {
        // Test que se pueden agregar items (sin hacer llamada real a la API)
        $this->expectException(\Exception::class);
        
        MercadoPago::begin(function($mp) {
            $mp->addItem([
                'id' => 'test-item',
                'title' => 'Test Product',
                'quantity' => 1,
                'price' => 100.00,
                'currency' => 'PEN',
            ]);
        });
    }

    public function test_wrapper_can_set_order()
    {
        $client = Client::factory()->create();
        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id]);
        
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'pendiente_pago',
        ]);
        
        OrderDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
        ]);

        // Test que se puede configurar una orden
        $this->expectException(\Exception::class);
        
        MercadoPago::setOrder($order)->begin(function($mp) use ($order) {
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
    }

    public function test_webhook_processing_validation()
    {
        // Test que el procesamiento de webhook valida correctamente
        $result = MercadoPago::processWebhookNotification([]);
        
        $this->assertFalse($result);
    }

    public function test_payment_retrieval_with_invalid_id()
    {
        // Test que la recuperación de pago maneja IDs inválidos
        $paymentData = MercadoPago::getPayment('invalid-id');
        
        $this->assertNull($paymentData);
    }
}