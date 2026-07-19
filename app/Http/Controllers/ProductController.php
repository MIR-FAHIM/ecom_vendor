<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCreateErrorLog;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private function success($message, $data = null, int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
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
    private function getFinalSalePrice($product)
    {
        $unitPrice = is_array($product) ? ($product['unit_price'] ?? 0) : ($product->unit_price ?? 0);
        $discount = is_array($product) ? ($product['discount'] ?? 0) : ($product->discount ?? 0);
        $discountType = is_array($product) ? ($product['discount_type'] ?? null) : ($product->discount_type ?? null);
        $finalSalePrice = $unitPrice;
        if ($discount && $discountType) {
            if ($discountType === 'percent') {
                $finalSalePrice = $unitPrice - ($unitPrice * ($discount / 100));
            } elseif ($discountType === 'amount') {
                $finalSalePrice = $unitPrice - $discount;
            }
            if ($finalSalePrice < 0) {
                $finalSalePrice = 0;
            }
        }
        return round($finalSalePrice, 2);
    }

    private function logProductCreateError(Request $request, \Throwable $e, string $level = 'error', ?array $requestData = null): void
    {
        try {
            ProductCreateErrorLog::create([
                'user_id' => $request->user()?->id ?? $request->input('user_id'),
                'level' => $level,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => json_encode($requestData ?? $request->all()),
                'stack_trace' => $e->getTraceAsString(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $ignored) {
            Log::error('Failed to write product create error log', [
                'logging_error' => $ignored->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }
    }

    private function makeUniqueProductSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'product';
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function makeDuplicateProductName(string $name): string
    {
        return Str::limit($name, 193, '') . ' Copy';
    }

    private function isAdminUser($user): bool
    {
        if (!$user) {
            return false;
        }

        $role = strtolower((string) ($user->role ?? ''));
        $userType = strtolower((string) ($user->user_type ?? ''));

        return in_array('admin', [$role, $userType], true);
    }

    private function validatedBoolean(array $data, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function productCreateDatabaseError(QueryException $e): array
    {
        $message = $e->getMessage();

        if (preg_match("/Column '([^']+)' cannot be null/", $message, $matches)) {
            $field = $matches[1];
            $label = str_replace('_', ' ', $field);

            return [
                'message' => ucfirst($label) . ' is required',
                'errors' => [
                    $field => ['The ' . $label . ' field is required.'],
                ],
                'code' => 422,
            ];
        }

        if (str_contains($message, 'Duplicate entry')) {
            return [
                'message' => 'Duplicate product data',
                'errors' => [
                    'slug' => ['The product slug has already been taken.'],
                ],
                'code' => 422,
            ];
        }

        return [
            'message' => 'Product could not be created',
            'errors' => ['database' => ['A database constraint prevented product creation.']],
            'code' => 500,
        ];
    }

    /**
     * POST /products/create
     * Creates product (optionally with images array)
     */
    public function createProduct(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:200'],
                'added_by' => ['nullable', 'string', 'max:6'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'shop_id' => ['required', 'integer', 'exists:shops,id'],
                'category_id' => ['required', 'integer', 'exists:categories,id'],
                'brand_id' => ['nullable', 'integer', 'exists:brands,id'],

                // photos may be an array of upload ids or a comma-separated string
                'photos' => ['nullable'],
                'photos.*' => ['integer', 'exists:uploads,id'],
                'thumbnail_img' => ['nullable', 'integer', 'exists:uploads,id'],

                'video_provider' => ['nullable', 'string', 'max:100'],
                'video_link' => ['nullable', 'string', 'max:255'],
                'tags' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],

                'unit_price' => ['required', 'numeric', 'min:0'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'],

                'variant_product' => ['nullable', 'boolean'],
                'attributes' => ['nullable'],
                'choice_options' => ['nullable'],
                'colors' => ['nullable'],
                'variations' => ['nullable'],

                'todays_deal' => ['nullable', 'boolean'],
                'published' => ['nullable', 'boolean'],
                'approved' => ['nullable', 'boolean'],
                'stock_visibility_state' => ['nullable', 'string', 'max:50'],
                'cash_on_delivery' => ['nullable', 'boolean'],
                'featured' => ['nullable', 'boolean'],
                'seller_featured' => ['nullable', 'boolean'],

                'current_stock' => ['nullable', 'integer', 'min:0'],
                'unit' => ['nullable', 'string', 'max:50'],
                'weight' => ['nullable', 'numeric', 'min:0'],
                'min_qty' => ['nullable', 'integer'],
                'low_stock_quantity' => ['nullable', 'integer'],

                'discount' => ['nullable', 'numeric'],
                'discount_type' => ['nullable', 'string', 'max:20'],
                'discount_start_date' => ['nullable', 'integer'],
                'discount_end_date' => ['nullable', 'integer'],

                'tax' => ['nullable', 'numeric'],
                'tax_type' => ['nullable', 'string', 'max:20'],

                'shipping_type' => ['nullable', 'string', 'max:50'],
                'shipping_cost' => ['nullable', 'numeric'],

                'is_quantity_multiplied' => ['nullable', 'boolean'],
                'est_shipping_days' => ['nullable', 'integer'],

                'meta_title' => ['nullable', 'string', 'max:255'],
                'meta_description' => ['nullable', 'string', 'max:15000'],
                'meta_img' => ['nullable', 'string', 'max:255'],

                'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
                'refundable' => ['nullable', 'boolean'],
                'earn_point' => ['nullable', 'integer'],
                'rating' => ['nullable', 'numeric'],
                'barcode' => ['nullable', 'string', 'max:255'],
                'digital' => ['nullable', 'boolean'],
                'auction_product' => ['nullable', 'boolean'],
                'file_name' => ['nullable', 'string', 'max:255'],
                'file_path' => ['nullable', 'string', 'max:255'],
                'external_link' => ['nullable', 'string', 'max:255'],
                'external_link_btn' => ['nullable', 'string', 'max:255'],
                'wholesale_product' => ['nullable', 'boolean'],
                'frequently_brought_selection_type' => ['nullable', 'string', 'max:50'],
            ], [
                'name.required' => 'Product name is required.',
                'user_id.required' => 'Seller user is required.',
                'shop_id.required' => 'Shop is required.',
                'category_id.required' => 'Category is required.',
                'unit_price.required' => 'Unit price is required.',
                'weight.numeric' => 'Weight must be a valid number.',
                'weight.min' => 'Weight cannot be negative.',
            ]);

            // Normalize photos: accept array of ids or comma string
            $photos = null;
            if (array_key_exists('photos', $validated)) {
                if (is_array($validated['photos'])) {
                    $photos = implode(',', $validated['photos']);
                } else {
                    $photos = (string) $validated['photos'];
                }
            }

            $productData = [
                'name' => $validated['name'],
                'added_by' => $validated['added_by'] ?? 'admin',
                'user_id' => $validated['user_id'],
                'shop_id' => $validated['shop_id'],
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'photos' => $photos,
                'thumbnail_img' => $validated['thumbnail_img'] ?? null,
                'video_provider' => $validated['video_provider'] ?? null,
                'video_link' => $validated['video_link'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'description' => $validated['description'] ?? null,
                'unit_price' => $validated['unit_price'],
                'purchase_price' => $validated['purchase_price'] ?? null,
                'variant_product' => array_key_exists('variant_product', $validated) ? (bool) $validated['variant_product'] : false,
                'attributes' => $validated['attributes'] ?? '[]',
                'choice_options' => $validated['choice_options'] ?? null,
                'colors' => $validated['colors'] ?? null,
                'variations' => $validated['variations'] ?? null,
                'todays_deal' => array_key_exists('todays_deal', $validated) ? (bool) $validated['todays_deal'] : false,
                'published' => array_key_exists('published', $validated) ? (bool) $validated['published'] : true,
                'approved' => array_key_exists('approved', $validated) ? (bool) $validated['approved'] : true,
                'stock_visibility_state' => $validated['stock_visibility_state'] ?? 'quantity',
                'cash_on_delivery' => array_key_exists('cash_on_delivery', $validated) ? (bool) $validated['cash_on_delivery'] : false,
                'featured' => array_key_exists('featured', $validated) ? (bool) $validated['featured'] : false,
                'seller_featured' => array_key_exists('seller_featured', $validated) ? (bool) $validated['seller_featured'] : false,
                'current_stock' => $validated['current_stock'] ?? 0,
                'unit' => $validated['unit'] ?? null,
                'weight' => $validated['weight'] ?? 0,
                'min_qty' => $validated['min_qty'] ?? 1,
                'low_stock_quantity' => $validated['low_stock_quantity'] ?? null,
                'discount' => $validated['discount'] ?? null,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_start_date' => $validated['discount_start_date'] ?? null,
                'discount_end_date' => $validated['discount_end_date'] ?? null,
                'tax' => $validated['tax'] ?? null,
                'tax_type' => $validated['tax_type'] ?? null,
                'shipping_type' => $validated['shipping_type'] ?? 'flat_rate',
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'is_quantity_multiplied' => array_key_exists('is_quantity_multiplied', $validated) ? (bool) $validated['is_quantity_multiplied'] : false,
                'est_shipping_days' => $validated['est_shipping_days'] ?? null,
                'num_of_sale' => $validated['num_of_sale'] ?? 0,
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'meta_img' => $validated['meta_img'] ?? null,
                'pdf' => $validated['pdf'] ?? null,
                'slug' => $validated['slug'] ?? $this->makeUniqueProductSlug($validated['name']),
                'refundable' => array_key_exists('refundable', $validated) ? (bool) $validated['refundable'] : false,
                'earn_point' => $validated['earn_point'] ?? 0,
                'rating' => $validated['rating'] ?? 0.00,
                'barcode' => $validated['barcode'] ?? null,
                'digital' => array_key_exists('digital', $validated) ? (bool) $validated['digital'] : false,
                'auction_product' => array_key_exists('auction_product', $validated) ? (bool) $validated['auction_product'] : false,
                'file_name' => $validated['file_name'] ?? null,
                'file_path' => $validated['file_path'] ?? null,
                'external_link' => $validated['external_link'] ?? null,
                'external_link_btn' => $validated['external_link_btn'] ?? 'Buy Now',
                'wholesale_product' => array_key_exists('wholesale_product', $validated) ? (bool) $validated['wholesale_product'] : false,
                'frequently_brought_selection_type' => $validated['frequently_brought_selection_type'] ?? 'product',
            ];

            try {
                $product = Product::create($productData);

                // Auto-generate SKU: p{id}v{vendor_id}
                $product->sku = 'p' . $product->id . 'v' . ($product->shop_id ?? '0');
                $product->save();

                if (!empty($productData['thumbnail_img'])) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image' => $productData['thumbnail_img'],
                        'is_primary' => true,
                        'status' => 'active',
                    ]);
                }
            } catch (QueryException $e) {
                $this->logProductCreateError($request, $e, 'error', $productData);

                $databaseError = $this->productCreateDatabaseError($e);

                return $this->failed($databaseError['message'], $databaseError['errors'], $databaseError['code']);
            } catch (\Throwable $e) {
                $this->logProductCreateError($request, $e, 'error', $productData);

                return $this->failed('Product could not be created', ['error' => $e->getMessage()], 500);
            }

            return $this->success('Product created successfully', $product, 201);
        } catch (ValidationException $e) {
            $this->logProductCreateError($request, $e, 'validation_error', [
                'validation_errors' => $e->errors(),
                'payload' => $request->all(),
            ]);

            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            $this->logProductCreateError($request, $e, 'error', [
                'payload' => $request->all(),
            ]);

            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /products/duplicate/{id}
     * Creates an editable draft copy of an existing product.
     */
    public function duplicateProductById(Request $request, $id)
    {
        try {
            if (!$this->isAdminUser($request->attributes->get('api_user'))) {
                return $this->failed('Only admin can duplicate products', null, 403);
            }

            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:200'],
                'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
                'published' => ['nullable', 'boolean'],
                'approved' => ['nullable', 'boolean'],
                'copy_images' => ['nullable', 'boolean'],
                'copy_attributes' => ['nullable', 'boolean'],
                'copy_discount' => ['nullable', 'boolean'],
            ]);

            $sourceProduct = Product::with([
                'images',
                'productAttributes',
                'productDiscount',
            ])->find($id);

            if (!$sourceProduct) {
                return $this->failed('Product not found', null, 404);
            }

            $duplicate = DB::transaction(function () use ($sourceProduct, $validated) {
                $duplicate = $sourceProduct->replicate();
                $duplicate->name = $validated['name'] ?? $this->makeDuplicateProductName($sourceProduct->name);
                $duplicate->slug = $validated['slug'] ?? $this->makeUniqueProductSlug($duplicate->name);
                $duplicate->published = $this->validatedBoolean($validated, 'published', false);
                $duplicate->approved = $this->validatedBoolean($validated, 'approved', false);
                $duplicate->todays_deal = false;
                $duplicate->featured = false;
                $duplicate->seller_featured = false;
                $duplicate->num_of_sale = 0;
                $duplicate->rating = 0;
                $duplicate->barcode = null;

                if (Schema::hasColumn('products', 'sku')) {
                    $duplicate->sku = null;
                }

                $duplicate->save();

                if (Schema::hasColumn('products', 'sku')) {
                    $duplicate->sku = 'p' . $duplicate->id . 'v' . ($duplicate->shop_id ?? '0');
                    $duplicate->save();
                }

                if ($this->validatedBoolean($validated, 'copy_images', true)) {
                    foreach ($sourceProduct->images as $image) {
                        $duplicateImage = $image->replicate();
                        $duplicateImage->product_id = $duplicate->id;
                        $duplicateImage->save();
                    }
                }

                if ($this->validatedBoolean($validated, 'copy_attributes', true)) {
                    foreach ($sourceProduct->productAttributes as $productAttribute) {
                        $duplicateAttribute = $productAttribute->replicate();
                        $duplicateAttribute->product_id = $duplicate->id;
                        $duplicateAttribute->save();
                    }
                }

                if ($this->validatedBoolean($validated, 'copy_discount', true) && $sourceProduct->productDiscount) {
                    $duplicateDiscount = $sourceProduct->productDiscount->replicate();
                    $duplicateDiscount->product_id = $duplicate->id;
                    $duplicateDiscount->save();
                }

                return $duplicate->fresh([
                    'images.upload',
                    'primaryImage',
                    'brand',
                    'category',
                    'subCategory',
                    'shop',
                    'productAttributes.attribute',
                    'productAttributes.value',
                    'productDiscount',
                ]);
            });

            $productArr = $duplicate->toArray();
            $productArr['final_sale_price'] = $this->getFinalSalePrice($duplicate);

            return $this->success('Product duplicated successfully', [
                'source_product_id' => (int) $sourceProduct->id,
                'product' => $productArr,
            ], 201);
        } catch (ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (QueryException $e) {
            return $this->failed('Product could not be duplicated', [
                'database' => [$e->getMessage()],
            ], 500);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /products/images/upload/{productId}
     * Upload product images separately (store paths/urls, not multipart file yet)
     */
    public function productImageUpload(Request $request, $productId)
    {
        DB::beginTransaction();

        try {
            $product = Product::find($productId);
            if (!$product) {
                DB::rollBack();
                return $this->failed('Product not found', null, 404);
            }

            // 1) Validate multipart form-data files
            $validated = $request->validate([
                'images' => ['required', 'array', 'min:1'],

                // IMPORTANT: This must be a file, not a string
                'images.*.image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

                'images.*.alt_text' => ['nullable', 'string', 'max:255'],
                'images.*.sort_order' => ['nullable', 'integer'],
                'images.*.is_primary' => ['nullable'], // handle manually because form-data can be "true","false","1","0"
                'images.*.status' => ['nullable', 'string', 'max:50'],
            ]);

            $created = [];

            foreach ($validated['images'] as $img) {

                // 2) Normalize is_primary from form-data reliably
                $isPrimary = false;
                if (array_key_exists('is_primary', $img)) {
                    $isPrimary = filter_var($img['is_primary'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $isPrimary = ($isPrimary === null) ? false : $isPrimary;
                }

                // 3) If this one is primary, reset other primary flags
                if ($isPrimary) {
                    ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
                }

                // 4) Store file and save path
                // storage/app/public/products/{productId}/xxxx.webp
                $path = $img['image']->store("products/{$product->id}", 'public');

                $created[] = ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $path, // store path in DB
                    'alt_text' => $img['alt_text'] ?? null,
                    'sort_order' => $img['sort_order'] ?? null,
                    'is_primary' => $isPrimary,
                    'status' => $img['status'] ?? 'active',
                ]);
            }

            DB::commit();

            $product->load(['images', 'primaryImage']);

            return $this->success('Product images uploaded successfully', [
                'product' => $product,
                'created_images' => $created,
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /products/list
     * Filters: shop_id, category_id, sub_category_id, brand_id, status, is_active, search
     */
    public function listProducts(Request $request)
    {
        try {
            $query = Product::query()->with([
                'primaryImage',
                'images',
                'category',
                'subCategory',
                'brand',
                'productDiscount',
                'averageReview',
                'shop'
            ]);

            if ($request->filled('shop_id')) {
                $query->where('shop_id', $request->shop_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('category_id')) {
                $categoryId = (int) $request->category_id;
                $query->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereHas('category', function ($qc) use ($categoryId) {
                            $qc->where('parent_id', $categoryId);
                        });
                });
            }

            if ($request->filled('sub_category_id')) {
                $query->where('sub_category_id', $request->sub_category_id);
            }

            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (int) $request->is_active);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                // split into tokens so multi-word searches behave well
                $tokens = preg_split('/\s+/', $search);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $t = "%" . $token . "%";
                        $q->where(function ($qq) use ($t) {
                            $qq->where('name', 'like', $t)

                                ->orWhere('slug', 'like', $t)
                                ->orWhereHas('category', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                })

                                ->orWhereHas('brand', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                });
                        });
                    }
                });
            }

            $perPage = (int) $request->get('per_page', 24);
            $products = $query->where('approved', 1)->latest()->paginate($perPage);

            return $this->success('Products fetched successfully', $products, 200,);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
    public function listInactiveProducts(Request $request)
    {
        try {
            $query = Product::query()->with([
                'primaryImage',
                'images',
                'category',
                'subCategory',
                'brand',
                'productDiscount',
                'averageReview',
                'shop'
            ]);

            if ($request->filled('shop_id')) {
                $query->where('shop_id', $request->shop_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('category_id')) {
                $categoryId = (int) $request->category_id;
                $query->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereHas('category', function ($qc) use ($categoryId) {
                            $qc->where('parent_id', $categoryId);
                        });
                });
            }

            if ($request->filled('sub_category_id')) {
                $query->where('sub_category_id', $request->sub_category_id);
            }

            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (int) $request->is_active);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                // split into tokens so multi-word searches behave well
                $tokens = preg_split('/\s+/', $search);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $t = "%" . $token . "%";
                        $q->where(function ($qq) use ($t) {
                            $qq->where('name', 'like', $t)

                                ->orWhere('slug', 'like', $t)
                                ->orWhereHas('category', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                })

                                ->orWhereHas('brand', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                });
                        });
                    }
                });
            }

            $perPage = (int) $request->get('per_page', 24);
            $products = $query->where('approved', 0)->latest()->paginate($perPage);

            return $this->success('Products fetched successfully', $products, 200,);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
    public function listFeaturedProducts(Request $request)
    {
        try {
            $query = Product::query()->with([
                'primaryImage',
                'images',
                'category',
                'subCategory',
                'brand',
                'productDiscount',
                'averageReview',
                'shop'
            ]);



            if ($request->filled('featured')) {
                $query->where('featured', $request->featured);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (int) $request->is_active);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                // split into tokens so multi-word searches behave well
                $tokens = preg_split('/\s+/', $search);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $t = "%" . $token . "%";
                        $q->where(function ($qq) use ($t) {
                            $qq->where('name', 'like', $t)

                                ->orWhere('slug', 'like', $t)
                                ->orWhereHas('category', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                })

                                ->orWhereHas('brand', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                });
                        });
                    }
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $products = $query->where('approved', 1)->latest()->paginate($perPage);

            return $this->success('Products fetched successfully', $products, 200,);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
    public function listCategoryProducts(Request $request)
    {
        try {
            $query = Product::query()->with(['primaryImage', 'images', 'category', 'subCategory', 'brand', 'productDiscount', 'averageReview']);

            if ($request->filled('category_id')) {
                $categoryId = (int) $request->category_id;
                $query->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereHas('category', function ($qc) use ($categoryId) {
                            $qc->where('parent_id', $categoryId);
                        });
                });
            }



            if ($request->filled('featured')) {
                $query->where('featured', $request->featured);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (int) $request->is_active);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                // split into tokens so multi-word searches behave well
                $tokens = preg_split('/\s+/', $search);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $t = "%" . $token . "%";
                        $q->where(function ($qq) use ($t) {
                            $qq->where('name', 'like', $t)

                                ->orWhere('slug', 'like', $t)
                                ->orWhereHas('category', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                })

                                ->orWhereHas('brand', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                });
                        });
                    }
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $products = $query->where('approved', 1)->latest()->paginate($perPage);

            return $this->success('Products fetched successfully', $products, 200,);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
    public function listTodayDealProducts(Request $request)
    {
        try {
            $query = Product::query()->with(['primaryImage', 'images', 'category', 'subCategory', 'brand', 'productDiscount', 'averageReview', 'shop']);



            if ($request->filled('todays_deal')) {
                $query->where('todays_deal', $request->todays_deal);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (int) $request->is_active);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                // split into tokens so multi-word searches behave well
                $tokens = preg_split('/\s+/', $search);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $t = "%" . $token . "%";
                        $q->where(function ($qq) use ($t) {
                            $qq->where('name', 'like', $t)

                                ->orWhere('slug', 'like', $t)
                                ->orWhereHas('category', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                })

                                ->orWhereHas('brand', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                });
                        });
                    }
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $products = $query->where('approved', 1)->latest()->paginate($perPage);

            return $this->success('Products fetched successfully', $products, 200,);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /products/list/stock-out
     * Filters: shop_id, user_id, category_id, sub_category_id, brand_id, status, is_active, search
     */
    public function listStockOutProducts(Request $request)
    {
        try {
            $query = Product::query()->with(['primaryImage', 'images', 'category', 'subCategory', 'brand', 'productDiscount', 'averageReview', 'shop']);

            if ($request->filled('shop_id')) {
                $query->where('shop_id', $request->shop_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('category_id')) {
                $categoryId = (int) $request->category_id;
                $query->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereHas('category', function ($qc) use ($categoryId) {
                            $qc->where('parent_id', $categoryId);
                        });
                });
            }

            if ($request->filled('sub_category_id')) {
                $query->where('sub_category_id', $request->sub_category_id);
            }

            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (int) $request->is_active);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                // split into tokens so multi-word searches behave well
                $tokens = preg_split('/\s+/', $search);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $t = "%" . $token . "%";
                        $q->where(function ($qq) use ($t) {
                            $qq->where('name', 'like', $t)
                                ->orWhere('slug', 'like', $t)
                                ->orWhereHas('category', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                })
                                ->orWhereHas('brand', function ($qc) use ($t) {
                                    $qc->where('name', 'like', $t);
                                });
                        });
                    }
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $products = $query->where('approved', 1)
                ->where('current_stock', 0)
                ->latest()
                ->paginate($perPage);

            return $this->success('Stock-out products fetched successfully', $products, 200,);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /products/details/{id}
     */
    public function getProductDetails($id)
    {
        try {
            $product = Product::with([
                'images.upload',
                'primaryImage',
                'brand',
                'category',
                'subCategory',
                'averageReview',
                'shop',
                'related',
                'productAttributes.attribute',
                'productAttributes.value',

            ])->find($id);

            if (!$product) {
                return $this->failed('Product not found', null, 404);
            }
            $productArr = $product->toArray();
            $productArr['final_sale_price'] = $this->getFinalSalePrice($product);
            return $this->success('Product fetched successfully', $productArr);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /products/update/{id}
     */
    public function updateProduct(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->failed('Product not found', null, 404);
            }
            // Normalize photos input: accept comma string or single id and convert to array

            $validated = $request->validate([
                'name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'added_by' => ['sometimes', 'nullable', 'string', 'max:255'],
                'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
                'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
                'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],


                'thumbnail_img' => ['sometimes', 'nullable', 'integer', 'exists:uploads,id'],

                'video_provider' => ['sometimes', 'nullable', 'string', 'max:100'],
                'video_link' => ['sometimes', 'nullable', 'string', 'max:255'],
                'tags' => ['sometimes', 'nullable', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],

                'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],

                'variant_product' => ['sometimes', 'nullable', 'boolean'],
                'attributes' => ['sometimes', 'nullable'],
                'choice_options' => ['sometimes', 'nullable'],
                'colors' => ['sometimes', 'nullable'],
                'variations' => ['sometimes', 'nullable'],

                'todays_deal' => ['sometimes', 'nullable', 'boolean'],
                'published' => ['sometimes', 'nullable', 'boolean'],
                'approved' => ['sometimes', 'nullable', 'boolean'],
                'stock_visibility_state' => ['sometimes', 'nullable', 'string', 'max:50'],
                'cash_on_delivery' => ['sometimes', 'nullable', 'boolean'],
                'featured' => ['sometimes', 'nullable', 'boolean'],
                'seller_featured' => ['sometimes', 'nullable', 'boolean'],

                'current_stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
                'weight' => ['sometimes', 'nullable', 'numeric'],
                'min_qty' => ['sometimes', 'nullable', 'integer'],
                'low_stock_quantity' => ['sometimes', 'nullable', 'integer'],

                'discount' => ['sometimes', 'nullable', 'numeric'],
                'discount_type' => ['sometimes', 'nullable', 'string', 'max:20'],
                'discount_start_date' => ['sometimes', 'nullable', 'integer'],
                'discount_end_date' => ['sometimes', 'nullable', 'integer'],

                'tax' => ['sometimes', 'nullable', 'numeric'],
                'tax_type' => ['sometimes', 'nullable', 'string', 'max:20'],

                'shipping_type' => ['sometimes', 'nullable', 'string', 'max:50'],
                'shipping_cost' => ['sometimes', 'nullable', 'numeric'],

                'is_quantity_multiplied' => ['sometimes', 'nullable', 'boolean'],
                'est_shipping_days' => ['sometimes', 'nullable', 'integer'],

                'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'meta_description' => ['sometimes', 'nullable', 'string', 'max:15000'],
                'meta_img' => ['sometimes', 'nullable', 'string', 'max:255'],

                'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
                'refundable' => ['sometimes', 'nullable', 'boolean'],
                'earn_point' => ['sometimes', 'nullable', 'integer'],
                'rating' => ['sometimes', 'nullable', 'numeric'],
                'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
                'digital' => ['sometimes', 'nullable', 'boolean'],
                'auction_product' => ['sometimes', 'nullable', 'boolean'],
                'file_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'file_path' => ['sometimes', 'nullable', 'string', 'max:255'],
                'external_link' => ['sometimes', 'nullable', 'string', 'max:255'],
                'external_link_btn' => ['sometimes', 'nullable', 'string', 'max:255'],
                'wholesale_product' => ['sometimes', 'nullable', 'boolean'],
                'frequently_brought_selection_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            ]);

            if (array_key_exists('photos', $validated) && is_array($validated['photos'])) {
                $validated['photos'] = implode(',', $validated['photos']);
            }

            // Normalize boolean flags explicitly when present
            foreach (
                [
                    'variant_product',
                    'todays_deal',
                    'published',
                    'approved',
                    'cash_on_delivery',
                    'featured',
                    'seller_featured',
                    'is_quantity_multiplied',
                    'refundable',
                    'digital',
                    'auction_product',
                    'wholesale_product',
                ] as $flag
            ) {
                if (array_key_exists($flag, $validated)) {
                    $validated[$flag] = (bool) $validated[$flag];
                }
            }

            $product->fill($validated);
            $product->save();



            return $this->success('Product updated successfully', $product);
        } catch (ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /products/delete/{id}
     */
    public function deleteProduct($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->failed('Product not found', null, 404);
            }

            // Optional: delete images too (if you want cascade behavior at app layer)
            ProductImage::where('product_id', $product->id)->delete();

            $product->delete();

            return $this->success('Product deleted successfully');
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /products/images/add/{id}
     * Adds a new image to an existing product
     */
    public function addProductImage(Request $request, $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return $this->failed('Product not found', null, 404);
            }

            $validated = $request->validate([
                'image' => ['nullable', 'string', 'max:255'],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['nullable', 'integer'],
                'is_primary' => ['nullable', 'boolean'],
                'status' => ['nullable', 'string', 'max:50'],
            ]);

            // If setting as primary, unset others (optional but useful)
            if (array_key_exists('is_primary', $validated) && (bool) $validated['is_primary'] === true) {
                ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
            }

            $img = ProductImage::create([
                'product_id' => $product->id,
                'image' => $validated['image'] ?? null,
                'alt_text' => $validated['alt_text'] ?? null,
                'sort_order' => $validated['sort_order'] ?? null,
                'is_primary' => array_key_exists('is_primary', $validated) ? (bool) $validated['is_primary'] : null,
                'status' => $validated['status'] ?? null,
            ]);

            return $this->success('Product image added successfully', $img, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /products/images/delete/{imageId}
     */
    public function deleteProductImage($imageId)
    {
        try {
            $img = ProductImage::find($imageId);

            if (!$img) {
                return $this->failed('Product image not found', null, 404);
            }

            $img->delete();

            return $this->success('Product image deleted successfully');
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }



        /**
     * GET /products/seller-featured-by-product?product_id=xxx
     * Returns all products from the same shop as the given product_id where seller_featured == 1
     */
    public function getSellerFeaturedByProduct(Request $request)
    {
        $productId = $request->query('product_id');
        if (!$productId) {
            return $this->failed('product_id is required', null, 422);
        }

        $product = Product::find($productId);
        if (!$product) {
            return $this->failed('Product not found', null, 404);
        }

        $shopId = $product->shop_id;
        if (!$shopId) {
            return $this->failed('Shop not found for this product', null, 404);
        }

        $products = Product::with([
            'primaryImage',
            'images',
            'category',
            'subCategory',
            'brand',
            'productDiscount',
            'averageReview',
            'shop'
        ])
        ->where('shop_id', $shopId)
        ->where('seller_featured', 1)
        ->limit(8)
        
        ->get();

        return $this->success('Seller featured products fetched successfully', $products, 200);
    }
}
