<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncSallaProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;

    /**
     * Creates a new job instance targeting the specific tenant context.
     *
     * @param  \App\Models\Tenant  $tenant
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Executes the job to fetch and synchronize products.
     *
     * @return void
     */
    public function handle(): void
    {
        // Bind the tenant ID to the container to enforce automated database scoping
        App::bind('current_tenant_id', fn () => $this->tenant->id);

        $config = $this->tenant->sallaConfig;
        if (empty($config)) {
            Log::error('Salla configuration missing for tenant product synchronization', [
                'tenant_id' => $this->tenant->id,
            ]);
            return;
        }

        $url = 'https://api.salla.dev/admin/v2/products';
        $fetchedProductIds = [];
        $syncSuccess = true;

        try {
            while ($url) {
                $response = Http::withToken($config->access_token)->get($url);

                if ($response->failed()) {
                    Log::error('Failed to fetch products page from Salla API', [
                        'tenant_id' => $this->tenant->id,
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                    $syncSuccess = false;
                    break;
                }

                $body = $response->json();
                $products = $body['data'] ?? [];

                foreach ($products as $productData) {
                    $sallaProductId = (string) $productData['id'];
                    $fetchedProductIds[] = $sallaProductId;

                    Product::updateOrCreate(
                        ['salla_product_id' => $sallaProductId],
                        [
                            'name' => $productData['name'] ?? '',
                            'image_url' => $productData['image'] ?? null,
                            'product_url' => $productData['urls']['customer'] ?? null,
                        ]
                    );
                }

                // Retrieve next page link to continue paginated synchronization
                $url = $body['pagination']['links']['next'] ?? null;

                // Throttling requests slightly to respect API rate boundaries
                if ($url) {
                    usleep(500000); // 0.5 seconds delay
                }
            }

            if ($syncSuccess) {
                // Delete local products that are no longer present on Salla store
                Product::whereNotIn('salla_product_id', $fetchedProductIds)->delete();
            }

            Log::info('Successfully completed product synchronization from Salla', [
                'tenant_id' => $this->tenant->id,
            ]);
        } catch (\Exception $exception) {
            Log::error('Exception triggered during Salla product synchronization', [
                'tenant_id' => $this->tenant->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
