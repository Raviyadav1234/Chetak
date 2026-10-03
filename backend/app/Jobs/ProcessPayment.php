<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPayment implements ShouldQueue
{
    use Queueable;

    public $orderId;

    /**
     * Create a new job instance.
     */
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = \App\Models\Order::with('product')->find($this->orderId);

        if (!$order || $order->status !== 'pending') {
            return;
        }

        // Mock payment processing time
        sleep(2);

        // 20% failure rate
        $isSuccess = rand(1, 100) > 20;

        if ($isSuccess) {
            $order->update(['status' => 'confirmed']);
        } else {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                $order->update(['status' => 'failed']);
                
                // Restore stock
                $product = \App\Models\Product::where('id', $order->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->stock += $order->quantity;
                    $product->save();
                }
            });
        }
    }
}
