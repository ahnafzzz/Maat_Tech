<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Product;
use App\Models\StorefrontNavigationLink;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminStorefrontController extends Controller
{
    public function index(): View
    {
        return view('admin.storefront', [
            'settings' => StorefrontSetting::current(),
            'pages' => StorefrontPage::orderBy('id')->get(),
            'links' => StorefrontNavigationLink::orderBy('location')->orderBy('column')->orderBy('sort_order')->get(),
            'banners' => Banner::orderBy('sort_order')->orderBy('id')->get(),
            'products' => Product::published()->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'logo_alt' => ['required', 'string', 'max:180'],
            'default_meta_description' => ['nullable', 'string', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'phone_display' => ['nullable', 'string', 'max:50'],
            'whatsapp_number' => ['nullable', 'regex:/^\d{8,20}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'business_hours' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'home_meta_title' => ['required', 'string', 'max:120'],
            'home_meta_description' => ['nullable', 'string', 'max:500'],
            'hero_badge' => ['nullable', 'string', 'max:120'],
            'hero_heading' => ['nullable', 'string', 'max:180'],
            'hero_copy' => ['nullable', 'string', 'max:1000'],
            'hero_primary_label' => ['required', 'string', 'max:80'],
            'hero_secondary_label' => ['required', 'string', 'max:80'],
            'hero_note' => ['nullable', 'string', 'max:180'],
            'featured_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'slideshow_enabled' => ['required', 'boolean'],
            'shop_label' => ['required', 'string', 'max:80'],
            'account_label' => ['required', 'string', 'max:80'],
            'sign_in_label' => ['required', 'string', 'max:80'],
            'wishlist_enabled' => ['required', 'boolean'],
            'cart_enabled' => ['required', 'boolean'],
            'footer_copyright' => ['required', 'string', 'max:180'],
            'footer_status' => ['required', 'string', 'max:120'],
            'footer_support_label' => ['required', 'string', 'max:80'],
            'whatsapp_cta_label' => ['required', 'string', 'max:80'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'panel_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $settings = StorefrontSetting::current();
        $oldLogo = $settings->logo_path;
        $newLogo = null;
        try {
            if ($request->hasFile('logo')) {
                $newLogo = $request->file('logo')->store('storefront/branding', 'public');
                if (! is_string($newLogo) || $newLogo === '') {
                    throw ValidationException::withMessages(['logo' => 'The logo could not be stored.']);
                }
                $validated['logo_path'] = $newLogo;
            } elseif ($request->boolean('remove_logo')) {
                $validated['logo_path'] = null;
            }
            unset($validated['logo'], $validated['remove_logo']);
            $settings->fill($validated)->save();
        } catch (Throwable $exception) {
            if ($newLogo) {
                Storage::disk('public')->delete($newLogo);
            }
            throw $exception;
        }

        if ($oldLogo && $oldLogo !== $settings->logo_path && str_starts_with($oldLogo, 'storefront/')) {
            Storage::disk('public')->delete($oldLogo);
        }
        $this->audit('storefront.settings_updated', $settings->id);

        return back()->with('status', 'Storefront identity, homepage, contact, and theme settings updated.');
    }

    public function updatePage(Request $request, StorefrontPage $page): RedirectResponse
    {
        $validated = $request->validate([
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:180'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'body_text' => ['nullable', 'string', 'max:30000'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.text' => ['nullable', 'string', 'max:3000'],
            'is_visible' => ['required', 'boolean'],
        ]);

        $items = collect($validated['items'] ?? [])->map(fn (array $item): array => [
            'title' => trim((string) ($item['title'] ?? '')),
            'text' => trim((string) ($item['text'] ?? '')),
        ])->filter(fn (array $item): bool => $item['title'] !== '' || $item['text'] !== '')->values();
        if ($items->contains(fn (array $item): bool => $item['title'] === '' || $item['text'] === '')) {
            throw ValidationException::withMessages(['items' => 'Every content card needs both a heading and text.']);
        }

        $page->update([
            'eyebrow' => $validated['eyebrow'] ?? null,
            'title' => $validated['title'],
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'body' => $this->paragraphs((string) ($validated['body_text'] ?? '')),
            'items' => $items->all(),
            'is_visible' => (bool) $validated['is_visible'],
        ]);
        $this->audit('storefront.page_updated', $page->id, ['slug' => $page->slug]);

        return back()->with('status', $page->title.' updated.');
    }

    public function storeLink(Request $request): RedirectResponse
    {
        $link = StorefrontNavigationLink::create($this->validatedLink($request));
        $this->audit('storefront.navigation_created', $link->id);

        return back()->with('status', 'Navigation link created.');
    }

    public function updateLink(Request $request, StorefrontNavigationLink $link): RedirectResponse
    {
        $link->update($this->validatedLink($request));
        $this->audit('storefront.navigation_updated', $link->id);

        return back()->with('status', 'Navigation link updated.');
    }

    public function destroyLink(StorefrontNavigationLink $link): RedirectResponse
    {
        $id = $link->id;
        $link->delete();
        $this->audit('storefront.navigation_deleted', $id);

        return back()->with('status', 'Navigation link deleted.');
    }

    public function storeBanner(Request $request): RedirectResponse
    {
        $validated = $this->validatedBanner($request, true);
        $path = $request->file('image')->store('storefront/slides', 'public');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['image' => 'The slide image could not be stored.']);
        }
        $validated['image_path'] = $path;
        unset($validated['image']);
        try {
            $banner = Banner::create($validated);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
        $this->audit('storefront.slide_created', $banner->id);

        return back()->with('status', 'Homepage slide created.');
    }

    public function updateBanner(Request $request, Banner $banner): RedirectResponse
    {
        $validated = $this->validatedBanner($request, false);
        $oldPath = $banner->image_path;
        $newPath = null;
        if ($request->hasFile('image')) {
            $newPath = $request->file('image')->store('storefront/slides', 'public');
            if (! is_string($newPath) || $newPath === '') {
                throw ValidationException::withMessages(['image' => 'The slide image could not be stored.']);
            }
            $validated['image_path'] = $newPath;
        }
        unset($validated['image']);
        try {
            $banner->update($validated);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath && $oldPath && str_starts_with($oldPath, 'storefront/')) {
            Storage::disk('public')->delete($oldPath);
        }
        $this->audit('storefront.slide_updated', $banner->id);

        return back()->with('status', 'Homepage slide updated.');
    }

    public function destroyBanner(Banner $banner): RedirectResponse
    {
        $id = $banner->id;
        $path = $banner->image_path;
        $banner->delete();
        if ($path && str_starts_with($path, 'storefront/')) {
            Storage::disk('public')->delete($path);
        }
        $this->audit('storefront.slide_deleted', $id);

        return back()->with('status', 'Homepage slide deleted.');
    }

    private function validatedLink(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $url = (string) $value;
                $external = filter_var($url, FILTER_VALIDATE_URL) !== false
                    && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
                $internal = preg_match('#^/(?!/)[^\x00-\x1F\x7F]*$#D', $url) === 1;
                if (! $external && ! $internal) {
                    $fail('Use an internal path beginning with / or a complete http/https URL.');
                }
            }],
            'location' => ['required', Rule::in(['header', 'footer'])],
            'column' => ['required', 'integer', 'min:1', 'max:4'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'open_new_tab' => ['required', 'boolean'],
        ]);

        return $validated;
    }

    private function validatedBanner(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'alt_text' => ['required', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }
                $url = (string) $value;
                $external = filter_var($url, FILTER_VALIDATE_URL) !== false
                    && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
                if (! $external && preg_match('#^/(?!/)[^\x00-\x1F\x7F]*$#D', $url) !== 1) {
                    $fail('Use an internal path beginning with / or a complete http/https URL.');
                }
            }],
            'button_label' => ['nullable', 'string', 'max:80'],
            'layout' => ['required', Rule::in(['image', 'split'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'image' => [$creating ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
    }

    /** @return list<string> */
    private function paragraphs(string $content): array
    {
        return collect(preg_split('/(?:\r?\n){2,}/u', trim($content)) ?: [])
            ->map(fn (string $paragraph): string => trim(preg_replace('/\s*\R\s*/u', ' ', $paragraph) ?? $paragraph))
            ->filter()->values()->all();
    }

    private function audit(string $event, int $id, array $context = []): void
    {
        Log::notice($event, [
            'admin' => hash('sha256', (string) Auth::guard('admin')->id()),
            'record' => hash('sha256', (string) $id),
            ...$context,
        ]);
    }
}
