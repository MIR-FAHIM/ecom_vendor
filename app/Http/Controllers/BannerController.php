<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Http\Response;

class BannerController extends Controller
{
    private function success($message, $data = null, int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    private function failed($message, $errors = null, int $code = 400)
    {
        return response()->json([
            'status' => 'failed',
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    /**
     * POST /banners/add
     */
    public function addBanner(Request $request)
    {
        try {
            $validated = $request->validate([
                'banner_name' => ['required', 'string', 'max:255'],
                'title' => ['nullable', 'string', 'max:255'],
                'related_product_id' => ['nullable', 'integer', 'exists:products,id'],
                'related_category_id' => ['nullable', 'integer', 'exists:categories,id'],
                // Use an existing uploaded image by `image_id` (no file upload here)
                'image_id' => ['required_without:image_path', 'nullable', 'integer', 'exists:uploads,id'],
                'image_path' => ['nullable', 'string', 'max:255'],
                'note' => ['nullable', 'string'],
                'is_active' => ['nullable', 'boolean'],
            ]);
            $banner = Banner::create([
                'banner_name' => $validated['banner_name'],
                'title' => $validated['title'] ?? null,
                'related_product_id' => $validated['related_product_id'] ?? null,
                'related_category_id' => $validated['related_category_id'] ?? null,
                'image_path' => $validated['image_path'] ?? null,
                'image_id' => $validated['image_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            ]);

            return $this->success('Banner created successfully', $banner, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /banners/active
     */
    public function getActiveBanner()
    {
        try {
            $banners = Banner::where('is_active', 1)
                ->with(['image'])
                ->latest()
                ->get();

            return $this->success('Active banners fetched', $banners);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /banners/remove/{id}
     * Soft-remove by setting is_active = false
     */
    public function removeBanner($id)
    {
        try {
            $banner = Banner::find($id);
            if (! $banner) {
                return $this->failed('Banner not found', null, 404);
            }

            $banner->is_active = false;
            $banner->save();

            return $this->success('Banner removed successfully');
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
}
