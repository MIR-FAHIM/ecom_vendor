<?php

namespace Tests\Feature;

use App\Http\Controllers\CategoryController;
use Illuminate\Http\Request;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    public function test_create_category_accepts_model_fields(): void
    {
        $request = new Request([
            'parent_id' => 0,
            'name' => 'Electronics',
            'slug' => 'electronics-' . uniqid(),
            'order_level' => 5,
            'is_active' => 1,
            'commision_rate' => 12.5,
            'banner' => 'banner.jpg',
            'icon' => 'icon.svg',
            'cover_image' => 'cover.jpg',
            'featured' => 1,
            'top' => 0,
            'digital' => 0,
            'meta_title' => 'Electronics',
            'meta_description' => 'Test category',
        ]);

        $controller = new CategoryController();
        $response = $controller->createCategory($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame('success', $response->getData(true)['status']);

        $data = $response->getData(true)['data'];
        $this->assertSame('Electronics', $data['name']);
        $this->assertSame(5, $data['order_level']);
        $this->assertSame(1, $data['is_active']);
        $this->assertSame(12.5, $data['commision_rate']);
        $this->assertSame('banner.jpg', $data['banner']);
        $this->assertSame('cover.jpg', $data['cover_image']);
        $this->assertSame('Electronics', $data['meta_title']);
    }
}
