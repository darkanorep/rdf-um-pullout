<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index()
    {
        $items = $this->categoryService->getAllCategories();

        if (empty($items->items())) {
            return $this->responseNotFound(message: 'No categories found.');
        }

        return $items instanceof LengthAwarePaginator
            ? $items->through(fn($item) => new CategoryResource($item))
            : $this->responseSuccess('Roles retrieved successfully.', CategoryResource::collection($items));
    }

    public function store(CategoryRequest $request) {
        $data = $request->validated();
        $item = $this->categoryService->storeCategory($data);

        return $this->responseCreated(
            message: 'Category created successfully.',
            data: new CategoryResource($item)
        );
    }

    public function show(int $id) {
        $item = $this->categoryService->findById($id);

        if (! $item) {
            return $this->responseNotFound(message: 'Item not found.');
        }

        return $this->responseSuccess(
            message: 'Category retrieved successfully.',
            data: new CategoryResource($item)
        );
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $data = $request->validated();

        $updatedCategory = $this->categoryService->updateCategory($data, $category);

        return $this->responseAccepted(
            message: 'Category updated successfully.',
            data: new CategoryResource($updatedCategory)
        );
    }

    public function destroy(int $id)
    {
        $this->categoryService->destroyCategory($id);

        return $this->responseAccepted(
            message: 'Category status updated successfully.'
        );
    }
}
