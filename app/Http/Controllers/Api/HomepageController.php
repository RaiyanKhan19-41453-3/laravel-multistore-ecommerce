<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;

class HomepageController extends Controller
{
    public function __construct(
        protected HomepageService $homepage,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->homepage->sections(),
        ]);
    }

    public function blocks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->homepage->blocks(),
        ]);
    }

    public function hero(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'display' => $this->homepage->heroDisplay(),
                'slides' => $this->homepage->slides(),
            ],
        ]);
    }

    public function banners(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->homepage->banners(),
        ]);
    }
}
