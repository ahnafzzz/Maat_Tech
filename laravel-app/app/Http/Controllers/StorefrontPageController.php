<?php

namespace App\Http\Controllers;

use App\Models\StorefrontPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontPageController extends Controller
{
    public function show(Request $request): View
    {
        $slug = (string) $request->route('contentPage');
        $page = StorefrontPage::where('slug', $slug)->where('is_visible', true)->firstOrFail();

        return view('storefront-page', compact('page'));
    }
}
