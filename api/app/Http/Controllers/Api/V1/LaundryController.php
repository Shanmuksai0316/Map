<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaundryController extends Controller
{
    public function index(Request $r) {
        $q = DB::table('laundry_cycles')->where('tenant_id', auth()->user()->tenant_id);
        if ($r->filled('status')) $q->where('status', $r->query('status'));
        return response()->json($q->orderByDesc('id')->limit(100)->get());
    }

    public function show($id) {
        $row = DB::table('laundry_cycles')->where('tenant_id', auth()->user()->tenant_id)->find($id);
        abort_if(!$row, 404);
        return response()->json($row);
    }

    public function status(Request $r, $id) {
        $data = $r->validate(['status'=>'required|in:in_progress,completed']);
        DB::table('laundry_cycles')
            ->where('tenant_id', auth()->user()->tenant_id)->where('id',$id)
            ->update(['status'=>$data['status'], 'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }
}