<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user:id,name')->latest();

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => (object) $request->only(['action']),
        ]);
    }
}
