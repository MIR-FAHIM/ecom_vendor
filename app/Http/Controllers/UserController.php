<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Shops;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
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
     * POST /users/create
     */
    public function createUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:6'],
                'user_type' => ['nullable', Rule::in(['admin', 'seller', 'customer', 'delivery_boy'])],
                'phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string', 'max:1000'],
                'avatar' => ['nullable', 'string', 'max:255'],
                'avatar_original' => ['nullable', 'string', 'max:255'],
                'country' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'postal_code' => ['nullable', 'string', 'max:20'],
              
            ]);

            $user = User::create([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'user_type' => $validated['user_type'] ?? 'customer',
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
                'avatar_original' => $validated['avatar_original'] ?? null,
                'country' => $validated['country'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
         
            ]);

            return $this->success('User created successfully', $user, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return $this->failed($firstError ?? 'Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
    public function createSeller(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:6'],
                'user_type' => ['nullable', Rule::in(['admin', 'seller', 'customer', 'delivery_boy'])],
                'phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string', 'max:1000'],
                'avatar' => ['nullable', 'string', 'max:255'],
                'avatar_original' => ['nullable', 'string', 'max:255'],
                'country' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'postal_code' => ['nullable', 'string', 'max:20'],
            ]);

            $user = User::create([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'user_type' => $validated['user_type'] ?? 'seller',
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
                'avatar_original' => $validated['avatar_original'] ?? null,
                'country' => $validated['country'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
            ]);

            // Create Shop for this seller
            Shops::create([
                'user_id' => $user->id,
                'name' => $user->name ?? 'Shop of ' . ($user->email ?? 'seller'),
                'shop_name' => $request->input('shop_name', $user->name . "'s Shop"),
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'status' => 'inactive', // Default status for new shops
                // Other fields can be filled later or left null
            ]);

            return $this->success('Seller and shop created successfully', $user, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/list?role=customer&is_banned=0&status=active&per_page=20
     */
    public function listUsers(Request $request)
    {
        try {
            $query = User::query();

            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            if ($request->filled('is_banned')) {
                $query->where('is_banned', (int) $request->is_banned);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $perPage = (int) ($request->get('per_page', 20));
            $users = $query->latest()->paginate($perPage);

            return $this->success('Users fetched successfully', $users);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/customers?per_page=20
     */
    public function getCustomers(Request $request)
    {
        try {
            $perPage = (int) ($request->get('per_page', 20));
            $customers = User::where('user_type', 'customer')->latest()->paginate($perPage);

            return $this->success('Customers fetched successfully', $customers);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/vendors?per_page=20
     */
    public function getVendors(Request $request)
    {
        try {
            $perPage = (int) ($request->get('per_page', 50));
            $vendors = User::where('user_type', 'seller')->latest()->paginate($perPage);

            return $this->success('Vendors fetched successfully', $vendors);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
    public function getDeliveryMan(Request $request)
    {
        try {
            $perPage = (int) ($request->get('per_page', 20));
            $deliveryMen = User::where('user_type', 'delivery_boy')->latest()->paginate($perPage);

            return $this->success('Delivery men fetched successfully', $deliveryMen);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/details/{id}
     */
    public function getUserDetails($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->failed('User not found', null, 404);
            }

            return $this->success('User fetched successfully', $user);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /users/update/{id}
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->failed('User not found', null, 404);
            }

            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => ['nullable', 'string', 'min:6'],
                'role' => ['nullable', Rule::in(['admin', 'vendor', 'customer'])],

                'mobile' => ['nullable', 'string', 'max:50'],
                'optional_phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string', 'max:1000'],
                'fcm_token' => ['nullable', 'string', 'max:500'],
                'status' => ['nullable', 'string', 'max:50'],

                'zone' => ['nullable', 'string', 'max:100'],
                'district' => ['nullable', 'string', 'max:100'],
                'area' => ['nullable', 'string', 'max:100'],
                'lat' => ['nullable', 'numeric'],
                'lon' => ['nullable', 'numeric'],

                'is_banned' => ['nullable', 'boolean'],
            ]);

            $user->fill([
                'name' => array_key_exists('name', $validated) ? $validated['name'] : $user->name,
                'email' => array_key_exists('email', $validated) ? $validated['email'] : $user->email,
                'role' => array_key_exists('role', $validated) ? $validated['role'] : $user->role,

                'mobile' => array_key_exists('mobile', $validated) ? $validated['mobile'] : $user->mobile,
                'optional_phone' => array_key_exists('optional_phone', $validated) ? $validated['optional_phone'] : $user->optional_phone,
                'address' => array_key_exists('address', $validated) ? $validated['address'] : $user->address,
                'fcm_token' => array_key_exists('fcm_token', $validated) ? $validated['fcm_token'] : $user->fcm_token,
                'status' => array_key_exists('status', $validated) ? $validated['status'] : $user->status,

                'zone' => array_key_exists('zone', $validated) ? $validated['zone'] : $user->zone,
                'district' => array_key_exists('district', $validated) ? $validated['district'] : $user->district,
                'area' => array_key_exists('area', $validated) ? $validated['area'] : $user->area,
                'lat' => array_key_exists('lat', $validated) ? $validated['lat'] : $user->lat,
                'lon' => array_key_exists('lon', $validated) ? $validated['lon'] : $user->lon,

                'is_banned' => array_key_exists('is_banned', $validated) ? (bool) $validated['is_banned'] : $user->is_banned,
            ]);

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            return $this->success('User updated successfully', $user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /users/ban/{id}
     */
    public function banUser($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->failed('User not found', null, 404);
            }

            $user->is_banned = true;
            $user->save();

            return $this->success('User banned successfully', $user);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /users/unban/{id}
     */
    public function unbanUser($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->failed('User not found', null, 404);
            }

            $user->is_banned = false;
            $user->save();

            return $this->success('User unbanned successfully', $user);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /users/delete/{id}
     */
    public function deleteUser($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->failed('User not found', null, 404);
            }

            $user->delete();

            return $this->success('User deleted successfully');
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /users/delete-seller/{id}
     */
    public function deleteSeller($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->failed('Seller not found', null, 404);
            }

            if ($user->user_type !== 'seller') {
                return $this->failed('This user is not a seller', null, 422);
            }

            $shopIds = Shops::where('user_id', $user->id)->pluck('id');

            $productsBySeller = Product::where('user_id', $user->id)->count();
            $productsByShop = $shopIds->isNotEmpty()
                ? Product::whereIn('shop_id', $shopIds)->count()
                : 0;

            if ($productsBySeller > 0 || $productsByShop > 0) {
                return $this->failed('Seller cannot be deleted because products are linked to this seller or seller shop', [
                    'seller_id' => (int) $user->id,
                    'shop_ids' => $shopIds->values(),
                    'products_by_seller' => $productsBySeller,
                    'products_by_shop' => $productsByShop,
                ], 409);
            }

            DB::transaction(function () use ($user) {
                Shops::where('user_id', $user->id)->delete();
                $user->delete();
            });

            return $this->success('Seller and shops deleted successfully');
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/number-exists?number=...
     * Returns whether the given phone number exists for any user.
     */
    public function numberExists(Request $request)
    {
        try {
            $validated = $request->validate([
                'number' => ['required', 'string', 'max:100'],
            ]);

            $number = $validated['number'];

            $exists = User::where('mobile', $number)
                ->orWhere('optional_phone', $number)
                ->exists();

            return $this->success('Number lookup completed', ['exists' => (bool) $exists]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failed('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->failed('Something went wrong', ['error' => $e->getMessage()], 500);
        }
    }
}
