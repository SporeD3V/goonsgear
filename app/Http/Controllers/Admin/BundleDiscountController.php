<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BundleDiscountController extends Controller
{
    public function index(): View
    {
        return view('admin.bundle-discounts.index');
    }
}
