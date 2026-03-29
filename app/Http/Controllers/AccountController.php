<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('account.index');
    }
}
