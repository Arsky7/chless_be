<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    private string $apiKey;
    private string $apiKeyOngkir;
    private int    $originCityId;

    public function __construct()
    {
        $this->apiKey       = env('RAJAONGKIR_API_KEY', '');
        $this->apiKeyOngkir = env('RAJAONGKIR_API_KEY_ONGKIR', env('RAJAONGKIR_API_KEY', ''));
        $this->originCityId = (int) env('RAJAONGKIR_ORIGIN_CITY_ID', 501);
    }

    /**
     * Static list of all Indonesian provinces
     */
    private function getStaticProvinces(): array
    {
        return [
            ['province_id' => '1',  'province' => 'Bali'],
            ['province_id' => '2',  'province' => 'Bangka Belitung'],
            ['province_id' => '3',  'province' => 'Banten'],
            ['province_id' => '4',  'province' => 'Bengkulu'],
            ['province_id' => '5',  'province' => 'DI Yogyakarta'],
            ['province_id' => '6',  'province' => 'DKI Jakarta'],
            ['province_id' => '7',  'province' => 'Gorontalo'],
            ['province_id' => '8',  'province' => 'Jambi'],
            ['province_id' => '9',  'province' => 'Jawa Barat'],
            ['province_id' => '10', 'province' => 'Jawa Tengah'],
            ['province_id' => '11', 'province' => 'Jawa Timur'],
            ['province_id' => '12', 'province' => 'Kalimantan Barat'],
            ['province_id' => '13', 'province' => 'Kalimantan Selatan'],
            ['province_id' => '14', 'province' => 'Kalimantan Tengah'],
            ['province_id' => '15', 'province' => 'Kalimantan Timur'],
            ['province_id' => '16', 'province' => 'Kalimantan Utara'],
            ['province_id' => '17', 'province' => 'Kepulauan Riau'],
            ['province_id' => '18', 'province' => 'Lampung'],
            ['province_id' => '19', 'province' => 'Maluku'],
            ['province_id' => '20', 'province' => 'Maluku Utara'],
            ['province_id' => '21', 'province' => 'Nusa Tenggara Barat'],
            ['province_id' => '22', 'province' => 'Nusa Tenggara Timur'],
            ['province_id' => '23', 'province' => 'Papua'],
            ['province_id' => '24', 'province' => 'Papua Barat'],
            ['province_id' => '25', 'province' => 'Riau'],
            ['province_id' => '26', 'province' => 'Sulawesi Barat'],
            ['province_id' => '27', 'province' => 'Sulawesi Selatan'],
            ['province_id' => '28', 'province' => 'Sulawesi Tengah'],
            ['province_id' => '29', 'province' => 'Sulawesi Tenggara'],
            ['province_id' => '30', 'province' => 'Sulawesi Utara'],
            ['province_id' => '31', 'province' => 'Sumatera Barat'],
            ['province_id' => '32', 'province' => 'Sumatera Selatan'],
            ['province_id' => '33', 'province' => 'Sumatera Utara'],
            ['province_id' => '34', 'province' => 'Aceh'],
        ];
    }

    /**
     * Get all provinces — returns static data (no external API needed)
     */
    public function provinces()
    {
        $provinces = $this->getStaticProvinces();
        // Sort alphabetically
        usort($provinces, fn($a, $b) => strcmp($a['province'], $b['province']));
        return response()->json(['success' => true, 'count' => count($provinces), 'data' => $provinces]);
    }

    /**
     * Get cities filtered by province_id — uses static data only (no external API)
     */
    public function cities(Request $request)
    {
        $provinceId = (string) $request->query('province_id', '');
        $data = $this->getStaticCities($provinceId);
        if (!empty($data)) {
            usort($data, fn($a, $b) => strcmp($a['city_name'], $b['city_name']));
        }
        return response()->json(['success' => true, 'count' => count($data), 'data' => $data]);
    }

    /**
     * Static city data for major provinces as fallback
     */
    private function getStaticCities(string $provinceId): array
    {
        $cities = [
            '6' => [ // DKI Jakarta
                ['city_id' => '151', 'province_id' => '6', 'type' => 'Kota', 'city_name' => 'Jakarta Barat', 'postal_code' => '11220'],
                ['city_id' => '152', 'province_id' => '6', 'type' => 'Kota', 'city_name' => 'Jakarta Pusat', 'postal_code' => '10540'],
                ['city_id' => '153', 'province_id' => '6', 'type' => 'Kota', 'city_name' => 'Jakarta Selatan', 'postal_code' => '12230'],
                ['city_id' => '154', 'province_id' => '6', 'type' => 'Kota', 'city_name' => 'Jakarta Timur', 'postal_code' => '13330'],
                ['city_id' => '155', 'province_id' => '6', 'type' => 'Kota', 'city_name' => 'Jakarta Utara', 'postal_code' => '14310'],
                ['city_id' => '455', 'province_id' => '6', 'type' => 'Kabupaten', 'city_name' => 'Kepulauan Seribu', 'postal_code' => '14550'],
            ],
            '9' => [ // Jawa Barat
                ['city_id' => '22', 'province_id' => '9', 'type' => 'Kabupaten', 'city_name' => 'Bandung', 'postal_code' => '40311'],
                ['city_id' => '23', 'province_id' => '9', 'type' => 'Kota', 'city_name' => 'Bandung', 'postal_code' => '40111'],
                ['city_id' => '85', 'province_id' => '9', 'type' => 'Kota', 'city_name' => 'Bekasi', 'postal_code' => '17121'],
                ['city_id' => '86', 'province_id' => '9', 'type' => 'Kabupaten', 'city_name' => 'Bekasi', 'postal_code' => '17510'],
                ['city_id' => '115', 'province_id' => '9', 'type' => 'Kabupaten', 'city_name' => 'Bogor', 'postal_code' => '16911'],
                ['city_id' => '116', 'province_id' => '9', 'type' => 'Kota', 'city_name' => 'Bogor', 'postal_code' => '16119'],
                ['city_id' => '148', 'province_id' => '9', 'type' => 'Kabupaten', 'city_name' => 'Depok', 'postal_code' => '16412'],
                ['city_id' => '149', 'province_id' => '9', 'type' => 'Kota', 'city_name' => 'Depok', 'postal_code' => '16411'],
                ['city_id' => '394', 'province_id' => '9', 'type' => 'Kota', 'city_name' => 'Sukabumi', 'postal_code' => '43111'],
                ['city_id' => '501', 'province_id' => '9', 'type' => 'Kota', 'city_name' => 'Tangerang', 'postal_code' => '15111'],
            ],
            '3' => [ // Banten
                ['city_id' => '39', 'province_id' => '3', 'type' => 'Kota', 'city_name' => 'Cilegon', 'postal_code' => '42411'],
                ['city_id' => '254', 'province_id' => '3', 'type' => 'Kabupaten', 'city_name' => 'Lebak', 'postal_code' => '42311'],
                ['city_id' => '322', 'province_id' => '3', 'type' => 'Kabupaten', 'city_name' => 'Pandeglang', 'postal_code' => '42211'],
                ['city_id' => '398', 'province_id' => '3', 'type' => 'Kabupaten', 'city_name' => 'Serang', 'postal_code' => '42182'],
                ['city_id' => '399', 'province_id' => '3', 'type' => 'Kota', 'city_name' => 'Serang', 'postal_code' => '42111'],
                ['city_id' => '455', 'province_id' => '3', 'type' => 'Kabupaten', 'city_name' => 'Tangerang', 'postal_code' => '15720'],
                ['city_id' => '456', 'province_id' => '3', 'type' => 'Kota', 'city_name' => 'Tangerang', 'postal_code' => '15111'],
                ['city_id' => '457', 'province_id' => '3', 'type' => 'Kota', 'city_name' => 'Tangerang Selatan', 'postal_code' => '15111'],
            ],
            '10' => [ // Jawa Tengah
                ['city_id' => '113', 'province_id' => '10', 'type' => 'Kabupaten', 'city_name' => 'Banyumas', 'postal_code' => '53111'],
                ['city_id' => '239', 'province_id' => '10', 'type' => 'Kabupaten', 'city_name' => 'Kudus', 'postal_code' => '59311'],
                ['city_id' => '254', 'province_id' => '10', 'type' => 'Kota', 'city_name' => 'Magelang', 'postal_code' => '56111'],
                ['city_id' => '338', 'province_id' => '10', 'type' => 'Kota', 'city_name' => 'Pekalongan', 'postal_code' => '51111'],
                ['city_id' => '355', 'province_id' => '10', 'type' => 'Kabupaten', 'city_name' => 'Purwokerto', 'postal_code' => '53111'],
                ['city_id' => '378', 'province_id' => '10', 'type' => 'Kota', 'city_name' => 'Salatiga', 'postal_code' => '50711'],
                ['city_id' => '379', 'province_id' => '10', 'type' => 'Kota', 'city_name' => 'Semarang', 'postal_code' => '50111'],
                ['city_id' => '413', 'province_id' => '10', 'type' => 'Kota', 'city_name' => 'Solo', 'postal_code' => '57111'],
                ['city_id' => '460', 'province_id' => '10', 'type' => 'Kota', 'city_name' => 'Tegal', 'postal_code' => '52111'],
            ],
            '11' => [ // Jawa Timur
                ['city_id' => '19', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Batu', 'postal_code' => '65311'],
                ['city_id' => '52', 'province_id' => '11', 'type' => 'Kabupaten', 'city_name' => 'Blitar', 'postal_code' => '66171'],
                ['city_id' => '53', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Blitar', 'postal_code' => '66111'],
                ['city_id' => '128', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Kediri', 'postal_code' => '64111'],
                ['city_id' => '130', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Malang', 'postal_code' => '65111'],
                ['city_id' => '296', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Mojokerto', 'postal_code' => '61311'],
                ['city_id' => '352', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Probolinggo', 'postal_code' => '67211'],
                ['city_id' => '370', 'province_id' => '11', 'type' => 'Kota', 'city_name' => 'Surabaya', 'postal_code' => '60111'],
            ],
            '5' => [ // DI Yogyakarta
                ['city_id' => '39',  'province_id' => '5', 'type' => 'Kabupaten', 'city_name' => 'Bantul', 'postal_code' => '55711'],
                ['city_id' => '157', 'province_id' => '5', 'type' => 'Kabupaten', 'city_name' => 'Gunung Kidul', 'postal_code' => '55811'],
                ['city_id' => '233', 'province_id' => '5', 'type' => 'Kabupaten', 'city_name' => 'Kulon Progo', 'postal_code' => '55611'],
                ['city_id' => '403', 'province_id' => '5', 'type' => 'Kabupaten', 'city_name' => 'Sleman', 'postal_code' => '55511'],
                ['city_id' => '501', 'province_id' => '5', 'type' => 'Kota', 'city_name' => 'Yogyakarta', 'postal_code' => '55111'],
            ],
            '1' => [ // Bali
                ['city_id' => '17',  'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Badung', 'postal_code' => '80351'],
                ['city_id' => '50',  'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Bangli', 'postal_code' => '80611'],
                ['city_id' => '99',  'province_id' => '1', 'type' => 'Kota', 'city_name' => 'Denpasar', 'postal_code' => '80111'],
                ['city_id' => '142', 'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Gianyar', 'postal_code' => '80511'],
                ['city_id' => '190', 'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Jembrana', 'postal_code' => '82251'],
                ['city_id' => '230', 'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Karangasem', 'postal_code' => '80811'],
                ['city_id' => '232', 'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Klungkung', 'postal_code' => '80711'],
                ['city_id' => '368', 'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Tabanan', 'postal_code' => '82111'],
                ['city_id' => '489', 'province_id' => '1', 'type' => 'Kabupaten', 'city_name' => 'Buleleng', 'postal_code' => '81111'],
            ],
            '33' => [ // Sumatera Utara
                ['city_id' => '82',  'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Binjai', 'postal_code' => '20711'],
                ['city_id' => '128', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Gunungsitoli', 'postal_code' => '22812'],
                ['city_id' => '244', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Medan', 'postal_code' => '20111'],
                ['city_id' => '282', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Padangsidimpuan', 'postal_code' => '22711'],
                ['city_id' => '341', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Pematang Siantar', 'postal_code' => '21111'],
                ['city_id' => '405', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Sibolga', 'postal_code' => '22511'],
                ['city_id' => '467', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Tanjung Balai', 'postal_code' => '21311'],
                ['city_id' => '471', 'province_id' => '33', 'type' => 'Kota', 'city_name' => 'Tebing Tinggi', 'postal_code' => '20611'],
            ],
            '25' => [ // Riau
                ['city_id' => '42',  'province_id' => '25', 'type' => 'Kota', 'city_name' => 'Dumai', 'postal_code' => '28811'],
                ['city_id' => '339', 'province_id' => '25', 'type' => 'Kota', 'city_name' => 'Pekanbaru', 'postal_code' => '28111'],
            ],
            '27' => [ // Sulawesi Selatan
                ['city_id' => '255', 'province_id' => '27', 'type' => 'Kota', 'city_name' => 'Makassar', 'postal_code' => '90111'],
                ['city_id' => '331', 'province_id' => '27', 'type' => 'Kota', 'city_name' => 'Palopo', 'postal_code' => '91911'],
                ['city_id' => '337', 'province_id' => '27', 'type' => 'Kota', 'city_name' => 'Pare-pare', 'postal_code' => '91111'],
            ],
        ];

        return $cities[$provinceId] ?? [];
    }

    /**
     * Calculate shipping cost via Komerce API
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'destination' => 'required',
            'weight'      => 'required|integer|min:1',
            'courier'     => 'required|string',
        ]);

        try {
            $response = Http::timeout(15)->withHeaders([
                'key'          => $this->apiKeyOngkir,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->post('https://rajaongkir.komerce.id/api/v1/cost/calculate', [
                'origin'      => $this->originCityId,
                'destination' => $request->destination,
                'weight'      => $request->weight,
                'courier'     => strtolower($request->courier),
            ]);

            Log::info('Calculate cost status: ' . $response->status());

            if ($response->successful()) {
                $data    = $response->json();
                $results = $data['data'] ?? $data['results'] ?? [];
                return response()->json(['success' => true, 'data' => $results]);
            }

            Log::error('Calculate cost failed: ' . $response->status() . ' ' . substr($response->body(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan ongkos kirim.',
                'detail'  => $response->json(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Calculate cost exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Debug endpoint (local only)
     */
    public function debug()
    {
        if (!app()->isLocal()) {
            return response()->json(['error' => 'Only available in local environment'], 403);
        }

        Cache::flush();

        return response()->json([
            'status'          => 'ok',
            'origin_city_id'  => $this->originCityId,
            'api_key_prefix'  => substr($this->apiKey, 0, 8) . '...',
            'provinces_count' => count($this->getStaticProvinces()),
            'message'         => 'Cache cleared. Provinces are now served from static data.',
        ]);
    }
}
