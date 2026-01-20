<?php

namespace App\Http\Controllers;

use App\Models\RelatedProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RelatedProductController extends Controller
{
    /**
     * Add or update a related product
     */
    public function addRelatedProduct(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'related_product_id' => 'required|integer|different:product_id|exists:products,id',
            'priority' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ]);

        $existing = RelatedProduct::where('product_id', $data['product_id'])
            ->where('related_product_id', $data['related_product_id'])
            ->first();

        if ($existing) {
            if (array_key_exists('priority', $data)) {
                $existing->priority = $data['priority'];
            }

            $existing->status = array_key_exists('status', $data) ? (bool) $data['status'] : true;
            $existing->save();

            return response()->json([
                'message' => 'Related product updated successfully',
                'related_product' => $existing->load('relatedProduct')
            ], Response::HTTP_OK);
        }

        $related = RelatedProduct::create([
            'product_id' => $data['product_id'],
            'related_product_id' => $data['related_product_id'],
            'priority' => $data['priority'] ?? 0,
            'status' => array_key_exists('status', $data) ? (bool) $data['status'] : true,
        ]);

        return response()->json([
            'message' => 'Related product added successfully',
            'related_product' => $related->load('relatedProduct')
        ], Response::HTTP_CREATED);
    }

    /**
     * Get related products for a product
     */
    public function getRelatedProduct($productId)
    {
        $items = RelatedProduct::where('product_id', $productId)
            ->where('status', 1)
            ->with([
                'relatedProduct',
                'relatedProduct.primaryImage',
                'relatedProduct.brand',
                'relatedProduct.category',
                'relatedProduct.subCategory',
                'relatedProduct.shop',
            ])
            ->orderBy('priority')
            ->get();

        return response()->json([
            'count' => $items->count(),
            'items' => $items
        ], Response::HTTP_OK);
    }

    /**
     * Remove (soft) a related product by id
     */
    public function remove($id)
    {
        $item = RelatedProduct::find($id);

        if (! $item) {
            return response()->json(['message' => 'Related product not found.'], Response::HTTP_NOT_FOUND);
        }

        $item->status = false;
        $item->save();

        return response()->json(['message' => 'Related product removed.'], Response::HTTP_OK);
    }
}
