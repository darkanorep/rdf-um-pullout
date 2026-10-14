<?php

namespace App\Services;

use App\Models\Category;

class CategoryService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly Category $category) {}

    public function getAllCategories()
    {
        return $this->category->query()->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeCategory(array $data): Category
    {
        return $this->category->create($data);
    }

    public function findById(int $id): ?Category
    {
        return $this->category->find($id);
    }

    public function updateCategory(array $data, Category $category): Category {
        $category->update($data);
        return $category;
    }

    public function destroyCategory(int $id): void
    {
        $category = $this->category->withTrashed()->find($id);
        if ($category->trashed()) {
            $category->restore();
        } else {
            $category->delete();
        }
    }
}
