<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function __construct(
        protected MenuService $menus,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->menus->tree(),
        ]);
    }
}
