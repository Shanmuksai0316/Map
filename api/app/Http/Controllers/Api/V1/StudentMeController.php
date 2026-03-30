<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StudentMeController extends Controller
{
    public function show(Request $r) {
        $u = $r->user();
        $s = $u->student()->with([
            'hostel:id,name','room:id,number','bed:id,code'
        ])->first();

        return response()->json([
            'id'=>$s->id,
            'name'=>$u->name,
            'email'=>$u->email,
            'phone'=>$u->phone,
            'map_student_id'=>$s->map_student_id,
            'hostel'=>$s->hostel?->name,
            'room'=>$s->room?->number,
            'bed'=>$s->bed?->code,
        ]);
    }
}
