<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TestController extends Controller
{
    public function testHash(Request $request)
    {
        if($request->has('hashed')){
            $newHash = Hash::make($request->input('plain'));
            return view('test.hash', ['newHash' => $newHash, 'plain' => $request->input('plain'), 'hashed' => $request->input('hashed'), 'match' => Hash::check($request->input('plain'), $request->input('hashed')), 'mode' => 'hashed']);
        }else if($request->has('toHash')){
            $newHash = Hash::make($request->input('toHash'));
            return view('test.hash', ['newHash' => $newHash, 'toHash' => $request->input('toHash'), 'mode' => 'toHash']);
        }else{
            return view('test.hash', ['mode' => 'none']);
        }
    }
}
